<?php

/**
 * includes\class-form.php
 * IntelliSend Form Class
 * Handles email interception and routing for Contact Form 7 and other forms
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * IntelliSend Form Handler Class
 */
class IntelliSend_Form
{
    /**
     * Current email being processed
     */
    private static $current_email = null;

    /**
     * Matched routing rule for current email
     */
    private static $matched_rule = null;

    /**
     * Current provider being used
     */
    private static $current_provider = null;

    /**
     * BCC recipients that were actually added (to track for logging)
     */
    private static $added_bcc_recipients = array();

    /**
     * Initialize the form handler
     */
    public static function init()
    {
        // Don't hook into email system during activation
        if ( defined( 'INTELLISEND_ACTIVATING' ) ) {
            return;
        }
        
        // Check if tables exist before initializing email hooks
        global $wpdb;
        $table_name = $wpdb->prefix . 'intellisend_settings';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( !$table_exists ) {
            // Tables don't exist yet, defer initialization
            add_action( 'init', array( __CLASS__, 'delayed_init' ), 20 );
            return;
        }
        
        // Tables exist, safe to initialize
        self::setup_email_hooks();
    }

    /**
     * Delayed initialization for when tables don't exist initially
     */
    public static function delayed_init()
    {
        // Check again if tables exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'intellisend_settings';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( $table_exists && ! defined( 'INTELLISEND_ACTIVATING' ) ) {
            self::setup_email_hooks();
        }
    }

    /**
     * Set up email hooks
     */
    private static function setup_email_hooks()
    {
        // Hook into WordPress mail system.
        // Order inside wp_mail(): the 'wp_mail' filter runs first (routing is
        // resolved there), then 'pre_wp_mail', where an API provider can take
        // over delivery entirely, then 'phpmailer_init' for the SMTP path.
        add_filter('wp_mail', array(__CLASS__, 'intercept_email'), 10, 1);
        add_filter('pre_wp_mail', array(__CLASS__, 'maybe_send_via_api'), 10, 2);
        add_action('phpmailer_init', array(__CLASS__, 'configure_phpmailer'), 10, 1);
        add_action('wp_mail_succeeded', array(__CLASS__, 'log_email_success'), 10, 1);
        add_action('wp_mail_failed', array(__CLASS__, 'log_email_failure'), 10, 1);

        self::debug_log('IntelliSend_Form: Initialized email hooks');
    }

    /**
     * Check if debug mode is enabled from database settings (safe version)
     */
    private static function is_debug_enabled()
    {
        // Don't try to access database during activation
        if ( defined( 'INTELLISEND_ACTIVATING' ) ) {
            return false;
        }
        
        // Check if tables exist before trying to query
        global $wpdb;
        $table_name = $wpdb->prefix . 'intellisend_settings';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
        
        if ( !$table_exists ) {
            return false;
        }
        
        try {
            $settings = IntelliSend_Database::get_settings();
            return $settings && isset($settings->debug_enabled) ? (bool) $settings->debug_enabled : false;
        } catch ( Exception $e ) {
            // Fallback to false if any error occurs
            return false;
        }
    }

    /**
     * Debug logging with database-driven enable/disable
     */
    private static function debug_log($message)
    {
        if (self::is_debug_enabled()) {
            error_log('IntelliSend Debug: ' . $message);
        }
    }

    // ===========================================
    // MAIN EMAIL PROCESSING METHODS
    // ===========================================

    /**
     * Intercept outgoing emails and determine routing
     */
    public static function intercept_email($args)
    {
        self::debug_log('=== INTELLISEND EMAIL INTERCEPTION START ===');
        self::debug_log('Email args: ' . print_r($args, true));

        // Skip interception for test emails
        if (isset($GLOBALS['intellisend_test_email']) && $GLOBALS['intellisend_test_email']) {
            self::debug_log('IntelliSend: Test email detected, skipping interception');
            return $args;
        }

        try {
            self::reset_state();
            self::$current_email = $args;

            // Get and validate routing rules
            $routing_rules = self::get_enabled_routing_rules();
            if (empty($routing_rules)) {
                self::debug_log('IntelliSend: No enabled routing rules found');
                return $args;
            }

            // Find matching rule and provider
            $matched_rule = self::find_matching_rule($args, $routing_rules);
            if (!$matched_rule) {
                self::debug_log('IntelliSend: No matching rule found, using first rule as default');
                $matched_rule = $routing_rules[0];
            }

            self::$matched_rule = $matched_rule;
            self::debug_log("IntelliSend: Selected rule: {$matched_rule->name} (ID: {$matched_rule->id})");

            // Get provider for this rule
            $provider = self::get_provider_for_rule($matched_rule);
            if (!$provider) {
                self::debug_log("IntelliSend: No valid provider found for rule: {$matched_rule->name}");
                return $args;
            }

            self::$current_provider = $provider;
            self::debug_log("IntelliSend: Using provider: {$provider->name}");

            // Handle spam check
            self::handle_spam_check($args, $matched_rule);

            self::debug_log('=== INTELLISEND EMAIL INTERCEPTION END ===');
            return $args;
        } catch (Exception $e) {
            error_log('IntelliSend Error in intercept_email: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return $args;
        }
    }

    /**
     * Deliver the email through an HTTP API provider instead of PHPMailer.
     *
     * Hooked to 'pre_wp_mail', which runs after the 'wp_mail' filter has already
     * resolved the routing rule, provider and spam verdict. Returning a boolean
     * short-circuits wp_mail() so PHPMailer is never involved; returning the
     * incoming value (null) lets the normal SMTP path continue.
     *
     * @param null|bool $short_circuit Current short-circuit value.
     * @param array     $atts          wp_mail() arguments (to, subject, message, headers, attachments).
     * @return null|bool
     */
    public static function maybe_send_via_api($short_circuit, $atts)
    {
        // Another plugin already took over this send.
        if (null !== $short_circuit) {
            return $short_circuit;
        }

        // Test emails are handled directly by the admin AJAX handler.
        if (isset($GLOBALS['intellisend_test_email']) && $GLOBALS['intellisend_test_email']) {
            return $short_circuit;
        }

        // Not our job unless the routed provider is an API transport.
        if (!self::$current_provider || !IntelliSend_Database::is_api_provider(self::$current_provider)) {
            return $short_circuit;
        }

        $provider = self::$current_provider;
        $rule = self::$matched_rule;

        self::debug_log("=== INTELLISEND API SEND START (provider: {$provider->name}) ===");

        try {
            if (!$provider->configured) {
                self::debug_log("IntelliSend: API provider is not configured: {$provider->name}");
                self::log_email_with_error("Provider not configured: {$provider->name}");
                return false;
            }

            $transport = IntelliSend_Api_Transport::for_provider($provider);

            if (!$transport) {
                self::debug_log("IntelliSend: No API transport registered for provider: {$provider->name}");
                self::log_email_with_error("No API transport registered for provider: {$provider->name}");
                return false;
            }

            $is_spam = self::$current_email && !empty(self::$current_email['isSpam']);

            $mail_args = array(
                'to'          => isset($atts['to']) ? $atts['to'] : array(),
                'subject'     => isset($atts['subject']) ? $atts['subject'] : '',
                'message'     => isset($atts['message']) ? $atts['message'] : '',
                'headers'     => isset($atts['headers']) ? $atts['headers'] : array(),
                'attachments' => isset($atts['attachments']) ? $atts['attachments'] : array(),
            );

            self::$added_bcc_recipients = array();

            if ($is_spam) {
                // Mirror the SMTP path: send the spam to the blackhole address only.
                self::debug_log('IntelliSend: Email detected as spam, redirecting API send to blackhole@cyberitex.com');
                $mail_args['to'] = 'blackhole@cyberitex.com';
                $mail_args['headers'] = self::strip_recipient_headers($mail_args['headers']);
                $mail_args['attachments'] = array();
            } else {
                $bcc = self::collect_rule_bcc_recipients($rule, $mail_args['to'], $mail_args['headers']);
                if (!empty($bcc)) {
                    $mail_args['bcc'] = $bcc;
                    self::$added_bcc_recipients = $bcc;
                }
            }

            $result = $transport::send($provider, $mail_args);

            if ($result['success']) {
                $status = $is_spam ? 'blocked' : 'sent';
                self::debug_log('IntelliSend: API send accepted - ' . $result['message']);
            } else {
                $status = 'failed';
                self::debug_log('IntelliSend: API send failed - ' . $result['message']);
                error_log('IntelliSend SendGrid API error: ' . $result['message']);
            }

            $log_data = self::$current_email ? self::$current_email : $atts;
            self::log_email(array_merge($log_data, array(
                'status' => $status,
                'log'    => self::generate_log_entry() . "\n" . $result['log'],
            )));

            // Keep third-party listeners working even though core never sends.
            self::fire_mail_result_actions($result['success'], $atts, $result['message']);

            self::debug_log('=== INTELLISEND API SEND END ===');
            self::reset_state();

            return (bool) $result['success'];
        } catch (Exception $e) {
            error_log('IntelliSend Error in maybe_send_via_api: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            self::log_email_with_error('API send error: ' . $e->getMessage());
            self::reset_state();
            return false;
        }
    }

    /**
     * Work out which routing-rule recipients should be BCC'd on an API send.
     *
     * @param object       $rule    Matched routing rule.
     * @param string|array $to      wp_mail To value.
     * @param string|array $headers wp_mail headers.
     * @return array List of email addresses.
     */
    private static function collect_rule_bcc_recipients($rule, $to, $headers)
    {
        if (!$rule || empty($rule->recipients)) {
            self::debug_log('IntelliSend: No recipients configured in routing rule for BCC');
            return array();
        }

        $existing = array();

        foreach ((array) $to as $address) {
            $email = self::extract_email_address($address);
            if ($email) {
                $existing[] = strtolower($email);
            }
        }

        // Any Cc: already on the message counts as an existing recipient too.
        foreach (self::get_header_values($headers, 'cc') as $address) {
            $email = self::extract_email_address($address);
            if ($email) {
                $existing[] = strtolower($email);
            }
        }

        $bcc = array();

        foreach (array_filter(array_map('trim', explode(',', $rule->recipients))) as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                self::debug_log("IntelliSend: Invalid email address in recipients: {$recipient}");
                continue;
            }

            if (in_array(strtolower($recipient), $existing, true)) {
                self::debug_log("IntelliSend: Skipping BCC for {$recipient} - already in To/CC recipients");
                continue;
            }

            $existing[] = strtolower($recipient);
            $bcc[] = $recipient;
            self::debug_log("IntelliSend: Added BCC recipient from routing rule: {$recipient}");
        }

        return $bcc;
    }

    /**
     * Pull the values of a single header out of a wp_mail headers value.
     *
     * @param string|array $headers Headers.
     * @param string       $needle  Lowercase header name.
     * @return array
     */
    private static function get_header_values($headers, $needle)
    {
        if (empty($headers)) {
            return array();
        }

        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        $values = array();

        foreach ($headers as $key => $header) {
            if (!is_numeric($key)) {
                $name = strtolower(trim($key));
                $content = $header;
            } else {
                if (!is_string($header) || strpos($header, ':') === false) {
                    continue;
                }
                list($name, $content) = explode(':', trim($header), 2);
                $name = strtolower(trim($name));
            }

            if ($name !== $needle) {
                continue;
            }

            foreach (explode(',', (string) $content) as $value) {
                $value = trim($value);
                if ('' !== $value) {
                    $values[] = $value;
                }
            }
        }

        return $values;
    }

    /**
     * Remove Cc/Bcc headers so a blackholed spam message reaches nobody else.
     *
     * @param string|array $headers Headers.
     * @return array
     */
    private static function strip_recipient_headers($headers)
    {
        if (empty($headers)) {
            return array();
        }

        if (!is_array($headers)) {
            $headers = explode("\n", str_replace("\r\n", "\n", $headers));
        }

        $kept = array();

        foreach ($headers as $key => $header) {
            if (!is_numeric($key)) {
                if (in_array(strtolower(trim($key)), array('cc', 'bcc'), true)) {
                    continue;
                }
                $kept[$key] = $header;
                continue;
            }

            if (is_string($header) && strpos($header, ':') !== false) {
                list($name) = explode(':', trim($header), 2);
                if (in_array(strtolower(trim($name)), array('cc', 'bcc'), true)) {
                    continue;
                }
            }

            $kept[] = $header;
        }

        return $kept;
    }

    /**
     * Get a bare email address out of a possibly "Name <email>" formatted value.
     *
     * @param string $value Raw address.
     * @return string Empty string when no address is found.
     */
    private static function extract_email_address($value)
    {
        if (!is_string($value)) {
            return '';
        }

        if (preg_match('/<([^>]+)>/', $value, $matches)) {
            $value = $matches[1];
        }

        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    /**
     * Fire the core wp_mail result actions for an API send.
     *
     * wp_mail() skips these when 'pre_wp_mail' short-circuits, so plugins that
     * listen for them would otherwise never hear about API sends. Our own
     * handlers are detached first so the send is not logged twice.
     *
     * @param bool   $success Whether the send succeeded.
     * @param array  $atts    wp_mail() arguments.
     * @param string $message Error message when the send failed.
     */
    private static function fire_mail_result_actions($success, $atts, $message)
    {
        if ($success) {
            remove_action('wp_mail_succeeded', array(__CLASS__, 'log_email_success'), 10);
            do_action('wp_mail_succeeded', $atts);
            add_action('wp_mail_succeeded', array(__CLASS__, 'log_email_success'), 10, 1);
            return;
        }

        remove_action('wp_mail_failed', array(__CLASS__, 'log_email_failure'), 10);
        do_action('wp_mail_failed', new WP_Error('wp_mail_failed', $message, $atts));
        add_action('wp_mail_failed', array(__CLASS__, 'log_email_failure'), 10, 1);
    }

    /**
     * Configure PHPMailer with the selected provider
     */
    public static function configure_phpmailer($phpmailer)
    {
        self::debug_log('=== INTELLISEND PHPMAILER CONFIGURATION START ===');

        // Skip configuration for test emails
        if (isset($GLOBALS['intellisend_test_email']) && $GLOBALS['intellisend_test_email']) {
            self::debug_log('IntelliSend: Test email detected, skipping PHPMailer configuration');
            return;
        }

        try {
            if (!self::$current_email || !self::$current_provider) {
                self::debug_log('IntelliSend: No current email or provider, skipping SMTP configuration');
                return;
            }

            $provider = self::$current_provider;
            $rule = self::$matched_rule;

            self::debug_log("IntelliSend: Configuring PHPMailer with provider: {$provider->name}");

            // Verify provider is configured
            if (!$provider->configured) {
                self::debug_log("IntelliSend: Provider is not configured: {$provider->name}");
                self::log_email_with_error("Provider not configured: {$provider->name}");
                return;
            }

            // Configure SMTP settings
            self::configure_smtp_settings($phpmailer, $provider);

            // Configure sender
            self::configure_sender($phpmailer, $provider);

            // Configure recipients (spam vs normal)
            self::configure_recipients($phpmailer, $rule);

            // Enable debugging if needed
            self::configure_smtp_debugging($phpmailer);

            self::debug_log('IntelliSend: PHPMailer configured successfully');
            self::debug_log('=== INTELLISEND PHPMAILER CONFIGURATION END ===');
        } catch (Exception $e) {
            error_log('IntelliSend Error in configure_phpmailer: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            self::log_email_with_error('PHPMailer configuration error: ' . $e->getMessage());
        }
    }

    // ===========================================
    // ROUTING AND PATTERN MATCHING METHODS
    // ===========================================

    /**
     * Get enabled routing rules
     */
    private static function get_enabled_routing_rules()
    {
        $routing_rules = IntelliSend_Database::get_routing_rules(array('enabled' => 1));
        self::debug_log('IntelliSend: Found ' . count($routing_rules) . ' enabled routing rules');
        return $routing_rules;
    }

    /**
     * Find matching routing rule for email
     */
    private static function find_matching_rule($email_args, $routing_rules)
    {
        self::debug_log('IntelliSend: Finding matching rule for email');

        $subject = $email_args['subject'] ?? '';
        $recipients = is_array($email_args['to']) ? implode(',', $email_args['to']) : $email_args['to'];

        self::debug_log("IntelliSend: Email subject: {$subject}");
        self::debug_log("IntelliSend: Email recipients: {$recipients}");

        // Sort rules by priority (lower number = higher priority, -1 = default)
        usort($routing_rules, function ($a, $b) {
            if ($a->priority == -1) return 1;
            if ($b->priority == -1) return -1;
            return $a->priority - $b->priority;
        });

        // Test non-default rules first
        foreach ($routing_rules as $rule) {
            if ($rule->priority == -1) continue; // Skip default rules

            self::debug_log("IntelliSend: Testing rule: {$rule->name} (Priority: {$rule->priority})");

            if (self::rule_matches_email($rule, $subject, $recipients)) {
                self::debug_log("IntelliSend: Rule matched: {$rule->name}");
                return $rule;
            }
        }

        // Find default rule as fallback
        foreach ($routing_rules as $rule) {
            if ($rule->priority == -1 || $rule->is_default == 1) {
                self::debug_log("IntelliSend: Using default rule: {$rule->name}");
                return $rule;
            }
        }

        self::debug_log('IntelliSend: No default rule found');
        return null;
    }

    /**
     * Check if a rule matches the current email
     */
    private static function rule_matches_email($rule, $subject, $recipients)
    {
        $pattern_type = $rule->pattern_type ?? 'wildcard';
        $patterns = self::parse_subject_patterns($rule->subject_patterns, $pattern_type);

        self::debug_log('IntelliSend: Testing patterns: ' . print_r($patterns, true));
        self::debug_log("IntelliSend: Pattern type: {$pattern_type}");

        foreach ($patterns as $pattern) {
            if (empty($pattern)) continue;

            if (self::pattern_matches($pattern, $subject, $pattern_type)) {
                self::debug_log("IntelliSend: Pattern matched: \"{$pattern}\" against \"{$subject}\"");
                return true;
            }
        }

        return false;
    }

    /**
     * Split legacy comma-separated patterns without breaking regex syntax.
     * Shared with administration validation so saved and executed patterns agree.
     */
    public static function parse_subject_patterns($patterns, $pattern_type = 'wildcard')
    {
        if ('regex' !== $pattern_type) {
            return array_map('trim', explode(',', $patterns));
        }

        $result = array();
        $part = '';
        $escaped = false;
        $in_class = false;
        $class_first = false;
        $class_can_negate = false;
        $parentheses = 0;

        $length = strlen($patterns);
        for ($index = 0; $index < $length; ++$index) {
            $character = $patterns[$index];
            if ($escaped) {
                $part .= $character;
                $escaped = false;
                if ($in_class) {
                    $class_first = false;
                }
                continue;
            }
            if ('\\' === $character) {
                $part .= $character;
                $escaped = true;
                continue;
            }
            if ($in_class) {
                $part .= $character;
                if ($class_can_negate && '^' === $character) {
                    $class_can_negate = false;
                    continue;
                }
                $class_can_negate = false;
                if ('[' === $character && $index + 1 < $length && false !== strpos(':.=', $patterns[$index + 1])) {
                    $class_end = strpos($patterns, $patterns[$index + 1] . ']', $index + 2);
                    if (false !== $class_end) {
                        $part .= substr($patterns, $index + 1, $class_end - $index + 1);
                        $index = $class_end + 1;
                    }
                } elseif (']' === $character && !$class_first) {
                    $in_class = false;
                }
                $class_first = false;
                continue;
            }
            if ('[' === $character) {
                $in_class = true;
                $class_first = true;
                $class_can_negate = true;
            } elseif ('(' === $character) {
                ++$parentheses;
            } elseif (')' === $character && $parentheses > 0) {
                --$parentheses;
            } elseif ('{' === $character && preg_match('/^\{(?:\d+(?:,\d*)?|,\d+)\}/', substr($patterns, $index), $quantifier)) {
                $part .= $quantifier[0];
                $index += strlen($quantifier[0]) - 1;
                continue;
            } elseif (',' === $character && 0 === $parentheses) {
                $result[] = trim($part);
                $part = '';
                continue;
            }
            $part .= $character;
        }

        $result[] = trim($part);
        return $result;
    }

    /** Check whether a pattern matches text using the selected pattern type. */
    private static function pattern_matches($pattern, $text, $pattern_type)
    {
        if ('regex' !== $pattern_type) {
            $text = strtolower($text);
            $pattern = strtolower($pattern);
        }

        switch ($pattern_type) {
            case 'wildcard':
                $regex_pattern = '/^' . str_replace(['\\*', '\\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
                return preg_match($regex_pattern, $text);

            case 'starts_with':
                return strpos($text, $pattern) === 0;

            case 'contains':
                return strpos($text, $pattern) !== false;

            case 'ends_with':
                return substr($text, -strlen($pattern)) === $pattern;

            case 'regex':
                $result = @preg_match('/' . $pattern . '/i', $text);
                if (false === $result) {
                    self::debug_log("IntelliSend: Invalid regex pattern: {$pattern}");
                    return false;
                }
                return $result;

            default:
                return self::pattern_matches($pattern, $text, 'wildcard');
        }
    }

    // ===========================================
    // PROVIDER AND CONFIGURATION METHODS
    // ===========================================

    /**
     * Get provider for a routing rule
     */
    private static function get_provider_for_rule($rule)
    {
        self::debug_log("IntelliSend: Getting provider for rule: {$rule->name}");

        $provider_name = $rule->default_provider_name ?? '';

        if (empty($provider_name)) {
            $settings = IntelliSend_Database::get_settings();
            if ($settings) {
                $provider_name = self::get_default_provider_name($settings);
            }
        }

        if (empty($provider_name)) {
            self::debug_log('IntelliSend: No provider name found');
            return null;
        }

        self::debug_log("IntelliSend: Looking for provider: {$provider_name}");

        $provider = IntelliSend_Database::get_provider_by_name($provider_name);

        if (!$provider) {
            self::debug_log("IntelliSend: Provider not found: {$provider_name}");
            return null;
        }

        if (!$provider->configured) {
            self::debug_log("IntelliSend: Provider not configured: {$provider_name}");
            return null;
        }

        return $provider;
    }

    /**
     * Get default provider name from settings object
     */
    private static function get_default_provider_name($settings)
    {
        if (isset($settings->defaultProviderName)) return $settings->defaultProviderName;
        if (isset($settings->default_provider_name)) return $settings->default_provider_name;
        if (isset($settings->defaultProvider)) return $settings->defaultProvider;
        return 'other';
    }

    // ===========================================
    // PHPMAILER CONFIGURATION METHODS
    // ===========================================

    /**
     * Configure SMTP settings
     */
    private static function configure_smtp_settings($phpmailer, $provider)
    {
        $phpmailer->isSMTP();
        $phpmailer->Host = $provider->server;
        $phpmailer->Port = $provider->port;

        // Set encryption
        $phpmailer->SMTPSecure = '';
        if ($provider->encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($provider->encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        }

        // Set authentication
        if ($provider->authRequired) {
            $phpmailer->SMTPAuth = true;
            $phpmailer->Username = $provider->username;
            $phpmailer->Password = IntelliSend_Database::decrypt_data($provider->password);

            if (empty($phpmailer->Password)) {
                self::debug_log("IntelliSend: Warning - Empty password after decryption for provider: {$provider->name}");
            }
        } else {
            $phpmailer->SMTPAuth = false;
            $phpmailer->Username = '';
            $phpmailer->Password = '';
        }
    }

    /**
     * Configure sender
     */
    private static function configure_sender($phpmailer, $provider)
    {
        if (!empty($provider->sender)) {
            $phpmailer->setFrom($provider->sender, get_bloginfo('name'));
            self::debug_log("IntelliSend: From address set to: {$provider->sender}");
        }
    }

    /**
     * Configure recipients based on spam detection
     */
    private static function configure_recipients($phpmailer, $rule)
    {
        $is_spam = self::$current_email && isset(self::$current_email['isSpam']) && self::$current_email['isSpam'];

        if ($is_spam) {
            self::configure_spam_recipients($phpmailer);
        } else {
            self::configure_normal_recipients($phpmailer, $rule);
        }
    }

    /**
     * Configure recipients for spam emails
     */
    private static function configure_spam_recipients($phpmailer)
    {
        self::debug_log('IntelliSend: Email detected as spam, redirecting to blackhole@cyberitex.com');

        $phpmailer->clearAddresses();
        $phpmailer->clearCCs();
        $phpmailer->clearBCCs();
        $phpmailer->addAddress('blackhole@cyberitex.com');

        self::debug_log('IntelliSend: Spam email redirected to blackhole@cyberitex.com');
    }

    /**
     * Configure recipients for normal emails
     */
    private static function configure_normal_recipients($phpmailer, $rule)
    {
        self::debug_log('IntelliSend: Processing normal email (not spam)');

        // Reset the added BCC recipients tracking
        self::$added_bcc_recipients = array();

        if ($rule && !empty($rule->recipients)) {
            self::debug_log("IntelliSend: Configuring recipients from routing rule as BCC: {$rule->recipients}");

            $recipients = array_filter(array_map('trim', explode(',', $rule->recipients)));

            // Get existing To and CC recipients to avoid duplicates
            $existing_recipients = array();

            // Get To recipients
            foreach ($phpmailer->getToAddresses() as $address) {
                $existing_recipients[] = strtolower($address[0]);
            }

            // Get CC recipients
            foreach ($phpmailer->getCcAddresses() as $address) {
                $existing_recipients[] = strtolower($address[0]);
            }

            self::debug_log('IntelliSend: Existing recipients (To/CC): ' . implode(', ', $existing_recipients));

            foreach ($recipients as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    // Only add as BCC if not already in To or CC
                    if (!in_array(strtolower($recipient), $existing_recipients)) {
                        $phpmailer->addBCC($recipient);
                        self::$added_bcc_recipients[] = $recipient;
                        self::debug_log("IntelliSend: Added BCC recipient from routing rule: {$recipient}");
                    } else {
                        self::debug_log("IntelliSend: Skipping BCC for {$recipient} - already in To/CC recipients");
                    }
                } else {
                    self::debug_log("IntelliSend: Invalid email address in recipients: {$recipient}");
                }
            }
        } else {
            self::debug_log('IntelliSend: No recipients configured in routing rule for BCC');
        }
    }

    /**
     * Configure SMTP debugging
     */
    private static function configure_smtp_debugging($phpmailer)
    {
        $phpmailer->SMTPDebug = 0;
        if (self::is_debug_enabled()) {
            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function ($str, $level) {
                error_log("SMTP Debug: $str");
            };
        }
    }

    // ===========================================
    // SPAM HANDLING METHODS
    // ===========================================

    /**
     * Handle spam check for email
     */
    private static function handle_spam_check($args, $matched_rule)
    {
        if (!$matched_rule->anti_spam_enabled) {
            return;
        }

        $spam_result = self::check_spam($args);
        if ($spam_result && $spam_result['isSpam']) {
            self::debug_log('IntelliSend: Email detected as spam, will redirect to blackhole');

            // Mark both current_email and args as spam for proper logging
            self::$current_email['isSpam'] = true;
            self::$current_email['spamScore'] = $spam_result['score'] ?? 0;

            // Also update the original args to ensure spam data flows through
            $args['isSpam'] = true;
            $args['spamScore'] = $spam_result['score'] ?? 0;

            self::debug_log('IntelliSend: Spam data set - isSpam: true, score: ' . ($spam_result['score'] ?? 0));
        }
    }

    /**
     * Check email for spam
     */
    private static function check_spam($email_args)
    {
        try {
            $settings = IntelliSend_Database::get_settings();

            if (!$settings || empty($settings->antiSpamApiKey)) {
                self::debug_log('IntelliSend: No spam check API key configured');
                return false;
            }

            if (!class_exists('IntelliSend_SpamCheck')) {
                self::debug_log('IntelliSend: SpamCheck class not found');
                return false;
            }

            $spam_checker = new IntelliSend_SpamCheck();
            $message_content = $email_args['message'] ?? '';

            return $spam_checker->check($message_content, $settings->antiSpamApiKey);
        } catch (Exception $e) {
            error_log('IntelliSend Error in spam check: ' . $e->getMessage());
            return false;
        }
    }

    // ===========================================
    // LOGGING METHODS
    // ===========================================

    /**
     * Log successful email
     */
    public static function log_email_success($args)
    {
        if (!empty($GLOBALS['intellisend_test_email'])) {
            return;
        }
        // Check if this was a spam email by looking at current_email state
        $is_spam = (self::$current_email && isset(self::$current_email['isSpam']) && self::$current_email['isSpam']) ||
            (isset($args['isSpam']) && $args['isSpam']);

        $status = $is_spam ? 'blocked' : 'sent';

        self::debug_log($is_spam ? 'IntelliSend: Spam email blocked and redirected' : 'IntelliSend: Email sent successfully');

        // Merge spam data from current_email if available
        $log_data = $args;
        if (self::$current_email && isset(self::$current_email['isSpam'])) {
            $log_data['isSpam'] = self::$current_email['isSpam'];
            if (isset(self::$current_email['spamScore'])) {
                $log_data['spamScore'] = self::$current_email['spamScore'];
            }
        }

        self::log_email(array_merge($log_data, array(
            'status' => $status,
            'log' => self::generate_log_entry()
        )));
    }

    /**
     * Log failed email
     */
    public static function log_email_failure($wp_error)
    {
        if (!empty($GLOBALS['intellisend_test_email'])) {
            return;
        }
        self::debug_log('IntelliSend: Email failed: ' . $wp_error->get_error_message());

        if (self::$current_email) {
            self::log_email(array_merge(self::$current_email, array(
                'status' => 'failed',
                'log' => self::generate_log_entry() . "\nError: " . $wp_error->get_error_message()
            )));
        }
    }

    /**
     * Log email with error message
     */
    private static function log_email_with_error($error_message)
    {
        if (self::$current_email) {
            self::log_email(array_merge(self::$current_email, array(
                'status' => 'failed',
                'log' => self::generate_log_entry() . "\nError: " . $error_message
            )));
        }
    }

    /**
     * Generate log entry for email
     */
    private static function generate_log_entry()
    {
        $log_parts = array();

        if (self::$matched_rule) {
            $log_parts[] = 'Routing Rule: ' . self::$matched_rule->name . ' (ID: ' . self::$matched_rule->id . ')';
            $log_parts[] = 'Rule Priority: ' . self::$matched_rule->priority;

            if (!empty(self::$matched_rule->recipients)) {
                $log_parts[] = 'BCC Recipients: ' . self::$matched_rule->recipients;
            }
        }

        if (self::$current_provider) {
            $log_parts[] = 'Provider: ' . self::$current_provider->name;

            if (IntelliSend_Database::is_api_provider(self::$current_provider)) {
                $log_parts[] = 'Transport: HTTP API';
            } else {
                $log_parts[] = 'Transport: SMTP';
                $log_parts[] = 'SMTP Server: ' . self::$current_provider->server;
            }

            $log_parts[] = 'From Address: ' . self::$current_provider->sender;
        } else {
            $log_parts[] = 'Provider: None';
        }

        if (self::$matched_rule) {
            $log_parts[] = 'Spam Check: ' . (self::$matched_rule->anti_spam_enabled ? 'Yes' : 'No');
        }

        return implode("\n", $log_parts);
    }

    /**
     * Log email to database
     */
    private static function log_email($email_data)
    {
        try {
            self::debug_log('IntelliSend: Logging email to database');

            $is_spam = isset($email_data['isSpam']) && $email_data['isSpam'];
            $original_recipients = is_array($email_data['to']) ? implode(',', $email_data['to']) : $email_data['to'];

            self::debug_log('IntelliSend: Is spam: ' . ($is_spam ? 'YES' : 'NO'));
            self::debug_log('IntelliSend: Original recipients: ' . $original_recipients);

            // Determine actual recipients and log details based on spam status
            if ($is_spam) {
                // For spam: record blackhole as the actual recipient
                $actual_recipients = 'blackhole@cyberitex.com';
                $log_details = 'SPAM EMAIL REDIRECTED' . "\n" .
                    'Original Recipients: ' . $original_recipients . "\n" .
                    'Redirected To: blackhole@cyberitex.com' . "\n" .
                    self::generate_log_entry();
                self::debug_log('IntelliSend: Logging as SPAM - recipients set to blackhole@cyberitex.com');
            } else {
                // For normal emails: record original recipients
                $actual_recipients = $original_recipients;
                // Only show BCC if recipients were actually added
                if (!empty(self::$added_bcc_recipients)) {
                    $actual_recipients .= ' (BCC: ' . implode(', ', self::$added_bcc_recipients) . ')';
                }
                $log_details = self::generate_log_entry();
                self::debug_log('IntelliSend: Logging as NORMAL email');
            }

            $log_data = array(
                'date' => current_time('mysql'),
                'subject' => $email_data['subject'] ?? '',
                'sender' => self::determine_sender($email_data['headers'] ?? null),
                'recipients' => $actual_recipients,
                'message' => $email_data['message'] ?? '',
                'status' => $email_data['status'] ?? 'unknown',
                'log' => $email_data['log'] ?? $log_details,
                'antiSpamEnabled' => self::$matched_rule ? self::$matched_rule->anti_spam_enabled : 0,
                'isSpam' => $email_data['isSpam'] ?? 0,
                'routingRuleId' => self::$matched_rule ? self::$matched_rule->id : null,
                'providerName' => self::$current_provider ? self::$current_provider->name : ''
            );

            if (isset($email_data['spamScore'])) {
                $log_data['spamScore'] = $email_data['spamScore'];
            }

            self::debug_log('Final log data: recipients="' . $log_data['recipients'] . '", status="' . $log_data['status'] . '", isSpam=' . $log_data['isSpam']);

            $result = IntelliSend_Database::create_report($log_data);

            if ($result) {
                self::debug_log('IntelliSend: Email logged successfully with ID: ' . $result);
            } else {
                self::debug_log('IntelliSend: Failed to log email to database');
            }
        } catch (Exception $e) {
            error_log('IntelliSend Error logging email: ' . $e->getMessage());
        }
    }

    /**
     * Determine sender from headers or fallback
     */
    private static function determine_sender($headers)
    {
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (stripos($header, 'From:') === 0) {
                    preg_match('/[\w\.-]+@[\w\.-]+\.\w+/', $header, $matches);
                    if (!empty($matches[0])) {
                        return $matches[0];
                    }
                }
            }
        }

        // Fallback hierarchy
        if (self::$current_provider && !empty(self::$current_provider->sender)) {
            return self::$current_provider->sender;
        }

        return get_option('admin_email') ?: 'wordpress@' . $_SERVER['HTTP_HOST'];
    }

    // ===========================================
    // UTILITY METHODS
    // ===========================================

    /**
     * Reset state after email processing
     */
    public static function reset_state()
    {
        self::$current_email = null;
        self::$matched_rule = null;
        self::$current_provider = null;
        self::$added_bcc_recipients = array();
    }

    /**
     * Enable or disable debug logging (legacy method for compatibility)
     */
    public static function set_debug_mode($enabled)
    {
        // This method is kept for backward compatibility but doesn't do anything
        // Debug mode is now controlled via database settings
        self::debug_log('Warning: set_debug_mode() is deprecated. Use database settings instead.');
    }

    /**
     * Get current debug mode status
     */
    public static function get_debug_status()
    {
        return self::is_debug_enabled();
    }
}

// Initialize the form handler
add_action('init', array('IntelliSend_Form', 'init'), 10);
