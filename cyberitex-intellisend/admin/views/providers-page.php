<?php
/**
 * Admin page for managing SMTP providers
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Render the providers page
function intellisend_render_providers_page_content() {
    // Get all providers
    $providers = IntelliSend_Database::get_providers();

    // Get settings
    $settings = IntelliSend_Database::get_settings();
    ?>
    <div class="wrap intellisend-admin">
        <h1><?php echo esc_html__( 'SMTP Providers', 'intellisend' ); ?></h1>
        
        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Manage SMTP Providers', 'intellisend' ); ?></h2>
                <p><?php echo esc_html__( 'Configure SMTP providers for sending emails. You can add multiple providers and use them in routing rules.', 'intellisend' ); ?></p>
                
                <div class="intellisend-providers-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Name', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Server', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Port', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Encryption', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Auth', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Actions', 'intellisend' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $providers ) ) : ?>
                                <tr>
                                    <td colspan="6"><?php echo esc_html__( 'No providers found. Add your first SMTP provider below.', 'intellisend' ); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $providers as $provider ) : ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html( $provider->name ); ?>
                                            <?php if ( $settings->defaultProviderName == $provider->name ) : ?>
                                                <span class="default-badge"><?php echo esc_html__( 'Default', 'intellisend' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $provider->server ); ?></td>
                                        <td><?php echo esc_html( $provider->port ); ?></td>
                                        <td><?php echo esc_html( $provider->encryption ); ?></td>
                                        <td><?php echo $provider->authRequired ? esc_html__( 'Yes', 'intellisend' ) : esc_html__( 'No', 'intellisend' ); ?></td>
                                        <td>
                                            <button class="button edit-provider" data-id="<?php echo esc_attr( $provider->id ); ?>">
                                                <?php echo esc_html__( 'Edit', 'intellisend' ); ?>
                                            </button>
                                            <button class="button delete-provider" data-id="<?php echo esc_attr( $provider->id ); ?>">
                                                <?php echo esc_html__( 'Delete', 'intellisend' ); ?>
                                            </button>
                                            <?php if ( $settings->defaultProviderName != $provider->name ) : ?>
                                                <button class="button set-default-provider" data-id="<?php echo esc_attr( $provider->id ); ?>" data-name="<?php echo esc_attr( $provider->name ); ?>">
                                                    <?php echo esc_html__( 'Set as Default', 'intellisend' ); ?>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="intellisend-add-provider">
                    <h3><?php echo esc_html__( 'Add New Provider', 'intellisend' ); ?></h3>
                    <form id="add-provider-form">
                        <div class="form-row">
                            <label for="provider-name"><?php echo esc_html__( 'Name', 'intellisend' ); ?></label>
                            <input type="text" id="provider-name" name="name" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-description"><?php echo esc_html__( 'Description', 'intellisend' ); ?></label>
                            <textarea id="provider-description" name="description"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-server"><?php echo esc_html__( 'Server', 'intellisend' ); ?></label>
                            <input type="text" id="provider-server" name="server" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-port"><?php echo esc_html__( 'Port', 'intellisend' ); ?></label>
                            <input type="number" id="provider-port" name="port" required>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-encryption"><?php echo esc_html__( 'Encryption', 'intellisend' ); ?></label>
                            <select id="provider-encryption" name="encryption">
                                <option value="none"><?php echo esc_html__( 'None', 'intellisend' ); ?></option>
                                <option value="ssl"><?php echo esc_html__( 'SSL', 'intellisend' ); ?></option>
                                <option value="tls"><?php echo esc_html__( 'TLS', 'intellisend' ); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-auth-required"><?php echo esc_html__( 'Authentication Required', 'intellisend' ); ?></label>
                            <input type="checkbox" id="provider-auth-required" name="authRequired" value="1">
                        </div>
                        
                        <div class="auth-fields" style="display: none;">
                            <div class="form-row">
                                <label for="provider-username"><?php echo esc_html__( 'Username', 'intellisend' ); ?></label>
                                <input type="text" id="provider-username" name="username">
                            </div>
                            
                            <div class="form-row">
                                <label for="provider-password"><?php echo esc_html__( 'Password', 'intellisend' ); ?></label>
                                <input type="password" id="provider-password" name="password">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <label for="provider-help-link"><?php echo esc_html__( 'Help Link', 'intellisend' ); ?></label>
                            <input type="url" id="provider-help-link" name="helpLink">
                        </div>
                        
                        <div class="form-row">
                            <button type="submit" class="button button-primary"><?php echo esc_html__( 'Add Provider', 'intellisend' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Provider Modal -->
    <div id="edit-provider-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <span class="intellisend-modal-close">&times;</span>
            <h2><?php echo esc_html__( 'Edit Provider', 'intellisend' ); ?></h2>
            
            <form id="edit-provider-form">
                <input type="hidden" id="edit-provider-id" name="id">
                
                <div class="form-row">
                    <label for="edit-provider-name"><?php echo esc_html__( 'Name', 'intellisend' ); ?></label>
                    <input type="text" id="edit-provider-name" name="name" required>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-description"><?php echo esc_html__( 'Description', 'intellisend' ); ?></label>
                    <textarea id="edit-provider-description" name="description"></textarea>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-server"><?php echo esc_html__( 'Server', 'intellisend' ); ?></label>
                    <input type="text" id="edit-provider-server" name="server" required>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-port"><?php echo esc_html__( 'Port', 'intellisend' ); ?></label>
                    <input type="number" id="edit-provider-port" name="port" required>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-encryption"><?php echo esc_html__( 'Encryption', 'intellisend' ); ?></label>
                    <select id="edit-provider-encryption" name="encryption">
                        <option value="none"><?php echo esc_html__( 'None', 'intellisend' ); ?></option>
                        <option value="ssl"><?php echo esc_html__( 'SSL', 'intellisend' ); ?></option>
                        <option value="tls"><?php echo esc_html__( 'TLS', 'intellisend' ); ?></option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-auth-required"><?php echo esc_html__( 'Authentication Required', 'intellisend' ); ?></label>
                    <input type="checkbox" id="edit-provider-auth-required" name="authRequired" value="1">
                </div>
                
                <div class="edit-auth-fields" style="display: none;">
                    <div class="form-row">
                        <label for="edit-provider-username"><?php echo esc_html__( 'Username', 'intellisend' ); ?></label>
                        <input type="text" id="edit-provider-username" name="username">
                    </div>
                    
                    <div class="form-row">
                        <label for="edit-provider-password"><?php echo esc_html__( 'Password', 'intellisend' ); ?></label>
                        <input type="password" id="edit-provider-password" name="password">
                        <p class="description"><?php echo esc_html__( 'Leave empty to keep current password', 'intellisend' ); ?></p>
                    </div>
                </div>
                
                <div class="form-row">
                    <label for="edit-provider-help-link"><?php echo esc_html__( 'Help Link', 'intellisend' ); ?></label>
                    <input type="url" id="edit-provider-help-link" name="helpLink">
                </div>
                
                <div class="form-row">
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Update Provider', 'intellisend' ); ?></button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        jQuery(document).ready(function($) {
            // Toggle auth fields based on checkbox
            $('#provider-auth-required').change(function() {
                if ($(this).is(':checked')) {
                    $('.auth-fields').show();
                } else {
                    $('.auth-fields').hide();
                }
            });
            
            $('#edit-provider-auth-required').change(function() {
                if ($(this).is(':checked')) {
                    $('.edit-auth-fields').show();
                } else {
                    $('.edit-auth-fields').hide();
                }
            });
            
            // Edit provider
            $('.edit-provider').click(function() {
                var providerId = $(this).data('id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_get_provider',
                        id: providerId,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var provider = response.data;
                            
                            // Populate form fields
                            $('#edit-provider-id').val(provider.id);
                            $('#edit-provider-name').val(provider.name);
                            $('#edit-provider-description').val(provider.description);
                            $('#edit-provider-server').val(provider.server);
                            $('#edit-provider-port').val(provider.port);
                            $('#edit-provider-encryption').val(provider.encryption);
                            $('#edit-provider-auth-required').prop('checked', provider.authRequired == 1);
                            $('#edit-provider-username').val(provider.username);
                            $('#edit-provider-help-link').val(provider.helpLink);
                            
                            // Show/hide auth fields
                            if (provider.authRequired == 1) {
                                $('.edit-auth-fields').show();
                            } else {
                                $('.edit-auth-fields').hide();
                            }
                            
                            // Show the modal
                            $('#edit-provider-modal').show();
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
            
            // Add provider form submission
            $('#add-provider-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_add_provider',
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
            
            // Edit provider form submission
            $('#edit-provider-form').submit(function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_update_provider',
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
            
            // Delete provider
            $('.delete-provider').click(function() {
                if (confirm('Are you sure you want to delete this provider?')) {
                    var providerId = $(this).data('id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'intellisend_delete_provider',
                            id: providerId,
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
            
            // Set as default provider
            $('.set-default-provider').click(function() {
                var providerId = $(this).data('id');
                var providerName = $(this).data('name');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_set_default_provider',
                        id: providerId,
                        name: providerName,
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

intellisend_render_providers_page_content();
