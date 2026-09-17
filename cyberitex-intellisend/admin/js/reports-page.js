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
            
            // Keep Tab within the visible dialog; Escape uses the existing close handler.
            $(document).on('keydown', '#view-report-modal', function(e) {
                if (e.key !== 'Tab') return;
                const $focusable = $(this).find('button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])').filter(':visible').not(':disabled');
                const first = $focusable[0];
                const last = $focusable[$focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
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
            
            $('.intellisend-table th.sortable').each(function() {
                const $header = $(this);
                const params = new URLSearchParams(window.location.search);
                $header.attr('aria-sort', params.get('orderby') === $header.data('sort') ? (params.get('order') === 'asc' ? 'ascending' : 'descending') : 'none');
                $header.wrapInner('<button type="button" class="sort-column"></button>');
            });

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
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_reports',
                    ids: reportIds,
                    nonce: intellisendData.nonce
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
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_delete_all_reports',
                    nonce: intellisendData.nonce
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

            // Show loading state in modal
            this.modalTrigger = document.activeElement;
            $('#view-report-modal').addClass('loading').attr('aria-busy', 'true');
            $('#view-report-modal').show().find('.intellisend-modal-close').trigger('focus');
            
            // Get report data via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intellisend_get_report',
                    id: reportId,
                    nonce: intellisendData.nonce
                },
                success: function(response) {

                    $('#view-report-modal').removeClass('loading').attr('aria-busy', 'false');
                    
                    if (response.success) {

                        self.populateReportModal(response.data);
                    } else {

                        self.showNotification('error', response.data && response.data.message ? response.data.message : 'Failed to load report details.');
                        self.closeModal($('#view-report-modal'));
                    }
                },
                error: function(xhr, status, error) {
                    $('#view-report-modal').removeClass('loading').attr('aria-busy', 'false');

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

                // Fill the modal with report data
                $('#report-date').text(this.formatDate(report.date) || 'N/A');
                $('#report-status').html(this.getStatusBadgeHtml(report.status || 'unknown'));
                $('#report-provider').text(report.providerName || 'N/A');
                $('#report-routing').text(report.routingRuleName || 'N/A');
                $('#report-from').text(report.sender || 'N/A');
                $('#report-to').text(report.recipients || 'N/A');
                $('#report-subject').text(report.subject || 'N/A');
                
                // Format message with syntax highlighting if possible
                this.renderMessagePreview(report.message);
                
                // Headers
                $('#report-headers').text(report.log || 'No log details available');
                
                // Show/hide spam section
                if (Number(report.isSpam) === 1) {
                    $('#report-spam-section').show();
                    $('#report-spam-score').text(report.spamScore || 'N/A');
                } else {
                    $('#report-spam-section').hide();
                }
                
                // Show/hide error section
                if (report.status === 'failed' || report.status === 'error') {
                    $('#report-error-section').show();
                    $('#report-error-message').text(report.errorMessage || report.log || 'No additional error details were recorded.');
                } else {
                    $('#report-error-section').hide();
                }

            } catch (error) {

                this.showNotification('error', 'Error displaying report details');
            }
        },
        
        /**
         * Format date for display
         */
        renderMessagePreview: function(message) {
            const $container = $('#report-message').empty();
            if (!message) { $container.text('No message content available'); return; }
            // Preserve email formatting in an isolated preview, with remote resources disabled.
            const parsed = new DOMParser().parseFromString(String(message), 'text/html');
            parsed.querySelectorAll('script, iframe, frame, object, embed, base, meta, link, form').forEach(node => node.remove());
            parsed.querySelectorAll('*').forEach(node => {
                Array.from(node.attributes).forEach(attribute => {
                    const name = attribute.name.toLowerCase();
                    if (name.startsWith('on') || ['href', 'srcset', 'srcdoc', 'action', 'formaction', 'target', 'ping'].includes(name) || (name === 'src' && !/^data:image\/(?:png|gif|jpeg|webp);/i.test(attribute.value))) node.removeAttribute(attribute.name);
                });
            });
            const preview = document.createElement('iframe');
            preview.className = 'report-message-preview';
            preview.title = 'Email message preview';
            // Same-origin access lets the parent size the preview and handle keyboard focus.
            // Scripts, forms, popups and navigation out of the frame remain sandboxed.
            preview.setAttribute('sandbox', 'allow-same-origin');
            preview.setAttribute('referrerpolicy', 'no-referrer');
            preview.setAttribute('tabindex', '0');
            preview.addEventListener('load', () => {
                const content = preview.contentDocument;
                if (!content) return;
                preview.style.height = Math.max(120, Math.min(content.body.scrollHeight + 24, 640)) + 'px';
                content.addEventListener('keydown', event => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        this.closeModal($('#view-report-modal'));
                    } else if (event.key === 'Tab') {
                        event.preventDefault();
                        $('#view-report-modal .intellisend-modal-close').trigger('focus');
                    }
                });
            });
            preview.srcdoc = '<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:"><style>html{color-scheme:light dark}body{margin:8px;background:#fff;color:#1e1e1e;font:14px/1.5 sans-serif;overflow-wrap:anywhere;white-space:pre-wrap}img{max-width:100%;height:auto}table{max-width:100%}@media(prefers-color-scheme:dark){body{background:#111827;color:#f8fafc}}</style></head><body>' + parsed.body.innerHTML + '</body></html>';
            $container.append(preview).append($('<p class="description"></p>').text('Remote images and links are disabled in this preview.'));
        },

        formatDate: function(dateString) {
            // WordPress stores site-local wall-clock time without a timezone offset.
            // Preserve it instead of interpreting it in the administrator's timezone.
            return dateString || '';
        },
        
        /**
         * Get HTML for status badge
         */
        getStatusBadgeHtml: function(status) {
            if (!status) return 'N/A';
            
            let label = status.charAt(0).toUpperCase() + status.slice(1);
            return $('<span class="status-badge"></span>').addClass('status-' + String(status).replace(/[^a-z-]/gi, '')).text(label).prop('outerHTML');
        },
        
        /**
         * Close modal
         */
        closeModal: function($modal) {
            if (!$modal) {

                return;
            }

            $modal.hide().attr('aria-busy', 'false');
            if (this.modalTrigger && document.contains(this.modalTrigger)) this.modalTrigger.focus();
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
                    <div class="intellisend-notice-content"></div>
                </div>
            `;
            
            const $notice = $(noticeHtml).attr('role', type === 'error' ? 'alert' : 'status');
            $notice.find('.intellisend-notice-content').text(message);
            $('.intellisend-admin h1').after($notice);
            
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
