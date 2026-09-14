<?php
/**
 * IntelliSend Providers Management Page
 *
 * Simplified interface for managing email providers with modern design and UX.
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
    
    // Get default provider
    $default_provider_name = $settings ? $settings->defaultProviderName : 'other';
    
    // Find default provider in the list
    $default_provider = null;
    foreach ($providers as $provider) {
        if ($provider->name === $default_provider_name) {
            $default_provider = $provider;
            break;
        }
    }
    
    // Default description and help link
    $default_description = $default_provider ? $default_provider->description : '';
    $default_help_link = $default_provider && !empty($default_provider->helpLink) ? $default_provider->helpLink : '';

    // The API transport fields are populated by providers-page.js from the
    // transport descriptions localized in IntelliSend_Admin::enqueue_admin_scripts().
    ?>
    <div class="wrap intellisend-providers-wrap">
        <h1><?php echo esc_html__('Email Providers', 'intellisend'); ?></h1>
        
        <?php
        // Show settings saved message if applicable
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
            ?>
            <div class="intellisend-notice success">
                <span class="intellisend-notice-icon dashicons dashicons-yes-alt"></span>
                <div class="intellisend-notice-content"><?php echo esc_html__('Provider settings saved successfully.', 'intellisend'); ?></div>
            </div>
            <?php
        }
        ?>
        
        <div class="intellisend-providers-container">
            <div class="intellisend-providers-header">
                <div class="provider-selector">
                    <label for="provider-selector" class="screen-reader-text"><?php echo esc_html__('Email provider', 'intellisend'); ?></label>
                <select id="provider-selector" name="provider">
                        <?php if (!empty($providers)) : ?>
                            <?php foreach ($providers as $provider) : ?>
                                <?php
                                $is_configured = !empty($provider->configured) && $provider->configured == 1;
                                $provider_type = IntelliSend_Database::get_provider_type($provider);
                                ?>
                                <option value="<?php echo esc_attr($provider->name); ?>"
                                    data-id="<?php echo esc_attr($provider->id); ?>"
                                    data-type="<?php echo esc_attr($provider_type); ?>"
                                data-configured="<?php echo $is_configured ? '1' : '0'; ?>"
                                    data-username="<?php echo esc_attr($provider->username); ?>"
                                    data-sender="<?php echo esc_attr($provider->sender); ?>"
                                    data-server="<?php echo esc_attr($provider->server); ?>"
                                    data-port="<?php echo esc_attr($provider->port); ?>"
                                    data-api-endpoint="<?php echo esc_attr(isset($provider->apiEndpoint) ? $provider->apiEndpoint : ''); ?>"
                                    data-has-api-key="<?php echo empty($provider->apiKey) ? '0' : '1'; ?>"
                                    data-editable-server="<?php echo IntelliSend_Database::has_editable_server($provider) ? '1' : '0'; ?>"
                                    <?php if (!$is_configured) : ?>
                                    data-description="<?php echo esc_attr($provider->description); ?>"
                                    data-help-link="<?php echo esc_attr($provider->helpLink); ?>"
                                    <?php endif; ?>
                                    <?php selected($default_provider_name, $provider->name); ?>>
                                    <?php echo esc_html(IntelliSend_Database::get_provider_label($provider)); ?>
                                    <?php if ($is_configured) : ?>
                                    (Configured)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="provider-info" id="provider-description">
                    <?php echo esc_html($default_description); ?>
                    <?php if (!empty($default_help_link)) : ?>
                        <a href="<?php echo esc_url($default_help_link); ?>" target="_blank" class="help-link">
                            <?php echo esc_html__('Learn More', 'intellisend'); ?> <span class="dashicons dashicons-external"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="provider-form" method="post" action="">
                <?php wp_nonce_field('intellisend_providers', 'intellisend_providers_nonce'); ?>
                <input type="hidden" id="provider-id" name="provider_id" value="">
                <input type="hidden" id="is-default" name="is_default" value="1">
                <input type="hidden" id="provider-type" name="provider_type" value="smtp">

                <div class="intellisend-providers-content">
                    <div class="provider-fields">
                        <!-- Web API fields - shown for API transports; contents are filled in by providers-page.js -->
                        <div class="provider-field-row api-field api-identity-field" style="display:none;">
                            <div class="provider-field">
                                <label for="provider-api-identity" data-tooltip="<?php esc_attr_e('The non-secret half of the credential pair, such as an AWS Access Key ID', 'intellisend'); ?>">
                                    <span class="api-identity-label"><?php echo esc_html__('Access Key ID', 'intellisend'); ?></span>
                                </label>
                                <input type="text" id="provider-api-identity" name="provider_api_identity" placeholder="" autocomplete="off">
                                <span class="field-description api-identity-hint"></span>
                            </div>
                        </div>

                        <div class="provider-field-row api-field" style="display:none;">
                            <div class="provider-field">
                                <label for="provider-api-key" data-tooltip="<?php esc_attr_e('The secret credential used to authenticate sends', 'intellisend'); ?>">
                                    <span class="api-key-label"><?php echo esc_html__('API Key', 'intellisend'); ?></span>
                                </label>
                                <div class="password-field-container">
                                    <input type="password" id="provider-api-key" name="provider_api_key" placeholder="" autocomplete="off">
                                    <button type="button" class="password-toggle api-key-toggle" aria-label="<?php esc_attr_e('Toggle API key visibility', 'intellisend'); ?>"></button>
                                </div>
                                <span class="field-description api-key-hint"></span>
                                <button type="button" id="test-api-key-btn" class="button button-secondary">
                                    <?php echo esc_html__('Test API Key', 'intellisend'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="provider-field-row api-field api-region-field" style="display:none;">
                            <div class="provider-field">
                                <label for="provider-api-endpoint" data-tooltip="<?php esc_attr_e('Where the provider stores and sends your mail', 'intellisend'); ?>">
                                    <span class="api-region-label"><?php echo esc_html__('Data Residency', 'intellisend'); ?></span>
                                </label>
                                <select id="provider-api-endpoint" name="provider_api_endpoint"></select>
                                <span class="field-description"><?php echo esc_html__('Change this only if your account is pinned to a specific region.', 'intellisend'); ?></span>
                            </div>
                        </div>

                        <!-- SMTP Server and Port fields in a single row - visible for "other" -->
                        <div class="provider-field-row smtp-field">
                            <div class="provider-field server-field">
                                <label for="provider-server" data-tooltip="<?php esc_attr_e('The hostname of your SMTP server (e.g., smtp.example.com)', 'intellisend'); ?>">
                                    <?php echo esc_html__('SMTP Server', 'intellisend'); ?>
                                </label>
                                <input type="text" id="provider-server" name="provider_server" placeholder="smtp.example.com">
                            </div>
                            
                            <div class="provider-field port-field">
                                <label for="provider-port" data-tooltip="<?php esc_attr_e('The port used to connect to your SMTP server', 'intellisend'); ?>">
                                    <?php echo esc_html__('SMTP Port', 'intellisend'); ?>
                                </label>
                                <select id="provider-port" name="provider_port">
                                    <?php foreach ($port_options as $port) : ?>
                                        <option value="<?php echo esc_attr($port['value']); ?>" <?php selected($default_port, $port['value']); ?>>
                                            <?php echo esc_html($port['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Username field -->
                        <div class="provider-field-row credentials-section smtp-only-field">
                            <div class="provider-field">
                                <label for="provider-username" data-tooltip="<?php esc_attr_e('Your email address or username required for authentication', 'intellisend'); ?>">
                                    <?php echo esc_html__('Username', 'intellisend'); ?>
                                </label>
                                <input type="text" id="provider-username" name="provider_username" placeholder="username@example.com">
                            </div>
                        </div>
                        
                        <!-- Password field -->
                        <div class="provider-field-row smtp-only-field">
                            <div class="provider-field">
                                <label for="provider-password" data-tooltip="<?php esc_attr_e('Your email password or app-specific password if 2FA is enabled', 'intellisend'); ?>">
                                    <?php echo esc_html__('Password', 'intellisend'); ?>
                                </label>
                                <div class="password-field-container">
                                    <input type="password" id="provider-password" name="provider_password" autocomplete="new-password" placeholder="••••••••">
                                    <button type="button" class="password-toggle" aria-label="<?php esc_attr_e('Toggle password visibility', 'intellisend'); ?>"></button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sender Email field -->
                        <div class="provider-field-row">
                            <div class="provider-field">
                                <label for="provider-sender" data-tooltip="<?php esc_attr_e('The email address that will appear as the sender in outgoing emails', 'intellisend'); ?>">
                                    <?php echo esc_html__('Sender Email', 'intellisend'); ?>
                                </label>
                                <input type="email" id="provider-sender" name="provider_sender" placeholder="sender@example.com">
                                <span class="field-description api-sender-hint" style="display:none;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="intellisend-providers-footer">
                    <!-- Send a real message through the provider being edited,
                         without having to make it the site default first. -->
                    <div class="provider-test-send">
                        <label for="provider-test-recipient"><?php echo esc_html__('Send a test email to', 'intellisend'); ?></label>
                        <div class="input-with-button">
                            <input type="email" id="provider-test-recipient" placeholder="email@example.com"
                                value="<?php echo esc_attr($settings && !empty($settings->testRecipient) ? $settings->testRecipient : get_option('admin_email')); ?>">
                            <button type="button" id="send-provider-test-btn" class="button button-secondary">
                                <?php echo esc_html__('Send Test Email', 'intellisend'); ?>
                            </button>
                        </div>
                        <span class="field-description">
                            <?php echo esc_html__('Uses the saved credentials for the provider selected above. Save any changes first.', 'intellisend'); ?>
                        </span>
                    </div>

                    <div class="provider-form-actions">
                        <button type="button" id="reset-provider-btn" class="button button-secondary">
                            <?php echo esc_html__('Reset', 'intellisend'); ?>
                        </button>
                        <button type="submit" id="save-provider-btn" class="button button-primary">
                            <?php echo esc_html__('Save Provider', 'intellisend'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}