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
     * @param {object} options - Configuration options
     */
    function _initContainer(options = {}) {
        // Set default position if not provided
        const position = options.position || DEFAULT_OPTIONS.position;
        
        // Create container if it doesn't exist
        if ($('#' + TOAST_CONTAINER_ID).length === 0) {
            $('body').append(`<div id="${TOAST_CONTAINER_ID}" class="${position}"></div>`);
            
            // Add container styles
            const css = `
                #${TOAST_CONTAINER_ID} {
                    position: fixed;
                    z-index: 9999;
                    display: flex;
                    flex-direction: column;
                    gap: 12px;
                    width: 360px;
                    max-width: 90vw;
                }
                
                /* Position variants */
                #${TOAST_CONTAINER_ID}.top-right {
                    top: 16px;
                    right: 16px;
                }
                
                #${TOAST_CONTAINER_ID}.top-left {
                    top: 16px;
                    left: 16px;
                }
                
                #${TOAST_CONTAINER_ID}.bottom-right {
                    bottom: 16px;
                    right: 16px;
                }
                
                #${TOAST_CONTAINER_ID}.bottom-left {
                    bottom: 16px;
                    left: 16px;
                }
                
                .intellisend-toast {
                    position: relative;
                    padding: 14px 16px;
                    border-radius: 8px;
                    background: #ffffff;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.1);
                    display: flex;
                    align-items: flex-start;
                    transform: translateY(16px);
                    opacity: 0;
                    transition: transform 0.25s cubic-bezier(0.2, 0, 0, 1), opacity 0.25s ease;
                    overflow: hidden;
                }
                
                .intellisend-toast.show {
                    transform: translateY(0);
                    opacity: 1;
                }
                
                .intellisend-toast::before {
                    content: '';
                    position: absolute;
                    left: 0;
                    top: 0;
                    height: 100%;
                    width: 4px;
                }
                
                .intellisend-toast.success {
                    background: #ffffff;
                    border-left: none;
                }
                
                .intellisend-toast.success::before {
                    background: #16a34a;
                }
                
                .intellisend-toast.spam {
                    background: #ffffff;
                    border-left: none;
                }
                
                .intellisend-toast.spam::before {
                    background: #dc2626;
                }
                
                .intellisend-toast.not-spam {
                    background: #ffffff;
                    border-left: none;
                }
                
                .intellisend-toast.not-spam::before {
                    background: #16a34a;
                }
                
                .intellisend-toast.error {
                    background: #ffffff;
                    border-left: none;
                }
                
                .intellisend-toast.error::before {
                    background: #dc2626;
                }
                
                .intellisend-toast .toast-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 24px;
                    width: 24px;
                    margin-right: 12px;
                    border-radius: 50%;
                    flex-shrink: 0;
                }
                
                .intellisend-toast.success .toast-icon {
                    background: rgba(22, 163, 74, 0.12);
                    color: #16a34a;
                }
                
                .intellisend-toast.spam .toast-icon {
                    background: rgba(220, 38, 38, 0.12);
                    color: #dc2626;
                }
                
                .intellisend-toast.not-spam .toast-icon {
                    background: rgba(22, 163, 74, 0.12);
                    color: #16a34a;
                }
                
                .intellisend-toast.error .toast-icon {
                    background: rgba(220, 38, 38, 0.12);
                    color: #dc2626;
                }
                
                .intellisend-toast .dashicons {
                    font-size: 16px;
                    line-height: 24px;
                    width: 16px;
                    height: 16px;
                }
                
                .intellisend-toast .toast-content {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                }
                
                .intellisend-toast .toast-title {
                    font-weight: 600;
                    font-size: 14px;
                    line-height: 1.3;
                    margin-bottom: 2px;
                    color: #111827;
                }
                
                .intellisend-toast .toast-message {
                    font-size: 13px;
                    color: #4b5563;
                    line-height: 1.4;
                }
                
                .intellisend-toast .toast-close {
                    cursor: pointer;
                    color: #9ca3af;
                    margin-left: 12px;
                    font-size: 16px;
                    line-height: 1;
                    opacity: 0.7;
                    transition: opacity 0.2s ease;
                    flex-shrink: 0;
                    align-self: flex-start;
                }
                
                .intellisend-toast .toast-close:hover {
                    opacity: 1;
                }
                
                /* Dark mode support */
                @media (prefers-color-scheme: dark) {
                    .intellisend-toast {
                        background: #1f2937;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2), 0 1px 3px rgba(0, 0, 0, 0.3);
                    }
                    
                    .intellisend-toast .toast-title {
                        color: #f3f4f6;
                    }
                    
                    .intellisend-toast .toast-message {
                        color: #d1d5db;
                    }
                    
                    .intellisend-toast .toast-close {
                        color: #9ca3af;
                    }
                    
                    .intellisend-toast.success .toast-icon {
                        background: rgba(22, 163, 74, 0.2);
                    }
                    
                    .intellisend-toast.spam .toast-icon {
                        background: rgba(220, 38, 38, 0.2);
                    }
                    
                    .intellisend-toast.not-spam .toast-icon {
                        background: rgba(22, 163, 74, 0.2);
                    }
                    
                    .intellisend-toast.error .toast-icon {
                        background: rgba(220, 38, 38, 0.2);
                    }
                }
            `;
            
            $('<style>').text(css).appendTo('head');
        }
        
        // Update position if container already exists
        if (options && options.position) {
            $('#' + TOAST_CONTAINER_ID).attr('class', position);
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
                <div class="toast-icon">${icon}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    ${message ? '<div class="toast-message">' + message + '</div>' : ''}
                </div>
                <div class="toast-close">&times;</div>
            </div>
        `);
        
        // Add close button functionality
        $toast.find('.toast-close').on('click', function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, options.animationDuration);
        });
        
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
        // Merge default options with custom options
        const options = $.extend({}, DEFAULT_OPTIONS, customOptions);
        
        // Initialize container with position
        _initContainer(options);
        
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
            // Only dismiss if still in DOM (user might have closed it)
            if ($toast.parent().length) {
                $toast.removeClass('show');
                setTimeout(function() {
                    $toast.remove();
                }, options.animationDuration);
            }
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