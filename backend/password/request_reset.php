<?php
require '../database.php';

$user_id = $_POST['user_id'];
$token = bin2hex(random_bytes(32));

// When user requests “Forgot password”
$stmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at)
                        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
$stmt->bind_param("is", $user_id, $token);
$stmt->execute();

// In real app, send $token via email
echo json_encode(["message" => "Password reset requested", "reset_token" => $token]);
