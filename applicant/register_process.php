<?php 
session_start(); // REQUIRED for OTP redirect

// ---------------------------------------------------------------
// ENVIRONMENT / DB CONNECTION
// ---------------------------------------------------------------
// Ensure the database connection file is loaded regardless of host (mobile may use IP or hostname)
$dbPath = __DIR__ . '/../database.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
    $env = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) ? 'LOCAL' : 'DEV';
} else {
    // fallback: try document root
    $alt = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . '/database.php';
    if (file_exists($alt)) {
        require_once $alt;
        $env = 'ALT';
    }
}

if (!isset($conn)) {
    // more helpful error including attempted paths
    error_log("register_process.php: database.php not found at $dbPath or $alt");
    die("❌ ERROR: conn NOT SET — check database.php path; attempted: $dbPath, $alt");
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
    $fname  = trim($_POST['fname'] ?? '');
    $mname  = trim($_POST['mname'] ?? '');
    $lname  = trim($_POST['lname'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $gender = $_POST['gender'] ?? '';

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
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation helpers
    function validateName($name){
        $n = trim($name);
        if ($n === '') throw new Exception('Name is required');
        if (!preg_match('/^[A-Za-z ]+$/', $n)) throw new Exception('Name must contain only letters and spaces');
        return $n;
    }

    function validateEmailAddr($email){
        $e = trim($email);
        if ($e === '') throw new Exception('Email is required');
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format');
        return $e;
    }

    function validateDisplayName($u) {
        $s = trim($u);
        if ($s === '') {
            throw new Exception('Display name is required');
        }


        // must start with a letter, and be 5–15 chars total
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{4,14}$/i', $s)) {
            throw new Exception('Display name must start with a letter and contain 5–15 characters (letters, numbers, underscores)');
        }

        return $s;
    }


    function validatePassword($pw){
        if ($pw === '') throw new Exception('Password is required');
        // Require at least one uppercase, one lowercase, one digit, and one non-alphanumeric (special) character.
        // Use a simpler character class for special chars to avoid complex escaping issues.
        $pattern = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
        if (!preg_match($pattern, $pw)) {
            throw new Exception('Password must be at least 8 characters and include uppercase, lowercase, number and symbol');
        }
        return $pw;
    }

    function validateAgeFromDob($year,$month,$day){
        $y=(int)$year; $m=(int)$month; $d=(int)$day;
        if (!checkdate($m,$d,$y)) throw new Exception('Invalid date of birth');
        $dob = new DateTime(sprintf('%04d-%02d-%02d',$y,$m,$d));
        $now = new DateTime();
        $age = $now->diff($dob)->y;
        if ($age < 18 || $age > 100) throw new Exception('Age must be between 18 and 100');
        return $age;
    }

    // Run validations and collect errors
    $errors = [];
    try { $clean_fname = validateName($fname); } catch(Exception $e){ $errors[] = 'First name: ' . $e->getMessage(); }
    if ($mname !== '') {
        try { $clean_mname = validateName($mname); } catch(Exception $e){ $errors[] = 'Middle name: ' . $e->getMessage(); }
    } else { $clean_mname = ''; }
    try { $clean_lname = validateName($lname); } catch(Exception $e){ $errors[] = 'Last name: ' . $e->getMessage(); }
    try { $clean_display_name = validateDisplayName($display_name); } catch(Exception $e){ $errors[] = $e->getMessage(); }
    try { $clean_email = validateEmailAddr($email); } catch(Exception $e){ $errors[] = $e->getMessage(); }
    try { $age = validateAgeFromDob($year,$month,$day); } catch(Exception $e){ $errors[] = $e->getMessage(); }
    try { $clean_pw = validatePassword($password); } catch(Exception $e){ $errors[] = $e->getMessage(); }
    if ($password !== $confirm_password) { $errors[] = 'Passwords do not match'; }

    if (!empty($errors)){
        $_SESSION['errors'] = $errors;
        header('Location: register.php'); exit;
    }

    // Build cleaned data and set success message
    $cleaned = [
        'first_name'=>$clean_fname,
        'middle_name'=>$clean_mname,
        'last_name'=>$clean_lname,
        'display_name'=>$clean_display_name,
        'email'=>$clean_email,
        'age'=>$age,
    ];
    // success message intentionally removed to avoid exposing cleaned data in session


    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

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

    $user_type_id = 2;
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
            display_name,
            activation_token,
            token_expires_at,
            user_created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param("iisssss", 
        $user_type_id,
        $user_status_id,
        $email,
        $hashed_password,
        $clean_display_name,
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
        $mail->SMTPDebug = 0; 
        $mail->Debugoutput = 'error_log';
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'workmuna310@gmail.com';
        $mail->Password   = 'xfqskaljimhpppam';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('workmuna310@gmail.com', 'WorkMuna Support');
        $mail->addReplyTo('workmuna310@gmail.com');
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

    header("Location: activate.php");
    exit();
}
?>
