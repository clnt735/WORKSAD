<?php
require_once 'database.php';

echo "<h2>applicant_achievements Table Structure</h2>";
$result = $conn->query('DESCRIBE applicant_achievements');
echo "<pre>";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Key'] . "\n";
}
echo "</pre>";
?>
