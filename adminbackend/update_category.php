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
$categoryName = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';

if ($categoryId <= 0 || $categoryName === '') {
    redirect_with_message('error', 'Category name is required.');
}

try {
    // Ensure category exists
    $existsStmt = $conn->prepare('SELECT job_category_id FROM job_category WHERE job_category_id = ? LIMIT 1');
    $existsStmt->bind_param('i', $categoryId);
    $existsStmt->execute();
    $existsStmt->store_result();
    if ($existsStmt->num_rows === 0) {
        $existsStmt->close();
        redirect_with_message('error', 'Category not found.');
    }
    $existsStmt->close();

    // Prevent duplicate names
    $duplicateStmt = $conn->prepare('SELECT job_category_id FROM job_category WHERE job_category_name = ? AND job_category_id <> ? LIMIT 1');
    $duplicateStmt->bind_param('si', $categoryName, $categoryId);
    $duplicateStmt->execute();
    $duplicateStmt->store_result();
    if ($duplicateStmt->num_rows > 0) {
        $duplicateStmt->close();
        redirect_with_message('error', 'A category with that name already exists.');
    }
    $duplicateStmt->close();

    $updateStmt = $conn->prepare('UPDATE job_category SET job_category_name = ? WHERE job_category_id = ?');
    $updateStmt->bind_param('si', $categoryName, $categoryId);
    $updateStmt->execute();
    $updateStmt->close();

    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    log_admin_action($conn, $adminId, 'Updated category #' . $categoryId . ' (' . $categoryName . ')');

    redirect_with_message('success', 'Category updated successfully.');
} catch (Throwable $e) {
    redirect_with_message('error', 'Failed to update category.');
}
