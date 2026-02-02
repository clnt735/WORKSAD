<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

// Debug: Log incoming POST data
error_log("employerId: " . ($_SESSION['user_id'] ?? 'null') . ", applicantId: " . ($_POST['applicant_id'] ?? 'null') . ", swipeType: " . ($_POST['swipe_type'] ?? 'null'));

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}


$employerId = (int)$_SESSION['user_id'];
$applicantId = (int)($_POST['applicant_id'] ?? 0);
$swipeType = $_POST['swipe_type'] ?? '';
$jobPostId = (int)($_POST['job_post_id'] ?? 0);

if (!$applicantId || !$jobPostId || !in_array($swipeType, ['like', 'dislike'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// Check if swipe exists for this employer, applicant, and job post
$stmt = $conn->prepare("SELECT swipe_id FROM employer_applicant_swipes WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?");
$stmt->bind_param('iii', $employerId, $applicantId, $jobPostId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();
    $updateStmt = $conn->prepare("UPDATE employer_applicant_swipes SET swipe_type = ?, created_at = NOW() WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?");
    $updateStmt->bind_param('siii', $swipeType, $employerId, $applicantId, $jobPostId);
    $success = $updateStmt->execute();
    if (!$success) error_log("SQL Error (update): " . $updateStmt->error);
    $updateStmt->close();
} else {
    $stmt->close();
    $insertStmt = $conn->prepare("INSERT INTO employer_applicant_swipes (employer_id, applicant_id, job_post_id, swipe_type, created_at) VALUES (?, ?, ?, ?, NOW())");
    $insertStmt->bind_param('iiis', $employerId, $applicantId, $jobPostId, $swipeType);
    $success = $insertStmt->execute();
    $swipeId = $conn->insert_id;
    if (!$success) error_log("SQL Error (insert): " . $insertStmt->error);
    $insertStmt->close();
    
    // Create like notification for applicant if employer liked them
    if ($success && $swipeType === 'like') {
        require_once '../backend/create_like_notification.php';
        createLikeNotification($conn, $swipeId, $applicantId, 'applicant', $employerId, 'employer');
    }
}

// If employer liked applicant, check if applicant also liked this job post
// If both liked, create a match
if ($success && $swipeType === 'like') {
    // Check if applicant liked this job post
    $checkApplicantLike = $conn->prepare("SELECT swipe_id FROM applicant_job_swipes WHERE applicant_id = ? AND job_post_id = ? AND swipe_type = 'like'");
    $checkApplicantLike->bind_param('ii', $applicantId, $jobPostId);
    $checkApplicantLike->execute();
    $checkApplicantLike->store_result();
    
    if ($checkApplicantLike->num_rows > 0) {
        // It's a mutual match! Check if match already exists
        $checkApplicantLike->close();
        
        $checkMatch = $conn->prepare("SELECT match_id FROM matches WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?");
        $checkMatch->bind_param('iii', $employerId, $applicantId, $jobPostId);
        $checkMatch->execute();
        $checkMatch->store_result();
        
        if ($checkMatch->num_rows === 0) {
            // Create new match
            $checkMatch->close();
            $createMatch = $conn->prepare("INSERT INTO matches (employer_id, applicant_id, job_post_id, matched_at) VALUES (?, ?, ?, NOW())");
            $createMatch->bind_param('iii', $employerId, $applicantId, $jobPostId);
            $createMatch->execute();
            $matchId = $conn->insert_id;
            $createMatch->close();
            error_log("Match created: employer=$employerId, applicant=$applicantId, job=$jobPostId");
            
            // Create match notifications for both parties
            require_once '../backend/create_match_notification.php';
            createMatchNotifications($conn, $matchId, $applicantId, $employerId);
        } else {
            $checkMatch->close();
        }
    } else {
        $checkApplicantLike->close();
    }
}

if ($success) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}