<?php
session_start();
include '../database.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'msg'=>'Not authenticated']); exit; }
$user_id = $_SESSION['user_id'];

if(empty($_FILES['file'])) { echo json_encode(['success'=>false,'msg'=>'No file']); exit; }
$f = $_FILES['file'];
$max = 5 * 1024 * 1024;
if($f['size'] > $max) { echo json_encode(['success'=>false,'msg'=>'File too large']); exit; }
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','png','jpg','jpeg','gif'];
if(!in_array($ext,$allowed)){ echo json_encode(['success'=>false,'msg'=>'Invalid file type']); exit; }

$dir = __DIR__ . '/uploads/certificates/';
if(!is_dir($dir)) mkdir($dir, 0755, true);
$filename = 'cert_'.$user_id.'_'.time().'_'.bin2hex(random_bytes(6)).'.'.$ext;
$dest = $dir . $filename;

if(!move_uploaded_file($f['tmp_name'], $dest)){ echo json_encode(['success'=>false,'msg'=>'Move failed']); exit; }
$url = 'uploads/certificates/' . $filename;
echo json_encode(['success'=>true,'url'=>$url]);
