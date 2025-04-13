/**
 * IntelliSend Admin JavaScript
 *
 * Handles AJAX interactions and UI enhancements for the admin area.
 */

(function($) {
    'use strict';

    // Document ready
    $(function() {
        // Ensure toast container exists
        if ($('.intellisend-toast-container').length === 0) {
            $('<div class="intellisend-toast-container"></div>').appendTo('.wrap');
        }
        
        // Handle settings form submission
        $('.intellisend-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitButton = form.find('input[type="submit"]');
            
            // Disable the submit button and show loading state
            submitButton.prop('disabled', true).val('Saving...');
            
            // Get all form data
            var formData = new FormData(form[0]);
            formData.append('action', 'intellisend_ajax_handler');
            formData.append('sub_action', 'settings_saved');
            formData.append('nonce', intellisend_admin.nonce);
            
            // Send AJAX request
            $.ajax({
                url: intellisend_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                    } else {
                        // Show error message and debug info if available
                        var errorMessage = response.data.message || 'An unknown error occurred.';
                        if (response.data.debug) {
                            // Create a collapsible debug section
                            var debugInfo = '<div class="debug-info-toggle">Show Technical Details</div>' +
                                           '<div class="debug-info-content" style="display:none;"><pre>' + 
                                           response.data.debug + '</pre></div>';
                            showNotice('error', errorMessage + '<br>' + debugInfo);
                            
                            // Add click handler for the toggle
                            setTimeout(function() {
                                $('.debug-info-toggle').on('click', function() {
                                    $('.debug-info-content').toggle();
                                    $(this).text($(this).text() === 'Show Technical Details' ? 
                                        'Hide Technical Details' : 'Show Technical Details');
                                });
                            }, 100);
                        } else {
                            showNotice('error', errorMessage);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Try to extract more information from the error
                    var errorMessage = 'Connection error. Please try again.';
                    var debugInfo = '';
                    
                    try {
                        // Try to parse the response as JSON
                        var jsonResponse = JSON.parse(xhr.responseText);
                        if (jsonResponse && jsonResponse.data) {
                            errorMessage = jsonResponse.data.message || errorMessage;
                            debugInfo = jsonResponse.data.debug || '';
                        }
                    } catch (e) {
                        // If parsing fails, include the raw response and error details
                        debugInfo = 'Status: ' + status + '\nError: ' + error + '\nResponse: ' + xhr.responseText;
                    }
                    
                    // Display the error with debug information
                    if (debugInfo) {
                        var debugSection = '<div class="debug-info-toggle">Show Technical Details</div>' +
                                          '<div class="debug-info-content" style="display:none;"><pre>' + 
                                          debugInfo + '</pre></div>';
                        showNotice('error', errorMessage + '<br>' + debugSection);
                        
                        // Add click handler for the toggle
                        setTimeout(function() {
                            $('.debug-info-toggle').on('click', function() {
                                $('.debug-info-content').toggle();
                                $(this).text($(this).text() === 'Show Technical Details' ? 
                                    'Hide Technical Details' : 'Show Technical Details');
                            });
                        }, 100);
                    } else {
                        showNotice('error', errorMessage);
                    }
                },
                complete: function() {
                    // Re-enable the submit button
                    submitButton.prop('disabled', false).val('Save Settings');
                }
            });
        });
        
        // Helper function to show toast notifications
        function showNotice(type, message) {
            var toast = $('<div class="intellisend-toast intellisend-toast-' + type + '">' +
                         '<div class="intellisend-toast-icon"></div>' +
                         '<div class="intellisend-toast-content">' + message + '</div>' +
                         '<div class="intellisend-toast-close">&times;</div>' +
                         '</div>');
            
            // Add to container
            $('.intellisend-toast-container').append(toast);
            
            // Animate in
            setTimeout(function() {
                toast.addClass('intellisend-toast-visible');
            }, 10);
            
            // Set up auto-dismiss
            var dismissTimeout = setTimeout(function() {
                removeToast(toast);
            }, 8000);
            
            // Handle manual close
            toast.find('.intellisend-toast-close').on('click', function() {
                clearTimeout(dismissTimeout);
                removeToast(toast);
            });
            
            // Function to remove toast with animation
            function removeToast(toast) {
                toast.removeClass('intellisend-toast-visible');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }
        }
        
        // Handle the "Test Email" button click
        $('#intellisend-test-email').on('click', function() {
            var button = $(this);
            var testRecipient = $('#testRecipient').val();
            
            // Disable the button and show loading state
            button.prop('disabled', true).text('Sending...');
            
            // Send AJAX request
            $.ajax({
                url: intellisend_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intellisend_ajax_handler',
                    sub_action: 'test_email_sent',
                    test_email: testRecipient,
                    provider_id: $('#defaultProviderName').val(),
                    nonce: intellisend_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        
                        // Show debug info if available
                        if (response.data.debug) {
                            var debugInfo = '<div class="debug-info-toggle">Show SMTP Debug</div>' +
                                           '<div class="debug-info-content" style="display:none;"><pre>' + 
                                           response.data.debug + '</pre></div>';
                            
                            $('#intellisend-test-email-debug').html(debugInfo).show();
                            
                            // Add click handler for the toggle
                            $('.debug-info-toggle').on('click', function() {
                                $('.debug-info-content').toggle();
                                $(this).text($(this).text() === 'Show SMTP Debug' ? 
                                    'Hide SMTP Debug' : 'Show SMTP Debug');
                            });
                        }
                    } else {
                        // Show error message
                        showNotice('error', response.data.message);
                        
                        // Show debug info if available
                        if (response.data.debug) {
                            var debugInfo = '<div class="debug-info-toggle">Show SMTP Debug</div>' +
                                           '<div class="debug-info-content" style="display:none;"><pre>' + 
                                           response.data.debug + '</pre></div>';
                            
                            $('#intellisend-test-email-debug').html(debugInfo).show();
                            
                            // Add click handler for the toggle
                            $('.debug-info-toggle').on('click', function() {
                                $('.debug-info-content').toggle();
                                $(this).text($(this).text() === 'Show SMTP Debug' ? 
                                    'Hide SMTP Debug' : 'Show SMTP Debug');
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('error', 'Connection error. Please try again.');
                },
                complete: function() {
                    // Re-enable the button
                    button.prop('disabled', false).text('Send Test Email');
                }
            });
        });
        
        // Handle SMTP provider selection change
        $('#defaultProviderName').on('change', function() {
            var selectedProvider = $(this).val();
            
            // Send AJAX request to get provider details
            $.ajax({
                url: intellisend_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intellisend_ajax_handler',
                    sub_action: 'get_smtp_providers',
                    nonce: intellisend_admin.nonce
                },
                success: function(response) {
                    if (response.success && response.data.providers && response.data.providers[selectedProvider]) {
                        var provider = response.data.providers[selectedProvider];
                        
                        // Update form fields with provider information
                        if (provider.server) {
                            $('#server').val(provider.server);
                        }
                        
                        if (provider.port) {
                            $('#port').val(provider.port);
                        }
                        
                        if (provider.encryption) {
                            $('#encryption').val(provider.encryption);
                        }
                        
                        if (provider.description) {
                            $('#provider-description').text(provider.description).show();
                        } else {
                            $('#provider-description').hide();
                        }
                        
                        if (provider.helpLink) {
                            $('#smtp-provider-help-link').attr('href', provider.helpLink).show();
                        } else {
                            $('#smtp-provider-help-link').hide();
                        }
                        
                        // Show/hide authentication fields based on provider
                        if (provider.authRequired === true) {
                            $('.smtp-auth-fields').show();
                            
                            // Fill in username and password if available
                            if (provider.username) {
                                $('#username').val(provider.username);
                            }
                            
                            if (provider.password) {
                                $('#password').val(provider.password);
                            }
                        } else if (provider.authRequired === false) {
                            $('.smtp-auth-fields').hide();
                        }
                    }
                }
            });
        });
        
        // Initialize Select2 for select fields if available
        if ($.fn.select2) {
            $('.intellisend-select2').select2();
        }
        
        // Initialize datepickers if available
        if ($.fn.datepicker) {
            $('.intellisend-datepicker').datepicker({
                dateFormat: 'yy-mm-dd'
            });
        }
        
        // Handle bulk actions
        $('.intellisend-bulk-action-form').on('submit', function(e) {
            var action = $(this).find('select[name="bulk_action"]').val();
            var checkedItems = $(this).find('input[type="checkbox"]:checked').not('#cb-select-all-1, #cb-select-all-2').length;
            
            if (action === 'delete' && checkedItems > 0) {
                if (!confirm(intellisend_admin.strings.confirm_delete)) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === '' || checkedItems === 0) {
                e.preventDefault();
                alert('Please select an action and at least one item.');
                return false;
            }
        });
        
        // Handle "Select All" checkboxes
        $('#cb-select-all-1, #cb-select-all-2').on('change', function() {
            var isChecked = $(this).prop('checked');
            $('input[name="report_ids[]"], input[name="rule_ids[]"], input[name="provider_ids[]"]').prop('checked', isChecked);
            $('#cb-select-all-1, #cb-select-all-2').prop('checked', isChecked);
        });
        
        // Handle email details modal
        $('.view-details').on('click', function(e) {
            e.preventDefault();
            var reportId = $(this).data('id');
            var modal = $('#intellisend-email-modal');
            
            // Show modal
            modal.show();
            
            // Load email details via AJAX
            $('#intellisend-email-details').html('<p>Loading...</p>');
            
            $.ajax({
                url: intellisend_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'intellisend_ajax_handler',
                    sub_action: 'get_email_details',
                    report_id: reportId,
                    nonce: intellisend_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#intellisend-email-details').html(response.data.html);
                    } else {
                        $('#intellisend-email-details').html('<p>Error loading email details: ' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    $('#intellisend-email-details').html('<p>Error loading email details. Please try again.</p>');
                }
            });
        });
        
        // Close modal when clicking the X
        $('.intellisend-modal-close').on('click', function() {
            $('.intellisend-modal').hide();
        });
        
        // Close modal when clicking outside of it
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('intellisend-modal')) {
                $('.intellisend-modal').hide();
            }
        });
    });
})(jQuery);
