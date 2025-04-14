<?php
/**
 * IntelliSend Providers Management Page
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the IntelliSend providers page content
 */
function intellisend_render_providers_page_content() {
    // Get providers
    $providers = IntelliSend_Database::get_providers();
    
    // Get settings to determine default provider
    $settings = IntelliSend_Database::get_settings();
    
    // Port options
    $port_options = [
        ['value' => '25', 'label' => '25 (None)'],
        ['value' => '465', 'label' => '465 (SSL)'],
        ['value' => '587', 'label' => '587 (TLS)'],
    ];
    
    // Default port is 587
    $default_port = '587';
    ?>
    <div class="wrap intellisend-providers-wrap">
        <h1><?php echo esc_html__('Email Providers', 'intellisend'); ?></h1>
        
        <div class="intellisend-providers-container">
            <div class="intellisend-providers-header">
                <div class="provider-selector">
                    <select id="provider-selector" name="provider">
                        <?php if (!empty($providers)) : ?>
                            <?php foreach ($providers as $provider) : ?>
                                <option value="<?php echo esc_attr($provider->name); ?>" 
                                    data-id="<?php echo esc_attr($provider->id); ?>"
                                    data-type="<?php echo esc_attr($provider->type); ?>"
                                    data-username="<?php echo esc_attr($provider->username); ?>"
                                    data-sender="<?php echo esc_attr($provider->sender); ?>"
                                    data-server="<?php echo esc_attr($provider->server); ?>"
                                    data-port="<?php echo esc_attr($provider->port); ?>"
                                    <?php selected($settings->defaultProviderName, $provider->name); ?>>
                                    <?php echo esc_html($provider->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <form id="provider-form" method="post" action="">
                <?php wp_nonce_field('intellisend_providers', 'intellisend_providers_nonce'); ?>
                <input type="hidden" id="provider-id" name="provider_id" value="">
                <input type="hidden" id="is-default" name="is_default" value="1">
                
                <div class="intellisend-providers-content">
                    <div class="provider-fields">
                        <!-- SMTP Server field -->
                        <div class="provider-field-row smtp-field">
                            <div class="provider-field">
                                <label for="provider-server"><?php echo esc_html__('SMTP Server', 'intellisend'); ?></label>
                                <input type="text" id="provider-server" name="provider_server" placeholder="smtp.example.com">
                                <span class="field-description"><?php echo esc_html__('The SMTP server hostname.', 'intellisend'); ?></span>
                            </div>
                        </div>
                        
                        <!-- SMTP Port field -->
                        <div class="provider-field-row smtp-field">
                            <div class="provider-field">
                                <label for="provider-port"><?php echo esc_html__('SMTP Port', 'intellisend'); ?></label>
                                <select id="provider-port" name="provider_port">
                                    <?php foreach ($port_options as $port) : ?>
                                        <option value="<?php echo esc_attr($port['value']); ?>" <?php selected($default_port, $port['value']); ?>>
                                            <?php echo esc_html($port['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="field-description"><?php echo esc_html__('The port used to connect to your SMTP server.', 'intellisend'); ?></span>
                            </div>
                        </div>
                        
                        <!-- Authentication fields - shown for all providers -->
                        <div class="provider-field-row">
                            <div class="provider-field">
                                <label for="provider-username"><?php echo esc_html__('Username', 'intellisend'); ?></label>
                                <input type="text" id="provider-username" name="provider_username" placeholder="username@example.com">
                                <span class="field-description"><?php echo esc_html__('Your email provider username or email address.', 'intellisend'); ?></span>
                            </div>
                        </div>
                        
                        
                        <div class="provider-field-row">
                            <div class="provider-field">
                                <label for="provider-password"><?php echo esc_html__('Password', 'intellisend'); ?></label>
                                <div class="password-field-container">
                                    <input type="password" id="provider-password" name="provider_password" placeholder="••••••••">
                                    <button type="button" class="password-toggle" aria-label="Toggle password visibility"></button>
                                </div>
                                <span class="field-description"><?php echo esc_html__('Your email provider password or app password.', 'intellisend'); ?></span>
                            </div>
                        </div>
                        
                        <div class="provider-field-row">
                            <div class="provider-field">
                                <label for="provider-sender"><?php echo esc_html__('Sender Email', 'intellisend'); ?></label>
                                <input type="text" id="provider-sender" name="provider_sender" placeholder="sender@example.com">
                                <span class="field-description"><?php echo esc_html__('The email address that will appear as the sender. Leave empty to use the username.', 'intellisend'); ?></span>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <div class="intellisend-providers-footer">
                    <div class="provider-form-actions">
                        <button type="button" id="reset-provider-btn" class="button button-secondary"><?php echo esc_html__('Reset', 'intellisend'); ?></button>
                        <button type="submit" id="save-provider-btn" class="button button-primary"><?php echo esc_html__('Save Provider', 'intellisend'); ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
    jQuery(document).ready(function($) {
        // Auto-populate sender field with username if empty
        $('#provider-username').on('input', function() {
            if ($('#provider-sender').val() === '') {
                $('#provider-sender').val($(this).val());
            }
        });
        
        // When selecting a provider from the dropdown
        $('#provider-selector').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const username = selectedOption.data('username');
            const sender = selectedOption.data('sender');
            
            // If sender is empty, use username
            if (!sender || sender === '') {
                $('#provider-sender').val(username);
            } else {
                $('#provider-sender').val(sender);
            }
        });
    });
    </script>
    <?php
}