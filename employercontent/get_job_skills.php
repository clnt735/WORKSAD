<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$jobPostId = isset($_GET['job_post_id']) ? (int)$_GET['job_post_id'] : 0;

if ($jobPostId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid job post ID.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Verify ownership - check both direct ownership and company ownership
$ownershipSql = "SELECT COUNT(*) as count FROM job_post jp 
                 LEFT JOIN company c ON jp.company_id = c.company_id
                 WHERE (jp.user_id = ? OR c.user_id = ?) AND jp.job_post_id = ?";
$ownershipStmt = $conn->prepare($ownershipSql);
$ownershipStmt->bind_param('iii', $userId, $userId, $jobPostId);
$ownershipStmt->execute();
$ownershipResult = $ownershipStmt->get_result();
$ownershipRow = $ownershipResult->fetch_assoc();
$ownershipStmt->close();

if ($ownershipRow['count'] == 0) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to view this job.']);
    exit;
}

// Fetch skills and job category
$skillsSql = "SELECT s.skill_id, s.name as skill_name, jc.job_category_name, jps.job_category_id
              FROM job_post_skills jps
              INNER JOIN skills s ON jps.skill_id = s.skill_id
              LEFT JOIN job_category jc ON jps.job_category_id = jc.job_category_id
              WHERE jps.job_post_id = ?
              ORDER BY s.name ASC";

$skillsStmt = $conn->prepare($skillsSql);
if (!$skillsStmt) {
    echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
    exit;
}

$skillsStmt->bind_param('i', $jobPostId);
$skillsStmt->execute();
$skillsResult = $skillsStmt->get_result();

$skills = [];
$jobCategoryName = 'Uncategorized';

while ($row = $skillsResult->fetch_assoc()) {
    $skills[] = [
        'skill_id' => (int)$row['skill_id'],
        'skill_name' => $row['skill_name'],
        'job_category_name' => $row['job_category_name'] ?? 'Uncategorized',
    ];
    
    // Get the category from the first skill (all skills should have the same category for a job)
    if ($jobCategoryName === 'Uncategorized' && !empty($row['job_category_name'])) {
        $jobCategoryName = $row['job_category_name'];
    }
}

$skillsStmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'skills' => $skills,
    'job_category_name' => $jobCategoryName,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
