<?php
include 'database.php';

echo "=== JOB_POST ===\n";
$r = $conn->query('SHOW COLUMNS FROM job_post');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n=== JOB_POST_SKILLS ===\n";
$r = $conn->query('SHOW COLUMNS FROM job_post_skills');
if($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Table does not exist\n";
}

echo "\n=== JOB_POST_LOCATION ===\n";
$r = $conn->query('SHOW COLUMNS FROM job_post_location');
if($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Table does not exist\n";
}

echo "\n=== APPLICANT_LOCATION ===\n";
$r = $conn->query('SHOW COLUMNS FROM applicant_location');
if($r) {
    while($row = $r->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Table does not exist\n";
}

echo "\n=== RESUME ===\n";
$r = $conn->query('SHOW COLUMNS FROM resume');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}
