/**
 * IntelliSend Providers Page JavaScript
 * 
 * Handles functionality for the email providers management page.
 */

(function($) {
    'use strict';

    // Main IntelliSend Providers object
    const IntelliSendProviders = {
        /**
         * Initialize all components
         */
        init: function() {
            this.providerSelector = $('#provider-selector');
            this.providerForm = $('#provider-form');
            this.providerIdField = $('#provider-id');
            this.otherProviderFields = $('#other-provider-fields');
            this.providerServerField = $('#provider-server');
            this.providerPortField = $('#provider-port');
            this.providerUsernameField = $('#provider-username');
            this.providerPasswordField = $('#provider-password');
            this.isDefaultField = $('#is-default');
            
            this.setupEventListeners();
            this.setupPasswordToggle();
            this.handleInitialProvider();
            this.injectCustomStyles();
            
            // Start with initial UI state
            this.updateUIState();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            const self = this;
            
            // Provider selector change
            this.providerSelector.on('change', function() {
                self.handleProviderSelection();
            });
            
            // Form submission
            this.providerForm.on('submit', function(e) {
                e.preventDefault();
                self.saveProvider();
            });
            
            // Reset button
            $('#reset-provider-btn').on('click', function() {
                self.resetForm();
            });
        },
        
        /**
         * Password visibility toggle
         */
        setupPasswordToggle: function() {
            const $passwordField = $('#provider-password');
            const $toggleButton = $('.password-toggle');
            
            $toggleButton.on('click', function() {
                const isVisible = $passwordField.attr('type') === 'text';
                
                // Toggle visibility
                $passwordField.attr('type', isVisible ? 'password' : 'text');
                $(this).toggleClass('show-password');
                
                // Focus back on the field
                $passwordField.focus();
            });
        },
        
        /**
         * Handle initial provider selection
         */
        handleInitialProvider: function() {
            // Load the initially selected provider
            const initialProvider = this.providerSelector.val();
            if (initialProvider) {
                this.loadProviderData(initialProvider);
            }
        },
        
        /**
         * Handle provider selection change
         */
        handleProviderSelection: function() {
            const selectedProvider = this.providerSelector.val();
            
            // Load selected provider data
            if (selectedProvider) {
                this.loadProviderData(selectedProvider);
            }
            
            // Update UI state
            this.updateUIState();
        },
        
        /**
         * Load provider data into form
         */
        loadProviderData: function(providerName) {
            // Get the selected option
            const $selectedOption = this.providerSelector.find('option[value="' + providerName + '"]');
            
            // Set form fields
            this.providerIdField.val($selectedOption.data('id') || '');
            this.providerServerField.val($selectedOption.data('server') || '');
            this.providerPortField.val($selectedOption.data('port') || '587');
            this.providerUsernameField.val($selectedOption.data('username') || '');
            this.providerPasswordField.val(''); // Always clear password for security
        },
        
        /**
         * Save provider data
         */
        saveProvider: function() {
            // Validate form
            if (!this.validateForm()) {
                return;
            }
            
            const self = this;
            const $saveButton = $('#save-provider-btn');
            
            // Set loading state
            this.setButtonLoading($saveButton, 'Saving...');
            
            // Get form data
            const formData = {
                action: 'intellisend_save_provider',
                nonce: $('#intellisend_providers_nonce').val(),
                provider_id: this.providerIdField.val(),
                provider_name: this.providerSelector.val(),
                provider_server: this.providerServerField.val(),
                provider_port: this.providerPortField.val(),
                provider_username: this.providerUsernameField.val(),
                provider_password: this.providerPasswordField.val(),
                is_default: this.isDefaultField.val()
            };
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        self.updateProviderSelector(formData);
                        
                        // Show success message
                        self.showNotification('success', response.data.message);
                    } else {
                        // Show error message
                        self.showNotification('error', response.data.message);
                    }
                },
                error: function() {
                    self.showNotification('error', 'An error occurred while saving the provider.');
                },
                complete: function() {
                    self.resetButtonLoading($saveButton, 'Save Provider');
                }
            });
        },
        
        /**
         * Update provider selector option with new data
         */
        updateProviderSelector: function(formData) {
            const $option = this.providerSelector.find('option[value="' + formData.provider_name + '"]');
            
            // Update option data attributes
            $option.data('id', formData.provider_id);
            $option.data('server', formData.provider_server);
            $option.data('port', formData.provider_port);
            $option.data('username', formData.provider_username);
        },
        
        /**
         * Reset form to current provider data
         */
        resetForm: function() {
            const currentProvider = this.providerSelector.val();
            this.loadProviderData(currentProvider);
            this.clearValidationErrors();
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function() {
            this.clearValidationErrors();
            
            let isValid = true;
            
            // Validate server
            if (!this.providerServerField.val().trim()) {
                this.showFieldError(this.providerServerField, 'SMTP Server is required');
                isValid = false;
            }
            
            // Validate username
            if (!this.providerUsernameField.val().trim()) {
                this.showFieldError(this.providerUsernameField, 'Username is required');
                isValid = false;
            }
            
            // Validate password if provider ID is empty (new provider) or password is provided
            const providerId = this.providerIdField.val();
            const password = this.providerPasswordField.val().trim();
            
            if (!providerId && !password) {
                this.showFieldError(this.providerPasswordField, 'Password is required');
                isValid = false;
            }
            
            if (!isValid) {
                this.providerForm.find('.has-error').first().focus();
            }
            
            return isValid;
        },
        
        /**
         * Show field validation error
         */
        showFieldError: function($field, message) {
            $field.addClass('has-error');
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
         * Show notification
         */
        showNotification: function(type, message) {
            // Remove any existing notifications
            $('.intellisend-notice').remove();
            
            const icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            const noticeHtml = `
                <div class="intellisend-notice ${type}">
                    <span class="intellisend-notice-icon dashicons ${icon}"></span>
                    <div class="intellisend-notice-content">${message}</div>
                </div>
            `;
            
            this.providerForm.before(noticeHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $('.intellisend-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },
        
        /**
         * Set button to loading state
         */
        setButtonLoading: function($button, loadingText) {
            $button.prop('disabled', true).addClass('is-loading');
            $button.data('original-text', $button.text()).text(loadingText);
            $button.append('<span class="loading-spinner"></span>');
        },
        
        /**
         * Reset button from loading state
         */
        resetButtonLoading: function($button, originalText) {
            $button.prop('disabled', false).removeClass('is-loading');
            $button.find('.loading-spinner').remove();
            $button.text(originalText);
        },
        
        /**
         * Update UI state based on current selection
         */
        updateUIState: function() {
            const selectedProvider = this.providerSelector.val();
            
            // Show SMTP server and port fields only if provider is "other"
            if (selectedProvider && selectedProvider.toLowerCase() === 'other') {
                $('.smtp-field').show();
            } else {
                $('.smtp-field').hide();
            }
        },
        
        /**
         * Inject custom styles for dynamic elements
         */
        injectCustomStyles: function() {
            const customStyles = `
                /* Field focus effects */
                .provider-field input:focus + label,
                .provider-field select:focus + label {
                    color: #2271b1;
                }
                
                /* Loading overlay */
                .providers-loading-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.7);
                    z-index: 99999;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                
                /* Fade effects */
                .fade-enter {
                    opacity: 0;
                    transition: opacity 0.3s ease;
                }
                
                .fade-enter-active {
                    opacity: 1;
                }
                
                .fade-exit {
                    opacity: 1;
                    transition: opacity 0.3s ease;
                }
                
                .fade-exit-active {
                    opacity: 0;
                }
            `;
            
            $('<style id="intellisend-providers-dynamic-styles"></style>')
                .text(customStyles)
                .appendTo('head');
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendProviders.init();
    });

})(jQuery);