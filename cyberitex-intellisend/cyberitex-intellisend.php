<?php
/**
 * Plugin Name: IntelliSend
 * Description: Advanced email routing, spam protection, and email management with flexible SMTP configuration.
 * Version: 1.0.0
 * Author: CyberITEX
 * Author URI: https://cyberitex.com/
 * Text Domain: cyberitex-intellisend
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'INTELLISEND_VERSION', '1.0.0' );
define( 'INTELLISEND_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INTELLISEND_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Register activation/deactivation hooks
register_activation_hook( __FILE__, 'intellisend_activate' );
register_deactivation_hook( __FILE__, 'intellisend_deactivate' );

/**
 * The code that runs during plugin activation.
 */
function intellisend_activate() {
    // Prevent any hooks from running during activation
    define( 'INTELLISEND_ACTIVATING', true );
    
    require_once INTELLISEND_PLUGIN_DIR . 'includes/class-database.php';
    require_once INTELLISEND_PLUGIN_DIR . 'includes/class-activator.php';
    
    // Create database tables first
    $result = IntelliSend_Database::create_tables();
    
    if ( !$result ) {
        // Log error but don't prevent activation
        error_log( 'IntelliSend: Database tables creation failed during activation' );
    }
    
    // Run activator
    IntelliSend_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function intellisend_deactivate() {
    require_once INTELLISEND_PLUGIN_DIR . 'includes/class-activator.php';
    IntelliSend_Activator::deactivate();
}

/**
 * Begins execution of the plugin.
 */
function run_intellisend() {
    require_once INTELLISEND_PLUGIN_DIR . 'includes/class-intellisend.php';
    $plugin = new IntelliSend();
    $plugin->run();
}

// Start the plugin immediately, but prevent form hooks during activation
if ( ! defined( 'INTELLISEND_ACTIVATING' ) ) {
    run_intellisend();
}