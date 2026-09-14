<?php

/**
 * admin\class-ajax.php
 * IntelliSend AJAX Handler
 *
 * Handles all AJAX requests for the IntelliSend plugin
 *
 * @package IntelliSend
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * IntelliSend AJAX Class
 */
class IntelliSend_Ajax
{
    /** Read one WordPress request value without corrupting escaped credentials. */
    private static function post_string($key, $default = '')
    {
        return isset($_POST[$key]) && is_string($_POST[$key])
            ? wp_unslash($_POST[$key]) : $default;
    }

    /** Only a nonce issued for this user/session may authorise an AJAX request. */
    private static function verify_nonce($actions)
    {
        $nonce = self::post_string('nonce');
        if ('' === $nonce) {
            return false;
        }
        foreach ((array) $actions as $action) {
            if (wp_verify_nonce($nonce, $action)) {
                return true;
            }
        }
        return false;
    }

    /** Validate routing values before creating or changing a delivery rule. */
    private static function validate_routing_input($data, $is_default = false)
    {
        foreach ($data as $value) {
            if (!is_scalar($value)) {
                return __('Invalid routing form data.', 'intellisend');
            }
        }
        $provider = IntelliSend_Database::get_provider_by_name(isset($data['default_provider_name']) ? $data['default_provider_name'] : '');
        if (!$provider || empty($provider->configured)) {
            return __('Choose a configured provider.', 'intellisend');
        }
        if (!empty($data['recipients'])) {
            foreach (explode(',', $data['recipients']) as $recipient) {
                if (!is_email(trim($recipient))) {
                    return __('Enter valid recipient email addresses separated by commas.', 'intellisend');
                }
            }
        }
        if (!$is_default && isset($data['priority']) && (!preg_match('/^\d+$/', (string) $data['priority']) || (float) $data['priority'] > 2147483647)) {
            return __('Priority must be a non-negative whole number.', 'intellisend');
        }
        $type = isset($data['pattern_type']) ? strtolower($data['pattern_type']) : 'wildcard';
        if (!$is_default && 'regex' === $type) {
            $patterns = IntelliSend_Form::parse_subject_patterns(isset($data['subject_patterns']) ? $data['subject_patterns'] : '', $type);
            if (empty($patterns)) {
                return __('At least one pattern is required.', 'intellisend');
            }
            foreach ($patterns as $pattern) {
                if (false === @preg_match('/' . $pattern . '/i', '')) {
                    return __('Enter a valid regular expression without delimiters.', 'intellisend');
                }
            }
        }
        return '';
    }

    /**
     * Initialize AJAX hooks
     */
    public static function init()
    {
        // Main AJAX handler for all sub-actions
        add_action('wp_ajax_intellisend_ajax_handler', array(__CLASS__, 'ajax_handler'));

        // Direct action handlers
        add_action('wp_ajax_intellisend_save_provider', array(__CLASS__, 'handle_save_provider'));
        add_action('wp_ajax_intellisend_check_provider_api_key', array(__CLASS__, 'handle_check_provider_api_key'));
        add_action('wp_ajax_intellisend_send_test_email', array(__CLASS__, 'handle_send_test_email'));
        add_action('wp_ajax_intellisend_save_routing_rule', array(__CLASS__, 'handle_save_routing_rule'));
        add_action('wp_ajax_intellisend_get_report', array(__CLASS__, 'handle_get_report'));
        add_action('wp_ajax_intellisend_delete_reports', array(__CLASS__, 'handle_delete_reports'));
        add_action('wp_ajax_intellisend_delete_all_reports', array(__CLASS__, 'handle_delete_all_reports'));

        // Routing rule AJAX handlers
        add_action('wp_ajax_intellisend_add_routing_rule', array(__CLASS__, 'handle_add_routing_rule'));
        add_action('wp_ajax_intellisend_update_routing_rule', array(__CLASS__, 'handle_update_routing_rule'));
        add_action('wp_ajax_intellisend_delete_routing_rule', array(__CLASS__, 'handle_delete_routing_rule'));
        add_action('wp_ajax_intellisend_activate_routing_rule', array(__CLASS__, 'handle_activate_routing_rule'));
        add_action('wp_ajax_intellisend_deactivate_routing_rule', array(__CLASS__, 'handle_deactivate_routing_rule'));

        // Enqueue scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
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
            error_log('IntelliSend AJAX Debug: ' . $message);
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts($hook)
    {
        // Only load on our plugin pages
        if (strpos($hook, 'intellisend') === false) {
            return;
        }

        wp_enqueue_script(
            'intellisend-admin-js',
            INTELLISEND_PLUGIN_URL . 'admin/js/intellisend-admin.js',
            array('jquery'),
            INTELLISEND_VERSION,
            true
        );

        wp_localize_script(
            'intellisend-admin-js',
            'intellisend_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('intellisend_ajax_nonce'),
            )
        );
    }

    /**
     * Main AJAX handler
     */
    public static function ajax_handler()
    {
        // Check for action parameter
        if (!isset($_POST['action']) || 'intellisend_ajax_handler' !== $_POST['action']) {
            wp_send_json_error(array('message' => esc_html__('Invalid action.', 'intellisend-form')));
            return;
        }

        // Check for sub-action parameter
        if (!isset($_POST['sub_action'])) {
            wp_send_json_error(array('message' => esc_html__('Missing sub-action parameter.', 'intellisend-form')));
            return;
        }

        // Verify nonce
        if (!self::verify_nonce('intellisend_settings')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend-form')));
        }

        // Get the action
        $action = sanitize_text_field(self::post_string('sub_action'));

        switch ($action) {
            case 'settings_saved':
                self::handle_save_settings();
                break;
            case 'auto_save_setting':
                self::handle_auto_save_setting();
                break;
            case 'api_checked':
                self::handle_api_check();
                break;
            case 'test_email_sent':
                self::handle_test_email();
                break;
            case 'spam_test_sent':
                self::handle_spam_test();
                break;
            case 'get_smtp_providers':
                self::handle_get_smtp_providers();
                break;
            case 'provider_api_key_checked':
                self::handle_provider_api_key_check();
                break;
            default:
                wp_send_json_error(array('message' => esc_html__('Invalid action.', 'intellisend-form')));
        }
    }

    /**
     * Handle auto-saving individual settings
     */
    private static function handle_auto_save_setting()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        // Get setting name and value
        $setting_name = isset($_POST['setting_name']) ? sanitize_text_field($_POST['setting_name']) : '';
        $setting_value = isset($_POST['setting_value']) ? sanitize_text_field($_POST['setting_value']) : '';

        if (empty($setting_name)) {
            wp_send_json_error(array('message' => esc_html__('Setting name is required.', 'intellisend')));
            return;
        }

        // Get existing settings
        $settings = IntelliSend_Database::get_settings();
        if (!$settings) {
            wp_send_json_error(array('message' => esc_html__('Settings not found.', 'intellisend')));
            return;
        }

        // Validate and update the specific setting
        $update_data = array();

        switch ($setting_name) {
            case 'defaultProviderName':
                $update_data['defaultProviderName'] = sanitize_text_field($setting_value);
                break;

            case 'logsRetentionDays':
                $update_data['logsRetentionDays'] = intval($setting_value);
                break;

            case 'testRecipient':
                if (!empty($setting_value) && !is_email($setting_value)) {
                    wp_send_json_error(array('message' => esc_html__('Invalid email address.', 'intellisend')));
                    return;
                }
                $update_data['testRecipient'] = sanitize_email($setting_value);
                break;

            case 'debug_enabled':
                // Handle debug toggle - convert to integer (0 or 1)
                $debug_value = ($setting_value === 'true' || $setting_value === '1' || $setting_value === 1) ? 1 : 0;
                $update_data['debug_enabled'] = $debug_value;
                break;

            default:
                wp_send_json_error(array('message' => esc_html__('Invalid setting name.', 'intellisend')));
                return;
        }

        // Update settings in database
        $result = IntelliSend_Database::update_settings($update_data);

        if ($result) {
            // For debug toggle, return additional info about the new state
            if ($setting_name === 'debug_enabled') {
                $new_state = $update_data['debug_enabled'] ? 'enabled' : 'disabled';
                wp_send_json_success(array(
                    'message' => sprintf(esc_html__('Debug mode %s.', 'intellisend'), $new_state),
                    'debug_state' => $update_data['debug_enabled']
                ));
            } else {
                wp_send_json_success(array('message' => esc_html__('Setting saved successfully.', 'intellisend')));
            }
        } else {
            wp_send_json_error(array('message' => esc_html__('Failed to save setting.', 'intellisend')));
        }
    }

    /**
     * Handle saving anti-spam settings via AJAX
     */
    private static function handle_save_settings()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        // Get existing settings to preserve values that shouldn't be changed
        $existing_settings = IntelliSend_Database::get_settings();

        // Process and save only anti-spam settings
        $settings = array(
            'antiSpamEndPoint'     => isset($_POST['antiSpamEndPoint']) ? esc_url_raw(self::post_string('antiSpamEndPoint')) : 'https://api.cyberitex.com/v1/tools/SpamCheck',
        );

        // Only update API key if a new one is provided
        $api_key = self::post_string('antiSpamApiKey');
        if ('' !== $api_key) {
            $settings['antiSpamApiKey'] = $api_key;
        }

        // Save settings to database
        $result = IntelliSend_Database::update_settings($settings);

        if ($result) {
            wp_send_json_success(array('message' => esc_html__('Anti-spam settings saved successfully!', 'intellisend')));
        } else {
            wp_send_json_error(array('message' => esc_html__('Failed to save anti-spam settings.', 'intellisend')));
        }
    }

    /**
     * Handle API key check
     */
    private static function handle_api_check()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend-form')));
            return;
        }

        // Get the API key
        $api_key = isset($_POST['api_key']) ? self::post_string('api_key') : '';
        $endpoint = isset($_POST['endpoint']) ? esc_url_raw(self::post_string('endpoint')) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => esc_html__('API key is required.', 'intellisend-form')));
            return;
        }

        if (empty($endpoint)) {
            wp_send_json_error(array('message' => esc_html__('API endpoint is required.', 'intellisend-form')));
            return;
        }

        // Perform actual API key validation by sending a request to the endpoint
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key
            ),
            'timeout' => 15,
            'body' => json_encode(array(
                'test' => true,
                'message' => 'Test message for API validation'
            ))
        ));

        // Check for errors
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => esc_html__('Connection error: ', 'intellisend-form') . $response->get_error_message(),
                'details' => $response->get_error_messages()
            ));
            return;
        }

        // Get response code
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Debug information
        $debug_info = array(
            'code' => $response_code,
            'body' => $response_body,
            'headers' => $response_headers,
        );

        if ($response_code === 200) {
            wp_send_json_success(array(
                'message' => esc_html__('API key is valid!', 'intellisend-form'),
                'debug' => $debug_info
            ));
        } else if ($response_code === 401) {
            wp_send_json_error(array(
                'message' => esc_html__('Invalid API key. Please check and try again.', 'intellisend-form'),
                'debug' => $debug_info
            ));
        } else {
            wp_send_json_error(array(
                'message' => sprintf(esc_html__('Unexpected response from API server: %d', 'intellisend-form'), $response_code),
                'debug' => $debug_info
            ));
        }
    }

    /**
     * Handle test email submission.
     */
    private static function handle_test_email()
    {
        global $wpdb;

        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend-form')));
            return;
        }

        // Get the test email address
        $test_email_address = isset($_POST['test_email']) ? sanitize_email(self::post_string('test_email')) : '';

        // If no test email is provided, use the default recipient from settings
        if (empty($test_email_address)) {
            $settings = IntelliSend_Database::get_settings();
            $test_email_address = $settings->testRecipient;
        }

        if (empty($test_email_address) || !is_email($test_email_address)) {
            wp_send_json_error(array('message' => esc_html__('Please provide a valid email address for testing.', 'intellisend-form')));
            return;
        }

        // Get the provider ID from the request or use the default
        $provider_id = isset($_POST['provider_id']) ? sanitize_text_field(self::post_string('provider_id')) : '';
        $provider = null;

        if (!empty($provider_id)) {
            // Get the provider by ID
            $provider = IntelliSend_Database::get_provider_by_name($provider_id);
        } else {
            // Otherwise, get the default provider from settings
            $settings = IntelliSend_Database::get_settings();
            $default_provider_id = $settings->defaultProviderName;
            $provider = IntelliSend_Database::get_provider_by_name($default_provider_id);
        }

        if (!$provider) {
            wp_send_json_error(array('message' => esc_html__('Provider not found. Please configure a provider first.', 'intellisend-form')));
            return;
        }

        // Fail fast with something readable instead of an SMTP timeout or a
        // credential error from the vendor.
        if (empty($provider->configured)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('%s is not configured yet. Save its credentials before sending a test.', 'intellisend'),
                    esc_html(IntelliSend_Database::get_provider_label($provider))
                ),
            ));
            return;
        }

        // Set up the email
        $to = $test_email_address;
        $subject = '[CyberITEX] IntelliSend Test Email';
        $message = 'This is a test email from IntelliSend using provider "' . $provider->name . '". If you received this, your settings are working correctly.';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: IntelliSend <' . $provider->sender . '>',
        );

        // API transports never touch PHPMailer, so send them directly.
        if (IntelliSend_Database::is_api_provider($provider)) {
            self::send_test_email_via_api($provider, $to, $subject, $message, $headers);
            return;
        }

        // Scope the test configuration to this send, including exceptional exits.
        $debug_output = '';
        $from_callback = static function () use ($provider) {
            return $provider->sender;
        };
        $name_callback = static function () {
            return 'IntelliSend Test';
        };
        $mailer_callback = static function ($phpmailer) use ($provider, &$debug_output) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $provider->server;
            $phpmailer->Port = $provider->port;
            $phpmailer->SMTPSecure = in_array($provider->encryption, array('ssl', 'tls'), true) ? $provider->encryption : '';
            $phpmailer->SMTPAuth = (bool) $provider->authRequired;
            $phpmailer->Username = $phpmailer->SMTPAuth ? $provider->username : '';
            $phpmailer->Password = $phpmailer->SMTPAuth ? IntelliSend_Database::decrypt_data($provider->password) : '';
            $phpmailer->SMTPDebug = self::is_debug_enabled() ? 2 : 0;
            $phpmailer->Debugoutput = static function ($str, $level) use (&$debug_output) {
                $debug_output .= $str . "\n";
            };
        };
        $had_test_flag = array_key_exists('intellisend_test_email', $GLOBALS);
        $previous_test_flag = $had_test_flag ? $GLOBALS['intellisend_test_email'] : null;
        $had_mailer = array_key_exists('phpmailer', $GLOBALS);
        $previous_mailer = $had_mailer ? $GLOBALS['phpmailer'] : null;
        $GLOBALS['intellisend_test_email'] = true;
        unset($GLOBALS['phpmailer']);
        add_filter('wp_mail_from', $from_callback, PHP_INT_MAX);
        add_filter('wp_mail_from_name', $name_callback, PHP_INT_MAX);
        add_action('phpmailer_init', $mailer_callback, PHP_INT_MAX);
        try {
            $result = wp_mail($to, $subject, $message, $headers);
        } catch (Throwable $error) {
            $result = false;
            // Third-party exceptions may contain credentials; do not return them.
            $debug_output = 'The mail transport raised an error while sending the test.';
        } finally {
            remove_filter('wp_mail_from', $from_callback, PHP_INT_MAX);
            remove_filter('wp_mail_from_name', $name_callback, PHP_INT_MAX);
            remove_action('phpmailer_init', $mailer_callback, PHP_INT_MAX);
            if ($had_test_flag) {
                $GLOBALS['intellisend_test_email'] = $previous_test_flag;
            } else {
                unset($GLOBALS['intellisend_test_email']);
            }
            if ($had_mailer) {
                $GLOBALS['phpmailer'] = $previous_mailer;
            } else {
                unset($GLOBALS['phpmailer']);
            }
        }

        // Extract and log only the error part if there is an error
        if (!$result && !empty($debug_output)) {
            // Look for error messages in the debug output
            if (
                preg_match('/SERVER -> CLIENT: (5\d{2}.*?)(?:\n|$)/s', $debug_output, $matches) ||
                preg_match('/SMTP ERROR: (.*?)(?:\n|$)/s', $debug_output, $matches)
            ) {
                $debug_output = $matches[1];
            }
        }

        // Collect debug information
        $error_details = "";
        if (!$result) {
            $error_details = "Email Error: " . ($debug_output ? $debug_output : 'Unknown error') . "\n\n";
            $error_details .= "SMTP Settings:\n";
            $error_details .= "Server: " . $provider->server . "\n";
            $error_details .= "Port: " . $provider->port . "\n";
            $error_details .= "Encryption: " . $provider->encryption . "\n";
            $error_details .= "Username: " . $provider->username . "\n";
            $error_details .= "Sender: " . $provider->sender . "\n";
            // Don't include the password for security reasons
        }

        // Create a single log entry for the test email
        $table_name = $wpdb->prefix . 'intellisend_reports';
        $log_data = array(
            'date'        => current_time('mysql'),
            'subject'     => $subject,
            'sender'      => $provider->sender,
            'recipients'  => $test_email_address,
            'message'     => $message,
            'status'      => $result ? 'sent' : 'failed',
            'log'         => $result ? $debug_output : $debug_output . "\n" . $error_details,
            'antiSpamEnabled' => 0,
            'isSpam'      => 0,
            'providerName' => $provider->name
        );
        $wpdb->insert($table_name, $log_data);

        if ($result) {
            wp_send_json_success(array(
                'message' => esc_html__('Test email sent successfully!', 'cyberitex-spam-interceptor'),
                'debug' => $debug_output // Include debug output even for successful sends
            ));
        } else {
            wp_send_json_error(array(
                'message' => esc_html__('Failed to send test email. Please check your SMTP settings.', 'cyberitex-spam-interceptor'),
                'debug' => $debug_output . "\n" . $error_details
            ));
        }
    }

    /**
     * Send a test email through an API provider and log the result.
     *
     * @param object $provider Provider row (type = 'api').
     * @param string $to       Recipient address.
     * @param string $subject  Subject line.
     * @param string $message  Body.
     * @param array  $headers  wp_mail style headers.
     */
    private static function send_test_email_via_api($provider, $to, $subject, $message, $headers)
    {
        global $wpdb;

        $transport = IntelliSend_Api_Transport::for_provider($provider);

        if (!$transport) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('No API transport is registered for the provider "%s".', 'intellisend'),
                    esc_html($provider->name)
                ),
            ));
            return;
        }

        $result = $transport::send(
            $provider,
            array(
                'to'      => $to,
                'subject' => $subject,
                'message' => $message,
                'headers' => $headers,
            )
        );

        $wpdb->insert(
            $wpdb->prefix . 'intellisend_reports',
            array(
                'date'            => current_time('mysql'),
                'subject'         => $subject,
                'sender'          => $provider->sender,
                'recipients'      => $to,
                'message'         => $message,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'log'             => $result['log'],
                'antiSpamEnabled' => 0,
                'isSpam'          => 0,
                'providerName'    => $provider->name,
            )
        );

        if ($result['success']) {
            wp_send_json_success(array(
                'message' => sprintf(
                    esc_html__('Test email accepted by %s.', 'intellisend'),
                    esc_html($transport::get_label())
                ),
                'debug'   => $result['log'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => $result['message'],
                'debug'   => $result['log'],
            ));
        }
    }

    /**
     * AJAX entry point for the providers page "Send Test Email" button.
     *
     * handle_test_email() already accepts any provider by name, so a provider
     * can be tested where it was configured without first being promoted to the
     * site default.
     */
    public static function handle_send_test_email()
    {
        if (!self::verify_nonce(array('intellisend_providers', 'intellisend_settings'))) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        self::handle_test_email();
    }

    /**
     * AJAX entry point for the providers page "Test API Key" button.
     */
    public static function handle_check_provider_api_key()
    {
        if (!self::verify_nonce(array('intellisend_providers', 'intellisend_settings'))) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        self::handle_provider_api_key_check();
    }

    /**
     * Validate the API key of an API-transport provider.
     */
    private static function handle_provider_api_key_check()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        $provider_name = isset($_POST['provider_name']) ? sanitize_text_field(self::post_string('provider_name')) : '';
        $transport = IntelliSend_Api_Transport::for_provider($provider_name);

        if (!$transport) {
            wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('No API transport is registered for the provider "%s".', 'intellisend'),
                    esc_html($provider_name)
                ),
            ));
            return;
        }

        $provider = IntelliSend_Database::get_provider_by_name($provider_name);

        // An empty key means "test whatever is already configured".
        $api_key = isset($_POST['api_key']) ? self::post_string('api_key') : '';

        $result = $transport::validate_api_key($api_key, $provider);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle spam test submission.
     */
    private static function handle_spam_test()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        // Get the API key, endpoint and test message from the request
        $api_key = isset($_POST['api_key']) ? self::post_string('api_key') : '';
        $endpoint = isset($_POST['endpoint']) ? esc_url_raw(self::post_string('endpoint')) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(self::post_string('message')) : '';
        $use_existing_key = isset($_POST['use_existing_key']) && $_POST['use_existing_key'] == 1;

        // If using existing key and no new key provided, get it from the database
        if ($use_existing_key && empty($api_key)) {
            $settings = IntelliSend_Database::get_settings();
            $api_key = $settings->antiSpamApiKey;
        }

        if (empty($api_key)) {
            wp_send_json_error(array('message' => esc_html__('API key is required.', 'intellisend')));
            return;
        }

        if (empty($endpoint)) {
            wp_send_json_error(array('message' => esc_html__('Endpoint URL is required.', 'intellisend')));
            return;
        }

        if (empty($message)) {
            wp_send_json_error(array('message' => esc_html__('Test message is required.', 'intellisend')));
            return;
        }

        $spam_checker = new IntelliSend_SpamCheck();
        $spam_result = $spam_checker->check($message, $api_key, $endpoint);

        if (isset($spam_result['success']) && $spam_result['success']) {
            // Send success response with spam check results
            wp_send_json_success(array(
                'isSpam' => isset($spam_result['isSpam']) ? $spam_result['isSpam'] : false,
                'score' => isset($spam_result['score']) ? $spam_result['score'] : 0,
                'message' => esc_html__('Spam check completed successfully.', 'intellisend')
            ));
        } else {
            // Send error response
            $error_message = isset($spam_result['message']) ? $spam_result['message'] : esc_html__('Spam check failed. Please check your API key and endpoint.', 'intellisend');
            wp_send_json_error(array('message' => $error_message));
        }
    }

    /**
     * Handle add routing rule AJAX request
     */
    public static function handle_add_routing_rule()
    {
        // Enable debugging
        if (self::is_debug_enabled()) {
            self::debug_log('=== INTELLISEND ADD ROUTING RULE START ===');
        }

        // Check nonce
        if (!self::verify_nonce('intellisend_routing_nonce')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Nonce validation failed in handle_add_routing_rule');
            }
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_add_routing_rule');
            }
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get form data
        if (!isset($_POST['formData'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No form data provided in handle_add_routing_rule');
            }
            wp_send_json_error('Invalid form data.');
            return;
        }

        $formData = array();
        parse_str(self::post_string('formData'), $formData);
        $validation_error = self::validate_routing_input($formData);
        if ('' !== $validation_error) {
            wp_send_json_error($validation_error);
            return;
        }

        // Validate required fields
        if (empty($formData['name'])) {
            wp_send_json_error('Rule name is required.');
            return;
        }

        if (empty($formData['default_provider_name'])) {
            wp_send_json_error('Provider is required.');
            return;
        }

        if (empty($formData['subject_patterns'])) {
            wp_send_json_error('At least one pattern is required.');
            return;
        }

        // Handle pattern type with case-insensitive validation
        $valid_pattern_types = array('wildcard', 'starts_with', 'contains', 'ends_with', 'regex');
        $pattern_type = isset($formData['pattern_type']) ? strtolower(sanitize_text_field($formData['pattern_type'])) : 'wildcard';
        if (!in_array($pattern_type, $valid_pattern_types)) {
            $pattern_type = 'wildcard';
        }
        if (self::is_debug_enabled()) {
            self::debug_log('Add - Pattern type after validation: ' . $pattern_type);
        }

        // Prepare rule data
        $rule = new stdClass();
        $rule->name = sanitize_text_field($formData['name']);
        $rule->default_provider_name = sanitize_text_field($formData['default_provider_name']);
        $rule->subject_patterns = sanitize_textarea_field($formData['subject_patterns']);
        $rule->pattern_type = $pattern_type;
        $rule->recipients = isset($formData['recipients']) && !empty($formData['recipients']) ? sanitize_textarea_field($formData['recipients']) : get_option('admin_email');
        $rule->priority = isset($formData['priority']) ? intval($formData['priority']) : 10;
        $rule->enabled = isset($formData['enabled']) ? absint($formData['enabled']) : 1;
        $rule->anti_spam_enabled = isset($formData['anti_spam_enabled']) ? absint($formData['anti_spam_enabled']) : 1;

        // Create the routing rule
        $result = IntelliSend_Database::create_routing_rule($rule);
        if (self::is_debug_enabled()) {
            self::debug_log('Add - Database result: ' . var_export($result, true));
        }

        if (!$result) {
            if (self::is_debug_enabled()) {
                self::debug_log('Failed to create routing rule in database');
            }
            wp_send_json_error('Failed to add routing rule. Database operation failed.');
            return;
        }

        if (self::is_debug_enabled()) {
            self::debug_log('=== INTELLISEND ADD ROUTING RULE SUCCESS ===');
        }
        wp_send_json_success('Routing rule added successfully.');
    }

    /**
     * Handle update routing rule AJAX request
     */
    public static function handle_update_routing_rule()
    {
        // Enable detailed debugging
        if (self::is_debug_enabled()) {
            self::debug_log('=== INTELLISEND UPDATE ROUTING RULE START ===');
        }

        // Check nonce
        if (!isset($_POST['nonce'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No nonce provided in handle_update_routing_rule');
            }
            wp_send_json_error('Security check failed - no nonce provided.');
            return;
        }

        $received_nonce = self::post_string('nonce');
        // Try both the routing nonce and the ajax nonce for backward compatibility
        $is_routing_nonce_valid = wp_verify_nonce($received_nonce, 'intellisend_routing_nonce');
        $is_ajax_nonce_valid = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (self::is_debug_enabled()) {
            self::debug_log('Routing nonce valid: ' . ($is_routing_nonce_valid ? 'true' : 'false'));
            self::debug_log('Ajax nonce valid: ' . ($is_ajax_nonce_valid ? 'true' : 'false'));
        }

        if (!$is_routing_nonce_valid && !$is_ajax_nonce_valid) {
            if (self::is_debug_enabled()) {
            }
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_update_routing_rule');
            }
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get form data
        if (!isset($_POST['formData'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No form data provided in handle_update_routing_rule');
            }
            wp_send_json_error('Invalid form data.');
            return;
        }

        $formData = isset($_POST['formData']) && is_array($_POST['formData']) ? wp_unslash($_POST['formData']) : self::post_string('formData');
        // Parse form data if it's a string
        if (is_string($formData)) {
            $parsed_data = array();
            parse_str($formData, $parsed_data);
            $formData = $parsed_data;
        }

        foreach ($formData as $value) {
            if (!is_scalar($value)) {
                wp_send_json_error(__('Invalid routing form data.', 'intellisend'));
                return;
            }
        }

        // Validate rule ID
        if (empty($formData['id'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No rule ID provided');
            }
            wp_send_json_error('Rule ID is required.');
            return;
        }

        $rule_id = intval($formData['id']);
        if (self::is_debug_enabled()) {
            self::debug_log('Rule ID: ' . $rule_id);
        }

        // Validate required fields
        if (empty($formData['name'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('Rule name is empty');
            }
            wp_send_json_error('Rule name is required.');
            return;
        }

        if (empty($formData['default_provider_name'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('Provider name is empty');
            }
            wp_send_json_error('Provider is required.');
            return;
        }

        // Get existing rule
        $existing_rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$existing_rule) {
            if (self::is_debug_enabled()) {
                self::debug_log('Rule not found with ID: ' . $rule_id);
            }
            wp_send_json_error('Rule not found.');
            return;
        }

        // Check if it's a default rule
        $is_default_rule = ($existing_rule->priority == -1 || (isset($existing_rule->is_default) && $existing_rule->is_default == 1));
        $validation_error = self::validate_routing_input($formData, $is_default_rule);
        if ('' !== $validation_error) {
            wp_send_json_error($validation_error);
            return;
        }

        if (self::is_debug_enabled()) {
            self::debug_log('Is default rule: ' . ($is_default_rule ? 'true' : 'false'));
        }

        // For non-default rules, validate subject patterns
        if (!$is_default_rule && empty($formData['subject_patterns'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('Subject patterns required for non-default rule');
            }
            wp_send_json_error('At least one pattern is required.');
            return;
        }

        // Handle pattern type with case-insensitive validation
        $valid_pattern_types = array('wildcard', 'starts_with', 'contains', 'ends_with', 'regex');
        $pattern_type = isset($formData['pattern_type']) ? strtolower(sanitize_text_field($formData['pattern_type'])) : 'wildcard';
        if (!in_array($pattern_type, $valid_pattern_types)) {
            $pattern_type = 'wildcard';
        }
        if (self::is_debug_enabled()) {
            self::debug_log('Pattern type after validation: ' . $pattern_type);
            self::debug_log('Original pattern_type from form: ' . (isset($formData['pattern_type']) ? $formData['pattern_type'] : 'NOT_SET'));
        }

        // Prepare rule data object
        $rule = new stdClass();
        $rule->id = $rule_id;
        $rule->name = sanitize_text_field($formData['name']);
        $rule->default_provider_name = sanitize_text_field($formData['default_provider_name']);
        $rule->pattern_type = $pattern_type;
        $rule->recipients = isset($formData['recipients']) ? sanitize_textarea_field($formData['recipients']) : $existing_rule->recipients;
        $rule->enabled = isset($formData['enabled']) ? absint($formData['enabled']) : 1;
        $rule->anti_spam_enabled = isset($formData['anti_spam_enabled']) ? absint($formData['anti_spam_enabled']) : 1;

        // Handle subject patterns and priority for default vs regular rules
        if ($is_default_rule) {
            // For default rule, keep fixed values but allow pattern type to be updated
            $rule->subject_patterns = '*';
            $rule->priority = -1;
            if (self::is_debug_enabled()) {
                self::debug_log('Default rule - using fixed patterns and priority, but allowing pattern_type: ' . $rule->pattern_type);
            }
        } else {
            // For regular rules, use provided values
            $rule->subject_patterns = sanitize_textarea_field($formData['subject_patterns']);
            $rule->priority = isset($formData['priority']) ? intval($formData['priority']) : $existing_rule->priority;
            if (self::is_debug_enabled()) {
                self::debug_log('Regular rule - using provided patterns: ' . $rule->subject_patterns . ', pattern_type: ' . $rule->pattern_type);
            }
        }

        if (self::is_debug_enabled()) {
            self::debug_log('Rule pattern_type specifically: "' . $rule->pattern_type . '"');
            self::debug_log('Rule pattern_type type: ' . gettype($rule->pattern_type));
        }

        // Check the current pattern_type in database BEFORE update
        global $wpdb;
        $table = $wpdb->prefix . 'intellisend_routing';
        $current_db_rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rule_id));
        // Verify database classes are available
        if (!class_exists('IntelliSend_Database')) {
            if (self::is_debug_enabled()) {
                self::debug_log('IntelliSend_Database class not found');
            }
            wp_send_json_error('Database class not available.');
            return;
        }

        if (!method_exists('IntelliSend_Database', 'update_routing_rule')) {
            if (self::is_debug_enabled()) {
                self::debug_log('update_routing_rule method not found');
            }
            wp_send_json_error('Database method not available.');
            return;
        }

        // Update rule
        if (self::is_debug_enabled()) {
            self::debug_log('Calling IntelliSend_Database::update_routing_rule');
        }
        $result = IntelliSend_Database::update_routing_rule($rule);
        if (self::is_debug_enabled()) {
            self::debug_log('Update result: ' . var_export($result, true));
        }

        // Check the pattern_type in database AFTER update
        $updated_db_rule = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rule_id));
        if (self::is_debug_enabled()) {
            self::debug_log('Pattern type changed from "' . ($current_db_rule ? $current_db_rule->pattern_type : 'NULL') . '" to "' . ($updated_db_rule ? $updated_db_rule->pattern_type : 'NULL') . '"');
        }

        if ($result === false) {
            if (self::is_debug_enabled()) {
                self::debug_log('Database error in handle_update_routing_rule');

                // Get additional debugging info
                if ($wpdb->last_error) {
                    self::debug_log('WordPress database error: ' . $wpdb->last_error);
                    self::debug_log('Last query: ' . $wpdb->last_query);
                }
            }

            wp_send_json_error('Failed to update routing rule. Database operation failed.');
            return;
        }

        if (self::is_debug_enabled()) {
            self::debug_log('Rule updated successfully');
            self::debug_log('=== INTELLISEND UPDATE ROUTING RULE SUCCESS ===');
        }

        wp_send_json_success('Routing rule updated successfully.');
    }

    /**
     * Handle delete routing rule AJAX request
     */
    public static function handle_delete_routing_rule()
    {
        // Check nonce
        if (!self::verify_nonce('intellisend_routing_nonce')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Nonce validation failed in handle_delete_routing_rule');
            }
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_delete_routing_rule');
            }
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            if (self::is_debug_enabled()) {
                self::debug_log('Invalid rule ID in handle_delete_routing_rule');
            }
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Delete rule
        $result = IntelliSend_Database::delete_routing_rule($rule_id);
        if (!$result) {
            if (self::is_debug_enabled()) {
                self::debug_log('Failed to delete routing rule: ' . $rule_id);
            }
            wp_send_json_error('Failed to delete routing rule. Database operation failed.');
            return;
        }

        // Send success response
        wp_send_json_success('Routing rule deleted successfully.');
    }

    /**
     * Handle activate routing rule AJAX request
     */
    public static function handle_activate_routing_rule()
    {
        // Check nonce
        if (!self::verify_nonce('intellisend_routing_nonce')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Nonce validation failed in handle_activate_routing_rule');
            }
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_activate_routing_rule');
            }
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            if (self::is_debug_enabled()) {
                self::debug_log('Invalid rule ID in handle_activate_routing_rule');
            }
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Get rule data
        $rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$rule) {
            if (self::is_debug_enabled()) {
                self::debug_log('Rule not found in handle_activate_routing_rule: ' . $rule_id);
            }
            wp_send_json_error('Rule not found.');
            return;
        }

        // Update rule status
        $rule->enabled = 1;
        $result = IntelliSend_Database::update_routing_rule($rule);
        if (!$result) {
            if (self::is_debug_enabled()) {
                self::debug_log('Failed to activate routing rule: ' . $rule_id);
            }
            wp_send_json_error('Failed to activate routing rule. Database operation failed.');
            return;
        }

        // Send success response
        wp_send_json_success('Routing rule activated successfully.');
    }

    /**
     * Handle deactivate routing rule AJAX request
     */
    public static function handle_deactivate_routing_rule()
    {
        // Check nonce
        if (!self::verify_nonce('intellisend_routing_nonce')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Nonce validation failed in handle_deactivate_routing_rule');
            }
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_deactivate_routing_rule');
            }
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            if (self::is_debug_enabled()) {
                self::debug_log('Invalid rule ID in handle_deactivate_routing_rule');
            }
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Get rule data
        $rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$rule) {
            if (self::is_debug_enabled()) {
                self::debug_log('Rule not found in handle_deactivate_routing_rule: ' . $rule_id);
            }
            wp_send_json_error('Rule not found.');
            return;
        }

        // Update rule status
        $rule->enabled = 0;
        $result = IntelliSend_Database::update_routing_rule($rule);
        if (!$result) {
            if (self::is_debug_enabled()) {
                self::debug_log('Failed to deactivate routing rule: ' . $rule_id);
            }
            wp_send_json_error('Failed to deactivate routing rule. Database operation failed.');
            return;
        }

        // Send success response
        wp_send_json_success('Routing rule deactivated successfully.');
    }

    /**
     * Handle get report AJAX request
     */
    public static function handle_get_report()
    {
        // Check permissions first
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_get_report');
            }
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No nonce provided in handle_get_report');
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = self::post_string('nonce');

        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce) {
            if (self::is_debug_enabled()) {
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'intellisend')
            ));
            return;
        }

        // Get report ID
        $report_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$report_id) {
            if (self::is_debug_enabled()) {
                self::debug_log('Invalid report ID in handle_get_report');
            }
            wp_send_json_error(array(
                'message' => esc_html__('Invalid report ID.', 'intellisend')
            ));
            return;
        }

        try {
            if (self::is_debug_enabled()) {
                self::debug_log('Attempting to get report with ID: ' . $report_id);
            }

            // Get report from database
            $report = IntelliSend_Database::get_report($report_id);
            if (!$report) {
                if (self::is_debug_enabled()) {
                    self::debug_log('Report not found with ID: ' . $report_id);
                }
                wp_send_json_error(array(
                    'message' => esc_html__('Report not found.', 'intellisend')
                ));
                return;
            }

            // Get routing rule name if applicable
            $routing_rule_name = esc_html__('Default Routing', 'intellisend');
            if (!empty($report->routing_rule_id)) {
                $routing_rule = IntelliSend_Database::get_routing_rule($report->routing_rule_id);
                if ($routing_rule) {
                    $routing_rule_name = $routing_rule->name;
                }
            }

            // Format report data for response - ensure field names match what JavaScript expects
            $report_data = array(
                'id' => $report->id,
                'date' => $report->date,
                'status' => $report->status,
                'sender' => $report->sender,
                'recipients' => $report->recipients,
                'subject' => $report->subject,
                'message' => $report->message,
                'providerName' => $report->providerName,
                'routingRuleName' => $routing_rule_name,
                'log' => $report->log,
                'isSpam' => (bool) $report->isSpam,
                'spamScore' => isset($report->spamScore) ? $report->spamScore : '',
                'errorMessage' => isset($report->errorMessage) ? $report->errorMessage : ''
            );

            // Log the response for debugging
            if (self::is_debug_enabled()) {
                self::debug_log('Report data successfully retrieved for ID: ' . $report_id);
            }

            wp_send_json_success($report_data);
        } catch (Exception $e) {
            if (self::is_debug_enabled()) {
                self::debug_log('Error: ' . $e->getMessage());
            }
            wp_send_json_error(array(
                'message' => esc_html__('An error occurred while retrieving the report.', 'intellisend'),
                'error' => $e->getMessage()
            ));
        }
    }

    /**
     * Handle fetching SMTP providers from the database
     */
    private static function handle_get_smtp_providers()
    {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend-form')));
            return;
        }

        // Get providers from the database
        $providers = IntelliSend_Database::get_providers();

        if (empty($providers)) {
            wp_send_json_error(array('message' => esc_html__('No SMTP providers found.', 'intellisend-form')));
            return;
        }

        // Format providers for JavaScript
        $formatted_providers = array();
        foreach ($providers as $provider) {
            $formatted_providers[$provider->name] = array(
                'type' => IntelliSend_Database::get_provider_type($provider),
                'label' => IntelliSend_Database::get_provider_label($provider),
                'server' => $provider->server,
                'port' => $provider->port,
                'encryption' => $provider->encryption,
                'description' => $provider->description,
                'helpLink' => $provider->helpLink,
                'authRequired' => (bool) $provider->authRequired,
                'username' => $provider->username,
                'sender' => $provider->sender,
                'configured' => (int) $provider->configured,
            );
        }

        // Get current settings
        $settings = IntelliSend_Database::get_settings();
        $default_provider = $settings ? $settings->defaultProviderName : 'other';

        wp_send_json_success(array(
            'providers' => $formatted_providers,
            'defaultProvider' => $default_provider
        ));
    }

    /**
     * Handle saving provider data
     */
    public static function handle_save_provider()
    {
        // Check nonce
        if (!self::verify_nonce('intellisend_providers')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        if (empty($_POST['provider_name'])) {
            wp_send_json_error(array('message' => esc_html__('Please fill in all required fields.', 'intellisend')));
            return;
        }

        $provider_name = sanitize_text_field(self::post_string('provider_name'));

        // Get provider ID
        $posted_id = self::post_string('provider_id');
        if (isset($_POST['provider_id']) && (!is_string($_POST['provider_id']) || ('' !== $posted_id && !ctype_digit($posted_id)))) {
            wp_send_json_error(array('message' => esc_html__('Invalid provider ID.', 'intellisend')));
            return;
        }
        $provider_id = absint($posted_id);

        // Work out the transport type: an explicit POST value wins, otherwise
        // fall back to the stored type so an SMTP save never flips a record.
        $existing_provider = $provider_id > 0
            ? IntelliSend_Database::get_provider($provider_id)
            : IntelliSend_Database::get_provider_by_name($provider_name);

        $provider_type = isset($_POST['provider_type'])
            ? sanitize_text_field(self::post_string('provider_type'))
            : IntelliSend_Database::get_provider_type($existing_provider);

        if (($provider_id > 0 && !$existing_provider)
            || !in_array($provider_type, array(IntelliSend_Database::TYPE_API, IntelliSend_Database::TYPE_SMTP), true)
            || ($existing_provider && ($existing_provider->name !== $provider_name
                || IntelliSend_Database::get_provider_type($existing_provider) !== $provider_type))) {
            wp_send_json_error(array('message' => esc_html__('The provider identity does not match the saved provider. Refresh the page and try again.', 'intellisend')));
            return;
        }
        if ($existing_provider) {
            $provider_id = (int) $existing_provider->id;
        }
        $is_api = IntelliSend_Database::TYPE_API === $provider_type;

        // Check if there are any configured providers before this operation
        $existing_configured_providers = IntelliSend_Database::get_providers(array('configured' => 1));
        $had_configured_providers = !empty($existing_configured_providers);

        if ($is_api) {
            $transport = IntelliSend_Api_Transport::for_provider($provider_name);

            if (!$transport) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        esc_html__('No API transport is registered for the provider "%s".', 'intellisend'),
                        esc_html($provider_name)
                    ),
                ));
                return;
            }

            $api_sender = !empty($_POST['provider_sender']) ? sanitize_email(self::post_string('provider_sender')) : '';

            if (empty($api_sender) || !is_email($api_sender)) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        esc_html__('A valid sender email is required. %s', 'intellisend'),
                        esc_html($transport::get_sender_hint())
                    ),
                ));
                return;
            }

            $api_key = isset($_POST['provider_api_key']) ? self::post_string('provider_api_key') : '';
            $has_stored_key = $existing_provider && !empty($existing_provider->apiKey);
            $has_env_key = '' !== $transport::get_environment_key();

            if (empty($api_key) && !$has_stored_key && !$has_env_key) {
                wp_send_json_error(array(
                    'message' => sprintf(
                        esc_html__('A %1$s is required. Paste one here, or define %2$s in wp-config.php.', 'intellisend'),
                        esc_html($transport::get_key_label()),
                        esc_html($transport::get_env_constant())
                    ),
                ));
                return;
            }

            // Vendors with a credential pair (AWS) also need the non-secret half.
            // It is not a secret, so it lives in the username column.
            $api_identity = '';

            if ($transport::requires_identity()) {
                $api_identity = isset($_POST['provider_api_identity']) ? sanitize_text_field(self::post_string('provider_api_identity')) : '';

                if ('' === $api_identity && '' === $transport::get_identity($existing_provider)) {
                    wp_send_json_error(array(
                        'message' => sprintf(
                            esc_html__('A %1$s is required. Enter one here, or define %2$s in wp-config.php.', 'intellisend'),
                            esc_html($transport::get_identity_label()),
                            esc_html($transport::get_identity_constant())
                        ),
                    ));
                    return;
                }
            }

            // Only accept an endpoint the transport actually offers, so a
            // tampered POST cannot redirect mail to an arbitrary host.
            $regions = $transport::get_regions();
            $posted_endpoint = isset($_POST['provider_api_endpoint']) ? esc_url_raw(self::post_string('provider_api_endpoint')) : '';
            $api_endpoint = isset($regions[$posted_endpoint]) ? $posted_endpoint : $transport::get_default_base();

            $provider_data = array(
                'name'         => $provider_name,
                'type'         => IntelliSend_Database::TYPE_API,
                'server'       => '',
                'port'         => 443,
                'encryption'   => '',
                'authRequired' => 1,
                'username'     => $api_identity,
                'sender'       => $api_sender,
                'apiEndpoint'  => $api_endpoint,
            );

            // Only overwrite the stored key when a new one was typed in.
            if (!empty($api_key)) {
                $provider_data['apiKey'] = $api_key; // Encrypted by the database class
            }
        } else {
            $server = sanitize_text_field(self::post_string('provider_server'));
            $posted_port = self::post_string('provider_port');
            $port = ctype_digit($posted_port) ? (int) $posted_port : 0;
            $username = sanitize_text_field(self::post_string('provider_username'));
            $sender = self::post_string('provider_sender');
            $sender = '' !== $sender ? $sender : $username;
            if ('' === $server || preg_match('/[\s\/\\\\]/', $server) || $port < 1 || $port > 65535 || !is_email($sender)) {
                wp_send_json_error(array('message' => esc_html__('Enter a valid SMTP server, port and sender email address.', 'intellisend')));
                return;
            }
            $encryption = 465 === $port ? 'ssl' : 'tls';
            if (465 !== $port && $existing_provider && (int) $existing_provider->port === $port) {
                $encryption = $existing_provider->encryption;
            }
            $provider_data = array(
                'name' => $provider_name,
                'type' => IntelliSend_Database::TYPE_SMTP,
                'server' => $server,
                'port' => $port,
                'encryption' => $encryption,
                'authRequired' => $existing_provider ? (int) $existing_provider->authRequired : 1,
                'username' => $username,
                'sender' => sanitize_email($sender),
            );
            $password = self::post_string('provider_password');
            if ('' !== $password) {
                $provider_data['password'] = $password; // Encrypted by database class.
            }
        }

        // Check if this should be the default provider
        $set_as_default = isset($_POST['is_default']) && $_POST['is_default'] == '1';

        // Save provider
        if ($provider_id > 0) {
            // Update existing provider
            $result = IntelliSend_Database::update_provider($provider_id, $provider_data, $set_as_default);
            $message = esc_html__('Provider updated successfully.', 'intellisend');
        } else {
            // Add new provider
            $result = IntelliSend_Database::add_provider($provider_data);
            $message = esc_html__('Provider added successfully.', 'intellisend');
        }

        // Handle default provider and routing rule updates
        if ($result) {
            $provider_name = $provider_data['name'];

            // If this is the first configured provider or set as default, update settings and default routing rule
            if (!$had_configured_providers || $set_as_default) {
                // Update default provider in settings
                $settings_update_data = array('defaultProviderName' => $provider_name);
                IntelliSend_Database::update_settings($settings_update_data);

                // Update the default routing rule to use this provider
                $default_routing_rule = IntelliSend_Database::get_routing_rules(array('is_default' => 1));
                if (!empty($default_routing_rule)) {
                    $default_rule = $default_routing_rule[0]; // Get the first (should be only) default rule

                    // Update the default rule's provider
                    $rule_update = new stdClass();
                    $rule_update->id = $default_rule->id;
                    $rule_update->name = $default_rule->name;
                    $rule_update->default_provider_name = $provider_name;
                    $rule_update->subject_patterns = '*'; // Keep default pattern
                    $rule_update->recipients = $default_rule->recipients;
                    $rule_update->enabled = 1; // Ensure it's enabled
                    $rule_update->anti_spam_enabled = $default_rule->anti_spam_enabled;
                    $rule_update->priority = -1; // Keep default priority

                    IntelliSend_Database::update_routing_rule($rule_update);

                    if (!$had_configured_providers) {
                        $message .= ' ' . esc_html__('Default routing rule has been updated to use this provider.', 'intellisend');
                    }
                }
            }
        }

        // Return response
        if ($result) {
            $saved = IntelliSend_Database::get_provider_by_name($provider_data['name']);

            wp_send_json_success(array(
                'message'    => $message,
                'label'      => IntelliSend_Database::get_provider_label($provider_data['name']),
                'type'       => $saved ? IntelliSend_Database::get_provider_type($saved) : $provider_type,
                'configured' => $saved ? (int) $saved->configured : 0,
            ));
        } else {
            wp_send_json_error(array('message' => esc_html__('An error occurred while saving the provider.', 'intellisend')));
        }
    }

    /**
     * Handle delete reports AJAX request
     */
    public static function handle_delete_reports()
    {
        // Check permissions first
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_delete_reports');
            }
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No nonce provided in handle_delete_reports');
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = self::post_string('nonce');

        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce) {
            if (self::is_debug_enabled()) {
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'intellisend')
            ));
            return;
        }

        // Get report IDs
        if (!isset($_POST['ids']) || !is_array($_POST['ids'])) {
            wp_send_json_error(array(
                'message' => esc_html__('No reports selected for deletion.', 'intellisend')
            ));
            return;
        }

        foreach ($_POST['ids'] as $id) {
            if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
                wp_send_json_error(array('message' => esc_html__('Invalid report IDs.', 'intellisend')));
                return;
            }
        }
        $report_ids = array_unique(array_map('intval', $_POST['ids']));
        if (empty($report_ids)) {
            wp_send_json_error(array(
                'message' => esc_html__('No valid report IDs provided.', 'intellisend')
            ));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'intellisend_reports';

        // Delete reports
        $placeholders = implode(',', array_fill(0, count($report_ids), '%d'));
        $query = $wpdb->prepare("DELETE FROM $table_name WHERE id IN ($placeholders)", $report_ids);
        $result = $wpdb->query($query);

        if ($result === false) {
            if (self::is_debug_enabled()) {
                self::debug_log('Database error in handle_delete_reports: ' . $wpdb->last_error);
            }
            wp_send_json_error(array(
                'message' => esc_html__('Database error occurred while deleting reports.', 'intellisend')
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => sprintf(esc_html__('%d reports deleted successfully.', 'intellisend'), count($report_ids))
        ));
    }

    /**
     * Handle delete all reports AJAX request
     */
    public static function handle_delete_all_reports()
    {
        // Check permissions first
        if (!current_user_can('manage_options')) {
            if (self::is_debug_enabled()) {
                self::debug_log('Permission check failed in handle_delete_all_reports');
            }
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            if (self::is_debug_enabled()) {
                self::debug_log('No nonce provided in handle_delete_all_reports');
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = self::post_string('nonce');

        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce) {
            if (self::is_debug_enabled()) {
            }
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'intellisend')
            ));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'intellisend_reports';

        // Delete all reports
        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result === false) {
            if (self::is_debug_enabled()) {
                self::debug_log('Database error in handle_delete_all_reports: ' . $wpdb->last_error);
            }
            wp_send_json_error(array(
                'message' => esc_html__('Database error occurred while deleting all reports.', 'intellisend')
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => esc_html__('All reports deleted successfully.', 'intellisend')
        ));
    }

    /**
     * Handle save routing rule AJAX request (legacy compatibility)
     */
    public static function handle_save_routing_rule()
    {
        // This method exists for backward compatibility but routes to appropriate add/update methods
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            self::handle_update_routing_rule();
        } else {
            self::handle_add_routing_rule();
        }
    }

    /**
     * Get current debug mode status (for compatibility)
     */
    public static function get_debug_status()
    {
        return self::is_debug_enabled();
    }
}
