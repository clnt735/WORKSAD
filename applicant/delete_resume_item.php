<?php
session_start();
include '../database.php';

header("Content-Type: application/json");

if(!isset($_SESSION['user_id'])){
    echo json_encode(["success"=>false, "msg"=>"Not logged in"]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $section = $data['section'] ?? '';
    $id = isset($data['id']) ? (int)$data['id'] : null;

    if (empty($id)) {
        echo json_encode(["success" => false, "msg" => "ID required"]);
        exit;
    }

    // Map section => table, pk
    $map = [
        'work' => ['table' => 'applicant_experience', 'pk' => 'experience_id'],
        'education' => ['table' => 'applicant_education', 'pk' => 'applicant_education_id'],
        'skill' => ['table' => 'applicant_skills', 'pk' => 'applicant_skills_id'],
        'achievement' => ['table' => 'applicant_achievements', 'pk' => 'achievement_id'],
    ];

    if (!isset($map[$section])) {
        echo json_encode(["success" => false, "msg" => "Invalid section"]);
        exit;
    }

    $table = $map[$section]['table'];
    $pk = $map[$section]['pk'];

    // Determine ownership:
    // If table has resume_id column, resolve resume_id and check resume.user_id == session user.
    // Else if table has user_id column, check that user_id == session user.
    $hasResumeCol = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'resume_id'")->num_rows > 0;
    $hasUserCol = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'")->num_rows > 0;

    $owned = false;
    if ($hasResumeCol) {
        $stmt = $conn->prepare("SELECT r.user_id FROM `$table` t JOIN resume r ON t.resume_id = r.resume_id WHERE t.`$pk` = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int)$row['user_id'] === $userId) $owned = true;
    } elseif ($hasUserCol) {
        $stmt = $conn->prepare("SELECT user_id FROM `$table` WHERE `$pk` = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && (int)$row['user_id'] === $userId) $owned = true;
    } else {
        // If table has neither, disallow
        echo json_encode(["success" => false, "msg" => "Cannot verify ownership for deletion"]);
        exit;
    }

    if (!$owned) {
        echo json_encode(["success" => false, "msg" => "Not authorized to delete this item"]);
        exit;
    }

    // Perform deletion
    $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$pk` = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["success" => false, "msg" => "Prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        echo json_encode(["success" => false, "msg" => "Execute failed: " . $stmt->error]);
        exit;
    }
    $stmt->close();

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

exit;
