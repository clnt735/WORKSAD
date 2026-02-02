<?php 
session_start();
include '../database.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email_or_display = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Fetch user by email OR display_name
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_email = ? OR display_name = ?");
    $stmt->bind_param("ss", $email_or_display, $email_or_display);
    $stmt->execute();
    $result = $stmt->get_result();

    // ---- USER NOT FOUND ----
    if ($result->num_rows != 1) {
        $error = "Invalid email/display name or password.";
    } else {
        $user = $result->fetch_assoc();

        // ---- PASSWORD CHECK ----
        if (!password_verify($password, $user['user_password'])) {
            $error = "Invalid email/display name or password.";
        }

        // ---- ACCOUNT STATUS CHECKS ----
        elseif ($user['user_status_id'] == 0) {
            $error = "Your account is not activated. Please check your email.";
        } 
        elseif ($user['user_status_id'] == 2) {
            $error = "Your account is deactivated. Contact support.";
        } 
        elseif ($user['user_status_id'] == 3) {
            $error = "Your account is blocked. Contact admin.";
        } 
        elseif ($user['user_type_id'] != 2) {
            $error = "This login page is for applicants only.";
        } 
        
        // ---- SUCCESSFUL LOGIN ----
        else {

            // ⭐ ONLY IF USER HAS 2FA ENABLED ⭐
            if ($user['twofa_enabled'] == 1) {

                $otp = random_int(100000, 999999);
                $expires = date('Y-m-d H:i:s', time() + 300);

                // Store OTP
                $update = $conn->prepare("UPDATE user SET twofa_code = ?, twofa_expires = ? WHERE user_id = ?");
                $update->bind_param("ssi", $otp, $expires, $user['user_id']);
                $update->execute();

                // Send OTP Email
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'workmuna310@gmail.com';
                    $mail->Password = 'xfqskaljimhpppam'; // Gmail App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom('workmuna310@gmail.com', 'WorkMuna');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = "Your WorkMuna Verification Code";
                    $mail->Body = "<h2>Your verification code is <b>$otp</b></h2><p>This code expires in 5 minutes.</p>";
                    $mail->send();

                    // Save ID temporarily for OTP verification
                    $_SESSION['pending_2fa_user'] = $user['user_id'];

                    header("Location: verify_2fa.php");
                    exit();

                } catch (Exception $e) {
                    $error = "Failed to send verification code. Please try again.";
                }
            }

            // ⭐ IF 2FA IS OFF → LOGIN DIRECTLY ⭐
            else {
                $_SESSION['user_id'] = $user['user_id'];
                header("Location: search_jobs.php");
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login</title>
    <link rel="stylesheet" href="../styles.css">
</head>

<body class="login-page">

<div class="login-wrapper">
    
    <!-- LEFT SIDE (Logo + text like Facebook) -->
    <div class="login-left">
        <img src="../images/workmunalogo2-removebg.png" class="big-logo" alt="WorkMuna Logo">
        <h1 class="tagline">Connect with jobs and opportunities around you on WorkMuna.</h1>
    </div>

    <div class="login-container">
        <h2>Applicant Login</h2>

        <?php if ($error != ""): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST" action=""> 
            <label>Email or Display Name</label>
            <input type="text" name="email" placeholder="Enter your email or display name" required>

            <label class="pw-label">Password
                    <a href="forgotpw/forgot_password.php" class="forgot-link">Forgot password?</a>
            </label>
            <input type="password" name="password" placeholder="Enter your password" required>

            <button type="submit">LOGIN</button>
        </form>

        <div class="register"> 
            Not yet registered? <a href="register.php">Create a free account.</a> 
        </div>

        <div class="redirect"> 
            <a href="../employercontent/login.php">Are you an employer?</a> 
        </div> 
    </div>
</div>

</body>
</html>
