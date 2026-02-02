<?php
session_start();
require_once '../database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
	echo json_encode(['success' => false, 'error' => 'Not authenticated']);
	exit;
}

$userId = (int)$_SESSION['user_id'];
$jobPostId = isset($_POST['job_post_id']) ? (int)$_POST['job_post_id'] : 0;

if ($jobPostId <= 0) {
	echo json_encode(['success' => false, 'error' => 'Invalid job post ID']);
	exit;
}

// Verify the job belongs to this employer
$jobCheckSql = "SELECT job_post_id FROM job_post WHERE job_post_id = ? AND user_id = ?";
if ($stmt = $conn->prepare($jobCheckSql)) {
	$stmt->bind_param('ii', $jobPostId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($result->num_rows === 0) {
		echo json_encode(['success' => false, 'error' => 'Job not found']);
		exit;
	}
	$stmt->close();
}

function wm_calculate_match_score(
	int $jobPostId,
	int $resumeId,
	?int $jobEducationLevelId,
	?int $applicantEducationLevelId,
	?int $jobExperienceLevelId,
	?int $applicantExperienceLevelId,
	?int $jobCityId,
	?int $jobBarangayId,
	?int $applicantCityId,
	?int $applicantBarangayId,
	array $jobSkills,
	array $applicantSkills,
	?string $jobTitle = null,
	array $applicantJobTitles = []
): int {
	$totalScore = 0;
	
	// 1. Skills Match (35% weight) - most important
	$skillScore = 0;
	sort($jobSkills);
	sort($applicantSkills);
	if (!empty($jobSkills) && !empty($applicantSkills)) {
		$matchingSkills = array_intersect($jobSkills, $applicantSkills);
		$skillMatchRate = count($matchingSkills) / count($jobSkills);
		$skillScore = $skillMatchRate * 35;
	}
	
	// 2. Job Title Match (25% weight) - new factor
	$jobTitleScore = 0;
	if ($jobTitle && !empty($applicantJobTitles)) {
		$jobTitleLower = strtolower(trim($jobTitle));
		foreach ($applicantJobTitles as $appTitle) {
			$appTitleLower = strtolower(trim($appTitle));
			// Exact match
			if ($appTitleLower === $jobTitleLower) {
				$jobTitleScore = 25;
				break;
			}
			// Partial match (either contains the other)
			if (strpos($appTitleLower, $jobTitleLower) !== false || strpos($jobTitleLower, $appTitleLower) !== false) {
				$jobTitleScore = max($jobTitleScore, 17.5);
			}
			// Word overlap for partial credit
			$appWords = explode(' ', $appTitleLower);
			$jobWords = explode(' ', $jobTitleLower);
			$commonWords = array_intersect($appWords, $jobWords);
			if (!empty($commonWords)) {
				$overlapScore = count($commonWords) / max(count($appWords), count($jobWords));
				$jobTitleScore = max($jobTitleScore, $overlapScore * 12.5);
			}
		}
	}
	
	// 3. Location Match (20% weight) - adjusted
	$locationScore = 0;
	if ($jobCityId && $applicantCityId) {
		if ($jobCityId === $applicantCityId) {
			// Same city
			$locationScore = 20;
			// Bonus for same barangay
			if ($jobBarangayId && $applicantBarangayId && $jobBarangayId === $applicantBarangayId) {
				$locationScore = 20; // same city & barangay
			}
		} else {
			// Different city - partial score
			$locationScore = 8;
		}
	}
	
	// 4. Education Level Match (15% weight) - adjusted
	$educationScore = 0;
	if ($jobEducationLevelId && $applicantEducationLevelId) {
		if ($applicantEducationLevelId >= $jobEducationLevelId) {
			// Applicant meets or exceeds requirement
			$educationScore = 15;
		} else {
			// Below requirement - partial score
			$educationScore = 8;
		}
	} else if ($jobEducationLevelId === null) {
		// No education requirement - full score
		$educationScore = 15;
	}
	
	// 5. Experience Level Match (10% weight) - adjusted
	$experienceScore = 0;
	if ($jobExperienceLevelId && $applicantExperienceLevelId) {
		if ($applicantExperienceLevelId >= $jobExperienceLevelId) {
			// Applicant meets or exceeds requirement
			$experienceScore = 10;
		} else {
			// Below requirement - partial score
			$experienceScore = 5;
		}
	} else if ($jobExperienceLevelId === null) {
		// No experience requirement - full score
		$experienceScore = 10;
	}
	
	$totalScore = $skillScore + $jobTitleScore + $locationScore + $educationScore + $experienceScore;
	
	// Ensure score is between 0-100
	return max(0, min(100, (int)round($totalScore)));
}

// Get job details for matching
$jobEducationLevelId = null;
$jobExperienceLevelId = null;
$jobTitle = null;
$jobCityId = null;
$jobBarangayId = null;
$jobSkills = [];

$jobDetailsSql = "SELECT education_level_id, experience_level_id, job_post_name FROM job_post WHERE job_post_id = ?";
if ($stmt = $conn->prepare($jobDetailsSql)) {
	$stmt->bind_param('i', $jobPostId);
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		if ($row = $result->fetch_assoc()) {
			$jobEducationLevelId = $row['education_level_id'] ? (int)$row['education_level_id'] : null;
			$jobExperienceLevelId = $row['experience_level_id'] ? (int)$row['experience_level_id'] : null;
			$jobTitle = $row['job_post_name'] ?? null;
		}
	}
	$stmt->close();
}

$jobLocationSql = "SELECT city_mun_id, barangay_id FROM job_post_location WHERE job_post_id = ?";
if ($stmt = $conn->prepare($jobLocationSql)) {
	$stmt->bind_param('i', $jobPostId);
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		if ($row = $result->fetch_assoc()) {
			$jobCityId = $row['city_mun_id'] ? (int)$row['city_mun_id'] : null;
			$jobBarangayId = $row['barangay_id'] ? (int)$row['barangay_id'] : null;
		}
	}
	$stmt->close();
}

$jobSkillsSql = "SELECT skill_id FROM job_post_skills WHERE job_post_id = ?";
if ($stmt = $conn->prepare($jobSkillsSql)) {
	$stmt->bind_param('i', $jobPostId);
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$jobSkills[] = (int)$row['skill_id'];
		}
	}
	$stmt->close();
}

// Fetch candidates who liked this job
$candidatesSql = "SELECT
	u.user_id,
	r.resume_id,
	edu.education_level_id,
	exp.experience_level_id,
	loc.city_mun_id,
	loc.barangay_id
FROM applicant_job_swipes ajs
INNER JOIN user u ON ajs.applicant_id = u.user_id
LEFT JOIN resume r ON r.user_id = u.user_id
LEFT JOIN applicant_location loc ON loc.resume_id = r.resume_id
LEFT JOIN applicant_education edu ON edu.resume_id = r.resume_id
LEFT JOIN applicant_experience exp ON exp.resume_id = r.resume_id
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

$candidates = [];

if ($stmt = $conn->prepare($candidatesSql)) {
	$stmt->bind_param('ii', $jobPostId, $userId);
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		$groupedCandidates = [];
		
		while ($row = $result->fetch_assoc()) {
			$candidateId = (int)$row['user_id'];
			$resumeId = (int)$row['resume_id'];
			
			// Skip if already processed
			if (isset($groupedCandidates[$candidateId])) {
				continue;
			}
			
			// Get applicant skills
			$applicantSkills = [];
			if ($resumeId) {
				$skillsSql = "SELECT skill_id FROM applicant_skills WHERE resume_id = ?";
				if ($skillsStmt = $conn->prepare($skillsSql)) {
					$skillsStmt->bind_param('i', $resumeId);
					if ($skillsStmt->execute()) {
						$skillsResult = $skillsStmt->get_result();
						while ($skillRow = $skillsResult->fetch_assoc()) {
							$applicantSkills[] = (int)$skillRow['skill_id'];
						}
					}
					$skillsStmt->close();
				}
			}
			
			// Get applicant job titles
			$applicantJobTitles = [];
			if ($resumeId) {
				$titlesSql = "SELECT experience_name FROM applicant_experience WHERE resume_id = ? AND experience_name IS NOT NULL AND experience_name != ''";
				if ($titlesStmt = $conn->prepare($titlesSql)) {
					$titlesStmt->bind_param('i', $resumeId);
					if ($titlesStmt->execute()) {
						$titlesResult = $titlesStmt->get_result();
						while ($titleRow = $titlesResult->fetch_assoc()) {
							$applicantJobTitles[] = $titleRow['experience_name'];
						}
					}
					$titlesStmt->close();
				}
			}
			
			// Get highest education level for this applicant
			$applicantEducationLevelId = null;
			if ($resumeId) {
				$eduSql = "SELECT MAX(education_level_id) AS highest_education FROM applicant_education WHERE resume_id = ?";
				if ($eduStmt = $conn->prepare($eduSql)) {
					$eduStmt->bind_param('i', $resumeId);
					if ($eduStmt->execute()) {
						$eduResult = $eduStmt->get_result();
						if ($eduRow = $eduResult->fetch_assoc()) {
							$applicantEducationLevelId = $eduRow['highest_education'] ? (int)$eduRow['highest_education'] : null;
						}
					}
					$eduStmt->close();
				}
			}
			
			// Get highest experience level for this applicant
			$applicantExperienceLevelId = null;
			if ($resumeId) {
				$expSql = "SELECT MAX(experience_level_id) AS highest_experience FROM applicant_experience WHERE resume_id = ?";
				if ($expStmt = $conn->prepare($expSql)) {
					$expStmt->bind_param('i', $resumeId);
					if ($expStmt->execute()) {
						$expResult = $expStmt->get_result();
						if ($expRow = $expResult->fetch_assoc()) {
							$applicantExperienceLevelId = $expRow['highest_experience'] ? (int)$expRow['highest_experience'] : null;
						}
					}
					$expStmt->close();
				}
			}
			
			// Calculate match score
			$matchScore = wm_calculate_match_score(
				$jobPostId,
				$resumeId,
				$jobEducationLevelId,
				$applicantEducationLevelId,
				$jobExperienceLevelId,
				$applicantExperienceLevelId,
				$jobCityId,
				$jobBarangayId,
				$row['city_mun_id'] ? (int)$row['city_mun_id'] : null,
				$row['barangay_id'] ? (int)$row['barangay_id'] : null,
				$jobSkills,
				$applicantSkills,
				$jobTitle,
				$applicantJobTitles
			);
			
			$groupedCandidates[$candidateId] = [
				'user_id' => $candidateId,
				'resume_id' => $resumeId,
				'match_score' => $matchScore
			];
		}
		
		// Convert to indexed array
		$candidates = array_values($groupedCandidates);
	}
	$stmt->close();
}

// Sort by match score
usort($candidates, function($a, $b) {
	return $b['match_score'] - $a['match_score'];
});

$conn->close();

echo json_encode([
	'success' => true,
	'candidates' => $candidates
]);
