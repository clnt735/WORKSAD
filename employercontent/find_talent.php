<?php
session_start();
include 'navbar.php';
require_once '../database.php';

$activePage = 'find_talent';

if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}

$userId = (int)$_SESSION['user_id'];

function wm_job_posted_display(?string $dateTime): string
{
	if (!$dateTime) {
		return '—';
	}
	$timestamp = strtotime($dateTime);
	if (!$timestamp) {
		return '—';
	}
	$diffSeconds = max(0, time() - $timestamp);
	$diffDays = (int)floor($diffSeconds / 86400);
	if ($diffDays === 0) {
		$diffHours = (int)floor($diffSeconds / 3600);
		if ($diffHours >= 1) {
			return $diffHours . ' hour' . ($diffHours === 1 ? '' : 's') . ' ago';
		}
		$diffMinutes = max(1, (int)floor($diffSeconds / 60));
		return $diffMinutes . ' min' . ($diffMinutes === 1 ? '' : 's') . ' ago';
	}
	if ($diffDays < 7) {
		return $diffDays . ' day' . ($diffDays === 1 ? '' : 's') . ' ago';
	}
	return date('M d, Y', $timestamp);
}

function wm_format_experience_length(?string $startDate, ?string $endDate): string
{
	if (empty($startDate)) {
		return 'Experience not specified';
	}
	$start = strtotime($startDate);
	if (!$start) {
		return 'Experience not specified';
	}
	$end = $endDate && strtolower((string)$endDate) !== 'present'
		? strtotime($endDate)
		: time();
	if (!$end) {
		$end = time();
	}
	$diffSeconds = max(0, $end - $start);
	$diffYears = (int)floor($diffSeconds / (365 * 24 * 60 * 60));
	if ($diffYears >= 1) {
		return $diffYears . ' yr' . ($diffYears === 1 ? '' : 's') . ' experience';
	}
	$diffMonths = (int)max(1, floor($diffSeconds / (30 * 24 * 60 * 60)));
	return $diffMonths . ' mo' . ($diffMonths === 1 ? '' : 's') . ' experience';
}

function wm_format_location(?string $cityName, ?string $barangayName, ?string $addressLine): string
{
	$parts = [];
	$geoParts = array_filter([
		$barangayName ? trim($barangayName) : null,
		$cityName ? trim($cityName) : null,
	]);
	if (!empty($addressLine)) {
		$parts[] = trim($addressLine);
	}
	if (!empty($geoParts)) {
		$parts[] = implode(', ', $geoParts);
	}
	$location = implode(', ', array_filter($parts));
	return $location !== '' ? $location : 'Location not specified';
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
	// Ensure deterministic order
	sort($jobSkills);
	sort($applicantSkills);
	if (!empty($jobSkills) && !empty($applicantSkills)) {
		$matchingSkills = array_intersect($jobSkills, $applicantSkills);
		$skillMatchRate = count($matchingSkills) / count($jobSkills);
		$skillScore = $skillMatchRate * 40;
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

$jobs = [];
$jobsSql = "SELECT jp.job_post_id, jp.job_post_name, jp.job_location_id, jp.work_setup_id,
	jp.created_at,
	COALESCE(jc.job_category_name, 'Uncategorized') AS job_category_name,
	COALESCE(jt.job_type_name, 'Flexible') AS job_type_name,
	COALESCE(jp.vacancies, 1) AS vacancies
FROM job_post jp
LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
WHERE jp.user_id = ?
ORDER BY jp.created_at DESC";

if ($stmt = $conn->prepare($jobsSql)) {
	$stmt->bind_param('i', $userId);
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$jobs[] = $row;
		}
	}
	$stmt->close();
}



$jobLikesMap = [];
if (!empty($jobs)) {
	$jobIds = array_map('intval', array_column($jobs, 'job_post_id'));
	$placeholders = implode(',', array_fill(0, count($jobIds), '?'));

	// Get likes from applicant_job_swipes
	$likesSql = "SELECT job_post_id, COUNT(DISTINCT applicant_id) AS like_count
		FROM applicant_job_swipes
		WHERE swipe_type = 'like' AND job_post_id IN ($placeholders)
		GROUP BY job_post_id";
	$likesCounts = [];
	if ($stmt = $conn->prepare($likesSql)) {
		$typeString = str_repeat('i', count($jobIds));
		$params = [$typeString];
		foreach ($jobIds as $index => $jobId) {
			$params[] = &$jobIds[$index];
		}
		call_user_func_array([$stmt, 'bind_param'], $params);
		$stmt->execute();
		$stmt->bind_result($likedJobId, $likeCount);
		while ($stmt->fetch()) {
			$likesCounts[(int)$likedJobId] = (int)$likeCount;
		}
		$stmt->close();
	}

	// Get matches from matches table
	$matchesSql = "SELECT job_post_id, COUNT(DISTINCT applicant_id) AS match_count
		FROM matches
		WHERE job_post_id IN ($placeholders)
		GROUP BY job_post_id";
	$matchesCounts = [];
	if ($stmt = $conn->prepare($matchesSql)) {
		$typeString = str_repeat('i', count($jobIds));
		$params = [$typeString];
		foreach ($jobIds as $index => $jobId) {
			$params[] = &$jobIds[$index];
		}
		call_user_func_array([$stmt, 'bind_param'], $params);
		$stmt->execute();
		$stmt->bind_result($matchedJobId, $matchCount);
		while ($stmt->fetch()) {
			$matchesCounts[(int)$matchedJobId] = (int)$matchCount;
		}
		$stmt->close();
	}

	// Sum likes and matches for each job_post_id
	foreach ($jobIds as $jobId) {
		$jobLikesMap[$jobId] = ($likesCounts[$jobId] ?? 0) + ($matchesCounts[$jobId] ?? 0);
	}
}

foreach ($jobs as &$job) {
	$jobId = (int)($job['job_post_id'] ?? 0);
	$job['likes'] = $jobLikesMap[$jobId] ?? 0;
	$job['posted_display'] = wm_job_posted_display($job['created_at'] ?? null);
	$job['posted_raw'] = $job['created_at'] ?? null;
	$job['vacancies'] = (int)($job['vacancies'] ?? 0);
}
unset($job);

// Fetch candidates with completed resumes
$candidateDeck = [];

// Get the primary job post details for matching
$primaryJobPostId = null;
$primaryJobTitle = null;
$primaryJobEducationLevelId = null;
$primaryJobExperienceLevelId = null;
$primaryJobCityId = null;
$primaryJobBarangayId = null;
$primaryJobSkills = [];

// Use job_post_id from URL parameter if provided, otherwise use first job
if (isset($_GET['job_post_id']) && !empty($_GET['job_post_id'])) {
	$primaryJobPostId = (int)$_GET['job_post_id'];
} elseif (!empty($jobs)) {
	$primaryJobPostId = (int)$jobs[0]['job_post_id'];
}

if ($primaryJobPostId) {
	// Get job education and experience level
	$jobDetailsSql = "SELECT education_level_id, experience_level_id, job_post_name FROM job_post WHERE job_post_id = ?";
	if ($stmt = $conn->prepare($jobDetailsSql)) {
		$stmt->bind_param('i', $primaryJobPostId);
		if ($stmt->execute()) {
			$result = $stmt->get_result();
			if ($row = $result->fetch_assoc()) {
				$primaryJobEducationLevelId = $row['education_level_id'] ? (int)$row['education_level_id'] : null;
				$primaryJobExperienceLevelId = $row['experience_level_id'] ? (int)$row['experience_level_id'] : null;
				$primaryJobTitle = $row['job_post_name'] ?? null;
			}
		}
		$stmt->close();
	}
	
	// Get job location
	$jobLocationSql = "SELECT city_mun_id, barangay_id FROM job_post_location WHERE job_post_id = ?";
	if ($stmt = $conn->prepare($jobLocationSql)) {
		$stmt->bind_param('i', $primaryJobPostId);
		if ($stmt->execute()) {
			$result = $stmt->get_result();
			if ($row = $result->fetch_assoc()) {
				$primaryJobCityId = $row['city_mun_id'] ? (int)$row['city_mun_id'] : null;
				$primaryJobBarangayId = $row['barangay_id'] ? (int)$row['barangay_id'] : null;
			}
		}
		$stmt->close();
	}
	
	// Get job skills
	$jobSkillsSql = "SELECT skill_id FROM job_post_skills WHERE job_post_id = ?";
	if ($stmt = $conn->prepare($jobSkillsSql)) {
		$stmt->bind_param('i', $primaryJobPostId);
		if ($stmt->execute()) {
			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$primaryJobSkills[] = (int)$row['skill_id'];
			}
		}
		$stmt->close();
	}
}

// Show only applicants who liked the selected job post
$candidatesSql = "SELECT
	u.user_id,
	u.user_email,
	COALESCE(up.user_profile_first_name, '') AS user_profile_first_name,
	COALESCE(up.user_profile_last_name, '') AS user_profile_last_name,
	up.user_profile_photo,
	r.resume_id,
	r.bio,
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
		  AND eas.job_post_id = ajs.job_post_id
	)
ORDER BY ajs.created_at DESC
LIMIT 100";


if ($stmt = $conn->prepare($candidatesSql)) {
	$stmt->bind_param('ii', $primaryJobPostId, $userId);
	error_log("DEBUG: Querying for job_post_id=$primaryJobPostId, employer_id=$userId");
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		error_log("DEBUG: Query executed, fetching results...");
		$groupedCandidates = [];
		$rowCount = 0;
		while ($row = $result->fetch_assoc()) {
			$rowCount++;
			error_log("DEBUG: Processing row #$rowCount for user_id={$row['user_id']}");
			$candidateId = (int)($row['user_id'] ?? 0);
			if ($candidateId === 0) {
				continue;
			}
			if (!isset($groupedCandidates[$candidateId])) {
				$firstName = trim((string)($row['user_profile_first_name'] ?? ''));
				$lastName = trim((string)($row['user_profile_last_name'] ?? ''));
				$fullName = trim($firstName . ' ' . $lastName);
				if ($fullName === '') {
					$emailParts = explode('@', (string)($row['user_email'] ?? ''));
					$fullName = ucfirst($emailParts[0] ?? 'Candidate');
				}
				$photo = '../uploads/profile_pics/default-avatar.png';
				$rawPhoto = $row['user_profile_photo'] ?? '';
				if (!empty($rawPhoto)) {
					$photoPath = '../uploads/profile_pics/' . $rawPhoto;
					if (file_exists($photoPath)) {
						$photo = $photoPath;
					}
				}
				if ($photo === '../uploads/profile_pics/default-avatar.png') {
					// Use SVG data URI for default avatar with person silhouette (Facebook-style)
					$photo = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'%3E%3Crect fill='%23e4e6eb' width='400' height='400'/%3E%3Ccircle cx='200' cy='140' r='80' fill='%23bcc0c4'/%3E%3Cellipse cx='200' cy='340' rx='130' ry='100' fill='%23bcc0c4'/%3E%3C/svg%3E";
				}
				$jobTitle = trim((string)($row['experience_name'] ?? ''));
				if ($jobTitle === '') {
					$jobTitle = 'Professional';
				}
				$experienceLabel = $jobTitle;
				$bioText = !empty($row['bio']) ? trim($row['bio']) : 'No bio available.';
				$locationLabel = wm_format_location(
					$row['city_mun_name'] ?? null,
					$row['barangay_name'] ?? null,
					$row['address_line'] ?? null
				);
				$educationSummaryParts = [];
				if (!empty($row['education_level_name'])) {
					$educationSummaryParts[] = $row['education_level_name'];
				}
				if (!empty($row['education_school'])) {
					$educationSummaryParts[] = $row['education_school'];
				}
				$educationSummary = implode(' @ ', $educationSummaryParts);
				$bio = '';
				if (!empty($row['experience_description'])) {
					$bio = $row['experience_description'];
				} elseif ($educationSummary !== '') {
					$bio = 'Studied at ' . $educationSummary;
				} else {
					$bio = 'No professional summary provided.';
				}
				
				// Get applicant skills for matching
				$applicantSkills = [];
				$resumeId = $row['resume_id'] ?? null;
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
				
				// Get applicant job titles from experience
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
				
				// Use the experience_level_id from the query result
				$applicantExperienceLevelId = $row['experience_level_id'] ? (int)$row['experience_level_id'] : null;
				
				// Calculate match score
				error_log("DEBUG: About to calculate match score for user_id=$candidateId");
				error_log("DEBUG: primaryJobSkills=" . json_encode($primaryJobSkills));
				error_log("DEBUG: applicantSkills=" . json_encode($applicantSkills));
				error_log("DEBUG: primaryJobTitle=" . ($primaryJobTitle ?? 'null'));
				error_log("DEBUG: applicantJobTitles=" . json_encode($applicantJobTitles));
				$matchScore = wm_calculate_match_score(
					$primaryJobPostId ?? 0,
					(int)($row['resume_id'] ?? 0),
					$primaryJobEducationLevelId,
					$row['education_level_id'] ? (int)$row['education_level_id'] : null,
					$primaryJobExperienceLevelId,
					$applicantExperienceLevelId,
					$primaryJobCityId,
					$primaryJobBarangayId,
					$row['city_mun_id'] ? (int)$row['city_mun_id'] : null,
					$row['barangay_id'] ? (int)$row['barangay_id'] : null,
					$primaryJobSkills,
					$applicantSkills,
					$primaryJobTitle,
					$applicantJobTitles
				);
				error_log("DEBUG: Match score calculated = $matchScore");
				
				$groupedCandidates[$candidateId] = [
					'user_id' => $candidateId,
					'resume_id' => (int)($row['resume_id'] ?? 0),
					'name' => $fullName,
					'headline' => $jobTitle,
					'title' => $jobTitle,
					'match_score' => $matchScore,
					'experience' => $experienceLabel,
					'location' => $locationLabel,
					'photo' => $photo,
					'salary' => 'Negotiable',
					'availability' => 'Available',
					'bio' => $bio,
					'resume_bio' => $bioText,
					'skills' => [],
					'skill_ids' => $applicantSkills, // Store for potential use
				];
			}
		}
		
		error_log("DEBUG: Processed $rowCount rows, grouped into " . count($groupedCandidates) . " unique candidates");
		
		// Now fetch skill names for display (top 5)
		foreach ($groupedCandidates as $candidateId => &$candidate) {
			if (!empty($candidate['skill_ids'])) {
				$skillIds = array_slice($candidate['skill_ids'], 0, 5);
				$placeholders = implode(',', array_fill(0, count($skillIds), '?'));
				$skillNamesSql = "SELECT name FROM skills WHERE skill_id IN ($placeholders)";
				if ($skillNamesStmt = $conn->prepare($skillNamesSql)) {
					$types = str_repeat('i', count($skillIds));
					$params = [$types];
					foreach ($skillIds as $index => $skillId) {
						$params[] = &$skillIds[$index];
					}
					call_user_func_array([$skillNamesStmt, 'bind_param'], $params);
					if ($skillNamesStmt->execute()) {
						$skillNamesResult = $skillNamesStmt->get_result();
						while ($skillNameRow = $skillNamesResult->fetch_assoc()) {
							$candidate['skills'][] = $skillNameRow['name'];
						}
					}
					$skillNamesStmt->close();
				}
			}
			if (empty($candidate['skills'])) {
				$candidate['skills'] = ['General Skills'];
			}
			unset($candidate['skill_ids']); // Clean up temporary data
		}
		unset($candidate);
		
		// Sort candidates by match score (highest first)
		usort($groupedCandidates, function($a, $b) {
			return $b['match_score'] - $a['match_score'];
		});
		
		error_log("DEBUG: After sorting, have " . count($groupedCandidates) . " candidates");
		
		$candidateDeck = array_values($groupedCandidates);
		error_log("DEBUG: Final candidateDeck has " . count($candidateDeck) . " candidates");
	}
	$stmt->close();
}

// Debug: Final count
error_log("Total candidates in deck: " . count($candidateDeck));

$conn->close();

$primaryJobData = $jobs[0] ?? null;
$primaryJob = $primaryJobData['job_post_name'] ?? 'Any Role';
$primaryJobVacancies = $primaryJobData['vacancies'] ?? 0;
$primaryJobLikes = $primaryJobData['likes'] ?? 0;
$primaryJobPostedDisplay = $primaryJobData['posted_display'] ?? '—';
$primaryJobPostedRaw = $primaryJobData['posted_raw'] ?? '';


?>

<main class="talent-matcher">
	<section class="filter-section">
		<div class="filter-content">
			<header>
				<p class="eyebrow">smart matches</p>
				<h1>Swipe through curated applicants</h1>
				<p>These candidates show strong overlap with your open roles. Swipe right to shortlist or left to pass—everything stays private until you schedule a chat.</p>
			</header>
			<div class="job-focus">
				<div>
					<p class="label">Focused role</p>
					<h2 id="job-focus-name"><?php echo htmlspecialchars($primaryJob); ?></h2>
					<p class="muted">Switch roles to refresh the deck.</p>
				</div>
				<label class="job-select">
					<span>Filter by job</span>
					<select id="job-select">
						<?php if (empty($jobs)): ?>
						<option>No job posts yet</option>
						<?php else: ?>
						<?php 
						$selectedJobPostId = isset($_GET['job_post_id']) ? (int)$_GET['job_post_id'] : (int)$jobs[0]['job_post_id'];
						foreach ($jobs as $index => $job): 
							$isThisSelected = ((int)$job['job_post_id'] === $selectedJobPostId);
						?>
						<option value="<?php echo (int)$job['job_post_id']; ?>"
							data-job-name="<?php echo htmlspecialchars($job['job_post_name'], ENT_QUOTES, 'UTF-8'); ?>"
							data-vacancies="<?php echo (int)$job['vacancies']; ?>"
							data-likes="<?php echo (int)$job['likes']; ?>"
							data-posted="<?php echo htmlspecialchars($job['posted_display'], ENT_QUOTES, 'UTF-8'); ?>"
							data-posted-raw="<?php echo htmlspecialchars($job['posted_raw'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
							<?php echo $isThisSelected ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($job['job_post_name']); ?>
						</option>
						<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</label>
			</div>
			<ul class="job-insights">
				<li>
					<span class="metric" id="job-vacancies-metric"><?php echo number_format((int)$primaryJobVacancies); ?></span>
					<p class="muted">Vacancies</p>
				</li>
				<li>
					<span class="metric" id="job-likes-metric"><?php echo number_format((int)$primaryJobLikes); ?></span>
					<p class="muted">Likes</p>
				</li>
				<li>
					<span class="metric" id="job-posted-metric"><?php echo htmlspecialchars($primaryJobPostedDisplay); ?></span>
					<p class="muted">Posted</p>
				</li>
			</ul>
		</div>
	</section>
	
	<div class="section-divider"></div>

	<section class="swipe-section">
		<div class="deck-shell">
			<?php if (empty($candidateDeck)): ?>
			<div class="empty-state" data-empty style="opacity: 1; pointer-events: auto;">
				<h3>No candidates available</h3>
				<p>There are currently no candidates with completed resumes. Check back later!</p>
			</div>
			<?php else: ?>
			<?php foreach ($candidateDeck as $index => $candidate): ?>
			<article class="candidate-card" data-index="<?php echo (int)$index; ?>" data-user_id="<?php echo (int)$candidate['user_id']; ?>" <?php echo $index === 0 ? 'data-active="true"' : ''; ?>>
				<span class="match-badge">
					<i class="fa-solid fa-lock"></i>
					<?php echo (int)$candidate['match_score']; ?>% Match
				</span>
				
				<div class="card-photo" style="background-image: url('<?php echo htmlspecialchars($candidate['photo']); ?>');"></div>
				
				<div class="card-content">
					<div class="card-header">
						<h3><?php echo htmlspecialchars($candidate['name']); ?></h3>
						<p class="job-title"><?php echo htmlspecialchars($candidate['title']); ?></p>
					</div>
					
					<div class="meta-row">
						<span><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($candidate['experience']); ?></span>
						<span><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($candidate['location']); ?></span>
					</div>
					
					<p class="candidate-bio"><?php echo htmlspecialchars($candidate['bio']); ?></p>
					
					<div class="info-grid">
						<div class="info-item">
							<i class="fa-solid fa-dollar-sign"></i>
							<div>
								<p class="info-label">Salary</p>
								<p class="info-value"><?php echo htmlspecialchars($candidate['salary']); ?></p>
							</div>
						</div>
						<div class="info-item">
							<i class="fa-solid fa-calendar-check"></i>
							<div>
								<p class="info-label">Available</p>
								<p class="info-value"><?php echo htmlspecialchars($candidate['availability']); ?></p>
							</div>
						</div>
					</div>
					
					<div class="skills-section">
						<p class="section-label"><i class="fa-solid fa-star"></i> Top Skills</p>
						<div class="pill-row">
							<?php foreach ($candidate['skills'] as $skill): ?>
							<span><?php echo htmlspecialchars($skill); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					
					<?php if (!empty($candidate['resume_bio'])): ?>
					<div class="resume-bio-section">
						<p class="bio-text"><?php echo htmlspecialchars($candidate['resume_bio']); ?></p>
					</div>
					<?php endif; ?>
				</div>
			</article>
			<?php endforeach; ?>
			<div class="empty-state" data-empty>
				<h3>No more candidates</h3>
				<p>Check back later or adjust your job filters to refresh the deck.</p>
			</div>
			<?php endif; ?>
		</div>
		<div class="swipe-actions">
			<button type="button" class="swipe-btn ghost" data-action="dislike">
				<i class="fa-solid fa-xmark" aria-hidden="true"></i>
				<span>Skip</span>
			</button>
			<button type="button" class="swipe-btn ghost" data-action="undo">
				<i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
			</button>
			<button type="button" class="swipe-btn primary" data-action="like">
				<i class="fa-solid fa-heart" aria-hidden="true"></i>
				<span>Save</span>
			</button>
		</div>
	</section>
</main>

<style>
:root {
	--talent-bg: #f5f7fb;
	--talent-surface: #ffffff;
	--talent-border: #e1e6f0;
	--talent-muted: #5f677a;
	--talent-primary: #2563eb;
	--talent-like: #16a34a;
	--talent-pass: #ef4444;
}

body {
	background: var(--talent-bg);
	margin: 0;
}

.talent-matcher {
	max-width: 1320px;
	/* margin: var(--nav-height, 88px) auto 1.5rem; */  /* remove this */
	margin: 0 auto 1.5rem;                               /* new */
	padding-top: var(--nav-height, 88px);                /* new */
	padding: 0 clamp(1rem, 4vw, 3rem) 2rem;
	display: grid;
	grid-template-columns: 380px 1px 1fr;
	gap: 0;
	font-family: "Poppins", "Segoe UI", Tahoma, sans-serif;
	align-items: stretch;
	min-height: 700px;
}

.filter-section {
	padding: 2rem;
	display: flex;
	flex-direction: column;
	overflow-y: auto;
	overflow-x: hidden;
	background: var(--talent-surface);
	border-radius: 1.5rem;
	box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
	margin-right: 1.5rem;
	margin-top: 32px;
}

.filter-content {
	display: flex;
	flex-direction: column;
	gap: 1.5rem;
}

.filter-section header h1 {
	margin: 0.25rem 0 0;
	font-size: 1.65rem;
	color: #0f172a;
	line-height: 1.3;
}

.filter-section header p {
	margin: 0.5rem 0 0;
	color: var(--talent-muted);
	font-size: 0.95rem;
	line-height: 1.5;
}

.section-divider {
	width: 1px;
	background: linear-gradient(to bottom, 
		transparent 0%, 
		var(--talent-border) 10%, 
		var(--talent-border) 90%, 
		transparent 100%);
	position: relative;
}

.eyebrow {
	text-transform: uppercase;
	letter-spacing: 0.1em;
	font-size: 0.75rem;
	color: var(--talent-muted);
	margin: 0;
}

.job-focus {
	display: flex;
	flex-direction: column;
	gap: 0.85rem;
	padding: 1.25rem;
	border: 1px solid var(--talent-border);
	border-radius: 1rem;
	background: #f9fbff;
}

.job-focus h2 {
	margin: 0.15rem 0;
	font-size: 1.25rem;
	color: #0f172a;
}

.job-focus .muted,
.filter-section .muted {
	color: var(--talent-muted);
	font-size: 0.9rem;
}

.job-select {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
	font-size: 0.9rem;
	color: var(--talent-muted);
}

.job-select select {
	border-radius: 0.75rem;
	border: 1px solid var(--talent-border);
	padding: 0.65rem 0.85rem;
	font-family: inherit;
	font-size: 0.95rem;
}

.job-insights {
	list-style: none;
	margin: 0;
	padding: 0;
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
	gap: 0.75rem;
}

.job-insights li {
	border: 1px solid var(--talent-border);
	border-radius: 0.75rem;
	padding: 0.85rem;
	background: #fff;
}

.job-insights .metric {
	display: block;
	font-weight: 600;
	font-size: 1.25rem;
	color: #0f172a;
}

.swipe-section {
	padding: 1.75rem 0 2rem 1.75rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;
	justify-content: center;
	align-items: center;
	position: relative;
	overflow: hidden;
	background: var(--talent-surface);
	border-radius: 1.5rem;
	box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
	margin-left: 1.5rem;
	margin-top: 32px; /* add gap below navbar */
	min-height: 800px;
}

.deck-shell {
	position: relative;
	width: 100%;
	max-width: 420px;
	height: 720px;
	margin: 0 auto;
}

.candidate-card {
	position: absolute;
	top: 0;
	left: 50%;
	transform: translateX(-50%) translateY(40px) scale(0.96);
	width: 100%;
	max-width: 420px;
	height: 100%;
	max-height: none;
	min-height: 0;
	border-radius: 1.75rem;
	background: var(--talent-surface);
	border: none;
	box-shadow: 0 8px 32px rgba(15, 23, 42, 0.12);
	padding: 0;
	display: flex;
	flex-direction: column;
	opacity: 0;
	pointer-events: none;
	transition: opacity 0.4s ease, transform 0.4s ease;
	user-select: none;
	overflow: hidden;
	touch-action: pan-y pinch-zoom;
}

.candidate-card[data-active="true"] {
	opacity: 1;
	transform: translateX(-50%) translateY(0) scale(1);
	z-index: 10;
	pointer-events: auto;
	touch-action: none;
}

.candidate-card[data-active="true"] + .candidate-card {
	opacity: 0.3;
	transform: translateX(-50%) translateY(20px) scale(0.97);
	z-index: 9;
}

.candidate-card[data-active="true"] + .candidate-card + .candidate-card {
	opacity: 0.15;
	transform: translateX(-50%) translateY(30px) scale(0.95);
	z-index: 8;
}

.match-badge {
	position: absolute;
	top: 1rem;
	left: 1rem;
	background: var(--talent-primary);
	color: white;
	padding: 0.5rem 1rem;
	border-radius: 999px;
	font-weight: 600;
	font-size: 0.85rem;
	z-index: 2;
	display: flex;
	align-items: center;
	gap: 0.4rem;
	box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.match-badge i {
	font-size: 0.75rem;
}

.card-photo {
	width: 100%;
	height: 240px;
	background-size: cover;
	background-position: center;
	background-color: #e5e7eb;
	position: relative;
}

.card-content {
	padding: 1.5rem;
	display: flex;
	flex-direction: column;
	gap: 1rem;
	overflow-y: auto;
	max-height: 440px;
}

.card-header {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.candidate-card h3 {
	margin: 0;
	font-size: 1.5rem;
	color: #0f172a;
	line-height: 1.2;
	font-weight: 600;
}

.candidate-card p {
	margin: 0;
}

.job-title {
	color: var(--talent-muted);
	font-size: 1rem;
	font-weight: 500;
}

.meta-row {
	display: flex;
	gap: 1rem;
	flex-wrap: wrap;
	font-size: 0.9rem;
	color: var(--talent-muted);
}

.meta-row span {
	display: flex;
	align-items: center;
	gap: 0.35rem;
}

.meta-row i {
	font-size: 0.85rem;
	color: var(--talent-primary);
}

.info-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 0.75rem;
}

.info-item {
	display: flex;
	align-items: flex-start;
	gap: 0.75rem;
	padding: 1rem;
	background: #f9fafb;
	border-radius: 0.75rem;
	border: 1px solid var(--talent-border);
}

.info-item i {
	font-size: 1.1rem;
	color: var(--talent-primary);
	margin-top: 0.2rem;
}

.info-label {
	font-size: 0.75rem;
	color: var(--talent-muted);
	text-transform: uppercase;
	letter-spacing: 0.05em;
	font-weight: 500;
	margin-bottom: 0.2rem;
}

.info-value {
	font-size: 0.9rem;
	color: #0f172a;
	font-weight: 600;
}

.candidate-bio,
.candidate-facts p {
	color: var(--talent-muted);
	font-size: 0.95rem;
	line-height: 1.6;
}

.candidate-bio {
	line-height: 1.6;
}

.skills-section {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.section-label {
	font-size: 0.85rem;
	color: #0f172a;
	font-weight: 600;
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.section-label i {
	color: var(--talent-primary);
	font-size: 0.8rem;
}

.pill-row {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}

.pill-row span {
	background: #e0e7ff;
	color: var(--talent-primary);
	padding: 0.4rem 0.85rem;
	border-radius: 999px;
	font-size: 0.85rem;
	font-weight: 500;
}

.resume-bio-section {
	margin-top: 1rem;
	padding: 1rem;
	background: #f9fafb;
	border-radius: 0.75rem;
	border: 1px solid var(--talent-border);
}

.resume-bio-section .bio-text {
	margin: 0;
	color: #374151;
	font-size: 0.9rem;
	line-height: 1.6;
	font-style: italic;
}

.swipe-actions {
	display: flex;
	justify-content: center;
	align-items: center;
	gap: 1rem;
	margin-top: 0.5rem;
	position: relative;
	z-index: 100;
}

.swipe-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: 0.5rem;
	border-radius: 999px;
	padding: 1rem 1.75rem;
	font-weight: 600;
	font-size: 1rem;
	cursor: pointer;
	border: none;
	transition: transform 0.2s ease, box-shadow 0.2s ease;
	min-width: 120px;
}

.swipe-btn.ghost {
	background: #fff;
	color: var(--talent-pass);
	border: 2px solid var(--talent-border);
}

.swipe-btn.ghost:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
	border-color: var(--talent-pass);
}

.swipe-btn[data-action="undo"] {
	background: #f3f4f6;
	color: #9ca3af;
	border: 2px solid #e5e7eb;
	cursor: not-allowed;
	opacity: 0.6;
}

.swipe-btn[data-action="undo"]:hover {
	transform: none;
	box-shadow: none;
	border-color: #e5e7eb;
}

.swipe-btn[data-action="undo"].active {
	background: #fff;
	color: #4b5563;
	border: 2px solid var(--talent-border);
	cursor: pointer;
	opacity: 1;
}

.swipe-btn[data-action="undo"].active:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(75, 85, 99, 0.15);
	border-color: #9ca3af;
}

.swipe-btn.primary {
	background: var(--talent-like);
	color: #fff;
	box-shadow: 0 15px 35px rgba(22, 163, 74, 0.35);
}

.swipe-btn.primary:hover {
	transform: translateY(-2px);
	box-shadow: 0 20px 45px rgba(22, 163, 74, 0.45);
}

.candidate-card.swipe-left {
	animation: swipe-left 0.35s forwards;
}

.candidate-card.swipe-right {
	animation: swipe-right 0.35s forwards;
}

@keyframes swipe-left {
	to {
		transform: translateX(-200%) rotate(-12deg);
		opacity: 0;
	}
}

@keyframes swipe-right {
	to {
		transform: translateX(100%) rotate(12deg);
		opacity: 0;
	}
}

.deck-shell .empty-state {
	position: absolute;
	inset: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	text-align: center;
	background: var(--talent-surface);
	border-radius: 1.5rem;
	border: 1px dashed var(--talent-border);
	color: var(--talent-muted);
	opacity: 0;
	pointer-events: none;
}

.deck-shell[data-empty] .empty-state {
	opacity: 1;
	pointer-events: auto;
}

@media (max-width: 1100px) {
	.talent-matcher {
		grid-template-columns: 340px 1px 1fr;
	}
	.filter-section {
		padding: 1.75rem;
		margin-right: 1.25rem;
	}
	.swipe-section {
		padding: 1.25rem 0 1.25rem 1.5rem;
		margin-left: 1.25rem;
	}
}

@media (max-width: 960px) {
	.talent-matcher {
		grid-template-columns: 1fr;
		gap: 0;
		min-height: auto;
	}
	.section-divider {
		display: none;
	}
	.filter-section {
		padding: 1.75rem;
		order: 2;
		margin: 1.5rem 0 0 0;
	}
	.filter-content {
		gap: 1.25rem;
	}
	.swipe-section {
		padding: 1.25rem 1rem 1.5rem;
		order: 1;
		min-height: 700px;
		margin: 0;
		justify-content: flex-start;
	}
	.deck-shell {
		height: auto;
		min-height: 580px;
		display: flex;
		align-items: flex-start;
		justify-content: center;
	}
	.candidate-card {
		max-width: 100%;
		height: 100%;
		min-height: 0;
		max-height: none;
	}
}

@media (max-width: 640px) {
	.talent-matcher {
		/* keep the same half‑inch gap on mobile */
		padding-top: 0.5in;
	}
	.filter-section {
		padding: 1.25rem;
	}
	.filter-section header h1 {
		font-size: 1.35rem;
		line-height: 1.35;
	}
	.filter-section header p {
		font-size: 0.9rem;
		line-height: 1.5;
	}
	.filter-content {
		gap: 1rem;
	}
	.job-focus {
		padding: 1rem;
	}
	.job-insights {
		grid-template-columns: repeat(3, 1fr);
		gap: 0.5rem;
	}
	.job-insights li {
		padding: 0.65rem;
	}
	.job-insights .metric {
		font-size: 1.1rem;
	}
	.swipe-section {
		padding: 0.75rem 0.75rem 1.25rem;
		min-height: auto;
		margin-top: 32px;
	}
	.candidate-card {
		max-width: 100%;
		height: 100%;
		min-height: 0;
		max-height: none;
		top: 0;
	}
	.card-photo {
		height: 180px;
	}
	.card-content {
		padding: 1rem;
		max-height: none;        /* allow full height */
		flex: 1;                 /* fill remaining space below photo */
		min-height: 0;           /* enable proper flex sizing */
		overflow-y: auto;        /* keep scroll if content exceeds */
	}
	.candidate-card h3 {
		font-size: 1.25rem;
	}
	.job-title {
		font-size: 0.95rem;
	}
	.info-grid {
		grid-template-columns: 1fr;
	}
	.deck-shell {
		min-height: 520px;
		max-height: 660px;
		height: 640px;
		margin-bottom: -48px;
		position: relative;
	}
	.swipe-actions {
		flex-direction: row;
		gap: 0.65rem;
		margin-top: 1.5rem;
		padding-top: 1.5rem;
	}
	.swipe-btn {
		flex: 1;
		min-width: auto;
		padding: 0.9rem 1rem;
		font-size: 0.95rem;
	}
	.swipe-btn i {
		font-size: 1.1rem;
	}
}

@media (max-width: 480px) {
	.talent-matcher {
		padding: 0 0.75rem 0.85rem;
	}
	.filter-section {
		padding: 1rem;
	}
	.eyebrow {
		font-size: 0.7rem;
	}
	.filter-section header h1 {
		font-size: 1.25rem;
		line-height: 1.3;
	}
	.filter-section header p {
		font-size: 0.85rem;
		line-height: 1.5;
		margin-top: 0.4rem;
	}
	.job-insights {
		grid-template-columns: 1fr;
		gap: 0.5rem;
	}
	.swipe-section {
		padding: 0.5rem 0.5rem 1rem;
		min-height: auto;
		margin-top: 32px;
	}
	.candidate-card {
		height: 100%;
		min-height: 0;
		max-height: none;
		border-radius: 1.5rem;
		top: 0;
	}
	.card-photo {
		height: 210px;
	}
	.card-content {
		padding: 0.9rem;
		max-height: none;
		flex: 1;
		min-height: 0;
		overflow-y: auto;
	}
	.candidate-card h3 {
		font-size: 1.15rem;
	}
	.job-title {
		font-size: 0.9rem;
	}
	.match-badge {
		padding: 0.35rem 0.75rem;
		font-size: 0.75rem;
		top: 0.85rem;
		left: 0.85rem;
	}
	.candidate-bio {
		font-size: 0.85rem;
		line-height: 1.5;
	}
	.meta-row {
		font-size: 0.85rem;
		gap: 0.75rem;
	}
	.info-item {
		padding: 0.85rem;
	}
	.info-label {
		font-size: 0.7rem;
	}
	.info-value {
		font-size: 0.85rem;
	}
	.section-label {
		font-size: 0.8rem;
	}
	.pill-row span {
		font-size: 0.75rem;
		padding: 0.3rem 0.6rem;
	}
	.deck-shell {
		min-height: 540px;
		max-height: 640px;
		height: 620px;
		margin-bottom: -48px;
		position: relative;
	}
	.swipe-actions {
		gap: 0.5rem;
		margin-top: 1.5rem;
		padding-top: 1.5rem;
	}
	.swipe-btn {
		padding: 0.8rem 0.85rem;
		min-width: auto;
		font-size: 0.9rem;
	}
	.swipe-btn span {
		display: inline;
	}
	.swipe-btn i {
		font-size: 1rem;
	}
}
</style>

<script>
window.addEventListener('DOMContentLoaded', () => {
	const jobSelect = document.getElementById('job-select');
	const jobNameHeading = document.getElementById('job-focus-name');
	const vacancyMetric = document.getElementById('job-vacancies-metric');
	const likesMetric = document.getElementById('job-likes-metric');
	const postedMetric = document.getElementById('job-posted-metric');

	const formatNumber = (value) => {
		const parsed = Number(value ?? 0);
		return new Intl.NumberFormat().format(Number.isFinite(parsed) ? parsed : 0);
	};

	const parseSQLDateTime = (value) => {
		if (!value || typeof value !== 'string') {
			return null;
		}
		const parts = value.match(/^(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2})(?::(\d{2}))?/);
		if (!parts) {
			return null;
		}
		const [, year, month, day, hour, minute, second] = parts;
		return new Date(
			Number(year),
			Number(month) - 1,
			Number(day),
			Number(hour),
			Number(minute),
			Number(second ?? 0)
		);
	};

	const formatRelativeTime = (value) => {
		const date = parseSQLDateTime(value);
		if (!date) {
			return null;
		}
		const now = new Date();
		let diffMs = now.getTime() - date.getTime();
		if (diffMs < 0) {
			diffMs = 0;
		}
		const diffMinutes = Math.floor(diffMs / 60000);
		if (diffMinutes < 1) {
			return 'Just now';
		}
		if (diffMinutes < 60) {
			return `${diffMinutes} min${diffMinutes === 1 ? '' : 's'} ago`;
		}
		const diffHours = Math.floor(diffMinutes / 60);
		if (diffHours < 24) {
			return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
		}
		const diffDays = Math.floor(diffHours / 24);
		if (diffDays < 7) {
			return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
		}
		return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
	};

	const refreshPostedMetric = (option = null) => {
		if (!postedMetric) {
			return;
		}
		const selected = option || (jobSelect ? jobSelect.selectedOptions[0] : null);
		if (!selected) {
			postedMetric.textContent = '—';
			return;
		}
		const relative = formatRelativeTime(selected.dataset.postedRaw);
		postedMetric.textContent = relative ?? (selected.dataset.posted || '—');
	};

	const updateJobInsights = () => {
		if (!jobSelect) return;
		const selected = jobSelect.selectedOptions[0];
		if (!selected) return;
		const { jobName, vacancies, likes } = selected.dataset;
		if (jobNameHeading && jobName) {
			jobNameHeading.textContent = jobName;
		}
		if (vacancyMetric) {
			vacancyMetric.textContent = formatNumber(vacancies);
		}
		if (likesMetric) {
			likesMetric.textContent = formatNumber(likes);
		}
		refreshPostedMetric(selected);
	};

	if (jobSelect) {
		jobSelect.addEventListener('change', () => {
			// Reload page to fetch candidates who liked the selected job post
			const selectedJobPostId = jobSelect.value;
			if (selectedJobPostId) {
				window.location.href = `find_talent.php?job_post_id=${encodeURIComponent(selectedJobPostId)}`;
			} else {
				window.location.href = 'find_talent.php';
			}
		});
		updateJobInsights();
		refreshPostedMetric();
		setInterval(refreshPostedMetric, 60 * 1000);
	}

    const deck = document.querySelector('.deck-shell');
    const cards = Array.from(document.querySelectorAll('.candidate-card'));
    const emptyState = deck.querySelector('.empty-state');
    let activeIndex = 0;

    function updateDeckState() {
        cards.forEach((card, index) => {
            const position = index - activeIndex;
            
            // Remove any previous classes
            card.removeAttribute('data-active');
            
            if (position < 0) {
                // Already swiped - hide completely
                card.style.display = 'none';
            } else if (position === 0) {
                // Current active card - front of stack
                card.setAttribute('data-active', 'true');
                card.style.display = 'flex';
                card.style.opacity = '1';
                card.style.transform = 'translateX(-50%) translateY(0) scale(1)';
                card.style.zIndex = 100;
            } else if (position === 1) {
                // Next card in stack
                card.style.display = 'flex';
                card.style.opacity = '0.6';
                card.style.transform = 'translateX(-50%) translateY(4px) scale(0.98)';
                card.style.zIndex = 99;
            } else if (position === 2) {
                // Third card in stack
                card.style.display = 'flex';
                card.style.opacity = '0.3';
                card.style.transform = 'translateX(-50%) translateY(8px) scale(0.96)';
                card.style.zIndex = 98;
            } else {
                // Cards further back - hide
                card.style.display = 'none';
                card.style.opacity = '0';
            }
        });
        
        const isEmpty = activeIndex >= cards.length;
        deck.toggleAttribute('data-empty', isEmpty);
        if (isEmpty && emptyState) {
            emptyState.style.opacity = '1';
            emptyState.style.pointerEvents = 'auto';
        }
        
        // Update undo button state
        const undoBtn = document.querySelector('[data-action="undo"]');
        if (undoBtn) {
            if (activeIndex > 0) {
                undoBtn.classList.add('active');
            } else {
                undoBtn.classList.remove('active');
            }
        }
    }

	function getSelectedJobPostId() {
		const jobSelect = document.getElementById('job-select');
		if (jobSelect && jobSelect.value) {
			return jobSelect.value;
		}
		return null;
	}

	function saveSwipe(applicantId, swipeType) {
		const jobPostId = getSelectedJobPostId();
		fetch('swipe_applicant.php', {
			method: 'POST',
			headers: {'Content-Type': 'application/x-www-form-urlencoded'},
			body: `applicant_id=${encodeURIComponent(applicantId)}&swipe_type=${encodeURIComponent(swipeType)}&job_post_id=${encodeURIComponent(jobPostId)}`
		})
		.then(res => res.json())
		.then(data => {
			if (!data.success) {
				console.error('Swipe not saved:', data.error);
			}
		})
		.catch(err => {
			console.error('Swipe error:', err);
		});
	}

    function processSwipe(direction) {
        if (activeIndex >= cards.length) return;
        const current = cards[activeIndex];
        const className = direction === 'right' ? 'swipe-right' : 'swipe-left';

        // Get applicant_id from card dataset
        const applicantId = current.getAttribute('data-user_id') || current.querySelector('[data-user_id]')?.getAttribute('data-user_id') || current.getAttribute('data-id') || current.dataset.user_id || current.dataset.id || null;
        if (applicantId) {
            saveSwipe(applicantId, direction === 'right' ? 'like' : 'dislike');
        }

        // Disable pointer events during animation
        current.style.pointerEvents = 'none';
        current.classList.add(className);

        setTimeout(() => {
            current.classList.remove(className);
            current.removeAttribute('data-active');
            current.style.display = 'none';
            activeIndex += 1;
            updateDeckState();
        }, 350);
    }

    function attachDrag(card) {
        let startX = 0;
        let startY = 0;
        let currentX = 0;
        let currentY = 0;
        let isDragging = false;
        let pointerId = null;
        let directionLocked = false;
        let isVerticalScroll = false;

        const handlePointerMove = (event) => {
            if (!isDragging) return;
            
            const clientX = event.type.includes('touch') ? event.touches[0].clientX : event.clientX;
            const clientY = event.type.includes('touch') ? event.touches[0].clientY : event.clientY;
            const dx = clientX - startX;
            const dy = clientY - startY;
            
            // Detect if user is scrolling vertically (mobile only)
            const isMobile = window.innerWidth <= 640;
            if (isMobile && !directionLocked) {
                const absDx = Math.abs(dx);
                const absDy = Math.abs(dy);
                
                // If moved more than 10px, lock direction
                if (absDx > 10 || absDy > 10) {
                    directionLocked = true;
                    isVerticalScroll = absDy > absDx;
                }
            }
            
            // If vertical scroll detected, don't prevent default and don't process drag
            if (isVerticalScroll) {
                return;
            }
            
            event.preventDefault();
            currentX = dx;
            currentY = dy;
            const rotation = currentX / 20;
            const verticalOffset = Math.abs(currentX) * 0.05;
            card.style.transform = `translateX(calc(-50% + ${currentX}px)) translateY(${verticalOffset}px) rotate(${rotation}deg)`;
            card.style.transition = 'none';
        };

        const handlePointerUp = (event) => {
            if (!isDragging) return;
            if (pointerId !== null && event.pointerId !== undefined) {
                card.releasePointerCapture(pointerId);
            }
            card.removeEventListener('pointermove', handlePointerMove);
            card.removeEventListener('pointerup', handlePointerUp);
            card.removeEventListener('touchmove', handlePointerMove);
            card.removeEventListener('touchend', handlePointerUp);
            isDragging = false;
            
            card.style.transition = 'transform 0.4s ease, opacity 0.4s ease';
            
            // Higher threshold on mobile to prevent accidental swipes
            const isMobile = window.innerWidth <= 640;
            const swipeThreshold = isMobile ? 150 : 120;
            
            if (Math.abs(currentX) > swipeThreshold) {
                processSwipe(currentX > 0 ? 'right' : 'left');
            } else {
                // Reset to original position
                card.style.transform = 'translateX(-50%) translateY(0) scale(1)';
            }
            
            // Reset tracking variables
            directionLocked = false;
            isVerticalScroll = false;
        };

        card.addEventListener('pointerdown', (event) => {
            if (!card.hasAttribute('data-active')) return;
            pointerId = event.pointerId;
            card.setPointerCapture(pointerId);
            startX = event.clientX;
            startY = event.clientY;
            currentX = 0;
            currentY = 0;
            isDragging = true;
            directionLocked = false;
            isVerticalScroll = false;
            card.addEventListener('pointermove', handlePointerMove);
            card.addEventListener('pointerup', handlePointerUp, { once: true });
        });

        // Fallback for older mobile browsers
        card.addEventListener('touchstart', (event) => {
            if (!card.hasAttribute('data-active')) return;
            const touch = event.touches[0];
            startX = touch.clientX;
            startY = touch.clientY;
            currentX = 0;
            currentY = 0;
            isDragging = true;
            directionLocked = false;
            isVerticalScroll = false;
            card.addEventListener('touchmove', handlePointerMove, { passive: false });
            card.addEventListener('touchend', handlePointerUp, { once: true });
        });
    }

    cards.forEach(attachDrag);

    const dislikeBtn = document.querySelector('[data-action="dislike"]');
    const likeBtn = document.querySelector('[data-action="like"]');
    const undoBtn = document.querySelector('[data-action="undo"]');
    
    if (dislikeBtn) {
        dislikeBtn.addEventListener('click', () => processSwipe('left'));
    }
    if (likeBtn) {
        likeBtn.addEventListener('click', () => processSwipe('right'));
    }
	if (undoBtn) {
		undoBtn.addEventListener('click', () => {
			if (activeIndex > 0) {
				activeIndex -= 1;
				const previousCard = cards[activeIndex];
				previousCard.style.display = 'flex';
				previousCard.style.pointerEvents = 'auto';
				previousCard.classList.remove('swipe-left', 'swipe-right');
				updateDeckState();

				// Remove swipe from DB
				const applicantId = previousCard.getAttribute('data-user_id');
				const jobPostId = getSelectedJobPostId();
				if (applicantId && jobPostId) {
					fetch('undo_swipe_applicant.php', {
						method: 'POST',
						headers: {'Content-Type': 'application/x-www-form-urlencoded'},
						body: `applicant_id=${encodeURIComponent(applicantId)}&job_post_id=${encodeURIComponent(jobPostId)}`
					})
					.then(res => res.json())
					.then(data => {
						if (!data.success) {
							console.error('Undo swipe not saved:', data.error);
						}
					})
					.catch(err => {
						console.error('Undo swipe error:', err);
					});
				}
			}
		});
	}

    // Initialize deck state
    updateDeckState();
});
</script>
