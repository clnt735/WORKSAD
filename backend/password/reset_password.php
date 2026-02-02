<?php
require '../database.php';

$token = $_POST['reset_token'];
$new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);

// Check if reset token is valid
$stmt = $conn->prepare("SELECT user_id FROM password_resets
                        WHERE reset_token = ? AND used_at IS NULL AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $user_id = $row['user_id'];

    // When user successfully resets password
    $update_user = $conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
    $update_user->bind_param("si", $new_password, $user_id);
    $update_user->execute();

    // Mark reset token as used
    $update_reset = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE reset_token = ?");
    $update_reset->bind_param("s", $token);
    $update_reset->execute();

    echo json_encode(["reset" => true]);
} else {
    echo json_encode(["reset" => false]);
}
