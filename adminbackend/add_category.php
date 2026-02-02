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

$categoryName = isset($_POST['category_name']) ? trim($_POST['category_name']) : '';

if ($categoryName === '') {
    redirect_with_message('error', 'Category name is required.');
}

try {
    $duplicateStmt = $conn->prepare('SELECT job_category_id FROM job_category WHERE job_category_name = ? LIMIT 1');
    $duplicateStmt->bind_param('s', $categoryName);
    $duplicateStmt->execute();
    $duplicateStmt->store_result();
    if ($duplicateStmt->num_rows > 0) {
        $duplicateStmt->close();
        redirect_with_message('error', 'A category with that name already exists.');
    }
    $duplicateStmt->close();

    $insertStmt = $conn->prepare('INSERT INTO job_category (job_category_name, created_at) VALUES (?, NOW())');
    $insertStmt->bind_param('s', $categoryName);
    $insertStmt->execute();
    $newCategoryId = $conn->insert_id;
    $insertStmt->close();

    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    log_admin_action($conn, $adminId, 'Added category #' . $newCategoryId . ' (' . $categoryName . ')');

    redirect_with_message('success', 'Category added successfully.');
} catch (Throwable $e) {
    redirect_with_message('error', 'Failed to add category.');
}
