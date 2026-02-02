<?php
require '../database.php';

$token = $_POST['token'];

// Delete session (logout)
$stmt = $conn->prepare("DELETE FROM sessions WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();

echo json_encode(["message" => "Logged out"]);
