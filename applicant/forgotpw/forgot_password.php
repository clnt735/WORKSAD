<?php
session_start();
require_once __DIR__ . "/../../database.php";

// LOAD PHPMailer
require __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE user_email = ? LIMIT 1");
    
    if (!$stmt) {
    die("SQL ERROR: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $error = "We could not find an account with this gmail.";
    } else {
        $user = $result->fetch_assoc();
        $fname = $user['user_firstname'];

        // Generate OTP
        $otp = rand(100000, 999999);

        // Store in session
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_otp']   = $otp;

        // --- SEND OTP USING PHPMailer ---
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'workmuna310@gmail.com';
            $mail->Password   = 'xfqskaljimhpppam';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('workmuna310@gmail.com', 'WorkMuna Support');
            $mail->addAddress($email);
            $mail->addReplyTo('workmuna310@gmail.com');

            $mail->isHTML(true);
            $mail->Subject = "Your WorkMuna Password Reset Code";

            $mail->Body = "
                <p>Hello <strong>$fname</strong>,</p>
                <p>You requested to reset your password.</p>
                <p>Your reset code is:</p>
                <h2 style='color:#4CAF50;'>$otp</h2>
                <p>This code expires in 1 hour.</p>
            ";

            $mail->send();

        } catch (Exception $e) {
            $error = "Email sending failed: " . $mail->ErrorInfo;
        }

        if (!$error) {
            header("Location: forgotpw_code.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password</title>

<style>
    .forgotpw-page {
        margin: 0;
        padding: 0;
        font-family: "Manrope", sans-serif;
        background-color: #ffffffff;
        color: #e6edf3;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }

    .forgotpw-container {
        width: 380px;
        text-align: center;
        margin: 0   ;
    }

    .logo {
        margin: 0 auto 12px;
        display: block;
        object-fit: contain;
        width: 180px;
        height: auto;
    }

    h2 {
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 24px;
        color: black;
    }

    p.subtitle {
        margin-bottom: 22px;
        font-size: 14px;
        color: #9ba3af;
        line-height: 1.4;
    }

    form {
        background: #fefefe;
        padding: 22px;
        border-radius: 8px;
        text-align: left;
        border: 1px solid #bebfc0;
    }

    label {
        font-size: 13px;
        margin-bottom: 6px;
        display: block;
        color: #555;
    }

    input[type="email"] {
        max-width: 310px;
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #bec0c2;
        background: #f3f8ff;
        color: black;
        font-size: 14px;
        margin-bottom: 15px;
    }

    input[type="email"]:focus {
        outline: none;
        border-color: #58a6ff;
        box-shadow: 0 0 0 2px rgba(56, 139, 253, 0.4);
    }

    button {
        width: 100%;
        background: #238636;
        color: white;
        padding: 10px;
        font-size: 14px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
    }

    button:hover {
        background: #2ea043;
    }

    .error {
        color: #ff7b72;
        font-size: 14px;
        margin-bottom: 10px;
    }


        /* ------ MOBILE RESPONSIVE (max-width: 600px) ------- */
    @media (max-width: 600px) {
        .forgotpw-container {
            width: 90%;
            max-width: 500px;
            margin: 0 auto;
        }

        form {
            padding: 20px;
        }

        input[type="email"] {
            max-width: 92%;
        }
    }


</style>
</head>



<body class="forgotpw-page">
    <div class="forgotpw-container">

        <!-- Replace with your logo -->
        <img src="../../images/workmunalogo2-removebg.png" class="logo" alt="Logo">

        <h2>Reset your password</h2>
        <p class="subtitle">
            Enter your user account's verified email address and we will send you a one-time password code.
        </p>

        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" placeholder="Enter your email" required>
            <button type="submit">Send code to email</button>
        </form>
    </div>
</body>
</html>
