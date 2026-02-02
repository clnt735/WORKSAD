<?php
session_start();
require_once '../database.php';

if (!isset($_SESSION['user_id'])) {
	die('Not logged in');
}

$userId = (int)$_SESSION['user_id'];

echo "<h1>Full Debug - Find Talent Logic</h1>";
echo "<p>Employer ID: $userId</p>";

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
echo "<h2>Primary Job: {$jobs[0]['job_post_name']} (ID: $primaryJobPostId)</h2>";

// Get job details
$primaryJobTitle = null;
$primaryJobEducationLevelId = null;
$primaryJobExperienceLevelId = null;
$primaryJobCityId = null;
$primaryJobBarangayId = null;
$primaryJobSkills = [];

$jobDetailsSql = "SELECT education_level_id, experience_level_id, job_post_name FROM job_post WHERE job_post_id = ?";
$stmt = $conn->prepare($jobDetailsSql);
$stmt->bind_param('i', $primaryJobPostId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
	$primaryJobEducationLevelId = $row['education_level_id'] ? (int)$row['education_level_id'] : null;
	$primaryJobExperienceLevelId = $row['experience_level_id'] ? (int)$row['experience_level_id'] : null;
	$primaryJobTitle = $row['job_post_name'] ?? null;
}
$stmt->close();

// Get job location
$jobLocationSql = "SELECT city_mun_id, barangay_id FROM job_post_location WHERE job_post_id = ?";
$stmt = $conn->prepare($jobLocationSql);
$stmt->bind_param('i', $primaryJobPostId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
	$primaryJobCityId = $row['city_mun_id'] ? (int)$row['city_mun_id'] : null;
	$primaryJobBarangayId = $row['barangay_id'] ? (int)$row['barangay_id'] : null;
}
$stmt->close();

// Get job skills
$jobSkillsSql = "SELECT skill_id FROM job_post_skills WHERE job_post_id = ?";
$stmt = $conn->prepare($jobSkillsSql);
$stmt->bind_param('i', $primaryJobPostId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
	$primaryJobSkills[] = (int)$row['skill_id'];
}
$stmt->close();

echo "<h3>Job Requirements:</h3>";
echo "<pre>";
echo "Title: $primaryJobTitle\n";
echo "Education Level ID: " . ($primaryJobEducationLevelId ?? 'null') . "\n";
echo "Experience Level ID: " . ($primaryJobExperienceLevelId ?? 'null') . "\n";
echo "City ID: " . ($primaryJobCityId ?? 'null') . "\n";
echo "Barangay ID: " . ($primaryJobBarangayId ?? 'null') . "\n";
echo "Skills: " . json_encode($primaryJobSkills) . "\n";
echo "</pre>";

// Now get candidates
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
$stmt->bind_param('ii', $primaryJobPostId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$allRows = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "<h3>Query Results: " . count($allRows) . " rows</h3>";

if (empty($allRows)) {
	echo "<p style='color: red;'>❌ No candidates found!</p>";
	die();
}

// Process first candidate in detail
$row = $allRows[0];
$candidateId = (int)$row['user_id'];
$resumeId = (int)$row['resume_id'];

echo "<h3>Processing Candidate #$candidateId (Resume #$resumeId)</h3>";

// Get applicant skills
echo "<h4>Step 1: Getting applicant skills</h4>";
$applicantSkills = [];
$skillsSql = "SELECT skill_id FROM applicant_skills WHERE resume_id = ?";
$skillsStmt = $conn->prepare($skillsSql);
$skillsStmt->bind_param('i', $resumeId);
$skillsStmt->execute();
$skillsResult = $skillsStmt->get_result();
while ($skillRow = $skillsResult->fetch_assoc()) {
	$applicantSkills[] = (int)$skillRow['skill_id'];
}
$skillsStmt->close();
echo "<p>Applicant skills: " . json_encode($applicantSkills) . "</p>";

// Get applicant job titles
echo "<h4>Step 2: Getting applicant job titles</h4>";
$applicantJobTitles = [];
$titlesSql = "SELECT experience_name FROM applicant_experience WHERE resume_id = ? AND experience_name IS NOT NULL AND experience_name != ''";
$titlesStmt = $conn->prepare($titlesSql);
$titlesStmt->bind_param('i', $resumeId);
$titlesStmt->execute();
$titlesResult = $titlesStmt->get_result();
while ($titleRow = $titlesResult->fetch_assoc()) {
	$applicantJobTitles[] = $titleRow['experience_name'];
}
$titlesStmt->close();
echo "<p>Applicant job titles: " . json_encode($applicantJobTitles) . "</p>";

// Calculate match score (inline simplified version)
echo "<h4>Step 3: Calculating match score</h4>";
echo "<pre>";
echo "Job Title: '$primaryJobTitle'\n";
echo "Applicant Titles: " . json_encode($applicantJobTitles) . "\n";
echo "Job Skills: " . json_encode($primaryJobSkills) . "\n";
echo "Applicant Skills: " . json_encode($applicantSkills) . "\n";
echo "</pre>";

// Skills score
$skillScore = 0;
if (!empty($primaryJobSkills) && !empty($applicantSkills)) {
	$matchingSkills = array_intersect($primaryJobSkills, $applicantSkills);
	echo "<p>Matching skills: " . json_encode($matchingSkills) . "</p>";
	$skillMatchRate = count($matchingSkills) / count($primaryJobSkills);
	$skillScore = $skillMatchRate * 40;
	echo "<p>Skill score: $skillScore (match rate: " . round($skillMatchRate * 100, 2) . "%)</p>";
} else {
	echo "<p style='color: orange;'>⚠️ No skills to match (job or applicant has no skills)</p>";
}

// Title score
$jobTitleScore = 0;
if ($primaryJobTitle && !empty($applicantJobTitles)) {
	$jobTitleLower = strtolower(trim($primaryJobTitle));
	foreach ($applicantJobTitles as $appTitle) {
		$appTitleLower = strtolower(trim($appTitle));
		if ($appTitleLower === $jobTitleLower) {
			$jobTitleScore = 25;
			echo "<p>✓ Exact title match: '$appTitle' = '$primaryJobTitle' (25 points)</p>";
			break;
		}
		if (strpos($appTitleLower, $jobTitleLower) !== false || strpos($jobTitleLower, $appTitleLower) !== false) {
			$jobTitleScore = max($jobTitleScore, 17.5);
			echo "<p>✓ Partial title match: '$appTitle' ≈ '$primaryJobTitle' (17.5 points)</p>";
		}
	}
} else {
	echo "<p style='color: orange;'>⚠️ No job title to match</p>";
}

$totalScore = $skillScore + $jobTitleScore;
echo "<h4>Total Match Score: $totalScore</h4>";

echo "<h3>✅ Candidate should appear with match score: $totalScore</h3>";

$conn->close();
?>
