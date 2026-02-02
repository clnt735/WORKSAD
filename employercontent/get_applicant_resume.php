<?php
/**
 * Get Applicant Resume Data for Employers
 * WorkMuna - Job Matching Platform
 * 
 * Returns resume data in JSON format for modal display.
 * Only accessible to employers who are matched with the applicant.
 */

session_start();
require_once '../database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit;
}

$employer_id = (int)$_SESSION['user_id'];
$applicant_id = isset($_GET['applicant_id']) ? (int)$_GET['applicant_id'] : 0;

if (!$applicant_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing applicant_id parameter.']);
    exit;
}

// Check if employer and applicant are matched
function isMatched($conn, $employerId, $applicantId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as match_count 
        FROM matches 
        WHERE employer_id = ? AND applicant_id = ?
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('ii', $employerId, $applicantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return ($row['match_count'] > 0);
}

// Verify match
if (!isMatched($conn, $employer_id, $applicant_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only view resumes of matched applicants.']);
    exit;
}

// Fetch resume data
function fetchResumeData($conn, $applicantId) {
    $data = [
        'full_name' => '',
        'email' => '',
        'phone' => '',
        'professional_summary' => '',
        'location' => '',
        'work_experience' => [],
        'education' => [],
        'skills' => [],
        'achievements' => [],
        'preferences' => []
    ];
    
    // Fetch user basic info
    $stmt = $conn->prepare("
        SELECT CONCAT(up.user_profile_first_name, ' ', 
               COALESCE(up.user_profile_middle_name, ''), ' ', 
               up.user_profile_last_name) as full_name,
               up.user_profile_email_address as email,
               up.user_profile_contact_no as phone
        FROM user u
        LEFT JOIN user_profile up ON u.user_id = up.user_id
        WHERE u.user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $applicantId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $data['full_name'] = trim(preg_replace('/\s+/', ' ', $row['full_name'] ?? ''));
            $data['email'] = $row['email'] ?? '';
            $data['phone'] = $row['phone'] ?? '';
        }
        $stmt->close();
    }
    
    // Fetch resume info
    $stmt = $conn->prepare("
        SELECT resume_id, bio 
        FROM resume 
        WHERE user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $applicantId);
        $stmt->execute();
        $result = $stmt->get_result();
        $resume = $result->fetch_assoc();
        $stmt->close();
        
        if ($resume) {
            $resume_id = (int)$resume['resume_id'];
            $data['professional_summary'] = $resume['bio'] ?? '';
            
            // Fetch location
            $stmt = $conn->prepare("
                SELECT CONCAT(
                    COALESCE(b.barangay_name, ''),
                    IF(b.barangay_name IS NOT NULL AND c.city_mun_name IS NOT NULL, ', ', ''),
                    COALESCE(c.city_mun_name, '')
                ) as location
                FROM applicant_location al
                LEFT JOIN barangay b ON al.barangay_id = b.barangay_id
                LEFT JOIN city_mun c ON al.city_mun_id = c.city_mun_id
                WHERE al.resume_id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data['location'] = trim($row['location'] ?? '');
                }
                $stmt->close();
            }
            
            // Fetch work experience
            $stmt = $conn->prepare("
                SELECT ae.experience_name, ae.experience_company, ae.start_date, 
                       ae.end_date, ae.experience_description, el.experience_level_name
                FROM applicant_experience ae
                LEFT JOIN experience_level el ON ae.experience_level_id = el.experience_level_id
                WHERE ae.resume_id = ?
                ORDER BY ae.start_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $data['work_experience'][] = $row;
                }
                $stmt->close();
            }
            
            // Fetch education
            $stmt = $conn->prepare("
                SELECT ae.school_name, ae.start_date, ae.end_date, 
                       el.education_level_name as education_level
                FROM applicant_education ae
                LEFT JOIN education_level el ON ae.education_level_id = el.education_level_id
                WHERE ae.resume_id = ?
                ORDER BY ae.start_date DESC
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $data['education'][] = $row;
                }
                $stmt->close();
            }
            
            // Fetch skills
            $stmt = $conn->prepare("
                SELECT s.skill_name, jc.job_category_name as category
                FROM applicant_skills askill
                LEFT JOIN skills s ON askill.skill_id = s.skill_id
                LEFT JOIN job_category jc ON askill.job_category_id = jc.job_category_id
                WHERE askill.resume_id = ?
                ORDER BY jc.job_category_name, s.skill_name
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $data['skills'][] = $row;
                }
                $stmt->close();
            }
            
            // Fetch achievements
            $stmt = $conn->prepare("
                SELECT achievement_name, achievement_organization, date_received, description
                FROM applicant_achievements
                WHERE resume_id = ?
                ORDER BY date_received DESC
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $data['achievements'][] = $row;
                }
                $stmt->close();
            }
            
            // Fetch preferences
            $stmt = $conn->prepare("
                SELECT jt.job_type_name as job_type, ind.industry_name as industry
                FROM resume_preference rp
                LEFT JOIN job_type jt ON rp.job_type_id = jt.job_type_id
                LEFT JOIN industry ind ON rp.industry_id = ind.industry_id
                WHERE rp.resume_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $data['preferences'][] = $row;
                }
                $stmt->close();
            }
        }
    }
    
    return $data;
}

// Get resume data
$resumeData = fetchResumeData($conn, $applicant_id);

// Return success response
echo json_encode([
    'success' => true,
    'resume' => $resumeData
]);
