<?php
// Debug applicant notifications
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Applicant Notification Debug</h2><hr>";

// Check session
echo "<h3>Session Info:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>❌ NOT LOGGED IN as applicant</p>";
    echo "<p>Please log in as applicant first, then come back to this page.</p>";
    exit;
}

$user_id = (int)$_SESSION['user_id'];
echo "<p>✅ Logged in as User ID: <strong>$user_id</strong></p>";

require_once '../database.php';

// Check what's in the database for THIS user
echo "<h3>Notifications for Applicant User $user_id:</h3>";

$stmt = $conn->prepare("
    SELECT * FROM notifications 
    WHERE receiver_id = ? AND receiver_type = 'applicant'
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo "<p>Found: <strong>" . count($notifications) . "</strong> notifications</p>";

if (count($notifications) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#007bff; color:white;'>";
    echo "<th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th>";
    echo "</tr>";
    
    foreach ($notifications as $n) {
        $style = $n['is_read'] == 0 ? 'background:#ffe5e5;' : '';
        echo "<tr style='$style'>";
        echo "<td>{$n['notification_id']}</td>";
        echo "<td>{$n['notification_type']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['message']}</td>";
        echo "<td>" . ($n['is_read'] == 0 ? '❌ Unread' : '✅ Read') . "</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>⚠️ No notifications found for this applicant in database.</p>";
}

// Check ALL applicant notifications
echo "<h3>All Applicant Notifications in Database:</h3>";
$allApplicant = $conn->query("SELECT * FROM notifications WHERE receiver_type = 'applicant' ORDER BY created_at DESC LIMIT 10");
echo "<p>Total applicant notifications: " . $allApplicant->num_rows . "</p>";

if ($allApplicant->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#28a745; color:white;'>";
    echo "<th>ID</th><th>Receiver ID</th><th>Type</th><th>Title</th><th>Read</th><th>Created</th>";
    echo "</tr>";
    
    while ($n = $allApplicant->fetch_assoc()) {
        $highlight = $n['receiver_id'] == $user_id ? 'background:#ffff99;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>{$n['notification_id']}</td>";
        echo "<td><strong>{$n['receiver_id']}</strong>" . ($n['receiver_id'] == $user_id ? ' (YOU)' : '') . "</td>";
        echo "<td>{$n['notification_type']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>" . ($n['is_read'] == 0 ? 'Unread' : 'Read') . "</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h3>Test API Endpoint:</h3>";
echo "<button onclick='testAPI()' style='padding:10px 20px; background:#007bff; color:white; border:none; border-radius:5px; cursor:pointer;'>Test Applicant API</button>";
echo "<div id='apiResult' style='margin-top:20px; padding:15px; background:#f5f5f5; border-radius:5px;'></div>";

echo "<script>
async function testAPI() {
    const result = document.getElementById('apiResult');
    result.innerHTML = 'Testing...';
    
    try {
        const response = await fetch('api/get_notifications.php');
        const data = await response.json();
        
        result.innerHTML = '<h4>API Response:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (error) {
        result.innerHTML = '<p style=\"color:red;\">Error: ' + error.message + '</p>';
    }
}
</script>";

$conn->close();
?>
