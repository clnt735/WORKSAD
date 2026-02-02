<?php
session_start();
require_once '../database.php';

// Ensure employer is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$employer_id = (int)$_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$application_id = isset($input['application_id']) ? (int)$input['application_id'] : 0;
$applicant_id = isset($input['applicant_id']) ? (int)$input['applicant_id'] : 0;
$interview_datetime = isset($input['interview_datetime']) ? trim($input['interview_datetime']) : '';

// Validate inputs
if ($application_id === 0 || $applicant_id === 0 || empty($interview_datetime)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Parse interview datetime (format: YYYY-MM-DD HH:MM:SS)
$datetime_parts = explode(' ', $interview_datetime);
if (count($datetime_parts) !== 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid datetime format']);
    exit;
}

$date_parts = explode('-', $datetime_parts[0]);
if (count($date_parts) !== 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

$year = (int)$date_parts[0];
$month = (int)$date_parts[1];
$day = (int)$date_parts[2];
$interview_time = $datetime_parts[1];

// Validate date
if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date']);
    exit;
}

// Get job_post_id from application
$job_post_id = 0;
$sql = "SELECT job_post_id FROM application WHERE application_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $job_post_id = (int)$row['job_post_id'];
    }
    $stmt->close();
}

if ($job_post_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Application not found']);
    exit;
}

// Format interview date
$interview_date = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $interview_time);

// Check if interview already exists for this application
$check_sql = "SELECT interview_id FROM interview_schedule WHERE application_id = ? AND status = 'scheduled'";
$check_stmt = $conn->prepare($check_sql);
if ($check_stmt) {
    $check_stmt->bind_param('i', $application_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing interview
        $check_stmt->close();
        
        $update_sql = "UPDATE interview_schedule SET 
            interview_date = ?,
            interview_month = ?,
            interview_day = ?,
            interview_time = ?,
            updated_at = NOW()
            WHERE application_id = ? AND status = 'scheduled'";
        
        $update_stmt = $conn->prepare($update_sql);
        if ($update_stmt) {
            $update_stmt->bind_param('siisi', $interview_date, $month, $day, $interview_time, $application_id);
            if ($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Interview updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update interview']);
            }
            $update_stmt->close();
        }
    } else {
        // Insert new interview
        $check_stmt->close();
        
        $insert_sql = "INSERT INTO interview_schedule 
            (application_id, employer_id, applicant_id, job_post_id, interview_date, interview_month, interview_day, interview_time, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())";
        
        $insert_stmt = $conn->prepare($insert_sql);
        if ($insert_stmt) {
            $insert_stmt->bind_param('iiiisiis', $application_id, $employer_id, $applicant_id, $job_post_id, $interview_date, $month, $day, $interview_time);
            if ($insert_stmt->execute()) {
                $interviewId = $conn->insert_id;
                
                // Create interview notification for applicant
                if (file_exists('../backend/create_interview_notification.php')) {
                    require_once '../backend/create_interview_notification.php';
                    createInterviewNotification($conn, $interviewId, $applicant_id, $job_post_id, $interview_date);
                }
                
                echo json_encode(['success' => true, 'message' => 'Interview scheduled successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to schedule interview']);
            }
            $insert_stmt->close();
        }
    }
}

$conn->close();
?>
