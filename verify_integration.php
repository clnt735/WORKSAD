<?php
// Clean up test notification and verify integration
session_start();
require_once 'database.php';

echo "<h2>Notification Integration Verification</h2>";
echo "<hr>";

// Clean up test notification
echo "<h3>Step 1: Cleanup Test Data</h3>";
$conn->query("DELETE FROM notifications WHERE related_id = 999");
echo "✅ Test notification removed<br>";

// Verify current state
$result = $conn->query("SELECT COUNT(*) as count FROM notifications");
$row = $result->fetch_assoc();
echo "Current notification count: <strong>" . $row['count'] . "</strong><br><br>";

// Check integration files
echo "<h3>Step 2: Verify Integration Files</h3>";

$files = [
    'applicant/interactions.php' => '../backend/create_like_notification.php',
    'employercontent/swipe_applicant.php' => '../backend/create_like_notification.php',
    'employercontent/schedule_interview.php' => '../backend/create_interview_notification.php'
];

foreach ($files as $file => $trigger) {
    $content = file_get_contents($file);
    $triggerName = basename($trigger);
    if (strpos($content, $triggerName) !== false) {
        echo "✅ <strong>$file</strong> - Integration found<br>";
    } else {
        echo "❌ <strong>$file</strong> - Integration MISSING<br>";
    }
}

echo "<hr>";
echo "<h3>✨ NEXT STEPS TO TEST:</h3>";
echo "<ol>";
echo "<li><strong>Test Like Notification (Applicant):</strong>";
echo "<ul>";
echo "<li>Log in as an applicant</li>";
echo "<li>Go to job search/swipe page</li>";
echo "<li>Like a NEW job post</li>";
echo "<li>Log in as that employer</li>";
echo "<li>Click notification bell - you should see a 'like' notification</li>";
echo "</ul></li>";

echo "<li><strong>Test Like Notification (Employer):</strong>";
echo "<ul>";
echo "<li>Log in as an employer</li>";
echo "<li>Go to find talent/swipe page</li>";
echo "<li>Like a NEW applicant</li>";
echo "<li>Log in as that applicant</li>";
echo "<li>Click notification bell - you should see a 'like' notification</li>";
echo "</ul></li>";

echo "<li><strong>Test Match Notification:</strong>";
echo "<ul>";
echo "<li>Have applicant like a job</li>";
echo "<li>Have employer like that same applicant for that job</li>";
echo "<li>Both should receive 'It's a match!' notification</li>";
echo "</ul></li>";

echo "<li><strong>After testing, check notifications:</strong>";
echo "<ul>";
echo "<li>Refresh this page to see notification count increase</li>";
echo "<li>Or run: <code>SELECT * FROM notifications ORDER BY created_at DESC;</code></li>";
echo "</ul></li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='test_notifications.php' style='padding:10px 20px; background:#007bff; color:white; text-decoration:none; border-radius:5px;'>← Back to Diagnostics</a></p>";
echo "<p><a href='#' onclick='location.reload()' style='padding:10px 20px; background:#28a745; color:white; text-decoration:none; border-radius:5px;'>🔄 Refresh Check</a></p>";

$conn->close();
?>
