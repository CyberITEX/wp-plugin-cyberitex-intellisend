<?php

/**
 * AJAX handlers for IntelliSend routing page
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Get routing rule details
 */
function intellisend_ajax_get_routing_rule()
{
    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }

    // Check capabilities
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }

    // Get rule ID
    $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (! $rule_id) {
        wp_send_json_error(__('Invalid rule ID.', 'intellisend'));
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule($rule_id);
    if (! $rule) {
        wp_send_json_error(__('Rule not found.', 'intellisend'));
    }

    // Get configured providers to validate the rule's provider
    $providers = IntelliSend_Database::get_providers(array('configured' => 1));
    $provider_names = array_map(function ($provider) {
        return $provider->name;
    }, $providers);

    // Add a flag to indicate if the rule's provider is still configured
    $rule->providerConfigured = in_array($rule->defaultProviderName, $provider_names);

    // Send response
    wp_send_json_success($rule);
}
add_action('wp_ajax_intellisend_get_routing_rule', 'intellisend_ajax_get_routing_rule');

/**
 * Add new routing rule
 */
function intellisend_ajax_add_routing_rule()
{
    // Enable error logging
    error_log('=== INTELLISEND ADD ROUTING RULE START ===');
    error_log('POST data: ' . print_r($_POST, true));

    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        error_log('IntelliSend: Nonce validation failed');
        error_log('Received nonce: ' . (isset($_POST['nonce']) ? $_POST['nonce'] : 'NONE'));
        error_log('Expected nonce action: intellisend_routing_nonce');
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }
    error_log('IntelliSend: Nonce validation passed');

    // Check capabilities
    if (! current_user_can('manage_options')) {
        error_log('IntelliSend: User capability check failed');
        error_log('Current user ID: ' . get_current_user_id());
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }
    error_log('IntelliSend: User capability check passed');

    // Check if formData exists
    if (!isset($_POST['formData'])) {
        error_log('IntelliSend: No formData in POST request');
        wp_send_json_error(__('No form data received.', 'intellisend'));
    }
    error_log('IntelliSend: formData found: ' . $_POST['formData']);

    // Parse form data
    parse_str($_POST['formData'], $form_data);
    error_log('IntelliSend: Parsed form data: ' . print_r($form_data, true));

    // Validate required fields with detailed logging
    if (empty($form_data['rule_name'])) {
        error_log('IntelliSend: rule_name is empty or missing');
        error_log('rule_name value: ' . var_export($form_data['rule_name'] ?? 'NOT_SET', true));
        error_log('All form_data keys: ' . print_r(array_keys($form_data), true));
        wp_send_json_error(__('Rule name is required.', 'intellisend'));
    }
    error_log('IntelliSend: rule_name validation passed: ' . $form_data['rule_name']);

    if (empty($form_data['rule_provider'])) {
        error_log('IntelliSend: rule_provider is empty or missing');
        error_log('rule_provider value: ' . var_export($form_data['rule_provider'] ?? 'NOT_SET', true));
        wp_send_json_error(__('Provider is required.', 'intellisend'));
    }
    error_log('IntelliSend: rule_provider validation passed: ' . $form_data['rule_provider']);

    if (empty($form_data['rule_patterns'])) {
        error_log('IntelliSend: rule_patterns is empty or missing');
        error_log('rule_patterns value: ' . var_export($form_data['rule_patterns'] ?? 'NOT_SET', true));
        wp_send_json_error(__('At least one pattern is required.', 'intellisend'));
    }
    error_log('IntelliSend: rule_patterns validation passed: ' . $form_data['rule_patterns']);

    // Prepare rule data
    $rule = new stdClass();
    $rule->name = sanitize_text_field($form_data['rule_name']);
    $rule->defaultProviderName = sanitize_text_field($form_data['rule_provider']);
    $rule->subjectPatterns = sanitize_textarea_field($form_data['rule_patterns']);
    $rule->priority = isset($form_data['rule_priority']) ? intval($form_data['rule_priority']) : 10;
    $rule->enabled = isset($form_data['rule_enabled']) ? 1 : 0;
    $rule->antiSpamEnabled = isset($form_data['rule_antispam']) ? 1 : 0;

    error_log('IntelliSend: Prepared rule object: ' . print_r($rule, true));

    // Check if IntelliSend_Database class exists
    if (!class_exists('IntelliSend_Database')) {
        error_log('IntelliSend: IntelliSend_Database class not found');
        wp_send_json_error(__('Database class not available.', 'intellisend'));
    }
    error_log('IntelliSend: IntelliSend_Database class found');

    // Check if add_routing_rule method exists
    if (!method_exists('IntelliSend_Database', 'add_routing_rule')) {
        error_log('IntelliSend: add_routing_rule method not found in IntelliSend_Database');
        error_log('Available methods: ' . print_r(get_class_methods('IntelliSend_Database'), true));
        wp_send_json_error(__('Database method not available.', 'intellisend'));
    }
    error_log('IntelliSend: add_routing_rule method found');

    // Add rule
    error_log('IntelliSend: Attempting to add rule to database');
    $result = IntelliSend_Database::add_routing_rule($rule);
    error_log('IntelliSend: Database add result: ' . var_export($result, true));

    if (! $result) {
        error_log('IntelliSend: Failed to add routing rule to database');
        // Get the last database error if available
        global $wpdb;
        if ($wpdb->last_error) {
            error_log('IntelliSend: Database error: ' . $wpdb->last_error);
        }
        wp_send_json_error(__('Failed to add routing rule. Please try again.', 'intellisend'));
    }

    error_log('IntelliSend: Rule added successfully with ID: ' . $result);
    error_log('=== INTELLISEND ADD ROUTING RULE SUCCESS ===');

    // Send success response
    wp_send_json_success(__('Routing rule added successfully.', 'intellisend'));
}
add_action('wp_ajax_intellisend_add_routing_rule', 'intellisend_ajax_add_routing_rule');

/**
 * Update routing rule
 */
function intellisend_ajax_update_routing_rule()
{
    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }

    // Check capabilities
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }

    // Parse form data
    parse_str($_POST['formData'], $form_data);

    // Validate rule ID
    if (empty($form_data['rule_id'])) {
        wp_send_json_error(__('Rule ID is required.', 'intellisend'));
    }

    // Validate required fields
    if (empty($form_data['rule_name'])) {
        wp_send_json_error(__('Rule name is required.', 'intellisend'));
    }

    if (empty($form_data['rule_provider'])) {
        wp_send_json_error(__('Provider is required.', 'intellisend'));
    }

    if (empty($form_data['rule_patterns'])) {
        wp_send_json_error(__('At least one pattern is required.', 'intellisend'));
    }

    // Get existing rule
    $rule_id = intval($form_data['rule_id']);
    $existing_rule = IntelliSend_Database::get_routing_rule($rule_id);
    if (! $existing_rule) {
        wp_send_json_error(__('Rule not found.', 'intellisend'));
    }

    // Prepare rule data
    $rule = new stdClass();
    $rule->id = intval($form_data['rule_id']);
    $rule->name = sanitize_text_field($form_data['rule_name']);
    $rule->defaultProviderName = sanitize_text_field($form_data['rule_provider']);
    $rule->subjectPatterns = sanitize_textarea_field($form_data['rule_patterns']);
    $rule->priority = isset($form_data['rule_priority']) ? intval($form_data['rule_priority']) : $existing_rule->priority;
    $rule->enabled = isset($form_data['rule_enabled']) ? 1 : 0;
    $rule->antiSpamEnabled = isset($form_data['rule_antispam']) ? 1 : 0;

    // Update rule
    $result = IntelliSend_Database::update_routing_rule($rule);
    if (! $result) {
        wp_send_json_error(__('Failed to update routing rule. Please try again.', 'intellisend'));
    }

    // Send success response
    wp_send_json_success(__('Routing rule updated successfully.', 'intellisend'));
}
add_action('wp_ajax_intellisend_update_routing_rule', 'intellisend_ajax_update_routing_rule');

/**
 * Delete routing rule
 */
function intellisend_ajax_delete_routing_rule()
{
    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }

    // Check capabilities
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }

    // Get rule ID
    $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (! $rule_id) {
        wp_send_json_error(__('Invalid rule ID.', 'intellisend'));
    }

    // Get rule data to check if it's the default rule
    $rule = IntelliSend_Database::get_routing_rule($rule_id);
    if (! $rule) {
        wp_send_json_error(__('Rule not found.', 'intellisend'));
    }

    // Don't allow deleting the default rule
    if ($rule->priority == -1) {
        wp_send_json_error(__('The default rule cannot be deleted.', 'intellisend'));
    }

    // Delete rule
    $result = IntelliSend_Database::delete_routing_rule($rule_id);
    if (! $result) {
        wp_send_json_error(__('Failed to delete routing rule. Please try again.', 'intellisend'));
    }

    // Send success response
    wp_send_json_success(__('Routing rule deleted successfully.', 'intellisend'));
}
add_action('wp_ajax_intellisend_delete_routing_rule', 'intellisend_ajax_delete_routing_rule');

/**
 * Activate routing rule
 */
function intellisend_ajax_activate_routing_rule()
{
    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }

    // Check capabilities
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }

    // Get rule ID
    $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (! $rule_id) {
        wp_send_json_error(__('Invalid rule ID.', 'intellisend'));
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule($rule_id);
    if (! $rule) {
        wp_send_json_error(__('Rule not found.', 'intellisend'));
    }

    // Update rule status
    $rule->enabled = 1;
    $result = IntelliSend_Database::update_routing_rule($rule);
    if (! $result) {
        wp_send_json_error(__('Failed to activate routing rule. Please try again.', 'intellisend'));
    }

    // Send success response
    wp_send_json_success(__('Routing rule activated successfully.', 'intellisend'));
}
add_action('wp_ajax_intellisend_activate_routing_rule', 'intellisend_ajax_activate_routing_rule');

/**
 * Deactivate routing rule
 */
function intellisend_ajax_deactivate_routing_rule()
{
    // Check nonce
    if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'intellisend_routing_nonce')) {
        wp_send_json_error(__('Security check failed.', 'intellisend'));
    }

    // Check capabilities
    if (! current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'intellisend'));
    }

    // Get rule ID
    $rule_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (! $rule_id) {
        wp_send_json_error(__('Invalid rule ID.', 'intellisend'));
    }

    // Get rule data
    $rule = IntelliSend_Database::get_routing_rule($rule_id);
    if (! $rule) {
        wp_send_json_error(__('Rule not found.', 'intellisend'));
    }

    // Update rule status
    $rule->enabled = 0;
    $result = IntelliSend_Database::update_routing_rule($rule);
    if (! $result) {
        wp_send_json_error(__('Failed to deactivate routing rule. Please try again.', 'intellisend'));
    }

    // Send success response
    wp_send_json_success(__('Routing rule deactivated successfully.', 'intellisend'));
}
add_action('wp_ajax_intellisend_deactivate_routing_rule', 'intellisend_ajax_deactivate_routing_rule');
