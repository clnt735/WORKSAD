<?php
session_start();
include '../database.php';
header('Content-Type: application/json');

$q = $_GET['q'] ?? '';
$type = $_GET['type'] ?? 'job';
$q = trim($q);
if($q === '') { echo json_encode([]); exit; }

$q_like = "%{$q}%";
$out = [];
if($type === 'skill'){
    $s = $conn->prepare("SELECT DISTINCT skill_name FROM skill WHERE skill_name LIKE ? LIMIT 8");
    $s->bind_param('s',$q_like);
    $s->execute();
    $r = $s->get_result();
    while($row = $r->fetch_assoc()) $out[] = $row['skill_name'];
} else {
    $s = $conn->prepare("SELECT DISTINCT job_post_name FROM job_post WHERE job_post_name LIKE ? LIMIT 8");
    $s->bind_param('s',$q_like);
    $s->execute();
    $r = $s->get_result();
    while($row = $r->fetch_assoc()) $out[] = $row['job_post_name'];
}
echo json_encode($out);
