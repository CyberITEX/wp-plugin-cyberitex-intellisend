// admin/js/routing-page.js

/**
 * IntelliSend Routing Page JavaScript
 * Handles all routing rule management functionality
 */
(function($) {
    'use strict';

    /**
     * Routing Page Manager
     */
    const RoutingManager = {
        
        /**
         * Initialize the routing manager
         */
        init() {
            this.bindEvents();
            this.setupRecipientTags();
            console.log('IntelliSend Routing Manager initialized');
        },

        /**
         * Bind all event handlers
         */
        bindEvents() {
            // Add new rule button - using the actual ID from your HTML
            $(document).on('click', '#add-rule-btn', this.handleAddNewRule.bind(this));
            
            // Rule management events - using the actual classes from your HTML
            $(document).on('click', '.edit-rule', this.handleEditRule.bind(this));
            $(document).on('click', '.save-rule', this.handleSaveRule.bind(this));
            $(document).on('click', '.cancel-edit', this.handleCancelEdit.bind(this));
            $(document).on('click', '.save-new-rule', this.handleSaveNewRule.bind(this));
            $(document).on('click', '.cancel-new-rule', this.handleCancelNewRule.bind(this));
            $(document).on('click', '.delete-rule', this.handleDeleteRule.bind(this));
            $(document).on('click', '.activate-rule', this.handleActivateRule.bind(this));
            $(document).on('click', '.deactivate-rule', this.handleDeactivateRule.bind(this));
            $(document).on('click', '.duplicate-rule', this.handleDuplicateRule.bind(this));

            // Form validation events
            $(document).on('input', '.rule-name, .rule-patterns, .rule-priority', this.handleFieldValidation.bind(this));
            $(document).on('change', '.rule-provider, .rule-pattern-type', this.handleProviderChange.bind(this));
            
            // Recipient tag events
            $(document).on('keypress', '.rule-recipients-input', this.handleRecipientInput.bind(this));
            $(document).on('click', '.remove-recipient', this.handleRemoveRecipient.bind(this));
            
            // Pattern type change event
            $(document).on('change', '.rule-pattern-type', this.handlePatternTypeChange.bind(this));
        },

        /**
         * Handle pattern type change
         */
        handlePatternTypeChange(e) {
            const $select = $(e.target);
            const $row = $select.closest('.rule-row');
            const patternType = $select.val().toLowerCase(); // Make case-insensitive
            
            // Update the placeholder text for patterns based on pattern type
            const $patternsField = $row.find('.rule-patterns');
            let placeholder = '';
            
            switch (patternType) {
                case 'wildcard':
                    placeholder = 'e.g. newsletter*, *urgent*, order*confirmation';
                    break;
                case 'starts_with':
                    placeholder = 'e.g. newsletter, urgent, order';
                    break;
                case 'contains':
                    placeholder = 'e.g. urgent, newsletter, confirmation';
                    break;
                case 'ends_with':
                    placeholder = 'e.g. confirmation, receipt, notification';
                    break;
                case 'regex':
                    placeholder = 'e.g. ^newsletter.*$, \\b(urgent|important)\\b';
                    break;
                default:
                    placeholder = 'e.g. newsletter*, *urgent*, order*';
            }
            
            $patternsField.attr('placeholder', placeholder);
            
            // Clear validation errors
            this.clearFieldError($select);
        },

        /**
         * Handle add new rule button click
         */
        handleAddNewRule(e) {
            e.preventDefault();
            console.log('Add new rule clicked');
            this.addNewRuleRow();
        },

        /**
         * Handle edit rule button click
         */
        handleEditRule(e) {
            e.preventDefault();
            console.log('Edit rule clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            this.enterEditMode($row);
        },

        /**
         * Handle save rule button click
         */
        handleSaveRule(e) {
            e.preventDefault();
            console.log('Save rule clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            
            if (this.validateRule($row)) {
                this.saveExistingRule($row);
            }
        },

        /**
         * Handle cancel edit button click
         */
        handleCancelEdit(e) {
            e.preventDefault();
            console.log('Cancel edit clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            this.exitEditMode($row, true);
        },

        /**
         * Handle save new rule button click
         */
        handleSaveNewRule(e) {
            e.preventDefault();
            console.log('Save new rule clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            
            if (this.validateRule($row)) {
                this.saveNewRule($row);
            }
        },

        /**
         * Handle cancel new rule button click
         */
        handleCancelNewRule(e) {
            e.preventDefault();
            console.log('Cancel new rule clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            $row.remove();
        },

        /**
         * Handle delete rule button click
         */
        handleDeleteRule(e) {
            e.preventDefault();
            console.log('Delete rule clicked');
            const $button = $(e.currentTarget);
            const ruleId = $button.data('id');
            const ruleName = $button.data('name');
            
            this.confirmDeleteRule(ruleId, ruleName);
        },

        /**
         * Handle activate rule button click
         */
        handleActivateRule(e) {
            e.preventDefault();
            console.log('Activate rule clicked');
            const ruleId = $(e.currentTarget).data('id');
            this.toggleRuleStatus(ruleId, true);
        },

        /**
         * Handle deactivate rule button click
         */
        handleDeactivateRule(e) {
            e.preventDefault();
            console.log('Deactivate rule clicked');
            const ruleId = $(e.currentTarget).data('id');
            this.toggleRuleStatus(ruleId, false);
        },

        /**
         * Handle duplicate rule button click
         */
        handleDuplicateRule(e) {
            e.preventDefault();
            console.log('Duplicate rule clicked');
            const $row = $(e.currentTarget).closest('.rule-row');
            this.duplicateRule($row);
        },

        /**
         * Handle field validation on input
         */
        handleFieldValidation(e) {
            const $field = $(e.target);
            this.clearFieldError($field);
        },

        /**
         * Handle provider change
         */
        handleProviderChange(e) {
            const $field = $(e.target);
            this.clearFieldError($field);
        },

        /**
         * Handle recipient input
         */
        handleRecipientInput(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                const $input = $(e.target);
                const email = $input.val().trim();
                
                if (email && this.isValidEmail(email)) {
                    const $container = $input.closest('.recipients-container');
                    this.addRecipientTag($container, email);
                    $input.val('');
                }
            }
        },

        /**
         * Handle remove recipient
         */
        handleRemoveRecipient(e) {
            e.preventDefault();
            const $tag = $(e.target).closest('.recipient-tag');
            const $container = $tag.closest('.recipients-container');
            $tag.remove();
            this.updateRecipientInput($container);
        },

        /**
         * Add a new rule row to the table
         */
        addNewRuleRow() {
            console.log('Adding new rule row');
            
            // Check if there's already a new rule being added
            if ($('#routing-rules-table .new-rule-row').length > 0) {
                this.showError('Please finish adding the current rule before adding another.');
                return;
            }

            // Get the template and clone it
            const template = document.getElementById('new-rule-template');
            if (!template) {
                console.error('New rule template not found');
                this.showError('Unable to add new rule. Template not found.');
                return;
            }

            const $newRow = $(template.content.cloneNode(true));
            
            // Add to table
            $('#routing-rules-table tbody').append($newRow);
            
            // Get the actual row that was added
            const $addedRow = $('#routing-rules-table .new-rule-row').last();
            
            // Show edit mode immediately
            this.enterEditMode($addedRow);
            
            console.log('New rule row added and in edit mode');
        },

        /**
         * Enter edit mode for a rule row
         */
        enterEditMode($row) {
            console.log('Entering edit mode for row:', $row);
            
            // Check if another row is already in edit mode
            const $editingRow = $('#routing-rules-table .rule-row').filter(function() {
                return $(this).find('.edit-mode').is(':visible');
            });
            
            if ($editingRow.length > 0 && !$editingRow.is($row)) {
                this.showError('Please finish editing the current rule before editing another.');
                return;
            }

            // Store original values for cancel functionality
            this.storeOriginalValues($row);
            
            // Show edit mode elements and hide view mode
            $row.find('.edit-mode').show();
            $row.find('.view-mode').hide();
            
            // Handle action buttons for edit mode
            $row.find('.edit-rule').hide();
            $row.find('.duplicate-rule').hide();
            $row.find('.activate-rule, .deactivate-rule').hide();
            $row.find('.delete-rule').hide();
            
            // Show save and cancel buttons (they exist for both default and regular rules)
            $row.find('.save-rule, .cancel-edit').show();
            
            // Focus on the first editable input
            setTimeout(() => {
                const $firstInput = $row.find('.rule-name');
                if (!$firstInput.prop('readonly') && !$firstInput.prop('disabled')) {
                    $firstInput.focus();
                } else {
                    // If name field is readonly (for default rule), focus on provider
                    $row.find('.rule-provider').focus();
                }
            }, 100);
            
            console.log('Edit mode activated');
        },

        /**
         * Exit edit mode for a rule row
         */
        exitEditMode($row, cancelled = false) {
            console.log('Exiting edit mode, cancelled:', cancelled);
            
            if (cancelled) {
                if ($row.hasClass('new-rule-row')) {
                    // Remove new rule row if cancelled
                    $row.remove();
                    return;
                }
                
                // Restore original values
                this.restoreOriginalValues($row);
            } else {
                // Update display values from form inputs
                this.updateDisplayValues($row);
            }
            
            // Clear validation errors
            this.clearAllValidationErrors($row);
            
            // Show view mode elements and hide edit mode
            $row.find('.edit-mode').hide();
            $row.find('.view-mode').show();
            
            // Hide save and cancel buttons
            $row.find('.save-rule, .cancel-edit').hide();
            
            // Show appropriate action buttons
            $row.find('.edit-rule').show();
            
            // Only show certain buttons for non-default rules
            if (!this.isDefaultRule($row)) {
                $row.find('.duplicate-rule').show();
                $row.find('.delete-rule').show();
                
                // Show appropriate status toggle
                if ($row.find('.rule-enabled').val() === '1') {
                    $row.find('.deactivate-rule').show();
                    $row.find('.activate-rule').hide();
                } else {
                    $row.find('.activate-rule').show();
                    $row.find('.deactivate-rule').hide();
                }
            } else {
                // For default rule, still show activate/deactivate
                if ($row.find('.rule-enabled').val() === '1') {
                    $row.find('.deactivate-rule').show();
                    $row.find('.activate-rule').hide();
                } else {
                    $row.find('.activate-rule').show();
                    $row.find('.deactivate-rule').hide();
                }
            }
            
            // Remove new-rule-row class if it exists
            $row.removeClass('new-rule-row');
        },

        /**
         * Store original values for cancel functionality
         */
        storeOriginalValues($row) {
            const originalValues = {
                name: $row.find('.rule-name').val() || '',
                patterns: $row.find('.rule-patterns').val() || '',
                patternType: $row.find('.rule-pattern-type').val() || 'wildcard',
                provider: $row.find('.rule-provider').val() || '',
                recipients: $row.find('.rule-recipients').val() || '',
                enabled: $row.find('.rule-enabled').val() || '1',
                antispam: $row.find('.rule-antispam').val() || '1',
                priority: $row.find('.rule-priority').val() || '10'
            };
            
            $row.data('original-values', originalValues);
        },

        /**
         * Restore original values
         */
        restoreOriginalValues($row) {
            const original = $row.data('original-values');
            if (!original) return;

            $row.find('.rule-name').val(original.name);
            $row.find('.rule-patterns').val(original.patterns);
            $row.find('.rule-pattern-type').val(original.patternType);
            $row.find('.rule-provider').val(original.provider);
            $row.find('.rule-recipients').val(original.recipients);
            $row.find('.rule-enabled').val(original.enabled);
            $row.find('.rule-antispam').val(original.antispam);
            $row.find('.rule-priority').val(original.priority);
        },

        /**
         * Update display values from form inputs
         */
        updateDisplayValues($row) {
            // Update text displays
            $row.find('.rule-name-display').text($row.find('.rule-name').val().trim());
            $row.find('.rule-patterns-display').text($row.find('.rule-patterns').val().trim());
            $row.find('.rule-provider-display').text($row.find('.rule-provider option:selected').text());
            $row.find('.rule-recipients-display').text($row.find('.rule-recipients').val().trim());
            
            // Update pattern type display
            const patternTypeValue = $row.find('.rule-pattern-type').val();
            const patternTypeText = $row.find('.rule-pattern-type option:selected').text();
            $row.find('[data-field="pattern_type"] .view-mode').text(patternTypeText);
            
            // Update status displays
            this.updateStatusDisplay($row, '.rule-enabled', '.rule-enabled-display');
            this.updateStatusDisplay($row, '.rule-antispam', '.rule-antispam-display');
            
            // Update priority (only for non-default rules)
            if (!this.isDefaultRule($row)) {
                $row.find('.rule-priority-display').text($row.find('.rule-priority').val());
            }
        },

        /**
         * Update status display helper
         */
        updateStatusDisplay($row, inputSelector, displaySelector) {
            const enabled = $row.find(inputSelector).val() === '1';
            const $display = $row.find(displaySelector);
            
            $display
                .text(enabled ? 'Enabled' : 'Disabled')
                .removeClass('status-enabled status-disabled')
                .addClass(enabled ? 'status-enabled' : 'status-disabled')
                .data('value', enabled ? '1' : '0');
        },

        /**
         * Validate a rule's data
         */
        validateRule($row) {
            let isValid = true;
            this.clearAllValidationErrors($row);

            console.log('Validating rule...');

            // Validate rule name (always required)
            if (!this.validateField($row.find('.rule-name'), 'Rule name is required')) {
                isValid = false;
            }

            // Validate provider (always required)
            if (!this.validateField($row.find('.rule-provider'), 'Provider is required')) {
                isValid = false;
            }

            // For default rules, patterns and priority may be handled differently
            if (!this.isDefaultRule($row)) {
                // Regular rules need patterns
                if (!this.validateField($row.find('.rule-patterns'), 'At least one pattern is required')) {
                    isValid = false;
                } else {
                    // Validate pattern format based on pattern type
                    const patternType = $row.find('.rule-pattern-type').val();
                    const patterns = $row.find('.rule-patterns').val().trim();
                    
                    if (!this.validatePatternFormat(patterns, patternType)) {
                        this.showFieldError($row.find('.rule-patterns'), this.getPatternFormatError(patternType));
                        isValid = false;
                    }
                }

                // Validate priority for regular rules
                const $priority = $row.find('.rule-priority');
                const priority = parseInt($priority.val());
                if (isNaN(priority) || priority < 1 || priority > 100) {
                    this.showFieldError($priority, 'Priority must be between 1 and 100');
                    isValid = false;
                }
            } else {
                // Default rule validation - patterns and priority handled by backend
                console.log('Validating default rule - patterns and priority handled by backend');
            }

            console.log('Validation result:', isValid);
            return isValid;
        },

        /**
         * Validate pattern format based on pattern type
         */
        validatePatternFormat(patterns, patternType) {
            if (!patterns) return false;
            
            const patternList = patterns.split(',').map(p => p.trim()).filter(p => p);
            const lowerPatternType = patternType.toLowerCase(); // Make case-insensitive
            
            switch (lowerPatternType) {
                case 'wildcard':
                    // Wildcard patterns: any non-empty string is valid, but should contain * for wildcards
                    return patternList.length > 0;
                    
                case 'starts_with':
                    // Starts with patterns: any non-empty string without wildcards
                    return patternList.length > 0 && patternList.every(pattern => !pattern.includes('*'));
                    
                case 'contains':
                    // Contains patterns: any non-empty string without wildcards
                    return patternList.length > 0 && patternList.every(pattern => !pattern.includes('*'));
                    
                case 'ends_with':
                    // Ends with patterns: any non-empty string without wildcards
                    return patternList.length > 0 && patternList.every(pattern => !pattern.includes('*'));
                    
                case 'regex':
                    // Regex patterns: validate that each pattern is a valid regex
                    return patternList.length > 0 && patternList.every(pattern => {
                        try {
                            new RegExp(pattern);
                            return true;
                        } catch (e) {
                            return false;
                        }
                    });
                    
                default:
                    return true;
            }
        },

        /**
         * Get pattern format error message
         */
        getPatternFormatError(patternType) {
            const lowerPatternType = patternType.toLowerCase(); // Make case-insensitive
            
            switch (lowerPatternType) {
                case 'wildcard':
                    return 'Enter patterns separated by commas. Use * for wildcards (e.g., newsletter*, *urgent*)';
                case 'starts_with':
                    return 'Enter text that emails should start with, separated by commas (e.g., newsletter, urgent)';
                case 'contains':
                    return 'Enter text that emails should contain, separated by commas (e.g., urgent, newsletter)';
                case 'ends_with':
                    return 'Enter text that emails should end with, separated by commas (e.g., confirmation, receipt)';
                case 'regex':
                    return 'Enter valid regular expressions separated by commas (e.g., ^newsletter.*$, \\b(urgent|important)\\b)';
                default:
                    return 'Invalid pattern format';
            }
        },

        /**
         * Validate a single field
         */
        validateField($field, errorMessage) {
            const value = $field.val() ? $field.val().trim() : '';
            if (!value) {
                this.showFieldError($field, errorMessage);
                return false;
            }
            return true;
        },

        /**
         * Show field validation error
         */
        showFieldError($field, message) {
            $field.addClass('has-error');
            
            // Remove existing error message
            $field.siblings('.field-error').remove();
            
            // Add new error message
            $field.after(`<span class="field-error">${message}</span>`);
        },

        /**
         * Clear field error
         */
        clearFieldError($field) {
            $field.removeClass('has-error');
            $field.siblings('.field-error').remove();
        },

        /**
         * Clear all validation errors in a row
         */
        clearAllValidationErrors($row) {
            $row.find('.has-error').removeClass('has-error');
            $row.find('.field-error').remove();
        },

        /**
         * Check if a rule is the default rule
         */
        isDefaultRule($row) {
            return $row.data('is-default') == 1;
        },

        /**
         * Save an existing rule
         */
        saveExistingRule($row) {
            console.log('=== SAVING EXISTING RULE ===');
            const ruleId = $row.data('id');
            console.log('Rule ID:', ruleId);
            
            // Debug: Log all form field values before serialization
            console.log('Form field values before serialization:');
            console.log('  rule-name:', $row.find('.rule-name').val());
            console.log('  rule-patterns:', $row.find('.rule-patterns').val());
            console.log('  rule-pattern-type:', $row.find('.rule-pattern-type').val());
            console.log('  rule-provider:', $row.find('.rule-provider').val());
            console.log('  rule-recipients:', $row.find('.rule-recipients').val());
            console.log('  rule-priority:', $row.find('.rule-priority').val());
            console.log('  rule-enabled:', $row.find('.rule-enabled').val());
            console.log('  rule-antispam:', $row.find('.rule-antispam').val());
            
            // Use the serialized format for existing rules too
            const formDataString = this.createSerializedFormData($row, ruleId);
            console.log('Serialized form data:', formDataString);
            
            // Debug: Log AJAX request details
            const ajaxData = {
                action: 'intellisend_update_routing_rule',
                nonce: intellisendData.nonce,
                formData: formDataString
            };
            
            console.log('AJAX Request Details:');
            console.log('  URL:', intellisendData.ajax_url);  // Fixed: was ajaxUrl, should be ajax_url
            console.log('  Data:', ajaxData);
            console.log('  Available intellisendData:', intellisendData);
            
            this.showLoading();
            
            $.ajax({
                url: intellisendData.ajax_url,  // Fixed: was ajaxUrl, should be ajax_url
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    console.log('=== UPDATE SUCCESS RESPONSE ===');
                    console.log('Full response:', response);
                    console.log('Response type:', typeof response);
                    console.log('Response success:', response.success);
                    console.log('Response data:', response.data);
                    
                    if (response.success) {
                        this.exitEditMode($row, false);
                        this.showSuccess('Routing rule updated successfully');
                    } else {
                        console.error('=== UPDATE RULE FAILED ===');
                        console.error('Error message:', response.data);
                        console.error('Full error response:', response);
                        this.showError(response.data || 'Failed to update routing rule');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('=== AJAX ERROR ===');
                    console.error('XHR object:', xhr);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    // Try to parse the response as JSON
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        console.error('Parsed error response:', errorResponse);
                    } catch (e) {
                        console.error('Could not parse error response as JSON');
                    }
                    
                    this.showError(`Network error: ${error || 'Unknown error'}`);
                },
                complete: () => {
                    console.log('=== UPDATE AJAX COMPLETE ===');
                    this.hideLoading();
                }
            });
        },

        /**
         * Save a new rule
         */
        saveNewRule($row) {
            console.log('=== SAVING NEW RULE ===');
            console.log('Row data:', $row.data());
            
            // Debug: Log all form field values
            console.log('Form field values:');
            console.log('  rule-name:', $row.find('.rule-name').val());
            console.log('  rule-patterns:', $row.find('.rule-patterns').val());
            console.log('  rule-pattern-type:', $row.find('.rule-pattern-type').val());
            console.log('  rule-provider:', $row.find('.rule-provider').val());
            console.log('  rule-recipients:', $row.find('.rule-recipients').val());
            console.log('  rule-priority:', $row.find('.rule-priority').val());
            console.log('  rule-enabled:', $row.find('.rule-enabled').val());
            console.log('  rule-antispam:', $row.find('.rule-antispam').val());
            
            // Create serialized form data that matches the backend expectations
            const formDataString = this.createSerializedFormData($row);
            
            // Debug: Log the AJAX request details
            const ajaxData = {
                action: 'intellisend_add_routing_rule',
                nonce: intellisendData.nonce,
                formData: formDataString
            };
            
            console.log('AJAX Request Data:', ajaxData);
            console.log('AJAX URL:', intellisendData.ajax_url);  // Fixed: was ajaxUrl, should be ajax_url
            console.log('Available intellisendData:', intellisendData);
            
            this.showLoading();
            
            $.ajax({
                url: intellisendData.ajax_url,  // Fixed: was ajaxUrl, should be ajax_url
                type: 'POST',
                data: ajaxData,
                success: (response) => {
                    console.log('=== AJAX SUCCESS RESPONSE ===');
                    console.log('Full response:', response);
                    console.log('Response type:', typeof response);
                    console.log('Response success:', response.success);
                    console.log('Response data:', response.data);
                    
                    if (response.success) {
                        this.showSuccess('Routing rule added successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        console.error('=== ADD RULE FAILED ===');
                        console.error('Error message:', response.data);
                        console.error('Full error response:', response);
                        this.showError(response.data || 'Failed to add routing rule');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('=== AJAX ERROR ===');
                    console.error('XHR object:', xhr);
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('Response Text:', xhr.responseText);
                    console.error('Status Code:', xhr.status);
                    
                    // Try to parse the response as JSON
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        console.error('Parsed error response:', errorResponse);
                    } catch (e) {
                        console.error('Could not parse error response as JSON');
                    }
                    
                    this.showError(`Network error: ${error || 'Unknown error'}`);
                },
                complete: () => {
                    console.log('=== AJAX COMPLETE ===');
                    this.hideLoading();
                }
            });
        },

        /**
         * Create serialized form data that matches backend expectations
         */
        createSerializedFormData($row, ruleId = null) {
            console.log('=== CREATING SERIALIZED FORM DATA ===');
            
            // Create a temporary form to serialize data properly
            const $form = $('<form></form>');
            
            // Get values from form fields
            const ruleName = $row.find('.rule-name').val().trim();
            const ruleProvider = $row.find('.rule-provider').val();
            const ruleRecipients = $row.find('.rule-recipients').val().trim();
            const rulePatterns = $row.find('.rule-patterns').val().trim();
            const rulePatternType = $row.find('.rule-pattern-type').val() || 'wildcard';
            const rulePriority = $row.find('.rule-priority').val();
            const ruleEnabled = $row.find('.rule-enabled').val();
            const ruleAntispam = $row.find('.rule-antispam').val();
            
            console.log('Field values extracted:');
            console.log('  ruleName:', ruleName);
            console.log('  ruleProvider:', ruleProvider);
            console.log('  ruleRecipients:', ruleRecipients);
            console.log('  rulePatterns:', rulePatterns);
            console.log('  rulePatternType:', rulePatternType);
            console.log('  rulePriority:', rulePriority);
            console.log('  ruleEnabled:', ruleEnabled);
            console.log('  ruleAntispam:', ruleAntispam);
            
            // Add fields with the names expected by admin/class-ajax.php
            $form.append(`<input type="hidden" name="name" value="${ruleName}">`);
            $form.append(`<input type="hidden" name="default_provider_name" value="${ruleProvider}">`);
            $form.append(`<input type="hidden" name="recipients" value="${ruleRecipients}">`);
            $form.append(`<input type="hidden" name="pattern_type" value="${rulePatternType}">`);
            
            // Add rule ID for updates
            if (ruleId) {
                console.log('Adding id:', ruleId);
                $form.append(`<input type="hidden" name="id" value="${ruleId}">`);
            }
            
            // Add patterns and priority for non-default rules
            if (!this.isDefaultRule($row)) {
                console.log('Non-default rule - adding patterns and priority');
                $form.append(`<input type="hidden" name="subject_patterns" value="${rulePatterns}">`);
                $form.append(`<input type="hidden" name="priority" value="${rulePriority}">`);
            } else {
                console.log('Default rule - skipping patterns and priority');
            }
            
            // Handle checkboxes - ALWAYS add both enabled and anti_spam_enabled
            // This ensures the backend receives explicit values for both on/off states
            console.log('Adding enabled=' + (ruleEnabled === '1' ? '1' : '0'));
            $form.append(`<input type="hidden" name="enabled" value="${ruleEnabled === '1' ? '1' : '0'}">`);
            
            console.log('Adding anti_spam_enabled=' + (ruleAntispam === '1' ? '1' : '0'));
            $form.append(`<input type="hidden" name="anti_spam_enabled" value="${ruleAntispam === '1' ? '1' : '0'}">`);
            
            
            const serializedData = $form.serialize();
            console.log('Final serialized data:', serializedData);
            
            // Also log the form HTML for debugging
            console.log('Form HTML created:', $form.html());
            
            return serializedData;
        },

        /**
         * Collect rule data from form inputs (legacy method for object format)
         */
        collectRuleData($row, ruleId = null) {
            const formData = {
                name: $row.find('.rule-name').val().trim(),
                default_provider_name: $row.find('.rule-provider').val(),
                recipients: $row.find('.rule-recipients').val().trim(),
                pattern_type: $row.find('.rule-pattern-type').val() || 'wildcard',
                enabled: $row.find('.rule-enabled').val() === '1' ? 1 : 0,
                anti_spam_enabled: $row.find('.rule-antispam').val() === '1' ? 1 : 0
            };

            // Add rule ID for updates
            if (ruleId) {
                formData.id = ruleId;
            }

            // Add patterns and priority for non-default rules
            if (!this.isDefaultRule($row)) {
                formData.subject_patterns = $row.find('.rule-patterns').val().trim();
                formData.priority = parseInt($row.find('.rule-priority').val());
            }

            console.log('Collected form data:', formData);
            return formData;
        },

        /**
         * Toggle rule status (activate/deactivate)
         */
        toggleRuleStatus(ruleId, activate) {
            const action = activate ? 'intellisend_activate_routing_rule' : 'intellisend_deactivate_routing_rule';
            const successMessage = activate ? 'Rule activated successfully' : 'Rule deactivated successfully';
            
            this.showLoading();
            
            $.ajax({
                url: intellisendData.ajax_url,  // Fixed: was ajaxUrl, should be ajax_url
                type: 'POST',
                data: {
                    action: action,
                    nonce: intellisendData.nonce,
                    id: ruleId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(successMessage);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showError(response.data || 'Failed to update rule status');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', xhr, status, error);
                    this.showError(`Network error: ${error || 'Unknown error'}`);
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        },

        /**
         * Confirm rule deletion
         */
        confirmDeleteRule(ruleId, ruleName) {
            const message = `Are you sure you want to delete the routing rule "${ruleName}"?\n\nThis action cannot be undone.`;
            
            if (confirm(message)) {
                this.deleteRule(ruleId);
            }
        },

        /**
         * Delete a rule
         */
        deleteRule(ruleId) {
            this.showLoading();
            
            $.ajax({
                url: intellisendData.ajax_url,  // Fixed: was ajaxUrl, should be ajax_url
                type: 'POST',
                data: {
                    action: 'intellisend_delete_routing_rule',
                    nonce: intellisendData.nonce,
                    id: ruleId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Routing rule deleted successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        this.showError(response.data || 'Failed to delete routing rule');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', xhr, status, error);
                    this.showError(`Network error: ${error || 'Unknown error'}`);
                },
                complete: () => {
                    this.hideLoading();
                }
            });
        },

        /**
         * Duplicate an existing rule
         */
        duplicateRule($row) {
            const isDefault = $row.data('is-default') == 1;
            
            // Don't allow duplicating default rule
            if (isDefault) {
                this.showError('Default rule cannot be duplicated');
                return;
            }
            
            // Create a new rule row
            this.addNewRuleRow();
            const $newRow = $('#routing-rules-table .new-rule-row').last();
            
            // Copy values from the source row
            $newRow.find('.rule-name').val($row.find('.rule-name').val() + ' (Copy)');
            $newRow.find('.rule-patterns').val($row.find('.rule-patterns').val());
            $newRow.find('.rule-pattern-type').val($row.find('.rule-pattern-type').val());
            $newRow.find('.rule-provider').val($row.find('.rule-provider').val());
            $newRow.find('.rule-priority').val($row.find('.rule-priority').val());
            $newRow.find('.rule-enabled').val($row.find('.rule-enabled').val());
            $newRow.find('.rule-antispam').val($row.find('.rule-antispam').val());
            
            // Copy recipients
            const recipients = $row.find('.rule-recipients').val();
            if (recipients) {
                $newRow.find('.rule-recipients').val(recipients);
                // Update recipient tags display
                const $container = $newRow.find('.recipients-container');
                $container.find('.recipients-tags').empty();
                recipients.split(',').forEach((recipient) => {
                    const email = recipient.trim();
                    if (email) {
                        this.addRecipientTag($container, email);
                    }
                });
            }
            
            // Trigger pattern type change to update placeholder
            $newRow.find('.rule-pattern-type').trigger('change');
        },

        /**
         * Setup recipient tags functionality
         */
        setupRecipientTags() {
            // Already handled in bindEvents
        },

        /**
         * Add recipient tag
         */
        addRecipientTag($container, email) {
            if (!this.isValidEmail(email)) {
                return false;
            }
            
            // Check if email already exists
            let exists = false;
            $container.find('.recipient-tag').each(function() {
                if ($(this).text().replace('×', '').trim() === email) {
                    exists = true;
                    return false;
                }
            });
            
            if (exists) {
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
        updateRecipientInput($container) {
            const recipients = [];
            
            $container.find('.recipient-tag').each(function() {
                recipients.push($(this).text().replace('×', '').trim());
            });
            
            $container.find('.rule-recipients').val(recipients.join(','));
        },

        /**
         * Validate email address
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Show loading indicator
         */
        showLoading() {
            let $overlay = $('.loading-overlay');
            if ($overlay.length === 0) {
                $overlay = $('<div class="loading-overlay"><div class="loading-spinner"></div></div>');
                $('body').append($overlay);
            }
            $overlay.show();
        },

        /**
         * Hide loading indicator
         */
        hideLoading() {
            $('.loading-overlay').hide();
        },

        /**
         * Show success notification
         */
        showSuccess(message) {
            this.showNotification('success', message);
        },

        /**
         * Show error notification
         */
        showError(message) {
            this.showNotification('error', message);
        },

        /**
         * Show notification
         */
        showNotification(type, message) {
            // Remove existing notifications
            $('.intellisend-notification').remove();
            
            // Create notification
            const $notification = $(`
                <div class="notice notice-${type} is-dismissible intellisend-notification">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            // Add to page
            $('.wrap h1').after($notification);
            
            // Handle dismiss button
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut();
            });
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    $notification.fadeOut();
                }, 5000);
            }
        }
    };

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        console.log('Document ready, initializing RoutingManager...');
        console.log('intellisendData available:', typeof intellisendData !== 'undefined');
        
        // Initialize the routing manager
        RoutingManager.init();
    });

})(jQuery);