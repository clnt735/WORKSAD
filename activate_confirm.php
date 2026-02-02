<?php
require 'database_live.php';

if (!isset($_POST['token'])) {
    exit("No activation token received.");
}

$token = $_POST['token'];

// Activate the account
$stmt = $conn->prepare("
    UPDATE user 
    SET user_status_id = 1, activation_token = NULL 
    WHERE activation_token = ?
");
$stmt->bind_param("s", $token);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "<h2>✅ Your account has been verified!</h2>";
    echo "<p><a href='login.php'>Click here to login</a></p>";
} else {
    echo "Invalid or expired activation token.";
}

$stmt->close();
$conn->close();
?>
