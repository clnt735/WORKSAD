<?php
// Trigger: Create notification when someone likes a profile
// Called after inserting into employer_applicant_swipes or applicant_job_swipes

require_once __DIR__ . '/../database.php';

function createLikeNotification($conn, $swipe_id, $receiver_id, $receiver_type, $sender_id = null, $sender_type = null) {
    $title = "You got a like!";
    $message = "Someone liked your profile.";
    $notification_type = 'like';
    
    // Fetch sender name to personalize message
    if ($sender_id && $sender_type) {
        if ($sender_type === 'employer') {
            // Fetch company name for employer
            $nameStmt = $conn->prepare("SELECT company_name FROM company WHERE user_id = ? LIMIT 1");
            $nameStmt->bind_param("i", $sender_id);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            if ($nameRow = $nameResult->fetch_assoc()) {
                $senderName = $nameRow['company_name'];
                $message = "$senderName liked your profile.";
            }
            $nameStmt->close();
        } elseif ($sender_type === 'applicant') {
            // Fetch applicant first name
            $nameStmt = $conn->prepare("SELECT user_profile_first_name FROM user_profile WHERE user_id = ? LIMIT 1");
            $nameStmt->bind_param("i", $sender_id);
            $nameStmt->execute();
            $nameResult = $nameStmt->get_result();
            if ($nameRow = $nameResult->fetch_assoc()) {
                $senderName = $nameRow['user_profile_first_name'];
                $message = "$senderName liked your profile.";
            }
            $nameStmt->close();
        }
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications 
        (receiver_id, receiver_type, title, message, notification_type, related_id, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare like notification: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("issssi", $receiver_id, $receiver_type, $title, $message, $notification_type, $swipe_id);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to insert like notification: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}
?>
