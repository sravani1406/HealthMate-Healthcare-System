<!-- Notification Widget - Include this in your dashboard header -->
<style>
.notification-widget {
    position: relative;
    display: inline-block;
}

.notification-bell {
    position: relative;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 24px;
    color: #333;
    padding: 8px;
    transition: all 0.3s;
}

.notification-bell:hover {
    color: #28a745;
    transform: scale(1.1);
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    width: 380px;
    max-height: 500px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    margin-top: 10px;
}

.notification-dropdown.show {
    display: block;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notification-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h3 {
    margin: 0;
    font-size: 16px;
    color: #333;
}

.mark-all-read {
    background: none;
    border: none;
    color: #28a745;
    font-size: 13px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background 0.3s;
}

.mark-all-read:hover {
    background: #e9ecef;
}

.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f8f9fa;
    cursor: pointer;
    transition: background 0.3s;
    position: relative;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #e7f3ff;
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background: #28a745;
    border-radius: 50%;
}

.notification-title {
    font-weight: 600;
    color: #333;
    font-size: 14px;
    margin-bottom: 4px;
}

.notification-message {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 6px;
}

.notification-time {
    color: #adb5bd;
    font-size: 12px;
}

.notification-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
    margin-right: 8px;
}

.type-appointment { background: #d1ecf1; color: #0c5460; }
.type-medication { background: #d4edda; color: #155724; }
.type-checkup { background: #fff3cd; color: #856404; }
.type-alert { background: #f8d7da; color: #721c24; }
.type-system { background: #e2e3e5; color: #383d41; }

.priority-urgent {
    border-left: 4px solid #dc3545;
}

.priority-high {
    border-left: 4px solid #ffc107;
}

.empty-notifications {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-notifications svg {
    width: 60px;
    height: 60px;
    opacity: 0.3;
    margin-bottom: 10px;
}

.delete-notification {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: #dc3545;
    cursor: pointer;
    font-size: 16px;
    padding: 4px;
    opacity: 0;
    transition: opacity 0.3s;
}

.notification-item:hover .delete-notification {
    opacity: 1;
}

.delete-notification:hover {
    color: #c82333;
}

.notification-actions {
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

.view-all-link {
    color: #28a745;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.view-all-link:hover {
    text-decoration: underline;
}

/* Loading animation */
.notification-loading {
    text-align: center;
    padding: 20px;
    color: #6c757d;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #e9ecef;
    border-top-color: #28a745;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}
</style>

<div class="notification-widget">
    <button class="notification-bell" id="notificationBell" title="Notifications">
        🔔
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    </button>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all-read" id="markAllRead">Mark all as read</button>
        </div>
        
        <div class="notification-list" id="notificationList">
            <div class="notification-loading">
                <div class="spinner"></div>
                <p>Loading notifications...</p>
            </div>
        </div>
    </div>
</div>

<script>
// Notification System
const NotificationSystem = {
    interval: null,
    isOpen: false,
    
    init() {
        this.setupEventListeners();
        this.fetchNotifications();
        this.startPolling();
    },
    
    setupEventListeners() {
        // Toggle dropdown
        document.getElementById('notificationBell').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        // Mark all as read
        document.getElementById('markAllRead').addEventListener('click', () => {
            this.markAllAsRead();
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.getElementById('notificationBell');
            
            if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                this.closeDropdown();
            }
        });
    },
    
    toggleDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            dropdown.classList.add('show');
            this.fetchNotifications();
        } else {
            dropdown.classList.remove('show');
        }
    },
    
    closeDropdown() {
        document.getElementById('notificationDropdown').classList.remove('show');
        this.isOpen = false;
    },
    
    async fetchNotifications() {
        try {
            const response = await fetch('../includes/notification_handler.php?action=get_notifications');
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.unread_count);
                this.renderNotifications(data.notifications);
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    },
    
    updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    },
    
    renderNotifications(notifications) {
        const list = document.getElementById('notificationList');
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="empty-notifications">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }
        
        list.innerHTML = notifications.map(notif => this.createNotificationHTML(notif)).join('');
        
        // Add click event listeners
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('delete-notification')) {
                    this.markAsRead(item.dataset.id, item.dataset.url);
                }
            });
        });
        
        // Add delete button listeners
        list.querySelectorAll('.delete-notification').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteNotification(btn.dataset.id);
            });
        });
    },
    
    createNotificationHTML(notif) {
        const isUnread = notif.is_read == 0 ? 'unread' : '';
        const priorityClass = notif.priority === 'urgent' || notif.priority === 'high' ? `priority-${notif.priority}` : '';
        const timeAgo = this.getTimeAgo(notif.created_at);
        
        return `
            <div class="notification-item ${isUnread} ${priorityClass}" data-id="${notif.id}" data-url="${notif.action_url || ''}">
                <button class="delete-notification" data-id="${notif.id}" title="Delete">×</button>
                <div class="notification-title">
                    <span class="notification-type-badge type-${notif.type}">${notif.type}</span>
                    ${this.escapeHtml(notif.title)}
                </div>
                <div class="notification-message">${this.escapeHtml(notif.message)}</div>
                <div class="notification-time">${timeAgo}</div>
            </div>
        `;
    },
    
    async markAsRead(notificationId, actionUrl) {
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            await fetch('../includes/notification_handler.php?action=mark_as_read', {
                method: 'POST',
                body: formData
            });
            
            // Refresh notifications
            await this.fetchNotifications();
            
            // Navigate to action URL if exists
            if (actionUrl && actionUrl !== 'null' && actionUrl !== '') {
                window.location.href = actionUrl;
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    },
    
    async markAllAsRead() {
        try {
            await fetch('../includes/notification_handler.php?action=mark_all_as_read', {
                method: 'POST'
            });
            
            await this.fetchNotifications();
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    },
    
    async deleteNotification(notificationId) {
        if (!confirm('Delete this notification?')) return;
        
        try {
            const formData = new FormData();
            formData.append('notification_id', notificationId);
            
            await fetch('../includes/notification_handler.php?action=delete_notification', {
                method: 'POST',
                body: formData
            });
            
            await this.fetchNotifications();
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    },
    
    startPolling() {
        // Poll every 30 seconds
        this.interval = setInterval(() => {
            this.fetchNotifications();
        }, 30000);
    },
    
    stopPolling() {
        if (this.interval) {
            clearInterval(this.interval);
        }
    },
    
    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        
        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
        
        return date.toLocaleDateString();
    },
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => NotificationSystem.init());
} else {
    NotificationSystem.init();
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    NotificationSystem.stopPolling();
});
</script>