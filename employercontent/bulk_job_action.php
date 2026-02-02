<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$jobIds = $_POST['job_ids'] ?? [];

// Decode JSON if it's a string
if (is_string($jobIds)) {
    $jobIds = json_decode($jobIds, true);
}

if (!in_array($action, ['archive', 'delete', 'restore'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (empty($jobIds) || !is_array($jobIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No jobs selected']);
    exit;
}

$jobIds = array_map('intval', $jobIds);
$jobIds = array_filter($jobIds, function($id) { return $id > 0; });

if (empty($jobIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid job IDs']);
    exit;
}

$placeholders = implode(',', array_fill(0, count($jobIds), '?'));
$types = str_repeat('i', count($jobIds) + 1);
$params = array_merge([$userId], $jobIds);

// Verify ownership
$ownershipSql = "SELECT COUNT(*) as count FROM job_post jp 
                 LEFT JOIN company c ON jp.company_id = c.company_id
                 WHERE (jp.user_id = ? OR c.user_id = ?) AND jp.job_post_id IN ($placeholders)";
$ownershipStmt = $conn->prepare($ownershipSql);
if (!$ownershipStmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$ownershipParams = array_merge([$userId, $userId], $jobIds);
$ownershipTypes = 'ii' . str_repeat('i', count($jobIds));
$ownershipStmt->bind_param($ownershipTypes, ...$ownershipParams);
$ownershipStmt->execute();
$ownershipResult = $ownershipStmt->get_result();
$ownershipRow = $ownershipResult->fetch_assoc();
$ownershipStmt->close();

if ($ownershipRow['count'] != count($jobIds)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not own all selected jobs']);
    exit;
}

$conn->begin_transaction();

try {
    if ($action === 'archive') {
        // Archive: Set job_status_id to 3 (Archived)
        $archiveSql = "UPDATE job_post SET job_status_id = 3 WHERE job_post_id IN ($placeholders)";
        $archiveStmt = $conn->prepare($archiveSql);
        if (!$archiveStmt) {
            throw new Exception('Failed to prepare archive statement');
        }
        $archiveStmt->bind_param(str_repeat('i', count($jobIds)), ...$jobIds);
        $archiveStmt->execute();
        $archiveStmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => count($jobIds) . ' job(s) archived successfully']);
        
    } elseif ($action === 'restore') {
        // Restore: Set job_status_id to 1 (Open)
        $restoreSql = "UPDATE job_post SET job_status_id = 1 WHERE job_post_id IN ($placeholders)";
        $restoreStmt = $conn->prepare($restoreSql);
        if (!$restoreStmt) {
            throw new Exception('Failed to prepare restore statement');
        }
        $restoreStmt->bind_param(str_repeat('i', count($jobIds)), ...$jobIds);
        $restoreStmt->execute();
        $restoreStmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => count($jobIds) . ' job(s) restored successfully']);
        
    } elseif ($action === 'delete') {
        // Delete: Remove from database with cascading
        foreach ($jobIds as $jobId) {
            // Delete related records
            $conn->query("DELETE FROM employer_applicant_swipes WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM applicant_job_swipes WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM matches WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM interactions WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM interaction_history WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM job_post_skills WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM job_post_location WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM job_likes WHERE job_post_id = $jobId");
            $conn->query("DELETE FROM like_history WHERE job_post_id = $jobId");
        }
        
        // Delete job posts
        $deleteSql = "DELETE FROM job_post WHERE job_post_id IN ($placeholders)";
        $deleteStmt = $conn->prepare($deleteSql);
        if (!$deleteStmt) {
            throw new Exception('Failed to prepare delete statement');
        }
        $deleteStmt->bind_param(str_repeat('i', count($jobIds)), ...$jobIds);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => count($jobIds) . ' job(s) deleted successfully']);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()]);
}

$conn->close();
