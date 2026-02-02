<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}


$employerId = (int)$_SESSION['user_id'];
$applicantId = (int)($_POST['applicant_id'] ?? 0);
$jobPostId = (int)($_POST['job_post_id'] ?? 0);

if (!$applicantId || !$jobPostId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}


$stmt = $conn->prepare(
    "DELETE FROM employer_applicant_swipes WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?"
);
$stmt->bind_param('iii', $employerId, $applicantId, $jobPostId);

if ($stmt->execute()) {
    $stmt->close();
    
    // Also remove any match that was created for this combination
    $removeMatch = $conn->prepare(
        "DELETE FROM matches WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?"
    );
    $removeMatch->bind_param('iii', $employerId, $applicantId, $jobPostId);
    $removeMatch->execute();
    $removeMatch->close();
    
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    $stmt->close();
}