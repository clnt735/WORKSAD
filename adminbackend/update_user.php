<?php
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/log_admin_action.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_with_message($status, $message)
{
    $params = $_GET;
    unset($params['flash_status'], $params['flash_message']);
    $query = http_build_query(array_merge($params, [
        'flash_status'  => $status,
        'flash_message' => $message,
    ]));
    header('Location: ../admin/users.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Invalid request method.');
}

$userId     = isset($_POST['userId']) ? (int) $_POST['userId'] : 0;
$email      = isset($_POST['email']) ? trim($_POST['email']) : '';
$password   = $_POST['password'] ?? '';
$userType   = isset($_POST['userType']) ? (int) $_POST['userType'] : 0;
$userStatus = isset($_POST['userStatus']) ? (int) $_POST['userStatus'] : 0;
$firstName  = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
$middleName = isset($_POST['middleName']) ? trim($_POST['middleName']) : '';
$lastName   = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
$dob        = isset($_POST['dob']) ? trim($_POST['dob']) : '';
$gender     = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$contactNo  = isset($_POST['contactNo']) ? trim($_POST['contactNo']) : '';

if ($userId <= 0) {
    redirect_with_message('error', 'Missing required fields for update.');
}

$currentStmt = $conn->prepare(
    'SELECT u.user_email, u.user_type_id, u.user_status_id, up.user_profile_first_name, up.user_profile_middle_name, up.user_profile_last_name
     FROM user u
     LEFT JOIN user_profile up ON u.user_id = up.user_id
     WHERE u.user_id = ?
     LIMIT 1'
);
$currentStmt->bind_param('i', $userId);
$currentStmt->execute();
$currentResult = $currentStmt->get_result();
$currentData = $currentResult ? $currentResult->fetch_assoc() : null;
$currentStmt->close();

if (!$currentData) {
    redirect_with_message('error', 'User record not found.');
}

$email = $email !== '' ? $email : trim($currentData['user_email'] ?? '');
$userType = $userType > 0 ? $userType : (int) ($currentData['user_type_id'] ?? 0);
$userStatus = $userStatus > 0 ? $userStatus : (int) ($currentData['user_status_id'] ?? 0);
$firstName = $firstName !== '' ? $firstName : trim($currentData['user_profile_first_name'] ?? '');
$middleName = $middleName !== '' ? $middleName : trim($currentData['user_profile_middle_name'] ?? '');
$lastName = $lastName !== '' ? $lastName : trim($currentData['user_profile_last_name'] ?? '');

if ($email === '' || $userType <= 0 || $userStatus <= 0) {
    redirect_with_message('error', 'Missing required fields for update.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_message('error', 'Enter a valid email address.');
}

try {
    $conn->begin_transaction();

    // Check unique email (exclude current user)
    $checkStmt = $conn->prepare('SELECT user_id FROM user WHERE user_email = ? AND user_id <> ? LIMIT 1');
    $checkStmt->bind_param('si', $email, $userId);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        $conn->rollback();
        redirect_with_message('error', 'Email address already exists.');
    }
    $checkStmt->close();

    if ($password !== '') {
        if (strlen($password) < 8) {
            $conn->rollback();
            redirect_with_message('error', 'Password must be at least 8 characters.');
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $userStmt = $conn->prepare('UPDATE user SET user_type_id = ?, user_status_id = ?, user_email = ?, user_password = ? WHERE user_id = ?');
        $userStmt->bind_param('iissi', $userType, $userStatus, $email, $passwordHash, $userId);
    } else {
        $userStmt = $conn->prepare('UPDATE user SET user_type_id = ?, user_status_id = ?, user_email = ? WHERE user_id = ?');
        $userStmt->bind_param('iisi', $userType, $userStatus, $email, $userId);
    }
    $userStmt->execute();
    $userStmt->close();

    $profileUpdatedAt = date('Y-m-d');
    $genderNormalized = $gender !== '' ? strtolower($gender) : '';

    $profileExistsStmt = $conn->prepare('SELECT 1 FROM user_profile WHERE user_id = ? LIMIT 1');
    $profileExistsStmt->bind_param('i', $userId);
    $profileExistsStmt->execute();
    $profileExistsStmt->store_result();
    $profileExists = $profileExistsStmt->num_rows > 0;
    $profileExistsStmt->close();

    if ($profileExists) {
        $profileSql = "
            UPDATE user_profile SET
                user_profile_first_name = NULLIF(?, ''),
                user_profile_middle_name = NULLIF(?, ''),
                user_profile_last_name = NULLIF(?, ''),
                user_profile_dob = NULLIF(?, ''),
                user_profile_contact_no = NULLIF(?, ''),
                user_profile_updated_at = ?,
                user_profile_gender = NULLIF(?, '')
            WHERE user_id = ?
        ";
        $profileStmt = $conn->prepare($profileSql);
        $profileStmt->bind_param(
            'sssssssi',
            $firstName,
            $middleName,
            $lastName,
            $dob,
            $contactNo,
            $profileUpdatedAt,
            $genderNormalized,
            $userId
        );
        $profileStmt->execute();
        $profileStmt->close();
    } else {
        $profileInsertSql = "
            INSERT INTO user_profile (
                user_id,
                user_profile_first_name,
                user_profile_middle_name,
                user_profile_last_name,
                user_profile_dob,
                user_profile_contact_no,
                user_profile_updated_at,
                user_profile_gender
            ) VALUES (
                ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, NULLIF(?, '')
            )
        ";
        $profileInsertStmt = $conn->prepare($profileInsertSql);
        $profileInsertStmt->bind_param(
            'isssssss',
            $userId,
            $firstName,
            $middleName,
            $lastName,
            $dob,
            $contactNo,
            $profileUpdatedAt,
            $genderNormalized
        );
        $profileInsertStmt->execute();
        $profileInsertStmt->close();
    }

    $conn->commit();
    $adminId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    log_admin_action($conn, $adminId, 'Updated user #' . $userId . ' (' . $email . ')');
    redirect_with_message('success', 'User updated successfully.');
} catch (Throwable $e) {
    $conn->rollback();
    redirect_with_message('error', 'Failed to update user.');
}
