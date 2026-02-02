<?php
session_start();
require_once __DIR__ . "/../../database.php";

if (!isset($_SESSION['verified'])) {
    header("Location: forgot_password.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $pass = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // validations
    if ($pass !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $pass)) {
        $error = "Password must contain at least 1 uppercase letter.";
    } elseif (!preg_match('/[0-9]/', $pass)) {
        $error = "Password must contain at least 1 number.";
    } elseif (!preg_match('/[\W_]/', $pass)) {
        $error = "Password must contain at least 1 special character.";
    } elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);

        $email = $_SESSION['reset_email'];

        $stmt = $conn->prepare("UPDATE user SET user_password = ? WHERE user_email = ?");
        $stmt->bind_param("ss", $hashed, $email);
        $stmt->execute();

        // clear session
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_otp']);
        unset($_SESSION['verified']);

        header("Location: ../login.php?reset=success");
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create New Password</title>

<style>
    .forgot-newpw {
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

    .forgot-newpw-container {
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

    input[type="password"] {
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

    input[type="password"]:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59,130,246,0.3);
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
        .forgot-newpw-container {
            width: 90%;
            max-width: 500px;
            margin: 0 auto;
        }

        form {
            padding: 20px;
        }

        input[type="password"] {
            max-width: 92%;
        }
    }
</style>
</head>
<body class="forgot-newpw">

<div class="forgot-newpw-container">

    <img src="../../images/workmunalogo2-removebg.png" class="logo" alt="Logo">

    <h2>Create New Password</h2>
    <p class="subtitle">
        Enter your new password below. Make sure it is strong and secure.
    </p>

    <?php if ($error): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>

        <label>Re-enter Password</label>
        <input type="password" name="confirm_password" placeholder="Re-enter new password" required>

        <button type="submit">Save New Password</button>
    </form>

</div>

</body>
</html>