<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/log_admin_action.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_with_message(string $status, string $message): void
{
    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $queryString = http_build_query(array_merge($params, [
        'flash_status'  => $status,
        'flash_message' => $message,
    ]));

    $location = '../admin/categories.php';
    if ($queryString !== '') {
        $location .= '?' . $queryString;
    }

    header('Location: ' . $location);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Invalid request method.');
}

if (!$conn) {
    redirect_with_message('error', 'Database connection error.');
}

$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;

if ($categoryId <= 0) {
    redirect_with_message('error', 'Invalid category selected.');
}

try {
    $detailStmt = $conn->prepare('SELECT job_category_name FROM job_category WHERE job_category_id = ? LIMIT 1');
    $detailStmt->bind_param('i', $categoryId);
    $detailStmt->execute();
    $detailStmt->store_result();
    if ($detailStmt->num_rows === 0) {
        $detailStmt->close();
        redirect_with_message('error', 'Category not found.');
    }
    $detailStmt->bind_result($categoryName);
    $detailStmt->fetch();
    $detailStmt->close();

    $deleteStmt = $conn->prepare('DELETE FROM job_category WHERE job_category_id = ?');
    $deleteStmt->bind_param('i', $categoryId);
    $deleteStmt->execute();
    $deleteStmt->close();

    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    log_admin_action($conn, $adminId, 'Deleted category #' . $categoryId . ' (' . $categoryName . ')');

    redirect_with_message('success', 'Category deleted successfully.');
} catch (Throwable $e) {
    redirect_with_message('error', 'Failed to delete category.');
}
