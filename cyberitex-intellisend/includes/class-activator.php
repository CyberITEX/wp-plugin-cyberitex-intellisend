<?php
/**
 * Fired during plugin activation and deactivation
 *
 * @since      1.0.0
 * @package    IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Fired during plugin activation and deactivation.
 *
 * This class defines all code necessary to run during the plugin's activation and deactivation.
 *
 * @since      1.0.0
 * @package    IntelliSend
 */
class IntelliSend_Activator {

    /**
     * Plugin activation.
     *
     * Creates database tables and sets default settings.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Create database tables
        IntelliSend_Database::create_tables();
        
        // Set default settings if not already set
        if ( ! IntelliSend_Database::get_settings() ) {
            $default_settings = array(
                'default_from_email'    => get_option( 'admin_email' ),
                'default_from_name'     => get_option( 'blogname' ),
                'default_recipient'     => get_option( 'admin_email' ),
                'logs_retention_days'   => 365,
                'enable_spam_detection' => 1,
                'enable_logging'        => 1,
                'api_key'               => '',
                'trigger_subjects'      => '',
            );
            
            IntelliSend_Database::update_settings( $default_settings );
        }
    }

    /**
     * Plugin deactivation.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Nothing to do on deactivation
    }
}
