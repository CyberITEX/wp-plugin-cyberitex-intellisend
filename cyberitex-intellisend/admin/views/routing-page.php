<?php
/**
 * Admin page for managing email routing rules
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Render the routing page content
 */
function intellisend_render_routing_page_content() {
    // Get all routing rules
    $routing_rules = IntelliSend_Database::get_routing_rules();

    // Get all configured providers
    $providers = IntelliSend_Database::get_providers( array( 'configured' => 1 ) );
    ?>
    <div class="wrap intellisend-admin">
        <h1><?php echo esc_html__( 'Email Routing', 'intellisend' ); ?></h1>
        
        <?php if ( empty( $providers ) ) : ?>
            <div class="intellisend-notice warning">
                <span class="intellisend-notice-icon dashicons dashicons-warning"></span>
                <div class="intellisend-notice-content">
                    <?php echo esc_html__( 'No configured SMTP providers found. Please configure at least one provider in the SMTP Providers section before setting up routing rules.', 'intellisend' ); ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=intellisend-providers' ) ); ?>" class="button button-secondary"><?php echo esc_html__( 'Configure Providers', 'intellisend' ); ?></a>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Manage Routing Rules', 'intellisend' ); ?></h2>
                <p><?php echo esc_html__( 'Configure rules to route emails through different SMTP providers based on recipient or subject patterns.', 'intellisend' ); ?></p>
                
                <div class="intellisend-routing-list">
                    <table class="intellisend-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Name', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Pattern', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Provider', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Priority', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Status', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Actions', 'intellisend' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $routing_rules ) ) : ?>
                                <tr>
                                    <td colspan="6"><?php echo esc_html__( 'No routing rules found. Add your first rule below.', 'intellisend' ); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $routing_rules as $rule ) : ?>
                                    <?php 
                                    $provider_name = '';
                                    foreach ( $providers as $provider ) {
                                        if ( $provider->name == $rule->defaultProviderName ) {
                                            $provider_name = $provider->name;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html( $rule->name ); ?>
                                            <?php if ( $rule->priority == -1 ) : ?>
                                                <span class="default-badge"><?php echo esc_html__( 'Default', 'intellisend' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $rule->subjectPatterns ); ?></td>
                                        <td><?php echo esc_html( $provider_name ); ?></td>
                                        <td><?php echo esc_html( $rule->priority ); ?></td>
                                        <td>
                                            <?php if ( $rule->enabled ) : ?>
                                                <span class="status-active"><?php echo esc_html__( 'Active', 'intellisend' ); ?></span>
                                            <?php else : ?>
                                                <span class="status-inactive"><?php echo esc_html__( 'Inactive', 'intellisend' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="action-button edit-rule" data-id="<?php echo esc_attr( $rule->id ); ?>" title="<?php echo esc_attr__( 'Edit', 'intellisend' ); ?>">
                                                    <span class="dashicons dashicons-edit"></span>
                                                </button>
                                                
                                                <?php if ( $rule->enabled ) : ?>
                                                    <button type="button" class="action-button deactivate-rule" data-id="<?php echo esc_attr( $rule->id ); ?>" title="<?php echo esc_attr__( 'Deactivate', 'intellisend' ); ?>">
                                                        <span class="dashicons dashicons-hidden"></span>
                                                    </button>
                                                <?php else : ?>
                                                    <button type="button" class="action-button activate-rule" data-id="<?php echo esc_attr( $rule->id ); ?>" title="<?php echo esc_attr__( 'Activate', 'intellisend' ); ?>">
                                                        <span class="dashicons dashicons-visibility"></span>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ( $rule->priority != -1 ) : ?>
                                                    <button type="button" class="action-button delete-rule" data-id="<?php echo esc_attr( $rule->id ); ?>" data-name="<?php echo esc_attr( $rule->name ); ?>" title="<?php echo esc_attr__( 'Delete', 'intellisend' ); ?>">
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
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php echo esc_html__( 'Add New Routing Rule', 'intellisend' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Rule Modal -->
    <div id="add-rule-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <div class="intellisend-modal-header">
                <h3><?php echo esc_html__( 'Add New Routing Rule', 'intellisend' ); ?></h3>
                <button type="button" class="intellisend-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'intellisend' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="intellisend-modal-body">
                <form id="add-rule-form" class="intellisend-form">
                    <?php wp_nonce_field( 'intellisend_routing_nonce', 'intellisend_routing_nonce' ); ?>
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="rule-name"><?php echo esc_html__( 'Rule Name', 'intellisend' ); ?></label>
                            <input type="text" id="rule-name" name="rule_name" placeholder="<?php echo esc_attr__( 'e.g. Marketing Emails', 'intellisend' ); ?>">
                            <p class="description"><?php echo esc_html__( 'A descriptive name for this routing rule.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="rule-provider"><?php echo esc_html__( 'Provider', 'intellisend' ); ?></label>
                            <select id="rule-provider" name="rule_provider">
                                <option value=""><?php echo esc_html__( 'Select Provider', 'intellisend' ); ?></option>
                                <?php foreach ( $providers as $provider ) : ?>
                                    <option value="<?php echo esc_attr( $provider->name ); ?>">
                                        <?php echo esc_html( $provider->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__( 'The email provider to use for emails matching this rule.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="rule-patterns"><?php echo esc_html__( 'Patterns', 'intellisend' ); ?></label>
                            <textarea id="rule-patterns" name="rule_patterns" placeholder="<?php echo esc_attr__( 'e.g. *@example.com, newsletter*, *promo*', 'intellisend' ); ?>"></textarea>
                            <p class="description"><?php echo esc_html__( 'Comma-separated list of patterns to match against email recipients or subjects. Use * as a wildcard.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="rule-priority"><?php echo esc_html__( 'Priority', 'intellisend' ); ?></label>
                            <input type="number" id="rule-priority" name="rule_priority" value="10" min="0" max="100">
                            <p class="description"><?php echo esc_html__( 'Rules with lower numbers are processed first. Default rule has priority -1.', 'intellisend' ); ?></p>
                        </div>
                        
                        <div class="form-field">
                            <label for="rule-enabled"><?php echo esc_html__( 'Status', 'intellisend' ); ?></label>
                            <div class="checkbox-field">
                                <input type="checkbox" id="rule-enabled" name="rule_enabled" value="1" checked>
                                <label for="rule-enabled"><?php echo esc_html__( 'Active', 'intellisend' ); ?></label>
                            </div>
                            <p class="description"><?php echo esc_html__( 'Inactive rules will not be applied to emails.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="rule-antispam"><?php echo esc_html__( 'Anti-Spam', 'intellisend' ); ?></label>
                            <div class="checkbox-field">
                                <input type="checkbox" id="rule-antispam" name="rule_antispam" value="1" checked>
                                <label for="rule-antispam"><?php echo esc_html__( 'Enable anti-spam check', 'intellisend' ); ?></label>
                            </div>
                            <p class="description"><?php echo esc_html__( 'When enabled, emails matching this rule will be checked for spam before sending.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="button button-secondary cancel-form"><?php echo esc_html__( 'Cancel', 'intellisend' ); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html__( 'Add Rule', 'intellisend' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Rule Modal -->
    <div id="edit-rule-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <div class="intellisend-modal-header">
                <h3><?php echo esc_html__( 'Edit Routing Rule', 'intellisend' ); ?></h3>
                <button type="button" class="intellisend-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'intellisend' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="intellisend-modal-body">
                <form id="edit-rule-form" class="intellisend-form">
                    <?php wp_nonce_field( 'intellisend_routing_nonce', 'intellisend_routing_nonce' ); ?>
                    <input type="hidden" id="edit-rule-id" name="rule_id" value="">
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="edit-rule-name"><?php echo esc_html__( 'Rule Name', 'intellisend' ); ?></label>
                            <input type="text" id="edit-rule-name" name="rule_name">
                            <p class="description"><?php echo esc_html__( 'A descriptive name for this routing rule.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="edit-rule-provider"><?php echo esc_html__( 'Provider', 'intellisend' ); ?></label>
                            <select id="edit-rule-provider" name="rule_provider">
                                <option value=""><?php echo esc_html__( 'Select Provider', 'intellisend' ); ?></option>
                                <?php foreach ( $providers as $provider ) : ?>
                                    <option value="<?php echo esc_attr( $provider->name ); ?>">
                                        <?php echo esc_html( $provider->name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__( 'The email provider to use for emails matching this rule.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field required">
                            <label for="edit-rule-patterns"><?php echo esc_html__( 'Patterns', 'intellisend' ); ?></label>
                            <textarea id="edit-rule-patterns" name="rule_patterns"></textarea>
                            <p class="description"><?php echo esc_html__( 'Comma-separated list of patterns to match against email recipients or subjects. Use * as a wildcard.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit-rule-priority"><?php echo esc_html__( 'Priority', 'intellisend' ); ?></label>
                            <input type="number" id="edit-rule-priority" name="rule_priority" min="0" max="100">
                            <p class="description"><?php echo esc_html__( 'Rules with lower numbers are processed first. Default rule has priority -1.', 'intellisend' ); ?></p>
                        </div>
                        
                        <div class="form-field">
                            <label for="edit-rule-enabled"><?php echo esc_html__( 'Status', 'intellisend' ); ?></label>
                            <div class="checkbox-field">
                                <input type="checkbox" id="edit-rule-enabled" name="rule_enabled" value="1">
                                <label for="edit-rule-enabled"><?php echo esc_html__( 'Active', 'intellisend' ); ?></label>
                            </div>
                            <p class="description"><?php echo esc_html__( 'Inactive rules will not be applied to emails.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="edit-rule-antispam"><?php echo esc_html__( 'Anti-Spam', 'intellisend' ); ?></label>
                            <div class="checkbox-field">
                                <input type="checkbox" id="edit-rule-antispam" name="rule_antispam" value="1">
                                <label for="edit-rule-antispam"><?php echo esc_html__( 'Enable anti-spam check', 'intellisend' ); ?></label>
                            </div>
                            <p class="description"><?php echo esc_html__( 'When enabled, emails matching this rule will be checked for spam before sending.', 'intellisend' ); ?></p>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="button button-secondary cancel-form"><?php echo esc_html__( 'Cancel', 'intellisend' ); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html__( 'Update Rule', 'intellisend' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php
    // Localize script data
    wp_localize_script(
        'intellisend-routing-js',
        'intellisendData',
        array(
            'nonce' => wp_create_nonce( 'intellisend_routing_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'strings' => array(
                'unconfiguredProvider' => __( 'The provider "%s" is not configured. Please select a configured provider or configure this provider in the SMTP Providers section.', 'intellisend' ),
                'confirmDelete' => __( 'Are you sure you want to delete this routing rule? This action cannot be undone.', 'intellisend' ),
                'noConfiguredProviders' => __( 'No configured SMTP providers found. Please configure at least one provider before adding routing rules.', 'intellisend' )
            )
        )
    );
}

// Enqueue styles and scripts
function intellisend_enqueue_routing_assets() {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'intellisend-routing' ) {
        wp_enqueue_style( 'intellisend-routing-css', INTELLISEND_PLUGIN_URL . 'admin/css/routing-page.css', array(), INTELLISEND_VERSION );
        wp_enqueue_script( 'intellisend-routing-js', INTELLISEND_PLUGIN_URL . 'admin/js/routing-page.js', array( 'jquery' ), INTELLISEND_VERSION, true );
    }
}
add_action( 'admin_enqueue_scripts', 'intellisend_enqueue_routing_assets' );
