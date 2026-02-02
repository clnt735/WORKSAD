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
    $industry_id = $_GET['industry_id'] ?? null;
    
    if (empty($industry_id)) {
        echo json_encode(["success" => false, "msg" => "Industry ID required"]);
        exit;
    }
    
    $categories = [];
    $cat_query = $conn->prepare("SELECT job_category_id, job_category_name FROM job_category WHERE industry_id = ? ORDER BY job_category_name ASC");
    $cat_query->bind_param("i", $industry_id);
    $cat_query->execute();
    $result = $cat_query->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    $cat_query->close();
    
    echo json_encode(["success" => true, "categories" => $categories]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
