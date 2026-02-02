<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false]);
    exit;
}

$uid = $_SESSION['user_id'];

if(!empty($_FILES['profile_pic']['name'])){
    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
    $newName = "PIC_".$uid."_".time().".".$ext;

    $target = "../uploads/profile_pics/".$newName;

    if(move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target)){
        $stmt = $conn->prepare("UPDATE user_profile SET user_profile_photo = ? WHERE user_id = ?");
        $stmt->bind_param("si", $newName, $uid);
        $stmt->execute();
        echo json_encode(["success"=>true, "filename"=>$newName]);
        exit;
    }
}

echo json_encode(["success"=>false]);
exit;
?>
