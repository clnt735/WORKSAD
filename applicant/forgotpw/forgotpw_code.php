<?php
session_start();

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST['code']);

    if ($code == $_SESSION['reset_otp']) {
        $_SESSION['verified'] = true;
        header("Location: forgot_newpw.php");
        exit;
    } else {
        $error = "Incorrect code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter Verification Code</title>

<style>
    .forgotpw-code {
        margin: 0;
        padding: 0;
        font-family: "Manrope", sans-serif;
        background-color: var(--color-bg);
        color: #333;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }

    .forgotpw-code-container {
        width: 380px;
        text-align: center;
        margin: 0;
    }

    .logo {
        margin: 0 auto 12px;
        display: block;
        object-fit: contain;
        width: 180px;
        height: auto;
    }

    h2 {
        margin-bottom: 6px;
        font-weight: 600;
        font-size: 24px;
    }

    p.subtitle {
        margin-bottom: 25px;
        font-size: 14px;
        color: #6b7280;
    }

    form {
        background: white;
        padding: 22px;
        border-radius: 8px;
        text-align: left;
        border: 1px solid #d0d7de;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    label {
        font-size: 13px;
        margin-bottom: 6px;
        display: block;
        color: #555;
    }

    input[type="text"] {
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

    input[type="text"]:focus {
        outline: none;
        border-color: #58a6ff;
        box-shadow: 0 0 0 2px rgba(56, 139, 253, 0.4);
    }

    button {
        width: 100%;
        background: #238636;
        color: white;
        padding: 10px;
        font-size: 15px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        margin-top: 5px;
    }

    button:hover {
        background: #2ea043;
    }

    .error {
        color: #d93025;
        font-size: 14px;
        margin-bottom: 12px;
        text-align: center;
    }

            /* ------ MOBILE RESPONSIVE (max-width: 600px) ------- */
    @media (max-width: 600px) {
        .forgotpw-code-container {
            width: 90%;
            max-width: 500px;
            margin: 0 auto;
        }

        form {
            padding: 20px;
        }

        input[type="text"] {
            max-width: 92%;
        }
    }
    
    
</style>
</head>
<bod class="forgotpw-code">

<div class="forgotpw-code-container">

    <img src="../../images/workmunalogo2-removebg.png" class="logo" alt="Logo">

    <h2>Enter Verification Code</h2>
    <p class="subtitle">
        We sent a 6-digit verification code to your email.  
        Enter it below to continue.
    </p>

    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Verification Code</label>
        <input type="text" name="code" maxlength="6" placeholder="Enter 6-digit code" required>
        <button type="submit">Verify</button>
    </form>

</div>

</body>
</html>

