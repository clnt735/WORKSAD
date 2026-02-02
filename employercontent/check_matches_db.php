<?php
session_start();
require_once '../database.php';

if (!isset($_SESSION['user_id'])) {
    die("Not logged in. Please log in first.");
}

$employer_id = (int)$_SESSION['user_id'];

echo "<h2>Matches Database Checker</h2>";
echo "<p><strong>Current employer_id from session:</strong> $employer_id</p>";

// Check if matches table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'matches'");
if ($tableCheck->num_rows === 0) {
    echo "<p style='color: red;'><strong>ERROR:</strong> The 'matches' table does not exist!</p>";
    exit;
}
echo "<p style='color: green;'>✓ Matches table exists</p>";

// Check all records in matches table
echo "<h3>All records in matches table:</h3>";
$allMatches = $conn->query("SELECT * FROM matches");
if ($allMatches->num_rows === 0) {
    echo "<p style='color: orange;'>⚠ No records found in matches table at all!</p>";
} else {
    echo "<p>Total records in matches table: <strong>{$allMatches->num_rows}</strong></p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>match_id</th><th>applicant_id</th><th>employer_id</th><th>job_post_id</th><th>matched_at</th></tr>";
    while ($row = $allMatches->fetch_assoc()) {
        $highlight = ($row['employer_id'] == $employer_id) ? "style='background: #90EE90;'" : "";
        echo "<tr $highlight>";
        echo "<td>{$row['match_id']}</td>";
        echo "<td>{$row['applicant_id']}</td>";
        echo "<td>{$row['employer_id']}</td>";
        echo "<td>{$row['job_post_id']}</td>";
        echo "<td>{$row['matched_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><em>Note: Green rows match your employer_id</em></p>";
}

// Check matches specifically for this employer
echo "<h3>Matches for your employer_id ($employer_id):</h3>";
$stmt = $conn->prepare("SELECT * FROM matches WHERE employer_id = ?");
$stmt->bind_param('i', $employer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color: orange;'>⚠ No matches found for your employer_id!</p>";
    echo "<p><strong>Possible reasons:</strong></p>";
    echo "<ul>";
    echo "<li>You haven't liked any applicants yet (check employer_applicant_swipes table)</li>";
    echo "<li>No applicants have liked your job posts yet (check applicant_job_swipes table)</li>";
    echo "<li>The matches table isn't being populated automatically</li>";
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✓ Found <strong>{$result->num_rows}</strong> match(es) for your employer_id!</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>match_id</th><th>applicant_id</th><th>job_post_id</th><th>matched_at</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['match_id']}</td>";
        echo "<td>{$row['applicant_id']}</td>";
        echo "<td>{$row['job_post_id']}</td>";
        echo "<td>{$row['matched_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
$stmt->close();

// Check user table to verify employer exists
echo "<h3>Your user record:</h3>";
$userCheck = $conn->prepare("SELECT user_id, user_email, user_type_id FROM user WHERE user_id = ?");
$userCheck->bind_param('i', $employer_id);
$userCheck->execute();
$userResult = $userCheck->get_result();
if ($userRow = $userResult->fetch_assoc()) {
    echo "<p>✓ User ID: {$userRow['user_id']}, Email: {$userRow['user_email']}, Type: {$userRow['user_type_id']}</p>";
} else {
    echo "<p style='color: red;'>ERROR: Your user_id not found in database!</p>";
}
$userCheck->close();

// Check job posts
echo "<h3>Your job posts:</h3>";
$jobCheck = $conn->prepare("SELECT job_post_id, job_post_name FROM job_post WHERE user_id = ?");
$jobCheck->bind_param('i', $employer_id);
$jobCheck->execute();
$jobResult = $jobCheck->get_result();
if ($jobResult->num_rows === 0) {
    echo "<p style='color: orange;'>⚠ You have no job posts yet!</p>";
} else {
    echo "<p>✓ You have <strong>{$jobResult->num_rows}</strong> job post(s):</p>";
    echo "<ul>";
    while ($jobRow = $jobResult->fetch_assoc()) {
        echo "<li>Job ID: {$jobRow['job_post_id']} - {$jobRow['job_post_name']}</li>";
    }
    echo "</ul>";
}
$jobCheck->close();

// Check if applicants exist and have profiles
echo "<h3>Applicant Data Check:</h3>";
$applicantIds = [11, 12, 10]; // From the matches above
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Applicant ID</th><th>User Exists?</th><th>Email</th><th>Profile Exists?</th><th>First Name</th><th>Last Name</th><th>Resume Exists?</th></tr>";

foreach ($applicantIds as $aid) {
    echo "<tr>";
    echo "<td>$aid</td>";
    
    // Check user
    $userCheck = $conn->query("SELECT user_id, user_email FROM user WHERE user_id = $aid");
    if ($userCheck && $userCheck->num_rows > 0) {
        $userData = $userCheck->fetch_assoc();
        echo "<td style='color: green;'>✓ Yes</td>";
        echo "<td>{$userData['user_email']}</td>";
    } else {
        echo "<td style='color: red;'>✗ NO</td>";
        echo "<td>-</td>";
    }
    
    // Check profile
    $profileCheck = $conn->query("SELECT user_profile_first_name, user_profile_last_name FROM user_profile WHERE user_id = $aid");
    if ($profileCheck && $profileCheck->num_rows > 0) {
        $profileData = $profileCheck->fetch_assoc();
        echo "<td style='color: green;'>✓ Yes</td>";
        echo "<td>{$profileData['user_profile_first_name']}</td>";
        echo "<td>{$profileData['user_profile_last_name']}</td>";
    } else {
        echo "<td style='color: red;'>✗ NO</td>";
        echo "<td>-</td>";
        echo "<td>-</td>";
    }
    
    // Check resume
    $resumeCheck = $conn->query("SELECT resume_id FROM resume WHERE user_id = $aid");
    if ($resumeCheck && $resumeCheck->num_rows > 0) {
        $resumeData = $resumeCheck->fetch_assoc();
        echo "<td style='color: green;'>✓ Yes (ID: {$resumeData['resume_id']})</td>";
    } else {
        echo "<td style='color: red;'>✗ NO</td>";
    }
    
    echo "</tr>";
}
echo "</table>";

// Test the actual query from matches.php
echo "<h3>Testing Actual Query from matches.php:</h3>";
$testSql = "
SELECT
	m.match_id,
	m.applicant_id,
	m.job_post_id,
	u.user_email,
	up.user_profile_first_name,
	up.user_profile_last_name,
	r.resume_id,
	jp.job_post_name
FROM matches m
INNER JOIN user u ON m.applicant_id = u.user_id
LEFT JOIN resume r ON r.user_id = u.user_id
LEFT JOIN user_profile up ON u.user_id = up.user_id
INNER JOIN job_post jp ON m.job_post_id = jp.job_post_id
WHERE m.employer_id = $employer_id
ORDER BY m.matched_at DESC
";

$testResult = $conn->query($testSql);
if (!$testResult) {
    echo "<p style='color: red;'>Query failed: " . $conn->error . "</p>";
} elseif ($testResult->num_rows === 0) {
    echo "<p style='color: orange;'>⚠ Query returned 0 rows!</p>";
    echo "<p><strong>This means one of the INNER JOINs is failing:</strong></p>";
    echo "<ul>";
    echo "<li>Check if user records exist for applicant_ids</li>";
    echo "<li>Check if job_post records exist for job_post_ids</li>";
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✓ Query returned <strong>{$testResult->num_rows}</strong> rows:</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>match_id</th><th>applicant_id</th><th>job_post_id</th><th>email</th><th>first_name</th><th>last_name</th><th>resume_id</th><th>job_name</th></tr>";
    while ($row = $testResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['match_id']}</td>";
        echo "<td>{$row['applicant_id']}</td>";
        echo "<td>{$row['job_post_id']}</td>";
        echo "<td>{$row['user_email']}</td>";
        echo "<td>{$row['user_profile_first_name']}</td>";
        echo "<td>{$row['user_profile_last_name']}</td>";
        echo "<td>{$row['resume_id']}</td>";
        echo "<td>{$row['job_post_name']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

<hr>
<p><a href="matches.php">← Back to Matches Page</a></p>
