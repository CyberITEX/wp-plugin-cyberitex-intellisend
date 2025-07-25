/**
 * IntelliSend Routing Page JavaScript
 * 
 * Handles functionality for the email routing rules management page with inline editing.
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
            this.setupValidation();
            this.setupRecipientTags();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            const self = this;
            
            // Add new rule button
            $('#add-rule-btn').on('click', function() {
                self.addNewRuleRow();
            });
            
            // Edit rule button
            $(document).on('click', '.edit-rule', function() {
                const $row = $(this).closest('.rule-row');
                self.enterEditMode($row);
            });
            
            // Save rule button
            $(document).on('click', '.save-rule', function() {
                const $row = $(this).closest('.rule-row');
                if (self.validateRow($row)) {
                    self.saveRule($row);
                }
            });
            
            // Cancel edit button
            $(document).on('click', '.cancel-edit', function() {
                const $row = $(this).closest('.rule-row');
                self.exitEditMode($row, true);
            });
            
            // Save new rule button
            $(document).on('click', '.save-new-rule', function() {
                const $row = $(this).closest('.rule-row');
                if (self.validateRow($row)) {
                    self.saveNewRule($row);
                }
            });
            
            // Cancel new rule button
            $(document).on('click', '.cancel-new-rule', function() {
                const $row = $(this).closest('.rule-row');
                $row.remove();
            });
            
            // Duplicate rule button
            $(document).on('click', '.duplicate-rule', function() {
                const $row = $(this).closest('.rule-row');
                self.duplicateRule($row);
            });
            
            // Delete rule button
            $(document).on('click', '.delete-rule', function() {
                const ruleId = $(this).data('id');
                const ruleName = $(this).data('name');
                self.confirmDeleteRule(ruleId, ruleName);
            });
            
            // Activate rule button
            $(document).on('click', '.activate-rule', function() {
                const ruleId = $(this).data('id');
                self.activateRule(ruleId);
            });
            
            // Deactivate rule button
            $(document).on('click', '.deactivate-rule', function() {
                const ruleId = $(this).data('id');
                self.deactivateRule(ruleId);
            });
            
            // Handle recipient input
            $(document).on('keydown', '.rule-recipients-input', function(e) {
                if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
                    e.preventDefault();
                    const email = $(this).val().trim();
                    if (email) {
                        self.addRecipientTag($(this).closest('.recipients-container'), email);
                        $(this).val('');
                    }
                }
            });
            
            // Handle recipient tag removal
            $(document).on('click', '.remove-recipient', function() {
                const $container = $(this).closest('.recipients-container');
                $(this).parent('.recipient-tag').remove();
                self.updateRecipientInput($container);
            });
        },
        
        /**
         * Set up form validation
         */
        setupValidation: function() {
            // Add email validation regex
            this.emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        },
        
        /**
         * Set up recipient tags functionality
         */
        setupRecipientTags: function() {
            // Initial setup for existing tags
            $('.recipients-container').each(function() {
                const $container = $(this);
                const recipients = $container.find('.rule-recipients').val();
                
                if (recipients) {
                    recipients.split(',').forEach(function(recipient) {
                        const email = recipient.trim();
                        if (email) {
                            IntelliSendRouting.addRecipientTag($container, email);
                        }
                    });
                }
            });
        },
        
        /**
         * Add a new recipient tag
         */
        addRecipientTag: function($container, email) {
            // Validate email
            if (!this.emailRegex.test(email)) {
                this.showError(intellisendData.strings.invalidRecipient);
                return false;
            }
            
            // Check if already exists
            let alreadyExists = false;
            $container.find('.recipient-tag').each(function() {
                if ($(this).text().replace('×', '') === email) {
                    alreadyExists = true;
                    return false;
                }
            });
            
            if (alreadyExists) {
                return false;
            }
            
            // Add tag
            const $tag = $('<span class="recipient-tag">' + email + '<span class="remove-recipient">×</span></span>');
            $container.find('.recipients-tags').append($tag);
            
            // Update hidden input
            this.updateRecipientInput($container);
            
            return true;
        },
        
        /**
         * Update the hidden input with recipient values
         */
        updateRecipientInput: function($container) {
            const recipients = [];
            
            $container.find('.recipient-tag').each(function() {
                recipients.push($(this).text().replace('×', ''));
            });
            
            $container.find('.rule-recipients').val(recipients.join(','));
        },
        
        /**
         * Add a new rule row to the table
         */
        addNewRuleRow: function() {
            // Clone the template
            const template = document.getElementById('new-rule-template');
            const $newRow = $(template.content.cloneNode(true));
            
            // Append to table
            $('#routing-rules-table').append($newRow);
            
            // Show edit inputs by default
            const $row = $('#routing-rules-table').find('.new-rule-row');
            $row.find('.edit-mode').show();
            $row.find('.view-mode').hide();
            
            // Focus on the first input
            $row.find('.rule-name').focus();
        },
        
        /**
         * Duplicate an existing rule
         */
        duplicateRule: function($row) {
            const ruleId = $row.data('id');
            const isDefault = $row.data('is-default');
            
            // Don't allow duplicating default rule
            if (isDefault) {
                this.showError('Default rule cannot be duplicated');
                return;
            }
            
            // Create a new rule row
            this.addNewRuleRow();
            const $newRow = $('#routing-rules-table .new-rule-row');
            
            // Copy values from the source row
            $newRow.find('.rule-name').val($row.find('.rule-name').val() + ' (Copy)');
            $newRow.find('.rule-patterns').val($row.find('.rule-patterns').val());
            $newRow.find('.rule-provider').val($row.find('.rule-provider').val());
            $newRow.find('.rule-priority').val($row.find('.rule-priority').val());
            $newRow.find('.rule-enabled').val($row.find('.rule-enabled').val());
            $newRow.find('.rule-antispam').val($row.find('.rule-antispam').val());
            
            // Copy recipients
            const recipients = $row.find('.rule-recipients').val();
            if (recipients) {
                recipients.split(',').forEach((recipient) => {
                    const email = recipient.trim();
                    if (email) {
                        this.addRecipientTag($newRow.find('.recipients-container'), email);
                    }
                });
            }
        },
        
        /**
         * Enter edit mode for a row
         */
        enterEditMode: function($row) {
            // Show edit inputs
            $row.find('.edit-mode').show();
            $row.find('.view-mode').hide();
            
            // Show save/cancel buttons
            $row.find('.edit-rule').hide();
            $row.find('.duplicate-rule').hide();
            $row.find('.activate-rule, .deactivate-rule').hide();
            $row.find('.delete-rule').hide();
            $row.find('.save-rule, .cancel-edit').show();
            
            // Focus on the name field
            $row.find('.rule-name').focus();
        },
        
        /**
         * Exit edit mode for a row
         */
        exitEditMode: function($row, revertChanges) {
            if (revertChanges) {
                // Revert any changed inputs
                $row.find('.rule-name').val($row.find('.view-mode[data-field="name"]').text());
                $row.find('.rule-patterns').val($row.find('.view-mode[data-field="patterns"]').text());
                $row.find('.rule-provider').val($row.find('.view-mode[data-field="provider"]').text());
                $row.find('.rule-priority').val($row.find('.view-mode[data-field="priority"]').text());
                $row.find('.rule-enabled').val($row.find('.rule-enabled option:contains("' + $row.find('.view-mode[data-field="enabled"] span').text() + '")').val());
                $row.find('.rule-antispam').val($row.find('.rule-antispam option:contains("' + $row.find('.view-mode[data-field="antispam"] span').text() + '")').val());
            } else {
                // Update view mode with the new values
                $row.find('.view-mode[data-field="name"]').text($row.find('.rule-name').val());
                $row.find('.view-mode[data-field="patterns"]').text($row.find('.rule-patterns').val());
                $row.find('.view-mode[data-field="provider"]').text($row.find('.rule-provider option:selected').text());
                $row.find('.view-mode[data-field="priority"]').text($row.find('.rule-priority').val());
                
                // Update status display
                const enabled = $row.find('.rule-enabled').val() === '1';
                const statusClass = enabled ? 'status-active' : 'status-inactive';
                const statusText = enabled ? 'Active' : 'Inactive';
                $row.find('.view-mode[data-field="enabled"] span').attr('class', statusClass).text(statusText);
                
                // Update anti-spam display
                const antispam = $row.find('.rule-antispam').val() === '1';
                const antispamClass = antispam ? 'antispam-active' : 'antispam-inactive';
                const antispamText = antispam ? 'On' : 'Off';
                $row.find('.view-mode[data-field="antispam"] span').attr('class', antispamClass).text(antispamText);
                
                // Update recipients display
                $row.find('.view-mode[data-field="recipients"]').text($row.find('.rule-recipients').val());
            }
            
            // Hide edit inputs
            $row.find('.edit-mode').hide();
            $row.find('.view-mode').show();
            
            // Show action buttons
            $row.find('.edit-rule').show();
            $row.find('.duplicate-rule').show();
            $row.find('.save-rule, .cancel-edit').hide();
            
            // Show appropriate status toggle
            if ($row.find('.rule-enabled').val() === '1') {
                $row.find('.deactivate-rule').show();
                $row.find('.activate-rule').hide();
            } else {
                $row.find('.deactivate-rule').hide();
                $row.find('.activate-rule').show();
            }
            
            // Show delete button except for default rule
            if ($row.data('is-default') !== 1) {
                $row.find('.delete-rule').show();
            }
        },
        
        /**
         * Validate a rule row before saving
         */
        validateRow: function($row) {
            // Clear previous errors
            $row.find('.has-error').removeClass('has-error');
            $('.field-error').remove();
            
            let isValid = true;
            const isDefaultRule = $row.data('is-default') === 1;
            
            // Validate required fields
            if (!$row.find('.rule-name').val().trim()) {
                this.showFieldError($row.find('.rule-name'), intellisendData.strings.requiredField);
                isValid = false;
            }
            
            if (!$row.find('.rule-patterns').val().trim()) {
                this.showFieldError($row.find('.rule-patterns'), intellisendData.strings.requiredField);
                isValid = false;
            }
            
            if (!$row.find('.rule-provider').val()) {
                this.showFieldError($row.find('.rule-provider'), intellisendData.strings.requiredField);
                isValid = false;
            }
            
            // Validate priority (1-100) - skip for default rule
            if (!isDefaultRule) {
                const priority = parseInt($row.find('.rule-priority').val());
                if (isNaN(priority) || priority < 1 || priority > 100) {
                    this.showFieldError($row.find('.rule-priority'), 'Priority must be between 1 and 100');
                    isValid = false;
                }
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
         * Save an existing rule
         */
        saveRule: function($row) {
            const self = this;
            const ruleId = $row.data('id');
            const isDefault = $row.data('is-default') === 1;
            
            // Prepare the data
            const formData = {
                id: ruleId,
                name: $row.find('.rule-name').val().trim(),
                subject_patterns: $row.find('.rule-patterns').val().trim(),
                default_provider_name: $row.find('.rule-provider').val(),
                recipients: $row.find('.rule-recipients').val(),
                enabled: $row.find('.rule-enabled').val(),
                anti_spam_enabled: $row.find('.rule-antispam').val()
            };
            
            // Add priority if not default rule
            if (!isDefault) {
                formData.priority = $row.find('.rule-priority').val();
            }
            
            // Show loading
            this.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: intellisendData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'intellisend_update_routing_rule',
                    nonce: intellisendData.nonce,
                    formData: formData
                },
                success: function(response) {
                    if (response.success) {
                        self.exitEditMode($row, false);
                        self.showSuccess(response.data || intellisendData.strings.saveSuccess);
                    } else {
                        const errorMsg = response.data || intellisendData.strings.saveFailed;
                        self.showError(errorMsg);
                        console.error('Save Error:', response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    self.showError('A network error occurred: ' + (error || 'undefined'));
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Save a new rule
         */
        saveNewRule: function($row) {
            const self = this;
            
            // Prepare the data
            const formData = {
                name: $row.find('.rule-name').val().trim(),
                subject_patterns: $row.find('.rule-patterns').val().trim(),
                default_provider_name: $row.find('.rule-provider').val(),
                recipients: $row.find('.rule-recipients').val(),
                priority: $row.find('.rule-priority').val(),
                enabled: $row.find('.rule-enabled').val(),
                anti_spam_enabled: $row.find('.rule-antispam').val()
            };
            
            // Show loading
            this.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: intellisendData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'intellisend_add_routing_rule',
                    nonce: intellisendData.nonce,
                    formData: formData
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data || intellisendData.strings.saveSuccess);
                        // Reload page to show new rule with proper ID
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        const errorMsg = response.data || intellisendData.strings.saveFailed;
                        self.showError(errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    self.showError('A network error occurred: ' + error);
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Confirm rule deletion
         */
        confirmDeleteRule: function(ruleId, ruleName) {
            if (confirm(intellisendData.strings.confirmDelete.replace('{name}', ruleName))) {
                this.deleteRule(ruleId);
            }
        },
        
        /**
         * Delete a rule
         */
        deleteRule: function(ruleId) {
            const self = this;
            
            // Show loading
            this.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: intellisendData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_routing_rule',
                    nonce: intellisendData.nonce,
                    id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the row
                        $('.rule-row[data-id="' + ruleId + '"]').fadeOut(300, function() {
                            $(this).remove();
                        });
                        self.showSuccess('Rule deleted successfully.');
                    } else {
                        self.showError(response.data || 'Failed to delete rule.');
                    }
                },
                error: function() {
                    self.showError('A network error occurred. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Activate a rule
         */
        activateRule: function(ruleId) {
            const self = this;
            const $row = $('.rule-row[data-id="' + ruleId + '"]');
            
            // Show loading
            this.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: intellisendData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'intellisend_activate_routing_rule',
                    nonce: intellisendData.nonce,
                    id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        // Update status in view
                        $row.find('.view-mode[data-field="enabled"] span')
                            .removeClass('status-inactive')
                            .addClass('status-active')
                            .text('Active');
                        
                        // Update form value
                        $row.find('.rule-enabled').val('1');
                        
                        // Update buttons
                        $row.find('.activate-rule').hide();
                        $row.find('.deactivate-rule').show();
                        
                        self.showSuccess('Rule activated successfully.');
                    } else {
                        self.showError(response.data || 'Failed to activate rule.');
                    }
                },
                error: function() {
                    self.showError('A network error occurred. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Deactivate a rule
         */
        deactivateRule: function(ruleId) {
            const self = this;
            const $row = $('.rule-row[data-id="' + ruleId + '"]');
            
            // Show loading
            this.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: intellisendData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'intellisend_deactivate_routing_rule',
                    nonce: intellisendData.nonce,
                    id: ruleId
                },
                success: function(response) {
                    if (response.success) {
                        // Update status in view
                        $row.find('.view-mode[data-field="enabled"] span')
                            .removeClass('status-active')
                            .addClass('status-inactive')
                            .text('Inactive');
                        
                        // Update form value
                        $row.find('.rule-enabled').val('0');
                        
                        // Update buttons
                        $row.find('.deactivate-rule').hide();
                        $row.find('.activate-rule').show();
                        
                        self.showSuccess('Rule deactivated successfully.');
                    } else {
                        self.showError(response.data || 'Failed to deactivate rule.');
                    }
                },
                error: function() {
                    self.showError('A network error occurred. Please try again.');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },
        
        /**
         * Show a success notification
         */
        showSuccess: function(message) {
            this.showNotification('success', message);
        },
        
        /**
         * Show an error notification
         */
        showError: function(message) {
            this.showNotification('error', message);
        },
        
        /**
         * Show a notification
         */
        showNotification: function(type, message) {
            // Remove any existing notifications
            $('.intellisend-notice').remove();
            
            const icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            
            const $notice = $(`
                <div class="intellisend-notice ${type}">
                    <span class="intellisend-notice-icon dashicons ${icon}"></span>
                    <div class="intellisend-notice-content">${message}</div>
                </div>
            `);
            
            $('.intellisend-admin h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Show loading overlay
         */
        showLoading: function() {
            const template = document.getElementById('loading-overlay-template');
            const $loading = $(template.content.cloneNode(true));
            
            // Append to admin content
            $('.intellisend-admin-content').append($loading);
        },
        
        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('.intellisend-loading-overlay').fadeOut(200, function() {
                $(this).remove();
            });
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendRouting.init();
    });

})(jQuery);