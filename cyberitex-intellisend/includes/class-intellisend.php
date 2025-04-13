<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * The core plugin class.
 */
class IntelliSend {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      IntelliSend_Admin    $admin    Maintains and registers all hooks for the admin area.
     */
    protected $admin;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - IntelliSend_Admin. Defines all hooks for the admin area.
     * - IntelliSend_Form. Defines all hooks for email interception and processing.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        // Core plugin functionality
        require_once INTELLISEND_PLUGIN_DIR . 'includes/class-database.php';
        require_once INTELLISEND_PLUGIN_DIR . 'includes/class-form.php';
        require_once INTELLISEND_PLUGIN_DIR . 'includes/class-spamcheck.php';

        // Admin functionality
        if ( is_admin() ) {
            require_once INTELLISEND_PLUGIN_DIR . 'admin/class-admin.php';
            require_once INTELLISEND_PLUGIN_DIR . 'admin/class-ajax.php';
        }
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        if ( is_admin() ) {
            $this->admin = new IntelliSend_Admin();
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        // Initialize the form class to handle email interception
        IntelliSend_Form::init();
    }

    /**
     * Run the plugin.
     *
     * @since    1.0.0
     */
    public function run() {
        // This method is intentionally left empty as all hooks are registered in the constructor
    }
}
