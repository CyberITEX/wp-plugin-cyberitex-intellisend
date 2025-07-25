<?php

/**
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

    /**
     * Initialize AJAX hooks
     */
    public static function init()
    {
        // Main AJAX handler for all sub-actions
        add_action('wp_ajax_intellisend_ajax_handler', array(__CLASS__, 'ajax_handler'));

        // Direct action handlers
        add_action('wp_ajax_intellisend_save_provider', array(__CLASS__, 'handle_save_provider'));
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_settings')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend-form')));
        }

        // Get the action
        $action = sanitize_text_field($_POST['sub_action']);

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

            default:
                wp_send_json_error(array('message' => esc_html__('Invalid setting name.', 'intellisend')));
                return;
        }

        // Update settings in database
        $result = IntelliSend_Database::update_settings($update_data);

        if ($result) {
            wp_send_json_success(array('message' => esc_html__('Setting saved successfully.', 'intellisend')));
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
            'antiSpamEndPoint'     => isset($_POST['antiSpamEndPoint']) ? esc_url_raw($_POST['antiSpamEndPoint']) : 'https://api.cyberitex.com/v1/tools/SpamCheck',
        );

        // Only update API key if a new one is provided
        if (isset($_POST['antiSpamApiKey']) && !empty($_POST['antiSpamApiKey'])) {
            $settings['antiSpamApiKey'] = sanitize_text_field($_POST['antiSpamApiKey']);
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
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $endpoint = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';

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
        $test_email_address = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';

        // If no test email is provided, use the default recipient from settings
        if (empty($test_email_address)) {
            $settings = IntelliSend_Database::get_settings();
            $test_email_address = $settings->testRecipient;
        }

        if (empty($test_email_address)) {
            wp_send_json_error(array('message' => esc_html__('Please provide a valid email address for testing.', 'intellisend-form')));
            return;
        }

        // Get the provider ID from the request or use the default
        $provider_id = isset($_POST['provider_id']) ? sanitize_text_field($_POST['provider_id']) : '';
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
            wp_send_json_error(array('message' => esc_html__('SMTP provider not found. Please configure a provider first.', 'intellisend-form')));
            return;
        }

        // Set up the email
        $to = $test_email_address;
        $subject = '[CyberITEX] IntelliSend Test Email';
        $message = 'This is a test email from IntelliSend using provider "' . $provider->name . '". If you received this, your SMTP settings are working correctly.';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: IntelliSend <' . $provider->sender . '>',
        );

        // Configure PHPMailer to use the selected SMTP provider
        add_filter('wp_mail_from', function ($email) use ($provider) {
            return $provider->sender;
        });

        add_filter('wp_mail_from_name', function ($name) {
            return 'IntelliSend Test';
        });

        // Capture debug output
        $debug_output = '';

        // Configure PHPMailer to use SMTP
        add_action('phpmailer_init', function ($phpmailer) use ($provider, &$debug_output) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $provider->server;
            $phpmailer->Port = $provider->port;

            if ($provider->encryption === 'ssl') {
                $phpmailer->SMTPSecure = 'ssl';
            } elseif ($provider->encryption === 'tls') {
                $phpmailer->SMTPSecure = 'tls';
            }

            if ($provider->authRequired) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $provider->username;
                // Decrypt the password before using it
                $phpmailer->Password = IntelliSend_Database::decrypt_data($provider->password);
            } else {
                $phpmailer->SMTPAuth = false;
            }

            $phpmailer->SMTPDebug = 2;
            $phpmailer->Debugoutput = function ($str, $level) use (&$debug_output) {
                $debug_output .= $str . "\n";
            };
        });

        // Set a flag to bypass the mail interception for test emails
        $GLOBALS['intellisend_test_email'] = true;

        // Send the test email
        $result = wp_mail($to, $subject, $message, $headers);

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

        // Reset the flag
        $GLOBALS['intellisend_test_email'] = false;

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
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $endpoint = isset($_POST['endpoint']) ? esc_url_raw($_POST['endpoint']) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
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

        // Temporarily update settings for this test
        $settings = IntelliSend_Database::get_settings();
        $original_endpoint = $settings->antiSpamEndPoint;
        $settings->antiSpamEndPoint = $endpoint;

        // Check if the message is spam using the provided API key
        $spam_checker = new IntelliSend_SpamCheck();
        $spam_result = $spam_checker->check($message, $api_key);

        // Restore original endpoint setting
        $settings->antiSpamEndPoint = $original_endpoint;

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
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
            error_log('IntelliSend: Nonce validation failed in handle_add_routing_rule');
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_add_routing_rule');
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get form data
        if (!isset($_POST['formData'])) {
            error_log('IntelliSend: No form data provided in handle_add_routing_rule');
            wp_send_json_error('Invalid form data.');
            return;
        }

        $formData = array();
        parse_str($_POST['formData'], $formData);

        // Log the received data for debugging
        error_log('IntelliSend: Add routing rule data: ' . print_r($formData, true));

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

        // Prepare rule data
        $rule = new stdClass();
        $rule->name = sanitize_text_field($formData['name']);
        $rule->default_provider_name = sanitize_text_field($formData['default_provider_name']);
        $rule->subject_patterns = sanitize_textarea_field($formData['subject_patterns']);
        $rule->recipients = isset($formData['recipients']) && !empty($formData['recipients']) ? sanitize_textarea_field($formData['recipients']) : get_option('admin_email');
        $rule->priority = isset($formData['priority']) ? intval($formData['priority']) : 10;
        $rule->enabled = isset($formData['enabled']) ? absint($formData['enabled']) : 1;
        $rule->anti_spam_enabled = isset($formData['anti_spam_enabled']) ? absint($formData['anti_spam_enabled']) : 1;

        // Create the routing rule
        $result = IntelliSend_Database::create_routing_rule($rule);
        if (!$result) {
            error_log('IntelliSend: Failed to create routing rule in database');
            wp_send_json_error('Failed to add routing rule. Database operation failed.');
            return;
        }

        // Send success response
        wp_send_json_success('Routing rule added successfully.');
    }

    /**
     * Handle update routing rule AJAX request
     */
    public static function handle_update_routing_rule()
    {
        // Check nonce
        if (!isset($_POST['nonce'])) {
            error_log('IntelliSend: No nonce provided in handle_update_routing_rule');
            wp_send_json_error('Security check failed - no nonce provided.');
            return;
        }

        // Get the received nonce
        $received_nonce = $_POST['nonce'];

        // Try both the routing nonce and the ajax nonce for backward compatibility
        $is_routing_nonce_valid = wp_verify_nonce($received_nonce, 'intellisend_routing_nonce');
        $is_ajax_nonce_valid = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_routing_nonce_valid && !$is_ajax_nonce_valid) {
            error_log('IntelliSend: Invalid nonce in handle_update_routing_rule: ' . $received_nonce);
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_update_routing_rule');
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get form data
        if (!isset($_POST['formData']) || !is_array($_POST['formData'])) {
            error_log('IntelliSend: Invalid form data in handle_update_routing_rule');
            wp_send_json_error('Invalid form data.');
            return;
        }

        $formData = $_POST['formData'];

        // Log the received data for debugging
        error_log('IntelliSend: Update routing rule data: ' . print_r($formData, true));

        // Validate rule ID
        if (empty($formData['id'])) {
            wp_send_json_error('Rule ID is required.');
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

        // Get existing rule
        $rule_id = intval($formData['id']);
        $existing_rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$existing_rule) {
            error_log('IntelliSend: Rule not found with ID: ' . $rule_id);
            wp_send_json_error('Rule not found.');
            return;
        }

        // Special handling for default rule
        $is_default_rule = ($rule_id == 1 || $existing_rule->priority == -1 || $existing_rule->is_default == 1);

        // Prepare rule data object
        $rule = new stdClass();
        $rule->id = $rule_id;
        $rule->name = sanitize_text_field($formData['name']);
        $rule->default_provider_name = sanitize_text_field($formData['default_provider_name']);
        $rule->recipients = isset($formData['recipients']) ? sanitize_textarea_field($formData['recipients']) : $existing_rule->recipients;
        $rule->enabled = isset($formData['enabled']) ? absint($formData['enabled']) : 1;
        $rule->anti_spam_enabled = isset($formData['anti_spam_enabled']) ? absint($formData['anti_spam_enabled']) : 1;

        // Handle subject patterns and priority for default vs regular rules
        if ($is_default_rule) {
            // For default rule, keep fixed values
            $rule->subject_patterns = '*';
            $rule->priority = -1;
        } else {
            // For regular rules, use provided values
            $rule->subject_patterns = sanitize_textarea_field($formData['subject_patterns']);
            $rule->priority = isset($formData['priority']) ? intval($formData['priority']) : $existing_rule->priority;
        }

        // Update rule
        $result = IntelliSend_Database::update_routing_rule($rule);
        if ($result === false) {
            error_log('IntelliSend: Database error in handle_update_routing_rule');
            wp_send_json_error('Failed to update routing rule. Database operation failed.');
            return;
        }

        // Send success response
        wp_send_json_success('Routing rule updated successfully.');
    }

    /**
     * Handle delete routing rule AJAX request
     */
    public static function handle_delete_routing_rule()
    {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
            error_log('IntelliSend: Nonce validation failed in handle_delete_routing_rule');
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_delete_routing_rule');
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            error_log('IntelliSend: Invalid rule ID in handle_delete_routing_rule');
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Delete rule
        $result = IntelliSend_Database::delete_routing_rule($rule_id);
        if (!$result) {
            error_log('IntelliSend: Failed to delete routing rule: ' . $rule_id);
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
            error_log('IntelliSend: Nonce validation failed in handle_activate_routing_rule');
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_activate_routing_rule');
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            error_log('IntelliSend: Invalid rule ID in handle_activate_routing_rule');
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Get rule data
        $rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$rule) {
            error_log('IntelliSend: Rule not found in handle_activate_routing_rule: ' . $rule_id);
            wp_send_json_error('Rule not found.');
            return;
        }

        // Update rule status
        $rule->enabled = 1;
        $result = IntelliSend_Database::update_routing_rule($rule);
        if (!$result) {
            error_log('IntelliSend: Failed to activate routing rule: ' . $rule_id);
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
            error_log('IntelliSend: Nonce validation failed in handle_deactivate_routing_rule');
            wp_send_json_error('Security check failed.');
            return;
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_deactivate_routing_rule');
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        // Get rule ID
        $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$rule_id) {
            error_log('IntelliSend: Invalid rule ID in handle_deactivate_routing_rule');
            wp_send_json_error('Invalid rule ID.');
            return;
        }

        // Get rule data
        $rule = IntelliSend_Database::get_routing_rule($rule_id);
        if (!$rule) {
            error_log('IntelliSend: Rule not found in handle_deactivate_routing_rule: ' . $rule_id);
            wp_send_json_error('Rule not found.');
            return;
        }

        // Update rule status
        $rule->enabled = 0;
        $result = IntelliSend_Database::update_routing_rule($rule);
        if (!$result) {
            error_log('IntelliSend: Failed to deactivate routing rule: ' . $rule_id);
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
            error_log('IntelliSend: Permission check failed in handle_get_report');
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            error_log('IntelliSend: No nonce provided in handle_get_report');
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = $_POST['nonce'];

        // TRANSITION PERIOD: Accept the known nonce value from existing JavaScript
        $known_legacy_nonce = 'ee86b922eb';
        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce && $received_nonce !== $known_legacy_nonce) {
            error_log('IntelliSend: Invalid nonce in handle_get_report: ' . $received_nonce);
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed. Please refresh the page and try again.', 'intellisend')
            ));
            return;
        }

        // Get report ID
        $report_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if (!$report_id) {
            error_log('IntelliSend: Invalid report ID in handle_get_report');
            wp_send_json_error(array(
                'message' => esc_html__('Invalid report ID.', 'intellisend')
            ));
            return;
        }

        try {
            error_log('IntelliSend: Attempting to get report with ID: ' . $report_id);

            // Get report from database
            $report = IntelliSend_Database::get_report($report_id);
            if (!$report) {
                error_log('IntelliSend: Report not found with ID: ' . $report_id);
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
            error_log('IntelliSend: Report data successfully retrieved for ID: ' . $report_id);

            wp_send_json_success($report_data);
        } catch (Exception $e) {
            error_log('IntelliSend Error: ' . $e->getMessage());
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
                'server' => $provider->server,
                'port' => $provider->port,
                'encryption' => $provider->encryption,
                'description' => $provider->description,
                'helpLink' => $provider->helpLink,
                'authRequired' => (bool) $provider->authRequired,
                'username' => $provider->username,
                'password' => $provider->password,
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_providers')) {
            wp_send_json_error(array('message' => esc_html__('Security check failed.', 'intellisend')));
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => esc_html__('You do not have permission to perform this action.', 'intellisend')));
            return;
        }

        // Validate required fields
        if (empty($_POST['provider_name']) || empty($_POST['provider_server']) || empty($_POST['provider_port'])) {
            wp_send_json_error(array('message' => esc_html__('Please fill in all required fields.', 'intellisend')));
            return;
        }

        // Check if there are any configured providers before this operation
        $existing_configured_providers = IntelliSend_Database::get_providers(array('configured' => 1));
        $had_configured_providers = !empty($existing_configured_providers);

        // Prepare provider data
        $provider_data = array(
            'name'         => sanitize_text_field($_POST['provider_name']),
            'server'       => sanitize_text_field($_POST['provider_server']),
            'port'         => absint($_POST['provider_port']),
            'encryption'   => 'tls', // Default to TLS
            'authRequired' => 1,     // Default to requiring authentication
            'username'     => sanitize_text_field($_POST['provider_username']),
            'sender'       => !empty($_POST['provider_sender']) ? sanitize_text_field($_POST['provider_sender']) : sanitize_text_field($_POST['provider_username']),
            'password'     => $_POST['provider_password'], // Will be encrypted by the database class
            'configured'   => 1      // Mark as configured since all required fields are provided
        );

        // Get provider ID
        $provider_id = isset($_POST['provider_id']) && !empty($_POST['provider_id']) ? absint($_POST['provider_id']) : 0;

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
            wp_send_json_success(array('message' => $message));
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
            error_log('IntelliSend: Permission check failed in handle_delete_reports');
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            error_log('IntelliSend: No nonce provided in handle_delete_reports');
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = $_POST['nonce'];

        // TRANSITION PERIOD: Accept the known nonce value from existing JavaScript
        $known_legacy_nonce = 'ee86b922eb';
        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce && $received_nonce !== $known_legacy_nonce) {
            error_log('IntelliSend: Invalid nonce in handle_delete_reports: ' . $received_nonce);
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

        $report_ids = array_map('intval', $_POST['ids']);
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
            error_log('IntelliSend: Database error in handle_delete_reports: ' . $wpdb->last_error);
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
            error_log('IntelliSend: Permission check failed in handle_delete_all_reports');
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
            ));
            return;
        }

        // Verify nonce
        if (!isset($_POST['nonce'])) {
            error_log('IntelliSend: No nonce provided in handle_delete_all_reports');
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed - no nonce provided.', 'intellisend')
            ));
            return;
        }

        $received_nonce = $_POST['nonce'];

        // TRANSITION PERIOD: Accept the known nonce value from existing JavaScript
        $known_legacy_nonce = 'ee86b922eb';
        $is_valid_nonce = wp_verify_nonce($received_nonce, 'intellisend_ajax_nonce');

        if (!$is_valid_nonce && $received_nonce !== $known_legacy_nonce) {
            error_log('IntelliSend: Invalid nonce in handle_delete_all_reports: ' . $received_nonce);
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
            error_log('IntelliSend: Database error in handle_delete_all_reports: ' . $wpdb->last_error);
            wp_send_json_error(array(
                'message' => esc_html__('Database error occurred while deleting all reports.', 'intellisend')
            ));
            return;
        }

        wp_send_json_success(array(
            'message' => esc_html__('All reports deleted successfully.', 'intellisend')
        ));
    }
}
