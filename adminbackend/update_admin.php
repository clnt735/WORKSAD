<?php
/**
 * Admin Management Backend Handler
 * Only accessible by Super Admin (user_type_id = 4)
 * Handles: edit, disable, enable actions for admin accounts (user_type_id = 1)
 */

session_start();
include '../database.php';
require_once __DIR__ . '/log_admin_action.php';

// Define role constants if not already defined
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 1);
}
if (!defined('ROLE_SUPER_ADMIN')) {
    define('ROLE_SUPER_ADMIN', 4);
}

// STATUS CONSTANTS
define('STATUS_PENDING', 0);
define('STATUS_ACTIVE', 1);
define('STATUS_DEACTIVATED', 2);
define('STATUS_BLOCKED', 3);

// Security check: Only Super Admin can access this
function isSuperAdminCheck(): bool {
    return isset($_SESSION['user_type_id']) && (int)$_SESSION['user_type_id'] === ROLE_SUPER_ADMIN;
}

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in'] || !isSuperAdminCheck()) {
    header('Location: ../admin/dashboard.php?error=access_denied');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/admin_management.php');
    exit();
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;

if ($userId <= 0) {
    header('Location: ../admin/admin_management.php?flash_status=error&flash_message=' . urlencode('Invalid user ID.'));
    exit();
}

// Verify the target user is an ADMIN (user_type_id = 1), not a Super Admin
$verifyStmt = $conn->prepare("SELECT user_id, user_email, user_type_id, user_status_id FROM user WHERE user_id = ?");
$verifyStmt->bind_param('i', $userId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    header('Location: ../admin/admin_management.php?flash_status=error&flash_message=' . urlencode('User not found.'));
    exit();
}

$targetUser = $verifyResult->fetch_assoc();
$verifyStmt->close();

// Prevent operations on non-admin accounts or Super Admin accounts
if ((int)$targetUser['user_type_id'] !== ROLE_ADMIN) {
    header('Location: ../admin/admin_management.php?flash_status=error&flash_message=' . urlencode('This operation is only allowed for Admin accounts.'));
    exit();
}

$redirectUrl = '../admin/admin_management.php';

switch ($action) {
    case 'edit':
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $status = isset($_POST['status']) ? intval($_POST['status']) : -1;

        if ($email === '') {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Email is required.'));
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Invalid email format.'));
            exit();
        }

        // Check if email already exists for another user
        $emailCheckStmt = $conn->prepare("SELECT user_id FROM user WHERE user_email = ? AND user_id != ?");
        $emailCheckStmt->bind_param('si', $email, $userId);
        $emailCheckStmt->execute();
        if ($emailCheckStmt->get_result()->num_rows > 0) {
            $emailCheckStmt->close();
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Email already in use by another account.'));
            exit();
        }
        $emailCheckStmt->close();

        // Build update query
        if ($password !== '') {
            // Update with new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE user SET user_email = ?, user_password = ?, user_status_id = ? WHERE user_id = ? AND user_type_id = ?");
            $adminType = ROLE_ADMIN;
            $updateStmt->bind_param('ssiii', $email, $hashedPassword, $status, $userId, $adminType);
        } else {
            // Update without password
            $updateStmt = $conn->prepare("UPDATE user SET user_email = ?, user_status_id = ? WHERE user_id = ? AND user_type_id = ?");
            $adminType = ROLE_ADMIN;
            $updateStmt->bind_param('siii', $email, $status, $userId, $adminType);
        }

        if ($updateStmt->execute()) {
            log_admin_action($conn, (int)$_SESSION['user_id'], "Updated admin account #$userId ($email)");
            header("Location: $redirectUrl?flash_status=success&flash_message=" . urlencode('Admin account updated successfully.'));
        } else {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Failed to update admin account.'));
        }
        $updateStmt->close();
        break;

    case 'disable':
        $disableStmt = $conn->prepare("UPDATE user SET user_status_id = ? WHERE user_id = ? AND user_type_id = ?");
        $deactivatedStatus = STATUS_DEACTIVATED;
        $adminType = ROLE_ADMIN;
        $disableStmt->bind_param('iii', $deactivatedStatus, $userId, $adminType);

        if ($disableStmt->execute() && $disableStmt->affected_rows > 0) {
            log_admin_action($conn, (int)$_SESSION['user_id'], "Disabled admin account #$userId ({$targetUser['user_email']})");
            header("Location: $redirectUrl?flash_status=success&flash_message=" . urlencode('Admin account disabled successfully.'));
        } else {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Failed to disable admin account.'));
        }
        $disableStmt->close();
        break;

    case 'enable':
        $enableStmt = $conn->prepare("UPDATE user SET user_status_id = ? WHERE user_id = ? AND user_type_id = ?");
        $activeStatus = STATUS_ACTIVE;
        $adminType = ROLE_ADMIN;
        $enableStmt->bind_param('iii', $activeStatus, $userId, $adminType);

        if ($enableStmt->execute() && $enableStmt->affected_rows > 0) {
            log_admin_action($conn, (int)$_SESSION['user_id'], "Enabled admin account #$userId ({$targetUser['user_email']})");
            header("Location: $redirectUrl?flash_status=success&flash_message=" . urlencode('Admin account enabled successfully.'));
        } else {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Failed to enable admin account.'));
        }
        $enableStmt->close();
        break;

    default:
        header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Invalid action.'));
        break;
}

$conn->close();
