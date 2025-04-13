<?php
/**
 * IntelliSend Admin
 *
 * @package IntelliSend
 * @subpackage Admin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Include admin page files - only when in admin area to prevent activation issues
if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'class-ajax.php';
}

/**
 * IntelliSend Admin Class
 */
class IntelliSend_Admin {

    /**
     * Initialize the admin class
     */
    public static function init() {
        // Register hooks
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_scripts' ) );
        
        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . plugin_basename( dirname( INTELLISEND_PLUGIN_DIR ) . '/cyberitex-intellisend.php' ), 
            array( __CLASS__, 'add_settings_link' ) 
        );
        
        // Initialize AJAX handler - only when in admin area
        if ( is_admin() && class_exists('IntelliSend_Ajax') ) {
            IntelliSend_Ajax::init();
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_intellisend_save_settings', array(__CLASS__, 'ajax_save_settings'));
        add_action('wp_ajax_intellisend_test_spam_detection', array(__CLASS__, 'ajax_test_spam_detection'));
        add_action('wp_ajax_intellisend_ajax_handler', array(__CLASS__, 'ajax_handler'));
    }

    /**
     * Register the admin menu page.
     *
     * @since    1.0.0
     */
    public static function register_admin_menu() {
        add_menu_page(
            'IntelliSend',
            'IntelliSend',
            'manage_options',
            'intellisend',
            array( __CLASS__, 'render_settings_page' ),
            'dashicons-email',
            30
        );
    }

    /**
     * Render the settings page
     * 
     * @since    1.0.0
     */
    public static function render_settings_page() {
        // Include the settings page view
        require_once INTELLISEND_PLUGIN_DIR . 'admin/views/settings-page.php';
        
        // Call the render function defined in the view file
        if (function_exists('intellisend_render_settings_page_content')) {
            intellisend_render_settings_page_content();
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     * @param    string    $hook    The current admin page.
     */
    public static function enqueue_admin_scripts( $hook ) {
        // Only load on plugin pages
        if ( $hook === 'toplevel_page_intellisend' ) {
            // Enqueue the settings page specific CSS
            wp_enqueue_style( 
                'intellisend-settings-style', 
                INTELLISEND_PLUGIN_URL . 'admin/css/settings-page.css', 
                array(), 
                INTELLISEND_VERSION, 
                'all' 
            );
            
            // Enqueue the settings page specific JS
            wp_enqueue_script( 
                'intellisend-settings-script', 
                INTELLISEND_PLUGIN_URL . 'admin/js/settings-page.js', 
                array( 'jquery' ), 
                INTELLISEND_VERSION, 
                false 
            );
            
            // Add WordPress admin styles
            wp_enqueue_style( 'wp-admin' );
            wp_enqueue_style( 'dashicons' );
        }
    }

    /**
     * Handle AJAX requests for saving settings
     * 
     * @since    1.0.0
     */
    public static function ajax_save_settings() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_settings')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action');
            return;
        }

        // Parse form data
        parse_str($_POST['formData'], $form_data);
        
        // Sanitize and save settings
        $result = IntelliSend_Database::update_settings($form_data);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    /**
     * Handle AJAX requests for testing spam detection
     * 
     * @since    1.0.0
     */
    public static function ajax_test_spam_detection() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_settings')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action');
            return;
        }

        // Get and sanitize parameters
        $api_key = sanitize_text_field($_POST['apiKey']);
        $endpoint = esc_url_raw($_POST['endpoint']);
        $message = sanitize_textarea_field($_POST['message']);
        
        // Validate parameters
        if (empty($api_key) || empty($endpoint) || empty($message)) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        // Decrypt API key if needed
        $decrypted_key = IntelliSend_Database::decrypt_data($api_key);
        if ($decrypted_key) {
            $api_key = $decrypted_key;
        }
        
        // Make API request
        $response = wp_remote_post($endpoint, array(
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key
            ),
            'body' => json_encode(array(
                'message' => $message
            )),
            'cookies' => array()
        ));
        
        // Check for errors
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        // Parse response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['is_spam']) && isset($data['score'])) {
            wp_send_json_success($data);
        } else {
            wp_send_json_error('Invalid API response format');
        }
    }

    /**
     * Handle general AJAX requests
     * 
     * @since    1.0.0
     */
    public static function ajax_handler() {
        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'intellisend_settings')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action');
            return;
        }

        // Get the sub-action
        $sub_action = isset($_POST['sub_action']) ? sanitize_text_field($_POST['sub_action']) : '';
        
        // Handle different sub-actions
        switch ($sub_action) {
            case 'test_email_sent':
                self::handle_test_email();
                break;
            default:
                wp_send_json_error('Invalid action');
                break;
        }
    }

    /**
     * Handle test email sending
     * 
     * @since    1.0.0
     */
    private static function handle_test_email() {
        // Get and sanitize parameters
        $test_email = isset($_POST['test_email']) ? sanitize_email($_POST['test_email']) : '';
        $provider_id = isset($_POST['provider_id']) ? sanitize_text_field($_POST['provider_id']) : '';
        
        // Validate parameters
        if (empty($test_email)) {
            wp_send_json_error('Missing recipient email address');
            return;
        }
        
        // Send test email
        $subject = 'IntelliSend Test Email';
        $message = 'This is a test email from IntelliSend. If you received this email, your email configuration is working correctly.';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Use WordPress mail function or custom SMTP based on provider_id
        $sent = false;
        if ($provider_id === 'wp_mail') {
            $sent = wp_mail($test_email, $subject, $message, $headers);
        } else {
            // Use SMTP provider
            // This would need to be implemented based on your SMTP configuration
            // For now, we'll just use wp_mail as a fallback
            $sent = wp_mail($test_email, $subject, $message, $headers);
        }
        
        if ($sent) {
            wp_send_json_success('Test email sent successfully');
        } else {
            wp_send_json_error('Failed to send test email');
        }
    }

    /**
     * Add settings link to the plugins page
     * 
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public static function add_settings_link( $links ) {
        $settings_link = '<a href="admin.php?page=intellisend">Settings</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}

function intellisend_render_settings_page() {
    require_once INTELLISEND_PLUGIN_DIR . 'admin/views/settings-page.php';
}

function intellisend_render_providers_page() {
    require_once INTELLISEND_PLUGIN_DIR . 'admin/views/providers-page.php';
}

function intellisend_render_routing_page() {
    require_once INTELLISEND_PLUGIN_DIR . 'admin/views/routing-page.php';
}

function intellisend_render_reports_page() {
    require_once INTELLISEND_PLUGIN_DIR . 'admin/views/reports-page.php';
}

// Initialize the admin class - only when in admin area
if ( is_admin() ) {
    add_action( 'plugins_loaded', array( 'IntelliSend_Admin', 'init' ) );
}
