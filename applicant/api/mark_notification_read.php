<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;

if ($notification_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

// Update notification as read (verify ownership)
$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE notification_id = ? AND receiver_id = ? AND receiver_type = 'applicant'
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param("ii", $notification_id, $user_id);
$result = $stmt->execute();

if (!$result) {
    echo json_encode(['success' => false, 'error' => 'Failed to update notification']);
    exit;
}

$affected = $stmt->affected_rows;
$stmt->close();

echo json_encode([
    'success' => $affected > 0,
    'message' => $affected > 0 ? 'Notification marked as read' : 'Notification not found'
]);
?>
