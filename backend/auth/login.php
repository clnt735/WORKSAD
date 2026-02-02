<?php
require '../database.php'; 

$user_id = $_POST['user_id']; 
$remember = isset($_POST['remember_me']); 
$token = bin2hex(random_bytes(32)); 

if ($remember) {
    // On login with "remember me" (30-day expiry)
    $stmt = $conn->prepare("INSERT INTO sessions (user_id, token, expires_at, is_remember_me)
                            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), TRUE)");
} else {
    // On login without "remember me" (1-day expiry)
    $stmt = $conn->prepare("INSERT INTO sessions (user_id, token, expires_at, is_remember_me)
                            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), FALSE)");
}

$stmt->bind_param("is", $user_id, $token);
$stmt->execute();

echo json_encode(["token" => $token]);
