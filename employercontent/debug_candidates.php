<?php
session_start();
require_once '../database.php';

if (!isset($_SESSION['user_id'])) {
	die('Not logged in');
}

$userId = (int)$_SESSION['user_id'];

// Get jobs
$jobsSql = "SELECT jp.job_post_id, jp.job_post_name FROM job_post jp WHERE jp.user_id = ? ORDER BY jp.created_at DESC";
$stmt = $conn->prepare($jobsSql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$jobs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($jobs)) {
	die('No jobs found');
}

$primaryJobPostId = (int)$jobs[0]['job_post_id'];
echo "<h2>Testing query for Job ID: $primaryJobPostId</h2>";
echo "<p>Employer ID: $userId</p>";

// Run the EXACT query from find_talent.php
$candidatesSql = "SELECT
	u.user_id,
	u.user_email,
	COALESCE(up.user_profile_first_name, '') AS user_profile_first_name,
	COALESCE(up.user_profile_last_name, '') AS user_profile_last_name,
	up.user_profile_photo,
	r.resume_id,
	r.updated_at AS resume_updated_at,
	exp.experience_name AS applicant_job_title,
	exp.experience_company,
	exp.start_date AS experience_start_date,
	exp.end_date AS experience_end_date,
	exp.experience_description,
	exp.experience_level_id AS experience_level_id,
	edu.school_name AS education_school,
	edu.start_date AS education_start_date,
	edu.end_date AS education_end_date,
	edu.education_level_id,
	lvl.education_level_name,
	loc.address_line,
	loc.city_mun_id,
	loc.barangay_id,
	city.city_mun_name,
	brgy.barangay_name
FROM applicant_job_swipes ajs
INNER JOIN user u ON ajs.applicant_id = u.user_id
LEFT JOIN resume r ON r.user_id = u.user_id
LEFT JOIN user_profile up ON up.user_id = u.user_id
LEFT JOIN applicant_location loc ON loc.resume_id = r.resume_id
LEFT JOIN city_mun city ON city.city_mun_id = loc.city_mun_id
LEFT JOIN barangay brgy ON brgy.barangay_id = loc.barangay_id
LEFT JOIN applicant_experience exp ON exp.resume_id = r.resume_id
LEFT JOIN applicant_education edu ON edu.resume_id = r.resume_id
LEFT JOIN education_level lvl ON lvl.education_level_id = edu.education_level_id
WHERE u.user_type_id = 2
	AND ajs.job_post_id = ?
	AND ajs.swipe_type = 'like'
	AND r.resume_id IS NOT NULL
	AND NOT EXISTS (
		SELECT 1
		FROM employer_applicant_swipes eas
		WHERE eas.employer_id = ?
		  AND eas.applicant_id = u.user_id
	)
ORDER BY ajs.created_at DESC
LIMIT 100";

$stmt = $conn->prepare($candidatesSql);
if ($stmt === false) {
	die("<p style='color: red;'>SQL Prepare Error: " . htmlspecialchars($conn->error) . "</p>");
}
$stmt->bind_param('ii', $primaryJobPostId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$allRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "<h3>Query Results</h3>";
echo "<p>Total rows returned: " . count($allRows) . "</p>";

if (empty($allRows)) {
	echo "<p style='color: red;'>❌ No rows returned from query!</p>";
	die();
}

echo "<h4>First few rows:</h4>";
echo "<pre>";
print_r(array_slice($allRows, 0, 3));
echo "</pre>";

// Process like find_talent.php does
$groupedCandidates = [];
foreach ($allRows as $index => $row) {
	$candidateId = (int)($row['user_id'] ?? 0);
	echo "<p>Row #" . ($index + 1) . ": user_id=$candidateId, resume_id={$row['resume_id']}</p>";
	
	if (!isset($groupedCandidates[$candidateId])) {
		echo "<p style='color: green;'>→ Creating new candidate entry for user_id=$candidateId</p>";
		$groupedCandidates[$candidateId] = [
			'user_id' => $candidateId,
			'resume_id' => (int)($row['resume_id'] ?? 0),
			'name' => trim($row['user_profile_first_name'] . ' ' . $row['user_profile_last_name']),
		];
	} else {
		echo "<p style='color: blue;'>→ Skipping, already have user_id=$candidateId</p>";
	}
}

echo "<h3>Final Result</h3>";
echo "<p>Unique candidates grouped: " . count($groupedCandidates) . "</p>";
echo "<pre>";
print_r($groupedCandidates);
echo "</pre>";

$conn->close();
?>
