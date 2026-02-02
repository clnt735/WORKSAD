<?php
header('Content-Type: application/json');
require_once 'database.php';

$action = $_GET['action'] ?? 'check';

if ($action === 'clear') {
    // Clear test notification only
    $conn->query("DELETE FROM notifications WHERE related_id = 999");
    echo json_encode(['success' => true, 'message' => 'Test notification cleared']);
    exit;
}

// Get notification statistics
$totalResult = $conn->query("SELECT COUNT(*) as count FROM notifications");
$totalRow = $totalResult->fetch_assoc();
$total = (int)$totalRow['count'];

$unreadResult = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
$unreadRow = $unreadResult->fetch_assoc();
$unread = (int)$unreadRow['count'];

// Get recent notifications
$notifications = [];
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20");
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode([
    'success' => true,
    'total' => $total,
    'unread' => $unread,
    'notifications' => $notifications
]);

$conn->close();
?>
