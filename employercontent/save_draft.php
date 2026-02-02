<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Handle both POST and sendBeacon requests
$postData = $_POST;

// If sendBeacon sent raw form data, parse it
if (empty($postData) && !empty(file_get_contents('php://input'))) {
    parse_str(file_get_contents('php://input'), $postData);
}

// Save all form data to session
$_SESSION['job_post_draft'] = [
    'current_step'         => $postData['current_step'] ?? '0',
    'job_post_name'        => $postData['job_post_name'] ?? '',
    'job_description'      => $postData['job_description'] ?? '',
    'vacancies'            => $postData['vacancies'] ?? '1',
    'industry_id'          => $postData['industry_id'] ?? '',
    'job_category_id'      => $postData['job_category_id'] ?? '',
    'work_setup_id'        => $postData['work_setup_id'] ?? '',
    'city_mun_id'          => $postData['city_mun_id'] ?? '',
    'barangay_id'          => $postData['barangay_id'] ?? '',
    'location'             => $postData['location'] ?? '',
    'budget'               => $postData['budget'] ?? '',
    'benefits'             => $postData['benefits'] ?? '',
    'job_type_id'          => $postData['job_type_id'] ?? '',
    'education_level_id'   => $postData['education_level_id'] ?? '',
    'experience_level_id'  => $postData['experience_level_id'] ?? '',
    'skill_ids'            => $postData['skill_ids'] ?? []
];

echo json_encode(['success' => true, 'message' => 'Draft saved successfully']);
?>
