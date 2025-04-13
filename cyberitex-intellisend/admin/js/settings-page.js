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
            this.setupFormSubmission();
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
         * Form submission handling
         */
        setupFormSubmission: function() {
            const self = this;
            const $form = $('#intellisend-settings-form');
            
            $form.on('submit', function(e) {
                e.preventDefault();
                
                // Validate form first
                if (!self.validateForm($form)) {
                    return false;
                }
                
                const $submitButton = $form.find('button[type="submit"]');
                const originalButtonText = $submitButton.text();
                
                // Add loading state
                self.setButtonLoading($submitButton, 'Saving...');
                
                // Disable all other buttons
                $form.find('button').not($submitButton).prop('disabled', true);
                
                // Get form data
                const formData = $form.serialize();
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_save_settings',
                        formData: formData,
                        nonce: $('#intellisend_settings_nonce').val()
                    },
                    success: function(response) {
                        // Remove any existing notices
                        $('.intellisend-notice').remove();
                        
                        if (response.success) {
                            // Show success message
                            self.showNotification($form, 'success', 'Settings saved successfully.');
                        } else {
                            // Show error message
                            self.showNotification($form, 'error', response.data || 'An error occurred while saving settings.');
                        }
                    },
                    error: function() {
                        // Show error message for network errors
                        $('.intellisend-notice').remove();
                        self.showNotification($form, 'error', 'A network error occurred. Please try again.');
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
            });
        },
        
        /**
         * Spam detection test implementation
         */
        setupSpamDetectionTest: function() {
            const self = this;
            const $button = $('#test-spam-detection');
            
            $button.on('click', function() {
                // Remove any previous results
                $('#spam-test-result').remove();
                
                const $apiKeyField = $('#api-key');
                const $endpointField = $('#anti-spam-endpoint');
                const $messageField = $('#spam-test-message');
                
                const apiKey = $apiKeyField.val();
                const endpoint = $endpointField.val();
                const testMessage = $messageField.val();
                
                // Validation
                self.clearValidationErrors();
                
                if (!apiKey) {
                    self.showFieldError($apiKeyField, 'Please enter an API key first');
                    return;
                }
                
                if (!endpoint) {
                    self.showFieldError($endpointField, 'Please enter an API endpoint first');
                    return;
                }
                
                if (!testMessage) {
                    self.showFieldError($messageField, 'Please enter a test message first');
                    return;
                }
                
                // API endpoint validation
                if (!self.isValidUrl(endpoint)) {
                    self.showFieldError($endpointField, 'Please enter a valid URL');
                    return;
                }
                
                // Set button to loading state
                const originalButtonText = $button.text();
                self.setButtonLoading($button, 'Testing...');
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_test_spam_detection',
                        apiKey: apiKey,
                        endpoint: endpoint,
                        message: testMessage,
                        nonce: $('#intellisend_settings_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show result
                            const resultHtml = self.createSpamTestResult(
                                response.data.is_spam, 
                                response.data.score
                            );
                            $messageField.after(resultHtml);
                        } else {
                            // Show error
                            const errorHtml = self.createTestErrorResult(
                                'Test failed', 
                                response.data || 'An unknown error occurred'
                            );
                            $messageField.after(errorHtml);
                        }
                    },
                    error: function() {
                        // Show network error
                        const errorHtml = self.createTestErrorResult(
                            'Test failed', 
                            'A network error occurred. Please try again.'
                        );
                        $messageField.after(errorHtml);
                    },
                    complete: function() {
                        // Reset button
                        self.resetButtonLoading($button, originalButtonText);
                    }
                });
            });
        },
        
        /**
         * Create spam test result HTML
         */
        createSpamTestResult: function(isSpam, score) {
            const resultClass = isSpam ? 'spam' : 'not-spam';
            const resultIcon = isSpam ? 
                '<span class="dashicons dashicons-warning"></span>' : 
                '<span class="dashicons dashicons-yes-alt"></span>';
            const resultMessage = isSpam ? 
                'Message detected as SPAM' : 
                'Message is NOT spam';
            
            return `
                <div id="spam-test-result" class="test-result ${resultClass}">
                    ${resultIcon}
                    <div class="test-result-content">
                        <strong>${resultMessage}</strong>
                        <span class="test-score">Score: ${score}</span>
                    </div>
                </div>
            `;
        },
        
        /**
         * Create test error result HTML
         */
        createTestErrorResult: function(title, message) {
            return `
                <div id="spam-test-result" class="test-result error">
                    <span class="dashicons dashicons-no-alt"></span>
                    <div class="test-result-content">
                        <strong>${title}</strong>
                        <span>${message}</span>
                    </div>
                </div>
            `;
        },
        
        /**
         * Send test email implementation
         */
        setupTestEmailSending: function() {
            const self = this;
            const $button = $('#send-test-email');
            
            $button.on('click', function() {
                // Remove any previous results
                $('#email-test-result').remove();
                
                const $emailField = $('#test-recipient');
                const $providerField = $('#default-provider');
                
                const testRecipient = $emailField.val();
                const defaultProvider = $providerField.val();
                
                // Validation
                self.clearValidationErrors();
                
                if (!testRecipient) {
                    self.showFieldError($emailField, 'Please enter a test recipient email address');
                    return;
                }
                
                if (!self.isValidEmail(testRecipient)) {
                    self.showFieldError($emailField, 'Please enter a valid email address');
                    return;
                }
                
                // Set button to loading state
                const originalButtonText = $button.text();
                self.setButtonLoading($button, 'Sending...');
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_ajax_handler',
                        sub_action: 'test_email_sent',
                        test_email: testRecipient,
                        provider_id: defaultProvider,
                        nonce: $('#intellisend_settings_nonce').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success result
                            const successHtml = `
                                <div id="email-test-result" class="test-result not-spam">
                                    <span class="dashicons dashicons-email-alt"></span>
                                    <div class="test-result-content">
                                        <strong>Test email sent successfully</strong>
                                        <span>A test email has been sent to ${testRecipient}</span>
                                    </div>
                                </div>
                            `;
                            $emailField.after(successHtml);
                        } else {
                            // Show error result
                            const errorHtml = self.createTestErrorResult(
                                'Failed to send test email', 
                                response.data || 'An unknown error occurred'
                            );
                            $emailField.after(errorHtml);
                        }
                    },
                    error: function() {
                        // Show network error
                        const errorHtml = self.createTestErrorResult(
                            'Failed to send test email', 
                            'A network error occurred. Please try again.'
                        );
                        $emailField.after(errorHtml);
                    },
                    complete: function() {
                        // Reset button
                        self.resetButtonLoading($button, originalButtonText);
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
            $button.prop('disabled', true).addClass('is-loading');
            if (loadingText) {
                $button.data('original-text', $button.text()).text(loadingText);
            }
            $button.append('<span class="loading-spinner"></span>');
        },
        
        /**
         * Helper: Reset button from loading state
         */
        resetButtonLoading: function($button, originalText) {
            $button.prop('disabled', false).removeClass('is-loading');
            $button.find('.loading-spinner').remove();
            if (originalText) {
                $button.text(originalText);
            }
        },
        
        /**
         * Helper: Show notification
         */
        showNotification: function($container, type, message) {
            const icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            
            const noticeHtml = `
                <div class="intellisend-notice ${type}">
                    <span class="intellisend-notice-icon dashicons ${icon}"></span>
                    <div class="intellisend-notice-content">${message}</div>
                </div>
            `;
            
            $container.before(noticeHtml);
            
            // Add animation class
            setTimeout(function() {
                $('.intellisend-notice').addClass('notice-visible');
            }, 10);
        },
        
        /**
         * Helper: Scroll to element
         */
        scrollToElement: function($element, offset = 0) {
            if ($element.length) {
                $('html, body').animate({
                    scrollTop: $element.offset().top + offset
                }, 300);
            }
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
                .test-score {
                    display: block;
                    margin-top: 4px;
                    font-size: 13px;
                    opacity: 0.8;
                }
                
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