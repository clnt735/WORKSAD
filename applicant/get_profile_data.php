<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

$uid = $_SESSION['user_id'];

try {
    // Get social media links from user_profile
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(facebook, '') as facebook,
            COALESCE(linkedin, '') as linkedin
        FROM user_profile 
        WHERE user_id = ?
    ");
    
    if (!$stmt) {
        // Columns might not exist, return empty
        echo json_encode([
            "success" => true,
            "facebook" => "",
            "linkedin" => ""
        ]);
        exit;
    }
    
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode([
        "success" => true,
        "facebook" => $row['facebook'] ?? '',
        "linkedin" => $row['linkedin'] ?? ''
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => true,
        "facebook" => "",
        "linkedin" => ""
    ]);
}

$conn->close();
?>
