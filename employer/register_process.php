<?php 
session_start(); // REQUIRED for OTP redirect

// ---------------------------------------------------------------
// ENVIRONMENT / DB CONNECTION
// ---------------------------------------------------------------
if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    require '../database.php'; // Local DB
    $env = "LOCAL";
}

if (!isset($conn)) {
    die("❌ ERROR: conn NOT SET — check database.php");
}

if ($conn->connect_error) {
    die("❌ DB connection failed: " . $conn->connect_error);
}

// ---------------------------------------------------------------
// LOAD COMPOSER & PHPMailer
// ---------------------------------------------------------------
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------------------------------------------------------------
// PROCESS REGISTRATION
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize input
    $fname  = trim($_POST['fname']);
    $mname  = trim($_POST['mname']);
    $lname  = trim($_POST['lname']);
    $email  = trim($_POST['email']);
    $gender = $_POST['gender'];

    $year  = (int) $_POST['year'];
    $month = (int) $_POST['month'];
    $day   = (int) $_POST['day'];

    if (!checkdate($month, $day, $year)) {
    $_SESSION['error'] = "❌ Invalid date of birth. Please enter a valid date.";
    header("Location: register.php");
    exit();
    }

    $dob = sprintf("%04d-%02d-%02d", $year, $month, $day);

    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
    $_SESSION['error'] = "❌ Passwords do not match!";
    header("Location: register.php");
    exit(); 
    }


    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email exists
    $check_stmt = $conn->prepare("SELECT user_id FROM user WHERE user_username = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
    $_SESSION['error'] = "❌ This email is already registered.";
    header("Location: register.php");
    exit();
    }

    $check_stmt->close();

    // ---------------------------------------------------------
    // OTP GENERATION
    // ---------------------------------------------------------
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $hashed_code = password_hash($verification_code, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $user_type_id = 2;
    $user_status_id = 0; // Unverified

    // ---------------------------------------------------------
    // INSERT USER
    // ---------------------------------------------------------
    $stmt = $conn->prepare("
        INSERT INTO user (
            user_type_id,
            user_status_id,
            user_username,
            user_password,
            activation_token,
            token_expires_at,
            user_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("iissss", 
        $user_type_id,
        $user_status_id,
        $email,
        $hashed_password,
        $hashed_code,
        $expires_at
    );

    if (!$stmt->execute()) {
        die("❌ SQL ERROR (user insert): " . $stmt->error);
    }

    $user_id = $stmt->insert_id;
    $stmt->close();

    // ---------------------------------------------------------
    // INSERT USER PROFILE
    // ---------------------------------------------------------
    $stmt2 = $conn->prepare("
        INSERT INTO user_profile (
            user_id, 
            user_status_id, 
            user_profile_first_name, 
            user_profile_middle_name, 
            user_profile_last_name,
            user_profile_dob, 
            user_profile_email_address, 
            user_profile_gender, 
            user_profile_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt2->bind_param("iissssss", 
        $user_id, 
        $user_status_id,
        $fname, 
        $mname, 
        $lname, 
        $dob, 
        $email, 
        $gender
    );

    if (!$stmt2->execute()) {
        die("❌ SQL ERROR (profile insert): " . $stmt2->error);
    }

    $stmt2->close();

    // ---------------------------------------------------------
    // SEND OTP EMAIL
    // ---------------------------------------------------------
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'workmuna310@gmail.com';
        $mail->Password   = 'ialjpczhkbfbigfq';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        $mail->setFrom('workmuna310@gmail.com', 'WorkMuna Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your WorkMuna Verification Code";

        $mail->Body = "
            <p>Hello <strong>$fname</strong>,</p>
            <p>Your verification code is:</p>
            <h1 style='color:#4CAF50;'>$verification_code</h1>
            <p>This code expires in 1 hour.</p>
        ";

        $mail->send();

    } catch (Exception $e) {
        die("❌ EMAIL ERROR: " . $mail->ErrorInfo);
    }

    // ---------------------------------------------------------
    // 🔥 REDIRECT USER TO OTP PAGE
    // ---------------------------------------------------------
    $_SESSION['pending_activation_user'] = $user_id;

    header("Location: activate_employer.php");
    exit();
}
?>
