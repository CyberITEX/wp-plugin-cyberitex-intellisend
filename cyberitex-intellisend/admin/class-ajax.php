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
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Include AJAX handlers
require_once INTELLISEND_PLUGIN_DIR . 'admin/ajax/routing-handlers.php';

/**
 * IntelliSend AJAX Class
 */
class IntelliSend_Ajax {

    /**
     * Initialize AJAX hooks
     */
    public static function init() {
        // Main AJAX handler for all sub-actions
        add_action( 'wp_ajax_intellisend_ajax_handler', array( __CLASS__, 'ajax_handler' ) );
        
        // Direct action handlers
        add_action( 'wp_ajax_intellisend_save_provider', array( __CLASS__, 'handle_save_provider' ) );
        add_action( 'wp_ajax_intellisend_save_routing_rule', array( __CLASS__, 'handle_save_routing_rule' ) );
        add_action( 'wp_ajax_intellisend_get_report', array( __CLASS__, 'handle_get_report' ) );
        
        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_scripts( $hook ) {
        // Only load on our plugin pages
        if ( strpos( $hook, 'intellisend' ) === false ) {
            return;
        }

        wp_enqueue_script(
            'intellisend-admin-js',
            INTELLISEND_PLUGIN_URL . 'admin/js/intellisend-admin.js',
            array( 'jquery' ),
            INTELLISEND_VERSION,
            true
        );

        wp_localize_script(
            'intellisend-admin-js',
            'intellisend_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'intellisend_ajax_nonce' ),
            )
        );
    }

    /**
     * Main AJAX handler
     */
    public static function ajax_handler() {
        // Check for action parameter
        if ( ! isset( $_POST['action'] ) || 'intellisend_ajax_handler' !== $_POST['action'] ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid action.', 'intellisend-form' ) ) );
            return;
        }

        // Check for sub-action parameter
        if ( ! isset( $_POST['sub_action'] ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Missing sub-action parameter.', 'intellisend-form' ) ) );
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_settings' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'intellisend' ) ) );
            return;
        }

        // Check if user has permission
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
        }

        // Get the action
        $action = sanitize_text_field( $_POST['sub_action'] );

        switch ( $action ) {
            case 'settings_saved':
                self::handle_save_settings();
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
                wp_send_json_error( array( 'message' => esc_html__( 'Invalid action.', 'intellisend-form' ) ) );
        }
    }

    /**
     * Handle saving settings via AJAX
     */
    private static function handle_save_settings() {
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend' ) ) );
            return;
        }

        // Parse the form data
        $form_data = array();
        parse_str($_POST['formData'], $form_data);
        
        // Get existing settings to preserve the spam test message
        $existing_settings = IntelliSend_Database::get_settings();
        
        // Process and save settings
        $settings = array(
            'defaultProviderName'  => isset( $form_data['defaultProviderName'] ) ? sanitize_text_field( $form_data['defaultProviderName'] ) : '',
            'antiSpamEndPoint'     => isset( $form_data['antiSpamEndPoint'] ) ? esc_url_raw( $form_data['antiSpamEndPoint'] ) : 'https://api.cyberitex.com/v1/tools/SpamCheck',
            'antiSpamApiKey'       => isset( $form_data['antiSpamApiKey'] ) ? sanitize_text_field( $form_data['antiSpamApiKey'] ) : '',
            'testRecipient'        => isset( $form_data['testRecipient'] ) ? sanitize_email( $form_data['testRecipient'] ) : '',
            'spamTestMessage'      => $existing_settings->spamTestMessage, // Preserve the existing spam test message
            'logsRetentionDays'    => isset( $form_data['logsRetentionDays'] ) ? intval( $form_data['logsRetentionDays'] ) : 365,
        );

        // Save settings to database
        $result = IntelliSend_Database::update_settings($settings);

        if ($result) {
            wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully!', 'intellisend' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to save settings.', 'intellisend' ) ) );
        }
    }

    /**
     * Handle API key check
     */
    private static function handle_api_check() {
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
            return;
        }

        // Get the API key
        $api_key = isset( $_POST['intellisend_api_key'] ) ? sanitize_text_field( $_POST['intellisend_api_key'] ) : '';

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'API key is required.', 'intellisend-form' ) ) );
            return;
        }

        // TODO: Implement actual API key check logic here
        // For now, just simulate a successful check
        $api_check_result = true;

        if ( $api_check_result ) {
            wp_send_json_success( array( 'message' => esc_html__( 'API key is valid!', 'intellisend-form' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Invalid API key. Please check and try again.', 'intellisend-form' ) ) );
        }
    }

    /**
     * Handle test email submission.
     */
    private static function handle_test_email() {
        global $wpdb;
        
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
            return;
        }
        
        // Get the test email address
        $test_email_address = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';
        
        // If no test email is provided, use the default recipient from settings
        if (empty($test_email_address)) {
            $settings = IntelliSend_Database::get_settings();
            $test_email_address = $settings->testRecipient;
        }

        if (empty($test_email_address)) {
            wp_send_json_error( array( 'message' => esc_html__( 'Please provide a valid email address for testing.', 'intellisend-form' ) ) );
            return;
        }
        
        // Get the provider ID from the request or use the default
        $provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( $_POST['provider_id'] ) : '';
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
            wp_send_json_error( array( 'message' => esc_html__( 'SMTP provider not found. Please configure a provider first.', 'intellisend-form' ) ) );
            return;
        }
        
        // Set up the email
        $to = $test_email_address;
        $subject = '[CyberITEX] IntelliSend Test Email';
        $message = 'This is a test email from IntelliSend. If you received this, your SMTP settings are working correctly.';
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: IntelliSend <' . $test_email_address . '>',
        );
        
        // Configure PHPMailer to use the selected SMTP provider
        add_filter( 'wp_mail_from', function( $email ) use ( $test_email_address ) {
            return $test_email_address;
        });
        
        add_filter( 'wp_mail_from_name', function( $name ) {
            return 'IntelliSend Test';
        });
        
        // Configure PHPMailer to use SMTP
        add_action( 'phpmailer_init', function( $phpmailer ) use ( $provider ) {
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
            $phpmailer->Debugoutput = function( $str, $level ) use ( &$debug_output ) {
                $debug_output .= $str . "\n";
            };
        });
        
        // Capture debug output
        $debug_output = '';
        
        // Set a flag to bypass the mail interception for test emails
        $GLOBALS['intellisend_test_email'] = true;
        
        // Send the test email
        $result = wp_mail( $to, $subject, $message, $headers );
        
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
        $wpdb->insert( $table_name, $log_data );
        
        if ($result) {
            wp_send_json_success( array(
                'message' => esc_html__( 'Test email sent successfully!', 'cyberitex-spam-interceptor' ),
                'debug' => $debug_output // Include debug output even for successful sends
            ) );
        } else {
            wp_send_json_error( array(
                'message' => esc_html__( 'Failed to send test email. Please check your SMTP settings.', 'cyberitex-spam-interceptor' ),
                'debug' => $debug_output . "\n" . $error_details
            ) );
        }
    }

    /**
     * Handle spam test submission.
     */
    private static function handle_spam_test() {
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend' ) ) );
            return;
        }
        
        // Get the API key, endpoint and test message from the request
        $api_key = isset( $_POST['apiKey'] ) ? sanitize_text_field( $_POST['apiKey'] ) : '';
        $endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( $_POST['endpoint'] ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( $_POST['message'] ) : '';
        
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'API key is required.', 'intellisend' ) ) );
            return;
        }
        
        if ( empty( $endpoint ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Endpoint URL is required.', 'intellisend' ) ) );
            return;
        }
        
        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Test message is required.', 'intellisend' ) ) );
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
        
        if ( isset( $spam_result['success'] ) && $spam_result['success'] ) {
            // Send success response with spam check results
            wp_send_json_success( array(
                'is_spam' => isset($spam_result['isSpam']) ? $spam_result['isSpam'] : false,
                'score' => isset($spam_result['score']) ? $spam_result['score'] : 0,
                'message' => esc_html__( 'Spam check completed successfully.', 'intellisend' )
            ) );
        } else {
            // Send error response
            $error_message = isset($spam_result['message']) ? $spam_result['message'] : esc_html__( 'Spam check failed. Please check your API key and endpoint.', 'intellisend' );
            wp_send_json_error( array( 'message' => $error_message ) );
        }
    }

    /**
     * Handle get report AJAX request
     */
    public static function handle_get_report() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_reports_nonce')) {
            error_log('IntelliSend: Nonce verification failed in handle_get_report');
            wp_send_json_error(array(
                'message' => esc_html__('Security check failed.', 'intellisend')
            ));
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('IntelliSend: Permission check failed in handle_get_report');
            wp_send_json_error(array(
                'message' => esc_html__('You do not have permission to perform this action.', 'intellisend')
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
    private static function handle_get_smtp_providers() {
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
            return;
        }
        
        // Get providers from the database
        $providers = IntelliSend_Database::get_providers();
        
        if (empty($providers)) {
            wp_send_json_error( array( 'message' => esc_html__( 'No SMTP providers found.', 'intellisend-form' ) ) );
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
        
        wp_send_json_success( array(
            'providers' => $formatted_providers,
            'defaultProvider' => $default_provider
        ) );
    }

    /**
     * Handle saving provider data
     */
    public static function handle_save_provider() {
        // Check nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_providers' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'intellisend' ) ) );
            return;
        }

        // Check user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend' ) ) );
            return;
        }

        // Validate required fields
        if ( empty( $_POST['provider_name'] ) || empty( $_POST['provider_server'] ) || empty( $_POST['provider_port'] ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Please fill in all required fields.', 'intellisend' ) ) );
            return;
        }

        // Prepare provider data
        $provider_data = array(
            'name'         => sanitize_text_field( $_POST['provider_name'] ),
            'server'       => sanitize_text_field( $_POST['provider_server'] ),
            'port'         => absint( $_POST['provider_port'] ),
            'encryption'   => 'tls', // Default to TLS
            'authRequired' => 1,     // Default to requiring authentication
            'username'     => sanitize_text_field( $_POST['provider_username'] ),
            'sender'       => !empty($_POST['provider_sender']) ? sanitize_text_field( $_POST['provider_sender'] ) : sanitize_text_field( $_POST['provider_username'] ), // Use username as default sender if not provided
            'password'     => $_POST['provider_password'], // Will be encrypted by the database class
            'configured'   => 1      // Mark as configured since all required fields are provided
        );

        // Get provider ID
        $provider_id = isset( $_POST['provider_id'] ) && ! empty( $_POST['provider_id'] ) ? absint( $_POST['provider_id'] ) : 0;

        // Check if this should be the default provider
        $set_as_default = isset( $_POST['is_default'] ) && $_POST['is_default'] == '1';

        // Save provider
        if ( $provider_id > 0 ) {
            // Update existing provider
            $result = IntelliSend_Database::update_provider( $provider_id, $provider_data, $set_as_default );
            $message = esc_html__( 'Provider updated successfully.', 'intellisend' );
        } else {
            // Add new provider
            $result = IntelliSend_Database::add_provider( $provider_data );
            if ( $result && $set_as_default ) {
                // Set as default if requested
                $settings = IntelliSend_Database::get_settings();
                if ( $settings ) {
                    $settings->defaultProviderName = $provider_data['name'];
                    IntelliSend_Database::update_settings( $settings );
                }
            }
            $message = esc_html__( 'Provider added successfully.', 'intellisend' );
        }

        // Return response
        if ( $result ) {
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while saving the provider.', 'intellisend' ) ) );
        }
    }
}

// Don't initialize directly - this will be called from the admin class
// IntelliSend_Ajax::init();
