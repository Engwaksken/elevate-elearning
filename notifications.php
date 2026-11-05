<!-- ============================================================================
     NOTIFICATION CENTER UI COMPONENT
     
     This component provides a complete notification interface:
     - Dropdown notification bell with badge
     - Notification list with filtering
     - Mark as read functionality
     - Delete/archive options
     - Real-time updates
     
     Include this in your main layout/header file
     ============================================================================ -->

<style>
/* Notification Bell Icon */
.notification-bell {
    position: relative;
    display: inline-block;
    cursor: pointer;
    padding: 10px;
}

.notification-bell i {
    font-size: 20px;
    color: #676a6c;
}

.notification-badge {
    position: absolute;
    top: 5px;
    right: 5px;
    background-color: #ed5565;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 11px;
    font-weight: bold;
    min-width: 18px;
    text-align: center;
}

/* Notification Dropdown */
.notification-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    width: 400px;
    max-width: 90vw;
    background: white;
    border-radius: 4px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.2);
    z-index: 1000;
    display: none;
    margin-top: 10px;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 15px;
    border-bottom: 1px solid #e7eaec;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.notification-actions {
    display: flex;
    gap: 10px;
}

.notification-actions button {
    font-size: 12px;
    padding: 4px 8px;
}

/* Notification Filters */
.notification-filters {
    padding: 10px 15px;
    border-bottom: 1px solid #e7eaec;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 4px 10px;
    border: 1px solid #e7eaec;
    background: white;
    border-radius: 3px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-btn:hover {
    background: #f3f3f4;
}

.filter-btn.active {
    background: #1ab394;
    color: white;
    border-color: #1ab394;
}

.filter-count {
    background: #f3f3f4;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    margin-left: 4px;
}

/* Notification List */
.notification-list {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 15px;
    border-bottom: 1px solid #e7eaec;
    cursor: pointer;
    transition: background 0.2s;
    position: relative;
}

.notification-item:hover {
    background: #f9f9f9;
}

.notification-item.unread {
    background: #f3f9ff;
    border-left: 3px solid #1c84c6;
}

.notification-item.unread:hover {
    background: #e8f4ff;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    float: left;
    margin-right: 12px;
    font-size: 18px;
    color: white;
}

.notification-content {
    overflow: hidden;
}

.notification-title {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    color: #333;
}

.notification-message {
    font-size: 13px;
    color: #676a6c;
    margin-bottom: 6px;
    line-height: 1.4;
}

.notification-meta {
    font-size: 11px;
    color: #999;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-time {
    display: flex;
    align-items: center;
    gap: 4px;
}

.notification-item-actions {
    display: flex;
    gap: 8px;
}

.notification-item-actions button {
    font-size: 11px;
    padding: 2px 8px;
}

/* Empty State */
.notification-empty {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.notification-empty i {
    font-size: 48px;
    margin-bottom: 10px;
    color: #ddd;
}

/* Loading State */
.notification-loading {
    text-align: center;
    padding: 20px;
}

.notification-footer {
    padding: 10px 15px;
    text-align: center;
    border-top: 1px solid #e7eaec;
}

.notification-footer a {
    color: #1ab394;
    font-size: 13px;
    text-decoration: none;
}

.notification-footer a:hover {
    text-decoration: underline;
}

/* Priority Colors */
.priority-low { background-color: #95a5a6; }
.priority-normal { background-color: #3498db; }
.priority-high { background-color: #f39c12; }
.priority-urgent { background-color: #e74c3c; }

/* Type Icons Colors */
.type-comment_reply { background-color: #1c84c6; }
.type-new_assignment { background-color: #ed5565; }
.type-new_note { background-color: #f8ac59; }
.type-graded { background-color: #1ab394; }
.type-enrollment { background-color: #23c6c8; }
.type-announcement { background-color: #9b59b6; }
</style>

<!-- Notification Bell (Place in your header/navbar) -->
<div class="notification-bell" id="notificationBell">
    <i class="fa fa-bell"></i>
    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    
    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <!-- Header -->
        <div class="notification-header">
            <h4>Notifications</h4>
            <div class="notification-actions">
                <button class="btn btn-xs btn-primary" onclick="markAllAsRead()">
                    <i class="fa fa-check"></i> Mark all read
                </button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="notification-filters">
            <button class="filter-btn active" data-filter="all" onclick="filterNotifications('all')">
                All <span class="filter-count" id="count-all">0</span>
            </button>
            <button class="filter-btn" data-filter="comment_reply" onclick="filterNotifications('comment_reply')">
                <i class="fa fa-comment"></i> Replies <span class="filter-count" id="count-comment_reply">0</span>
            </button>
            <button class="filter-btn" data-filter="new_assignment" onclick="filterNotifications('new_assignment')">
                <i class="fa fa-tasks"></i> Assignments <span class="filter-count" id="count-new_assignment">0</span>
            </button>
            <button class="filter-btn" data-filter="new_note" onclick="filterNotifications('new_note')">
                <i class="fa fa-file-text"></i> Notes <span class="filter-count" id="count-new_note">0</span>
            </button>
            <button class="filter-btn" data-filter="graded" onclick="filterNotifications('graded')">
                <i class="fa fa-check-circle"></i> Graded <span class="filter-count" id="count-graded">0</span>
            </button>
        </div>
        
        <!-- Notification List -->
        <div class="notification-list" id="notificationList">
            <div class="notification-loading">
                <i class="fa fa-spinner fa-spin"></i> Loading notifications...
            </div>
        </div>
        
        <!-- Footer -->
        <div class="notification-footer">
            <a href="?view=all_notifications">View all notifications</a>
        </div>
    </div>
</div>

<script>
/* ============================================================================
   NOTIFICATION CENTER JAVASCRIPT
   ============================================================================ */

// Global variables
let currentFilter = 'all';
let allNotifications = [];
let unreadCounts = {};

/**
 * Initialize notification center on page load
 */
$(document).ready(function() {
    // Load initial notifications
    loadNotifications();
    
    // Toggle dropdown on bell click
    $('#notificationBell').click(function(e) {
        e.stopPropagation();
        $('#notificationDropdown').toggleClass('show');
    });
    
    // Close dropdown when clicking outside
    $(document).click(function(e) {
        if (!$(e.target).closest('.notification-bell').length) {
            $('#notificationDropdown').removeClass('show');
        }
    });
    
    // Refresh notifications every 60 seconds
    setInterval(loadNotifications, 60000);
});

/**
 * Load notifications from server
 */
function loadNotifications() {
    $.ajax({
        url: 'scripts/get_notifications.php',
        method: 'GET',
        data: {
            limit: 50,
            offset: 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                allNotifications = response.notifications;
                unreadCounts = response.unread_counts;
                
                // Update badge
                updateBadge(unreadCounts.total);
                
                // Update filter counts
                updateFilterCounts();
                
                // Display notifications
                displayNotifications();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading notifications:', error);
            $('#notificationList').html(`
                <div class="notification-empty">
                    <i class="fa fa-exclamation-triangle"></i>
                    <p>Failed to load notifications</p>
                </div>
            `);
        }
    });
}

/**
 * Update notification badge
 */
function updateBadge(count) {
    const badge = $('#notificationBadge');
    
    if (count > 0) {
        badge.text(count > 99 ? '99+' : count);
        badge.show();
    } else {
        badge.hide();
    }
}

/**
 * Update filter button counts
 */
function updateFilterCounts() {
    // Total count
    $('#count-all').text(allNotifications.length);
    
    // Unread counts by type
    for (let type in unreadCounts.by_type) {
        $('#count-' + type).text(unreadCounts.by_type[type]);
    }
}

/**
 * Filter notifications by type
 */
function filterNotifications(type) {
    currentFilter = type;
    
    // Update active filter button
    $('.filter-btn').removeClass('active');
    $('.filter-btn[data-filter="' + type + '"]').addClass('active');
    
    // Display filtered notifications
    displayNotifications();
}

/**
 * Display notifications in the list
 */
function displayNotifications() {
    const list = $('#notificationList');
    
    // Filter notifications
    let filtered = allNotifications;
    if (currentFilter !== 'all') {
        filtered = allNotifications.filter(n => n.type === currentFilter);
    }
    
    // Check if empty
    if (filtered.length === 0) {
        list.html(`
            <div class="notification-empty">
                <i class="fa fa-bell-slash"></i>
                <p>No notifications</p>
            </div>
        `);
        return;
    }
    
    // Build HTML
    let html = '';
    
    filtered.forEach(function(notification) {
        const unreadClass = notification.is_read ? '' : 'unread';
        const iconClass = 'type-' + notification.type;
        
        html += `
            <div class="notification-item ${unreadClass}" data-id="${notification.id}">
                <div class="notification-icon ${iconClass}">
                    <i class="fa ${notification.icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-meta">
                        <div class="notification-time">
                            <i class="fa fa-clock-o"></i> ${notification.time_ago}
                        </div>
                        <div class="notification-item-actions">
                            ${!notification.is_read ? '<button class="btn btn-xs btn-default" onclick="markAsRead(' + notification.id + ', event)"><i class="fa fa-check"></i></button>' : ''}
                            <button class="btn btn-xs btn-default" onclick="deleteNotification(' + notification.id + ', event)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    list.html(html);
    
    // Add click handlers for navigation
    $('.notification-item').click(function(e) {
        if ($(e.target).closest('button').length) return; // Ignore if clicking action button
        
        const notificationId = $(this).data('id');
        const notification = allNotifications.find(n => n.id === notificationId);
        
        if (notification && notification.action_url) {
            // Mark as read
            if (!notification.is_read) {
                markAsRead(notificationId);
            }
            
            // Navigate to URL
            window.location.href = notification.action_url;
        }
    });
}

/**
 * Mark single notification as read
 */
function markAsRead(notificationId, event) {
    if (event) event.stopPropagation();
    
    $.ajax({
        url: 'scripts/mark_notification_read.php',
        method: 'POST',
        data: {
            notification_id: notificationId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update local data
                const notification = allNotifications.find(n => n.id === notificationId);
                if (notification) {
                    notification.is_read = true;
                }
                
                // Refresh display
                loadNotifications();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error marking notification as read:', error);
        }
    });
}

/**
 * Mark all notifications as read
 */
function markAllAsRead() {
    $.ajax({
        url: 'scripts/mark_notification_read.php',
        method: 'POST',
        data: {
            mark_all: 'true'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh notifications
                loadNotifications();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error marking all as read:', error);
        }
    });
}

/**
 * Delete notification
 */
function deleteNotification(notificationId, event) {
    if (event) event.stopPropagation();
    
    if (!confirm('Delete this notification?')) return;
    
    $.ajax({
        url: 'scripts/manage_notification.php',
        method: 'POST',
        data: {
            notification_id: notificationId,
            action: 'delete'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Remove from local data
                allNotifications = allNotifications.filter(n => n.id !== notificationId);
                
                // Refresh display
                displayNotifications();
                updateBadge(allNotifications.filter(n => !n.is_read).length);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error deleting notification:', error);
        }
    });
}
</script>