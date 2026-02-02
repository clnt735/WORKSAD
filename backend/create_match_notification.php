<?php
// Trigger: Create notification when a match occurs
// Called after inserting into matches table

require_once __DIR__ . '/../database.php';

function createMatchNotifications($conn, $match_id, $applicant_id, $employer_id) {
    $title = "It's a match!";
    $notification_type = 'match';
    
    // Fetch company name for applicant's notification
    $companyName = 'the other user';
    $companyStmt = $conn->prepare("SELECT company_name FROM company WHERE user_id = ? LIMIT 1");
    $companyStmt->bind_param("i", $employer_id);
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();
    if ($companyRow = $companyResult->fetch_assoc()) {
        $companyName = $companyRow['company_name'];
    }
    $companyStmt->close();
    
    // Fetch applicant name for employer's notification
    $applicantName = 'the other user';
    $applicantStmt = $conn->prepare("SELECT user_profile_first_name FROM user_profile WHERE user_id = ? LIMIT 1");
    $applicantStmt->bind_param("i", $applicant_id);
    $applicantStmt->execute();
    $applicantResult = $applicantStmt->get_result();
    if ($applicantRow = $applicantResult->fetch_assoc()) {
        $applicantName = $applicantRow['user_profile_first_name'];
    }
    $applicantStmt->close();
    
    // Notification for applicant with company name
    $messageForApplicant = "You and $companyName liked each other.";
    $stmt1 = $conn->prepare("
        INSERT INTO notifications 
        (receiver_id, receiver_type, title, message, notification_type, related_id, is_read, created_at)
        VALUES (?, 'applicant', ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt1) {
        error_log("Failed to prepare match notification for applicant: " . $conn->error);
        return false;
    }
    
    $stmt1->bind_param("isssi", $applicant_id, $title, $messageForApplicant, $notification_type, $match_id);
    $result1 = $stmt1->execute();
    
    if (!$result1) {
        error_log("Failed to insert match notification for applicant: " . $stmt1->error);
    }
    
    $stmt1->close();
    
    // Notification for employer with applicant name
    $messageForEmployer = "You and $applicantName liked each other.";
    $stmt2 = $conn->prepare("
        INSERT INTO notifications 
        (receiver_id, receiver_type, title, message, notification_type, related_id, is_read, created_at)
        VALUES (?, 'employer', ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt2) {
        error_log("Failed to prepare match notification for employer: " . $conn->error);
        return false;
    }
    
    $stmt2->bind_param("isssi", $employer_id, $title, $messageForEmployer, $notification_type, $match_id);
    $result2 = $stmt2->execute();
    
    if (!$result2) {
        error_log("Failed to insert match notification for employer: " . $stmt2->error);
    }
    
    $stmt2->close();
    
    return $result1 && $result2;
}
?>
