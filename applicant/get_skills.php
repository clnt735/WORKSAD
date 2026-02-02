<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

try {
    $category_id = $_GET['category_id'] ?? null;
    
    if (empty($category_id)) {
        echo json_encode(["success" => false, "msg" => "Category ID required"]);
        exit;
    }
    
    $skills = [];
    $skill_query = $conn->prepare("SELECT skill_id, name AS skill_name FROM skills WHERE job_category_id = ? ORDER BY name ASC");
    $skill_query->bind_param("i", $category_id);
    $skill_query->execute();
    $result = $skill_query->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }
    
    $skill_query->close();
    
    echo json_encode(["success" => true, "skills" => $skills]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
