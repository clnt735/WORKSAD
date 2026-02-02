<?php
session_start();
include '../database.php';
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If user tries to access without 2FA session
if (!isset($_SESSION['pending_2fa_user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['pending_2fa_user'];
$error = "";
$success = "";

// ✅ Handle Resend Code Button
if (isset($_POST['resend'])) {

    // Generate new OTP & expiry
    $otp = random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 min

    // Save new OTP to DB
    $stmt = $conn->prepare("UPDATE user SET twofa_code = ?, twofa_expires = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $otp, $expires, $user_id);
    $stmt->execute();

    // Get user email
   $fetch = $conn->prepare("
    SELECT user_profile.user_profile_email_address 
    FROM user 
    INNER JOIN user_profile ON user.user_id = user_profile.user_id
    WHERE user.user_id = ?
    ");
    $fetch->bind_param("i", $user_id);
    $fetch->execute();
    $userEmailData = $fetch->get_result()->fetch_assoc();
    var_dump($userEmailData); die();
    $user_email = $userEmailData['user_profile_email_address'];


    // Send email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'workmuna310@gmail.com';
        $mail->Password = 'xfqskaljimhpppam'; // App password only
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('workmuna310@gmail.com', 'WorkMuna');
        $mail->addAddress($user_email);
        $mail->isHTML(true);
        $mail->Subject = 'Your New WorkMuna Verification Code';
        $mail->Body = "<h2>Your new verification code is <b>$otp</b></h2><p>It expires in 5 minutes.</p>";
        $mail->send();

        $success = "A new verification code has been sent to your email ✅";
    } catch (Exception $e) {
        $error = "Failed to resend code. Error: " . $mail->ErrorInfo;
    }
}

// ✅ Handle Verify Code
if (isset($_POST['verify'])) {

    $input_otp = trim($_POST['otp']);

    // Fetch stored OTP
    $stmt = $conn->prepare("SELECT twofa_code, twofa_expires, user_type_id FROM user WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stored_otp = $user['twofa_code'];
    $expires = strtotime($user['twofa_expires']);

    if (time() > $expires) {
        $error = "Your code has expired. Please click Resend Code.";
    } elseif ($input_otp !== $stored_otp) {
        $error = "Incorrect code. Try again.";
    } else {
        // ✅ Success → Log user in
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type_id'] = $user['user_type_id'];

        // Clear OTP
        $clear = $conn->prepare("UPDATE user SET twofa_code = NULL, twofa_expires = NULL WHERE user_id = ?");
        $clear->bind_param("i", $user_id);
        $clear->execute();

        unset($_SESSION['pending_2fa_user']);

        header("Location: home.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify Code</title>
    <link rel="stylesheet" href="../styles.css">
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
        <input type="text" name="otp" maxlength="6" placeholder="6-digit code" required>

        <button type="submit" name="verify">Verify</button>
        <button type="submit" name="resend" class="resend-btn" id="resendBtn">Resend Code</button>
    </form>
</div>



<script>
let resendBtn = document.getElementById('resendBtn');
let countdown = 20; // seconds

resendBtn.addEventListener('click', function() {
    // Show loading spinner
    resendBtn.innerHTML = '<span class="spinner"></span>';
    resendBtn.classList.add('disabled');
    resendBtn.disabled = true;

    // Start countdown AFTER form submit returns (small delay)
    setTimeout(() => {
        let timer = setInterval(() => {
            resendBtn.innerText = `Resend in ${countdown}s`;
            countdown--;

            if (countdown < 0) {
                clearInterval(timer);
                resendBtn.innerText = 'Resend Code';
                resendBtn.classList.remove('disabled');
                resendBtn.disabled = false;
                countdown = 20; // reset
            }
        }, 1000);
    }, 1500); // delay so user sees spinner
});
</script>

</body>
</html>
