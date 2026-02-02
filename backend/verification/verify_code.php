<?php
require '../database.php';

$user_id = $_POST['user_id'];
$code = $_POST['code'];

// When verifying the code
$stmt = $conn->prepare("UPDATE user_verifications
                        SET is_verified = TRUE, verified_at = NOW()
                        WHERE user_id = ? AND verification_code = ?
                          AND is_verified = FALSE AND expires_at > NOW()");
$stmt->bind_param("is", $user_id, $code);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["verified" => true]);
} else {
    echo json_encode(["verified" => false]);
}
