<?php
/**
 * Admin page for managing email routing rules
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Render the routing page
function intellisend_render_routing_page_content() {
    // Get all routing rules
    $routing_rules = IntelliSend_Database::get_routing_rules();

    // Get all providers
    $providers = IntelliSend_Database::get_providers();
    ?>
    <div class="wrap intellisend-admin">
        <h1><?php echo esc_html__( 'Email Routing', 'intellisend' ); ?></h1>
        
        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Manage Routing Rules', 'intellisend' ); ?></h2>
                <p><?php echo esc_html__( 'Configure rules to route emails through different SMTP providers based on recipient or subject patterns.', 'intellisend' ); ?></p>
                
                <div class="intellisend-routing-list">
                    <table class="wp-list-table widefat fixed striped">
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
                                            <button class="button edit-rule" data-id="<?php echo esc_attr( $rule->id ); ?>">
                                                <?php echo esc_html__( 'Edit', 'intellisend' ); ?>
                                            </button>
                                            <?php if ( $rule->priority != -1 ) : ?>
                                                <button class="button delete-rule" data-id="<?php echo esc_attr( $rule->id ); ?>">
                                                    <?php echo esc_html__( 'Delete', 'intellisend' ); ?>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ( $rule->enabled ) : ?>
                                                <button class="button deactivate-rule" data-id="<?php echo esc_attr( $rule->id ); ?>">
                                                    <?php echo esc_html__( 'Deactivate', 'intellisend' ); ?>
                                                </button>
                                            <?php else : ?>
                                                <button class="button activate-rule" data-id="<?php echo esc_attr( $rule->id ); ?>">
                                                    <?php echo esc_html__( 'Activate', 'intellisend' ); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="intellisend-add-rule">
                    <h3><?php echo esc_html__( 'Add New Rule', 'intellisend' ); ?></h3>
                    <form id="add-rule-form">
                        <div class="form-row">
                            <label for="rule-name"><?php echo esc_html__( 'Name', 'intellisend' ); ?></label>
                            <input type="text" id="rule-name" name="name" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-pattern"><?php echo esc_html__( 'Subject Pattern', 'intellisend' ); ?></label>
                            <input type="text" id="rule-pattern" name="subjectPatterns" required>
                            <p class="description">
                                <?php echo esc_html__( 'Use * as a wildcard. For example, *@example.com will match all emails to example.com.', 'intellisend' ); ?>
                            </p>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-provider"><?php echo esc_html__( 'Provider', 'intellisend' ); ?></label>
                            <select id="rule-provider" name="defaultProviderName" required>
                                <?php foreach ( $providers as $provider ) : ?>
                                    <option value="<?php echo esc_attr( $provider->name ); ?>"><?php echo esc_html( $provider->name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-recipients"><?php echo esc_html__( 'Recipients', 'intellisend' ); ?></label>
                            <input type="text" id="rule-recipients" name="recipients">
                            <p class="description">
                                <?php echo esc_html__( 'Optional. Comma-separated list of email addresses to send to. Leave empty to use the original recipients.', 'intellisend' ); ?>
                            </p>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-priority"><?php echo esc_html__( 'Priority', 'intellisend' ); ?></label>
                            <input type="number" id="rule-priority" name="priority" value="10" required>
                            <p class="description">
                                <?php echo esc_html__( 'Higher number means higher priority. Rules are processed in order of priority.', 'intellisend' ); ?>
                            </p>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-antispam"><?php echo esc_html__( 'Anti-Spam', 'intellisend' ); ?></label>
                            <input type="checkbox" id="rule-antispam" name="antiSpamEnabled" value="1" checked>
                            <span class="checkbox-label"><?php echo esc_html__( 'Enable anti-spam check for this rule', 'intellisend' ); ?></span>
                        </div>
                        
                        <div class="form-row">
                            <label for="rule-enabled"><?php echo esc_html__( 'Status', 'intellisend' ); ?></label>
                            <input type="checkbox" id="rule-enabled" name="enabled" value="1" checked>
                            <span class="checkbox-label"><?php echo esc_html__( 'Enable this rule', 'intellisend' ); ?></span>
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" class="button button-primary"><?php echo esc_html__( 'Add Rule', 'intellisend' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Rule Modal -->
    <div id="edit-rule-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <span class="intellisend-modal-close">&times;</span>
            <h2><?php echo esc_html__( 'Edit Rule', 'intellisend' ); ?></h2>
            
            <form id="edit-rule-form">
                <input type="hidden" id="edit-rule-id" name="id">
                
                <div class="form-row">
                    <label for="edit-rule-name"><?php echo esc_html__( 'Name', 'intellisend' ); ?></label>
                    <input type="text" id="edit-rule-name" name="name" required>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-pattern"><?php echo esc_html__( 'Subject Pattern', 'intellisend' ); ?></label>
                    <input type="text" id="edit-rule-pattern" name="subjectPatterns" required>
                    <p class="description">
                        <?php echo esc_html__( 'Use * as a wildcard. For example, *@example.com will match all emails to example.com.', 'intellisend' ); ?>
                    </p>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-provider"><?php echo esc_html__( 'Provider', 'intellisend' ); ?></label>
                    <select id="edit-rule-provider" name="defaultProviderName" required>
                        <?php foreach ( $providers as $provider ) : ?>
                            <option value="<?php echo esc_attr( $provider->name ); ?>"><?php echo esc_html( $provider->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-recipients"><?php echo esc_html__( 'Recipients', 'intellisend' ); ?></label>
                    <input type="text" id="edit-rule-recipients" name="recipients">
                    <p class="description">
                        <?php echo esc_html__( 'Optional. Comma-separated list of email addresses to send to. Leave empty to use the original recipients.', 'intellisend' ); ?>
                    </p>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-priority"><?php echo esc_html__( 'Priority', 'intellisend' ); ?></label>
                    <input type="number" id="edit-rule-priority" name="priority" required>
                    <p class="description">
                        <?php echo esc_html__( 'Higher number means higher priority. Rules are processed in order of priority.', 'intellisend' ); ?>
                    </p>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-antispam"><?php echo esc_html__( 'Anti-Spam', 'intellisend' ); ?></label>
                    <input type="checkbox" id="edit-rule-antispam" name="antiSpamEnabled" value="1">
                    <span class="checkbox-label"><?php echo esc_html__( 'Enable anti-spam check for this rule', 'intellisend' ); ?></span>
                </div>
                
                <div class="form-row">
                    <label for="edit-rule-enabled"><?php echo esc_html__( 'Status', 'intellisend' ); ?></label>
                    <input type="checkbox" id="edit-rule-enabled" name="enabled" value="1">
                    <span class="checkbox-label"><?php echo esc_html__( 'Enable this rule', 'intellisend' ); ?></span>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Update Rule', 'intellisend' ); ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        jQuery(document).ready(function($) {
            // Edit rule
            $('.edit-rule').click(function() {
                var ruleId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_get_routing_rule',
                        id: ruleId,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var rule = response.data;
                            
                            // Populate form fields
                            $('#edit-rule-id').val(rule.id);
                            $('#edit-rule-name').val(rule.name);
                            $('#edit-rule-pattern').val(rule.subjectPatterns);
                            $('#edit-rule-provider').val(rule.defaultProviderName);
                            $('#edit-rule-recipients').val(rule.recipients);
                            $('#edit-rule-priority').val(rule.priority);
                            $('#edit-rule-antispam').prop('checked', rule.antiSpamEnabled == 1);
                            $('#edit-rule-enabled').prop('checked', rule.enabled == 1);
                            
                            // Show the modal
                            $('#edit-rule-modal').show();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Close modal
            $('.intellisend-modal-close').click(function() {
                $(this).closest('.intellisend-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).click(function(event) {
                if ($(event.target).hasClass('intellisend-modal')) {
                    $('.intellisend-modal').hide();
                }
            });
            
            // Add rule form submission
            $('#add-rule-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_add_routing_rule',
                        formData: formData,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Edit rule form submission
            $('#edit-rule-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_update_routing_rule',
                        formData: formData,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Delete rule
            $('.delete-rule').click(function() {
                if (confirm('Are you sure you want to delete this rule?')) {
                    var ruleId = $(this).data('id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'intellisend_delete_routing_rule',
                            id: ruleId,
                            nonce: intellisendData.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data);
                            }
                        }
                    });
                }
            });
            
            // Activate rule
            $('.activate-rule').click(function() {
                var ruleId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_activate_routing_rule',
                        id: ruleId,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Deactivate rule
            $('.deactivate-rule').click(function() {
                var ruleId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_deactivate_routing_rule',
                        id: ruleId,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
        });
    </script>
    <?php
}

intellisend_render_routing_page_content();
