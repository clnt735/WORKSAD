<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database.php';

try {
    $sql = "SELECT city_mun_id, city_mun_name FROM city_mun ORDER BY city_mun_name ASC";
    $res = $conn->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]);
}

?>
