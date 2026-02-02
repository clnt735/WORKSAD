<?php
require '../database.php';

$token = $_POST['token'];

// On page load (check if session is still valid)
$stmt = $conn->prepare("SELECT * FROM sessions WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // On every activity (reset last activity + sliding expiry)
    $update = $conn->prepare("UPDATE sessions
                              SET last_activity_at = NOW(),
                                  expires_at = CASE
                                      WHEN is_remember_me = TRUE THEN DATE_ADD(NOW(), INTERVAL 30 DAY)
                                      ELSE DATE_ADD(NOW(), INTERVAL 1 DAY)
                                  END
                              WHERE token = ?");
    $update->bind_param("s", $token);
    $update->execute();

    echo json_encode(["valid" => true, "user_id" => $row['user_id']]);
} else {
    echo json_encode(["valid" => false]);
}
