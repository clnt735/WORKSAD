<?php
/**
 * Resume PDF Download for Applicants (Own Resume)
 * WorkMuna - Job Matching Platform
 * 
 * This file generates PDF for applicant's own resume using Dompdf.
 */

session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ============================================
// AUTHENTICATION
// ============================================

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized: Please log in.');
}

$applicant_id = $_SESSION['user_id'];

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
            
            // Fetch skills grouped by category
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

// Fetch data
$resumeData = fetchResumeData($conn, $applicant_id);

// ============================================
// PREPARE PDF
// ============================================

/**
 * Configure and prepare PDF using Dompdf
 * @param array $data Resume data
 * @return Dompdf Configured Dompdf instance
 */
function preparePDF($data) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isFontSubsettingEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Load HTML template
    ob_start();
    include(__DIR__ . '/../templates/resume_pdf.php');
    $html = ob_get_clean();
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    return $dompdf;
}

// ============================================
// GENERATE AND DOWNLOAD PDF
// ============================================

$data = $resumeData; // Make data available to template
$dompdf = preparePDF($resumeData);

// Generate filename
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $resumeData['full_name']) . '_Resume.pdf';

// Stream PDF to browser for download
$dompdf->stream($filename, [
    'Attachment' => true,
    'compress' => true
]);

exit;
