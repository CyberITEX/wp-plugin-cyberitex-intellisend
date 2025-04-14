<?php

/**
 * IntelliSend Admin
 *
 * @package IntelliSend
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Include admin page files - only when in admin area to prevent activation issues
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'class-ajax.php';
}

/**
 * IntelliSend Admin Class
 */
class IntelliSend_Admin
{

    /**
     * Initialize the admin class
     */
    public static function init()
    {
        // Register hooks
        add_action('admin_menu', array(__CLASS__, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));

        // Add settings link to plugins page
        add_filter(
            'plugin_action_links_' . plugin_basename(INTELLISEND_PLUGIN_DIR . 'cyberitex-intellisend.php'),
            array(__CLASS__, 'add_settings_link')
        );

        // Initialize AJAX handler - only when in admin area
        if (is_admin() && class_exists('IntelliSend_Ajax')) {
            IntelliSend_Ajax::init();
        }
    }

    /**
     * Register the admin menu page.
     *
     * @since    1.0.0
     */
    public static function register_admin_menu()
    {
        // Main menu
        add_menu_page(
            'IntelliSend',
            'IntelliSend',
            'manage_options',
            'intellisend',
            array(__CLASS__, 'render_settings_page'),
            'dashicons-email',
            30
        );

        // Submenu items - first item with same slug as parent to replace default submenu
        add_submenu_page(
            'intellisend',
            'Settings',
            'Settings',
            'manage_options',
            'intellisend',
            array(__CLASS__, 'render_settings_page')
        );

        add_submenu_page(
            'intellisend',
            'SMTP Providers',
            'SMTP Providers',
            'manage_options',
            'intellisend-providers',
            array(__CLASS__, 'render_providers_page')
        );

        add_submenu_page(
            'intellisend',
            'Email Routing',
            'Email Routing',
            'manage_options',
            'intellisend-routing',
            array(__CLASS__, 'render_routing_page')
        );

        add_submenu_page(
            'intellisend',
            'Email Reports',
            'Reports',
            'manage_options',
            'intellisend-reports',
            array(__CLASS__, 'render_reports_page')
        );
    }

    /**
     * Render the admin pages by including the appropriate view files
     */
    public static function render_settings_page()
    {
        require_once INTELLISEND_PLUGIN_DIR . 'admin/views/settings-page.php';
        if (function_exists('intellisend_render_settings_page_content')) {
            intellisend_render_settings_page_content();
        }
    }

    public static function render_providers_page()
    {
        require_once INTELLISEND_PLUGIN_DIR . 'admin/views/providers-page.php';
        if (function_exists('intellisend_render_providers_page_content')) {
            intellisend_render_providers_page_content();
        }
    }

    public static function render_routing_page()
    {
        require_once INTELLISEND_PLUGIN_DIR . 'admin/views/routing-page.php';
        if (function_exists('intellisend_render_routing_page_content')) {
            intellisend_render_routing_page_content();
        }
    }

    public static function render_reports_page()
    {
        require_once INTELLISEND_PLUGIN_DIR . 'admin/views/reports-page.php';
        if (function_exists('intellisend_render_reports_page_content')) {
            intellisend_render_reports_page_content();
        }
    }

    /**
     * Register the JavaScript and CSS for the admin area.
     *
     * @param string $hook The current admin page.
     */
    public static function enqueue_admin_scripts($hook)
    {
        // Only load on plugin pages
        if (strpos($hook, 'intellisend') !== false) {
            // Always load the main admin CSS and JS
            wp_enqueue_style(
                'intellisend-admin-style',
                INTELLISEND_PLUGIN_URL . 'admin/css/intellisend-admin.css',
                array(),
                INTELLISEND_VERSION,
                'all'
            );

            wp_enqueue_script(
                'intellisend-admin-script',
                INTELLISEND_PLUGIN_URL . 'admin/js/intellisend-admin.js',
                array('jquery'),
                INTELLISEND_VERSION,
                false
            );

            // Localize script with AJAX data
            wp_localize_script(
                'intellisend-admin-script',
                'intellisendData',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('intellisend_nonce'),
                    'strings' => array(
                        'confirm_delete' => __('Are you sure you want to delete this item?', 'intellisend')
                    )
                )
            );

            // Add WordPress admin styles
            wp_enqueue_style('wp-admin');
            wp_enqueue_style('dashicons');

            // Load page-specific files
            if ($hook === 'toplevel_page_intellisend') {
                // Settings page
                wp_enqueue_style(
                    'intellisend-settings-style',
                    INTELLISEND_PLUGIN_URL . 'admin/css/settings-page.css',
                    array(),
                    INTELLISEND_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'intellisend-settings-script',
                    INTELLISEND_PLUGIN_URL . 'admin/js/settings-page.js',
                    array('jquery'),
                    INTELLISEND_VERSION,
                    false
                );
            } elseif ($hook === 'intellisend_page_intellisend-providers') {
                wp_enqueue_style(
                    'intellisend-providers-style',
                    INTELLISEND_PLUGIN_URL . 'admin/css/providers-page.css',
                    array(),
                    INTELLISEND_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'intellisend-providers-script',
                    INTELLISEND_PLUGIN_URL . 'admin/js/providers-page.js',
                    array('jquery'),
                    INTELLISEND_VERSION,
                    false
                );
            } elseif ($hook === 'intellisend_page_intellisend-routing') {
                wp_enqueue_style(
                    'intellisend-routes-style',
                    INTELLISEND_PLUGIN_URL . 'admin/css/routing-page.css',
                    array(),
                    INTELLISEND_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'intellisend-providers-script',
                    INTELLISEND_PLUGIN_URL . 'admin/js/routing-page.js',
                    array('jquery'),
                    INTELLISEND_VERSION,
                    false
                );
            } elseif ($hook === 'intellisend_page_intellisend-reports') {
                wp_enqueue_style(
                    'intellisend-reports-style',
                    INTELLISEND_PLUGIN_URL . 'admin/css/reports-page.css',
                    array(),
                    INTELLISEND_VERSION,
                    'all'
                );

                wp_enqueue_script(
                    'intellisend-reports-script',
                    INTELLISEND_PLUGIN_URL . 'admin/js/reports-page.js',
                    array('jquery'),
                    INTELLISEND_VERSION,
                    false
                );
            }
        }
    }

    /**
     * Add settings link to the plugins page
     * 
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public static function add_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=intellisend">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the admin class - only when in admin area
if (is_admin()) {
    add_action('plugins_loaded', array('IntelliSend_Admin', 'init'));
}
