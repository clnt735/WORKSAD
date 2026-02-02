<?php
session_start();
require_once '../database.php';

if (!isset($_SESSION['user_id'])) {
	die('Not logged in');
}

$userId = (int)$_SESSION['user_id'];

// Get the first job post for this employer
$jobSql = "SELECT job_post_id, job_post_name FROM job_post WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($jobSql);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
	die('No job posts found');
}

$jobPostId = $job['job_post_id'];

echo "<h2>Debugging Job: " . htmlspecialchars($job['job_post_name']) . " (ID: $jobPostId)</h2>";

// STEP 1: Check applicant_job_swipes table
echo "<h3>STEP 1: Check applicant_job_swipes table</h3>";
$sql1 = "SELECT * FROM applicant_job_swipes WHERE job_post_id = ? AND swipe_type = 'like'";
$stmt = $conn->prepare($sql1);
$stmt->bind_param('i', $jobPostId);
$stmt->execute();
$result = $stmt->get_result();
$swipes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "<pre>";
print_r($swipes);
echo "</pre>";

if (empty($swipes)) {
	die('No likes found for this job in applicant_job_swipes table');
}

$applicantId = $swipes[0]['applicant_id'];
echo "<hr><p><strong>Applicant ID who liked: $applicantId</strong></p>";

// STEP 2: Check if user exists
echo "<h3>STEP 2: Check if user exists with this ID</h3>";
$sql2 = "SELECT user_id, user_email, user_type_id FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql2);
$stmt->bind_param('i', $applicantId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

echo "<pre>";
print_r($user);
echo "</pre>";

if (!$user) {
	die("❌ ERROR: User with ID $applicantId does not exist in user table!");
}

if ($user['user_type_id'] != 2) {
	die("❌ ERROR: User type is {$user['user_type_id']} but should be 2 (applicant)!");
}

echo "<p>✅ User exists and is an applicant</p>";

// STEP 3: Check if resume exists
echo "<h3>STEP 3: Check if resume exists for this user</h3>";
$sql3 = "SELECT * FROM resume WHERE user_id = ?";
$stmt = $conn->prepare($sql3);
$stmt->bind_param('i', $applicantId);
$stmt->execute();
$result = $stmt->get_result();
$resume = $result->fetch_assoc();
$stmt->close();

echo "<pre>";
print_r($resume);
echo "</pre>";

if (!$resume) {
	die("❌ ERROR: No resume found for user ID $applicantId! This is why the candidate is not showing.");
}

echo "<p>✅ Resume exists (resume_id: {$resume['resume_id']})</p>";

// STEP 4: Check if employer already swiped
echo "<h3>STEP 4: Check if employer already swiped this applicant</h3>";
$sql4 = "SELECT * FROM employer_applicant_swipes WHERE employer_id = ? AND applicant_id = ?";
$stmt = $conn->prepare($sql4);
$stmt->bind_param('ii', $userId, $applicantId);
$stmt->execute();
$result = $stmt->get_result();
$employerSwipe = $result->fetch_assoc();
$stmt->close();

if ($employerSwipe) {
	echo "<pre>";
	print_r($employerSwipe);
	echo "</pre>";
	die("❌ ERROR: Employer already swiped this applicant! That's why they're filtered out.");
}

echo "<p>✅ Employer has not swiped this applicant yet</p>";

// STEP 5: Test the actual query from find_talent.php
echo "<h3>STEP 5: Test the actual query</h3>";
$testSql = "SELECT
	u.user_id,
	u.user_email,
	r.resume_id
FROM applicant_job_swipes ajs
INNER JOIN user u ON ajs.applicant_id = u.user_id
INNER JOIN resume r ON r.user_id = u.user_id
WHERE u.user_type_id = 2
	AND ajs.job_post_id = ?
	AND ajs.swipe_type = 'like'
	AND NOT EXISTS (
		SELECT 1
		FROM employer_applicant_swipes eas
		WHERE eas.employer_id = ?
		  AND eas.applicant_id = u.user_id
	)
LIMIT 10";

$stmt = $conn->prepare($testSql);
$stmt->bind_param('ii', $jobPostId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$candidates = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo "<pre>";
print_r($candidates);
echo "</pre>";

if (empty($candidates)) {
	echo "<p>❌ Query returned no results - the issue is confirmed!</p>";
} else {
	echo "<p>✅ Query works! Found " . count($candidates) . " candidate(s)</p>";
}

$conn->close();
?>
