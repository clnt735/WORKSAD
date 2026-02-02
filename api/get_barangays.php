<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database.php';

$city = isset($_GET['city_mun_id']) ? (int)$_GET['city_mun_id'] : 0;
if ($city <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $conn->prepare('SELECT barangay_id, barangay_name FROM barangay WHERE city_mun_id = ? ORDER BY barangay_name ASC');
    $stmt->bind_param('i', $city);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}

?>
