/**
 * IntelliSend Routing Page JavaScript
 * 
 * Handles functionality for the email routing rules management page.
 */

(function($) {
    'use strict';

    // Main IntelliSend Routing object
    const IntelliSendRouting = {
        /**
         * Initialize all components
         */
        init: function() {
            this.setupEventListeners();
            this.setupFormValidation();
            this.setupStatusBadges();
            this.injectCustomStyles();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            const self = this;
            
            // Add rule button
            $('#add-rule-btn').on('click', function() {
                self.showAddRuleModal();
            });
            
            // Edit rule button
            $('.edit-rule').on('click', function() {
                const ruleId = $(this).data('id');
                self.showEditRuleModal(ruleId);
            });
            
            // Delete rule button
            $('.delete-rule').on('click', function() {
                const ruleId = $(this).data('id');
                const ruleName = $(this).data('name');
                self.confirmDeleteRule(ruleId, ruleName);
            });
            
            // Activate rule button
            $('.activate-rule').on('click', function() {
                const ruleId = $(this).data('id');
                self.activateRule(ruleId);
            });
            
            // Deactivate rule button
            $('.deactivate-rule').on('click', function() {
                const ruleId = $(this).data('id');
                self.deactivateRule(ruleId);
            });
            
            // Close modal buttons
            $('.intellisend-modal-close').on('click', function() {
                self.closeModal($(this).closest('.intellisend-modal'));
            });
            
            // Click outside modal to close
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('intellisend-modal')) {
                    self.closeModal($(event.target));
                }
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.intellisend-modal:visible').each(function() {
                        self.closeModal($(this));
                    });
                }
            });
            
            // Add rule form submission
            $('#add-rule-form').on('submit', function(e) {
                e.preventDefault();
                if (self.validateForm($(this))) {
                    self.addRule($(this));
                }
            });
            
            // Edit rule form submission
            $('#edit-rule-form').on('submit', function(e) {
                e.preventDefault();
                if (self.validateForm($(this))) {
                    self.updateRule($(this));
                }
            });
            
            // Cancel button in forms
            $('.cancel-form').on('click', function(e) {
                e.preventDefault();
                self.closeModal($(this).closest('.intellisend-modal'));
            });
        },
        
        /**
         * Set up form validation
         */
        setupFormValidation: function() {
            // Add required attribute to required fields
            $('.form-field.required input, .form-field.required select, .form-field.required textarea').attr('required', true);
            
            // Add validation for number inputs
            $('input[type="number"]').on('input', function() {
                const min = parseInt($(this).attr('min'));
                const max = parseInt($(this).attr('max'));
                let value = parseInt($(this).val());
                
                if (!isNaN(min) && value < min) {
                    $(this).val(min);
                }
                
                if (!isNaN(max) && value > max) {
                    $(this).val(max);
                }
            });
        },
        
        /**
         * Set up status badges with appropriate colors
         */
        setupStatusBadges: function() {
            // Already handled by CSS classes
        },
        
        /**
         * Show add rule modal
         */
        showAddRuleModal: function() {
            // Reset form
            $('#add-rule-form')[0].reset();
            
            // Clear validation errors
            this.clearValidationErrors($('#add-rule-form'));
            
            // Show modal
            $('#add-rule-modal').show();
            
            // Focus first field
            $('#add-rule-form input:first').focus();
        },
        
        /**
         * Show edit rule modal
         */
        showEditRuleModal: function(ruleId) {
            const self = this;
            
            // Show loading state
            $('#edit-rule-modal').addClass('loading');
            $('#edit-rule-modal').show();
            
            // Get rule data via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_get_routing_rule',
                    id: ruleId,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.populateEditForm(response.data);
                    } else {
                        self.showNotification('error', response.data || 'Failed to load routing rule details.');
                        self.closeModal($('#edit-rule-modal'));
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred while loading the routing rule.');
                    self.closeModal($('#edit-rule-modal'));
                },
                complete: function() {
                    $('#edit-rule-modal').removeClass('loading');
                }
            });
        },
        
        /**
         * Populate edit form with rule data
         */
        populateEditForm: function(rule) {
            // Set form values
            $('#edit-rule-id').val(rule.id);
            $('#edit-rule-name').val(rule.name);
            $('#edit-rule-provider').val(rule.defaultProviderName);
            $('#edit-rule-patterns').val(rule.subjectPatterns);
            $('#edit-rule-priority').val(rule.priority);
            $('#edit-rule-enabled').prop('checked', rule.enabled);
            
            // Check if the provider is still configured
            if (!rule.providerConfigured) {
                // Show warning about unconfigured provider
                const warningHtml = `
                    <div class="form-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <p>${intellisendData.strings.unconfiguredProvider.replace('%s', rule.defaultProviderName)}</p>
                    </div>
                `;
                $('#edit-rule-provider').after(warningHtml);
                $('#edit-rule-provider').addClass('has-warning');
            }
            
            // Focus first field
            $('#edit-rule-name').focus();
        },
        
        /**
         * Confirm delete rule
         */
        confirmDeleteRule: function(ruleId, ruleName) {
            const self = this;
            
            // Create confirmation dialog
            const confirmMessage = `Are you sure you want to delete the routing rule "${ruleName}"? This action cannot be undone.`;
            
            if (confirm(confirmMessage)) {
                self.deleteRule(ruleId);
            }
        },
        
        /**
         * Add new rule
         */
        addRule: function($form) {
            const self = this;
            const $submitButton = $form.find('button[type="submit"]');
            
            // Set loading state
            this.setButtonLoading($submitButton, 'Adding...');
            
            // Get form data
            const formData = $form.serialize();
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_add_routing_rule',
                    formData: formData,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Routing rule added successfully.');
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data || 'Failed to add routing rule.');
                        self.resetButtonLoading($submitButton, 'Add Rule');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred. Please try again.');
                    self.resetButtonLoading($submitButton, 'Add Rule');
                }
            });
        },
        
        /**
         * Update existing rule
         */
        updateRule: function($form) {
            const self = this;
            const $submitButton = $form.find('button[type="submit"]');
            
            // Set loading state
            this.setButtonLoading($submitButton, 'Updating...');
            
            // Get form data
            const formData = $form.serialize();
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_update_routing_rule',
                    formData: formData,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Routing rule updated successfully.');
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data || 'Failed to update routing rule.');
                        self.resetButtonLoading($submitButton, 'Update Rule');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred. Please try again.');
                    self.resetButtonLoading($submitButton, 'Update Rule');
                }
            });
        },
        
        /**
         * Delete rule
         */
        deleteRule: function(ruleId) {
            const self = this;
            
            // Show loading notification
            this.showNotification('warning', 'Deleting routing rule...');
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_routing_rule',
                    id: ruleId,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Routing rule deleted successfully.');
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data || 'Failed to delete routing rule.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred. Please try again.');
                }
            });
        },
        
        /**
         * Activate rule
         */
        activateRule: function(ruleId) {
            const self = this;
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_activate_routing_rule',
                    id: ruleId,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Routing rule activated successfully.');
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data || 'Failed to activate routing rule.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred. Please try again.');
                }
            });
        },
        
        /**
         * Deactivate rule
         */
        deactivateRule: function(ruleId) {
            const self = this;
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_deactivate_routing_rule',
                    id: ruleId,
                    nonce: intellisendData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotification('success', 'Routing rule deactivated successfully.');
                        
                        // Reload page after a short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data || 'Failed to deactivate routing rule.');
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred. Please try again.');
                }
            });
        },
        
        /**
         * Validate form
         */
        validateForm: function($form) {
            this.clearValidationErrors($form);
            
            let isValid = true;
            
            // Check required fields
            $form.find('[required]').each(function() {
                if (!$(this).val().trim()) {
                    const $field = $(this);
                    const fieldName = $field.prev('label').text() || 'This field';
                    
                    IntelliSendRouting.showFieldError($field, `${fieldName} is required.`);
                    isValid = false;
                }
            });
            
            // Focus first error field
            if (!isValid) {
                $form.find('.has-error').first().focus();
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
         * Clear validation errors
         */
        clearValidationErrors: function($form) {
            $form.find('.has-error').removeClass('has-error');
            $form.find('.field-error').remove();
        },
        
        /**
         * Close modal
         */
        closeModal: function($modal) {
            $modal.hide();
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
            $button.text(originalText || $button.data('original-text'));
        },
        
        /**
         * Show notification
         */
        showNotification: function(type, message) {
            // Remove any existing notifications
            $('.intellisend-notice').remove();
            
            const icon = type === 'success' ? 'dashicons-yes-alt' : 
                         type === 'warning' ? 'dashicons-warning' : 'dashicons-dismiss';
            
            const noticeHtml = `
                <div class="intellisend-notice ${type}">
                    <span class="intellisend-notice-icon dashicons ${icon}"></span>
                    <div class="intellisend-notice-content">${message}</div>
                </div>
            `;
            
            $('.intellisend-admin h1').after(noticeHtml);
            
            // Auto dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $('.intellisend-notice').fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        },
        
        /**
         * Inject custom styles for dynamic elements
         */
        injectCustomStyles: function() {
            const customStyles = `
                /* Loading overlay for modal */
                .intellisend-modal.loading .intellisend-modal-body {
                    position: relative;
                    min-height: 200px;
                }
                
                .intellisend-modal.loading .intellisend-modal-body:after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .intellisend-modal.loading .intellisend-modal-body:before {
                    content: '';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    margin: -20px 0 0 -20px;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    border: 3px solid rgba(0, 0, 0, 0.1);
                    border-top-color: #2271b1;
                    z-index: 1;
                    animation: spin 0.8s linear infinite;
                }
                
                /* Button loading state */
                .button.is-loading {
                    position: relative;
                    color: transparent !important;
                }
                
                .button.is-loading .loading-spinner {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    margin-top: -7px;
                    margin-left: -7px;
                }
                
                /* Tooltip styles */
                .intellisend-tooltip {
                    position: relative;
                    display: inline-block;
                    cursor: help;
                }
                
                .intellisend-tooltip .tooltip-text {
                    visibility: hidden;
                    width: 200px;
                    background-color: #333;
                    color: #fff;
                    text-align: center;
                    border-radius: 4px;
                    padding: 8px;
                    position: absolute;
                    z-index: 1;
                    bottom: 125%;
                    left: 50%;
                    margin-left: -100px;
                    opacity: 0;
                    transition: opacity 0.3s;
                    font-size: 12px;
                    line-height: 1.4;
                    pointer-events: none;
                }
                
                .intellisend-tooltip .tooltip-text::after {
                    content: "";
                    position: absolute;
                    top: 100%;
                    left: 50%;
                    margin-left: -5px;
                    border-width: 5px;
                    border-style: solid;
                    border-color: #333 transparent transparent transparent;
                }
                
                .intellisend-tooltip:hover .tooltip-text {
                    visibility: visible;
                    opacity: 1;
                }
                
                /* Dark mode adjustments */
                @media (prefers-color-scheme: dark) {
                    .admin-color-modern .intellisend-modal.loading .intellisend-modal-body:after {
                        background: rgba(30, 30, 30, 0.7);
                    }
                    
                    .admin-color-modern .intellisend-modal.loading .intellisend-modal-body:before {
                        border-color: rgba(255, 255, 255, 0.1);
                        border-top-color: #2271b1;
                    }
                }
            `;
            
            $('<style id="intellisend-routing-dynamic-styles"></style>')
                .text(customStyles)
                .appendTo('head');
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendRouting.init();
    });

})(jQuery);
