<?php

/**
 * admin\views\settings-page.php
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
function intellisend_render_settings_page_content()
{
    // Get settings
    $settings = IntelliSend_Database::get_settings();

    // Get providers
    $providers = IntelliSend_Database::get_providers(array('configured' => 1));

    // Enqueue the IntelliSendToast script
    wp_enqueue_script(
        'intellisend-toast',
        INTELLISEND_PLUGIN_URL . 'admin/js/intellisend-toast.js',
        array('jquery'),
        INTELLISEND_VERSION,
        true
    );
?>
    <div class="wrap intellisend-settings-wrap">
        <h1><?php echo esc_html__('IntelliSend Settings', 'intellisend'); ?></h1>

        <?php wp_nonce_field('intellisend_settings', 'intellisend_settings_nonce'); ?>

        <!-- Auto-Save Settings Container -->
        <div class="intellisend-settings-container">
            <!-- General Settings Section (Auto-Save) -->
            <div class="intellisend-settings-section">
                <h2 class="intellisend-settings-section-title"><?php echo esc_html__('General Settings', 'intellisend'); ?> <span style="font-size: 12px; color: #666; font-weight: normal;">(Auto-saved)</span></h2>

                <div class="intellisend-settings-row">
                    <div class="intellisend-settings-field">
                        <label for="default-provider"><?php echo esc_html__('Default SMTP Provider', 'intellisend'); ?></label>
                        <select name="defaultProviderName" id="default-provider">
                            <?php if (empty($providers)) : ?>
                                <option value=""><?php echo esc_html__('No providers available', 'intellisend'); ?></option>
                            <?php else : ?>
                                <?php foreach ($providers as $provider) : ?>
                                    <option value="<?php echo esc_attr($provider->name); ?>" <?php selected($settings->defaultProviderName, $provider->name); ?>><?php echo esc_html($provider->name); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <span class="field-description"><?php echo esc_html__('Changes are saved automatically when you select a different provider.', 'intellisend'); ?></span>
                    </div>

                    <div class="intellisend-settings-field">
                        <label for="test-recipient"><?php echo esc_html__('Test Recipient Email', 'intellisend'); ?></label>
                        <div class="input-with-button">
                            <input type="email" name="testRecipient" id="test-recipient" value="<?php echo esc_attr($settings->testRecipient ?? ''); ?>" placeholder="email@example.com">
                            <button type="button" id="send-test-email" class="button button-secondary"><?php echo esc_html__('Send Test', 'intellisend'); ?></button>
                        </div>
                        <span class="field-description"><?php echo esc_html__('Changes are saved automatically 1 second after you stop typing.', 'intellisend'); ?></span>
                    </div>
                </div>

                <div class="intellisend-settings-row">
                    <div class="intellisend-settings-field">
                        <label for="logs-retention-days"><?php echo esc_html__('Logs Retention Period', 'intellisend'); ?></label>
                        <input type="hidden" name="logsRetentionDays" id="logs-retention-days" value="<?php echo esc_attr($settings->logsRetentionDays ?? 30); ?>">
                        <!-- Dropdown will be inserted here by JavaScript -->
                        <span class="field-description"><?php echo esc_html__('Changes are saved automatically when you select a different period.', 'intellisend'); ?></span>
                    </div>
                </div>

                <div class="intellisend-settings-row">
                    <div class="intellisend-settings-field">
                        <label for="debug-enabled"><?php echo esc_html__('Debug Mode', 'intellisend'); ?></label>
                        <div class="toggle-switch-container">
                            <label class="toggle-switch">
                                <input type="checkbox" name="debug_enabled" id="debug-enabled" value="1" <?php checked($settings->debug_enabled ?? 0); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                            <span class="toggle-label"><?php echo ($settings->debug_enabled ?? 0) ? esc_html__('Enabled', 'intellisend') : esc_html__('Disabled', 'intellisend'); ?></span>
                        </div>
                        <span class="field-description"><?php echo esc_html__('Enable detailed debug logging. Only enable when troubleshooting issues.', 'intellisend'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Anti-Spam Settings Form (Manual Save) -->
        <form id="intellisend-settings-form" method="post" action="">
            <div class="intellisend-settings-container">
                <div class="intellisend-settings-section">
                    <h2 class="intellisend-settings-section-title"><?php echo esc_html__('Anti-Spam Settings', 'intellisend'); ?></h2>

                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field">
                            <label for="anti-spam-endpoint"><?php echo esc_html__('Endpoint', 'intellisend'); ?></label>
                            <input type="url" name="antiSpamEndPoint" id="anti-spam-endpoint" value="<?php echo esc_attr($settings->antiSpamEndPoint ?? ''); ?>" placeholder="https://api.example.com/spam-check">
                        </div>

                        <div class="intellisend-settings-field">
                            <label for="api-key"><?php echo esc_html__('API Key', 'intellisend'); ?></label>
                            <div class="input-with-button">
                                <input type="password" name="antiSpamApiKey" id="api-key" value="" placeholder="Enter your API key">
                                <!-- Password toggle will be inserted here by JavaScript -->
                            </div>
                            <?php if (!empty($settings->antiSpamApiKey)) : ?>
                                <p class="api-key-info"><?php echo esc_html__('API key is stored securely. For security reasons, it is not displayed.', 'intellisend'); ?></p>
                                <input type="hidden" id="has-existing-api-key" value="1">
                            <?php else: ?>
                                <input type="hidden" id="has-existing-api-key" value="0">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="intellisend-settings-row">
                        <div class="intellisend-settings-field full-width">
                            <label for="spam-test-message"><?php echo esc_html__('Test Spam Message', 'intellisend'); ?></label>
                            <div class="textarea-with-button">
                                <textarea id="spam-test-message" rows="4" placeholder="Enter a test message to check if it's detected as spam..."><?php echo esc_attr($settings->spamTestMessage ?? 'URGENT: Your account has been compromised! Click here to verify: http://suspicious-link.com - Claim your $500 prize now! Limited time offer for Viagra and other medications at 90% discount. Reply with your credit card details.'); ?></textarea>
                                <button type="button" id="test-spam-detection" class="button button-secondary"><?php echo esc_html__('Test Spam Detection', 'intellisend'); ?></button>
                            </div>
                            <span class="field-description"><?php echo esc_html__('Use this to test if your spam detection is working correctly. This message is not saved.', 'intellisend'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="intellisend-settings-submit">
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Save Anti-Spam Settings', 'intellisend'); ?></button>
                </div>
            </div>
        </form>
    </div>
<?php
}
