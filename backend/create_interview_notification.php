<?php
// Trigger: Create notification when an interview is scheduled
// Called after inserting into interview_schedule table

require_once __DIR__ . '/../database.php';

function createInterviewNotification($conn, $interview_id, $applicant_id, $job_post_id, $interview_date) {
    // Fetch job post and company details
    $query = "
        SELECT jp.job_description, c.company_name
        FROM interview_schedule isc
        JOIN job_post jp ON isc.job_post_id = jp.job_post_id
        JOIN company c ON jp.user_id = c.user_id
        WHERE isc.interview_id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare interview details query: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $interview_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $company_name = $row['company_name'] ?? 'a company';
        $job_description = $row['job_description'] ?? 'a position';
        
        // Limit job description to first 50 characters
        if (strlen($job_description) > 50) {
            $job_description = substr($job_description, 0, 47) . '...';
        }
    } else {
        $company_name = 'a company';
        $job_description = 'a position';
    }
    
    $stmt->close();
    
    // Format date
    $formatted_date = date('M d, Y', strtotime($interview_date));
    
    $title = "Interview Scheduled";
    $message = "You have an interview with {$company_name} for {$job_description} on {$formatted_date}.";
    $notification_type = 'interview';
    
    // Insert notification
    $stmt2 = $conn->prepare("
        INSERT INTO notifications 
        (receiver_id, receiver_type, title, message, notification_type, related_id, is_read, created_at)
        VALUES (?, 'applicant', ?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt2) {
        error_log("Failed to prepare interview notification: " . $conn->error);
        return false;
    }
    
    $stmt2->bind_param("isssi", $applicant_id, $title, $message, $notification_type, $interview_id);
    $result = $stmt2->execute();
    
    if (!$result) {
        error_log("Failed to insert interview notification: " . $stmt2->error);
    }
    
    $stmt2->close();
    return $result;
}
?>
