<?php
session_start();
require '../database.php';

header('Content-Type: application/json');

if (!isset($conn)) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['available' => false, 'message' => 'Email is required']);
        exit;
    }
    
    // Check if email exists in user table
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE user_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        echo json_encode([
            'available' => false, 
            'message' => 'This email is already used. Please use a different email.'
        ]);
    } else {
        echo json_encode(['available' => true]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
?>
