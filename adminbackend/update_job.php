<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/log_admin_action.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_with_message(string $status, string $message): void
{
    $query = http_build_query([
        'flash_status'  => $status,
        'flash_message' => $message,
    ]);
    header('Location: ../admin/jobs.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Invalid request method.');
}

$jobId          = isset($_POST['jobId']) ? (int) $_POST['jobId'] : 0;
$jobTitle       = trim($_POST['jobTitle'] ?? '');
$jobDescription = trim($_POST['jobDescription'] ?? '');
$jobType        = isset($_POST['jobType']) ? (int) $_POST['jobType'] : 0;
$jobCategory    = isset($_POST['jobCategory']) ? (int) $_POST['jobCategory'] : 0;
$jobStatus      = isset($_POST['jobStatus']) ? (int) $_POST['jobStatus'] : 0;
$vacanciesInput = trim($_POST['vacancies'] ?? '');
$budgetInput    = trim($_POST['budget'] ?? '');
$cityMunId      = isset($_POST['cityMun']) ? (int) $_POST['cityMun'] : 0;
$barangayRaw    = $_POST['barangay'] ?? '';
$barangayId     = $barangayRaw !== '' ? (int) $barangayRaw : null;
$addressLine    = trim($_POST['addressLine'] ?? '');
$jobLocationId  = isset($_POST['jobLocationId']) ? (int) $_POST['jobLocationId'] : 0;

if ($jobId <= 0) {
    redirect_with_message('error', 'Missing job reference.');
}

if ($jobTitle === '' || $jobDescription === '' || $jobType <= 0 || $addressLine === '' || $cityMunId <= 0 || $jobCategory <= 0 || $jobStatus <= 0) {
    redirect_with_message('error', 'Please complete all required fields.');
}

if ($vacanciesInput === '' || $budgetInput === '') {
    redirect_with_message('error', 'Vacancies and budget are required.');
}

$vacancies = filter_var($vacanciesInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
if ($vacancies === false) {
    redirect_with_message('error', 'Enter a valid number of vacancies.');
}

$budget = filter_var(str_replace([',', ' '], '', $budgetInput), FILTER_VALIDATE_FLOAT);
if ($budget === false || $budget < 0) {
    redirect_with_message('error', 'Enter a valid budget amount.');
}

$cityStmt = $conn->prepare('SELECT city_mun_name FROM city_mun WHERE city_mun_id = ? LIMIT 1');
if (!$cityStmt) {
    redirect_with_message('error', 'Unable to validate city.');
}
$cityStmt->bind_param('i', $cityMunId);
$cityStmt->execute();
$cityStmt->bind_result($cityName);
if (!$cityStmt->fetch()) {
    $cityStmt->close();
    redirect_with_message('error', 'Selected city / municipality was not found.');
}
$cityStmt->close();

$barangayName = '';
if ($barangayId !== null) {
    if ($barangayId <= 0) {
        redirect_with_message('error', 'Invalid barangay selection.');
    }
    $barangayStmt = $conn->prepare('SELECT barangay_name FROM barangay WHERE barangay_id = ? AND city_mun_id = ? LIMIT 1');
    if (!$barangayStmt) {
        redirect_with_message('error', 'Unable to validate barangay.');
    }
    $barangayStmt->bind_param('ii', $barangayId, $cityMunId);
    $barangayStmt->execute();
    $barangayStmt->bind_result($barangayName);
    if (!$barangayStmt->fetch()) {
        $barangayStmt->close();
        redirect_with_message('error', 'Selected barangay does not belong to the chosen city.');
    }
    $barangayStmt->close();
}

$locationParts = array_filter([
    $addressLine,
    $barangayName,
    $cityName
], static function ($part) {
    return $part !== null && $part !== '';
});
$locationDisplay = implode(', ', $locationParts);

$conn->begin_transaction();

$stmt = $conn->prepare('UPDATE job_post SET job_post_name = ?, job_description = ?, job_type_id = ?, job_category_id = ?, job_status_id = ?, vacancies = ?, budget = ? WHERE job_post_id = ? LIMIT 1');
if (!$stmt) {
    $conn->rollback();
    redirect_with_message('error', 'Failed to prepare job update.');
}

$stmt->bind_param('ssiiiidi', $jobTitle, $jobDescription, $jobType, $jobCategory, $jobStatus, $vacancies, $budget, $jobId);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->rollback();
    redirect_with_message('error', 'Failed to update job.');
}

if ($stmt->affected_rows === 0) {
    $existsStmt = $conn->prepare('SELECT 1 FROM job_post WHERE job_post_id = ? LIMIT 1');
    if (!$existsStmt) {
        $stmt->close();
        $conn->rollback();
        redirect_with_message('error', 'Unable to verify job record.');
    }
    $existsStmt->bind_param('i', $jobId);
    $existsStmt->execute();
    $existsStmt->store_result();
    if ($existsStmt->num_rows === 0) {
        $existsStmt->close();
        $stmt->close();
        $conn->rollback();
        redirect_with_message('error', 'Job not found.');
    }
    $existsStmt->close();
}

$stmt->close();

$resolvedLocationId = $jobLocationId;
if ($resolvedLocationId <= 0) {
    $lookupStmt = $conn->prepare('SELECT job_location_id FROM job_post_location WHERE job_post_id = ? LIMIT 1');
    if (!$lookupStmt) {
        $conn->rollback();
        redirect_with_message('error', 'Unable to read existing job location.');
    }
    $lookupStmt->bind_param('i', $jobId);
    $lookupStmt->execute();
    $lookupStmt->bind_result($existingLocationId);
    if ($lookupStmt->fetch()) {
        $resolvedLocationId = (int) $existingLocationId;
    }
    $lookupStmt->close();
}

$barangayIdParam = $barangayId;
if ($resolvedLocationId > 0) {
    $locationStmt = $conn->prepare('UPDATE job_post_location SET city_mun_id = ?, barangay_id = ?, address_line = ? WHERE job_location_id = ? LIMIT 1');
    if (!$locationStmt) {
        $conn->rollback();
        redirect_with_message('error', 'Unable to prepare location update.');
    }
    $locationStmt->bind_param('iisi', $cityMunId, $barangayIdParam, $addressLine, $resolvedLocationId);
    if (!$locationStmt->execute()) {
        $locationStmt->close();
        $conn->rollback();
        redirect_with_message('error', 'Failed to update job location.');
    }
    $locationStmt->close();
} else {
    $insertLocationStmt = $conn->prepare('INSERT INTO job_post_location (job_post_id, city_mun_id, barangay_id, address_line, created_at) VALUES (?, ?, ?, ?, NOW())');
    if (!$insertLocationStmt) {
        $conn->rollback();
        redirect_with_message('error', 'Unable to prepare location insert.');
    }
    $insertLocationStmt->bind_param('iiis', $jobId, $cityMunId, $barangayIdParam, $addressLine);
    if (!$insertLocationStmt->execute()) {
        $insertLocationStmt->close();
        $conn->rollback();
        redirect_with_message('error', 'Failed to save job location.');
    }
    $insertLocationStmt->close();
}

$conn->commit();

$adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
log_admin_action($conn, $adminId, 'Updated job #' . $jobId . ' (' . $jobTitle . ')');

redirect_with_message('success', 'Job updated successfully.');
