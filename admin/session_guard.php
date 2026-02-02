<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Role constants for access control
define('ROLE_ADMIN', 1);
define('ROLE_SUPER_ADMIN', 4);

if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has super admin access (user_type_id = 4)
// Regular admin (user_type_id = 1) is reserved for future limited features
$userTypeId = isset($_SESSION['user_type_id']) ? (int)$_SESSION['user_type_id'] : 0;

if ($userTypeId !== ROLE_SUPER_ADMIN) {
    // Only SUPER ADMIN (user_type_id = 4) can access admin panel
    // ADMIN (user_type_id = 1) is reserved for future use
    session_destroy();
    header('Location: login.php?error=access_denied');
    exit();
}

/**
 * Check if current user is a Super Admin
 * @return bool
 */
function isSuperAdmin(): bool {
    return isset($_SESSION['user_type_id']) && (int)$_SESSION['user_type_id'] === ROLE_SUPER_ADMIN;
}

/**
 * Check if current user is a regular Admin
 * @return bool
 */
function isAdmin(): bool {
    return isset($_SESSION['user_type_id']) && (int)$_SESSION['user_type_id'] === ROLE_ADMIN;
}

/**
 * Get the current user's role name
 * @return string
 */
function getUserRoleName(): string {
    if (isSuperAdmin()) {
        return 'Super Admin';
    } elseif (isAdmin()) {
        return 'Admin';
    }
    return 'Unknown';
}
