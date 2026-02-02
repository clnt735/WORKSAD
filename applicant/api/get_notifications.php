<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch notifications for applicant
$stmt = $conn->prepare("
    SELECT 
        notification_id,
        title,
        message,
        notification_type,
        related_id,
        is_read,
        created_at
    FROM notifications
    WHERE receiver_id = ? AND receiver_type = 'applicant'
    ORDER BY created_at DESC
    LIMIT 20
");

if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Determine navigation URL based on notification type
    $nav_url = '#';
    if ($row['notification_type'] === 'like') {
        $nav_url = 'interactions.php?tab=likes';
    } elseif ($row['notification_type'] === 'match') {
        $nav_url = 'interactions.php?tab=matches';
    } elseif ($row['notification_type'] === 'interview') {
        $nav_url = 'application.php?tab=interview';
    }
    
    $notifications[] = [
        'id' => $row['notification_id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['notification_type'],
        'related_id' => $row['related_id'],
        'is_read' => (int)$row['is_read'],
        'created_at' => $row['created_at'],
        'time_ago' => timeAgo($row['created_at']),
        'nav_url' => $nav_url
    ];
}

$stmt->close();

// Count unread notifications
$stmt2 = $conn->prepare("
    SELECT COUNT(*) as unread_count
    FROM notifications
    WHERE receiver_id = ? AND receiver_type = 'applicant' AND is_read = 0
");

$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$unread_row = $result2->fetch_assoc();
$unread_count = (int)$unread_row['unread_count'];
$stmt2->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>
