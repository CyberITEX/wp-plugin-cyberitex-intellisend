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
            this.injectCustomStyles();
        },

        /**
         * Set up event listeners
         */
        setupEventListeners: function() {
            const self = this;
            
            // View report
            $('.view-report').on('click', function() {
                self.viewReport($(this).data('id'));
            });
            
            // Close modal
            $('.intellisend-modal-close').on('click', function() {
                self.closeModal($(this).closest('.intellisend-modal'));
            });
            
            // Click outside modal to close
            $(window).on('click', function(event) {
                if ($(event.target).hasClass('intellisend-modal')) {
                    self.closeModal($(event.target));
                }
            });
            
            // Filter form reset
            $('#reset-filters').on('click', function(e) {
                e.preventDefault();
                self.resetFilters();
            });
            
            // Filter form submit with loading state
            $('#filter-form').on('submit', function() {
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
         * View report details
         */
        viewReport: function(reportId) {
            const self = this;
            
            // Show loading state in modal
            $('#view-report-modal').addClass('loading');
            $('#view-report-modal').show();
            
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
                    if (response.success) {
                        self.populateReportModal(response.data);
                    } else {
                        self.showNotification('error', response.data || 'Failed to load report details.');
                        self.closeModal($('#view-report-modal'));
                    }
                },
                error: function() {
                    self.showNotification('error', 'A network error occurred while loading the report.');
                    self.closeModal($('#view-report-modal'));
                },
                complete: function() {
                    $('#view-report-modal').removeClass('loading');
                }
            });
        },
        
        /**
         * Populate report modal with data
         */
        populateReportModal: function(report) {
            // Fill the modal with report data
            $('#report-date').text(this.formatDate(report.date));
            $('#report-status').html(this.getStatusBadgeHtml(report.status));
            $('#report-provider').text(report.defaultProviderName || 'N/A');
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
