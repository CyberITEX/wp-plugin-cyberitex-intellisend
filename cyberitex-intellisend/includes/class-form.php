<?php

/**
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
     * Debug mode flag
     */
    private static $debug_enabled = true; // Set to false to disable debug logging

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
     * Initialize the form handler
     */
    public static function init()
    {
        // Hook into WordPress mail system
        add_filter('wp_mail', array(__CLASS__, 'intercept_email'), 10, 1);
        add_action('phpmailer_init', array(__CLASS__, 'configure_phpmailer'), 10, 1);
        add_action('wp_mail_succeeded', array(__CLASS__, 'log_email_success'), 10, 1);
        add_action('wp_mail_failed', array(__CLASS__, 'log_email_failure'), 10, 1);

        self::debug_log('IntelliSend_Form: Initialized email hooks');
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
     * Configure PHPMailer with the selected provider
     */
    public static function configure_phpmailer($phpmailer)
    {
        self::debug_log('=== INTELLISEND PHPMAILER CONFIGURATION START ===');

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
        $patterns = array_map('trim', explode(',', $rule->subject_patterns));
        $pattern_type = $rule->pattern_type ?? 'wildcard';

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
     * Check if a pattern matches text based on pattern type
     */
    private static function pattern_matches($pattern, $text, $pattern_type)
    {
        $text = strtolower($text);
        $pattern = strtolower($pattern);

        switch ($pattern_type) {
            case 'wildcard':
                $regex_pattern = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
                return preg_match($regex_pattern, $text);

            case 'starts_with':
                return strpos($text, $pattern) === 0;

            case 'contains':
                return strpos($text, $pattern) !== false;

            case 'ends_with':
                return substr($text, -strlen($pattern)) === $pattern;

            case 'regex':
                try {
                    return preg_match('/' . $pattern . '/i', $text);
                } catch (Exception $e) {
                    self::debug_log("IntelliSend: Invalid regex pattern: {$pattern}");
                    return false;
                }

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

        if ($rule && !empty($rule->recipients)) {
            self::debug_log("IntelliSend: Configuring recipients from routing rule as BCC: {$rule->recipients}");

            $recipients = array_filter(array_map('trim', explode(',', $rule->recipients)));

            foreach ($recipients as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $phpmailer->addBCC($recipient);
                    self::debug_log("IntelliSend: Added BCC recipient from routing rule: {$recipient}");
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
        if (self::$debug_enabled && defined('WP_DEBUG') && WP_DEBUG) {
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
            $log_parts[] = 'SMTP Server: ' . self::$current_provider->server;
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
                if (self::$matched_rule && !empty(self::$matched_rule->recipients)) {
                    $actual_recipients .= ' (BCC: ' . self::$matched_rule->recipients . ')';
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
     * Debug logging with enable/disable switch
     */
    private static function debug_log($message)
    {
        if (self::$debug_enabled) {
            error_log($message);
        }
    }

    /**
     * Reset state after email processing
     */
    public static function reset_state()
    {
        self::$current_email = null;
        self::$matched_rule = null;
        self::$current_provider = null;
    }

    /**
     * Enable or disable debug logging
     */
    public static function set_debug_mode($enabled)
    {
        self::$debug_enabled = (bool) $enabled;
    }

    /**
     * Get current debug mode status
     */
    public static function is_debug_enabled()
    {
        return self::$debug_enabled;
    }
}

// Initialize the form handler
add_action('init', array('IntelliSend_Form', 'init'), 10);