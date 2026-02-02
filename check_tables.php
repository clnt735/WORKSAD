<?php
require 'database.php';
echo "=== city_mun ===\n";
$r = $conn->query('DESCRIBE city_mun');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n=== barangay ===\n";
$r = $conn->query('DESCRIBE barangay');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "\n=== Sample city_mun ===\n";
$r = $conn->query('SELECT * FROM city_mun LIMIT 5');
while($row = $r->fetch_assoc()) {
    print_r($row);
}
echo "\n=== Sample barangay ===\n";
$r = $conn->query('SELECT * FROM barangay LIMIT 5');
while($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
