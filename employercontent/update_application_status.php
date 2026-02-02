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
$new_status = isset($input['status']) ? trim($input['status']) : '';
$interview_datetime = isset($input['interview_datetime']) ? trim($input['interview_datetime']) : null;

// Validate inputs
if ($application_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

// Validate status value - 'interview' is the correct status for scheduled interviews
$allowed_statuses = ['pending', 'in_progress', 'shortlisted', 'interview', 'interviewing', 'accepted', 'rejected'];
if (!in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// Verify that this application belongs to a job post created by this employer
// Also fetch the current status for transition validation
$verify_sql = "SELECT a.application_id, a.status AS current_status
    FROM application a
    INNER JOIN job_post jp ON jp.job_post_id = a.job_post_id
    WHERE a.application_id = ? AND jp.user_id = ?";

$verify_stmt = $conn->prepare($verify_sql);
if (!$verify_stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$verify_stmt->bind_param('ii', $application_id, $employer_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Application not found or access denied']);
    $verify_stmt->close();
    exit;
}

$app_row = $verify_result->fetch_assoc();
$current_status = strtolower(trim($app_row['current_status'] ?? ''));
$verify_stmt->close();

// Define allowed status transitions (STRICT)
// shortlisted -> interview, rejected
// interview -> accepted, rejected
// Also allow initial shortlisting from pending/in_progress/applied states
$valid_transitions = [
    'pending' => ['shortlisted', 'rejected'],
    'in_progress' => ['shortlisted', 'rejected'],
    'applied' => ['shortlisted', 'rejected'],
    'shortlisted' => ['interview', 'rejected'],
    'interview' => ['accepted', 'rejected'],
    'interviewing' => ['accepted', 'rejected'], // alias for interview
];

// Normalize current status
if ($current_status === 'interviewing') {
    $current_status = 'interview';
}

// Check if transition is valid
$allowed_next = $valid_transitions[$current_status] ?? [];
if (!in_array($new_status, $allowed_next)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => "Invalid status transition from '$current_status' to '$new_status'. Allowed: " . implode(', ', $allowed_next)
    ]);
    exit;
}

// Get the job_post_id for this application (needed for vacancy updates)
$job_post_id = 0;
$get_job_sql = "SELECT job_post_id FROM application WHERE application_id = ?";
$get_job_stmt = $conn->prepare($get_job_sql);
if ($get_job_stmt) {
    $get_job_stmt->bind_param('i', $application_id);
    $get_job_stmt->execute();
    $job_result = $get_job_stmt->get_result();
    if ($job_row = $job_result->fetch_assoc()) {
        $job_post_id = (int)$job_row['job_post_id'];
    }
    $get_job_stmt->close();
}

// Start transaction for atomic updates (especially important for hiring)
$conn->begin_transaction();

try {
    // Update the application status
    $update_sql = "UPDATE application SET status = ?, updated_at = NOW() WHERE application_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        throw new Exception('Database error preparing application update');
    }
    
    $update_stmt->bind_param('si', $new_status, $application_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Failed to update application status');
    }
    $update_stmt->close();
    
    // If hiring (accepted), decrement vacancy and potentially close the job
    $vacancy_updated = false;
    $job_closed = false;
    $new_vacancy_count = null;
    
    if ($new_status === 'accepted' && $job_post_id > 0) {
        // Decrement vacancy (but not below 0)
        $decrement_sql = "UPDATE job_post SET vacancies = GREATEST(0, vacancies - 1), updated_at = NOW() WHERE job_post_id = ? AND vacancies > 0";
        $decrement_stmt = $conn->prepare($decrement_sql);
        
        if (!$decrement_stmt) {
            throw new Exception('Database error preparing vacancy update');
        }
        
        $decrement_stmt->bind_param('i', $job_post_id);
        
        if (!$decrement_stmt->execute()) {
            throw new Exception('Failed to decrement vacancy');
        }
        
        $vacancy_updated = ($decrement_stmt->affected_rows > 0);
        $decrement_stmt->close();
        
        // Check current vacancy count after decrement
        $check_vacancy_sql = "SELECT vacancies FROM job_post WHERE job_post_id = ?";
        $check_stmt = $conn->prepare($check_vacancy_sql);
        
        if ($check_stmt) {
            $check_stmt->bind_param('i', $job_post_id);
            $check_stmt->execute();
            $vacancy_result = $check_stmt->get_result();
            
            if ($vacancy_row = $vacancy_result->fetch_assoc()) {
                $new_vacancy_count = (int)$vacancy_row['vacancies'];
                
                // If vacancy is now 0, auto-close the job (job_status_id = 2)
                if ($new_vacancy_count === 0) {
                    $close_sql = "UPDATE job_post SET job_status_id = 2, updated_at = NOW() WHERE job_post_id = ?";
                    $close_stmt = $conn->prepare($close_sql);
                    
                    if ($close_stmt) {
                        $close_stmt->bind_param('i', $job_post_id);
                        if ($close_stmt->execute()) {
                            $job_closed = true;
                        }
                        $close_stmt->close();
                    }
                }
            }
            $check_stmt->close();
        }
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Build success response
    $response = [
        'success' => true, 
        'message' => 'Application status updated to ' . $new_status
    ];
    
    if ($new_status === 'accepted') {
        $response['vacancy_updated'] = $vacancy_updated;
        $response['new_vacancy_count'] = $new_vacancy_count;
        
        if ($job_closed) {
            $response['job_closed'] = true;
            $response['message'] .= '. Job post has been automatically closed (all vacancies filled).';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback on any error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>
