/**
 * LMS Notifications JavaScript
 */

(function($) {
    'use strict';
    
    const LMSNotifications = {
        
        init: function() {
            this.bindEvents();
            this.loadNotifications();
            this.startPolling();
        },
        
        bindEvents: function() {
            // Notification bell click
            $(document).on('click', '.notification-bell', this.toggleNotifications.bind(this));
            
            // Mark notification as read
            $(document).on('click', '.notification-item', this.markAsRead.bind(this));
            
            // Mark all as read
            $(document).on('click', '.mark-all-read', this.markAllAsRead.bind(this));
            
            // Close notifications dropdown
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.notifications-container').length) {
                    $('.notifications-dropdown').hide();
                }
            });
        },
        
        toggleNotifications: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const $dropdown = $('.notifications-dropdown');
            
            if ($dropdown.is(':visible')) {
                $dropdown.hide();
            } else {
                this.loadNotifications();
                $dropdown.show();
            }
        },
        
        loadNotifications: function() {
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_get_notifications',
                nonce: lms_ajax.nonce
            }, (response) => {
                if (response.success) {
                    this.renderNotifications(response.data.notifications);
                    this.updateNotificationCount(response.data.unread_count);
                }
            });
        },
        
        renderNotifications: function(notifications) {
            const $container = $('.notifications-list');
            $container.empty();
            
            if (notifications.length === 0) {
                $container.html('<div class="no-notifications">No notifications</div>');
                return;
            }
            
            notifications.forEach(notification => {
                const $item = $(`
                    <div class="notification-item ${notification.is_read ? 'read' : 'unread'}" 
                         data-id="${notification.id}">
                        <div class="notification-content">
                            <h4>${notification.title}</h4>
                            <p>${notification.message}</p>
                            <span class="notification-time">${this.formatTime(notification.created_at)}</span>
                        </div>
                        <div class="notification-actions">
                            ${!notification.is_read ? '<button class="mark-read-btn">Mark as read</button>' : ''}
                        </div>
                    </div>
                `);
                
                $container.append($item);
            });
        },
        
        markAsRead: function(e) {
            const $item = $(e.currentTarget);
            const notificationId = $item.data('id');
            
            if ($item.hasClass('read')) {
                return;
            }
            
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_mark_notification_read',
                notification_id: notificationId,
                nonce: lms_ajax.nonce
            }, (response) => {
                if (response.success) {
                    $item.removeClass('unread').addClass('read');
                    $item.find('.mark-read-btn').remove();
                    this.updateNotificationCount();
                }
            });
        },
        
        markAllAsRead: function(e) {
            e.preventDefault();
            
            $.post(lms_ajax.ajaxurl, {
                action: 'lms_mark_all_notifications_read',
                nonce: lms_ajax.nonce
            }, (response) => {
                if (response.success) {
                    $('.notification-item').removeClass('unread').addClass('read');
                    $('.mark-read-btn').remove();
                    this.updateNotificationCount(0);
                }
            });
        },
        
        updateNotificationCount: function(count = null) {
            if (count === null) {
                // Fetch current count
                $.post(lms_ajax.ajaxurl, {
                    action: 'lms_get_notification_count',
                    nonce: lms_ajax.nonce
                }, (response) => {
                    if (response.success) {
                        this.setNotificationCount(response.data.count);
                    }
                });
            } else {
                this.setNotificationCount(count);
            }
        },
        
        setNotificationCount: function(count) {
            const $badge = $('.notification-badge');
            
            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count).show();
            } else {
                $badge.hide();
            }
        },
        
        startPolling: function() {
            // Poll for new notifications every 30 seconds
            setInterval(() => {
                this.updateNotificationCount();
            }, 30000);
        },
        
        formatTime: function(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(diff / 3600000);
            const days = Math.floor(diff / 86400000);
            
            if (minutes < 1) {
                return 'Just now';
            } else if (minutes < 60) {
                return `${minutes}m ago`;
            } else if (hours < 24) {
                return `${hours}h ago`;
            } else if (days < 7) {
                return `${days}d ago`;
            } else {
                return date.toLocaleDateString();
            }
        },
        
        showToast: function(message, type = 'info') {
            const $toast = $(`
                <div class="lms-toast toast-${type}">
                    <div class="toast-content">
                        <span class="toast-message">${message}</span>
                        <button class="toast-close">&times;</button>
                    </div>
                </div>
            `);
            
            $('body').append($toast);
            
            // Show toast
            setTimeout(() => {
                $toast.addClass('show');
            }, 100);
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                $toast.removeClass('show');
                setTimeout(() => {
                    $toast.remove();
                }, 300);
            }, 5000);
            
            // Manual close
            $toast.find('.toast-close').on('click', function() {
                $toast.removeClass('show');
                setTimeout(() => {
                    $toast.remove();
                }, 300);
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.notifications-container').length) {
            LMSNotifications.init();
        }
    });
    
    // Make globally available
    window.LMSNotifications = LMSNotifications;
    
})(jQuery);