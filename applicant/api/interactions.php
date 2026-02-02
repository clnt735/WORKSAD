<?php
// Minimal API: create/update interactions and write history
header('Content-Type: application/json');
include '../../database.php';
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Not authenticated']);
    exit;
}

$job_post_id = isset($input['job_post_id']) ? (int)$input['job_post_id'] : 0;
$action = $input['action'] ?? '';

if (!$job_post_id || !in_array($action, ['like','dislike','bookmark','undo'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid payload']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch existing interaction
    $stmt = $conn->prepare("SELECT interaction_id, action FROM interactions WHERE user_id=? AND job_post_id=? FOR UPDATE");
    $stmt->bind_param('ii', $user_id, $job_post_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $existing = $res->fetch_assoc();

    $previous_action = $existing['action'] ?? null;

    if ($action === 'undo') {
        // remove the interaction if exists
        if ($existing) {
            $del = $conn->prepare("DELETE FROM interactions WHERE interaction_id = ?");
            $del->bind_param('i', $existing['interaction_id']);
            $del->execute();
        }
        // log history
        $hist = $conn->prepare("INSERT INTO interaction_history (user_id, job_post_id, action, previous_action) VALUES (?,?,?,?)");
        $undoLabel = 'undo';
        $hist->bind_param('iiss', $user_id, $job_post_id, $undoLabel, $previous_action);
        $hist->execute();

        $conn->commit();
        echo json_encode(['success'=>true,'message'=>'Undone']);
        exit;
    }

    if ($existing) {
        // update action
        $up = $conn->prepare("UPDATE interactions SET action=?, updated_at=NOW() WHERE interaction_id=?");
        $up->bind_param('si', $action, $existing['interaction_id']);
        $up->execute();
    } else {
        // insert
        $ins = $conn->prepare("INSERT INTO interactions (user_id, job_post_id, action, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())");
        $ins->bind_param('iis', $user_id, $job_post_id, $action);
        $ins->execute();
    }

    // always append history
    $hist = $conn->prepare("INSERT INTO interaction_history (user_id, job_post_id, action, previous_action, created_at) VALUES (?,?,?,?,NOW())");
    $hist->bind_param('iiss', $user_id, $job_post_id, $action, $previous_action);
    $hist->execute();

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'OK']);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
