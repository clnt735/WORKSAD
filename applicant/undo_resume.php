<?php
session_start();
include '../database.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'msg'=>'Not authenticated']); exit; }
$user_id = $_SESSION['user_id'];

if(empty($_SESSION['resume_history'][$user_id] ?? [])){
    echo json_encode(['success'=>false,'msg'=>'No history']); exit;
}
$snapshot = array_pop($_SESSION['resume_history'][$user_id]);
if(!$snapshot){ echo json_encode(['success'=>false,'msg'=>'No snapshot']); exit; }

$u = $conn->prepare("UPDATE resume SET professional_summary = ?, work_experience = ?, education = ?, skills = ?, achievements = ?, updated_at = NOW() WHERE user_id = ?");
$u->bind_param('sssssi', $snapshot['professional_summary'], $snapshot['work_experience'], $snapshot['education'], $snapshot['skills'], $snapshot['achievements'], $user_id);
$ok = $u->execute();

if($ok) echo json_encode(['success'=>true,'snapshot'=>$snapshot]); else echo json_encode(['success'=>false,'msg'=>$conn->error]);
?>