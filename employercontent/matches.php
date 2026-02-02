
<?php
session_start();
include "navbar.php";
require_once '../database.php';

$activePage = 'matches';

// Ensure employer is logged in
if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}
$employer_id = (int)$_SESSION['user_id'];

// DEBUG: Show employer_id at the top of page
echo "<!-- DEBUG: employer_id = $employer_id -->";

// DEBUG: Check what's in the application table
$debugAppSql = "SELECT application_id, job_post_id, applicant_id, status FROM application LIMIT 10";
$debugResult = $conn->query($debugAppSql);
if ($debugResult) {
	$apps = [];
	while ($appRow = $debugResult->fetch_assoc()) {
		$apps[] = $appRow;
	}
	echo "<!-- DEBUG: Applications in database: " . htmlspecialchars(json_encode($apps)) . " -->";
}

// DEBUG: Check what's in the matches table for this employer
$debugMatchSql = "SELECT match_id, employer_id, applicant_id, job_post_id FROM matches WHERE employer_id = ? LIMIT 10";
$debugMatchStmt = $conn->prepare($debugMatchSql);
if ($debugMatchStmt) {
	$debugMatchStmt->bind_param('i', $employer_id);
	$debugMatchStmt->execute();
	$debugMatchResult = $debugMatchStmt->get_result();
	$matchRecords = [];
	while ($matchRow = $debugMatchResult->fetch_assoc()) {
		$matchRecords[] = $matchRow;
	}
	echo "<!-- DEBUG: Matches in database for employer $employer_id: " . htmlspecialchars(json_encode($matchRecords)) . " -->";
	$debugMatchStmt->close();
}

// DEBUG: Test the JOIN directly
$debugJoinSql = "SELECT m.match_id, m.applicant_id, m.job_post_id, app.application_id, app.status 
	FROM matches m 
	LEFT JOIN application app ON app.applicant_id = m.applicant_id AND app.job_post_id = m.job_post_id
	WHERE m.employer_id = ? LIMIT 10";
$debugJoinStmt = $conn->prepare($debugJoinSql);
if ($debugJoinStmt) {
	$debugJoinStmt->bind_param('i', $employer_id);
	$debugJoinStmt->execute();
	$debugJoinResult = $debugJoinStmt->get_result();
	$joinResults = [];
	while ($joinRow = $debugJoinResult->fetch_assoc()) {
		$joinResults[] = $joinRow;
	}
	echo "<!-- DEBUG: JOIN results: " . htmlspecialchars(json_encode($joinResults)) . " -->";
	$debugJoinStmt->close();
}

// Fetch matches for this employer using the matches table
// The matches table is automatically populated when both parties like each other:
// 1. Applicant liked the job post (applicant_job_swipes.swipe_type = 'like')
// 2. Employer liked the applicant (employer_applicant_swipes.swipe_type = 'like')
// 3. Both reference the same job_post_id
$matches = [];
$matchesSql = "
SELECT
	m.match_id,
	m.applicant_id,
	m.job_post_id,
	m.matched_at,
	r.resume_id,
	r.updated_at AS resume_updated_at,
	u.user_email,
	up.user_profile_first_name,
	up.user_profile_last_name,
	up.user_profile_photo,
	exp.experience_name,
	exp.experience_company,
	exp.start_date AS experience_start_date,
	exp.end_date AS experience_end_date,
	exp.experience_description,
	exp.experience_level_id,
	edu.school_name AS education_school,
	edu.education_level_id,
	lvl.education_level_name,
	loc.address_line AS applicant_address_line,
	loc.city_mun_id,
	loc.barangay_id,
	acity.city_mun_name AS applicant_city_name,
	abrgy.barangay_name AS applicant_barangay_name,
	jp.job_post_name,
	ws.work_setup_name AS job_work_setup,
	jpl.address_line AS job_address_line,
	jcity.city_mun_name AS job_city_mun_name,
	jbrgy.barangay_name AS job_barangay_name,
	app.application_id,
	app.status AS application_status,
	app.created_at AS applied_at,
	app.resume_type,
	app.resume_file_path
FROM matches m
INNER JOIN user u ON m.applicant_id = u.user_id
LEFT JOIN resume r ON r.user_id = u.user_id
LEFT JOIN user_profile up ON u.user_id = up.user_id
LEFT JOIN applicant_location loc ON loc.resume_id = r.resume_id
LEFT JOIN city_mun acity ON acity.city_mun_id = loc.city_mun_id
LEFT JOIN barangay abrgy ON abrgy.barangay_id = loc.barangay_id
LEFT JOIN applicant_experience exp ON exp.resume_id = r.resume_id
LEFT JOIN applicant_education edu ON edu.resume_id = r.resume_id
LEFT JOIN education_level lvl ON lvl.education_level_id = edu.education_level_id
INNER JOIN job_post jp ON m.job_post_id = jp.job_post_id
LEFT JOIN work_setup ws ON ws.work_setup_id = jp.work_setup_id
LEFT JOIN job_post_location jpl ON jpl.job_location_id = jp.job_location_id
LEFT JOIN city_mun jcity ON jcity.city_mun_id = jpl.city_mun_id
LEFT JOIN barangay jbrgy ON jbrgy.barangay_id = jpl.barangay_id
LEFT JOIN application app ON app.applicant_id = m.applicant_id AND app.job_post_id = m.job_post_id
WHERE m.employer_id = ?
ORDER BY m.matched_at DESC
";

if ($stmt = $conn->prepare($matchesSql)) {
	$stmt->bind_param('i', $employer_id);
	error_log("DEBUG matches.php: Querying matches for employer_id=$employer_id");
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		$rowCount = 0;
		while ($row = $result->fetch_assoc()) {
			$rowCount++;
			error_log("DEBUG matches.php: Processing row #$rowCount, match_id={$row['match_id']}");
			
			// DEBUG: Log application data for this row
			$appIdFromJoin = $row['application_id'] ?? 'NULL';
			$appStatusFromJoin = $row['application_status'] ?? 'NULL';
			error_log("DEBUG matches.php: Row #$rowCount - applicant_id={$row['applicant_id']}, job_post_id={$row['job_post_id']}, application_id={$appIdFromJoin}, app_status={$appStatusFromJoin}");
			
			$matchId = (int)($row['match_id'] ?? 0);
			if ($matchId === 0) {
				continue;
			}
			if (!isset($matches[$matchId])) {
				$jobTitle = trim((string)($row['experience_name'] ?? ''));
				if ($jobTitle === '') {
					$jobTitle = 'Professional';
				}
				$matches[$matchId] = [
					'match_id' => $matchId,
					'applicant_id' => (int)($row['applicant_id'] ?? 0),
					'job_post_id' => (int)($row['job_post_id'] ?? 0),
					'matched_at' => $row['matched_at'] ?? null,
					'resume_id' => (int)($row['resume_id'] ?? 0),
					'first_name' => $row['user_profile_first_name'] ?? '',
					'last_name' => $row['user_profile_last_name'] ?? '',
					'email' => $row['user_email'] ?? '',
					'photo' => getProfilePhoto($row['user_profile_photo'] ?? '', (int)($row['applicant_id'] ?? 0), $row['user_profile_first_name'] ?? ''),
					'job_title' => $jobTitle,
					'experience_length' => wm_format_experience_length($row['experience_start_date'] ?? null, $row['experience_end_date'] ?? null),
					'job_post_name' => $row['job_post_name'] ?? 'Open role',
					'job_location' => !empty($row['job_address_line']) ? trim($row['job_address_line']) : 'Location not specified',
					'job_work_setup' => $row['job_work_setup'] ?? 'Flexible',
					'summary' => wm_candidate_summary_from_row($row),
					'skills' => [],
					'application_id' => !empty($row['application_id']) ? (int)$row['application_id'] : null,
					'application_status' => $row['application_status'] ?? null,
					'applied_at' => $row['applied_at'] ?? null,
					// IMPORTANT: Only set resume_type if application exists - NO defaults
					'resume_type' => !empty($row['application_id']) ? ($row['resume_type'] ?? null) : null,
					'resume_file_path' => !empty($row['application_id']) ? ($row['resume_file_path'] ?? null) : null,
				];
			} else {
				// Update application data if it exists in a subsequent row
				if (!empty($row['application_id']) && empty($matches[$matchId]['application_id'])) {
					$matches[$matchId]['application_id'] = (int)$row['application_id'];
					$matches[$matchId]['application_status'] = $row['application_status'] ?? null;
					$matches[$matchId]['applied_at'] = $row['applied_at'] ?? null;
					// IMPORTANT: Only set from actual application data - NO defaults
					$matches[$matchId]['resume_type'] = $row['resume_type'] ?? null;
					$matches[$matchId]['resume_file_path'] = $row['resume_file_path'] ?? null;
				}
			}
		}
		error_log("DEBUG matches.php: Total rows fetched=$rowCount, matches array size=" . count($matches));
	} else {
		echo "<!-- DEBUG: Query execution failed: " . htmlspecialchars($stmt->error) . " -->";
		error_log("DEBUG matches.php: Query execution failed: " . $stmt->error);
	}
	$stmt->close();
} else {
	echo "<!-- DEBUG: Failed to prepare statement: " . htmlspecialchars($conn->error) . " -->";
	error_log("DEBUG matches.php: Failed to prepare statement: " . $conn->error);
}

// Fetch skills for each match separately
foreach ($matches as $matchId => &$match) {
	$resumeId = $match['resume_id'] ?? 0;
	if ($resumeId > 0) {
		$skillsSql = "SELECT s.name 
			FROM applicant_skills askill
			INNER JOIN skills s ON s.skill_id = askill.skill_id
			WHERE askill.resume_id = ?
			LIMIT 5";
		if ($skillsStmt = $conn->prepare($skillsSql)) {
			$skillsStmt->bind_param('i', $resumeId);
			if ($skillsStmt->execute()) {
				$skillsResult = $skillsStmt->get_result();
				while ($skillRow = $skillsResult->fetch_assoc()) {
					$match['skills'][] = $skillRow['name'];
				}
			}
			$skillsStmt->close();
		}
	}
	if (empty($match['skills'])) {
		$match['skills'] = ['No skills listed'];
	}
}
unset($match);

// DEBUG: Output the matches array
echo "<!-- DEBUG: Matches array after skills fetch: " . htmlspecialchars(json_encode(array_map(function($m) {
	return ['match_id' => $m['match_id'], 'name' => $m['first_name'] . ' ' . $m['last_name'], 'job' => $m['job_post_name']];
}, $matches))) . " -->";

// Fetch ALL job posts created by this employer (for roleFilter dropdown)
$employerJobPosts = [];
$jobSql = "SELECT job_post_id, job_post_name FROM job_post WHERE user_id = ? ORDER BY job_post_name ASC";
if ($jobStmt = $conn->prepare($jobSql)) {
	$jobStmt->bind_param('i', $employer_id);
	if ($jobStmt->execute()) {
		$jobResult = $jobStmt->get_result();
		while ($jobRow = $jobResult->fetch_assoc()) {
			$employerJobPosts[] = $jobRow;
		}
	}
	$jobStmt->close();
}

function getInitials($first, $last, $email) {
	$first = trim($first);
	$last = trim($last);
	if ($first || $last) {
		return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
	}
	// fallback to email
	$parts = explode('@', $email);
	return strtoupper(mb_substr($parts[0], 0, 2));
}

function wm_format_experience_length(?string $startDate, ?string $endDate): string {
	if (empty($startDate)) {
		return 'Experience not specified';
	}
	$start = strtotime($startDate);
	if (!$start) {
		return 'Experience not specified';
	}
	$end = $endDate && strtolower((string)$endDate) !== 'present' ? strtotime($endDate) : time();
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

function wm_format_location(?string $cityName, ?string $barangayName, ?string $addressLine): string {
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
	$label = implode(', ', array_filter($parts));
	return $label !== '' ? $label : 'Location not specified';
}

function wm_candidate_summary_from_row(array $row): string {
	$candidates = [
		trim((string)($row['experience_description'] ?? '')),
		trim((string)($row['achievement_description'] ?? '')),
	];
	$achievementName = trim((string)($row['achievement_name'] ?? ''));
	if ($achievementName !== '') {
		$candidates[] = 'Achievement: ' . $achievementName;
	}
	$educationLevel = trim((string)($row['education_level_name'] ?? ''));
	$educationSchool = trim((string)($row['education_school'] ?? ''));
	if ($educationLevel !== '' || $educationSchool !== '') {
		$educationSummary = trim($educationLevel . ($educationLevel && $educationSchool ? ' @ ' : '') . $educationSchool);
		if ($educationSummary !== '') {
			$candidates[] = $educationSummary;
		}
	}
	foreach ($candidates as $text) {
		if ($text !== '') {
			return $text;
		}
	}
	return 'No profile summary provided.';
}

function getProfilePhoto($photo, $user_id, $firstName = 'U') {
	if ($photo && file_exists('../uploads/profile_pics/' . $photo)) {
		return '../uploads/profile_pics/' . $photo;
	}
	// Return SVG data URI for default avatar with person silhouette (Facebook-style)
	return "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 400'%3E%3Crect fill='%23e4e6eb' width='400' height='400'/%3E%3Ccircle cx='200' cy='140' r='80' fill='%23bcc0c4'/%3E%3Cellipse cx='200' cy='340' rx='130' ry='100' fill='%23bcc0c4'/%3E%3C/svg%3E";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Candidate Matches - WorkMuna</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
	<style>
		:root {
			--match-bg: #f8fafc;
			--match-surface: #ffffff;
			--match-border: #e2e8f0;
			--match-muted: #64748b;
			--match-heading: #0f172a;
			--match-accent: #3b82f6;
			--match-accent-hover: #2563eb;
			--match-success: #10b981;
			--match-warning: #f59e0b;
			--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
			--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
			--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
			--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
		}

		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			color: var(--match-heading);
			line-height: 1.6;
		}

		.matches-shell {
			min-height: calc(100vh - 80px);
			padding: 2rem clamp(1rem, 5vw, 3rem) 3rem;
		}

		.matches-dashboard {
			max-width: 1400px;
			margin: 0 auto;
			display: flex;
			flex-direction: column;
			gap: 2rem;
		}

		.page-header {
			background: var(--match-surface);
			border-radius: 1rem;
			padding: 2rem;
			box-shadow: var(--shadow-sm);
			border: 1px solid var(--match-border);
		}

		.page-header h1 {
			margin: 0 0 0.5rem 0;
			font-size: clamp(1.75rem, 3vw, 2.25rem);
			font-weight: 700;
			color: var(--match-heading);
			letter-spacing: -0.025em;
		}

		.page-header p {
			margin: 0;
			color: var(--match-muted);
			font-size: 1rem;
		}

		.stats-bar {
			display: flex;
			gap: 1.5rem;
			margin-top: 1.25rem;
			flex-wrap: wrap;
		}

		.stat-item {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.stat-item i {
			color: var(--match-accent);
			font-size: 1.1rem;
		}

		.stat-item strong {
			color: var(--match-heading);
			font-weight: 600;
		}

		.filters-card {
			background: var(--match-surface);
			border-radius: 1rem;
			padding: 1.5rem;
			box-shadow: var(--shadow-sm);
			border: 1px solid var(--match-border);
		}

		.filters-bar {
			display: flex;
			flex-wrap: wrap;
			gap: 1rem;
			align-items: center;
		}

		.search-field {
			flex: 1;
			min-width: 250px;
			position: relative;
		}

		.search-field i {
			position: absolute;
			left: 1rem;
			top: 50%;
			transform: translateY(-50%);
			color: var(--match-muted);
			pointer-events: none;
		}

		.search-field input {
			width: 100%;
			border: 2px solid var(--match-border);
			border-radius: 0.75rem;
			padding: 0.75rem 1rem 0.75rem 2.75rem;
			font-size: 0.95rem;
			font-family: inherit;
			transition: all 0.2s ease;
			background: var(--match-surface);
		}

		.search-field input:focus {
			outline: none;
			border-color: var(--match-accent);
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
		}

		.select-pill {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			border: 2px solid var(--match-border);
			border-radius: 0.75rem;
			padding: 0.75rem 1rem;
			background: var(--match-surface);
			transition: all 0.2s ease;
		}

		.select-pill:focus-within {
			border-color: var(--match-accent);
			box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
		}

		.select-pill label {
			font-size: 0.875rem;
			color: var(--match-muted);
			font-weight: 600;
			white-space: nowrap;
		}

		.select-pill select {
			border: none;
			background: transparent;
			font-weight: 600;
			font-family: inherit;
			color: var(--match-heading);
			cursor: pointer;
			font-size: 0.95rem;
			min-width: 120px;
		}

		.select-pill select:focus {
			outline: none;
		}

		.matches-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
			gap: 1rem;
			align-items: stretch;
		}

		.candidate-card {
			border: none;
			border-radius: 1.75rem;
			padding: 0;
			background: var(--match-surface);
			box-shadow: 0 8px 32px rgba(15, 23, 42, 0.12);
			display: flex;
			flex-direction: column;
			gap: 0;
			transition: all 0.3s ease;
			position: relative;
			overflow: hidden;
			height: 100%;
		}

		.candidate-card:hover {
			transform: translateY(-4px);
			box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
		}

		.card-photo {
			width: 100%;
			height: 200px;
			background-size: cover;
			background-position: center;
			background-color: #e5e7eb;
			position: relative;
		}

		.card-content {
			padding: 1rem;
			display: flex;
			flex-direction: column;
			gap: 0.65rem;
			flex: 1;
		}

		.card-header {
			display: flex;
			flex-direction: column;
			gap: 0.1rem;
		}

		.candidate-header {
			display: flex;
			align-items: flex-start;
			gap: 0.5rem;
			justify-content: space-between;
		}

		.candidate-card h3 {
			margin: 0;
			font-size: 1.25rem;
			color: #0f172a;
			line-height: 1.2;
			font-weight: 600;
		}

		.job-title {
			color: var(--match-muted);
			font-size: 0.9rem;
			font-weight: 500;
			margin: 0;
		}

		.match-badge {
			position: absolute;
			top: 0.75rem;
			left: 0.75rem;
			background: var(--match-accent);
			color: white;
			padding: 0.4rem 0.75rem;
			border-radius: 999px;
			font-weight: 600;
			font-size: 0.75rem;
			z-index: 2;
			display: flex;
			align-items: center;
			gap: 0.35rem;
			box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
		}

		.match-badge i {
			font-size: 0.7rem;
		}

		.meta-row {
			display: flex;
			gap: 0.6rem;
			flex-wrap: wrap;
			font-size: 0.85rem;
			color: var(--match-muted);
		}

		.meta-row span {
			display: flex;
			align-items: center;
			gap: 0.35rem;
		}

		.meta-row i {
			font-size: 0.8rem;
			color: var(--match-accent);
		}

		.info-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 0.4rem;
		}

		.info-item {
			display: flex;
			align-items: flex-start;
			gap: 0.5rem;
			padding: 0.65rem;
			background: #f9fafb;
			border-radius: 0.5rem;
			border: 1px solid var(--match-border);
		}

		.info-item i {
			font-size: 1rem;
			color: var(--match-accent);
			margin-top: 0.15rem;
		}

		.info-label {
			font-size: 0.7rem;
			color: var(--match-muted);
			text-transform: uppercase;
			letter-spacing: 0.05em;
			font-weight: 500;
			margin-bottom: 0.15rem;
			display: block;
		}

		.info-value {
			font-size: 0.85rem;
			color: #0f172a;
			font-weight: 600;
			display: block;
		}

		.skills-section {
			display: flex;
			flex-direction: column;
			gap: 0.4rem;
		}

		.section-label {
			font-size: 0.8rem;
			color: #0f172a;
			font-weight: 600;
			display: flex;
			align-items: center;
			gap: 0.4rem;
			margin: 0;
		}

		.section-label i {
			color: var(--match-accent);
			font-size: 0.75rem;
		}

		.pill-row {
			display: flex;
			flex-wrap: wrap;
			gap: 0.4rem;
		}

		.pill-row span {
			background: #e0e7ff;
			color: var(--match-accent);
			padding: 0.3rem 0.65rem;
			border-radius: 999px;
			font-size: 0.8rem;
			font-weight: 500;
			transition: all 0.2s ease;
		}

		.pill-row span:hover {
			background: var(--match-accent);
			color: white;
			transform: scale(1.05);
		}

		.match-details {
			background: #f9fafb;
			border-radius: 0.75rem;
			padding: 1rem;
			border: 1px solid var(--match-border);
		}

		.match-details .meta-row {
			gap: 0.75rem;
		}

		.match-details strong {
			color: var(--match-accent);
			font-weight: 700;
		}

		.candidate-bio,
		.candidate-summary {
			color: var(--match-muted);
			font-size: 0.875rem;
			line-height: 1.5;
			margin: 0;
		}

		.actions-row {
			display: flex;
			gap: 0.5rem;
			align-items: center;
			margin-top: auto;
		}

		.action-btn {
			flex: 1;
			border-radius: 0.5rem;
			padding: 0.65rem 1rem;
			font-weight: 600;
			font-size: 0.875rem;
			border: 2px solid var(--match-border);
			background: var(--match-surface);
			cursor: pointer;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.4rem;
		}

		.action-btn:hover {
			transform: translateY(-2px);
			box-shadow: var(--shadow-md);
		}

		.action-btn.primary {
			background: linear-gradient(135deg, var(--match-accent), var(--match-accent-hover));
			border-color: var(--match-accent);
			color: #fff;
		}

		.action-btn.primary:hover {
			background: linear-gradient(135deg, var(--match-accent-hover), #1d4ed8);
		}

		

		.empty-state {
			grid-column: 1 / -1;
			text-align: center;
			padding: 4rem 2rem;
			background: var(--match-surface);
			border-radius: 1rem;
			border: 2px dashed var(--match-border);
		}

		.empty-state i {
			font-size: 4rem;
			color: var(--match-muted);
			margin-bottom: 1rem;
			opacity: 0.5;
		}

		.empty-state h3 {
			margin: 1rem 0 0.5rem 0;
			color: var(--match-heading);
			font-size: 1.5rem;
		}

		.empty-state p {
			color: var(--match-muted);
			margin: 0;
			font-size: 1rem;
		}

		@media (max-width: 768px) {
			.matches-grid {
				grid-template-columns: 1fr;
			}

			.actions-row {
				flex-direction: column;
			}

			.action-btn {
				width: 100%;
			}

			.filters-bar {
				flex-direction: column;
			}

			.search-field,
			.select-pill {
				width: 100%;
			}

			.stats-bar {
				flex-direction: column;
				gap: 0.75rem;
			}

			.info-grid {
				grid-template-columns: 1fr;
			}

			.card-photo {
				height: 165px;
			}
		}

		@media (max-width: 480px) {
			.matches-shell {
				padding: 1rem;
			}

			.page-header {
				padding: 1.25rem;
			}

			.card-photo {
				height: 255px;
			}

			.card-content {
				padding: 0.85rem;
				gap: 0.55rem;
			}

			.candidate-card h3 {
				font-size: 1.1rem;
			}

			.job-title {
				font-size: 0.85rem;
			}

			.match-badge {
				padding: 0.35rem 0.6rem;
				font-size: 0.7rem;
			}

			.meta-row {
				font-size: 0.8rem;
				gap: 0.5rem;
			}

			.info-item {
				padding: 0.6rem;
			}

			.candidate-bio {
				font-size: 0.825rem;
			}

			.action-btn {
				padding: 0.6rem 0.85rem;
				font-size: 0.825rem;
			}

			.pill-row span {
				padding: 0.25rem 0.55rem;
				font-size: 0.75rem;
			}
		}

		/* Smooth animations */
		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(10px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		.candidate-card {
			animation: fadeIn 0.5s ease forwards;
		}

		.candidate-card:nth-child(1) { animation-delay: 0.05s; }
		.candidate-card:nth-child(2) { animation-delay: 0.1s; }
		.candidate-card:nth-child(3) { animation-delay: 0.15s; }
		.candidate-card:nth-child(4) { animation-delay: 0.2s; }
		
		/* Modal Styles */
		.modal-overlay {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.6);
			z-index: 9998;
			animation: fadeIn 0.3s ease;
		}
		
		.modal-overlay.active {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 20px;
		}
		
		.modal-content {
			background: white;
			border-radius: 16px;
			max-width: 700px;
			width: 100%;
			max-height: 90vh;
			overflow-y: auto;
			box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
			animation: slideUp 0.3s ease;
		}
		
		@keyframes slideUp {
			from {
				opacity: 0;
				transform: translateY(30px);
			}
			to {
				opacity: 1;
				transform: translateY(0);
			}
		}
		
		.modal-header {
			padding: 24px;
			border-bottom: 2px solid var(--match-border);
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		
		.modal-header h2 {
			margin: 0;
			font-size: 1.5rem;
			color: var(--match-heading);
		}
		
		.modal-close {
			background: none;
			border: none;
			font-size: 28px;
			color: var(--match-muted);
			cursor: pointer;
			padding: 0;
			width: 32px;
			height: 32px;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: 50%;
			transition: all 0.2s;
		}
		
		.modal-close:hover {
			background: var(--match-border);
			color: var(--match-heading);
		}
		
		.modal-body {
			padding: 24px;
		}
		
		.modal-section {
			margin-bottom: 24px;
		}
		
		.modal-section-title {
			font-size: 1.1rem;
			font-weight: 600;
			color: var(--match-heading);
			margin-bottom: 12px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		
		.modal-section-title i {
			color: var(--match-accent);
		}
		
		.answer-item {
			background: var(--gray-50);
			padding: 16px;
			border-radius: 8px;
			margin-bottom: 12px;
			border-left: 3px solid var(--match-accent);
		}
		
		.answer-question {
			font-weight: 600;
			color: var(--match-heading);
			margin-bottom: 6px;
			font-size: 0.95rem;
		}
		
		.answer-text {
			color: var(--match-muted);
			font-size: 0.9rem;
			line-height: 1.5;
		}
		
		.schedule-section {
			background: #f0f9ff;
			padding: 20px;
			border-radius: 12px;
			margin-top: 20px;
		}
		
		.schedule-title {
			font-size: 1rem;
			font-weight: 600;
			color: var(--match-heading);
			margin-bottom: 16px;
			display: flex;
			align-items: center;
			gap: 8px;
		}
		
		.schedule-inputs {
			display: flex;
			gap: 12px;
			margin-bottom: 16px;
		}
		
		.schedule-inputs select {
			flex: 1;
			padding: 10px 14px;
			border: 2px solid var(--match-border);
			border-radius: 8px;
			font-family: inherit;
			font-size: 0.95rem;
			background: white;
			cursor: pointer;
		}
		
		.schedule-inputs select:focus {
			outline: none;
			border-color: var(--match-accent);
		}
		
		.schedule-btn {
			width: 100%;
			padding: 12px;
			background: var(--match-accent);
			color: white;
			border: none;
			border-radius: 8px;
			font-weight: 600;
			font-size: 1rem;
			cursor: pointer;
			transition: all 0.2s;
		}
		
		.schedule-btn:hover {
			background: var(--match-accent-hover);
			transform: translateY(-1px);
		}
		
		.view-resume-btn {
			padding: 10px 16px;
			background: #3b82f6;
			color: white;
			border: none;
			border-radius: 8px;
			font-weight: 600;
			font-size: 0.95rem;
			cursor: pointer;
			transition: all 0.2s;
			display: inline-flex;
			align-items: center;
			gap: 8px;
		}
		
		.view-resume-btn:hover {
			background: #2563eb;
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
		}
	</style>
</head>
<body>
	<div class="matches-shell">
		<main class="matches-dashboard">
			<header class="page-header">
				<h1><i class="fas fa-handshake" style="color: var(--match-accent); margin-right: 0.5rem;"></i>Your Matches</h1>
				<p>Candidates who are mutually interested in your open positions</p>
				<div class="stats-bar">
					<div class="stat-item">
						<i class="fas fa-users"></i>
						<span><strong><?= count($matches) ?></strong> Total Matches</span>
					</div>
					<div class="stat-item">
						<i class="fas fa-clock"></i>
						<span><strong>Today</strong> <?= date('F j, Y') ?></span>
					</div>
				</div>
			</header>

			<section class="filters-card">
				<div class="filters-bar">
					<div class="search-field">
						<i class="fas fa-search"></i>
						<input type="search" id="searchInput" placeholder="Search by name, skill, or location..." />
					</div>
					<div class="select-pill">
						<label for="roleFilter"><i class="fas fa-briefcase"></i> Role</label>
						<select id="roleFilter">
							<option value="">All Job Posts</option>
							<?php
							// Display all job posts created by this employer
							foreach ($employerJobPosts as $jobPost) {
								$jobId = (int)$jobPost['job_post_id'];
								$jobName = htmlspecialchars($jobPost['job_post_name']);
								echo '<option value="' . $jobId . '">' . $jobName . '</option>';
							}
							?>
						</select>
					</div>
					<div class="select-pill">
						<label for="applicationStatusFilter"><i class="fas fa-check-circle"></i> Status</label>
						<select id="applicationStatusFilter">
							<option value="">All Candidates</option>
							<option value="applied">Applied</option>
							<option value="not_applied">Not Applied</option>
							<option value="shortlisted">Shortlisted</option>
							<option value="interview">Interview</option>
							<option value="accepted">Accepted</option>
							<option value="rejected">Rejected</option>
						</select>
					</div>
				</div>
			</section>

			<section class="matches-grid">
			<?php 
			// DEBUG output
			echo "<!-- DEBUG: Total matches in array: " . count($matches) . " -->";
			echo "<!-- DEBUG: Matches data: " . htmlspecialchars(json_encode(array_keys($matches))) . " -->";
			?>
			<?php if (empty($matches)): ?>
				<div class="empty-state">
					<i class="fas fa-heart-broken"></i>
					<h3>No Matches Yet</h3>
					<p>When you and an applicant both like each other for a job, they will appear here!</p>
				</div>
			<?php else: ?>
				<?php foreach ($matches as $match): ?>
					<?php
						$first = $match['first_name'] ?? '';
						$last = $match['last_name'] ?? '';
						$email = $match['email'] ?? '';
						$initials = getInitials($first, $last, $email);
						$skills = $match['skills'] ?? [];
						$photo = $match['photo'] ?? getProfilePhoto('', $match['applicant_id'], $first);
						$jobTitle = $match['job_title'] ?? 'Professional';
						$years = $match['experience_length'] ?? 'Experience not specified';
						$jobName = $match['job_post_name'] ?? 'Job';
						$jobLoc = $match['job_location'] ?? 'Location not specified';
						$jobSetup = $match['job_work_setup'] ?? '';
						$summary = $match['summary'] ?? 'No profile summary provided.';
						$matchedAt = $match['matched_at'] ? date('M d, Y', strtotime($match['matched_at'])) : 'Recently';
						// Determine application status for filtering
						$dbAppStatus = strtolower(trim($match['application_status'] ?? ''));
						if (!empty($match['application_id'])) {
							// Use actual status from database if it's a recognized status
							$recognizedStatuses = ['shortlisted', 'interview', 'interviewing', 'accepted', 'rejected'];
							if (in_array($dbAppStatus, $recognizedStatuses)) {
								// Normalize 'interviewing' to 'interview'
								$applicationStatus = ($dbAppStatus === 'interviewing') ? 'interview' : $dbAppStatus;
							} else {
								$applicationStatus = 'applied';
							}
						} else {
							$applicationStatus = 'not_applied';
						}
						$applicantId = $match['applicant_id'] ?? 0;
						$applicationId = $match['application_id'] ?? 0;
						
						// DEBUG: Log application data
						echo "<!-- DEBUG Card: {$first} {$last}, job_post_id={$match['job_post_id']}, app_id={$applicationId}, status={$applicationStatus} -->";
					?>
					<article class="candidate-card" 
						data-status="matched" 
						data-job="<?= htmlspecialchars($jobName) ?>"
						data-job-post-id="<?= (int)$match['job_post_id'] ?>"
						data-name="<?= htmlspecialchars(trim($first . ' ' . $last)) ?>"
						data-skills="<?= htmlspecialchars(implode(' ', $skills)) ?>"
						data-location="<?= htmlspecialchars($jobLoc) ?>"
						data-application-status="<?= $applicationStatus ?>"
						data-applicant-id="<?= $applicantId ?>"
						data-application-id="<?= $applicationId ?>"
						data-resume-type="<?= htmlspecialchars($match['resume_type'] ?? '') ?>"
						data-resume-file-path="<?= htmlspecialchars($match['resume_file_path'] ?? '') ?>">
						
						<span class="match-badge">
							<i class="fas fa-heart"></i>
							Matched
						</span>
						
						<div class="card-photo" style="background-image: url('<?= htmlspecialchars($photo) ?>');">
						</div>
						
						<div class="card-content">
							<div class="card-header">
								<h3><?= htmlspecialchars(trim($first . ' ' . $last)) ?></h3>
								<p class="job-title"><?= htmlspecialchars($jobTitle) ?></p>
							</div>
							
							<div class="meta-row">
								<span><i class="fas fa-briefcase"></i><?= htmlspecialchars($years) ?></span>
								<span><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars($jobLoc) ?></span>
								<?php if ($jobSetup): ?>
									<span><i class="fas fa-laptop-house"></i><?= htmlspecialchars($jobSetup) ?></span>
								<?php endif; ?>
							</div>
							
							<p class="candidate-bio"><?= htmlspecialchars($summary) ?></p>
							
							<div class="info-grid">
								<div class="info-item">
									<i class="fas fa-briefcase"></i>
									<div>
										<span class="info-label">Position</span>
										<span class="info-value"><?= htmlspecialchars($jobName) ?></span>
									</div>
								</div>
								<div class="info-item">
									<i class="fas fa-calendar-check"></i>
									<div>
										<span class="info-label">Matched</span>
										<span class="info-value"><?= htmlspecialchars($matchedAt) ?></span>
									</div>
								</div>
							</div>
							
							<div class="skills-section">
								<p class="section-label"><i class="fas fa-star"></i> Top Skills</p>
								<div class="pill-row">
									<?php foreach ($skills as $skill): ?>
										<span><?= htmlspecialchars($skill) ?></span>
									<?php endforeach; ?>
								</div>
							</div>
							
							<div class="actions-row">
								<button class="action-btn primary view-more-btn" type="button" 
									data-applicant-id="<?= $applicantId ?>" 
									data-application-id="<?= $applicationId ?>"
									data-name="<?= htmlspecialchars(trim($first . ' ' . $last)) ?>"
									data-job="<?= htmlspecialchars($jobName) ?>"
									data-resume-type="<?= htmlspecialchars($match['resume_type'] ?? '') ?>"
									data-resume-file-path="<?= htmlspecialchars($match['resume_file_path'] ?? '') ?>"
									data-app-status="<?= htmlspecialchars($applicationStatus) ?>">
									<i class="fas fa-eye"></i> View More
								</button>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			<?php endif; ?>
			</section>
		</main>
	</div>
	
	<!-- Modal for applicant details -->
	<div class="modal-overlay" id="applicantModal">
		<div class="modal-content">
			<div class="modal-header">
				<h2 id="modalApplicantName">Applicant Details</h2>
				<button class="modal-close" onclick="closeModal()">&times;</button>
			</div>
			<div class="modal-body">
				<div class="modal-section">
					<div class="modal-section-title">
						<i class="fas fa-briefcase"></i>
						<span id="modalJobTitle">Job Application</span>
					</div>
					<!-- Dynamic resume button - shows View Resume or Download Resume based on type -->
					<div id="resumeButtonContainer" style="margin-top: 10px;">
						<button class="view-resume-btn" id="viewResumeBtn" onclick="viewResume()" style="display: none;">
							<i class="fas fa-file-alt"></i> View Resume
						</button>
						<a class="view-resume-btn" id="downloadResumeBtn" href="#" target="_blank" style="display: none; text-decoration: none;">
							<i class="fas fa-download"></i> Download Resume
						</a>
					</div>
				</div>
				
				<div class="modal-section" id="answersSection">
					<div class="modal-section-title">
						<i class="fas fa-question-circle"></i>
						Application Answers
					</div>
					<div id="answersContainer">
						<p style="color: var(--match-muted); font-style: italic;">No answers available yet.</p>
					</div>
				</div>
				
				<!-- Dynamic Action Section - changes based on application status -->
				<div class="action-section" id="actionSection">
					<!-- Applied status: Show Shortlist button -->
					<div id="appliedActions" style="display: none;">
						<button class="schedule-btn" onclick="shortlistApplicant()" style="background: #10b981;">
							<i class="fas fa-star"></i> Shortlist
						</button>
					</div>
					
					<!-- Shortlisted status: Show Schedule Interview and Reject buttons -->
					<div id="shortlistedActions" style="display: none;">
						<div class="schedule-section">
							<div class="schedule-title">
								<i class="fas fa-calendar-alt"></i>
								Schedule Interview
							</div>
							<div class="schedule-inputs">
								<input type="date" id="interviewDate" min="" style="flex: 1; padding: 10px 14px; border: 2px solid var(--match-border); border-radius: 8px; font-family: inherit; font-size: 0.95rem; background: white;" />
								<input type="time" id="interviewTime" style="flex: 1; padding: 10px 14px; border: 2px solid var(--match-border); border-radius: 8px; font-family: inherit; font-size: 0.95rem; background: white;" />
							</div>
							<div style="display: flex; gap: 10px; margin-top: 12px;">
								<button class="schedule-btn" onclick="scheduleInterview()" style="flex: 1;">
									<i class="fas fa-calendar-check"></i> Schedule Interview
								</button>
								<button class="schedule-btn" onclick="rejectApplicant()" style="flex: 1; background: #ef4444;">
									<i class="fas fa-times"></i> Reject
								</button>
							</div>
						</div>
					</div>
					
					<!-- Interview status: Show Hire and Reject buttons -->
					<div id="interviewActions" style="display: none;">
						<div style="display: flex; gap: 10px;">
							<button class="schedule-btn" onclick="hireApplicant()" style="flex: 1; background: #10b981;">
								<i class="fas fa-check"></i> Hire
							</button>
							<button class="schedule-btn" onclick="rejectApplicant()" style="flex: 1; background: #ef4444;">
								<i class="fas fa-times"></i> Reject
							</button>
						</div>
					</div>
					
					<!-- Accepted status: Read-only -->
					<div id="acceptedStatus" style="display: none;">
						<p style="text-align: center; color: #10b981; font-weight: 600; padding: 16px; background: #d1fae5; border-radius: 8px;">
							<i class="fas fa-check-circle"></i> Applicant has been hired
						</p>
					</div>
					
					<!-- Rejected status: Read-only -->
					<div id="rejectedStatus" style="display: none;">
						<p style="text-align: center; color: #ef4444; font-weight: 600; padding: 16px; background: #fee2e2; border-radius: 8px;">
							<i class="fas fa-times-circle"></i> Application rejected
						</p>
					</div>
					
					<!-- No application: Hidden -->
					<div id="noApplicationStatus" style="display: none;">
						<p style="text-align: center; color: var(--match-muted); font-style: italic; padding: 16px;">
							No application submitted yet.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<!-- Resume View Modal (for builtin resumes) -->
	<div class="modal-overlay" id="resumeModal">
		<div class="modal-content" style="max-width: 800px;">
			<div class="modal-header">
				<h2 id="resumeModalTitle">Applicant Resume</h2>
				<button class="modal-close" onclick="closeResumeModal()">&times;</button>
			</div>
			<div class="modal-body" id="resumeModalBody">
				<div style="text-align: center; padding: 40px;">
					<i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--match-accent);"></i>
					<p>Loading resume...</p>
				</div>
			</div>
		</div>
	</div>
	
	<script>
	let currentApplicantId = null;
	let currentApplicationId = null;
	let currentResumeType = 'builtin';
	let currentResumeFilePath = null;
	let currentAppStatus = '';
	
	// Set minimum date for interview scheduler to today
	document.addEventListener('DOMContentLoaded', function() {
		const today = new Date().toISOString().split('T')[0];
		const interviewDateInput = document.getElementById('interviewDate');
		if (interviewDateInput) {
			interviewDateInput.min = today;
			interviewDateInput.value = today;
		}
	});
	
	document.addEventListener('DOMContentLoaded', function () {
		const searchInput = document.getElementById('searchInput');
		const roleFilter = document.getElementById('roleFilter');
		const applicationStatusFilter = document.getElementById('applicationStatusFilter');
		const cards = document.querySelectorAll('.candidate-card');

		function filterCards() {
			const searchTerm = searchInput.value.toLowerCase();
			const selectedRole = roleFilter.value; // job_post_id as string
			const selectedStatus = applicationStatusFilter.value.toLowerCase();
			let visibleCount = 0;

			console.log('=== FILTER DEBUG ===');
			console.log('Selected Role (job_post_id):', selectedRole, typeof selectedRole);
			console.log('Selected Status:', selectedStatus, typeof selectedStatus);
			console.log('Total cards:', cards.length);

			cards.forEach(card => {
				const name = (card.dataset.name || '').toLowerCase();
				const skills = (card.dataset.skills || '').toLowerCase();
				const location = (card.dataset.location || '').toLowerCase();
				const jobPostId = String(card.dataset.jobPostId || ''); // Convert to string
				const applicationStatus = (card.dataset.applicationStatus || '').toLowerCase();

				console.log('---');
				console.log('Card applicant:', card.dataset.name);
				console.log('  jobPostId:', jobPostId, typeof jobPostId);
				console.log('  applicationStatus:', applicationStatus, typeof applicationStatus);
				console.log('  applicationId:', card.dataset.applicationId);

				const matchesSearch = !searchTerm || 
					name.includes(searchTerm) || 
					skills.includes(searchTerm) || 
					location.includes(searchTerm);
				
				// Use loose equality (==) to handle type coercion
				const matchesRole = !selectedRole || (jobPostId == selectedRole);
				const matchesStatus = !selectedStatus || (applicationStatus == selectedStatus);

				console.log('  matchesRole:', matchesRole, '(comparing', jobPostId, '==', selectedRole, ')');
				console.log('  matchesStatus:', matchesStatus, '(comparing', applicationStatus, '==', selectedStatus, ')');
				console.log('  VISIBLE:', matchesSearch && matchesRole && matchesStatus);

				if (matchesSearch && matchesRole && matchesStatus) {
					card.style.display = '';
					visibleCount++;
				} else {
					card.style.display = 'none';
				}
			});

			console.log('Total visible cards:', visibleCount);
			console.log('===================');

			// Show empty state if no results
			const emptyState = document.querySelector('.empty-state');
			const grid = document.querySelector('.matches-grid');
			if (visibleCount === 0 && cards.length > 0) {
				if (!document.querySelector('.no-results-state')) {
					const noResults = document.createElement('div');
					noResults.className = 'empty-state no-results-state';
					noResults.innerHTML = `
						<i class="fas fa-search"></i>
						<h3>No Results Found</h3>
						<p>Try adjusting your search or filters</p>
					`;
					grid.appendChild(noResults);
				}
			} else {
				const noResults = document.querySelector('.no-results-state');
				if (noResults) noResults.remove();
			}
		}

		if (searchInput) searchInput.addEventListener('input', filterCards);
		if (roleFilter) roleFilter.addEventListener('change', filterCards);
		if (applicationStatusFilter) applicationStatusFilter.addEventListener('change', filterCards);
		
		// View More button click handlers
		document.querySelectorAll('.view-more-btn').forEach(btn => {
			btn.addEventListener('click', function() {
				const applicantId = this.dataset.applicantId;
				const applicationId = this.dataset.applicationId;
				const name = this.dataset.name;
				const job = this.dataset.job;
				const resumeType = this.dataset.resumeType || 'builtin';
				const resumeFilePath = this.dataset.resumeFilePath || '';
				const appStatus = this.dataset.appStatus || '';
				
				openModal(applicantId, applicationId, name, job, resumeType, resumeFilePath, appStatus);
			});
		});

		// Add smooth scroll to top when filters change
		function scrollToResults() {
			const grid = document.querySelector('.matches-grid');
			if (grid) {
				grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		}

		if (roleFilter) {
			roleFilter.addEventListener('change', () => {
				setTimeout(scrollToResults, 100);
			});
		}
	});
	
	function openModal(applicantId, applicationId, name, job, resumeType, resumeFilePath, appStatus) {
		currentApplicantId = applicantId;
		currentApplicationId = applicationId;
		// IMPORTANT: Do NOT default to 'builtin' - use exactly what's from the database
		currentResumeType = resumeType || '';
		currentResumeFilePath = resumeFilePath || '';
		currentAppStatus = appStatus || '';
		
		document.getElementById('modalApplicantName').textContent = name;
		document.getElementById('modalJobTitle').textContent = 'Application for: ' + job;
		
		// Hide all action sections first
		document.getElementById('appliedActions').style.display = 'none';
		document.getElementById('shortlistedActions').style.display = 'none';
		document.getElementById('interviewActions').style.display = 'none';
		document.getElementById('acceptedStatus').style.display = 'none';
		document.getElementById('rejectedStatus').style.display = 'none';
		document.getElementById('noApplicationStatus').style.display = 'none';
		
		// Show appropriate action section based on status
		if (!applicationId || applicationId == '0') {
			document.getElementById('noApplicationStatus').style.display = 'block';
		} else if (appStatus === 'applied' || appStatus === 'pending' || appStatus === 'in_progress') {
			document.getElementById('appliedActions').style.display = 'block';
		} else if (appStatus === 'shortlisted') {
			document.getElementById('shortlistedActions').style.display = 'block';
		} else if (appStatus === 'interview' || appStatus === 'interviewing') {
			document.getElementById('interviewActions').style.display = 'block';
		} else if (appStatus === 'accepted') {
			document.getElementById('acceptedStatus').style.display = 'block';
		} else if (appStatus === 'rejected') {
			document.getElementById('rejectedStatus').style.display = 'block';
		} else {
			// Default to applied actions if status is unknown but application exists
			document.getElementById('appliedActions').style.display = 'block';
		}
		
		// Update resume button based on EXACT resume type from application table
		const viewResumeBtn = document.getElementById('viewResumeBtn');
		const downloadResumeBtn = document.getElementById('downloadResumeBtn');
		const resumeButtonContainer = document.getElementById('resumeButtonContainer');
		
		// Hide both buttons by default
		viewResumeBtn.style.display = 'none';
		downloadResumeBtn.style.display = 'none';
		
		if (currentResumeType === 'file' && currentResumeFilePath) {
			// ONLY show download button when resume_type is EXACTLY 'file'
			downloadResumeBtn.style.display = 'inline-flex';
			downloadResumeBtn.href = '../' + currentResumeFilePath;
		} else if (currentResumeType === 'builtin') {
			// ONLY show view button when resume_type is EXACTLY 'builtin'
			viewResumeBtn.style.display = 'inline-flex';
		} else {
			// No application or unknown resume_type - show message
			resumeButtonContainer.innerHTML = '<p style="color: var(--match-muted); font-style: italic; margin: 0;">No resume submitted with application.</p>' + 
				'<button class="view-resume-btn" id="viewResumeBtn" onclick="viewResume()" style="display: none;"><i class="fas fa-file-alt"></i> View Resume</button>' +
				'<a class="view-resume-btn" id="downloadResumeBtn" href="#" target="_blank" style="display: none; text-decoration: none;"><i class="fas fa-download"></i> Download Resume</a>';
		}
		
		// Fetch answers if application exists
		if (applicationId && applicationId != '0') {
			fetchAnswers(applicationId);
		} else {
			document.getElementById('answersContainer').innerHTML = '<p style="color: var(--match-muted); font-style: italic;">Applicant has not submitted an application yet.</p>';
		}
		
		document.getElementById('applicantModal').classList.add('active');
		document.body.style.overflow = 'hidden';
	}
	
	function closeModal() {
		document.getElementById('applicantModal').classList.remove('active');
		document.body.style.overflow = '';
		currentApplicantId = null;
		currentApplicationId = null;
	}
	
	async function fetchAnswers(applicationId) {
		try {
			const response = await fetch(`get_application_answers.php?application_id=${applicationId}`);
			const data = await response.json();
			
			const container = document.getElementById('answersContainer');
			
			if (data.success && data.answers && data.answers.length > 0) {
				let html = '';
				data.answers.forEach(answer => {
					html += `
						<div class="answer-item">
							<div class="answer-question">${answer.question_text}</div>
							<div class="answer-text">${answer.answer_text || '—'}</div>
						</div>
					`;
				});
				container.innerHTML = html;
			} else {
				container.innerHTML = '<p style="color: var(--match-muted); font-style: italic;">No answers available yet.</p>';
			}
		} catch (error) {
			console.error('Error fetching answers:', error);
			document.getElementById('answersContainer').innerHTML = '<p style="color: red;">Error loading answers.</p>';
		}
	}
	
	function viewResume() {
		if (!currentApplicantId) {
			alert('No applicant selected.');
			return;
		}
		
		// CRITICAL: Only allow viewing builtin resumes via modal
		if (currentResumeType !== 'builtin') {
			alert('This applicant submitted a file resume. Please use the Download button instead.');
			return;
		}
		
		// Show resume modal and load data
		document.getElementById('resumeModal').classList.add('active');
		document.getElementById('resumeModalBody').innerHTML = `
			<div style="text-align: center; padding: 40px;">
				<i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--match-accent);"></i>
				<p>Loading resume...</p>
			</div>
		`;
		
		// Fetch resume data via AJAX
		fetch('get_applicant_resume.php?applicant_id=' + currentApplicantId)
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					renderResumeModal(data);
				} else {
					document.getElementById('resumeModalBody').innerHTML = `
						<div style="text-align: center; padding: 40px; color: var(--match-muted);">
							<i class="fas fa-exclamation-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
							<p>${data.message || 'Could not load resume.'}</p>
						</div>
					`;
				}
			})
			.catch(error => {
				console.error('Error fetching resume:', error);
				document.getElementById('resumeModalBody').innerHTML = `
					<div style="text-align: center; padding: 40px; color: red;">
						<i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
						<p>Error loading resume. Please try again.</p>
					</div>
				`;
			});
	}
	
	function closeResumeModal() {
		document.getElementById('resumeModal').classList.remove('active');
	}
	
	function renderResumeModal(data) {
		const resume = data.resume || {};
		const name = resume.full_name || 'Unknown';
		const email = resume.email || '';
		const phone = resume.phone || '';
		const location = resume.location || '';
		const summary = resume.professional_summary || '';
		
		// Update modal title
		document.getElementById('resumeModalTitle').textContent = name + "'s Resume";
		
		let html = '<div class="resume-container" style="padding: 0;">';
		
		// Header section
		html += `
			<div style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: white; padding: 24px; border-radius: 8px; margin-bottom: 20px;">
				<h3 style="margin: 0 0 8px 0; font-size: 1.5rem;">${escapeHtml(name)}</h3>
				<div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.9rem;">
					${email ? `<span><i class="fas fa-envelope" style="margin-right: 6px;"></i>${escapeHtml(email)}</span>` : ''}
					${phone ? `<span><i class="fas fa-phone" style="margin-right: 6px;"></i>${escapeHtml(phone)}</span>` : ''}
					${location ? `<span><i class="fas fa-map-marker-alt" style="margin-right: 6px;"></i>${escapeHtml(location)}</span>` : ''}
				</div>
			</div>
		`;
		
		// Professional Summary
		if (summary) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-user" style="margin-right: 8px;"></i>Professional Summary</h4>
					<p style="color: #475569; line-height: 1.6; margin: 0;">${escapeHtml(summary)}</p>
				</div>
			`;
		}
		
		// Work Experience
		const experience = resume.work_experience || [];
		if (experience.length > 0) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-briefcase" style="margin-right: 8px;"></i>Work Experience</h4>
			`;
			experience.forEach(exp => {
				const startDate = exp.start_date ? formatDate(exp.start_date) : '';
				const endDate = exp.end_date ? formatDate(exp.end_date) : 'Present';
				html += `
					<div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #3b82f6;">
						<div style="font-weight: 600; color: #0f172a;">${escapeHtml(exp.experience_name || 'Position')}</div>
						<div style="color: #64748b; font-size: 0.9rem; margin: 4px 0;">${escapeHtml(exp.experience_company || '')} ${exp.experience_level_name ? '• ' + escapeHtml(exp.experience_level_name) : ''}</div>
						<div style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 8px;">${startDate} - ${endDate}</div>
						${exp.experience_description ? `<p style="color: #475569; font-size: 0.9rem; margin: 0; line-height: 1.5;">${escapeHtml(exp.experience_description)}</p>` : ''}
					</div>
				`;
			});
			html += '</div>';
		}
		
		// Education
		const education = resume.education || [];
		if (education.length > 0) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-graduation-cap" style="margin-right: 8px;"></i>Education</h4>
			`;
			education.forEach(edu => {
				const startDate = edu.start_date ? formatDate(edu.start_date) : '';
				const endDate = edu.end_date ? formatDate(edu.end_date) : 'Present';
				html += `
					<div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #10b981;">
						<div style="font-weight: 600; color: #0f172a;">${escapeHtml(edu.school_name || 'School')}</div>
						<div style="color: #64748b; font-size: 0.9rem; margin: 4px 0;">${escapeHtml(edu.education_level || '')}</div>
						<div style="color: #94a3b8; font-size: 0.85rem;">${startDate} - ${endDate}</div>
					</div>
				`;
			});
			html += '</div>';
		}
		
		// Skills
		const skills = resume.skills || [];
		if (skills.length > 0) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-code" style="margin-right: 8px;"></i>Skills</h4>
					<div style="display: flex; flex-wrap: wrap; gap: 8px;">
			`;
			skills.forEach(skill => {
				html += `<span style="background: #e0e7ff; color: #3b82f6; padding: 6px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 500;">${escapeHtml(skill.skill_name || skill)}</span>`;
			});
			html += '</div></div>';
		}
		
		// Achievements
		const achievements = resume.achievements || [];
		if (achievements.length > 0) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-trophy" style="margin-right: 8px;"></i>Achievements</h4>
			`;
			achievements.forEach(ach => {
				const dateReceived = ach.date_received ? formatDate(ach.date_received) : '';
				html += `
					<div style="background: #fef3c7; padding: 16px; border-radius: 8px; margin-bottom: 12px; border-left: 3px solid #f59e0b;">
						<div style="font-weight: 600; color: #0f172a;">${escapeHtml(ach.achievement_name || 'Achievement')}</div>
						<div style="color: #64748b; font-size: 0.9rem; margin: 4px 0;">${escapeHtml(ach.achievement_organization || '')} ${dateReceived ? '• ' + dateReceived : ''}</div>
						${ach.description ? `<p style="color: #475569; font-size: 0.9rem; margin: 8px 0 0 0; line-height: 1.5;">${escapeHtml(ach.description)}</p>` : ''}
					</div>
				`;
			});
			html += '</div>';
		}
		
		// Preferences
		const preferences = resume.preferences || [];
		if (preferences.length > 0) {
			html += `
				<div style="margin-bottom: 20px;">
					<h4 style="color: #3b82f6; margin: 0 0 10px 0; font-size: 1.1rem;"><i class="fas fa-cog" style="margin-right: 8px;"></i>Job Preferences</h4>
					<div style="display: flex; flex-wrap: wrap; gap: 8px;">
			`;
			preferences.forEach(pref => {
				if (pref.job_type) {
					html += `<span style="background: #d1fae5; color: #059669; padding: 6px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 500;">${escapeHtml(pref.job_type)}</span>`;
				}
				if (pref.industry) {
					html += `<span style="background: #fee2e2; color: #dc2626; padding: 6px 12px; border-radius: 999px; font-size: 0.85rem; font-weight: 500;">${escapeHtml(pref.industry)}</span>`;
				}
			});
			html += '</div></div>';
		}
		
		html += '</div>';
		
		// Add download PDF button at bottom
		html += `
			<div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
				<a href="../employer/download-resume.php?applicant_id=${currentApplicantId}" target="_blank" class="view-resume-btn" style="text-decoration: none;">
					<i class="fas fa-download"></i> Download as PDF
				</a>
			</div>
		`;
		
		document.getElementById('resumeModalBody').innerHTML = html;
	}
	
	function escapeHtml(text) {
		if (!text) return '';
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}
	
	function formatDate(dateStr) {
		if (!dateStr) return '';
		const date = new Date(dateStr);
		const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		return months[date.getMonth()] + ' ' + date.getFullYear();
	}
	
	async function shortlistApplicant() {
		if (!currentApplicationId || currentApplicationId == '0') {
			alert('Cannot shortlist: No application found for this candidate.');
			return;
		}
		
		try {
			const response = await fetch('update_application_status.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					application_id: currentApplicationId,
					status: 'shortlisted'
				})
			});
			
			const data = await response.json();
			
			if (data.success) {
				// Update the card's data attribute so filter works immediately
				const card = document.querySelector(`.candidate-card[data-application-id="${currentApplicationId}"]`);
				if (card) {
					card.dataset.applicationStatus = 'shortlisted';
				}
				
				alert('Applicant has been shortlisted successfully!');
				closeModal();
				// Optionally refresh the page to reflect changes
				// location.reload();
			} else {
				alert('Error: ' + (data.message || 'Failed to shortlist applicant'));
			}
		} catch (error) {
			console.error('Error shortlisting applicant:', error);
			alert('An error occurred while shortlisting the applicant.');
		}
	}
	
	async function scheduleInterview() {
		if (!currentApplicationId || currentApplicationId == '0') {
			alert('Cannot schedule interview: No application found.');
			return;
		}
		
		if (!currentApplicantId) {
			alert('Cannot schedule interview: Applicant ID not found.');
			return;
		}
		
		const interviewDate = document.getElementById('interviewDate').value;
		const interviewTime = document.getElementById('interviewTime').value;
		
		if (!interviewDate) {
			alert('Please select a date for the interview.');
			return;
		}
		
		if (!interviewTime) {
			alert('Please select a time for the interview.');
			return;
		}
		
		const interviewDatetime = `${interviewDate} ${interviewTime}:00`;
		
		try {
			// First, update application status to 'interview'
			const statusResponse = await fetch('update_application_status.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					application_id: currentApplicationId,
					status: 'interview'
				})
			});
			
			const statusData = await statusResponse.json();
			
			if (!statusData.success) {
				alert('Error updating status: ' + (statusData.message || 'Failed to update status'));
				return;
			}
			
			// Then, create/update interview schedule
			const scheduleResponse = await fetch('schedule_interview.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					application_id: currentApplicationId,
					applicant_id: currentApplicantId,
					interview_datetime: interviewDatetime
				})
			});
			
			const scheduleData = await scheduleResponse.json();
			
			if (scheduleData.success) {
				// Update the card's data attribute
				const card = document.querySelector(`.candidate-card[data-application-id="${currentApplicationId}"]`);
				if (card) {
					card.dataset.applicationStatus = 'interview';
				}
				
				alert('Interview scheduled successfully for ' + interviewDatetime + '!');
				closeModal();
				location.reload(); // Reload to show updated status
			} else {
				alert('Error scheduling interview: ' + (scheduleData.message || 'Failed to schedule interview'));
			}
		} catch (error) {
			console.error('Error scheduling interview:', error);
			alert('An error occurred while scheduling the interview.');
		}
	}
	
	async function hireApplicant() {
		if (!currentApplicationId || currentApplicationId == '0') {
			alert('Cannot hire: No application found.');
			return;
		}
		
		if (!confirm('Are you sure you want to hire this applicant?')) {
			return;
		}
		
		try {
			const response = await fetch('update_application_status.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					application_id: currentApplicationId,
					status: 'accepted'
				})
			});
			
			const data = await response.json();
			
			if (data.success) {
				// Update the card's data attribute
				const card = document.querySelector(`.candidate-card[data-application-id="${currentApplicationId}"]`);
				if (card) {
					card.dataset.applicationStatus = 'accepted';
				}
				
				alert('Applicant has been hired successfully!');
				closeModal();
			} else {
				alert('Error: ' + (data.message || 'Failed to hire applicant'));
			}
		} catch (error) {
			console.error('Error hiring applicant:', error);
			alert('An error occurred while hiring the applicant.');
		}
	}
	
	async function rejectApplicant() {
		if (!currentApplicationId || currentApplicationId == '0') {
			alert('Cannot reject: No application found.');
			return;
		}
		
		if (!confirm('Are you sure you want to reject this applicant?')) {
			return;
		}
		
		try {
			const response = await fetch('update_application_status.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					application_id: currentApplicationId,
					status: 'rejected'
				})
			});
			
			const data = await response.json();
			
			if (data.success) {
				// Update the card's data attribute
				const card = document.querySelector(`.candidate-card[data-application-id="${currentApplicationId}"]`);
				if (card) {
					card.dataset.applicationStatus = 'rejected';
				}
				
				alert('Application has been rejected.');
				closeModal();
			} else {
				alert('Error: ' + (data.message || 'Failed to reject applicant'));
			}
		} catch (error) {
			console.error('Error rejecting applicant:', error);
			alert('An error occurred while rejecting the applicant.');
		}
	}
	
	// Close modal on overlay click
	document.getElementById('applicantModal')?.addEventListener('click', function(e) {
		if (e.target === this) {
			closeModal();
		}
	});
	</script>
</body>
</html>