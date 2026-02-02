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

function wm_abort_registration(mysqli $conn, int $userId, string $message, ?string $logoPath = null, ?string $verificationDocPath = null): void
{
    if ($logoPath && file_exists($logoPath)) {
        @unlink($logoPath);
    }
    if ($verificationDocPath && file_exists($verificationDocPath)) {
        @unlink($verificationDocPath);
    }
    
    // First get the employer_id from employer_profiles (if it exists)
    $employerId = null;
    if ($empStmt = $conn->prepare('SELECT employer_id FROM employer_profiles WHERE user_id = ?')) {
        $empStmt->bind_param('i', $userId);
        $empStmt->execute();
        $empResult = $empStmt->get_result();
        if ($empRow = $empResult->fetch_assoc()) {
            $employerId = $empRow['employer_id'];
        }
        $empStmt->close();
    }
    
    // Delete verification request if exists (using employer_id from employer_profiles)
    if ($employerId && $stmt = $conn->prepare('DELETE FROM employer_verification_requests WHERE employer_id = ?')) {
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete employer profile
    if ($stmt = $conn->prepare('DELETE FROM employer_profiles WHERE user_id = ?')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    
    if ($stmt = $conn->prepare('DELETE FROM company WHERE user_id = ?')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    if ($stmt = $conn->prepare('DELETE FROM user_profile WHERE user_id = ?')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    if ($stmt = $conn->prepare('DELETE FROM user WHERE user_id = ?')) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
    $_SESSION['error'] = $message;
    header('Location: register.php');
    exit();
}

// ---------------------------------------------------------------
// PROCESS REGISTRATION
// ---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Sanitize input
    $fname  = trim($_POST['fname']);
    $mname  = trim($_POST['mname']); // Now optional
    $lname  = trim($_POST['lname']);
    $email  = trim($_POST['email']);
    
    // Validate names (letters only)
    if (!preg_match("/^[A-Za-z\s]+$/", $fname)) {
        $_SESSION['error'] = "❌ First name can only contain letters.";
        header("Location: register.php");
        exit();
    }
    
    if (!empty($mname) && !preg_match("/^[A-Za-z\s]+$/", $mname)) {
        $_SESSION['error'] = "❌ Middle name can only contain letters.";
        header("Location: register.php");
        exit();
    }
    
    if (!preg_match("/^[A-Za-z\s]+$/", $lname)) {
        $_SESSION['error'] = "❌ Last name can only contain letters.";
        header("Location: register.php");
        exit();
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "❌ Invalid email address.";
        header("Location: register.php");
        exit();
    }

    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password requirements
    if (strlen($password) < 8) {
        $_SESSION['error'] = "❌ Password must be at least 8 characters long.";
        header("Location: register.php");
        exit();
    }
    
    if (!preg_match("/[A-Z]/", $password)) {
        $_SESSION['error'] = "❌ Password must contain at least one uppercase letter.";
        header("Location: register.php");
        exit();
    }
    
    if (!preg_match("/[a-z]/", $password)) {
        $_SESSION['error'] = "❌ Password must contain at least one lowercase letter.";
        header("Location: register.php");
        exit();
    }
    
    if (!preg_match("/[0-9]/", $password)) {
        $_SESSION['error'] = "❌ Password must contain at least one number.";
        header("Location: register.php");
        exit();
    }
    
    if (!preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\|,.<>\/?]/", $password)) {
        $_SESSION['error'] = "❌ Password must contain at least one special character.";
        header("Location: register.php");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "❌ Passwords do not match!";
        header("Location: register.php");
        exit(); 
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $company_name = trim($_POST['company_name'] ?? '');
    $company_industry = trim($_POST['industry'] ?? '');
    $company_location = trim($_POST['location'] ?? '');
    $company_website = trim($_POST['website'] ?? '');
    $company_description = trim($_POST['description'] ?? '');

    if ($company_name === '') {
        $_SESSION['error'] = "❌ Please provide your company name.";
        header('Location: register.php');
        exit();
    }

    if ($company_website !== '' && !filter_var($company_website, FILTER_VALIDATE_URL)) {
        $_SESSION['error'] = "❌ Please enter a valid company website URL.";
        header('Location: register.php');
        exit();
    }

    $logoUpload = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $logoFile = $_FILES['logo'];
        if ($logoFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "❌ There was a problem uploading your logo. Please try again.";
            header('Location: register.php');
            exit();
        }
        $maxLogoSize = 3 * 1024 * 1024; // 3MB
        if ($logoFile['size'] > $maxLogoSize) {
            $_SESSION['error'] = "❌ Logo must be 3MB or smaller.";
            header('Location: register.php');
            exit();
        }
        $extension = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $_SESSION['error'] = "❌ Logo must be an image (JPG, PNG, GIF, or WEBP).";
            header('Location: register.php');
            exit();
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($logoFile['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            $_SESSION['error'] = "❌ Logo file type is not supported.";
            header('Location: register.php');
            exit();
        }
        $logoUpload = [
            'tmp_name' => $logoFile['tmp_name'],
            'extension' => $extension,
        ];
    }

    // ---------------------------------------------------------
    // VERIFICATION DOCUMENT VALIDATION
    // ---------------------------------------------------------
    $documentType = isset($_POST['document_type']) ? trim($_POST['document_type']) : '';
    $allowedDocumentTypes = ['national_id', 'business_permit'];
    
    if (!in_array($documentType, $allowedDocumentTypes, true)) {
        $_SESSION['error'] = "❌ Please select a valid document type (National ID or Business Permit).";
        header('Location: register.php');
        exit();
    }

    $verificationDocUpload = null;
    if (!isset($_FILES['verification_document']) || $_FILES['verification_document']['error'] === UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = "❌ Please upload a verification document.";
        header('Location: register.php');
        exit();
    }

    $verificationFile = $_FILES['verification_document'];
    if ($verificationFile['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "❌ There was a problem uploading your verification document. Please try again.";
        header('Location: register.php');
        exit();
    }

    $maxVerificationSize = 5 * 1024 * 1024; // 5MB
    if ($verificationFile['size'] > $maxVerificationSize) {
        $_SESSION['error'] = "❌ Verification document must be 5MB or smaller.";
        header('Location: register.php');
        exit();
    }

    $verificationExtension = strtolower(pathinfo($verificationFile['name'], PATHINFO_EXTENSION));
    $allowedVerificationExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($verificationExtension, $allowedVerificationExtensions, true)) {
        $_SESSION['error'] = "❌ Verification document must be a JPG, PNG, or PDF file.";
        header('Location: register.php');
        exit();
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $verificationMimeType = $finfo->file($verificationFile['tmp_name']);
    $allowedVerificationMimes = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($verificationMimeType, $allowedVerificationMimes, true)) {
        $_SESSION['error'] = "❌ Verification document file type is not supported.";
        header('Location: register.php');
        exit();
    }

    $verificationDocUpload = [
        'tmp_name' => $verificationFile['tmp_name'],
        'extension' => $verificationExtension,
        'document_type' => $documentType,
    ];

    $company_industry = $company_industry !== '' ? $company_industry : null;
    $company_location = $company_location !== '' ? $company_location : null;
    $company_website = $company_website !== '' ? $company_website : null;
    $company_description = $company_description !== '' ? $company_description : null;

    // Check if email exists
    $check_stmt = $conn->prepare("SELECT user_id FROM user WHERE user_email = ?");
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

    $user_type_id = 3;
    $user_status_id = 0; // Unverified

    // ---------------------------------------------------------
    // INSERT USER
    // ---------------------------------------------------------
    $stmt = $conn->prepare("
        INSERT INTO user (
            user_type_id,
            user_status_id,
            user_email,
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
            user_profile_email_address, 
            user_profile_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt2->bind_param("iissss", 
        $user_id, 
        $user_status_id,
        $fname, 
        $mname, 
        $lname, 
        $email
    );

    if (!$stmt2->execute()) {
        die("❌ SQL ERROR (profile insert): " . $stmt2->error);
    }

    $stmt2->close();

    $logoRelativePath = null;
    $logoAbsolutePath = null;
    if ($logoUpload) {
        $logoDir = dirname(__DIR__) . '/uploads/company_logos/';
        if (!is_dir($logoDir) && !mkdir($logoDir, 0777, true)) {
            wm_abort_registration($conn, $user_id, 'Unable to prepare storage for the company logo.');
        }
        $logoFilename = 'company_' . $user_id . '_' . uniqid('', true) . '.' . $logoUpload['extension'];
        $logoAbsolutePath = $logoDir . $logoFilename;
        if (!move_uploaded_file($logoUpload['tmp_name'], $logoAbsolutePath)) {
            wm_abort_registration($conn, $user_id, 'Unable to save the uploaded logo. Please try again.');
        }
        $logoRelativePath = 'uploads/company_logos/' . $logoFilename;
    }

    $companyStmt = $conn->prepare('
        INSERT INTO company (
            user_id,
            company_name,
            description,
            industry,
            location,
            website,
            logo
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ');

    $companyStmt->bind_param(
        'issssss',
        $user_id,
        $company_name,
        $company_description,
        $company_industry,
        $company_location,
        $company_website,
        $logoRelativePath
    );

    if (!$companyStmt->execute()) {
        wm_abort_registration($conn, $user_id, 'Unable to save company details. Please try again.', $logoAbsolutePath);
    }
    $companyStmt->close();

    // ---------------------------------------------------------
    // CREATE EMPLOYER PROFILE
    // ---------------------------------------------------------
    $employerProfileStmt = $conn->prepare('
        INSERT INTO employer_profiles (
            user_id,
            company_name,
            company_address,
            verification_status,
            is_verified
        ) VALUES (?, ?, ?, ?, ?)
    ');
    
    $verificationStatus = 'pending';
    $isVerified = 0;
    $employerProfileStmt->bind_param(
        'isssi',
        $user_id,
        $company_name,
        $company_location,
        $verificationStatus,
        $isVerified
    );
    
    if (!$employerProfileStmt->execute()) {
        wm_abort_registration($conn, $user_id, 'Unable to create employer profile. Please try again.', $logoAbsolutePath);
    }
    
    // Get the employer_id for the verification request
    $employer_id = $conn->insert_id;
    $employerProfileStmt->close();

    // ---------------------------------------------------------
    // SAVE VERIFICATION DOCUMENT
    // ---------------------------------------------------------
    $verificationDocRelativePath = null;
    $verificationDocAbsolutePath = null;
    
    if ($verificationDocUpload) {
        $verificationDir = dirname(__DIR__) . '/uploads/verification_documents/';
        if (!is_dir($verificationDir) && !mkdir($verificationDir, 0777, true)) {
            wm_abort_registration($conn, $user_id, 'Unable to prepare storage for the verification document.', $logoAbsolutePath);
        }
        
        $verificationFilename = 'verification_' . $user_id . '_' . uniqid('', true) . '.' . $verificationDocUpload['extension'];
        $verificationDocAbsolutePath = $verificationDir . $verificationFilename;
        
        if (!move_uploaded_file($verificationDocUpload['tmp_name'], $verificationDocAbsolutePath)) {
            wm_abort_registration($conn, $user_id, 'Unable to save the verification document. Please try again.', $logoAbsolutePath);
        }
        
        $verificationDocRelativePath = 'uploads/verification_documents/' . $verificationFilename;
        
        // Insert verification request
        $verificationStmt = $conn->prepare('
            INSERT INTO employer_verification_requests (
                employer_id,
                document_path,
                document_type,
                status,
                submitted_at
            ) VALUES (?, ?, ?, ?, NOW())
        ');
        
        $pendingStatus = 'pending';
        $verificationStmt->bind_param(
            'isss',
            $employer_id,
            $verificationDocRelativePath,
            $verificationDocUpload['document_type'],
            $pendingStatus
        );
        
        if (!$verificationStmt->execute()) {
            wm_abort_registration($conn, $user_id, 'Unable to submit verification request. Please try again.', $logoAbsolutePath, $verificationDocAbsolutePath);
        }
        $verificationStmt->close();
    }

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
