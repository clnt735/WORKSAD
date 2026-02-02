<?php
// Direct test of employer notification API
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Direct Employer Notification Test</h2>";
echo "<hr>";

// Show session info
echo "<h3>Session Info:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>❌ NOT LOGGED IN</p>";
    echo "<p>Please log in as employer first.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<p>✅ User ID: <strong>$user_id</strong></p>";

// Include database
require_once '../database.php';

// Query notifications directly (same as API does)
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
    WHERE receiver_id = ? AND receiver_type = 'employer'
    ORDER BY created_at DESC
    LIMIT 20
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo "<h3>Query Results:</h3>";
echo "<p>Found <strong>" . count($notifications) . "</strong> notifications for employer user_id=$user_id</p>";

if (count($notifications) > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#007bff; color:white;'>";
    echo "<th>ID</th><th>Type</th><th>Title</th><th>Message</th><th>Read</th><th>Created</th>";
    echo "</tr>";
    
    foreach ($notifications as $n) {
        $readStyle = $n['is_read'] == 0 ? 'background:#ffe5e5;' : '';
        echo "<tr style='$readStyle'>";
        echo "<td>{$n['notification_id']}</td>";
        echo "<td><span style='padding:3px 8px; background:#28a745; color:white; border-radius:3px;'>{$n['notification_type']}</span></td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['message']}</td>";
        echo "<td>" . ($n['is_read'] == 0 ? '❌ Unread' : '✅ Read') . "</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color:orange;'>⚠️ No notifications found for this employer.</p>";
}

// Count unread
$stmt2 = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE receiver_id = ? AND receiver_type = 'employer' AND is_read = 0");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$result2 = $stmt2->get_result();
$unreadRow = $result2->fetch_assoc();

echo "<h3>Unread Count:</h3>";
echo "<p style='font-size:24px; font-weight:bold; color:#dc3545;'>" . $unreadRow['unread'] . "</p>";

echo "<hr>";
echo "<h3>🧪 Now Test API Endpoint:</h3>";
echo "<p>The API endpoint should return the same data as above.</p>";
echo "<button onclick='testAPI()' style='padding:10px 20px; background:#007bff; color:white; border:none; border-radius:5px; cursor:pointer;'>Test API Endpoint</button>";
echo "<div id='apiResult' style='margin-top:20px; padding:15px; background:#f5f5f5; border-radius:5px;'></div>";

echo "<script>
async function testAPI() {
    const resultDiv = document.getElementById('apiResult');
    resultDiv.innerHTML = 'Loading...';
    
    try {
        const response = await fetch('api/get_notifications.php');
        const data = await response.json();
        
        resultDiv.innerHTML = '<h4>API Response:</h4><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<p style=\"color:red;\">Error: ' + error.message + '</p>';
    }
}
</script>";

$conn->close();
?>
