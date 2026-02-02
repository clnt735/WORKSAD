<?php
session_start();
header('Content-Type: application/json');
require_once '../database.php';

function respond_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $GLOBALS['conn']->close();
    }
    echo json_encode($payload);
    exit;
}

function abort_request(string $message, int $statusCode = 400, bool $rollback = false): void
{
    global $conn;
    if ($rollback && $conn instanceof mysqli) {
        $conn->rollback();
    }
    respond_json(['success' => false, 'message' => $message], $statusCode);
}

function verify_record_exists(mysqli $conn, string $sql, string $types, array $params, string $message): void
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        abort_request('Unable to validate reference data.', 500);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || !$result->num_rows) {
        $stmt->close();
        abort_request($message, 422);
    }
    $stmt->close();
}

function resolve_work_setup_id($value): int
{
    if ($value === null || $value === '') {
        abort_request('Work setup is required.', 422);
    }
    if (ctype_digit((string)$value)) {
        $id = (int)$value;
        if ($id > 0) {
            return $id;
        }
    }
    $map = [
        'on_site' => 1,
        'onsite' => 1,
        'hybrid' => 2,
        'remote' => 3,
    ];
    $key = strtolower((string)$value);
    if (isset($map[$key])) {
        return $map[$key];
    }
    abort_request('Invalid work setup option.', 422);
}

function resolve_experience_level_id($value): int
{
    if ($value === null || $value === '') {
        abort_request('Experience level is required.', 422);
    }
    if (ctype_digit((string)$value)) {
        $id = (int)$value;
        if ($id > 0) {
            return $id;
        }
    }
    $map = [
        'intern' => 1,
        'entry' => 1,
        'junior' => 2,
        'mid' => 3,
        'senior' => 4,
        'lead' => 5,
        'expert' => 5,
    ];
    $key = strtolower((string)$value);
    if (isset($map[$key])) {
        return $map[$key];
    }
    abort_request('Invalid experience level option.', 422);
}

function resolve_education_level_id($value): int
{
    if ($value === null || $value === '') {
        abort_request('Education level is required.', 422);
    }
    if (ctype_digit((string)$value)) {
        $id = (int)$value;
        if ($id > 0) {
            return $id;
        }
    }
    $map = [
        'high_school' => 5,
        'high_school_level' => 4,
        'vocational' => 8,
        'college_undergrad' => 10,
        'college_graduate' => 11,
        'bachelors' => 12,
        'bachelor' => 12,
        'masters' => 13,
        'doctorate' => 14,
    ];
    $key = strtolower((string)$value);
    if (isset($map[$key])) {
        return $map[$key];
    }
    abort_request('Invalid education level option.', 422);
    return 0; // <- satisfies static analyzers; never reached
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    respond_json(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$userId = (int)$_SESSION['user_id'];
$jobId = (int)($_POST['job_post_id'] ?? 0);

if ($jobId <= 0) {
    abort_request('Invalid job reference.', 422);
}

$jobTitle = trim($_POST['job_post_name'] ?? '');
$typeId = (int)($_POST['job_type_id'] ?? 0);
$vacancies = max(1, (int)($_POST['vacancies'] ?? 1));
$workSetupId = resolve_work_setup_id($_POST['work_setup_id'] ?? $_POST['work_setup'] ?? null);
$jobDescription = trim($_POST['job_description'] ?? '');
$requirements = trim($_POST['requirements'] ?? '');
$benefits = trim($_POST['benefits'] ?? '');
$budgetInput = trim($_POST['budget'] ?? '');
$budget = $budgetInput === '' ? null : (float)$budgetInput;
$experienceLevelId = resolve_experience_level_id($_POST['experience_level_id'] ?? $_POST['experience_level'] ?? null);
$educationLevelId = resolve_education_level_id($_POST['education_level_id'] ?? $_POST['education_level'] ?? null);
$cityMunId = (int)($_POST['city_mun_id'] ?? 0);
$barangayIdRaw = $_POST['barangay_id'] ?? null;
$locationStreet = trim($_POST['location_street'] ?? '');

if ($jobTitle === '') {
    abort_request('Job title is required.', 422);
}

if ($typeId <= 0) {
    abort_request('Please pick a job type.', 422);
}

if ($cityMunId <= 0) {
    abort_request('City / municipality selection is required.', 422);
}

if ($budget !== null && $budget < 0) {
    abort_request('Budget must be zero or greater.', 422);
}

$barangayId = null;
if ($barangayIdRaw !== null && $barangayIdRaw !== '') {
    $barangayId = (int)$barangayIdRaw;
    if ($barangayId <= 0) {
        $barangayId = null;
    }
}

verify_record_exists($conn, "SELECT 1 FROM job_type WHERE job_type_id = ? LIMIT 1", 'i', [$typeId], 'Selected job type does not exist.');
verify_record_exists($conn, "SELECT 1 FROM work_setup WHERE work_setup_id = ? LIMIT 1", 'i', [$workSetupId], 'Selected work setup does not exist.');
verify_record_exists($conn, "SELECT 1 FROM experience_level WHERE experience_level_id = ? LIMIT 1", 'i', [$experienceLevelId], 'Selected experience level does not exist.');
verify_record_exists($conn, "SELECT 1 FROM education_level WHERE education_level_id = ? LIMIT 1", 'i', [$educationLevelId], 'Selected education level does not exist.');

$cityStmt = $conn->prepare("SELECT city_mun_name FROM city_mun WHERE city_mun_id = ? LIMIT 1");
if (!$cityStmt) {
    abort_request('Unable to validate city / municipality.', 500);
}
$cityStmt->bind_param('i', $cityMunId);
$cityStmt->execute();
$cityResult = $cityStmt->get_result();
$cityRow = $cityResult ? $cityResult->fetch_assoc() : null;
$cityStmt->close();
if (!$cityRow) {
    abort_request('Selected city / municipality does not exist.', 422);
}
$cityName = trim((string)($cityRow['city_mun_name'] ?? ''));

$barangayName = null;
if ($barangayId !== null) {
    $barangayStmt = $conn->prepare("SELECT barangay_name FROM barangay WHERE barangay_id = ? AND city_mun_id = ? LIMIT 1");
    if (!$barangayStmt) {
        abort_request('Unable to validate barangay.', 500);
    }
    $barangayStmt->bind_param('ii', $barangayId, $cityMunId);
    $barangayStmt->execute();
    $barangayResult = $barangayStmt->get_result();
    $barangayRow = $barangayResult ? $barangayResult->fetch_assoc() : null;
    $barangayStmt->close();
    if (!$barangayRow) {
        abort_request('Selected barangay does not belong to the chosen city / municipality.', 422);
    }
    $barangayName = trim((string)($barangayRow['barangay_name'] ?? '')) ?: null;
}

$locationStreetValue = $locationStreet !== '' ? $locationStreet : null;
$addressParts = array_filter([
    $cityName,
    $barangayName,
    $locationStreetValue,
], function ($segment) {
    return $segment !== null && $segment !== '';
});
$addressLineValue = $addressParts ? implode(', ', $addressParts) : ($cityName !== '' ? $cityName : null);

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $jobStmt = $conn->prepare("
        SELECT jp.job_location_id
        FROM job_post jp
        LEFT JOIN company c ON jp.company_id = c.company_id
        WHERE jp.job_post_id = ?
          AND (jp.user_id = ? OR c.user_id = ?)
        FOR UPDATE
    ");
    $jobStmt->bind_param('iii', $jobId, $userId, $userId);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result();
    $jobRecord = $jobResult ? $jobResult->fetch_assoc() : null;
    $jobStmt->close();

    if (!$jobRecord) {
        abort_request('Job not found or not owned by your account.', 404, true);
    }

    $updateStmt = $conn->prepare("
        UPDATE job_post SET
            job_post_name = ?,
            job_type_id = ?,
            vacancies = ?,
            work_setup_id = ?,
            job_description = ?,
            requirements = ?,
            benefits = ?,
            budget = ?,
            experience_level_id = ?,
            education_level_id = ?,
            updated_at = NOW()
        WHERE job_post_id = ?
    ");
    $budgetValue = $budget;
    $requirementsValue = $requirements === '' ? null : $requirements;
    $updateStmt->bind_param(
        'siiisssdiii',
        $jobTitle,
        $typeId,
        $vacancies,
        $workSetupId,
        $jobDescription,
        $requirementsValue,
        $benefits,
        $budgetValue,
        $experienceLevelId,
        $educationLevelId,
        $jobId
    );
    $updateStmt->execute();
    $updateStmt->close();

    $currentLocationId = (int)($jobRecord['job_location_id'] ?? 0);
    $barangayParam = $barangayId;
    $locationStreetParam = $locationStreetValue;
    $addressLineParam = $addressLineValue;

    if ($currentLocationId > 0) {
        $locStmt = $conn->prepare("
            UPDATE job_post_location
            SET city_mun_id = ?, barangay_id = ?, location_street = ?, address_line = ?
            WHERE job_location_id = ?
        ");
        $locStmt->bind_param('iissi', $cityMunId, $barangayParam, $locationStreetParam, $addressLineParam, $currentLocationId);
        $locStmt->execute();
        $locStmt->close();
    } else {
        $locInsert = $conn->prepare("
            INSERT INTO job_post_location (job_post_id, city_mun_id, barangay_id, location_street, address_line)
            VALUES (?, ?, ?, ?, ?)
        ");
        $locInsert->bind_param('iiiss', $jobId, $cityMunId, $barangayParam, $locationStreetParam, $addressLineParam);
        $locInsert->execute();
        $newLocationId = $locInsert->insert_id;
        $locInsert->close();

        $jobLocationUpdate = $conn->prepare("UPDATE job_post SET job_location_id = ? WHERE job_post_id = ?");
        $jobLocationUpdate->bind_param('ii', $newLocationId, $jobId);
        $jobLocationUpdate->execute();
        $jobLocationUpdate->close();
    }

    $conn->commit();
    $transactionStarted = false;
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    respond_json([
        'success' => false,
        'message' => 'Unable to update the job post: ' . $e->getMessage(),
    ], 500);
}

$selectSql = "
    SELECT
        jp.job_post_id,
        jp.job_post_name,
        jp.job_category_id,
        jc.job_category_name,
        jp.job_type_id,
        jt.job_type_name,
        jp.job_description,
        jp.requirements,
        jp.benefits,
        jp.vacancies,
        jp.budget,
        jp.experience_level_id,
        ex.experience_level_name,
        jp.education_level_id,
        ed.education_level_name,
        jp.work_setup_id,
        ws.work_setup_name,
        jp.job_status_id,
        js.job_status_name,
        jp.job_location_id,
        jpl.location_street,
        jpl.address_line,
        jpl.city_mun_id,
        cm.city_mun_name,
        jpl.barangay_id,
        br.barangay_name,
        jp.updated_at
    FROM job_post jp
    LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
    LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
    LEFT JOIN experience_level ex ON jp.experience_level_id = ex.experience_level_id
    LEFT JOIN education_level ed ON jp.education_level_id = ed.education_level_id
    LEFT JOIN work_setup ws ON jp.work_setup_id = ws.work_setup_id
    LEFT JOIN job_status js ON jp.job_status_id = js.job_status_id
    LEFT JOIN job_post_location jpl ON jp.job_location_id = jpl.job_location_id
    LEFT JOIN city_mun cm ON jpl.city_mun_id = cm.city_mun_id
    LEFT JOIN barangay br ON jpl.barangay_id = br.barangay_id
    LEFT JOIN company c ON jp.company_id = c.company_id
    WHERE jp.job_post_id = ?
      AND (jp.user_id = ? OR c.user_id = ?)
    LIMIT 1
";
$jobStmt = $conn->prepare($selectSql);
$jobStmt->bind_param('iii', $jobId, $userId, $userId);
$jobStmt->execute();
$result = $jobStmt->get_result();
$jobData = $result ? $result->fetch_assoc() : null;
$jobStmt->close();

if (!$jobData) {
    respond_json(['success' => false, 'message' => 'Unable to load updated job data.'], 404);
}

respond_json([
    'success' => true,
    'message' => 'Job updated successfully.',
    'job' => $jobData,
]);
?>