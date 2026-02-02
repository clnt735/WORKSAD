<?php
// Start session to access user data early, but only when headers allow it.
// If headers were already sent by the including script, avoid calling
// session_start() to prevent the "headers already sent" warning.
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        // Headers have already been sent. We cannot start the session here
        // without causing a warning. To fix fully, call session_start()
        // at the very beginning of the request (before any output) in
        // the including pages (e.g. at the top of each entry PHP file).
    }
}

require_once '../database.php';

$user_first_name = null;

if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT user_profile_first_name
        FROM user_profile
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($first_name);
    if ($stmt->fetch()) {
        $user_first_name = trim($first_name);
    }
    $stmt->close();
}
?>


<link rel="stylesheet" href="../styles.css">

<!-- ======= Header ======= -->
<header class="wm-header">
    

     <!-- LOGO / BRAND -->
    <div class="wm-logo">
        <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
    </div>


    <!-- LEFT SIDE LINKS -->
    <nav class="wm-nav-left">
        <!-- <a href="home.php" class="<?= $activePage === 'home' ? 'active' : '' ?>">Home</a> -->
        <a href="search_jobs.php" class="<?= $activePage === 'job' ? 'active' : '' ?>"><span class="nav-label">Jobs</span></a>
        <a href="companies.php" class="<?= $activePage === 'companies' ? 'active' : '' ?>"><span class="nav-label">Companies</span></a>
        <a href="application.php" class="<?= $activePage === 'myapplications' ? 'active' : '' ?>"><span class="nav-label">My Applications</span></a>
        <a href="profile.php" class="<?= $activePage === 'profile' ? 'active' : '' ?>"><span class="nav-label">Profile</span></a>
    </nav>

    <!-- RIGHT SIDE -->
    <div class="wm-nav-right">

        <!-- SEARCH BAR -->
        <!-- <div class="wm-header-search">
            <i class="fa-solid fa-search"></i>
            <input type="text" placeholder="Search jobs...">
        </div> -->

        <!-- NOTIFICATION BELL -->
        <div class="wm-notification-wrapper">
            <button type="button" class="wm-notification-btn" id="notificationBtn" aria-label="Notifications" aria-expanded="false">
                <i class="fa-solid fa-bell"></i>
                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
            </button>
            <div class="wm-notification-dropdown" id="notificationDropdown" aria-hidden="true">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <button type="button" class="notification-close" id="notificationClose">&times;</button>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-loading">Loading...</div>
                </div>
            </div>
        </div>

        <!-- USER DROPDOWN -->
        <div class="wm-user-menu">
            <button class="wm-user-btn">
                <span class="user-circle">
                    <?= strtoupper(substr($user_first_name ?? 'U', 0, 1)) ?>
                </span>
                <span class="user-tooltip"><?= htmlspecialchars($user_first_name ?? 'User') ?></span>
                <span class="user-name"><?= htmlspecialchars($user_first_name ?? 'User') ?></span>
                <i class="fa-solid fa-chevron-down"></i>
            </button>



            <div class="wm-user-dropdown">
                <a href="interactions.php">Interactions</a>
                <a href="settings.php">Settings</a>
                 <hr class="dropdown-divider">
                <a href="#" id="logoutBtn" class="logout-link">Logout</a>
            </div>
        </div>

        <div id="logoutModal" class="logout-modal"> 
            <div class="logout-box">
                <h3 class="logout-title">Logout Confirmation</h3>
                <p class="logout-message">Are you sure you want to logout?</p>

                <div class="logout-actions">
                    <button id="cancelLogout" class="cancel-btn">Cancel</button>
                    <a href="logout.php" class="confirm-btn">Logout</a>
                </div>
            </div>
        </div>

    </div>

</header>

<style>
/* Notification Styles */
.wm-notification-wrapper {
    position: relative;
    margin-right: 12px;
    display: inline-block;
}

.wm-notification-btn {
    background: #f5f5f5;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    transition: background 0.2s;
    z-index: 10;
}

.wm-notification-btn:hover {
    background: #e5e5e5;
}

.wm-notification-btn i {
    font-size: 1.1rem;
    color: #333;
    pointer-events: none;
}

.notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #e74c3c;
    color: white;
    font-size: 0.65rem;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    padding: 0 4px;
}

.wm-notification-dropdown {
    position: fixed;
    top: 60px;
    right: 10px;
    width: 360px;
    max-width: 90vw;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s;
    z-index: 9999;
    max-height: 500px;
    display: flex;
    flex-direction: column;
}

.wm-notification-wrapper.active .wm-notification-dropdown {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) !important;
    pointer-events: auto !important;
}

.notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #111827;
}

.notification-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.notification-close:hover {
    background: #f3f4f6;
}

.notification-list {
    overflow-y: auto;
    max-height: 400px;
    padding: 8px 0;
}

.notification-list::-webkit-scrollbar {
    width: 6px;
}

.notification-list::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.notification-list::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.notification-item {
    padding: 12px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item.unread {
    background: #eff6ff;
}

.notification-item.unread:hover {
    background: #dbeafe;
}

.notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.notification-icon.like {
    background: #fce7f3;
    color: #ec4899;
}

.notification-icon.match {
    background: #dcfce7;
    color: #22c55e;
}

.notification-icon.interview {
    background: #dbeafe;
    color: #3b82f6;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #111827;
    margin: 0 0 4px 0;
}

.notification-message {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0 0 4px 0;
    line-height: 1.4;
}

.notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}

.notification-loading,
.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}

.notification-empty i {
    font-size: 2.5rem;
    color: #d1d5db;
    margin-bottom: 12px;
    display: block;
}

.notification-empty p {
    margin: 0;
}

@media (max-width: 600px) {
    .wm-notification-dropdown {
        width: 320px;
    }
}
</style>

<script>
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
        console.log('Toggle dropdown clicked');
        const dropdown = document.getElementById('notificationDropdown');
        const isActive = notificationWrapper.classList.contains('active');
        
        if (isActive) {
            // Close dropdown
            notificationWrapper.classList.remove('active');
            if (dropdown) {
                dropdown.style.opacity = '0';
                dropdown.style.visibility = 'hidden';
                dropdown.style.transform = 'translateY(-10px)';
            }
        } else {
            // Open dropdown
            notificationWrapper.classList.add('active');
            if (dropdown) {
                dropdown.style.opacity = '1';
                dropdown.style.visibility = 'visible';
                dropdown.style.transform = 'translateY(0)';
            }
            loadNotifications();
        }
        
        console.log('Active class:', notificationWrapper.classList.contains('active'));
        if (dropdown) {
            console.log('Dropdown visibility:', dropdown.style.visibility);
            console.log('Dropdown opacity:', dropdown.style.opacity);
        }
    }

    // Close dropdown
    function closeDropdown() {
        notificationWrapper.classList.remove('active');
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.style.opacity = '0';
            dropdown.style.visibility = 'hidden';
            dropdown.style.transform = 'translateY(-10px)';
        }
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
            const unreadClass = !notif.is_read ? 'unread' : '';
            
            // Simplified navigation: like/match -> interactions.php, interview -> application.php
            let navUrl = 'interactions.php';
            if (notif.type === 'interview') {
                navUrl = 'application.php';
            }
            
            return `
                <div class="notification-item ${unreadClass}" data-id="${notif.id}" data-url="${navUrl}" data-timestamp="${notif.created_at}">
                    <div class="notification-icon ${notif.type}">
                        <i class="fa-solid ${iconClass}"></i>
                    </div>
                    <div class="notification-content">
                        <p class="notification-title">${escapeHtml(notif.title)}</p>
                        <p class="notification-message">${escapeHtml(notif.message)}</p>
                        <p class="notification-time" data-timestamp="${notif.created_at}">${escapeHtml(notif.time_ago)}</p>
                    </div>
                </div>
            `;
        }).join('');
        
        // Add click handlers to notification items
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notifId = this.getAttribute('data-id');
                const navUrl = this.getAttribute('data-url');
                handleNotificationClick(notifId, navUrl);
            });
        });
        
        // Update times dynamically
        updateNotificationTimes();
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
    window.handleNotificationClick = async function(notificationId, navUrl) {
        try {
            await fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });
            loadNotifications();
            
            // Navigate if URL is provided
            if (navUrl && navUrl !== '#') {
                window.location.href = navUrl;
            }
        } catch (error) {
            console.error('Error:', error);
        }
    };

    // Update notification times dynamically
    function updateNotificationTimes() {
        const timeElements = document.querySelectorAll('.notification-time[data-timestamp]');
        timeElements.forEach(el => {
            const timestamp = el.getAttribute('data-timestamp');
            if (timestamp) {
                el.textContent = formatTimeAgo(timestamp);
            }
        });
    }

    function formatTimeAgo(datetime) {
        const time = new Date(datetime).getTime();
        const now = Date.now();
        const diff = Math.floor((now - time) / 1000);

        if (diff < 60) {
            return 'Just now';
        } else if (diff < 3600) {
            const mins = Math.floor(diff / 60);
            return mins + ' min' + (mins > 1 ? 's' : '') + ' ago';
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        } else if (diff < 604800) {
            const days = Math.floor(diff / 86400);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        } else {
            const date = new Date(time);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

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
    // Update notification times every 30 seconds
    setInterval(updateNotificationTimes, 30000);
});
</script>

<script>
const userBtn = document.querySelector(".wm-user-btn");
const userMenu = document.querySelector(".wm-user-menu");

userBtn.addEventListener("click", e => {
  userMenu.classList.toggle("active");
  e.stopPropagation();
});

document.addEventListener("click", () => {
  userMenu.classList.remove("active");
});




const logoutBtn = document.getElementById('logoutBtn');
const logoutModal = document.getElementById('logoutModal');
const cancelLogout = document.getElementById('cancelLogout');

logoutBtn.addEventListener('click', () => {
    logoutModal.style.display = 'flex';
});

cancelLogout.addEventListener('click', () => {
    logoutModal.style.display = 'none';
});

window.addEventListener('click', (e) => {
    if (e.target === logoutModal) {
        logoutModal.style.display = 'none';
    }
});

</script>

