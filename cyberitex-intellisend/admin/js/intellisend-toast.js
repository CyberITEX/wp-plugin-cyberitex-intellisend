/**
 * IntelliSend Toast Notification System
 * 
 * A reusable toast notification component for the IntelliSend plugin.
 * Can be used across different parts of the plugin for consistent notifications.
 */

const IntelliSendToast = (function($) {
    'use strict';
    
    // Toast container ID
    const TOAST_CONTAINER_ID = 'intellisend-toast-container';
    
    // Default options
    const DEFAULT_OPTIONS = {
        duration: 5000,     // Duration in ms before auto-dismiss
        position: 'bottom-right', // Position of the toast
        animationDuration: 300 // Animation duration in ms
    };
    
    /**
     * Initialize the toast container
     * @private
     */
    function _initContainer() {
        // Create container if it doesn't exist
        if ($('#' + TOAST_CONTAINER_ID).length === 0) {
            $('body').append(`<div id="${TOAST_CONTAINER_ID}"></div>`);
            
            // Add container styles
            const css = `
                #${TOAST_CONTAINER_ID} {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 9999;
                }
                
                .intellisend-toast {
                    position: relative;
                    margin-top: 10px;
                    padding: 10px 20px;
                    border-radius: 6px;
                    background: #f8f9fa;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    display: flex;
                    align-items: center;
                    transform: translateY(10px);
                    opacity: 0;
                    transition: transform 0.3s ease, opacity 0.3s ease;
                    max-width: 300px;
                }
                
                .intellisend-toast.show {
                    transform: translateY(0);
                    opacity: 1;
                }
                
                .intellisend-toast.spam {
                    background: #fcf0f1;
                }
                
                .intellisend-toast.not-spam {
                    background: #f0f6e9;
                }
                
                .intellisend-toast.error {
                    background: #fcf0f1;
                }
                
                .intellisend-toast .dashicons {
                    margin-right: 10px;
                    font-size: 20px;
                }
                
                .intellisend-toast.spam .dashicons { color: #d63638; }
                .intellisend-toast.not-spam .dashicons { color: #46b450; }
                .intellisend-toast.error .dashicons { color: #d63638; }
                
                .intellisend-toast .toast-content { flex: 1; }
            `;
            
            $('<style>').text(css).appendTo('head');
        }
    }
    
    /**
     * Create a toast element
     * @private
     * @param {string} message - The message to display
     * @param {string} type - The type of toast (success, error, spam, not-spam)
     * @param {object} options - Custom options for this toast
     * @return {jQuery} The toast element
     */
    function _createToastElement(message, type, options) {
        let icon = '';
        let title = '';
        
        // Set icon and title based on type
        switch (type) {
            case 'success':
                icon = '<span class="dashicons dashicons-yes-alt"></span>';
                title = 'Success';
                break;
            case 'error':
                icon = '<span class="dashicons dashicons-no-alt"></span>';
                title = 'Error';
                break;
            case 'spam':
                icon = '<span class="dashicons dashicons-warning"></span>';
                title = 'Message detected as SPAM';
                break;
            case 'not-spam':
                icon = '<span class="dashicons dashicons-yes-alt"></span>';
                title = 'Message is NOT spam';
                break;
            default:
                icon = '<span class="dashicons dashicons-info"></span>';
                title = 'Information';
        }
        
        // Create the toast element
        const $toast = $(`
            <div class="intellisend-toast ${type}">
                ${icon}
                <div class="toast-content">
                    <strong>${title}</strong>
                    ${message ? '<span>' + message + '</span>' : ''}
                </div>
            </div>
        `);
        
        return $toast;
    }
    
    /**
     * Show a toast notification
     * @public
     * @param {string} message - The message to display
     * @param {string} type - The type of toast (success, error, spam, not-spam)
     * @param {object} customOptions - Custom options for this toast
     */
    function show(message, type, customOptions) {
        // Initialize container
        _initContainer();
        
        // Merge default options with custom options
        const options = $.extend({}, DEFAULT_OPTIONS, customOptions);
        
        // Create toast element
        const $toast = _createToastElement(message, type, options);
        
        // Add to container
        $('#' + TOAST_CONTAINER_ID).append($toast);
        
        // Animate in
        setTimeout(function() {
            $toast.addClass('show');
        }, 10);
        
        // Auto dismiss after duration
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, options.animationDuration);
        }, options.duration);
    }
    
    /**
     * Show a success toast
     * @public
     * @param {string} message - The success message
     * @param {object} options - Custom options
     */
    function success(message, options) {
        show(message, 'success', options);
    }
    
    /**
     * Show an error toast
     * @public
     * @param {string} message - The error message
     * @param {object} options - Custom options
     */
    function error(message, options) {
        show(message, 'error', options);
    }
    
    /**
     * Show a spam detection toast
     * @public
     * @param {boolean} isSpam - Whether the message is spam
     * @param {object} options - Custom options
     */
    function spamResult(isSpam, options) {
        const type = isSpam ? 'spam' : 'not-spam';
        show('', type, options);
    }
    
    // Public API
    return {
        show: show,
        success: success,
        error: error,
        spamResult: spamResult
    };
    
})(jQuery);
