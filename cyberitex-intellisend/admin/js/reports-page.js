/**
 * IntelliSend Reports Page JavaScript
 * 
 * Handles functionality for the email reports and logs page.
 */

(function($) {
    'use strict';

    // Main IntelliSend Reports object
    const IntelliSendReports = {
        /**
         * Initialize all components
         */
        init: function() {
            this.setupEventListeners();
            this.setupDatePickers();
            this.setupStatusBadges();
            this.setupSortableColumns();
            this.setupRecordSelection();
            this.injectCustomStyles();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            const self = this;
            
            // View report
            $(document).on('click', '.view-report', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.viewReport($(this).data('id'));
            });
            
            // Close modal
            $(document).on('click', '.intellisend-modal-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeModal($(this).closest('.intellisend-modal'));
            });
            
            // Click outside modal to close
            $(document).on('click', '.intellisend-modal', function(event) {
                if ($(event.target).hasClass('intellisend-modal')) {
                    self.closeModal($(event.target));
                }
            });
            
            // Prevent clicks inside modal from closing it
            $(document).on('click', '.intellisend-modal-content', function(e) {
                e.stopPropagation();
            });
            
            // Filter form reset
            $(document).on('click', '#reset-filters', function(e) {
                e.preventDefault();
                self.resetFilters();
            });
            
            // Filter form submit with loading state
            $(document).on('submit', '#filter-form', function() {
                const $submitButton = $(this).find('button[type="submit"]');
                self.setButtonLoading($submitButton, 'Filtering...');
            });
            
            // Escape key to close modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.intellisend-modal:visible').each(function() {
                        self.closeModal($(this));
                    });
                }
            });
            
            // Bulk action apply button
            $(document).on('click', '#bulk-action-apply', function() {
                const action = $('#bulk-action-selector').val();
                if (!action) {
                    self.showNotification('error', 'Please select an action');
                    return;
                }
                
                const selectedIds = self.getSelectedReportIds();
                if (selectedIds.length === 0) {
                    self.showNotification('error', 'Please select at least one report');
                    return;
                }
                
                if (action === 'delete') {
                    self.confirmDeleteReports(selectedIds);
                }
            });
            
            // Delete all reports button
            $(document).on('click', '#delete-all-reports', function() {
                self.confirmDeleteAllReports();
            });
        },
        
        /**
         * Set up date pickers for filter form
         */
        setupDatePickers: function() {
            // Use native date inputs with fallback to jQuery UI datepicker if needed
            const dateInputs = $('.date-picker');
            
            if (dateInputs.length) {
                // Check if browser supports date input
                const input = document.createElement('input');
                input.setAttribute('type', 'date');
                const supportsDate = input.type === 'date';
                
                if (!supportsDate && $.fn.datepicker) {
                    dateInputs.datepicker({
                        dateFormat: 'yy-mm-dd',
                        changeMonth: true,
                        changeYear: true,
                        maxDate: '+0d'
                    });
                }
            }
        },
        
        /**
         * Set up status badges with appropriate colors
         */
        setupStatusBadges: function() {
            $('.status-badge').each(function() {
                const status = $(this).data('status');
                $(this).addClass('status-' + status);
            });
        },
        
        /**
         * Set up sortable columns
         */
        setupSortableColumns: function() {
            const self = this;
            
            // Handle sortable column clicks
            $(document).on('click', '.intellisend-table th.sortable', function() {
                const column = $(this).data('sort');
                if (!column) return;
                
                // Get current URL and parameters
                let currentUrl = new URL(window.location.href);
                let params = new URLSearchParams(currentUrl.search);
                
                // Determine sort order
                let order = 'asc';
                if (params.get('orderby') === column) {
                    order = params.get('order') === 'asc' ? 'desc' : 'asc';
                }
                
                // Update URL parameters
                params.set('orderby', column);
                params.set('order', order);
                
                // Redirect to new URL
                currentUrl.search = params.toString();
                window.location.href = currentUrl.toString();
            });
        },
        
        /**
         * Set up record selection
         */
        setupRecordSelection: function() {
            const self = this;
            
            // Select all checkbox
            $(document).on('change', '#select-all-reports', function() {
                const isChecked = $(this).prop('checked');
                $('.report-checkbox').prop('checked', isChecked);
                self.updateSelectedRowsHighlight();
                self.updateSelectedCount();
            });
            
            // Individual checkboxes
            $(document).on('change', '.report-checkbox', function() {
                self.updateSelectAllCheckbox();
                self.updateSelectedRowsHighlight();
                self.updateSelectedCount();
            });
            
            // Make row clickable for selection
            $(document).on('click', '.intellisend-table tbody tr', function(e) {
                // Don't toggle if clicking on action buttons or links
                if ($(e.target).is('button, a, .dashicons') || $(e.target).closest('button, a').length) {
                    return;
                }
                
                const $checkbox = $(this).find('.report-checkbox');
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            });
            
            // Initialize selected count
            this.updateSelectedCount();
        },
        
        /**
         * Update the "Select All" checkbox state
         */
        updateSelectAllCheckbox: function() {
            const totalCheckboxes = $('.report-checkbox').length;
            const checkedCheckboxes = $('.report-checkbox:checked').length;
            
            if (checkedCheckboxes === 0) {
                $('#select-all-reports').prop('checked', false).prop('indeterminate', false);
            } else if (checkedCheckboxes === totalCheckboxes) {
                $('#select-all-reports').prop('checked', true).prop('indeterminate', false);
            } else {
                $('#select-all-reports').prop('checked', false).prop('indeterminate', true);
            }
        },
        
        /**
         * Update the selected count display
         */
        updateSelectedCount: function() {
            const selectedCount = $('.report-checkbox:checked').length;
            $('#selected-count').text(selectedCount);
        },
        
        /**
         * Update row highlighting based on selection
         */
        updateSelectedRowsHighlight: function() {
            $('.intellisend-table tbody tr').each(function() {
                const isChecked = $(this).find('.report-checkbox').prop('checked');
                $(this).toggleClass('selected', isChecked);
            });
        },
        
        /**
         * Get array of selected report IDs
         */
        getSelectedReportIds: function() {
            const selectedIds = [];
            $('.report-checkbox:checked').each(function() {
                selectedIds.push($(this).data('id'));
            });
            return selectedIds;
        },
        
        /**
         * Confirm deletion of selected reports
         */
        confirmDeleteReports: function(reportIds) {
            if (!reportIds || !reportIds.length) return;
            
            if (confirm('Are you sure you want to delete the selected reports? This action cannot be undone.')) {
                this.deleteReports(reportIds);
            }
        },
        
        /**
         * Confirm deletion of all reports
         */
        confirmDeleteAllReports: function() {
            if (confirm('Are you sure you want to delete ALL reports? This action cannot be undone.')) {
                this.deleteAllReports();
            }
        },
        
        /**
         * Delete selected reports
         */
        deleteReports: function(reportIds) {
            const self = this;
            
            // Show loading state
            const $deleteButton = $('#bulk-action-apply');
            self.setButtonLoading($deleteButton, 'Deleting...');
            
            // Determine which nonce to use with proper fallbacks
            let nonceToUse = '';
            if (typeof intellisendData !== 'undefined' && intellisendData.nonce) {
                nonceToUse = intellisendData.nonce;
            } else if (typeof intellisend_ajax !== 'undefined' && intellisend_ajax.nonce) {
                nonceToUse = intellisend_ajax.nonce;
            } else {
                nonceToUse = 'ee86b922eb'; // Legacy fallback
            }
            
            // Determine AJAX URL
            const ajaxUrlToUse = typeof intellisendData !== 'undefined' && intellisendData.ajaxurl ? 
                intellisendData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
            // Send AJAX request
            $.ajax({
                url: ajaxUrlToUse,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_reports',
                    ids: reportIds,
                    nonce: nonceToUse
                },
                success: function(response) {
                    self.resetButtonLoading($deleteButton);
                    
                    if (response.success) {
                        self.showNotification('success', response.data.message || 'Reports deleted successfully');
                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data.message || 'Failed to delete reports');
                    }
                },
                error: function() {
                    self.resetButtonLoading($deleteButton);
                    self.showNotification('error', 'A network error occurred');
                }
            });
        },
        
        /**
         * Delete all reports
         */
        deleteAllReports: function() {
            const self = this;
            
            // Show loading state
            const $deleteButton = $('#delete-all-reports');
            self.setButtonLoading($deleteButton, 'Deleting...');
            
            // Determine which nonce to use with proper fallbacks
            let nonceToUse = '';
            if (typeof intellisendData !== 'undefined' && intellisendData.nonce) {
                nonceToUse = intellisendData.nonce;
            } else if (typeof intellisend_ajax !== 'undefined' && intellisend_ajax.nonce) {
                nonceToUse = intellisend_ajax.nonce;
            } else {
                nonceToUse = 'ee86b922eb'; // Legacy fallback
            }
            
            // Determine AJAX URL
            const ajaxUrlToUse = typeof intellisendData !== 'undefined' && intellisendData.ajaxurl ? 
                intellisendData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            
            // Send AJAX request
            $.ajax({
                url: ajaxUrlToUse,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_all_reports',
                    nonce: nonceToUse
                },
                success: function(response) {
                    self.resetButtonLoading($deleteButton);
                    
                    if (response.success) {
                        self.showNotification('success', response.data.message || 'All reports deleted successfully');
                        // Reload the page after a short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        self.showNotification('error', response.data.message || 'Failed to delete reports');
                    }
                },
                error: function() {
                    self.resetButtonLoading($deleteButton);
                    self.showNotification('error', 'A network error occurred');
                }
            });
        },
        
        /**
         * View report details
         */
        viewReport: function(reportId) {
            const self = this;
            
            console.log('Opening modal for report ID:', reportId);
            
            // Fallback nonce for compatibility during transition
            const legacyNonce = 'ee86b922eb';
            
            // Determine which nonce to use with proper fallbacks
            let nonceToUse = '';
            if (typeof intellisendData !== 'undefined' && intellisendData.nonce) {
                nonceToUse = intellisendData.nonce;
                console.log('Using nonce from intellisendData:', nonceToUse);
            } else if (typeof intellisend_ajax !== 'undefined' && intellisend_ajax.nonce) {
                nonceToUse = intellisend_ajax.nonce;
                console.log('Using nonce from intellisend_ajax:', nonceToUse);
            } else {
                nonceToUse = legacyNonce;
                console.log('Using fallback legacy nonce:', nonceToUse);
            }
            
            // Determine AJAX URL
            const ajaxUrlToUse = typeof intellisendData !== 'undefined' && intellisendData.ajaxurl ? 
                intellisendData.ajaxurl : (typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php');
            console.log('Using AJAX URL:', ajaxUrlToUse);
            
            // Show loading state in modal
            $('#view-report-modal').addClass('loading');
            $('#view-report-modal').show();
            
            // Get report data via AJAX
            $.ajax({
                url: ajaxUrlToUse,
                type: 'POST',
                data: {
                    action: 'intellisend_get_report',
                    id: reportId,
                    nonce: nonceToUse
                },
                success: function(response) {
                    console.log('AJAX response:', response);
                    $('#view-report-modal').removeClass('loading');
                    
                    if (response.success) {
                        console.log('Report data:', response.data);
                        self.populateReportModal(response.data);
                    } else {
                        console.error('Error response:', response);
                        self.showNotification('error', response.data && response.data.message ? response.data.message : 'Failed to load report details.');
                        self.closeModal($('#view-report-modal'));
                    }
                },
                error: function(xhr, status, error) {
                    $('#view-report-modal').removeClass('loading');
                    console.error('AJAX error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    self.showNotification('error', 'A network error occurred while loading the report.');
                    self.closeModal($('#view-report-modal'));
                }
            });
        },
        
        /**
         * Populate report modal with data
         */
        populateReportModal: function(report) {
            try {
                console.log('Populating modal with data:', report);
                
                // Fill the modal with report data
                $('#report-date').text(this.formatDate(report.date) || 'N/A');
                $('#report-status').html(this.getStatusBadgeHtml(report.status || 'unknown'));
                $('#report-provider').text(report.providerName || 'N/A');
                $('#report-routing').text(report.routingRuleName || 'N/A');
                $('#report-from').text(report.sender || 'N/A');
                $('#report-to').text(report.recipients || 'N/A');
                $('#report-subject').text(report.subject || 'N/A');
                
                // Format message with syntax highlighting if possible
                const messageHtml = report.message ? report.message.replace(/\n/g, '<br>') : 'No message content available';
                $('#report-message').html(messageHtml);
                
                // Headers
                $('#report-headers').text(report.log || 'No headers available');
                
                // Show/hide spam section
                if (report.isSpam) {
                    $('#report-spam-section').show();
                    $('#report-spam-score').text(report.spamScore || 'N/A');
                } else {
                    $('#report-spam-section').hide();
                }
                
                // Show/hide error section
                if (report.status === 'error') {
                    $('#report-error-section').show();
                    $('#report-error-message').text(report.errorMessage || 'Unknown error');
                } else {
                    $('#report-error-section').hide();
                }
                
                console.log('Modal populated successfully');
            } catch (error) {
                console.error('Error populating modal:', error);
                this.showNotification('error', 'Error displaying report details');
            }
        },
        
        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            if (!dateString) return 'N/A';
            
            try {
                const date = new Date(dateString);
                return date.toLocaleString();
            } catch (e) {
                return dateString;
            }
        },
        
        /**
         * Get HTML for status badge
         */
        getStatusBadgeHtml: function(status) {
            if (!status) return 'N/A';
            
            let label = status.charAt(0).toUpperCase() + status.slice(1);
            return '<span class="status-badge status-' + status + '">' + label + '</span>';
        },
        
        /**
         * Close modal
         */
        closeModal: function($modal) {
            if (!$modal) {
                console.error('Modal not found');
                return;
            }
            console.log('Closing modal:', $modal.attr('id'));
            $modal.hide();
        },
        
        /**
         * Reset filters
         */
        resetFilters: function() {
            const $form = $('#filter-form');
            
            // Reset all form fields except page and submit
            $form.find('input:not([name="page"]), select').each(function() {
                $(this).val('');
            });
            
            // Submit the form
            $form.submit();
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
        resetButtonLoading: function($button) {
            $button.prop('disabled', false).removeClass('is-loading');
            $button.find('.loading-spinner').remove();
            $button.text($button.data('original-text'));
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
            
            $('.intellisend-admin h1').after(noticeHtml);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $('.intellisend-notice').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
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
            
            $('<style id="intellisend-reports-dynamic-styles"></style>')
                .text(customStyles)
                .appendTo('head');
        }
    };

    // Initialize on document ready
    $(function() {
        IntelliSendReports.init();
    });

})(jQuery);
