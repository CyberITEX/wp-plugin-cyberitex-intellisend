<?php
/**
 * Fired when the plugin is uninstalled.
 * 
 * @package CyberITEX_Spam_Interceptor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
$options = array(
    'cyberitex_smtp_server',
    'cyberitex_smtp_port',
    'cyberitex_smtp_username',
    'cyberitex_smtp_password',
    'cyberitex_smtp_provider',
    'cyberitex_api_key',
    'cyberitex_si_recipient',
    'cyberitex_subject_recipients',
    'cyberitex_trigger_subjects',
    'cyberitex_logs_retention',
    'cyberitex_si_version'
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove scheduled event
wp_clear_scheduled_hook('cyberitex_si_purge_logs');

// Drop the custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'cyberitex_si_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );