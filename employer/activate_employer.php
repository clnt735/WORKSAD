<?php
session_start();
// Assuming database.php connects and sets $conn
include '../database.php'; 
require __DIR__ . '/../vendor/autoload.php'; 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If user tries to access without a pending account session
if (!isset($_SESSION['pending_activation_user'])) {
    // Redirect to registration page if no pending user is set
    header("Location: register.php"); 
    exit();
}

$user_id = $_SESSION['pending_activation_user'];
$error = "";
$success = "";

// Helper function to send the code
function send_new_code($conn, $user_id, $code, $email) {
    // 1. Store HASHED code and expiry
    $hashed_code = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 1200); // 20 min expiry

    $stmt = $conn->prepare("UPDATE user SET activation_token = ?, token_expires_at = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $hashed_code, $expires, $user_id);
    $stmt->execute();
    
    // 2. Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'workmuna310@gmail.com';
        $mail->Password = 'ialjpczhkbfbigfq'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('workmuna310@gmail.com', 'WorkMuna Support');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Your New WorkMuna Verification Code';
        $mail->Body = "<h2>Your new verification code is <b>$code</b></h2><p>It expires in 20 minutes.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log mail error if needed
        return false;
    }
}


// ✅ Handle Resend Code Button
if (isset($_POST['resend'])) {
    
    // 1. Get user email
    $fetch = $conn->prepare("
     SELECT user_profile.user_profile_email_address 
     FROM user 
     INNER JOIN user_profile ON user.user_id = user_profile.user_id
     WHERE user.user_id = ?
     ");
    $fetch->bind_param("i", $user_id);
    $fetch->execute();
    $userEmailData = $fetch->get_result()->fetch_assoc();
    $user_email = $userEmailData['user_profile_email_address'];

    // 2. Generate new OTP
    $new_otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // 3. Send and update DB
    if (send_new_code($conn, $user_id, $new_otp, $user_email)) {
        $success = "A new verification code has been sent to your email. Check your spam folder! ✅";
    } else {
        $error = "Failed to resend code. Please contact support.";
    }
}


// ✅ Handle Verify Code
if (isset($_POST['verify'])) {

    $input_otp = trim($_POST['otp']);

    // Fetch stored HASH and expiry
    $stmt = $conn->prepare("SELECT activation_token, token_expires_at FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stored_hash = $user['activation_token'];
    $expires = strtotime($user['token_expires_at']);

    if (time() > $expires) {
        $error = "Your code has expired. Please click **Resend Code**.";
    } elseif (!password_verify($input_otp, $stored_hash)) {
        // --- SECURITY CHECK: VERIFY INPUT AGAINST HASH ---
        $error = "Incorrect code. Try again.";
    } else {
        // ✅ Success: ACTIVATE USER
        
        // user_status_id=1 is assumed to be "Active"
        $activate = $conn->prepare("UPDATE user SET user_status_id = 1, activation_token = NULL, token_expires_at = NULL WHERE user_id = ?");
        $activate->bind_param("i", $user_id);
        $activate->execute();

        // Start session for the newly activated user and redirect
        $_SESSION['user_id'] = $user_id; 
        // You might need to fetch the user_type_id here if it's required for the session.
        
        unset($_SESSION['pending_activation_user']);

        header("Location: login.php?status=activated"); 
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Code</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Minimal CSS for spinner */
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 4px solid #fff;
            width: 16px;
            height: 16px;
            -webkit-animation: spin 2s linear infinite; /* Safari */
            animation: spin 2s linear infinite;
            display: inline-block;
            margin-right: 5px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .resend-btn[disabled] {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="login-page">

<div class="login-container">
    <h2>Verify Your Account</h2>
    <p>We sent a verification code to your email.</p>

    <?php if ($error): ?>
        <p style="color: red;"><?= $error ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color: green;"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Enter Code</label>
        <input type="text" name="otp" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="6-digit code" required>

        <button type="submit" name="verify">Verify</button>
        <button type="submit" name="resend" class="resend-btn" id="resendBtn">Resend Code</button>
    </form>
</div>

<script>
let resendBtn = document.getElementById('resendBtn');
const cooldownSeconds = 20; 
let countdown = cooldownSeconds;

// Function to start the countdown timer
function startCountdown() {
    resendBtn.disabled = true;
    resendBtn.classList.add('disabled');
    
    let timer = setInterval(() => {
        if (countdown >= 0) {
            resendBtn.innerText = `Resend in ${countdown}s`;
            countdown--;
        } else {
            clearInterval(timer);
            resendBtn.innerText = 'Resend Code';
            resendBtn.classList.remove('disabled');
            resendBtn.disabled = false;
            countdown = cooldownSeconds; // reset
        }
    }, 1000);
}

// Check if the form was submitted with the resend button and initiate countdown immediately
if (window.performance && window.performance.navigation.type === window.performance.navigation.TYPE_RELOAD) {
    // If it was a standard page reload, check if the "resend" success message is present
    const successMessage = document.querySelector('p[style="color: green;"]');
    if (successMessage && successMessage.innerText.includes('new verification code has been sent')) {
        startCountdown();
    }
} else if (resendBtn.classList.contains('just-resent')) {
    // This is useful if you use AJAX, but for a full POST submit, the RELOAD check above is better.
    startCountdown();
}


// Event listener handles the visual feedback *before* the form submits
resendBtn.addEventListener('click', function(e) {
    if (resendBtn.disabled) {
        e.preventDefault(); // Stop the form submission if already disabled/in countdown
    }
    
    // Quick visual feedback *before* submit
    resendBtn.innerHTML = '<span class="spinner"></span> Sending...';
});
</script>

</body>
</html>