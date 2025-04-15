<?php
/**
 * IntelliSend Settings Page
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Render the IntelliSend settings page content
 */
function intellisend_render_settings_page_content() {
    // Get settings
    $z = IntelliSend_Database::get_settings();


    // Get providers
    $providers = IntelliSend_Database::get_providers(array( 'configured' => 1 ));
    
    // Enqueue the IntelliSendToast script
    wp_enqueue_script(
        'intellisend-toast',
        INTELLISEND_PLUGIN_URL . 'admin/js/intellisend-toast.js',
        array( 'jquery' ),
        INTELLISEND_VERSION,
        true
    );
    ?>
    <div class="wrap intellisend-settings-wrap">
        <h1><?php echo esc_html__('IntelliSend Settings', 'intellisend'); ?></h1>
        
        <!-- Settings form with compact structure -->
        <form id="intellisend-settings-form" method="post" action="">
            <div class="intellisend-settings-container">
                <?php wp_nonce_field('intellisend_settings', 'intellisend_settings_nonce'); ?>
                
                <!-- General Settings Section -->
                <div class="intellisend-settings-section">
                    <h2 class="intellisend-settings-section-title"><?php echo esc_html__('General Settings', 'intellisend'); ?></h2>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="default-provider"><?php echo esc_html__('Default SMTP Provider', 'intellisend'); ?></label>
                            <select name="defaultProviderName" id="default-provider">
                                <?php if (empty($providers)) : ?>
                                    <option value=""><?php echo esc_html__('No providers available', 'intellisend'); ?></option>
                                <?php else : ?>
                                    <?php foreach ($providers as $provider) : ?>
                                        <option value="<?php echo esc_attr($provider->name); ?>" <?php selected($z->defaultProviderName, $provider->name); ?>><?php echo esc_html($provider->name); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        
                        <div class="intellisend-settings-field">
                            <label for="test-recipient"><?php echo esc_html__('Test Recipient Email', 'intellisend'); ?></label>
                            <div class="input-with-button">
                                <input type="email" name="testRecipient" id="test-recipient" value="<?php echo esc_attr($z->testRecipient ?? ''); ?>" placeholder="email@example.com">
                                <button type="button" id="send-test-email" class="button button-secondary"><?php echo esc_html__('Send Test', 'intellisend'); ?></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="logs-retention-days"><?php echo esc_html__('Logs Retention Period', 'intellisend'); ?></label>
                            <input type="hidden" name="logsRetentionDays" id="logs-retention-days" value="<?php echo esc_attr($z->logsRetentionDays ?? 30); ?>">
                            <!-- Dropdown will be inserted here by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Anti-Spam Settings Section -->
                <div class="intellisend-settings-section">
                    <h2 class="intellisend-settings-section-title"><?php echo esc_html__('Anti-Spam Settings', 'intellisend'); ?></h2>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="anti-spam-endpoint"><?php echo esc_html__('Endpoint', 'intellisend'); ?></label>
                            <input type="url" name="antiSpamEndPoint" id="anti-spam-endpoint" value="<?php echo esc_attr($z->antiSpamEndPoint ?? ''); ?>" placeholder="https://api.example.com/spam-check">
                        </div>
                        
                        <div class="intellisend-settings-field">
                            <label for="api-key"><?php echo esc_html__('API Key', 'intellisend'); ?></label>
                            <div class="input-with-button">
                                <input type="password" name="antiSpamApiKey" id="api-key" value="" placeholder="Enter your API key">
                                <!-- Password toggle will be inserted here by JavaScript -->
                            </div>
                            <?php if (!empty($z->antiSpamApiKey)) : ?>
                                <p class="api-key-info"><?php echo esc_html__('API key is stored securely. For security reasons, it is not displayed.', 'intellisend'); ?></p>
                                <input type="hidden" id="has-existing-api-key" value="1">
                            <?php else: ?>
                                <input type="hidden" id="has-existing-api-key" value="0">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field full-width">
                            <label for="spam-test-message"><?php echo esc_html__('Test Message', 'intellisend'); ?></label>
                            <div class="textarea-with-button">
                                <textarea name="spamTestMessage" id="spam-test-message" rows="3" placeholder="Enter a message to test for spam detection"><?php echo esc_textarea($z->spamTestMessage ?? ''); ?></textarea>
                                <button type="button" id="test-spam-detection" class="button button-secondary"><?php echo esc_html__('Test', 'intellisend'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div class="intellisend-settings-submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Settings', 'intellisend'); ?></button>
                </div>
            </div>
        </form>
    </div>
    <?php
}