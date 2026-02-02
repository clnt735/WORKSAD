<?php
require '../database.php';

$user_id = $_POST['user_id'];
$code = bin2hex(random_bytes(4)); // random 8-char code
$type = $_POST['type']; // 'email' or 'phone'

// When a new user signs up (generate code)
$stmt = $conn->prepare("INSERT INTO user_verifications (user_id, verification_code, verification_type, expires_at)
                        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))");
$stmt->bind_param("iss", $user_id, $code, $type);
$stmt->execute();

// In real app, send $code via email/SMS
echo json_encode(["message" => "Verification code sent", "code" => $code]);
