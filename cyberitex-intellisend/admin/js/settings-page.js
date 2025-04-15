/**
 * IntelliSend Settings Page JavaScript
 * 
 * Handles enhanced functionality for the settings page with improved UX.
 */

(function($) {
    'use strict';

    // Main IntelliSend Settings object
    const IntelliSendSettings = {
        /**
         * Initialize all components
         */
        init: function() {
            this.setupPasswordToggle();
            this.setupLogsRetentionDropdown();
            this.handleFormSubmit();
            this.setupSpamDetectionTest();
            this.setupTestEmailSending();
            this.setupFormAnimations();
            this.setupSectionToggle();
            this.setupPageLoadEffects();
            this.injectCustomStyles();
        },

        /**
         * Password visibility toggle implementation
         */
        setupPasswordToggle: function() {
            const eyeOpenSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeClosedSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            
            const $apiKeyField = $('#api-key');
            
            // Create container and toggle button
            $apiKeyField.wrap('<div class="password-field-container"></div>');
            const $toggleButton = $('<button type="button" class="password-toggle" aria-label="Toggle password visibility">' + eyeClosedSvg + '</button>');
            $apiKeyField.after($toggleButton);
            
            // Initialize as password type
            $apiKeyField.attr('type', 'password');
            
            // Handle toggle click
            $toggleButton.on('click', function(e) {
                e.preventDefault();
                
                const isVisible = $apiKeyField.attr('type') === 'text';
                
                // Toggle visibility
                $apiKeyField.attr('type', isVisible ? 'password' : 'text');
                $(this).html(isVisible ? eyeClosedSvg : eyeOpenSvg);
                $apiKeyField.focus();
            });
        },
        
        /**
         * Logs retention dropdown implementation
         */
        setupLogsRetentionDropdown: function() {
            const retentionOptions = [
                { label: '1 Week', days: 7 },
                { label: '1 Month', days: 30 },
                { label: '3 Months', days: 90 },
                { label: '6 Months', days: 180 },
                { label: '1 Year', days: 365 }
            ];
            
            const $originalInput = $('#logs-retention-days');
            const currentDays = parseInt($originalInput.val(), 10) || 30;
            
            // Create and populate select element
            const $select = $('<select id="logs-retention-select" class="logs-retention-select"></select>');
            
            let hasMatchingOption = false;
            
            // Add standard options
            $.each(retentionOptions, function(index, option) {
                const $option = $('<option></option>')
                    .val(option.days)
                    .text(option.label);
                
                if (currentDays === option.days) {
                    $option.prop('selected', true);
                    hasMatchingOption = true;
                }
                
                $select.append($option);
            });
            
            // Add custom option if needed
            if (!hasMatchingOption && currentDays > 0) {
                const $customOption = $('<option></option>')
                    .val(currentDays)
                    .text(currentDays + ' Days (Custom)')
                    .prop('selected', true);
                
                $select.append($customOption);
            } else if (!hasMatchingOption) {
                // Default to 30 days if no valid value
                $select.find('option[value="30"]').prop('selected', true);
                $originalInput.val(30);
            }
            
            // Add select to DOM
            $originalInput.after($select);
            
            // Listen for changes
            $select.on('change', function() {
                const newVal = $(this).val();
                $originalInput.val(newVal);
                
                // Add highlight effect
                $(this).addClass('highlight-field');
                setTimeout(() => {
                    $(this).removeClass('highlight-field');
                }, 600);
            });
        },
        
        /**
         * Handle form submission
         */
        handleFormSubmit: function() {
            const self = this;
            const $form = $('#intellisend-settings-form');
            
            $form.on('submit', function(e) {
                e.preventDefault();
                
                // Get form data
                const apiKey = $('#api-key').val();
                const endpoint = $('#anti-spam-endpoint').val();
                const hasExistingApiKey = $('#has-existing-api-key').val() === '1';
                
                // Get submit button
                const $submitButton = $form.find('button[type="submit"]');
                const originalButtonText = $submitButton.text();
                
                // Show loading state
                self.setButtonLoading($submitButton, 'Saving...');
                
                // Disable all buttons
                $form.find('button').prop('disabled', true);
                
                // Check if a new API key is provided
                if (apiKey.trim() !== '') {
                    // Validate the new API key
                    self.validateApiKey(apiKey, endpoint, function(isValid) {
                        if (isValid) {
                            // API key is valid, save settings
                            self.saveSettings($form, $submitButton, originalButtonText);
                        } else {
                            // API key is invalid, reset button state
                            self.resetButtonLoading($submitButton, originalButtonText);
                            
                            // Re-enable all buttons
                            $form.find('button').prop('disabled', false);
                        }
                    });
                } else if (hasExistingApiKey) {
                    // No new API key provided, but an existing one is in the database
                    // Just save the settings without API key validation
                    self.saveSettings($form, $submitButton, originalButtonText);
                } else if (endpoint.trim() !== '') {
                    // No API key provided (new or existing) but endpoint is specified
                    // Show error message
                    IntelliSendToast.error('API key is required when an endpoint is specified');
                    self.resetButtonLoading($submitButton, originalButtonText);
                    $form.find('button').prop('disabled', false);
                } else {
                    // No API key or endpoint provided, just save settings
                    self.saveSettings($form, $submitButton, originalButtonText);
                }
                
                // Prevent default form submission
                return false;
            });
        },
        
        /**
         * Validate API key with the server
         */
        validateApiKey: function(apiKey, endpoint, callback) {
            const self = this;
            
            // Send AJAX request to validate API key
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_ajax_handler',
                    sub_action: 'api_checked',
                    api_key: apiKey,
                    endpoint: endpoint,
                    nonce: $('#intellisend_settings_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        // API key is valid
                        IntelliSendToast.success(response.data.message);
                        callback(true);
                    } else {
                        // API key is invalid
                        IntelliSendToast.error(response.data.message);
                        callback(false);
                    }
                },
                error: function(xhr, status, error) {
                    // Network error
                    IntelliSendToast.error('A network error occurred while validating the API key. Please try again.');
                    callback(false);
                }
            });
        },
        
        /**
         * Save settings to the server
         */
        saveSettings: function($form, $submitButton, originalButtonText) {
            const self = this;
            
            // Get form data
            const formData = $form.serialize();
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_ajax_handler',
                    sub_action: 'settings_saved',
                    formData: formData,
                    nonce: $('#intellisend_settings_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        IntelliSendToast.success('Settings saved successfully.');
                        
                        // Clear API key field for security
                        $('#api-key').val('');
                    } else {
                        // Show error message
                        const errorMessage = response.data && response.data.message ? response.data.message : 'An error occurred while saving settings.';
                        IntelliSendToast.error(errorMessage);
                    }
                },
                error: function() {
                    // Show error message for network errors
                    IntelliSendToast.error('A network error occurred. Please try again.');
                },
                complete: function() {
                    // Reset button state
                    self.resetButtonLoading($submitButton, originalButtonText);
                    
                    // Re-enable all buttons
                    $form.find('button').prop('disabled', false);
                    
                    // Scroll to notification
                    self.scrollToElement($form.parent(), -50);
                }
            });
        },
        
        /**
         * Spam detection test implementation
         */
        setupSpamDetectionTest: function() {
            const self = this;
            const $testButton = $('#test-spam-detection');
            const $messageField = $('#spam-test-message');
            const $apiKeyField = $('#api-key');
            const $endpointField = $('#anti-spam-endpoint');
            
            $testButton.on('click', function() {
                // Validate required fields
                let isValid = true;
                const hasExistingApiKey = $('#has-existing-api-key').val() === '1';
                
                if (!$messageField.val().trim()) {
                    self.showFieldError($messageField, 'Please enter a message to test');
                    isValid = false;
                }
                
                if (!$apiKeyField.val().trim() && !hasExistingApiKey) {
                    self.showFieldError($apiKeyField, 'API key is required');
                    isValid = false;
                }
                
                if (!$endpointField.val().trim()) {
                    self.showFieldError($endpointField, 'Endpoint is required');
                    isValid = false;
                } else if (!self.isValidUrl($endpointField.val())) {
                    self.showFieldError($endpointField, 'Please enter a valid URL');
                    isValid = false;
                }
                
                if (!isValid) {
                    return;
                }
                
                // Clear previous results
                $('.test-result').remove();
                
                // Get values
                const message = $messageField.val();
                const apiKey = $apiKeyField.val();
                const endpoint = $endpointField.val();
                
                // Set button to loading state
                const originalButtonText = $testButton.text();
                self.setButtonLoading($testButton, 'Testing...');
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_ajax_handler',
                        sub_action: 'spam_test_sent',
                        message: message,
                        api_key: apiKey,
                        endpoint: endpoint,
                        nonce: $('#intellisend_settings_nonce').val(),
                        use_existing_key: hasExistingApiKey && !apiKey.trim() ? 1 : 0
                    },
                    success: function(response) {
                        if (response.success) {
                            const isSpam = response.data.isSpam;
                            // Use IntelliSendToast instead of createSpamTestResult
                            IntelliSendToast.spamResult(isSpam);
                        } else {
                            const errorMessage = response.data && response.data.message ? response.data.message : 'An error occurred during spam detection.';
                            IntelliSendToast.error(errorMessage);
                        }
                    },
                    error: function() {
                        IntelliSendToast.error('A network error occurred. Please try again.');
                    },
                    complete: function() {
                        self.resetButtonLoading($testButton, originalButtonText);
                    }
                });
            });
        },
        
        /**
         * Send test email implementation
         */
        setupTestEmailSending: function() {
            const self = this;
            const $testButton = $('#send-test-email');
            const $emailField = $('#test-recipient');
            const $providerField = $('#default-provider');
            
            $testButton.on('click', function() {
                // Validate required fields
                let isValid = true;
                
                if (!$emailField.val().trim()) {
                    self.showFieldError($emailField, 'Please enter a recipient email');
                    isValid = false;
                } else if (!self.isValidEmail($emailField.val())) {
                    self.showFieldError($emailField, 'Please enter a valid email address');
                    isValid = false;
                }
                
                if (!$providerField.val()) {
                    self.showFieldError($providerField, 'Please select a provider');
                    isValid = false;
                }
                
                if (!isValid) {
                    return;
                }
                
                // Clear previous results
                $('.test-result').remove();
                
                // Get values
                const email = $emailField.val();
                const provider = $providerField.val();
                
                // Set button to loading state
                const originalButtonText = $testButton.text();
                self.setButtonLoading($testButton, 'Sending...');
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_ajax_handler',
                        sub_action: 'test_email_sent',
                        email: email,
                        provider: provider,
                        nonce: $('#intellisend_settings_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            IntelliSendToast.success(response.data.message || 'Test email sent successfully!');
                        } else {
                            const errorMessage = response.data && response.data.message ? response.data.message : 'An error occurred while sending test email.';
                            IntelliSendToast.error(errorMessage);
                        }
                    },
                    error: function() {
                        IntelliSendToast.error('A network error occurred. Please try again.');
                    },
                    complete: function() {
                        self.resetButtonLoading($testButton, originalButtonText);
                    }
                });
            });
        },
        
        /**
         * Form validation utilities
         */
        validateForm: function($form) {
            let isValid = true;
            const self = this;
            
            // Clear previous validation errors
            self.clearValidationErrors();
            
            // Validate email field if it's not empty
            const $emailField = $('#test-recipient');
            if ($emailField.length && $emailField.val() !== '') {
                if (!self.isValidEmail($emailField.val())) {
                    self.showFieldError($emailField, 'Please enter a valid email address');
                    isValid = false;
                }
            }
            
            // Validate URL field if it's not empty
            const $urlField = $('#anti-spam-endpoint');
            if ($urlField.length && $urlField.val() !== '') {
                if (!self.isValidUrl($urlField.val())) {
                    self.showFieldError($urlField, 'Please enter a valid URL');
                    isValid = false;
                }
            }
            
            // If not valid, scroll to first error
            if (!isValid) {
                const $firstError = $form.find('.has-error').first();
                if ($firstError.length) {
                    self.scrollToElement($firstError, -100);
                    $firstError.focus();
                }
            }
            
            return isValid;
        },
        
        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            // Add error class and message
            $field.addClass('has-error');
            
            // Remove any existing error for this field
            $field.siblings('.field-error').remove();
            
            // Add error message
            $field.after('<span class="field-error">' + message + '</span>');
        },
        
        /**
         * Clear all validation errors
         */
        clearValidationErrors: function() {
            $('.has-error').removeClass('has-error');
            $('.field-error').remove();
        },
        
        /**
         * Email validation
         */
        isValidEmail: function(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },
        
        /**
         * URL validation
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },
        
        /**
         * UI Enhancement: Form animations
         */
        setupFormAnimations: function() {
            const $fields = $('.intellisend-settings-field input, .intellisend-settings-field select, .intellisend-settings-field textarea');
            
            // Add focus/blur effects
            $fields.on('focus', function() {
                $(this).closest('.intellisend-settings-field').addClass('field-focus');
            }).on('blur', function() {
                $(this).closest('.intellisend-settings-field').removeClass('field-focus');
            });
        },
        
        /**
         * UI Enhancement: Section expand/collapse
         */
        setupSectionToggle: function() {
            // Add toggle indicators to section titles
            $('.intellisend-settings-section-title').append('<span class="section-toggle-indicator dashicons dashicons-arrow-up-alt2"></span>');
            
            // Add toggle click handler
            $('.intellisend-settings-section-title').on('click', function() {
                const $section = $(this).parent();
                const $content = $section.find('.intellisend-settings-row');
                const $indicator = $(this).find('.section-toggle-indicator');
                
                if ($content.is(':visible')) {
                    $content.slideUp(300);
                    $indicator.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
                    $section.addClass('section-collapsed');
                } else {
                    $content.slideDown(300);
                    $indicator.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
                    $section.removeClass('section-collapsed');
                }
            });
        },
        
        /**
         * UI Enhancement: Page load effects
         */
        setupPageLoadEffects: function() {
            // Add loading animation
            $('<div id="page-loading-overlay"><div class="loading-spinner-large"></div></div>').appendTo('body');
            
            // Remove loading overlay after a short delay
            $(window).on('load', function() {
                setTimeout(function() {
                    $('#page-loading-overlay').fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    // Fade in sections sequentially
                    $('.intellisend-settings-section').each(function(index) {
                        const $section = $(this);
                        setTimeout(function() {
                            $section.addClass('section-visible');
                        }, 100 * index);
                    });
                }, 300);
            });
        },
        
        /**
         * Helper: Set button to loading state
         */
        setButtonLoading: function($button, loadingText) {
            $button.data('original-text', $button.text())
                .html('<span class="dashicons dashicons-update spinning"></span> ' + loadingText)
                .prop('disabled', true);
        },
        
        /**
         * Helper: Reset button from loading state
         */
        resetButtonLoading: function($button, originalText) {
            $button.html(originalText || $button.data('original-text'))
                .prop('disabled', false);
        },
        
        /**
         * Helper: Scroll to element
         */
        scrollToElement: function($element, offset = 0) {
            $('html, body').animate({
                scrollTop: $element.offset().top + offset
            }, 300);
        },
        
        /**
         * Inject custom CSS styles
         */
        injectCustomStyles: function() {
            const customStyles = `
                /* Form validation styles */
                .has-error {
                    border-color: #d63638 !important;
                    box-shadow: 0 0 0 1px #d63638 !important;
                }
                .field-error {
                    color: #d63638;
                    font-size: 13px;
                    margin-top: 5px;
                    display: block;
                    font-style: italic;
                }
                
                /* Field focus effects */
                .field-focus label {
                    color: #2271b1 !important;
                    transition: color 0.2s ease;
                }
                
                /* Highlight effect */
                .highlight-field {
                    background-color: rgba(34, 113, 177, 0.05);
                    transition: background-color 0.6s ease;
                }
                
                /* Test result styles */
                .test-result {
                    display: flex;
                    align-items: flex-start;
                    padding: 12px 16px;
                    margin-top: 10px;
                    border-radius: 6px;
                    background: #f8f9fa;
                    animation: slideIn 0.3s ease;
                }
                .test-result.spam { background: #fcf0f1; }
                .test-result.not-spam { background: #f0f6e9; }
                .test-result.error { background: #fcf0f1; }
                
                .test-result .dashicons {
                    margin-right: 10px;
                    font-size: 20px;
                }
                .test-result.spam .dashicons { color: #d63638; }
                .test-result.not-spam .dashicons { color: #46b450; }
                .test-result.error .dashicons { color: #d63638; }
                
                .test-result-content { flex: 1; }
                
                /* Section toggle styles */
                .intellisend-settings-section-title {
                    cursor: pointer;
                    position: relative;
                    transition: color 0.2s ease;
                }
                .intellisend-settings-section-title:hover {
                    color: #2271b1;
                }
                .section-toggle-indicator {
                    position: absolute;
                    right: 0;
                    top: 50%;
                    transform: translateY(-50%);
                    transition: transform 0.3s ease;
                }
                .section-collapsed {
                    background-color: #f8f9fa;
                }
                
                /* Page loading animation */
                #page-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255,255,255,0.9);
                    z-index: 9999;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                .loading-spinner-large {
                    width: 40px;
                    height: 40px;
                    border: 3px solid rgba(34,113,177,0.3);
                    border-radius: 50%;
                    border-top-color: #2271b1;
                    animation: spin 1s linear infinite;
                }
                
                /* Section visibility animation */
                .intellisend-settings-section {
                    opacity: 0;
                    transform: translateY(10px);
                    transition: opacity 0.4s ease, transform 0.4s ease;
                }
                .section-visible {
                    opacity: 1;
                    transform: translateY(0);
                }
                
                /* Notice animation */
                .intellisend-notice {
                    opacity: 0;
                    transform: translateY(-10px);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                }
                .notice-visible {
                    opacity: 1;
                    transform: translateY(0);
                }
                
                /* Toast styles */
                .intellisend-toast {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 9999;
                    padding: 10px 20px;
                    border-radius: 6px;
                    background: #f8f9fa;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    display: flex;
                    align-items: center;
                    transform: translateY(10px);
                    opacity: 0;
                    transition: transform 0.3s ease, opacity 0.3s ease;
                }
                .intellisend-toast.show {
                    transform: translateY(0);
                    opacity: 1;
                }
                .intellisend-toast.spam {
                    background: #fcf0f1;
                }
                .intellisend-toast.not-spam {
                    background: #f0f6e9;
                }
                .intellisend-toast.error {
                    background: #fcf0f1;
                }
                
                .intellisend-toast .dashicons {
                    margin-right: 10px;
                    font-size: 20px;
                }
                .intellisend-toast.spam .dashicons { color: #d63638; }
                .intellisend-toast.not-spam .dashicons { color: #46b450; }
                .intellisend-toast.error .dashicons { color: #d63638; }
                
                .intellisend-toast .toast-content { flex: 1; }
                
                /* Animations */
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                /* Debug info styles */
                .api-debug-container {
                    margin-top: 20px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 4px;
                    border: 1px solid #ddd;
                }
                
                .debug-section {
                    padding: 10px;
                    border-radius: 4px;
                }
                
                .debug-section.success {
                    background: rgba(70, 180, 80, 0.1);
                    border-left: 4px solid #46b450;
                }
                
                .debug-section.error {
                    background: rgba(214, 54, 56, 0.1);
                    border-left: 4px solid #d63638;
                }
                
                .debug-section h3 {
                    margin-top: 0;
                    color: #23282d;
                }
                
                .debug-section pre {
                    background: rgba(0, 0, 0, 0.05);
                    padding: 10px;
                    overflow: auto;
                    max-height: 200px;
                    font-family: monospace;
                    font-size: 12px;
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                
                .debug-note {
                    font-style: italic;
                    color: #666;
                    margin-top: 10px;
                }
                
                .debug-headers, .debug-body {
                    margin-bottom: 10px;
                }
                
                /* Dark mode enhancements */
                @media (prefers-color-scheme: dark) {
                    #page-loading-overlay {
                        background: rgba(30, 30, 30, 0.9);
                    }
                    .test-result {
                        background: #2c3338;
                    }
                    .test-result.spam {
                        background: rgba(214, 54, 56, 0.1);
                    }
                    .test-result.not-spam {
                        background: rgba(70, 180, 80, 0.1);
                    }
                    .test-result.error {
                        background: rgba(214, 54, 56, 0.1);
                    }
                    .section-collapsed {
                        background-color: #23282d;
                    }
                    .api-debug-container {
                        background: #2c3338;
                        border-color: #1d2327;
                    }
                    
                    .debug-section h3 {
                        color: #f0f0f1;
                    }
                    
                    .debug-section pre {
                        background: rgba(0, 0, 0, 0.2);
                        color: #bbb;
                    }
                    
                    .debug-note {
                        color: #aaa;
                    }
                }
            `;
            
            $('<style id="intellisend-custom-styles"></style>')
                .text(customStyles)
                .appendTo('head');
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendSettings.init();
    });

})(jQuery);