<?php
/**
 * Admin page for managing email routing rules
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the routing page content
 */
function intellisend_render_routing_page_content()
{
    // Get all routing rules
    $routing_rules = IntelliSend_Database::get_routing_rules();

    // Get all configured providers
    $providers = IntelliSend_Database::get_providers(array('configured' => 1));
    ?>
    <div class="wrap intellisend-admin">
        <h1><?php echo esc_html__('Email Routing', 'intellisend'); ?></h1>
        
        <?php if (empty($providers)) : ?>
            <div class="intellisend-notice warning">
                <span class="intellisend-notice-icon dashicons dashicons-warning"></span>
                <div class="intellisend-notice-content">
                    <?php echo esc_html__('No configured SMTP providers found. Please configure at least one provider in the SMTP Providers section before setting up routing rules.', 'intellisend'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=intellisend-providers')); ?>" class="button button-secondary"><?php echo esc_html__('Configure Providers', 'intellisend'); ?></a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2><?php echo esc_html__('Manage Routing Rules', 'intellisend'); ?></h2>
                <p><?php echo esc_html__('Configure rules to route emails through different SMTP providers based on recipient or subject patterns.', 'intellisend'); ?></p>
                
                <div class="intellisend-routing-list">
                    <table class="intellisend-table" id="routing-rules-table">
                        <thead>
                            <tr>
                                <th class="col-name"><?php echo esc_html__('Name', 'intellisend'); ?></th>
                                <th class="col-pattern"><?php echo esc_html__('Pattern', 'intellisend'); ?></th>
                                <th class="col-provider"><?php echo esc_html__('Provider', 'intellisend'); ?></th>
                                <th class="col-recipients"><?php echo esc_html__('Recipients', 'intellisend'); ?></th>
                                <th class="col-priority"><?php echo esc_html__('Priority', 'intellisend'); ?></th>
                                <th class="col-status"><?php echo esc_html__('Status', 'intellisend'); ?></th>
                                <th class="col-antispam"><?php echo esc_html__('Anti-Spam', 'intellisend'); ?></th>
                                <th class="col-actions"><?php echo esc_html__('Actions', 'intellisend'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($routing_rules)) : ?>
                                <tr>
                                    <td colspan="8"><?php echo esc_html__('No routing rules found. Add your first rule below.', 'intellisend'); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($routing_rules as $rule) : 
                                    $is_default_rule = ($rule->id == 1 || $rule->priority == -1);
                                    $provider_name = '';
                                    foreach ($providers as $provider) {
                                        if ($provider->name == $rule->defaultProviderName) {
                                            $provider_name = $provider->name;
                                            break;
                                        }
                                    }
                                ?>
                                    <tr class="rule-row" data-id="<?php echo esc_attr($rule->id); ?>" data-is-default="<?php echo $is_default_rule ? '1' : '0'; ?>">
                                        <td class="editable" data-field="name">
                                            <span class="view-mode"><?php echo esc_html($rule->name); ?></span>
                                            <input type="text" class="edit-mode rule-name" value="<?php echo esc_attr($rule->name); ?>" style="display:none;">
                                            <?php if ($is_default_rule) : ?>
                                                <span class="default-badge"><?php echo esc_html__('Default', 'intellisend'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="editable" data-field="patterns">
                                            <span class="view-mode"><?php echo esc_html($rule->subjectPatterns); ?></span>
                                            <textarea class="edit-mode rule-patterns" style="display:none;"><?php echo esc_textarea($rule->subjectPatterns); ?></textarea>
                                        </td>
                                        <td class="editable" data-field="provider">
                                            <span class="view-mode"><?php echo esc_html($provider_name); ?></span>
                                            <select class="edit-mode rule-provider" style="display:none;">
                                                <?php foreach ($providers as $provider) : ?>
                                                    <option value="<?php echo esc_attr($provider->name); ?>" <?php selected($rule->defaultProviderName, $provider->name); ?>>
                                                        <?php echo esc_html($provider->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="editable" data-field="recipients">
                                            <span class="view-mode"><?php echo esc_html(!empty($rule->recipients) ? $rule->recipients : ''); ?></span>
                                            <div class="edit-mode recipients-container" style="display:none;">
                                                <input type="text" class="rule-recipients-input" placeholder="<?php echo esc_attr__('Add email, press Enter', 'intellisend'); ?>">
                                                <div class="recipients-tags">
                                                    <?php 
                                                    if (!empty($rule->recipients)) {
                                                        $recipients = explode(',', $rule->recipients);
                                                        foreach ($recipients as $recipient) {
                                                            $recipient = trim($recipient);
                                                            if (!empty($recipient)) {
                                                                echo '<span class="recipient-tag">' . esc_html($recipient) . '<span class="remove-recipient">×</span></span>';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <input type="hidden" class="rule-recipients" value="<?php echo esc_attr($rule->recipients ?? ''); ?>">
                                            </div>
                                        </td>
                                        <td class="editable" data-field="priority">
                                            <span class="view-mode"><?php echo $is_default_rule ? 'Default' : esc_html($rule->priority); ?></span>
                                            <?php if (!$is_default_rule) : ?>
                                                <input type="number" class="edit-mode rule-priority" value="<?php echo esc_attr($rule->priority); ?>" min="1" max="100" style="display:none;">
                                            <?php else : ?>
                                                <input type="hidden" class="rule-priority" value="-1">
                                            <?php endif; ?>
                                        </td>
                                        <td class="editable" data-field="enabled">
                                            <span class="view-mode">
                                                <?php if ($rule->enabled) : ?>
                                                    <span class="status-active"><?php echo esc_html__('Active', 'intellisend'); ?></span>
                                                <?php else : ?>
                                                    <span class="status-inactive"><?php echo esc_html__('Inactive', 'intellisend'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <select class="edit-mode rule-enabled" style="display:none;">
                                                <option value="1" <?php selected($rule->enabled, 1); ?>><?php echo esc_html__('Active', 'intellisend'); ?></option>
                                                <option value="0" <?php selected($rule->enabled, 0); ?>><?php echo esc_html__('Inactive', 'intellisend'); ?></option>
                                            </select>
                                        </td>
                                        <td class="editable" data-field="antispam">
                                            <span class="view-mode">
                                                <?php if ($rule->antiSpamEnabled) : ?>
                                                    <span class="antispam-active"><?php echo esc_html__('On', 'intellisend'); ?></span>
                                                <?php else : ?>
                                                    <span class="antispam-inactive"><?php echo esc_html__('Off', 'intellisend'); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <select class="edit-mode rule-antispam" style="display:none;">
                                                <option value="1" <?php selected($rule->antiSpamEnabled, 1); ?>><?php echo esc_html__('On', 'intellisend'); ?></option>
                                                <option value="0" <?php selected($rule->antiSpamEnabled, 0); ?>><?php echo esc_html__('Off', 'intellisend'); ?></option>
                                            </select>
                                        </td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <button type="button" class="action-button edit-rule" title="<?php echo esc_attr__('Edit', 'intellisend'); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                
                                                <button type="button" class="action-button save-rule" style="display:none;" title="<?php echo esc_attr__('Save', 'intellisend'); ?>">
                                                    <span class="dashicons dashicons-saved"></span>
                                                </button>
                                                
                                                <button type="button" class="action-button cancel-edit" style="display:none;" title="<?php echo esc_attr__('Cancel', 'intellisend'); ?>">
                                                    <span class="dashicons dashicons-no"></span>
                                                </button>
                                                
                                                <button type="button" class="action-button duplicate-rule" title="<?php echo esc_attr__('Duplicate', 'intellisend'); ?>">
                                                    <span class="dashicons dashicons-admin-page"></span>
                                                </button>
                                                
                                                <?php if ($rule->enabled) : ?>
                                                    <button type="button" class="action-button deactivate-rule" data-id="<?php echo esc_attr($rule->id); ?>" title="<?php echo esc_attr__('Deactivate', 'intellisend'); ?>">
                                                        <span class="dashicons dashicons-hidden"></span>
                                                    </button>
                                                <?php else : ?>
                                                    <button type="button" class="action-button activate-rule" data-id="<?php echo esc_attr($rule->id); ?>" title="<?php echo esc_attr__('Activate', 'intellisend'); ?>">
                                                        <span class="dashicons dashicons-visibility"></span>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!$is_default_rule) : ?>
                                                    <button type="button" class="action-button delete-rule" data-id="<?php echo esc_attr($rule->id); ?>" data-name="<?php echo esc_attr($rule->name); ?>" title="<?php echo esc_attr__('Delete', 'intellisend'); ?>">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="add-rule-button">
                    <button type="button" id="add-rule-btn" class="button button-primary">
                        <span class="dashicons dashicons-plus"></span>
                        <?php echo esc_html__('Add New Routing Rule', 'intellisend'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Rule Template (hidden) -->
    <template id="new-rule-template">
        <tr class="rule-row new-rule-row" data-id="new" data-is-default="0">
            <td class="editable" data-field="name">
                <span class="view-mode"></span>
                <input type="text" class="edit-mode rule-name" value="" placeholder="<?php echo esc_attr__('Rule Name', 'intellisend'); ?>">
            </td>
            <td class="editable" data-field="patterns">
                <span class="view-mode"></span>
                <textarea class="edit-mode rule-patterns" placeholder="<?php echo esc_attr__('e.g. *@example.com, newsletter*', 'intellisend'); ?>"></textarea>
            </td>
            <td class="editable" data-field="provider">
                <span class="view-mode"></span>
                <select class="edit-mode rule-provider">
                    <option value=""><?php echo esc_html__('Select Provider', 'intellisend'); ?></option>
                    <?php foreach ($providers as $provider) : ?>
                        <option value="<?php echo esc_attr($provider->name); ?>">
                            <?php echo esc_html($provider->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="editable" data-field="recipients">
                <span class="view-mode"></span>
                <div class="edit-mode recipients-container">
                    <input type="text" class="rule-recipients-input" placeholder="<?php echo esc_attr__('Add email, press Enter', 'intellisend'); ?>">
                    <div class="recipients-tags"></div>
                    <input type="hidden" class="rule-recipients" value="">
                </div>
            </td>
            <td class="editable" data-field="priority">
                <span class="view-mode"></span>
                <input type="number" class="edit-mode rule-priority" value="10" min="1" max="100">
            </td>
            <td class="editable" data-field="enabled">
                <span class="view-mode"></span>
                <select class="edit-mode rule-enabled">
                    <option value="1" selected><?php echo esc_html__('Active', 'intellisend'); ?></option>
                    <option value="0"><?php echo esc_html__('Inactive', 'intellisend'); ?></option>
                </select>
            </td>
            <td class="editable" data-field="antispam">
                <span class="view-mode"></span>
                <select class="edit-mode rule-antispam">
                    <option value="1" selected><?php echo esc_html__('On', 'intellisend'); ?></option>
                    <option value="0"><?php echo esc_html__('Off', 'intellisend'); ?></option>
                </select>
            </td>
            <td class="actions">
                <div class="action-buttons">
                    <button type="button" class="action-button save-new-rule" title="<?php echo esc_attr__('Save', 'intellisend'); ?>">
                        <span class="dashicons dashicons-saved"></span>
                    </button>
                    
                    <button type="button" class="action-button cancel-new-rule" title="<?php echo esc_attr__('Cancel', 'intellisend'); ?>">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
            </td>
        </tr>
    </template>
    
    <!-- Loading spinner template -->
    <template id="loading-overlay-template">
        <div class="intellisend-loading-overlay">
            <div class="intellisend-loading-spinner"></div>
        </div>
    </template>
    <?php
}

/**
 * Enqueue scripts and styles for the routing page
 */
add_action('admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'intellisend-routing') {
        wp_enqueue_style('intellisend-routing-css', INTELLISEND_PLUGIN_URL . 'admin/css/routing-page.css', array(), INTELLISEND_VERSION);
        wp_enqueue_script('intellisend-routing-js', INTELLISEND_PLUGIN_URL . 'admin/js/routing-page.js', array('jquery'), INTELLISEND_VERSION, true);
        
        // Localize script with necessary data
        wp_localize_script(
            'intellisend-routing-js',
            'intellisendData',
            array(
                'nonce' => wp_create_nonce('intellisend_routing_nonce'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'strings' => array(
                    'saveSuccess' => __('Routing rule saved successfully.', 'intellisend'),
                    'saveFailed' => __('Failed to save routing rule.', 'intellisend'),
                    'confirmDelete' => __('Are you sure you want to delete this routing rule? This action cannot be undone.', 'intellisend'),
                    'invalidRecipient' => __('Invalid email address format.', 'intellisend'),
                    'requiredField' => __('This field is required.', 'intellisend'),
                    'unconfiguredProvider' => __('The provider "%s" is not configured properly.', 'intellisend'),
                    'noConfiguredProviders' => __('No configured SMTP providers found.', 'intellisend'),
                )
            )
        );
    }
});