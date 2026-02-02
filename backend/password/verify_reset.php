<?php
require '../database.php';

$token = $_POST['reset_token'];

// When user clicks the reset link
$stmt = $conn->prepare("SELECT * FROM password_resets
                        WHERE reset_token = ? AND used_at IS NULL AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["valid" => true, "user_id" => $row['user_id']]);
} else {
    echo json_encode(["valid" => false]);
}
