<?php
// Prevent any output before JSON
ob_start();

// Suppress all errors that might break JSON output
error_reporting(0);
ini_set('display_errors', '0');

session_start();
include '../database.php';

// Clear any previous output
ob_clean();

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get job post ID
$job_post_id = $_GET['job_post_id'] ?? null;

if (!$job_post_id) {
    echo json_encode(['success' => false, 'error' => 'Job post ID required']);
    exit;
}

try {
    // First check if job exists
    $check_query = "SELECT job_post_id, job_post_name FROM job_post WHERE job_post_id = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $job_post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Job post not found']);
        exit;
    }
    $check_stmt->close();

    // Fetch comprehensive job details with related data
    $query = "
        SELECT 
            jp.*,
            c.company_name,
            COALESCE(c.description, '') as company_description,
            COALESCE(c.logo, '') as company_logo,
            COALESCE(c.location, '') as company_address,
            COALESCE(c.industry, '') as company_industry,
            COALESCE(c.website, '') as company_website,
            COALESCE(jc.job_category_name, 'General') as job_category_name,
            COALESCE(jt.job_type_name, 'Full-time') as job_type_name,
            'Active' as job_status_name,
            COALESCE(el.education_level_name, 'Not specified') as education_level_name,
            COALESCE(exp.experience_level_name, 'Entry level') as experience_level_name,
            COALESCE(ws.work_setup_name, 'On-site') as work_setup_name,
            COALESCE(c.location, 'Location TBD') as job_location_name,
            COALESCE(c.location, '') as city,
            '' as province
        FROM job_post jp
        LEFT JOIN company c ON jp.company_id = c.company_id
        LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
        LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
        LEFT JOIN education_level el ON jp.education_level_id = el.education_level_id
        LEFT JOIN experience_level exp ON jp.experience_level_id = exp.experience_level_id
        LEFT JOIN work_setup ws ON jp.work_setup_id = ws.work_setup_id
        WHERE jp.job_post_id = ?
    ";

    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        echo json_encode(['success' => false, 'error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("i", $job_post_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Job post not found']);
        exit;
    }

    $job = $result->fetch_assoc();
    $stmt->close();
    
    // Get skills separately to avoid GROUP_CONCAT issues
    $skills_query = "
        SELECT GROUP_CONCAT(s.skill_name ORDER BY s.skill_name ASC SEPARATOR ', ') as required_skills
        FROM job_post_skills jps
        LEFT JOIN skills s ON jps.skill_id = s.skill_id
        WHERE jps.job_post_id = ?
    ";
    
    $skills_stmt = $conn->prepare($skills_query);
    if ($skills_stmt) {
        $skills_stmt->bind_param("i", $job_post_id);
        $skills_stmt->execute();
        $skills_result = $skills_stmt->get_result();
        $skills_data = $skills_result->fetch_assoc();
        $job['required_skills'] = $skills_data['required_skills'] ?? '';
        $skills_stmt->close();
    } else {
        $job['required_skills'] = '';
    }

    // Calculate match score if needed
    $match_score = 0;
    
    if (isset($_SESSION['user_id'])) {
        try {
            include 'job_matching.php';
            // Fix: Parameters should be ($conn, $user_id), not ($user_id, $conn)
            $matched_jobs = getMatchedJobs($conn, $_SESSION['user_id']);
            
            if (!empty($matched_jobs['jobs'])) {
                foreach ($matched_jobs['jobs'] as $job_match) {
                    if ($job_match['job_post_id'] == $job_post_id && isset($job_match['match_percentage'])) {
                        $match_score = $job_match['match_percentage'];
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // If match calculation fails, just use 0
            error_log("Match calculation error: " . $e->getMessage());
            $match_score = 0;
        }
    }

    // Format the response with safe defaults
    $response = [
        'success' => true,
        'job' => [
            'job_post_id' => $job['job_post_id'],
            'user_id' => $job['user_id'] ?? null,
            'company_id' => $job['company_id'] ?? null,
            'job_post_name' => $job['job_post_name'] ?? 'Job Position',
            'job_category_id' => $job['job_category_id'] ?? null,
            'job_category_name' => $job['job_category_name'] ?? 'General',
            'job_type_id' => $job['job_type_id'] ?? null,
            'job_type_name' => $job['job_type_name'] ?? 'Full-time',
            'job_status_id' => $job['job_status_id'] ?? 1,
            'job_status_name' => $job['job_status_name'] ?? 'Active',
            'job_description' => $job['job_description'] ?? 'No description available.',
            'requirements' => $job['requirements'] ?? 'No specific requirements.',
            'job_location_id' => $job['job_location_id'] ?? null,
            'job_location_name' => $job['job_location_name'] ?? 'Location TBD',
            'city' => $job['city'] ?? '',
            'province' => $job['province'] ?? '',
            'budget' => $job['budget'] ?? null,
            'benefits' => $job['benefits'] ?? '',
            'vacancies' => $job['vacancies'] ?? 1,
            'experience_level_id' => $job['experience_level_id'] ?? null,
            'experience_level_name' => $job['experience_level_name'] ?? 'Entry level',
            'education_level_id' => $job['education_level_id'] ?? null,
            'education_level_name' => $job['education_level_name'] ?? 'Not specified',
            'work_setup_id' => $job['work_setup_id'] ?? null,
            'work_setup_name' => $job['work_setup_name'] ?? 'On-site',
            'created_at' => $job['created_at'] ?? '',
            'updated_at' => $job['updated_at'] ?? '',
            'company_name' => $job['company_name'] ?? 'Company',
            'company_email' => '', // Not available in company table
            'company_phone' => '', // Not available in company table
            'company_description' => $job['company_description'] ?? '',
            'company_logo' => $job['company_logo'] ?? '',
            'company_address' => $job['company_address'] ?? '',
            'company_industry' => $job['company_industry'] ?? '',
            'company_website' => $job['company_website'] ?? '',
            'required_skills' => $job['required_skills'] ?? '',
            'match_score' => $match_score
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error fetching job details: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>