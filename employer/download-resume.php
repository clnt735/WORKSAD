<?php
/**
 * Resume PDF Download for Employers
 * WorkMuna - Job Matching Platform
 * 
 * This file prepares resume PDF generation using Dompdf.
 * Employers can only access resumes of matched applicants.
 */

session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================
// AUTHENTICATION & AUTHORIZATION
// ============================================

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized: Please log in.');
}

$employer_id = $_SESSION['user_id'];
$applicant_id = isset($_GET['applicant_id']) ? (int)$_GET['applicant_id'] : 0;

if (!$applicant_id) {
    http_response_code(400);
    die('Bad Request: Missing applicant_id parameter.');
}

// ============================================
// HELPER FUNCTION: CHECK IF MATCHED
// ============================================

/**
 * Check if employer and applicant are matched
 * @param mysqli $conn Database connection
 * @param int $employerId Employer user ID
 * @param int $applicantId Applicant user ID
 * @return bool True if matched, false otherwise
 */
function isMatched($conn, $employerId, $applicantId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as match_count 
        FROM matches 
        WHERE employer_id = ? AND applicant_id = ?
    ");
    
    if (!$stmt) {
        error_log("isMatched prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param('ii', $employerId, $applicantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return ($row['match_count'] > 0);
}

// ============================================
// VERIFY MATCH
// ============================================

if (!isMatched($conn, $employer_id, $applicant_id)) {
    http_response_code(403);
    die('Forbidden: You can only access resumes of matched applicants.');
}

// ============================================
// FETCH APPLICANT DATA
// ============================================

/**
 * Fetch complete resume data for an applicant
 * @param mysqli $conn Database connection
 * @param int $applicantId Applicant user ID
 * @return array Resume data array
 */
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
        SELECT CONCAT(firstname, ' ', lastname) as full_name, email, contact_number as phone
        FROM user 
        WHERE user_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param('i', $applicantId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $data['full_name'] = $row['full_name'] ?? '';
            $data['email'] = $row['email'] ?? '';
            $data['phone'] = $row['phone'] ?? '';
        }
        $stmt->close();
    }
    
    // Fetch resume info
    $stmt = $conn->prepare("
        SELECT resume_id, professional_summary 
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
            $data['professional_summary'] = $resume['professional_summary'] ?? '';
            
            // Fetch location
            $stmt = $conn->prepare("
                SELECT CONCAT(
                    COALESCE(al.street, ''), 
                    IF(al.street IS NOT NULL, ', ', ''),
                    COALESCE(b.barangay_name, ''),
                    IF(b.barangay_name IS NOT NULL, ', ', ''),
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
                SELECT rp.job_type_id, rp.industry_id,
                       jt.job_type_name as job_type,
                       i.industry_name as industry
                FROM resume_preference rp
                LEFT JOIN job_type jt ON rp.job_type_id = jt.job_type_id
                LEFT JOIN industry i ON rp.industry_id = i.industry_id
                WHERE rp.resume_id = ?
                LIMIT 1
            ");
            if ($stmt) {
                $stmt->bind_param('i', $resume_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data['preferences'] = [
                        'job_type' => $row['job_type'] ?? '',
                        'industry' => $row['industry'] ?? ''
                    ];
                }
                $stmt->close();
            }
        }
    }
    
    return $data;
}

// Fetch the resume data
$resumeData = fetchResumeData($conn, $applicant_id);

// ============================================
// PREPARE PDF GENERATION
// ============================================

/**
 * Generate PDF from resume data
 * @param array $data Resume data
 * @return Dompdf Configured Dompdf instance
 */
function preparePDF($data) {
    // Configure Dompdf options
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('chroot', __DIR__ . '/../');
    
    // Initialize Dompdf
    $dompdf = new Dompdf($options);
    
    // Load HTML template
    ob_start();
    include __DIR__ . '/../templates/resume_pdf.php';
    $html = ob_get_clean();
    
    // Load HTML into Dompdf
    $dompdf->loadHtml($html);
    
    // Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');
    
    // Render the PDF (important!)
    $dompdf->render();
    
    return $dompdf;
}

// Prepare the PDF
$data = $resumeData; // Make data available to template
$dompdf = preparePDF($resumeData);

// ============================================
// GENERATE AND DOWNLOAD PDF
// ============================================

// Generate filename
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resumeData['full_name']) . '_Resume.pdf';

// Stream PDF to browser (opens in new tab)
$dompdf->stream($filename, [
    'Attachment' => false,  // Display inline in browser
    'compress' => true
]);

exit;

