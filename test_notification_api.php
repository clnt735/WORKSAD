<?php
// Test if the notification API is working for the current session
session_start();
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Notification API Test</h2>";
echo "<hr>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>❌ Not logged in. Session user_id not found.</p>";
    echo "<p>Please log in first, then return to this page.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? 'unknown';

echo "<p>✅ Logged in as User ID: <strong>$user_id</strong></p>";
echo "<p>User Type: <strong>$user_type</strong></p>";
echo "<hr>";

// Test applicant API
if ($user_type === 'applicant' || $user_type === 'Applicant') {
    echo "<h3>Testing Applicant API:</h3>";
    $url = "http://localhost/WORKSAD/applicant/api/get_notifications.php";
    echo "<p>Calling: <code>$url</code></p>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo "<h4>Response:</h4>";
    echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";
    $data = json_decode($response, true);
    print_r($data);
    echo "</pre>";
    
    if (isset($data['notifications']) && count($data['notifications']) > 0) {
        echo "<p style='color:green;'>✅ API working! Found " . count($data['notifications']) . " notifications</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ API returned but no notifications found for this user</p>";
    }
}

// Test employer API
if ($user_type === 'employer' || $user_type === 'Employer') {
    echo "<h3>Testing Employer API:</h3>";
    $url = "http://localhost/WORKSAD/employercontent/api/get_notifications.php";
    echo "<p>Calling: <code>$url</code></p>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo "<h4>Response:</h4>";
    echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";
    $data = json_decode($response, true);
    print_r($data);
    echo "</pre>";
    
    if (isset($data['notifications']) && count($data['notifications']) > 0) {
        echo "<p style='color:green;'>✅ API working! Found " . count($data['notifications']) . " notifications</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ API returned but no notifications found for this user</p>";
    }
}

echo "<hr>";
echo "<p><strong>Next Step:</strong> Check browser console (F12) for JavaScript errors when clicking the bell.</p>";
?>
