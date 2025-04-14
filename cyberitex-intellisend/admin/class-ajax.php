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
        add_action( 'wp_ajax_intellisend_ajax_handler', array( __CLASS__, 'ajax_handler' ) );
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
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_ajax_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'intellisend-form' ) ) );
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
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
            return;
        }

        // Process and save settings
        $settings = array(
            'defaultProviderName'  => isset( $_POST['intellisend_smtp_provider'] ) ? sanitize_text_field( $_POST['intellisend_smtp_provider'] ) : '',
            'antiSpamEndPoint'     => isset( $_POST['intellisend_api_endpoint'] ) ? esc_url_raw( $_POST['intellisend_api_endpoint'] ) : 'https://api.cyberitex.com/v1/spam-check',
            'antiSpamApiKey'       => isset( $_POST['intellisend_api_key'] ) ? sanitize_text_field( $_POST['intellisend_api_key'] ) : '',
            'testRecipient'        => isset( $_POST['intellisend_test_recipient'] ) ? sanitize_email( $_POST['intellisend_test_recipient'] ) : '',
            'spamTestMessage'      => isset( $_POST['intellisend_spam_test_message'] ) ? sanitize_textarea_field( $_POST['intellisend_spam_test_message'] ) : '',
            'logsRetentionDays'    => isset( $_POST['intellisend_logs_retention'] ) ? intval( $_POST['intellisend_logs_retention'] ) : 365,
        );

        // Save settings to database
        $result = IntelliSend_Database::update_settings($settings);

        if ($result) {
            wp_send_json_success( array( 'message' => esc_html__( 'Settings saved successfully!', 'intellisend-form' ) ) );
        } else {
            wp_send_json_error( array( 'message' => esc_html__( 'Failed to save settings.', 'intellisend-form' ) ) );
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
            // Get the provider by name
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
        $subject = 'IntelliSend Test Email';
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
                $phpmailer->Password = $provider->password;
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
        
        // Send the test email
        $result = wp_mail( $to, $subject, $message, $headers );
        
        // Create a log entry for the test email
        $table_name = $wpdb->prefix . 'intellisend_logs';
        $log_data = array(
            'log_type'    => 'test_email',
            'log_status'  => $result ? 'success' : 'failed',
            'log_message' => $result ? 'Test email sent successfully to ' . $test_email_address : 'Failed to send test email to ' . $test_email_address,
            'log_date'    => current_time( 'mysql' ),
            'log_ip'      => $_SERVER['REMOTE_ADDR'],
            'log_details' => json_encode(array(
                'provider' => $provider->name,
                'recipient' => $test_email_address,
                'debug_output' => $debug_output
            ))
        );
        $wpdb->insert( $table_name, $log_data );
        
        if ($result) {
            wp_send_json_success( array(
                'message' => esc_html__( 'Test email sent successfully!', 'cyberitex-spam-interceptor' ),
                'debug' => $debug_output // Include debug output even for successful sends
            ) );
        } else {
            // Create a log entry for the failed test
            $table_name = $wpdb->prefix . 'intellisend_logs';
            
            // Collect as much debug information as possible
            $error_details = "Email Error: " . ($debug_output ? $debug_output : 'Unknown error') . "\n\n";
            $error_details .= "SMTP Settings:\n";
            $error_details .= "Server: " . $provider->server . "\n";
            $error_details .= "Port: " . $provider->port . "\n";
            $error_details .= "Encryption: " . $provider->encryption . "\n";
            $error_details .= "Username: " . $provider->username . "\n";
            // Don't include the password for security reasons
            
            $log_data = array(
                'log_type'    => 'test_email',
                'log_status'  => 'failed',
                'log_message' => 'Test email failed: ' . ($debug_output ? $debug_output : 'Unknown error'),
                'log_date'    => current_time( 'mysql' ),
                'log_ip'      => $_SERVER['REMOTE_ADDR'],
                'log_details' => json_encode(array(
                    'error_type' => 'send_failure',
                    'debug_output' => $debug_output,
                    'recipient' => $test_email_address,
                    'smtp_server' => $provider->server,
                    'smtp_port' => $provider->port,
                    'smtp_encryption' => $provider->encryption,
                    'smtp_username' => $provider->username,
                    'error_details' => $error_details
                ))
            );
            $wpdb->insert( $table_name, $log_data );
            
            wp_send_json_error( array(
                'message' => esc_html__( 'Failed to send test email. Error: ', 'cyberitex-spam-interceptor' ) . ($debug_output ? $debug_output : 'Unknown error'),
                'debug' => $error_details
            ) );
        }
    }

    /**
     * Handle spam test email submission.
     */
    private static function handle_spam_test() {
        global $wpdb;
        
        // Verify user permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to perform this action.', 'intellisend-form' ) ) );
            return;
        }
        
        // Get the test email address and spam test message
        $test_email_address = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : '';
        $spam_test_message = isset( $_POST['spam_test_message'] ) ? sanitize_textarea_field( $_POST['spam_test_message'] ) : '';
        
        // If no test email is provided, use the default recipient from settings
        if (empty($test_email_address)) {
            $settings = IntelliSend_Database::get_settings();
            $test_email_address = $settings->testRecipient;
        }

        if (empty($test_email_address)) {
            wp_send_json_error( array( 'message' => esc_html__( 'Please provide a valid email address for testing.', 'intellisend-form' ) ) );
            return;
        }
        
        // If no spam test message is provided, use the default from settings
        if (empty($spam_test_message)) {
            $settings = IntelliSend_Database::get_settings();
            $spam_test_message = $settings->spamTestMessage;
            
            if (empty($spam_test_message)) {
                $spam_test_message = "This is a test spam message. It contains common spam trigger words like: viagra, casino, free money, lottery winner, Nigerian prince, wire transfer, bank account details, etc.";
            }
        }
        
        // Check if the message is spam
        $spam_checker = new IntelliSend_SpamCheck();
        $settings = IntelliSend_Database::get_settings();
        $spam_result = $spam_checker->check($spam_test_message, $settings->antiSpamApiKey);
        
        // Get the provider ID from the request or use the default
        $provider_id = isset( $_POST['provider_id'] ) ? sanitize_text_field( $_POST['provider_id'] ) : '';
        $provider = null;
        
        if (!empty($provider_id)) {
            // Get the provider by name
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
        $subject = 'IntelliSend Spam Test Email';
        $message = $spam_test_message;
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: IntelliSend <' . $test_email_address . '>',
        );
        
        // Add spam score information to the message
        $message .= '<hr><p><strong>Spam Test Results:</strong></p>';
        if (isset($spam_result['success']) && $spam_result['success']) {
            $message .= '<p>Spam Score: ' . $spam_result['score'] . '/10</p>';
            $message .= '<p>Is Spam: ' . (($spam_result['score'] >= 7) ? 'Yes' : 'No') . '</p>';
            if (isset($spam_result['details'])) {
                $message .= '<p>Details: ' . $spam_result['details'] . '</p>';
            }
        } else {
            $message .= '<p>Spam check failed. Please check your API key and try again.</p>';
        }
        
        // Configure PHPMailer to use the selected SMTP provider
        add_filter( 'wp_mail_from', function( $email ) use ( $test_email_address ) {
            return $test_email_address;
        });
        
        add_filter( 'wp_mail_from_name', function( $name ) {
            return 'IntelliSend Spam Test';
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
                $phpmailer->Password = $provider->password;
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
        
        // Send the test email
        $result = wp_mail( $to, $subject, $message, $headers );
        
        // Create a log entry for the spam test email
        $table_name = $wpdb->prefix . 'intellisend_logs';
        $log_data = array(
            'log_type'    => 'spam_test',
            'log_status'  => $result ? 'success' : 'failed',
            'log_message' => $result ? 'Spam test email sent successfully to ' . $test_email_address : 'Failed to send spam test email to ' . $test_email_address,
            'log_date'    => current_time( 'mysql' ),
            'log_ip'      => $_SERVER['REMOTE_ADDR'],
            'log_details' => json_encode(array(
                'provider' => $provider->name,
                'recipient' => $test_email_address,
                'spam_score' => isset($spam_result['score']) ? $spam_result['score'] : 'N/A',
                'is_spam' => isset($spam_result['score']) && $spam_result['score'] >= 7 ? true : false,
                'debug_output' => $debug_output
            ))
        );
        $wpdb->insert( $table_name, $log_data );
        
        if ($result) {
            wp_send_json_success( array(
                'message' => esc_html__( 'Spam test email sent successfully!', 'cyberitex-spam-interceptor' ),
                'debug' => $debug_output // Include debug output even for successful sends
            ) );
        } else {
            // Create a log entry for the failed spam test
            $table_name = $wpdb->prefix . 'intellisend_logs';
            
            // Collect as much debug information as possible
            $error_details = "Email Error: " . ($debug_output ? $debug_output : 'Unknown error') . "\n\n";
            $error_details .= "SMTP Settings:\n";
            $error_details .= "Server: " . $provider->server . "\n";
            $error_details .= "Port: " . $provider->port . "\n";
            $error_details .= "Encryption: " . $provider->encryption . "\n";
            $error_details .= "Username: " . $provider->username . "\n";
            // Don't include the password for security reasons
            
            $log_data = array(
                'log_type'    => 'spam_test',
                'log_status'  => 'failed',
                'log_message' => 'Spam test email failed: ' . ($debug_output ? $debug_output : 'Unknown error'),
                'log_date'    => current_time( 'mysql' ),
                'log_ip'      => $_SERVER['REMOTE_ADDR'],
                'log_details' => json_encode(array(
                    'error_type' => 'send_failure',
                    'debug_output' => $debug_output,
                    'recipient' => $test_email_address,
                    'smtp_server' => $provider->server,
                    'smtp_port' => $provider->port,
                    'smtp_encryption' => $provider->encryption,
                    'smtp_username' => $provider->username,
                    'message_length' => strlen($spam_test_message),
                    'error_details' => $error_details
                ))
            );
            $wpdb->insert( $table_name, $log_data );
            
            wp_send_json_error( array(
                'message' => esc_html__( 'Failed to send spam test email. Error: ', 'cyberitex-spam-interceptor' ) . ($debug_output ? $debug_output : 'Unknown error'),
                'debug' => $error_details
            ) );
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
}

// Don't initialize directly - this will be called from the admin class
// IntelliSend_Ajax::init();
