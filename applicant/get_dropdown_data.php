<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

try {
    // Get education levels
    $levels = [];
    $level_query = $conn->query("SELECT education_level_id, education_level_name FROM education_level ORDER BY education_level_name ASC");
    if ($level_query) {
        while ($row = $level_query->fetch_assoc()) {
            $levels[] = $row;
        }
    }
    
    // Get industries
    $industries = [];
    $industry_query = $conn->query("SELECT industry_id, industry_name FROM industry ORDER BY industry_name ASC");
    if ($industry_query) {
        while ($row = $industry_query->fetch_assoc()) {
            $industries[] = $row;
        }
    }
    
    echo json_encode([
        "success" => true, 
        "education_levels" => $levels,
        "industries" => $industries
    ]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
