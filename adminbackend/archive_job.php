<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/log_admin_action.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobId = isset($input['jobId']) ? (int)$input['jobId'] : 0;

if ($jobId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid job ID.']);
    exit;
}

$stmt = $conn->prepare('UPDATE job_post SET job_status_id = 4 WHERE job_post_id = ?');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    exit;
}

$stmt->bind_param('i', $jobId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Job not found or already archived.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

log_admin_action($conn, $_SESSION['user_id'] ?? null, 'Archived job ID ' . $jobId);

$conn->close();

echo json_encode(['success' => true, 'message' => 'Job archived successfully.']);