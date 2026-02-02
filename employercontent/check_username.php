<?php
session_start();
require '../database.php';

header('Content-Type: application/json');

if (!isset($conn)) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    
    if (empty($username)) {
        echo json_encode(['available' => false, 'message' => 'Username is required']);
        exit;
    }
    
    // Check if username exists in user_profile table
    $stmt = $conn->prepare("SELECT user_id FROM user_profile WHERE user_profile_username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode([
            'available' => false, 
            'message' => 'This username is already taken. Please choose a different username.'
        ]);
    } else {
        echo json_encode(['available' => true]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
