<?php
session_start();
require_once __DIR__ . '/../database.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? null;

/* -------------------------------------------------------
   Helper: Generate 6-digit OTP
--------------------------------------------------------*/
function generateOTP() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/* -------------------------------------------------------
   Helper: Send email (basic PHP mail)
--------------------------------------------------------*/
function sendEmail($to, $subject, $message) {
    // For development — always succeed
    return true;

    // In production, replace with PHPMailer.
}

/* =======================================================
   ACTION 1: SEND OTP TO NEW EMAIL
=======================================================*/
if ($action === 'send_email_otp') {
    $new = trim($_POST['new_email'] ?? '');

    if (!filter_var($new, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid email"]);
        exit;
    }

    // Check if already taken
    global $conn;
    $chk = $conn->prepare("SELECT user_id FROM user WHERE user_email = ? LIMIT 1");
    $chk->bind_param("s", $new);
    $chk->execute();
    $exists = $chk->get_result()->fetch_assoc();
    if ($exists) {
        echo json_encode(["success" => false, "message" => "Email already in use"]);
        exit;
    }

    $otp = generateOTP();
    $expires = date("Y-m-d H:i:s", time() + 300); // 5 minutes

    $stmt = $conn->prepare("UPDATE user SET twofa_code=?, twofa_expires=? WHERE user_id=?");
    $stmt->bind_param("ssi", $otp, $expires, $user_id);
    $stmt->execute();

    // remember the new email while verification is pending
    $_SESSION['pending_new_email'] = $new;

    sendEmail($new, "Email Verification Code", "Your verification code is: $otp");

    echo json_encode([
        "success" => true,
        "message" => "Verification code sent to $new",
        "debug_code" => $otp // REMOVE in production
    ]);
    exit;
}

/* =======================================================
   ACTION 2: VERIFY EMAIL OTP & UPDATE EMAIL
=======================================================*/
if ($action === 'verify_email_otp') {
    $code = trim($_POST['code'] ?? '');

    $stmt = $conn->prepare("SELECT twofa_code, twofa_expires FROM user WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();

    if (!$u || !$u['twofa_code']) {
        echo json_encode(["success" => false, "message" => "No verification pending"]);
        exit;
    }

    if ($u['twofa_code'] !== $code) {
        echo json_encode(["success" => false, "message" => "Incorrect verification code"]);
        exit;
    }

    if (strtotime($u['twofa_expires']) < time()) {
        echo json_encode(["success" => false, "message" => "Verification code expired"]);
        exit;
    }

    // Now update email
    $newEmail = trim($_SESSION['pending_new_email']);
    unset($_SESSION['pending_new_email']);

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["success" => false, "message" => "Invalid stored email"]);
        exit;
    }

    $stmt2 = $conn->prepare("UPDATE user SET user_email=?, twofa_code=NULL, twofa_expires=NULL WHERE user_id=?");
    $stmt2->bind_param("si", $newEmail, $user_id);
    $stmt2->execute();

    echo json_encode(["success" => true, "message" => "Email updated successfully"]);
    exit;
}

/* =======================================================
   ACTION 3: CHANGE PASSWORD (NO OTP)
=======================================================*/
if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        echo json_encode(["success" => false, "message" => "New passwords do not match"]);
        exit;
    }

    // Fetch user password
    $stmt = $conn->prepare("SELECT user_password FROM user WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $row['user_password'])) {
        echo json_encode(["success" => false, "message" => "Current password incorrect"]);
        exit;
    }

    $hashed = password_hash($new, PASSWORD_DEFAULT);

    $stmt2 = $conn->prepare("UPDATE user SET user_password=? WHERE user_id=?");
    $stmt2->bind_param("si", $hashed, $user_id);
    $stmt2->execute();

    echo json_encode(["success" => true, "message" => "Password successfully updated"]);
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid action"]);
