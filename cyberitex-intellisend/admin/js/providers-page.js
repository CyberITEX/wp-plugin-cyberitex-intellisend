// cyberitex-intellisend/admin/js/providers-page.js
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
            this.providerTypeField = $('#provider-type');
            this.providerApiKeyField = $('#provider-api-key');
            this.providerApiEndpointField = $('#provider-api-endpoint');
            this.providerApiIdentityField = $('#provider-api-identity');
            this.testRecipientField = $('#provider-test-recipient');

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
            
            // Test API key button
            $('#test-api-key-btn').on('click', function() {
                self.testApiKey();
            });

            // Send a real test message through the selected provider
            $('#send-provider-test-btn').on('click', function() {
                self.sendTestEmail();
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
            $('.password-toggle').on('click', function() {
                // Reveal the secret field that sits inside the same container
                const $field = $(this).closest('.password-field-container').find('input');
                const isVisible = $field.attr('type') === 'text';

                $field.attr('type', isVisible ? 'password' : 'text');
                $(this).toggleClass('show-password');
                $field.focus();
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
         * Report the missing-transport case rather than silently doing nothing
         */
        requireTransport: function() {
            const transport = this.currentTransport();

            if (!transport) {
                this.showNotification('error', 'No API transport is registered for this provider.');
                return null;
            }

            return transport;
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
            this.providerTypeField.val($selectedOption.data('type') || 'smtp');
            this.providerServerField.val($selectedOption.data('server') || '');
            this.providerPortField.val($selectedOption.data('port') || '587');
            this.providerUsernameField.val($selectedOption.data('username') || '');
            
            // Set sender field - use sender if available, otherwise use username
            const sender = $selectedOption.data('sender');
            const username = $selectedOption.data('username') || '';
            this.providerSenderField.val(sender || username);

            // Always clear secrets for security
            this.providerPasswordField.val('');
            this.providerApiKeyField.val('');
            this.providerApiIdentityField.val('');

            if (this.providerTypeField.val() === 'api') {
                this.applyTransport(providerName, $selectedOption);
            }
        },

        /**
         * Whether the selected provider sends over an HTTP API
         */
        isApiProvider: function() {
            return this.providerTypeField.val() === 'api';
        },

        /**
         * Description of the transport backing the selected API provider
         */
        currentTransport: function() {
            const all = (window.intellisendProviders && window.intellisendProviders.transports) || {};
            return all[this.providerSelector.val()] || null;
        },

        /**
         * Fill the API fields from the selected transport's description:
         * region choices, key placeholder, hints, and whether the key is
         * managed outside the database.
         */
        applyTransport: function(providerName, $selectedOption) {
            const all = (window.intellisendProviders && window.intellisendProviders.transports) || {};
            const transport = all[providerName];

            if (!transport) {
                return;
            }

            // Field labels come from the transport, so wording matches the vendor
            $('.api-key-label').text(transport.keyLabel || 'API Key');
            $('.api-region-label').text(transport.regionLabel || 'Data Residency');

            // Identity: the non-secret half of a credential pair (AWS Access Key ID)
            const identityManaged = transport.identitySource === 'constant' || transport.identitySource === 'environment';

            this.providerApiIdentityField
                .attr('placeholder', transport.identityPlaceholder || '')
                .prop('disabled', identityManaged)
                .val(identityManaged ? '' : ($selectedOption.data('username') || ''));

            $('.api-identity-label').text(transport.identityLabel || '');

            if (identityManaged) {
                $('.api-identity-hint').text(
                    'Using the ' + transport.identityConstant +
                    (transport.identitySource === 'constant' ? ' constant from wp-config.php.' : ' environment variable.')
                );
            } else {
                $('.api-identity-hint').text(
                    transport.identityConstant
                        ? 'You can also define ' + transport.identityConstant + ' in wp-config.php instead.'
                        : ''
                );
            }

            // Region / data residency choices
            const regions = transport.regions || {};
            const values = Object.keys(regions);
            const $endpoint = this.providerApiEndpointField;

            $endpoint.empty();
            values.forEach(function(value) {
                $endpoint.append($('<option></option>').attr('value', value).text(regions[value]));
            });

            const saved = $selectedOption.data('api-endpoint');
            $endpoint.val(regions[saved] ? saved : (transport.defaultBase || values[0]));

            // Key field: placeholder, lock state, and where the key comes from
            const managed = transport.keySource === 'constant' || transport.keySource === 'environment';
            const constantName = transport.envConstant || '';

            this.providerApiKeyField
                .attr('placeholder', managed ? '' : (transport.keyPlaceholder || ''))
                .prop('disabled', managed);

            let hint;
            if (transport.keySource === 'constant') {
                hint = 'Using the ' + constantName + ' constant from wp-config.php. Remove it to manage the key here.';
            } else if (transport.keySource === 'environment') {
                hint = 'Using the ' + constantName + ' environment variable. Unset it to manage the key here.';
            } else if (String($selectedOption.data('has-api-key')) === '1') {
                hint = 'A key is saved (encrypted). Leave blank to keep it, or paste a new one to replace it.';
            } else {
                hint = 'Stored encrypted. You can also define ' + constantName + ' in wp-config.php instead.';
            }

            $('.api-key-hint').text(hint);
            $('.api-sender-hint').text(transport.senderHint || '');
        },

        /**
         * Validate the API key against the provider without saving it
         */
        testApiKey: function() {
            const self = this;

            if (!this.requireTransport()) {
                return;
            }

            const $button = $('#test-api-key-btn');

            $button.prop('disabled', true).addClass('loading');
            $button.data('original-text', $button.text()).text('Testing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_check_provider_api_key',
                    nonce: $('#intellisend_providers_nonce').val(),
                    provider_name: this.providerSelector.val(),
                    api_key: this.providerApiKeyField.val().trim()
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', response.data.message);
                    } else {
                        self.showNotification('error', (response.data && response.data.message) || 'API key check failed');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                    $button.text($button.data('original-text'));
                }
            });
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
            
            const isApi = this.isApiProvider();

            // Get form data
            const formData = {
                action: 'intellisend_save_provider',
                nonce: $('#intellisend_providers_nonce').val(),
                provider_id: this.providerIdField.val(),
                provider_name: this.providerSelector.val(),
                provider_type: this.providerTypeField.val(),
                provider_sender: this.providerSenderField.val(),
                is_default: this.isDefaultField.val()
            };

            if (isApi) {
                formData.provider_api_endpoint = this.providerApiEndpointField.val();
                formData.provider_api_identity = this.providerApiIdentityField.val();

                // Only send the key when a new one was typed, so a blank field
                // keeps whatever is already stored.
                const apiKey = this.providerApiKeyField.val().trim();
                if (apiKey) {
                    formData.provider_api_key = apiKey;
                }
            } else {
                formData.provider_server = this.providerServerField.val();
                formData.provider_port = this.providerPortField.val();
                formData.provider_username = this.providerUsernameField.val();

                // Only include password if it's not empty
                // This prevents clearing saved passwords when nothing is entered
                const password = this.providerPasswordField.val().trim();
                if (password) {
                    formData.provider_password = password;
                }
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
                        $option.data('sender', formData.provider_sender);

                        if (isApi) {
                            $option.data('api-endpoint', formData.provider_api_endpoint);
                            $option.data('username', formData.provider_api_identity || '');
                            if (formData.provider_api_key) {
                                $option.data('has-api-key', '1');
                            }
                        } else {
                            $option.data('username', formData.provider_username);
                            $option.data('server', formData.provider_server);
                            $option.data('port', formData.provider_port);
                        }

                        // Update the option text using the label the server reports
                        const label = (response.data && response.data.label) || formData.provider_name;
                        const isConfigured = !response.data || response.data.configured !== 0;
                        $option.text(isConfigured ? label + ' (Configured)' : label).data('configured', isConfigured ? '1' : '0');

                        // Clear the description since it's now configured
                        if (isConfigured) {
                            $option.removeData('description');
                            $option.removeData('help-link');
                            $('#provider-description').fadeOut(200);
                        }

                        // Clear the secret box so the saved value is never echoed back
                        self.providerApiKeyField.val('');
                    self.providerPasswordField.val('');
                        
                        // If this was the first provider configured, show additional message
                        if (response.data.message.includes('Default routing rule')) {
                            self.showNotification('info', 'This provider has been set as the default for all email routing.');
                        }
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
         * Send a test email through the provider currently selected.
         *
         * Works for SMTP presets and API transports alike: the server decides
         * which path to take from the provider's stored transport type.
         */
        sendTestEmail: function() {
            const self = this;
            const $button = $('#send-provider-test-btn');
            const recipient = this.testRecipientField.val().trim();

            $('.field-error').remove();
            $('.has-error').removeClass('has-error').removeAttr('aria-invalid');

            if (!recipient || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) {
                this.showFieldError(this.testRecipientField, 'Enter a valid recipient email');
                return;
            }

            // Credentials are read from the database, so unsaved edits are not tested.
            const $option = this.providerSelector.find('option:selected');
            if (String($option.data('configured')) !== '1') {
                this.showNotification('error', 'Save this provider before sending a test.');
                return;
            }

            $button.prop('disabled', true).addClass('loading');
            $button.data('original-text', $button.text()).text('Sending...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_send_test_email',
                    nonce: $('#intellisend_providers_nonce').val(),
                    provider_id: this.providerSelector.val(),
                    test_email: recipient
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', (response.data && response.data.message) || 'Test email sent.');
                    } else {
                        self.showNotification('error', (response.data && response.data.message) || 'Failed to send the test email.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred');
                },
                complete: function() {
                    $button.prop('disabled', false).removeClass('loading');
                    $button.text($button.data('original-text'));
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
            $('.has-error').removeClass('has-error').removeAttr('aria-invalid');
            
            let isValid = true;

            // API transports validate the key and sender instead of SMTP credentials
            if (this.isApiProvider()) {
                const sender = this.providerSenderField.val().trim();

                if (!sender) {
                    this.showFieldError(this.providerSenderField, 'Sender Email is required');
                    isValid = false;
                }

                const $option = this.providerSelector.find('option:selected');
                const hasApiKey = String($option.data('has-api-key')) === '1';
                const keyIsManaged = this.providerApiKeyField.prop('disabled');
                const transport = this.currentTransport();

                const keyLabel = (transport && transport.keyLabel) || 'API Key';

                if (!hasApiKey && !keyIsManaged && !this.providerApiKeyField.val().trim()) {
                    const constantName = (transport && transport.envConstant) || '';
                    this.showFieldError(
                        this.providerApiKeyField,
                        constantName
                            ? keyLabel + ' is required, or define ' + constantName + ' in wp-config.php'
                            : keyLabel + ' is required'
                    );
                    isValid = false;
                }

                // Vendors with a credential pair need the identity too
                if (transport && transport.requiresIdentity && !this.providerApiIdentityField.prop('disabled')
                    && !this.providerApiIdentityField.val().trim()) {
                    this.showFieldError(
                        this.providerApiIdentityField,
                        transport.identityLabel + ' is required, or define ' + transport.identityConstant + ' in wp-config.php'
                    );
                    isValid = false;
                }

                return isValid;
            }

            // Validate the host whenever the field is editable for this provider
            const $selected = this.providerSelector.find('option:selected');
            if (String($selected.data('editable-server')) === '1' && !this.providerServerField.val().trim()) {
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
            $field.attr('aria-invalid', 'true').after($('<span class="field-error" role="alert"></span>').text(message));
        },
        
        /**
         * Show notification
         */
        showNotification: function(type, message) {
            // Remove any existing notifications
            $('.intellisend-notification').remove();
            
            const notification = $('<div class="intellisend-notification"></div>').addClass(type).attr('role', type === 'error' ? 'alert' : 'status').text(message);
            
            $('body').append(notification);
            
            // Show notification
            setTimeout(function() {
                notification.addClass('show');
            }, 10);
            
            // Auto-dismiss after 4 seconds
            setTimeout(function() {
                notification.removeClass('show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 4000);
        },
        
        /**
         * Update UI state based on current selection
         */
        updateUIState: function() {
            const selectedProvider = this.providerSelector.val();
            const isApi = this.isApiProvider();

            const transport = this.currentTransport();

            if (isApi) {
                // API transports have no host, port, username or password
                $('.smtp-field').slideUp(300);
                $('.smtp-only-field').slideUp(300);
                $('.api-field').not('.api-region-field').not('.api-identity-field').slideDown(300);

                // A single region is not a choice, so keep that row hidden
                const regionCount = transport ? Object.keys(transport.regions || {}).length : 0;
                $('.api-region-field')[regionCount > 1 ? 'slideDown' : 'slideUp'](300);

                // Only vendors with a credential pair get the identity row
                $('.api-identity-field')[transport && transport.requiresIdentity ? 'slideDown' : 'slideUp'](300);

                $('.api-sender-hint').show();
                return;
            }

            $('.api-field').slideUp(300);
            $('.api-sender-hint').hide();
            $('.smtp-only-field').slideDown(300);

            // The host row is editable for "other" and for presets whose host
            // is region specific, such as Amazon SES.
            const $option = this.providerSelector.find('option:selected');
            if (String($option.data('editable-server')) === '1') {
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
                    padding: 12px 16px;
                    border-radius: 6px;
                    background: #fff;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    transform: translateX(120%);
                    transition: transform 0.3s ease;
                    z-index: 99999;
                    font-size: 14px;
                    max-width: 350px;
                    line-height: 1.4;
                }
                
                .intellisend-notification.show {
                    transform: translateX(0);
                }
                
                .intellisend-notification.success {
                    border-left: 4px solid #46b450;
                    color: #155724;
                }
                
                .intellisend-notification.error {
                    border-left: 4px solid #dc3232;
                    color: #721c24;
                }
                
                .intellisend-notification.info {
                    border-left: 4px solid #00a0d2;
                    color: #0073aa;
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