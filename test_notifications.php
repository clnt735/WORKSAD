<?php
// Test script to verify notification system
session_start();
require_once 'database.php';

echo "<h2>Notification System Diagnostic Test</h2>";
echo "<hr>";

// Test 1: Check if notifications table exists
echo "<h3>Test 1: Database Table Check</h3>";
$result = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($result->num_rows > 0) {
    echo "✅ 'notifications' table exists<br>";
} else {
    echo "❌ 'notifications' table NOT found<br>";
}

// Test 2: Check current notifications in database
echo "<h3>Test 2: Current Notifications in Database</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM notifications");
$row = $result->fetch_assoc();
echo "Total notifications in database: <strong>" . $row['count'] . "</strong><br>";

if ($row['count'] > 0) {
    echo "<br><strong>Recent notifications:</strong><br>";
    $recent = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    while ($notif = $recent->fetch_assoc()) {
        echo "- ID: {$notif['notification_id']} | Receiver: {$notif['receiver_id']} ({$notif['receiver_type']}) | Type: {$notif['notification_type']} | Read: {$notif['is_read']} | Created: {$notif['created_at']}<br>";
    }
}

// Test 3: Test creating a like notification manually
echo "<h3>Test 3: Manual Notification Creation Test</h3>";
require_once 'backend/create_like_notification.php';

// Test with dummy data (receiver_id=10, swipe_id=999, receiver_type='applicant')
$testReceiverId = 10;
$testSwipeId = 999;
$testReceiverType = 'applicant';

echo "Attempting to create test notification...<br>";
$result = createLikeNotification($conn, $testSwipeId, $testReceiverId, $testReceiverType);

if ($result) {
    echo "✅ Test notification created successfully!<br>";
    
    // Verify it was inserted
    $verify = $conn->query("SELECT * FROM notifications WHERE receiver_id = $testReceiverId AND related_id = $testSwipeId ORDER BY created_at DESC LIMIT 1");
    if ($verify->num_rows > 0) {
        $notif = $verify->fetch_assoc();
        echo "✅ Verified in database:<br>";
        echo "&nbsp;&nbsp;- Title: {$notif['title']}<br>";
        echo "&nbsp;&nbsp;- Message: {$notif['message']}<br>";
        echo "&nbsp;&nbsp;- Type: {$notif['notification_type']}<br>";
        echo "&nbsp;&nbsp;- Receiver: {$notif['receiver_id']} ({$notif['receiver_type']})<br>";
    }
} else {
    echo "❌ Failed to create test notification<br>";
}

// Test 4: Check recent swipes
echo "<h3>Test 4: Recent Swipe Activity</h3>";
$swipes = $conn->query("SELECT * FROM applicant_job_swipes WHERE swipe_type='like' ORDER BY created_at DESC LIMIT 3");
echo "Recent applicant likes: " . $swipes->num_rows . "<br>";
while ($swipe = $swipes->fetch_assoc()) {
    echo "- Swipe ID: {$swipe['swipe_id']} | Applicant: {$swipe['applicant_id']} | Job: {$swipe['job_post_id']} | Date: {$swipe['created_at']}<br>";
}

// Test 5: Check PHP error log
echo "<h3>Test 5: Check for PHP Errors</h3>";
$errorLog = ini_get('error_log');
echo "PHP error log location: " . ($errorLog ? $errorLog : "Default location") . "<br>";
echo "Check your PHP error log for any notification-related errors.<br>";

echo "<hr>";
echo "<h3>Diagnostic Complete</h3>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If Test 3 succeeded, the notification functions work correctly</li>";
echo "<li>If notifications count is 0, the trigger functions are not being called during swipes</li>";
echo "<li>Check browser console for JavaScript errors when clicking notification bell</li>";
echo "<li>Check Network tab in browser DevTools when fetching notifications</li>";
echo "</ul>";

$conn->close();
?>
