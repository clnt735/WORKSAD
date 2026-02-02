
// Notification System - Applicant
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationWrapper = document.querySelector('.wm-notification-wrapper');
    const notificationClose = document.getElementById('notificationClose');
    const notificationList = document.getElementById('notificationList');

    if (!notificationBtn || !notificationWrapper) return;

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toggle dropdown
    function toggleDropdown() {
        notificationWrapper.classList.toggle('active');
        if (notificationWrapper.classList.contains('active')) {
            loadNotifications();
        }
    }

    // Close dropdown
    function closeDropdown() {
        notificationWrapper.classList.remove('active');
    }

    // Load notifications
    async function loadNotifications() {
        if (!notificationList) return;
        
        notificationList.innerHTML = '<div class="notification-loading">Loading...</div>';

        try {
            const response = await fetch('api/get_notifications.php');
            const data = await response.json();

            if (data.success) {
                displayNotifications(data.notifications);
                updateBadge(data.unread_count);
            } else {
                notificationList.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
            }
        } catch (error) {
            notificationList.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
        }
    }

    // Display notifications
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            notificationList.innerHTML = '<div class="notification-empty"><i class="fa-solid fa-bell-slash"></i><p>No notifications yet</p></div>';
            return;
        }

        notificationList.innerHTML = notifications.map(notif => {
            const iconClass = notif.type === 'like' ? 'fa-heart' : 
                             notif.type === 'match' ? 'fa-handshake' : 
                             'fa-calendar-check';
            
            return '<div class="notification-item ' + (!notif.is_read ? 'unread' : '') + '" data-id="' + notif.id + '" onclick="handleNotificationClick(' + notif.id + ')"><div class="notification-icon ' + notif.type + '"><i class="fa-solid ' + iconClass + '"></i></div><div class="notification-content"><p class="notification-title">' + escapeHtml(notif.title) + '</p><p class="notification-message">' + escapeHtml(notif.message) + '</p><p class="notification-time">' + escapeHtml(notif.time_ago) + '</p></div></div>';
        }).join('');
    }

    // Update badge
    function updateBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (!badge) return;
        
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Handle notification click
    window.handleNotificationClick = async function(notificationId) {
        try {
            await fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });
            loadNotifications();
        } catch (error) {
            console.error('Error:', error);
        }
    };

    // Event listeners
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });

    if (notificationClose) {
        notificationClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closeDropdown();
        });
    }

    document.addEventListener('click', function(e) {
        if (!notificationWrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    // Initial load and auto-refresh
    loadNotifications();
    setInterval(loadNotifications, 60000);
});
