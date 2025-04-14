<?php
/**
 * AJAX handlers for IntelliSend routing page
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Get routing rule details
 */
function intellisend_ajax_get_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Get rule ID
    $rule_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    if ( ! $rule_id ) {
        wp_send_json_error( __( 'Invalid rule ID.', 'intellisend' ) );
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule( $rule_id );
    if ( ! $rule ) {
        wp_send_json_error( __( 'Rule not found.', 'intellisend' ) );
    }

    // Send response
    wp_send_json_success( $rule );
}
add_action( 'wp_ajax_intellisend_get_routing_rule', 'intellisend_ajax_get_routing_rule' );

/**
 * Add new routing rule
 */
function intellisend_ajax_add_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Parse form data
    parse_str( $_POST['formData'], $form_data );

    // Validate required fields
    if ( empty( $form_data['rule_name'] ) ) {
        wp_send_json_error( __( 'Rule name is required.', 'intellisend' ) );
    }

    if ( empty( $form_data['rule_provider'] ) ) {
        wp_send_json_error( __( 'Provider is required.', 'intellisend' ) );
    }

    if ( empty( $form_data['rule_patterns'] ) ) {
        wp_send_json_error( __( 'At least one pattern is required.', 'intellisend' ) );
    }

    // Prepare rule data
    $rule = new stdClass();
    $rule->name = sanitize_text_field( $form_data['rule_name'] );
    $rule->defaultProviderName = sanitize_text_field( $form_data['rule_provider'] );
    $rule->subjectPatterns = sanitize_textarea_field( $form_data['rule_patterns'] );
    $rule->priority = isset( $form_data['rule_priority'] ) ? intval( $form_data['rule_priority'] ) : 10;
    $rule->enabled = isset( $form_data['rule_enabled'] ) ? 1 : 0;

    // Add rule
    $result = IntelliSend_Database::add_routing_rule( $rule );
    if ( ! $result ) {
        wp_send_json_error( __( 'Failed to add routing rule. Please try again.', 'intellisend' ) );
    }

    // Send success response
    wp_send_json_success( __( 'Routing rule added successfully.', 'intellisend' ) );
}
add_action( 'wp_ajax_intellisend_add_routing_rule', 'intellisend_ajax_add_routing_rule' );

/**
 * Update routing rule
 */
function intellisend_ajax_update_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Parse form data
    parse_str( $_POST['formData'], $form_data );

    // Validate rule ID
    if ( empty( $form_data['rule_id'] ) ) {
        wp_send_json_error( __( 'Rule ID is required.', 'intellisend' ) );
    }

    // Validate required fields
    if ( empty( $form_data['rule_name'] ) ) {
        wp_send_json_error( __( 'Rule name is required.', 'intellisend' ) );
    }

    if ( empty( $form_data['rule_provider'] ) ) {
        wp_send_json_error( __( 'Provider is required.', 'intellisend' ) );
    }

    if ( empty( $form_data['rule_patterns'] ) ) {
        wp_send_json_error( __( 'At least one pattern is required.', 'intellisend' ) );
    }

    // Get existing rule
    $rule_id = intval( $form_data['rule_id'] );
    $existing_rule = IntelliSend_Database::get_routing_rule( $rule_id );
    if ( ! $existing_rule ) {
        wp_send_json_error( __( 'Rule not found.', 'intellisend' ) );
    }

    // Prepare rule data
    $rule = new stdClass();
    $rule->id = $rule_id;
    $rule->name = sanitize_text_field( $form_data['rule_name'] );
    $rule->defaultProviderName = sanitize_text_field( $form_data['rule_provider'] );
    $rule->subjectPatterns = sanitize_textarea_field( $form_data['rule_patterns'] );
    $rule->priority = isset( $form_data['rule_priority'] ) ? intval( $form_data['rule_priority'] ) : $existing_rule->priority;
    $rule->enabled = isset( $form_data['rule_enabled'] ) ? 1 : 0;

    // Update rule
    $result = IntelliSend_Database::update_routing_rule( $rule );
    if ( ! $result ) {
        wp_send_json_error( __( 'Failed to update routing rule. Please try again.', 'intellisend' ) );
    }

    // Send success response
    wp_send_json_success( __( 'Routing rule updated successfully.', 'intellisend' ) );
}
add_action( 'wp_ajax_intellisend_update_routing_rule', 'intellisend_ajax_update_routing_rule' );

/**
 * Delete routing rule
 */
function intellisend_ajax_delete_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Get rule ID
    $rule_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    if ( ! $rule_id ) {
        wp_send_json_error( __( 'Invalid rule ID.', 'intellisend' ) );
    }

    // Get rule data to check if it's the default rule
    $rule = IntelliSend_Database::get_routing_rule( $rule_id );
    if ( ! $rule ) {
        wp_send_json_error( __( 'Rule not found.', 'intellisend' ) );
    }

    // Don't allow deleting the default rule
    if ( $rule->priority == -1 ) {
        wp_send_json_error( __( 'The default rule cannot be deleted.', 'intellisend' ) );
    }

    // Delete rule
    $result = IntelliSend_Database::delete_routing_rule( $rule_id );
    if ( ! $result ) {
        wp_send_json_error( __( 'Failed to delete routing rule. Please try again.', 'intellisend' ) );
    }

    // Send success response
    wp_send_json_success( __( 'Routing rule deleted successfully.', 'intellisend' ) );
}
add_action( 'wp_ajax_intellisend_delete_routing_rule', 'intellisend_ajax_delete_routing_rule' );

/**
 * Activate routing rule
 */
function intellisend_ajax_activate_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Get rule ID
    $rule_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    if ( ! $rule_id ) {
        wp_send_json_error( __( 'Invalid rule ID.', 'intellisend' ) );
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule( $rule_id );
    if ( ! $rule ) {
        wp_send_json_error( __( 'Rule not found.', 'intellisend' ) );
    }

    // Update rule status
    $rule->enabled = 1;
    $result = IntelliSend_Database::update_routing_rule( $rule );
    if ( ! $result ) {
        wp_send_json_error( __( 'Failed to activate routing rule. Please try again.', 'intellisend' ) );
    }

    // Send success response
    wp_send_json_success( __( 'Routing rule activated successfully.', 'intellisend' ) );
}
add_action( 'wp_ajax_intellisend_activate_routing_rule', 'intellisend_ajax_activate_routing_rule' );

/**
 * Deactivate routing rule
 */
function intellisend_ajax_deactivate_routing_rule() {
    // Check nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'intellisend_routing_nonce' ) ) {
        wp_send_json_error( __( 'Security check failed.', 'intellisend' ) );
    }

    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'You do not have permission to perform this action.', 'intellisend' ) );
    }

    // Get rule ID
    $rule_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    if ( ! $rule_id ) {
        wp_send_json_error( __( 'Invalid rule ID.', 'intellisend' ) );
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule( $rule_id );
    if ( ! $rule ) {
        wp_send_json_error( __( 'Rule not found.', 'intellisend' ) );
    }

    // Update rule status
    $rule->enabled = 0;
    $result = IntelliSend_Database::update_routing_rule( $rule );
    if ( ! $result ) {
        wp_send_json_error( __( 'Failed to deactivate routing rule. Please try again.', 'intellisend' ) );
    }

    // Send success response
    wp_send_json_success( __( 'Routing rule deactivated successfully.', 'intellisend' ) );
}
add_action( 'wp_ajax_intellisend_deactivate_routing_rule', 'intellisend_ajax_deactivate_routing_rule' );
