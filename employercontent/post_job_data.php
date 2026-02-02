<?php
session_start();
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch categories
$categories_query = "SELECT job_category_id, job_category_name FROM job_category ORDER BY job_category_name ASC";
$categories_result = $conn->query($categories_query);
$categories = [];
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch companies - either all companies or companies associated with this user
$companies_query = "SELECT company_id, company_name FROM company WHERE user_id = ? OR user_id IS NULL ORDER BY company_name ASC";
$stmt = $conn->prepare($companies_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$companies_result = $stmt->get_result();
$companies = [];
if ($companies_result) {
    while ($row = $companies_result->fetch_assoc()) {
        $companies[] = $row;
    }
}
$stmt->close();

// Fetch job types from database
$job_types = [];
$jobTypesSql = "SELECT job_type_id, job_type_name FROM job_type ORDER BY job_type_name";
if ($stmt = $conn->prepare($jobTypesSql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $job_types[] = $row;
        }
    }
    $stmt->close();
}

// Fetch industries from database
$industries = [];
$industrySql = "SELECT industry_id, industry_name FROM industry ORDER BY industry_name";
if ($stmt = $conn->prepare($industrySql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $industries[] = $row;
        }
    }
    $stmt->close();
}

// Fetch job categories from database (include industry for dependency mapping)
$job_categories = [];
$jobCategoriesSql = "SELECT job_category_id, job_category_name, industry_id FROM job_category ORDER BY job_category_name";
if ($stmt = $conn->prepare($jobCategoriesSql)) {
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$job_categories[] = $row;
		}
	}
	$stmt->close();
}

// Fetch skills from database grouped by job_category_id
$skills = [];
$skillsSql = "SELECT skill_id, job_category_id, name FROM skills ORDER BY name";
if ($stmt = $conn->prepare($skillsSql)) {
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$skills[] = $row;
		}
	}
	$stmt->close();
}

// Fetch education levels from database
$education_levels = [];
$educationLevelsSql = "SELECT education_level_id, education_level_name FROM education_level ORDER BY education_level_id";
if ($stmt = $conn->prepare($educationLevelsSql)) {
	if ($stmt->execute()) {
		$result = $stmt->get_result();
		while ($row = $result->fetch_assoc()) {
			$education_levels[] = $row;
		}
	}
	$stmt->close();
}

// Fetch experience levels
$experience_levels = [];
$experienceSql = "SELECT experience_level_id, experience_level_name FROM experience_level ORDER BY experience_level_id";
if ($stmt = $conn->prepare($experienceSql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $experience_levels[] = $row;
        }
    }
    $stmt->close();
}

// Fetch work setups
$work_setups = [];
$workSetupSql = "SELECT work_setup_id, work_setup_name FROM work_setup ORDER BY work_setup_id";
if ($stmt = $conn->prepare($workSetupSql)) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $work_setups[] = $row;
        }
    }
    $stmt->close();
}

// Initialize draft data from session (for form persistence on reload)
if (!isset($_SESSION['job_post_draft'])) {
	$_SESSION['job_post_draft'] = [];
}
$draft = $_SESSION['job_post_draft'] ?? [];

$legacyMap = [
    'work_setup' => 'work_setup_id',
    'education_level' => 'education_level_id',
    'experience_level' => 'experience_level_id'
];

foreach ($legacyMap as $oldKey => $newKey) {
    if (!empty($draft[$oldKey]) && empty($draft[$newKey])) {
        $draft[$newKey] = $draft[$oldKey];
    }
}

$conn->close();
?>
