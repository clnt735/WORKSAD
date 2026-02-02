<?php
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../adminbackend/log_admin_action.php';

if (!empty($_SESSION['user_id'])) {
    log_admin_action($conn, (int)$_SESSION['user_id'], 'Logout: Signed out from admin portal');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

header('Location: login.php');
exit();
