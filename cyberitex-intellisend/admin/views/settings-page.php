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
    $providers = IntelliSend_Database::get_providers();
    ?>
    <div class="wrap intellisend-settings-wrap">
        <h1><?php echo esc_html__('IntelliSend Settings', 'intellisend'); ?></h1>
        
        <!-- Settings form with improved structure -->
        <form id="intellisend-settings-form" method="post" action="">
            <div class="intellisend-settings-container">
                <?php wp_nonce_field('intellisend_settings', 'intellisend_settings_nonce'); ?>
                
                <!-- General Settings Section -->
                <div class="intellisend-settings-section">
                    <h2 class="intellisend-settings-section-title"><?php echo esc_html__('General Settings', 'intellisend'); ?></h2>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="default-provider"><?php echo esc_html__('Default Email Provider', 'intellisend'); ?></label>
                            <select name="defaultProviderName" id="default-provider">
                                <?php if (empty($providers)) : ?>
                                    <option value=""><?php echo esc_html__('No providers available', 'intellisend'); ?></option>
                                <?php else : ?>
                                    <?php foreach ($providers as $provider) : ?>
                                        <option value="<?php echo esc_attr($provider->name); ?>" <?php selected($z->defaultProviderName, $provider->name); ?>><?php echo esc_html($provider->name); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <span class="field-description"><?php echo esc_html__('Select the default email provider for sending emails.', 'intellisend'); ?></span>
                        </div>
                        
                        <div class="intellisend-settings-field">
                            <label for="test-recipient"><?php echo esc_html__('Test Recipient Email', 'intellisend'); ?></label>
                            <div class="input-with-button">
                                <input type="email" name="testRecipient" id="test-recipient" value="<?php echo esc_attr($z->testRecipient ?? ''); ?>" placeholder="email@example.com">
                                <button type="button" id="send-test-email" class="button button-secondary"><?php echo esc_html__('Send Test Email', 'intellisend'); ?></button>
                            </div>
                            <span class="field-description"><?php echo esc_html__('Enter an email address to test your email configuration.', 'intellisend'); ?></span>
                        </div>
                    </div>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="logs-retention-days"><?php echo esc_html__('Logs Retention Period', 'intellisend'); ?></label>
                            <input type="hidden" name="logsRetentionDays" id="logs-retention-days" value="<?php echo esc_attr($z->logsRetentionDays ?? 30); ?>">
                            <!-- Dropdown will be inserted here by JavaScript -->
                            <span class="field-description"><?php echo esc_html__('Define how long email logs should be kept before automatic deletion.', 'intellisend'); ?></span>
                        </div>
                        
                        <!-- Empty field to balance the layout -->
                        <div class="intellisend-settings-field">
                            <!-- We're adding this empty div to maintain the two-column layout -->
                        </div>
                    </div>
                </div>
                
                <!-- Anti-Spam Settings Section -->
                <div class="intellisend-settings-section">
                    <h2 class="intellisend-settings-section-title"><?php echo esc_html__('Anti-Spam Settings', 'intellisend'); ?></h2>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="anti-spam-endpoint"><?php echo esc_html__('Anti-Spam API Endpoint', 'intellisend'); ?></label>
                            <input type="url" name="antiSpamEndpoint" id="anti-spam-endpoint" value="<?php echo esc_attr($z->antiSpamEndpoint ?? ''); ?>" placeholder="https://api.example.com/spam-check">
                            <span class="field-description"><?php echo esc_html__('The URL of the anti-spam service API.', 'intellisend'); ?></span>
                        </div>
                        
                        <div class="intellisend-settings-field">
                            <label for="api-key"><?php echo esc_html__('API Key', 'intellisend'); ?></label>
                            <input type="password" name="antiSpamApiKey" id="api-key" value="<?php echo esc_attr($z->antiSpamApiKey ?? ''); ?>">
                            <span class="field-description"><?php echo esc_html__('Your authentication key for the anti-spam service.', 'intellisend'); ?></span>
                            <!-- Password toggle will be inserted here by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field full-width">
                            <label for="spam-test-message"><?php echo esc_html__('Test Message', 'intellisend'); ?></label>
                            <div class="textarea-with-button">
                                <textarea name="spamTestMessage" id="spam-test-message" rows="4" placeholder="Enter a message to test for spam detection"><?php echo esc_textarea($z->spamTestMessage ?? ''); ?></textarea>
                                <button type="button" id="test-spam-detection" class="button button-secondary"><?php echo esc_html__('Test Spam Detection', 'intellisend'); ?></button>
                            </div>
                            <span class="field-description"><?php echo esc_html__('Enter a message to check if it would be detected as spam.', 'intellisend'); ?></span>
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