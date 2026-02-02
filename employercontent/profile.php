<?php
session_start();
require_once '../database.php';

$activePage = 'profile';

if (!isset($_SESSION['user_id'])) {
	header('Location: login.php');
	exit;
}

$userId = (int)$_SESSION['user_id'];

$updateFeedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $contactNumberInput = trim($_POST['contact_number'] ?? '');
    $companyNameInput = trim($_POST['company_name'] ?? '');
    $companyDescriptionInput = trim($_POST['company_description'] ?? '');
    $companyIndustryInput = trim($_POST['company_industry'] ?? '');
    $companyLocationInput = trim($_POST['company_location'] ?? '');
    $companyWebsiteInput = trim($_POST['company_website'] ?? '');
    $facebookLinkInput = trim($_POST['facebook_link'] ?? '');
    $linkedinLinkInput = trim($_POST['linkedin_link'] ?? '');

    $errors = [];

    // Handle company logo upload
    $newLogoPath = null;
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $uploadedFile = $_FILES['company_logo'];
        
        // Validate file type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($uploadedFile['tmp_name']);
        
        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Logo must be a JPG, PNG, GIF, or WebP image.';
        } elseif ($uploadedFile['size'] > $maxSize) {
            $errors[] = 'Logo file size must not exceed 2MB.';
        } else {
            // Generate unique filename
            $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
            $newFilename = 'company_logo_' . $userId . '_' . time() . '.' . strtolower($extension);
            $uploadDir = dirname(__DIR__) . '/uploads/company_logos/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $destination = $uploadDir . $newFilename;
            
            if (move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
                $newLogoPath = 'uploads/company_logos/' . $newFilename;
            } else {
                $errors[] = 'Failed to upload logo. Please try again.';
            }
        }
    }
    if ($firstName === '') {
        $errors[] = 'First name is required.';
    }
    if ($lastName === '') {
        $errors[] = 'Last name is required.';
    }
    if ($companyNameInput === '') {
        $errors[] = 'Company name is required.';
    }
    if ($companyWebsiteInput !== '' && !preg_match('/^https?:\/\//i', $companyWebsiteInput)) {
        $companyWebsiteInput = 'https://' . $companyWebsiteInput;
    }
    if ($companyWebsiteInput !== '' && !filter_var($companyWebsiteInput, FILTER_VALIDATE_URL)) {
        $errors[] = 'Enter a valid company website URL or leave it blank.';
    }
    // Validate Facebook link
    if ($facebookLinkInput !== '') {
        if (!preg_match('/^https?:\/\//i', $facebookLinkInput)) {
            $facebookLinkInput = 'https://' . $facebookLinkInput;
        }
        if (!filter_var($facebookLinkInput, FILTER_VALIDATE_URL)) {
            $errors[] = 'Enter a valid Facebook URL or leave it blank.';
        }
    }
    // Validate LinkedIn link
    if ($linkedinLinkInput !== '') {
        if (!preg_match('/^https?:\/\//i', $linkedinLinkInput)) {
            $linkedinLinkInput = 'https://' . $linkedinLinkInput;
        }
        if (!filter_var($linkedinLinkInput, FILTER_VALIDATE_URL)) {
            $errors[] = 'Enter a valid LinkedIn URL or leave it blank.';
        }
    }
    // Basic phone validation (PH: 11 digits)
    if ($contactNumberInput !== '') {
        $digits = preg_replace('/\D+/', '', $contactNumberInput);

        // Normalize +63XXXXXXXXXX to 0XXXXXXXXXX
        if (strpos($contactNumberInput, '+63') === 0 && strlen($digits) === 13) {
            // +63 + 10 digits => convert to 0 + 10 digits (total 11)
            $digits = '0' . substr($digits, 3); // drop 63, prefix 0
        }

        if (strlen($digits) !== 11) {
            $errors[] = 'Philippine contact number must have exactly 11 digits.';
        } else {
            // Optional: require it to start with 09
            if (!preg_match('/^09\d{9}$/', $digits)) {
                $errors[] = 'Use local format starting with 09 followed by 9 digits (e.g., 09171234567).';
            } else {
                // Store normalized local format
                $contactNumberInput = $digits;
            }
        }
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Ensure a profile row exists for this user
            $hasProfile = false;
            if ($stmt = $conn->prepare('SELECT 1 FROM user_profile WHERE user_id = ? LIMIT 1')) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->bind_result($one);
                $hasProfile = $stmt->fetch() ? true : false;
                $stmt->close();
            } else {
                throw new Exception('Failed to prepare profile existence check: ' . $conn->error);
            }

            if ($hasProfile) {
                // UPDATE existing profile
				$profileUpdateSql = "UPDATE user_profile SET
					user_profile_first_name = ?,
					user_profile_middle_name = NULLIF(?, ''),
					user_profile_last_name = ?,
					user_profile_contact_no = NULLIF(?, '')
				WHERE user_id = ?";
				if (!($stmt = $conn->prepare($profileUpdateSql))) {
					throw new Exception('Failed to prepare profile update statement: ' . $conn->error);
				}
				$stmt->bind_param('ssssi', $firstName, $middleName, $lastName, $contactNumberInput, $userId);
				if (!$stmt->execute()) {
					throw new Exception('Failed to execute profile update: ' . $stmt->error);
				}
				$stmt->close();
            } else {
                // INSERT new profile
				$profileInsertSql = "INSERT INTO user_profile (
					user_id,
					user_profile_first_name,
					user_profile_middle_name,
					user_profile_last_name,
					user_profile_contact_no
				) VALUES (?, ?, NULLIF(?, ''), ?, NULLIF(?, ''))";
				if (!($stmt = $conn->prepare($profileInsertSql))) {
					throw new Exception('Failed to prepare profile insert statement: ' . $conn->error);
				}
				$stmt->bind_param('issss', $userId, $firstName, $middleName, $lastName, $contactNumberInput);
				if (!$stmt->execute()) {
					throw new Exception('Failed to insert profile record: ' . $stmt->error);
				}
				$stmt->close();
            }

            $companyExists = false;
            if ($stmt = $conn->prepare('SELECT company_id FROM company WHERE user_id = ? LIMIT 1')) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->bind_result($companyId);
                if ($stmt->fetch()) {
                    $companyExists = true;
                }
                $stmt->close();
            }

            if ($companyExists) {
                // Build dynamic update query based on whether logo was uploaded
                if ($newLogoPath) {
                    $companyUpdateSql = "UPDATE company SET
                        company_name = ?,
                        description = NULLIF(?, ''),
                        industry = NULLIF(?, ''),
                        location = NULLIF(?, ''),
                        website = NULLIF(?, ''),
                        facebook_link = NULLIF(?, ''),
                        linkedin_link = NULLIF(?, ''),
                        logo = ?
                    WHERE user_id = ?";
                    if (!($stmt = $conn->prepare($companyUpdateSql))) {
                        throw new Exception('Failed to prepare company update statement.');
                    }
                    $stmt->bind_param('ssssssssi', $companyNameInput, $companyDescriptionInput, $companyIndustryInput, $companyLocationInput, $companyWebsiteInput, $facebookLinkInput, $linkedinLinkInput, $newLogoPath, $userId);
                } else {
                    $companyUpdateSql = "UPDATE company SET
                        company_name = ?,
                        description = NULLIF(?, ''),
                        industry = NULLIF(?, ''),
                        location = NULLIF(?, ''),
                        website = NULLIF(?, ''),
                        facebook_link = NULLIF(?, ''),
                        linkedin_link = NULLIF(?, '')
                    WHERE user_id = ?";
                    if (!($stmt = $conn->prepare($companyUpdateSql))) {
                        throw new Exception('Failed to prepare company update statement.');
                    }
                    $stmt->bind_param('sssssssi', $companyNameInput, $companyDescriptionInput, $companyIndustryInput, $companyLocationInput, $companyWebsiteInput, $facebookLinkInput, $linkedinLinkInput, $userId);
                }
                if (!$stmt->execute()) {
                    throw new Exception('Failed to execute company update.');
                }
                $stmt->close();
            } else {
                // Insert new company with optional logo
                if ($newLogoPath) {
                    $companyInsertSql = "INSERT INTO company (company_name, description, industry, location, website, facebook_link, linkedin_link, logo, user_id)
                        VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, ?)";
                    if (!($stmt = $conn->prepare($companyInsertSql))) {
                        throw new Exception('Failed to prepare company insert statement.');
                    }
                    $stmt->bind_param('ssssssssi', $companyNameInput, $companyDescriptionInput, $companyIndustryInput, $companyLocationInput, $companyWebsiteInput, $facebookLinkInput, $linkedinLinkInput, $newLogoPath, $userId);
                } else {
                    $companyInsertSql = "INSERT INTO company (company_name, description, industry, location, website, facebook_link, linkedin_link, user_id)
                        VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)";
                    if (!($stmt = $conn->prepare($companyInsertSql))) {
                        throw new Exception('Failed to prepare company insert statement.');
                    }
                    $stmt->bind_param('sssssssi', $companyNameInput, $companyDescriptionInput, $companyIndustryInput, $companyLocationInput, $companyWebsiteInput, $facebookLinkInput, $linkedinLinkInput, $userId);
                }
                if (!$stmt->execute()) {
                    throw new Exception('Failed to insert company record.');
                }
                $stmt->close();
            }

            $conn->commit();
            header('Location: profile.php?updated=1');
            exit;
        } catch (Throwable $th) {
            $conn->rollback();
            error_log('Employer profile update failed: ' . $th->getMessage());
            $updateFeedback = [
                'status' => 'error',
                'messages' => ['We could not save your changes. Please try again.'],
            ];
        }
    } else {
        $updateFeedback = [
            'status' => 'error',
            'messages' => $errors,
        ];
    }
}

if (!$updateFeedback && isset($_GET['updated'])) {
	$updateFeedback = [
		'status' => 'success',
		'messages' => ['Profile updated successfully.'],
	];
}

$updateSuccess = $updateFeedback && $updateFeedback['status'] === 'success';
$formShouldBeOpen = $updateFeedback && $updateFeedback['status'] === 'error';
$successMessage = $updateSuccess ? ($updateFeedback['messages'][0] ?? 'Profile updated successfully.') : '';

function wm_safe(?string $value): string
{
	return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function wm_initials(?string ...$parts): string
{
	$source = trim(implode(' ', array_filter($parts)));
	if ($source === '') {
		return 'WM';
	}
	$words = preg_split('/\s+/', $source);
	$initials = '';
	foreach ($words as $word) {
		if ($word === '') {
			continue;
		}
		$initials .= strtoupper(mb_substr($word, 0, 1));
		if (mb_strlen($initials) >= 2) {
			break;
		}
	}
	return $initials !== '' ? $initials : 'WM';
}

function wm_format_date(?string $date, string $format = 'M d, Y'): string
{
	if (!$date) {
		return '—';
	}
	$timestamp = strtotime($date);
	return $timestamp ? date($format, $timestamp) : '—';
}

$profile = [];

$profileSql = "SELECT user_profile_first_name AS first_name,
    user_profile_middle_name AS middle_name,
    user_profile_last_name AS last_name,
    user_profile_email_address AS email,
    user_profile_gender AS gender,
    user_profile_dob AS dob,
    user_profile_contact_no AS contact_number,
    user_profile_created_at AS created_at
    FROM user_profile
	WHERE user_id = ?
	LIMIT 1";
if ($stmt = $conn->prepare($profileSql)) {
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$profile = $result->fetch_assoc() ?: [];
	$stmt->close();
}

$company = [];
$companySql = "SELECT
	c.company_name,
	c.description,
	c.industry,
	c.location,
	c.website,
	c.logo,
	c.facebook_link,
	c.linkedin_link
	FROM company c WHERE c.user_id = ? LIMIT 1";
if ($stmt = $conn->prepare($companySql)) {
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$company = $result->fetch_assoc() ?: [];
	$stmt->close();
}

$jobStats = [
	'total_jobs' => 0,
	'open_jobs' => 0,
	'total_vacancies' => 0,
	'avg_budget' => null,
	'last_post_at' => null,
];
$statsSql = "SELECT
	COUNT(*) AS total_jobs,
	SUM(CASE WHEN jp.job_status_id = 1 THEN 1 ELSE 0 END) AS open_jobs,
	SUM(COALESCE(jp.vacancies, 0)) AS total_vacancies,
	AVG(jp.budget) AS avg_budget,
	MAX(jp.created_at) AS last_post_at
	FROM job_post jp
	LEFT JOIN company c ON jp.company_id = c.company_id
	WHERE (jp.user_id = ? OR c.user_id = ?)";
if ($stmt = $conn->prepare($statsSql)) {
	$stmt->bind_param('ii', $userId, $userId);
	$stmt->execute();
	$stmt->bind_result($totalJobs, $openJobs, $totalVacancies, $avgBudget, $lastPostAt);
	if ($stmt->fetch()) {
		$jobStats['total_jobs'] = (int)($totalJobs ?? 0);
		$jobStats['open_jobs'] = (int)($openJobs ?? 0);
		$jobStats['total_vacancies'] = (int)($totalVacancies ?? 0);
		$jobStats['avg_budget'] = $avgBudget !== null ? (float)$avgBudget : null;
		$jobStats['last_post_at'] = $lastPostAt;
	}
	$stmt->close();
}

$matchesCount = 0;
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM matches WHERE employer_id = ?')) {
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->bind_result($matchesCount);
	$stmt->fetch();
	$stmt->close();
}

$talentScore = null; // Talent score disabled: table dropped, show static placeholder

$teams = [];
$teamSql = "SELECT
    COALESCE(jc_from_skills.job_category_name, 'Uncategorized') AS category_name,
    COUNT(*) AS job_count,
    SUM(COALESCE(jp.vacancies, 0)) AS total_vacancies
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
LEFT JOIN (
    SELECT jps.job_post_id, jc.job_category_name
    FROM job_post_skills jps
    INNER JOIN job_category jc ON jps.job_category_id = jc.job_category_id
    GROUP BY jps.job_post_id
) jc_from_skills ON jp.job_post_id = jc_from_skills.job_post_id
WHERE (jp.user_id = ? OR c.user_id = ?)
GROUP BY category_name
ORDER BY job_count DESC, total_vacancies DESC
LIMIT 4";
if ($stmt = $conn->prepare($teamSql)) {
	$stmt->bind_param('ii', $userId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) {
		$teams[] = $row;
	}
	$stmt->close();
}

$recentActivities = [];
$activitySql = "SELECT job_post_name, created_at, updated_at
	FROM job_post jp
	LEFT JOIN company c ON jp.company_id = c.company_id
	WHERE (jp.user_id = ? OR c.user_id = ?)
	ORDER BY COALESCE(jp.updated_at, jp.created_at) DESC
	LIMIT 3";
if ($stmt = $conn->prepare($activitySql)) {
	$stmt->bind_param('ii', $userId, $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	while ($row = $result->fetch_assoc()) {
		$updatedAt = $row['updated_at'] ?? null;
		$createdAt = $row['created_at'] ?? null;
		$isUpdated = $updatedAt && $updatedAt !== $createdAt;
		$row['activity_status'] = $isUpdated ? 'Updated' : 'Published';
		$row['activity_timestamp'] = $isUpdated ? $updatedAt : $createdAt;
		$recentActivities[] = $row;
	}
	$stmt->close();
}

$companyName = isset($company['company_name']) && $company['company_name'] !== '' ? $company['company_name'] : 'Your Company';
$heroLocation = isset($company['location']) && $company['location'] !== '' ? $company['location'] : 'Not set';
$hiringSinceYear = isset($profile['created_at']) ? date('Y', strtotime($profile['created_at'])) : date('Y');
$avatarInitials = wm_initials($companyName, ($profile['first_name'] ?? ''), ($profile['last_name'] ?? ''));
$avgBudgetDisplay = $jobStats['avg_budget'] !== null ? 'PHP ' . number_format((float)$jobStats['avg_budget'], 2) : 'Budget TBD';
$talentScoreDisplay = $talentScore !== null ? $talentScore : '—';
$primaryContact = trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')) ?: 'Not set';
$contactEmail = $profile['email'] ?? 'Not set';
$companyWebsite = isset($company['website']) ? $company['website'] : '';
$companyIndustry = isset($company['industry']) && $company['industry'] !== '' ? $company['industry'] : 'Not set';
$companyDescription = isset($company['description']) && $company['description'] !== '' ? $company['description'] : 'Not set';
$companyHeadquarters = isset($company['location']) && $company['location'] !== '' ? $company['location'] : 'Not set';
$companyLogoPath = $company['logo'] ?? null;
$contactNumber = isset($profile['contact_number']) && $profile['contact_number'] !== '' ? $profile['contact_number'] : 'Not set';

$genderValue = $profile['gender'] ?? null;
$genderDisplay = $genderValue !== null && $genderValue !== '' ? ucwords($genderValue) : 'Not set';

$ageDisplay = 'Not set';
$dobValue = $profile['dob'] ?? null;
if ($dobValue) {
	$dobDate = DateTime::createFromFormat('Y-m-d', $dobValue);
	$today = new DateTime('today');
	if ($dobDate && $dobDate <= $today) {
		$ageDisplay = (string)$dobDate->diff($today)->y;
	}
}

include 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Employer Profile - WorkMuna</title>
	<!-- Google Fonts - Roboto -->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<!-- Add Font Awesome if not present -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
	<style>
		:root {
			--profile-bg: #f5f7fb;
			--profile-surface: #ffffff;
			--profile-border: #e2e8f0;
			--profile-muted: #4a5568;
			--profile-heading: #000000;
			--profile-accent: #1f7bff;
			--profile-success: #2fbd67;
		}

		body {
			margin: 0;
			background: var(--profile-bg);
			font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
			color: var(--profile-heading);
		}

		.profile-shell {
			background: var(--profile-bg);
			min-height: calc(100vh - 80px);
			padding: 1.5rem clamp(1rem, 6vw, 4rem) 3rem;
		}

		.profile-dashboard {
			max-width: 1120px;
			margin: 0 auto;
			display: flex;
			flex-direction: column;
			gap: 2rem;
		}

		.profile-card {
			background: var(--profile-surface);
			border-radius: 1.25rem;
			border: 1px solid var(--profile-border);
			box-shadow: 0 18px 55px rgba(15, 23, 42, 0.08);
			padding: clamp(1.5rem, 3vw, 2.5rem);
		}

		/* Modal Styles */
		.modal-overlay {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(15, 23, 42, 0.6);
			backdrop-filter: blur(4px);
			display: flex;
			align-items: center;
			justify-content: center;
			z-index: 9999;
			padding: 1rem;
			animation: fadeIn 0.2s ease;
		}

		.modal-overlay.is-hidden {
			display: none;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
			}
			to {
				opacity: 1;
			}
		}

		.modal-container {
			background: var(--profile-surface);
			border-radius: 1.25rem;
			box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
			width: 100%;
			max-width: 900px;
			max-height: 90vh;
			display: flex;
			flex-direction: column;
			animation: slideUp 0.3s ease;
			position: relative;
		}

		@keyframes slideUp {
			from {
				transform: translateY(20px);
				opacity: 0;
			}
			to {
				transform: translateY(0);
				opacity: 1;
			}
		}

		.modal-header {
			padding: 2rem 2.5rem 1.5rem;
			border-bottom: 1px solid var(--profile-border);
			display: flex;
			align-items: center;
			justify-content: space-between;
		}

		.modal-header h3 {
			margin: 0;
			font-size: 1.5rem;
			color: var(--profile-heading);
		}

		.modal-header p {
			margin: 0.3rem 0 0;
			color: var(--profile-muted);
			font-size: 0.9rem;
		}

		.modal-close-btn {
			background: transparent;
			border: none;
			width: 36px;
			height: 36px;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			color: var(--profile-muted);
			font-size: 1.3rem;
			transition: all 0.2s;
			flex-shrink: 0;
			margin-left: 1rem;
		}

		.modal-close-btn:hover {
			background: #f3f6ff;
			color: var(--profile-accent);
		}

		.modal-body {
			padding: 2rem 2.5rem;
			overflow-y: auto;
			flex: 1;
		}

		.modal-footer {
			padding: 1.5rem 2.5rem;
			border-top: 1px solid var(--profile-border);
			display: flex;
			gap: 0.75rem;
			justify-content: flex-end;
			align-items: center;
			background: #fafbfc;
			border-radius: 0 0 1.25rem 1.25rem;
		}

		.form-section {
			margin-bottom: 2rem;
		}

		.form-section:last-child {
			margin-bottom: 0;
		}

		.form-section-header {
			margin-bottom: 1.25rem;
			padding-bottom: 0.75rem;
			border-bottom: 2px solid var(--profile-border);
		}

		.form-section-header h4 {
			margin: 0;
			font-size: 1.1rem;
			color: var(--profile-heading);
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.form-section-header h4 i {
			color: var(--profile-accent);
			font-size: 1rem;
		}

		.form-section-header p {
			margin: 0.3rem 0 0;
			color: var(--profile-muted);
			font-size: 0.85rem;
		}

		.form-grid {
			display: grid;
			grid-template-columns: repeat(2, 1fr);
			gap: 1.25rem;
		}

		.form-field {
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
		}

		.form-field label {
			font-size: 0.875rem;
			font-weight: 600;
			color: var(--profile-heading);
			display: flex;
			align-items: center;
			gap: 0.3rem;
		}

		.form-field label .optional {
			font-weight: 400;
			color: var(--profile-muted);
			font-size: 0.8rem;
		}

		.form-field input,
		.form-field textarea {
			border: 1px solid var(--profile-border);
			border-radius: 0.5rem;
			padding: 0.75rem 1rem;
			font-family: inherit;
			font-size: 0.95rem;
			background: #fff;
			transition: border-color 0.2s, box-shadow 0.2s;
		}

		.form-field input:focus,
		.form-field textarea:focus {
			outline: none;
			border-color: var(--profile-accent);
			box-shadow: 0 0 0 3px rgba(31, 123, 255, 0.1);
		}

		.form-field textarea {
			min-height: 120px;
			resize: vertical;
		}

		.form-field.full-row {
			grid-column: 1 / -1;
		}

		.form-alert {
			border-radius: 0.85rem;
			padding: 0.85rem 1rem;
			margin-bottom: 1rem;
			font-size: 0.9rem;
			border: 1px solid transparent;
		}

		.form-alert ul {
			margin: 0.35rem 0 0;
			padding-left: 1.1rem;
		}

		.form-alert-success {
			background: rgba(47, 189, 103, 0.12);
			color: var(--profile-success);
			border-color: rgba(47, 189, 103, 0.35);
		}

		.form-alert-error {
			background: rgba(239, 68, 68, 0.12);
			color: #b91c1c;
			border-color: rgba(239, 68, 68, 0.25);
		}

		.btn.secondary {
			background: #f1f5f9;
			color: var(--profile-heading);
			border: 1px solid var(--profile-border);
		}

		.btn.secondary:hover {
			background: #e2e8f0;
		}

		.btn:disabled {
			opacity: 0.5;
			cursor: not-allowed;
		}

		.profile-hero {
			background: linear-gradient(to bottom, #ffffff 0%, #f8fafb 100%);
			position: relative;
			overflow: hidden;
		}

		.hero-content {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 1.5rem;
			position: relative;
			z-index: 1;
		}

		.avatar-ring {
			width: 110px;
			height: 110px;
			border-radius: 999px;
			background: var(--profile-accent);
			padding: 3px;
			flex-shrink: 0;
			box-shadow: 0 6px 16px rgba(31, 123, 255, 0.15);
			transition: all 0.3s ease;
			position: relative;
		}

		.avatar-ring:hover {
			transform: translateY(-4px);
			box-shadow: 0 12px 30px rgba(31, 123, 255, 0.25);
		}

		.avatar-ring span {
			width: 100%;
			height: 100%;
			border-radius: inherit;
			background: #fff;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			font-size: 1.8rem;
			font-weight: 600;
			color: var(--profile-heading);
		}

		.hero-meta {
			flex: 1;
			min-width: 300px;
		}

		.eyebrow {
			margin: 0 0 0.5rem;
			font-size: 0.65rem;
			text-transform: uppercase;
			letter-spacing: 0.15em;
			color: var(--profile-accent);
			font-weight: 700;
			display: inline-flex;
			align-items: center;
			gap: 0.4rem;
			padding: 0.35rem 1rem;
			background: rgba(31, 123, 255, 0.08);
			border-radius: 999px;
			border: 1px solid rgba(31, 123, 255, 0.2);
		}

		.hero-meta h1 {
			margin: 0.5rem 0 0;
			font-size: clamp(1.75rem, 3.5vw, 2.25rem);
			color: var(--profile-heading);
			line-height: 1.1;
			font-weight: 700;
			letter-spacing: -0.02em;
		}

		.hero-meta p {
			margin: 0.6rem 0;
			color: var(--profile-muted);
			line-height: 1.6;
			font-size: 0.9rem;
		}

		.hero-info-row {
			display: flex;
			flex-wrap: wrap;
			gap: 1rem;
			margin-top: 0.75rem;
			align-items: center;
		}

		.hero-info-item {
			display: flex;
			align-items: center;
			gap: 0.4rem;
			color: var(--profile-muted);
			font-size: 0.85rem;
			font-weight: 500;
		}

		.hero-info-item i {
			font-size: 0.9rem;
			color: var(--profile-accent);
		}

		.hero-description {
			margin-top: 1rem;
			color: var(--profile-muted);
			max-width: 680px;
			line-height: 1.6;
			font-size: 0.9rem;
			background: rgba(31, 123, 255, 0.03);
			padding: 0.875rem 1rem;
			border-radius: 0.625rem;
		}

		.hero-actions {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
			margin-top: 1.25rem;
		}

		.btn {
			border: none;
			border-radius: 8px;
			padding: 0.75rem 1.5rem;
			font-weight: 600;
			font-size: 0.875rem;
			cursor: pointer;
			transition: all 0.2s ease;
			display: inline-flex;
			align-items: center;
			gap: 0.5rem;
		}

		.btn.primary {
			background: var(--profile-accent);
			color: #ffffff;
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.2);
		}

		.btn.primary:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 20px rgba(31, 123, 255, 0.3);
			background: #0d6efd;
		}

		.btn.ghost {
			background: transparent;
			border: 2px solid var(--profile-border);
			color: var(--profile-heading);
		}

		.btn.ghost:hover {
			background: #f8f9fa;
			border-color: var(--profile-accent);
			color: var(--profile-accent);
			transform: translateY(-2px);
		}

		.stats-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
			gap: 1rem;
		}

		.stat-card {
			border: 1px solid var(--profile-border);
			border-radius: 1rem;
			padding: 1.25rem 1.5rem;
			background: #f8faff;
			transition: all 0.2s;
		}

		.stat-card:hover {
			border-color: var(--profile-accent);
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.1);
		}

		.stat-card p {
			margin: 0;
		}

		.stat-label {
			font-size: 0.8rem;
			text-transform: uppercase;
			letter-spacing: 0.08em;
			color: var(--profile-muted);
			font-weight: 600;
		}

		.stat-value {
			margin-top: 0.5rem;
			font-size: 2rem;
			font-weight: 700;
			color: #000000;
			line-height: 1;
		}

		.stat-caption {
			margin-top: 0.4rem;
			font-size: 0.85rem;
			color: var(--profile-muted);
			line-height: 1.4;
		}

		@media (max-width: 768px) {
			.stats-grid {
				grid-template-columns: 1fr;
				gap: 0;
				background: #f8faff;
				border-radius: 1rem;
				overflow: hidden;
			}

			.stat-card {
				border: none;
				border-radius: 0;
				border-bottom: 1px solid var(--profile-border);
				padding: 1rem 1.25rem;
				background: #fff;
				display: flex;
				justify-content: space-between;
				align-items: center;
			}

			.stat-card:hover {
				border-color: var(--profile-border);
				box-shadow: none;
				background: #fafcff;
			}

			.stat-card:last-child {
				border-bottom: none;
			}

			.stat-info {
				flex: 1;
			}

			.stat-value {
				margin-top: 0;
				font-size: 2rem;
				flex-shrink: 0;
				margin-left: 1rem;
			}

			.stat-caption {
				margin-top: 0.25rem;
			}

			/* Mobile Hero Optimization */
			.profile-hero {
				padding: 1.5rem 1rem !important;
				background: linear-gradient(to bottom, #f8fafb 0%, #ffffff 100%);
			}

			.hero-content {
				gap: 1.25rem;
				flex-direction: column;
				text-align: center;
				align-items: center;
			}

			.avatar-ring {
				width: 120px;
				height: 120px;
				padding: 4px;
				margin: 0 auto;
			}

			.avatar-ring span {
				font-size: 2.2rem;
			}

			.hero-meta {
				min-width: 0;
				width: 100%;
				display: flex;
				flex-direction: column;
				align-items: center;
			}

			.eyebrow {
				margin: 0 auto 0.6rem;
				font-size: 0.65rem;
				padding: 0.35rem 0.9rem;
				gap: 0.35rem;
				width: fit-content;
			}

			.eyebrow i {
				font-size: 0.7rem;
			}

			.hero-meta h1 {
				margin: 0.5rem 0 0;
				font-size: 1.65rem;
				text-align: center;
			}

			.hero-info-row {
				gap: 0.75rem;
				margin-top: 0.6rem;
				justify-content: center;
				flex-wrap: wrap;
			}

			.hero-info-item {
				font-size: 1rem;
				gap: 0.35rem;
			}

			.hero-info-item i {
				font-size: 1rem;
			}

			.hero-description {
				margin-top: 0.85rem;
				font-size: 1rem;
				padding: 0.75rem 0.9rem;
				line-height: 1.55;
				text-align: center;
			}

			.hero-actions {
				margin-top: 1.1rem;
				gap: 0.7rem;
				justify-content: center;
				width: 100%;
			}

			.btn {
				padding: 0.7rem 1.4rem;
				font-size: 1rem;
				border-radius: 8px;
				flex: 1;
				max-width: 180px;
			}

			/* Profile Cards */
			.profile-card {
				padding: 1rem;
				border-radius: 1rem;
			}

			.profile-dashboard {
				gap: 1rem;
			}

			.profile-shell {
				padding: 1rem 0.75rem 2rem;
			}

			/* Detail sections */
			.detail-section h3 {
				font-size: 1rem;
				margin-bottom: 0.65rem;
			}

			.detail-item {
				padding: 0.65rem 0;
				flex-direction: column;
				align-items: flex-start;
				gap: 0.35rem;
			}

			.detail-item-label {
				font-size: 1rem;
				min-width: 0;
			}

			.detail-item-value {
				text-align: left;
				font-size: 1rem;
			}

			/* Social links */
			.social-link-item {
				padding: 0.75rem;
			}

			.social-icon {
				width: 40px;
				height: 40px;
				font-size: 1.2rem;
			}

			.social-link-content strong {
				font-size: 1rem;
			}

			.social-link-content span {
				font-size: 1rem;
			}
		}

		.detail-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
			gap: 1.5rem;
		}

		.detail-grid-three {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 1.5rem;
		}

		@media (min-width: 1024px) {
			.detail-grid-three {
				grid-template-columns: repeat(3, 1fr);
			}
		}

		.detail-section h3 {
			margin: 0 0 0.85rem;
			font-size: 1.1rem;
		}

		.detail-list {
			margin: 0;
			padding: 0;
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: 0.75rem;
		}

		.detail-item {
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 1rem;
			padding: 0.85rem 0;
			border-bottom: 1px solid #eef2ff;
			transition: all 0.2s;
		}

		.detail-item:hover {
			padding-left: 0.5rem;
			background: #fafcff;
			margin-left: -0.5rem;
			margin-right: -0.5rem;
			padding-right: 0.5rem;
			border-radius: 0.5rem;
		}

		.detail-item:last-child {
			border-bottom: none;
		}

		.detail-item-label {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			color: var(--profile-muted);
			font-size: 0.9rem;
			min-width: 140px;
		}

		.detail-item-label i {
			width: 18px;
			text-align: center;
			color: var(--profile-accent);
			font-size: 0.95rem;
		}

		.detail-item-value {
			flex: 1;
			text-align: right;
			font-weight: 500;
		}

		.detail-item-value a {
			color: var(--profile-accent);
			text-decoration: none;
			transition: all 0.2s;
		}

		.detail-item-value a:hover {
			text-decoration: underline;
			color: #0d5fd4;
		}

		.social-links {
			display: flex;
			gap: 0.75rem;
			justify-content: flex-end;
		}

		.social-links a {
			width: 36px;
			height: 36px;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.2s;
			text-decoration: none;
		}

		.social-links a:hover {
			transform: translateY(-2px);
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		}

		.social-links .facebook {
			background: #1877f2;
			color: white;
		}

		.social-links .linkedin {
			background: #0a66c2;
			color: white;
		}

		.social-links-large {
			display: flex;
			flex-direction: column;
			gap: 1rem;
			margin-top: 0.5rem;
		}

		.social-link-item {
			display: flex;
			align-items: center;
			gap: 1rem;
			padding: 1rem;
			border: 1px solid var(--profile-border);
			border-radius: 0.75rem;
			transition: all 0.2s;
			text-decoration: none;
			background: #fafcff;
		}

		.social-link-item:hover {
			border-color: var(--profile-accent);
			background: #fff;
			transform: translateX(4px);
			box-shadow: 0 4px 12px rgba(31, 123, 255, 0.1);
		}

		.social-icon {
			width: 48px;
			height: 48px;
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 1.5rem;
			flex-shrink: 0;
		}

		.social-icon.facebook {
			background: #1877f2;
			color: white;
		}

		.social-icon.linkedin {
			background: #0a66c2;
			color: white;
		}

		.social-link-content {
			flex: 1;
		}

		.social-link-content strong {
			display: block;
			color: var(--profile-heading);
			font-size: 1rem;
			margin-bottom: 0.2rem;
		}

		.social-link-content span {
			display: block;
			color: var(--profile-muted);
			font-size: 0.85rem;
		}

		.empty-state {
			text-align: center;
			padding: 2rem 1rem;
			color: var(--profile-muted);
		}

		.empty-state i {
			font-size: 3rem;
			opacity: 0.3;
			margin-bottom: 1rem;
			display: block;
		}

		.empty-state p {
			margin: 0;
			font-size: 0.9rem;
		}

		.about-company {
			margin-top: 1.5rem;
			padding-top: 1.5rem;
			border-top: 2px solid var(--profile-border);
		}

		.about-company h4 {
			margin: 0 0 0.75rem;
			font-size: 1rem;
			color: var(--profile-heading);
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}

		.about-company h4 i {
			color: var(--profile-accent);
		}

		.about-company p {
			margin: 0;
			color: var(--profile-muted);
			line-height: 1.6;
			font-size: 0.95rem;
		}

		.profile-status-badge {
			display: inline-flex;
			align-items: center;
			padding: 0.25rem 0.75rem;
			border-radius: 999px;
			background: rgba(47, 189, 103, 0.12);
			color: var(--profile-success);
			font-weight: 600;
			font-size: 0.9rem;
		}

		.notes-list {
			list-style: none;
			margin: 0;
			padding: 0;
			display: flex;
			flex-direction: column;
			gap: 1rem;
		}

		.note {
			border: 1px dashed var(--profile-border);
			border-radius: 1rem;
			padding: 1rem 1.2rem;
		}

		.note p {
			margin: 0;
			line-height: 1.5;
			color: var(--profile-heading);
		}

		.note time {
			display: block;
			font-size: 0.85rem;
			color: var(--profile-muted);
			margin-bottom: 0.3rem;
		}

		.note-meta {
			margin: 0.25rem 0 0;
			font-size: 0.8rem;
			color: var(--profile-muted);
			line-height: 1.4;
		}

		.preferences-list {
			display: flex;
			flex-direction: column;
			gap: 1rem;
			margin: 0;
			padding: 0;
			list-style: none;
		}

		.preference-row {
			display: flex;
			justify-content: space-between;
			gap: 1rem;
			align-items: center;
			border: 1px solid var(--profile-border);
			border-radius: 1rem;
			padding: 0.9rem 1.2rem;
		}

		.switch {
			position: relative;
			width: 44px;
			height: 24px;
		}

		.switch input {
			opacity: 0;
			width: 0;
			height: 0;
		}

		.slider {
			position: absolute;
			cursor: pointer;
			inset: 0;
			background: #cbd5f5;
			border-radius: 999px;
			transition: background 0.2s ease;
		}

		.slider::before {
			content: "";
			position: absolute;
			height: 18px;
			width: 18px;
			left: 3px;
			top: 3px;
			background: #fff;
			border-radius: 50%;
			transition: transform 0.2s ease;
		}

		.switch input:checked + .slider {
			background: var(--profile-accent);
		}

		.switch input:checked + .slider::before {
			transform: translateX(20px);
		}

		.teams-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 1rem;
		}

		.team-card {
			border: 1px solid var(--profile-border);
			border-radius: 1rem;
			padding: 1rem 1.25rem;
			background: #fafcff;
		}

		.team-card h4 {
			margin: 0;
			font-size: 1rem;
		}

		.team-card p {
			margin: 0.2rem 0 0;
			color: var(--profile-muted);
			font-size: 0.9rem;
		}

		@media (max-width: 768px) {
			.modal-container {
				width: 100%;
				max-height: 100vh;
				border-radius: 0;
			}

			.modal-header,
			.modal-body,
			.modal-footer {
				padding: 1.25rem;
			}

			.modal-header h3 {
				font-size: 1.25rem;
			}

			.form-grid {
				grid-template-columns: 1fr;
				gap: 1rem;
			}

			.form-section {
				margin-bottom: 1.5rem;
			}

			.form-section-header {
				margin-bottom: 1rem;
			}

			.form-section-header h4 {
				font-size: 1rem;
			}

			.form-field input,
			.form-field textarea {
				padding: 0.65rem 0.85rem;
				font-size: 1rem;
			}

			.modal-footer {
				flex-direction: column-reverse;
			}

			.modal-footer .btn {
				width: 100%;
				justify-content: center;
			}
		}

		@media (max-width: 680px) {
			.profile-card {
				padding: 0.875rem;
				border-radius: 1rem;
			}

			.detail-item {
				padding: 0.5rem 0;
				gap: 0.25rem;
			}

			.form-grid {
				gap: 0.875rem;
			}

			/* Extra small text for tiny screens */
			.hero-meta h1 {
				font-size: 1.35rem;
			}

			.eyebrow {
				font-size: 0.55rem;
				padding: 0.2rem 0.65rem;
			}

			.hero-info-item {
				font-size: 1rem;
			}

			.hero-description {
				font-size: 1rem;
				padding: 0.5rem 0.65rem;
			}

			.btn {
				padding: 0.55rem 1rem;
				font-size: 1rem;
			}

			.avatar-ring {
				width: 100px;
				height: 100px;
			}

			.avatar-ring span {
				font-size: 1.8rem;
			}

			.stat-value {
				font-size: 1.35rem;
			}

			.stat-label {
				font-size: 1rem;
			}

			.detail-section h3 {
				font-size: 1rem;
			}

			.modal-header h3 {
				font-size: 1.15rem;
			}

			.profile-shell {
				padding: 0.75rem 0.5rem 1.5rem;
			}

			.detail-item-label {
				font-size: 1rem;
			}

			.detail-item-value {
				font-size: 1rem;
			}

			.detail-item {
				flex-direction: column;
				align-items: flex-start;
			}

			.form-grid {
				grid-template-columns: 1fr;
			}

			.preference-row {
				flex-direction: column;
				align-items: flex-start;
			}

			.hero-actions {
				width: 100%;
			}

			.hero-actions .btn {
				flex: 1;
				text-align: center;
			}
		}

		/* Logo Upload Styles */
		.logo-upload-wrapper {
			display: flex;
			align-items: center;
			gap: 1.5rem;
			padding: 1rem;
			background: #f8fafc;
			border: 2px dashed var(--profile-border);
			border-radius: 12px;
			transition: all 0.2s ease;
		}

		.logo-upload-wrapper:hover {
			border-color: var(--profile-accent);
			background: #f0f7ff;
		}

		.logo-upload-wrapper.dragover {
			border-color: var(--profile-accent);
			background: #e8f4ff;
		}

		.logo-preview {
			width: 80px;
			height: 80px;
			border-radius: 12px;
			object-fit: cover;
			background: var(--profile-surface);
			border: 2px solid var(--profile-border);
			display: flex;
			align-items: center;
			justify-content: center;
			overflow: hidden;
			flex-shrink: 0;
		}

		.logo-preview img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.logo-preview-placeholder {
			width: 100%;
			height: 100%;
			display: flex;
			align-items: center;
			justify-content: center;
			background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
			color: #64748b;
			font-size: 1.5rem;
		}

		.logo-upload-content {
			flex: 1;
			min-width: 0;
		}

		.logo-upload-content h5 {
			margin: 0 0 0.25rem;
			font-size: 0.95rem;
			font-weight: 600;
			color: var(--profile-heading);
		}

		.logo-upload-content p {
			margin: 0;
			font-size: 0.8rem;
			color: var(--profile-muted);
		}

		.logo-upload-actions {
			display: flex;
			gap: 0.5rem;
			margin-top: 0.75rem;
		}

		.logo-upload-btn {
			padding: 0.5rem 1rem;
			font-size: 0.85rem;
			font-weight: 500;
			border: none;
			border-radius: 6px;
			cursor: pointer;
			transition: all 0.2s ease;
		}

		.logo-upload-btn.primary {
			background: var(--profile-accent);
			color: white;
		}

		.logo-upload-btn.primary:hover {
			background: #1a6de0;
		}

		.logo-upload-btn.secondary {
			background: transparent;
			color: #dc2626;
			border: 1px solid #fecaca;
		}

		.logo-upload-btn.secondary:hover {
			background: #fef2f2;
		}

		.logo-upload-input {
			display: none;
		}

		@media (max-width: 480px) {
			.logo-upload-wrapper {
				flex-direction: column;
				text-align: center;
			}

			.logo-upload-actions {
				justify-content: center;
			}
		}
	</style>
</head>
<body data-update-success="<?php echo $updateSuccess ? 'true' : 'false'; ?>" data-update-message="<?php echo wm_safe($successMessage); ?>">
	<div class="profile-shell">
		<main class="profile-dashboard">
			<section class="profile-card profile-hero">
				<div class="hero-content">
					<div class="avatar-ring" aria-hidden="true">
						<?php if ($companyLogoPath && file_exists(dirname(__DIR__) . '/' . $companyLogoPath)): ?>
							<span><img src="<?php echo wm_safe('../' . $companyLogoPath); ?>" alt="<?php echo wm_safe($companyName); ?> logo" style="width:100%;height:100%;object-fit:cover;border-radius:999px;" /></span>
						<?php else: ?>
							<span><?php echo wm_safe($avatarInitials); ?></span>
						<?php endif; ?>
					</div>
					<div class="hero-meta">
						<p class="eyebrow"><i class="fas fa-building"></i>Organization Profile</p>
						<h1><?php echo wm_safe($companyName); ?></h1>
						<div class="hero-info-row">
							<span class="hero-info-item"><i class="fas fa-map-marker-alt"></i><?php echo wm_safe($heroLocation); ?></span>
							<span class="hero-info-item"><i class="fas fa-calendar-check"></i>Since <?php echo wm_safe($hiringSinceYear); ?></span>
							<?php if ($jobStats['last_post_at']): ?>
							<span class="hero-info-item"><i class="fas fa-clock"></i><?php echo wm_safe(wm_format_date($jobStats['last_post_at'])); ?></span>
							<?php endif; ?>
							<?php if ($jobStats['open_jobs'] > 0): ?>
							<span class="hero-info-item" style="background: rgba(47, 189, 103, 0.2); padding: 0.25rem 0.75rem; border-radius: 999px;"><i class="fas fa-check-circle"></i><?php echo $jobStats['open_jobs']; ?> Active <?php echo $jobStats['open_jobs'] === 1 ? 'Role' : 'Roles'; ?></span>
							<?php endif; ?>
						</div>
						<?php if ($companyDescription !== 'Not set'): ?>
						<p class="hero-description">
							<?php echo wm_safe($companyDescription); ?>
						</p>
						<?php endif; ?>
						<div class="hero-actions">
							<button class="btn primary" type="button" data-action="update-profile"><i class="fas fa-edit"></i>Update Profile</button>
						</div>
					</div>
				</div>
			</section>

			<!-- Modal for Profile Editing -->
			<div class="modal-overlay<?php echo $formShouldBeOpen ? '' : ' is-hidden'; ?>" id="profile-modal">
				<div class="modal-container">
					<div class="modal-header">
						<div>
							<h3>Update Company Profile</h3>
							<p>Keep your company information current and professional</p>
						</div>
						<button class="modal-close-btn" type="button" id="close-modal" aria-label="Close modal">
							<i class="fa fa-times"></i>
						</button>
					</div>

					<div class="modal-body">
						<?php if ($updateFeedback && $updateFeedback['status'] === 'error'): ?>
							<div class="form-alert form-alert-error">
								<strong>Please fix the following errors:</strong>
								<?php if (!empty($updateFeedback['messages'])): ?>
									<ul>
										<?php foreach ($updateFeedback['messages'] as $msg): ?>
											<li><?php echo wm_safe($msg); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<form method="post" id="profile-form" enctype="multipart/form-data" novalidate>
							<input type="hidden" name="action" value="update_profile" />

							<!-- Contact Details Section -->
							<div class="form-section">
								<div class="form-section-header">
									<h4><i class="fa fa-user"></i> Contact Details</h4>
									<p>Primary contact information for your account</p>
								</div>
								<div class="form-grid">
									<div class="form-field">
										<label for="first_name">First Name *</label>
										<input type="text" id="first_name" name="first_name" value="<?php echo wm_safe($profile['first_name'] ?? ''); ?>" required autocomplete="given-name" />
									</div>
									<div class="form-field">
										<label for="middle_name">Middle Name <span class="optional">(Optional)</span></label>
										<input type="text" id="middle_name" name="middle_name" value="<?php echo wm_safe($profile['middle_name'] ?? ''); ?>" autocomplete="additional-name" />
									</div>
									<div class="form-field">
										<label for="last_name">Last Name *</label>
										<input type="text" id="last_name" name="last_name" value="<?php echo wm_safe($profile['last_name'] ?? ''); ?>" required autocomplete="family-name" />
									</div>
									<div class="form-field">
										<label for="contact_number">Contact Number</label>
										<input
											type="tel"
											id="contact_number"
											name="contact_number"
											value="<?php echo wm_safe($profile['contact_number'] ?? ''); ?>"
											placeholder="09171234567"
											autocomplete="tel"
											inputmode="numeric"
											pattern="\d{11}"
											maxlength="11"
										/>
									</div>
								</div>
							</div>

							<!-- Company Details Section -->
							<div class="form-section">
								<div class="form-section-header">
									<h4><i class="fa fa-building"></i> Company Details</h4>
									<p>Your company's professional information</p>
								</div>
								
								<!-- Company Logo Upload -->
								<div class="form-field full-row" style="margin-bottom: 1.5rem;">
									<label>Company Logo <span class="optional">(Optional)</span></label>
									<div class="logo-upload-wrapper" id="logo-upload-wrapper">
										<div class="logo-preview" id="logo-preview">
											<?php if ($companyLogoPath && file_exists(dirname(__DIR__) . '/' . $companyLogoPath)): ?>
												<img src="<?php echo wm_safe('../' . $companyLogoPath); ?>" alt="Current logo" id="logo-preview-img" />
											<?php else: ?>
												<div class="logo-preview-placeholder" id="logo-placeholder">
													<i class="fas fa-building"></i>
												</div>
											<?php endif; ?>
										</div>
										<div class="logo-upload-content">
											<h5>Upload Company Logo</h5>
											<p>JPG, PNG, GIF or WebP. Max 2MB. Recommended: 200x200px</p>
											<div class="logo-upload-actions">
												<button type="button" class="logo-upload-btn primary" id="logo-choose-btn">
													<i class="fas fa-upload"></i> Choose File
												</button>
												<button type="button" class="logo-upload-btn secondary" id="logo-remove-btn" style="<?php echo ($companyLogoPath && file_exists(dirname(__DIR__) . '/' . $companyLogoPath)) ? '' : 'display:none;'; ?>">
													<i class="fas fa-trash"></i> Remove
												</button>
											</div>
										</div>
										<input type="file" name="company_logo" id="company_logo" class="logo-upload-input" accept="image/jpeg,image/png,image/gif,image/webp" />
									</div>
								</div>

								<div class="form-grid">
									<div class="form-field">
										<label for="company_name">Company Name *</label>
										<input type="text" id="company_name" name="company_name" value="<?php echo wm_safe($company['company_name'] ?? ''); ?>" required autocomplete="organization" />
									</div>
									<div class="form-field">
										<label for="company_industry">Industry</label>
										<input type="text" id="company_industry" name="company_industry" value="<?php echo wm_safe($company['industry'] ?? ''); ?>" placeholder="e.g., Technology, Healthcare" />
									</div>
									<div class="form-field full-row">
										<label for="company_location">Headquarters</label>
										<input type="text" id="company_location" name="company_location" value="<?php echo wm_safe($company['location'] ?? ''); ?>" placeholder="City, Country" autocomplete="organization" />
									</div>
									<div class="form-field full-row">
										<label for="company_description">About the Company</label>
										<textarea id="company_description" name="company_description" placeholder="Tell us about your company..." spellcheck="true"><?php echo wm_safe($company['description'] ?? ''); ?></textarea>
									</div>
								</div>
							</div>

							<!-- Social Links Section -->
							<div class="form-section">
								<div class="form-section-header">
									<h4><i class="fa fa-link"></i> Social Links</h4>
									<p>Connect your business social media profiles</p>
								</div>
								<div class="form-grid">
									<div class="form-field">
										<label for="company_website"><i class="fab fa-chrome"></i> Website</label>
										<input type="url" id="company_website" name="company_website" value="<?php echo wm_safe($company['website'] ?? ''); ?>" placeholder="https://yourcompany.com" autocomplete="url" />
									</div>
									<div class="form-field">
										<label for="facebook_link"><i class="fab fa-facebook"></i> Facebook</label>
										<input type="url" id="facebook_link" name="facebook_link" value="<?php echo wm_safe($company['facebook_link'] ?? ''); ?>" placeholder="https://facebook.com/yourcompany" />
									</div>
									<div class="form-field">
										<label for="linkedin_link"><i class="fab fa-linkedin"></i> LinkedIn</label>
										<input type="url" id="linkedin_link" name="linkedin_link" value="<?php echo wm_safe($company['linkedin_link'] ?? ''); ?>" placeholder="https://linkedin.com/company/yourcompany" />
									</div>
								</div>
							</div>
						</form>
					</div>

					<div class="modal-footer">
						<button class="btn secondary" type="button" id="cancel-modal">Cancel</button>
						<button class="btn primary" type="submit" form="profile-form">Save Changes</button>
					</div>
				</div>
			</div>

			<section class="profile-card">
				<div class="stats-grid">
					<div class="stat-card">
						<div class="stat-info">
							<p class="stat-label">Roles Posted</p>
							<p class="stat-caption">All roles under your account</p>
						</div>
						<p class="stat-value"><?php echo number_format($jobStats['total_jobs']); ?></p>
					</div>
					<div class="stat-card">
						<div class="stat-info">
							<p class="stat-label">Open Roles</p>
							<p class="stat-caption">Currently accepting applicants</p>
						</div>
						<p class="stat-value"><?php echo number_format($jobStats['open_jobs']); ?></p>
					</div>
					<div class="stat-card">
						<div class="stat-info">
							<p class="stat-label">Total Vacancies</p>
							<p class="stat-caption">Headcount needed across jobs</p>
						</div>
						<p class="stat-value"><?php echo number_format($jobStats['total_vacancies']); ?></p>
					</div>
					<div class="stat-card">
						<div class="stat-info">
							<p class="stat-label">Total Matches</p>
							<p class="stat-caption">Applicants matched to your jobs</p>
						</div>
						<p class="stat-value"><?php echo number_format($matchesCount); ?></p>
					</div>
				</div>
			</section>

			<section class="detail-grid-three">
				<!-- Company Details -->
				<div class="profile-card detail-section">
					<h3>Company Details</h3>
					<ul class="detail-list">
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-industry"></i>
								<span>Industry</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($companyIndustry); ?></strong>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-map-marker-alt"></i>
								<span>Headquarters</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($companyHeadquarters); ?></strong>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-globe"></i>
								<span>Website</span>
							</div>
							<div class="detail-item-value">
								<?php if ($companyWebsite): ?>
									<a href="<?php echo wm_safe($companyWebsite); ?>" target="_blank" rel="noopener noreferrer">
										<?php echo wm_safe(parse_url($companyWebsite, PHP_URL_HOST) ?: $companyWebsite); ?>
										<i class="fas fa-external-link-alt" style="font-size: 0.75rem; margin-left: 0.3rem;"></i>
									</a>
								<?php else: ?>
									<span style="color: var(--profile-muted);">Not set</span>
								<?php endif; ?>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-money-bill-wave"></i>
								<span>Avg. Budget</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($avgBudgetDisplay); ?></strong>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-calendar-alt"></i>
								<span>Operating Since</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($hiringSinceYear); ?></strong>
							</div>
						</li>
					</ul>
				</div>

				<!-- Contact Information -->
				<div class="profile-card detail-section">
					<h3>Contact Information</h3>
					<ul class="detail-list">
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-user"></i>
								<span>Primary Contact</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($primaryContact); ?></strong>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-envelope"></i>
								<span>Email</span>
							</div>
							<div class="detail-item-value">
								<a href="mailto:<?php echo wm_safe($contactEmail); ?>"><?php echo wm_safe($contactEmail); ?></a>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-phone"></i>
								<span>Phone</span>
							</div>
							<div class="detail-item-value">
								<?php if ($contactNumber !== 'Not set'): ?>
									<a href="tel:+63<?php echo substr(wm_safe($contactNumber), 1); ?>"><?php echo wm_safe($contactNumber); ?></a>
								<?php else: ?>
									<span style="color: var(--profile-muted);">Not set</span>
								<?php endif; ?>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-venus-mars"></i>
								<span>Gender</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($genderDisplay); ?></strong>
							</div>
						</li>
						<li class="detail-item">
							<div class="detail-item-label">
								<i class="fas fa-birthday-cake"></i>
								<span>Age</span>
							</div>
							<div class="detail-item-value">
								<strong><?php echo wm_safe($ageDisplay); ?></strong>
							</div>
						</li>
					</ul>
				</div>

				<!-- Social Links -->
				<div class="profile-card detail-section">
					<h3>Social Links</h3>
					<?php if (!empty($company['facebook_link']) || !empty($company['linkedin_link'])): ?>
						<div class="social-links-large">
							<?php if (!empty($company['facebook_link'])): ?>
								<a href="<?php echo wm_safe($company['facebook_link']); ?>" target="_blank" rel="noopener noreferrer" class="social-link-item">
									<div class="social-icon facebook">
										<i class="fab fa-facebook-f"></i>
									</div>
									<div class="social-link-content">
										<strong>Facebook</strong>
										<span>Visit our Facebook page</span>
									</div>
									<i class="fas fa-external-link-alt" style="color: var(--profile-muted); font-size: 0.9rem;"></i>
								</a>
							<?php endif; ?>
							<?php if (!empty($company['linkedin_link'])): ?>
								<a href="<?php echo wm_safe($company['linkedin_link']); ?>" target="_blank" rel="noopener noreferrer" class="social-link-item">
									<div class="social-icon linkedin">
										<i class="fab fa-linkedin-in"></i>
									</div>
									<div class="social-link-content">
										<strong>LinkedIn</strong>
										<span>Visit our LinkedIn profile</span>
									</div>
									<i class="fas fa-external-link-alt" style="color: var(--profile-muted); font-size: 0.9rem;"></i>
								</a>
							<?php endif; ?>
						</div>
					<?php else: ?>
						<div class="empty-state">
							<i class="fas fa-link"></i>
							<p>No social media links added yet.<br>Update your profile to add social links.</p>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<section class="profile-card detail-section">
				<h3>Team & Departments</h3>
				<div class="teams-grid">
					<?php if (!empty($teams)): ?>
						<?php foreach ($teams as $team): ?>
							<div class="team-card">
								<h4><?php echo wm_safe($team['category_name']); ?></h4>
								<p><?php echo number_format($team['job_count']); ?> active roles · <?php echo number_format($team['total_vacancies']); ?> vacancies</p>
							</div>
						<?php endforeach; ?>
					<?php else: ?>
						<p style="color:var(--profile-muted);">Post jobs to visualize how your departments are hiring.</p>
					<?php endif; ?>
				</div>
			</section>

			<section class="detail-grid">
				<div class="profile-card detail-section">
					<h3>Recent Notes</h3>
					<ul class="notes-list">
						<?php if (!empty($recentActivities)): ?>
							<?php foreach ($recentActivities as $activity): ?>
								<li class="note">
									<?php $noteTimestamp = $activity['activity_timestamp'] ?? $activity['created_at'] ?? null; ?>
									<time datetime="<?php echo wm_safe($noteTimestamp); ?>"><?php echo wm_safe(wm_format_date($noteTimestamp, 'M d, Y · g:i A')); ?></time>
									<p><?php echo wm_safe($activity['activity_status'] ?? 'Published'); ?> <?php echo wm_safe($activity['job_post_name']); ?>.</p>
									<?php if (!empty($activity['created_at'])): ?>
										<p class="note-meta">Posted <?php echo wm_safe(wm_format_date($activity['created_at'], 'M d, Y · g:i A')); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						<?php else: ?>
							<li class="note">
								<p>No recent updates yet. Post a role to start building your activity history.</p>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</section>
		</main>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			const heroButtons = document.querySelectorAll('[data-action]');
			const editSection = document.getElementById('edit-profile-form');
			const updateSuccess = document.body.dataset.updateSuccess === 'true';
			const updateMessage = document.body.dataset.updateMessage || 'Profile updated successfully.';
			const clearUpdatedFlag = function () {
				const url = new URL(window.location.href);
				if (url.searchParams.has('updated')) {
					url.searchParams.delete('updated');
					const newPath = url.pathname + (url.search ? '?' + url.search : '') + url.hash;
					history.replaceState({}, '', newPath);
				}
			};
			if (updateSuccess && window.Swal) {
				Swal.fire({
					icon: 'success',
					title: 'Changes saved successfully',
					text: updateMessage,
					confirmButtonText: 'Great!'
				}).then(clearUpdatedFlag);
				if (editSection && !editSection.classList.contains('is-collapsed')) {
					editSection.classList.add('is-collapsed');
					editSection.dataset.expanded = 'false';
				}
			} else if (updateSuccess) {
				clearUpdatedFlag();
			}
			const revealEditSection = function () {
				if (!editSection) {
					return;
				}
				if (editSection.classList.contains('is-collapsed')) {
					editSection.classList.remove('is-collapsed');
					editSection.dataset.expanded = 'true';
				}
				editSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
				editSection.classList.add('pulse');
				setTimeout(function () {
					editSection.classList.remove('pulse');
				}, 2000);
				const focusTarget = editSection.querySelector('input[name="company_name"]') || editSection.querySelector('input, textarea');
				if (focusTarget) {
					focusTarget.focus({ preventScroll: true });
				}
			};
			const actionHandlers = {
				'update-profile': revealEditSection,
				'preview-page': function () {
					alert('Public company page preview is coming soon.');
				},
			};
			heroButtons.forEach(function (button) {
				button.addEventListener('click', function (event) {
					const action = button.dataset.action;
					if (actionHandlers[action]) {
						actionHandlers[action](event);
					}
				});
			});
		});
	</script>
	<script>
document.addEventListener('DOMContentLoaded', function () {
    const heroButtons = document.querySelectorAll('[data-action]');
    const profileModal = document.getElementById('profile-modal');
    const updateSuccess = document.body.dataset.updateSuccess === 'true';
    const updateMessage = document.body.dataset.updateMessage || 'Profile updated successfully.';
    const clearUpdatedFlag = function () {
        const url = new URL(window.location.href);
        if (url.searchParams.has('updated')) {
            url.searchParams.delete('updated');
            const newPath = url.pathname + (url.search ? '?' + url.search : '') + url.hash;
            history.replaceState({}, '', newPath);
        }
    };
    if (updateSuccess && window.Swal) {
        Swal.fire({
            icon: 'success',
            title: 'Changes saved successfully',
            text: updateMessage,
            confirmButtonText: 'Great!'
        }).then(clearUpdatedFlag);
        if (profileModal && !profileModal.classList.contains('is-hidden')) {
            profileModal.classList.add('is-hidden');
        }
    } else if (updateSuccess) {
        clearUpdatedFlag();
    }
    const openModal = function () {
        if (!profileModal) {
            return;
        }
        profileModal.classList.remove('is-hidden');
        const focusTarget = profileModal.querySelector('input[name="first_name"]') || profileModal.querySelector('input, textarea');
        if (focusTarget) {
            setTimeout(function() {
                focusTarget.focus({ preventScroll: true });
            }, 100);
        }
    };
    const actionHandlers = {
        'update-profile': openModal
    };
    heroButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const action = button.dataset.action;
            if (actionHandlers[action]) {
                actionHandlers[action](event);
            }
        });
    });

    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-modal');
    if (closeBtn && profileModal) {
        closeBtn.addEventListener('click', function () {
            profileModal.classList.add('is-hidden');
        });
    }
    if (cancelBtn && profileModal) {
        cancelBtn.addEventListener('click', function () {
            profileModal.classList.add('is-hidden');
        });
    }
    
    // Close modal when clicking outside
    if (profileModal) {
        profileModal.addEventListener('click', function(e) {
            if (e.target === profileModal) {
                profileModal.classList.add('is-hidden');
            }
        });
    }
});
</script>
	<script>
  document.addEventListener('DOMContentLoaded', function () {
    var contactInput = document.getElementById('contact_number');
    if (!contactInput) return;

    // Block non-digit keys and stop input once 11 digits are present
    contactInput.addEventListener('keydown', function (e) {
      const allowedControl = [
        'Backspace','Delete','Tab','ArrowLeft','ArrowRight','Home','End'
      ];
      const isCtrlCmd = e.ctrlKey || e.metaKey;
      const isSelection = contactInput.selectionStart !== contactInput.selectionEnd;

      // Allow common controls and shortcuts (copy/paste/select all)
      if (allowedControl.includes(e.key) || isCtrlCmd) return;

      // Only digits
      if (!/^\d$/.test(e.key)) {
        e.preventDefault();
        return;
      }

      // Enforce 11-digit cap
      const val = contactInput.value;
      const digitsCount = (val.match(/\d/g) || []).length;

      // If already 11 digits and not replacing a selection, block
      if (digitsCount >= 11 && !isSelection) {
        e.preventDefault();
      }
    });

    // Clean any pasted content; keep only digits and trim to 11
    contactInput.addEventListener('input', function () {
      const onlyDigits = contactInput.value.replace(/\D+/g, '').slice(0, 11);
      if (contactInput.value !== onlyDigits) {
        contactInput.value = onlyDigits;
      }
    });
  });
</script>
<script>
  // Company Logo Upload Handler
  document.addEventListener('DOMContentLoaded', function () {
    const logoInput = document.getElementById('company_logo');
    const logoChooseBtn = document.getElementById('logo-choose-btn');
    const logoRemoveBtn = document.getElementById('logo-remove-btn');
    const logoPreview = document.getElementById('logo-preview');
    const logoWrapper = document.getElementById('logo-upload-wrapper');
    
    if (!logoInput || !logoChooseBtn) return;

    // Click to choose file
    logoChooseBtn.addEventListener('click', function () {
      logoInput.click();
    });

    // Handle file selection
    logoInput.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid File Type',
          text: 'Please select a JPG, PNG, GIF, or WebP image.'
        });
        this.value = '';
        return;
      }

      // Validate file size (2MB max)
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire({
          icon: 'error',
          title: 'File Too Large',
          text: 'Logo file size must not exceed 2MB.'
        });
        this.value = '';
        return;
      }

      // Preview the image
      const reader = new FileReader();
      reader.onload = function (e) {
        logoPreview.innerHTML = '<img src="' + e.target.result + '" alt="Logo preview" id="logo-preview-img" />';
        logoRemoveBtn.style.display = 'inline-flex';
      };
      reader.readAsDataURL(file);
    });

    // Remove logo
    if (logoRemoveBtn) {
      logoRemoveBtn.addEventListener('click', function () {
        logoInput.value = '';
        logoPreview.innerHTML = '<div class="logo-preview-placeholder" id="logo-placeholder"><i class="fas fa-building"></i></div>';
        logoRemoveBtn.style.display = 'none';
      });
    }

    // Drag and drop support
    if (logoWrapper) {
      ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
        logoWrapper.addEventListener(eventName, function (e) {
          e.preventDefault();
          e.stopPropagation();
        });
      });

      ['dragenter', 'dragover'].forEach(function (eventName) {
        logoWrapper.addEventListener(eventName, function () {
          logoWrapper.classList.add('dragover');
        });
      });

      ['dragleave', 'drop'].forEach(function (eventName) {
        logoWrapper.addEventListener(eventName, function () {
          logoWrapper.classList.remove('dragover');
        });
      });

      logoWrapper.addEventListener('drop', function (e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
          logoInput.files = files;
          logoInput.dispatchEvent(new Event('change'));
        }
      });
    }
  });
</script>
</body>
</html>