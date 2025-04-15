/**
 * IntelliSend Providers Page JavaScript - Updated
 * 
 * Handles functionality for the email providers management page with improved UX.
 * Uses provider data from the database instead of hardcoded values.
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
            this.providerServerField = $('#provider-server');
            this.providerPortField = $('#provider-port');
            this.providerUsernameField = $('#provider-username');
            this.providerPasswordField = $('#provider-password');
            this.providerSenderField = $('#provider-sender');
            this.isDefaultField = $('#is-default');
            
            this.setupEventListeners();
            this.setupPasswordToggle();
            this.setupAutoPopulateSender();
            this.setupTooltips();
            this.handleInitialProvider();
            this.injectDynamicStyles();
            
            // Update UI state based on initial provider
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
            
            // Dismiss notification
            $(document).on('click', '.intellisend-notice', function() {
                $(this).fadeOut(300);
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
         * Auto-populate sender email based on username
         */
        setupAutoPopulateSender: function() {
            const self = this;
            
            this.providerUsernameField.on('input', function() {
                // Only auto-populate if sender field is empty
                if (self.providerSenderField.val() === '') {
                    self.providerSenderField.val($(this).val());
                }
            });
        },
        
        /**
         * Setup tooltips for form labels
         */
        setupTooltips: function() {
            // Create tooltip container if it doesn't exist
            if ($('#tooltip-container').length === 0) {
                $('body').append('<div id="tooltip-container"></div>');
            }
            
            // Handle tooltip display on hover
            $(document).on('mouseenter', 'label[data-tooltip]', function() {
                const tooltip = $(this).data('tooltip');
                const $container = $('#tooltip-container');
                
                $container.text(tooltip);
                
                // Position tooltip near the label
                const offset = $(this).offset();
                $container.css({
                    top: offset.top - $container.outerHeight() - 5,
                    left: offset.left + ($(this).outerWidth() / 2) - ($container.outerWidth() / 2)
                }).addClass('visible');
            });
            
            $(document).on('mouseleave', 'label[data-tooltip]', function() {
                $('#tooltip-container').removeClass('visible');
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
                this.updateProviderDescription(this.providerSelector.find('option:selected'));
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
                this.updateProviderDescription(this.providerSelector.find('option:selected'));
            }
            
            // Update UI state
            this.updateUIState();
        },
        
        /**
         * Update provider description in the info box using data from the option
         */
        updateProviderDescription: function($option) {
            const description = $option.data('description') || '';
            const helpLink = $option.data('help-link') || '';
            
            // Check if this provider has a description (unconfigured provider)
            if (description || helpLink) {
                // Update with fade effect
                $('#provider-description').fadeOut(200, function() {
                    let html = description;
                    
                    // Add help link if available
                    if (helpLink) {
                        html += ` <a href="${helpLink}" target="_blank" class="help-link">Learn More <span class="dashicons dashicons-external"></span></a>`;
                    }
                    
                    $(this).html(html).fadeIn(200);
                });
            } else {
                // No description available (configured provider), hide the description area
                $('#provider-description').fadeOut(200);
            }
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
            
            // Set sender field - use sender if available, otherwise use username
            const sender = $selectedOption.data('sender');
            const username = $selectedOption.data('username') || '';
            this.providerSenderField.val(sender || username);
            
            // Always clear password for security
            this.providerPasswordField.val('');
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
            $saveButton.prop('disabled', true).addClass('loading');
            $saveButton.data('original-text', $saveButton.text()).text('Saving...');
            
            // Get form data
            const formData = {
                action: 'intellisend_save_provider',
                nonce: $('#intellisend_providers_nonce').val(),
                provider_id: this.providerIdField.val(),
                provider_name: this.providerSelector.val(),
                provider_server: this.providerServerField.val(),
                provider_port: this.providerPortField.val(),
                provider_username: this.providerUsernameField.val(),
                provider_sender: this.providerSenderField.val(),
                is_default: this.isDefaultField.val()
            };
            
            // Only include password if it's not empty
            // This prevents clearing saved passwords when nothing is entered
            const password = this.providerPasswordField.val().trim();
            if (password) {
                formData.provider_password = password;
            }
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Update UI and show success message
                        self.showNotification('success', response.data.message);
                        
                        // Update the option attributes with new data
                        const $option = self.providerSelector.find('option[value="' + formData.provider_name + '"]');
                        $option.data('username', formData.provider_username);
                        $option.data('sender', formData.provider_sender);
                        $option.data('server', formData.provider_server);
                        $option.data('port', formData.provider_port);
                    } else {
                        // Show error message
                        self.showNotification('error', response.data.message || 'Failed to save provider');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred');
                },
                complete: function() {
                    // Reset button state
                    $saveButton.prop('disabled', false).removeClass('loading');
                    $saveButton.text($saveButton.data('original-text'));
                }
            });
        },
        
        /**
         * Reset form to current provider data
         */
        resetForm: function() {
            // Get current provider
            const currentProvider = this.providerSelector.val();
            
            // Load provider data
            this.loadProviderData(currentProvider);
            
            // Show brief notification
            this.showNotification('info', 'Form reset to saved values');
        },
        
        /**
         * Validate form before submission
         */
        validateForm: function() {
            // Remove any previous error messages
            $('.field-error').remove();
            $('.has-error').removeClass('has-error');
            
            let isValid = true;
            
            // Validate server for "other" provider
            if (this.providerSelector.val() === 'other' && !this.providerServerField.val().trim()) {
                this.showFieldError(this.providerServerField, 'SMTP Server is required');
                isValid = false;
            }
            
            // Validate username
            if (!this.providerUsernameField.val().trim()) {
                this.showFieldError(this.providerUsernameField, 'Username is required');
                isValid = false;
            }
            
            // Validate password if provider ID is empty (new provider)
            const providerId = this.providerIdField.val();
            const password = this.providerPasswordField.val().trim();
            
            // Only require password for new providers (when ID is empty)
            if (!providerId && !password) {
                this.showFieldError(this.providerPasswordField, 'Password is required for new providers');
                isValid = false;
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
         * Show notification
         */
        showNotification: function(type, message) {
            // Remove any existing notifications
            $('.intellisend-notification').remove();
            
            const notification = $('<div class="intellisend-notification ' + type + '">' + message + '</div>');
            
            $('body').append(notification);
            
            // Show notification
            setTimeout(function() {
                notification.addClass('show');
            }, 10);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 3000);
        },
        
        /**
         * Update UI state based on current selection
         */
        updateUIState: function() {
            const selectedProvider = this.providerSelector.val();
            
            // Show SMTP server and port fields only if provider is "other"
            if (selectedProvider && selectedProvider.toLowerCase() === 'other') {
                $('.smtp-field').slideDown(300);
            } else {
                $('.smtp-field').slideUp(300);
            }
        },
        
        /**
         * Inject dynamic styles
         */
        injectDynamicStyles: function() {
            const styles = `
                /* Tooltips */
                #tooltip-container {
                    position: absolute;
                    background: #23282d;
                    color: #fff;
                    padding: 6px 10px;
                    border-radius: 4px;
                    font-size: 12px;
                    max-width: 250px;
                    z-index: 999999;
                    opacity: 0;
                    transition: opacity 0.2s ease;
                    pointer-events: none;
                }
                
                #tooltip-container.visible {
                    opacity: 0.9;
                }
                
                #tooltip-container:after {
                    content: '';
                    position: absolute;
                    bottom: -5px;
                    left: 50%;
                    margin-left: -5px;
                    border-width: 5px 5px 0;
                    border-style: solid;
                    border-color: #23282d transparent;
                }
                
                /* Help Link */
                .help-link {
                    display: inline-block;
                    margin-left: 8px;
                    color: #2271b1;
                    text-decoration: none;
                    font-size: 12px;
                    font-weight: 500;
                    vertical-align: middle;
                }
                
                .help-link:hover {
                    text-decoration: underline;
                    color: #135e96;
                }
                
                .help-link .dashicons {
                    font-size: 14px;
                    width: 14px;
                    height: 14px;
                    vertical-align: text-bottom;
                }
                
                /* Notifications */
                .intellisend-notification {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    padding: 10px 15px;
                    border-radius: 4px;
                    background: #fff;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    transform: translateX(120%);
                    transition: transform 0.3s ease;
                    z-index: 99999;
                    font-size: 14px;
                }
                
                .intellisend-notification.show {
                    transform: translateX(0);
                }
                
                .intellisend-notification.success {
                    border-left: 4px solid #46b450;
                }
                
                .intellisend-notification.error {
                    border-left: 4px solid #dc3232;
                }
                
                .intellisend-notification.info {
                    border-left: 4px solid #00a0d2;
                }
                
                /* Field errors */
                .has-error {
                    border-color: #dc3232 !important;
                }
                
                .field-error {
                    color: #dc3232;
                    font-size: 12px;
                    display: block;
                    margin-top: 5px;
                }
                
                /* Loading state */
                .loading:after {
                    content: '';
                    display: inline-block;
                    width: 12px;
                    height: 12px;
                    margin-left: 8px;
                    border: 2px solid rgba(255,255,255,0.3);
                    border-radius: 50%;
                    border-top-color: #fff;
                    animation: spin 0.8s linear infinite;
                    vertical-align: middle;
                }
                
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            `;
            
            $('<style id="intellisend-dynamic-styles"></style>')
                .text(styles)
                .appendTo('head');
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendProviders.init();
    });

})(jQuery);