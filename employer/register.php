<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Account - WorkMuna</title>
  <link rel="stylesheet" href="../styles.css">
</head>

<body class="register-page">

<div class="register-box">

    <h2>Create a new account</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div style="background:#ffdddd; padding:10px; border-left:4px solid #ff4444; margin-bottom:15px;">
                <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']); 
                ?>
            </div>
        <?php endif; ?>

    <form action="register_process.php" method="POST">

        <div class="name-fields">
            <input type="text" name="fname" placeholder="First name" required>
            <input type="text" name="mname" placeholder="Middle name" required>
            <input type="text" name="lname" placeholder="Last name" required>
        </div>

        <label class="label-small">Birthday</label>
        <div class="dob-selection">
            <select name="month" required>
                <option value="" selected disabled>Month</option>
                <option value="1">Jan</option>
                <option value="2">Feb</option>
                <option value="3">Mar</option>
                <option value="4">Apr</option>
                <option value="5">May</option>
                <option value="6">Jun</option>
                <option value="7">Jul</option>
                <option value="8">Aug</option>
                <option value="9">Sep</option>
                <option value="10">Oct</option>
                <option value="11">Nov</option>
                <option value="12">Dec</option>
            </select>

            <input type="number" name="day" placeholder="Day" min="1" max="31" required>
            <input type="number" name="year" placeholder="Year" min="1900" max="2025" required>
        </div>

        <label class="label-small">Gender</label>
        <div class="gender-row">
            <label class="gender-box">
                Female <input type="radio" name="gender" value="female" required>
            </label>
            <label class="gender-box">
                Male <input type="radio" name="gender" value="male" required>
            </label>
            <label class="gender-box">
                Like a lot <input type="radio" name="gender" value="notsay" required>
            </label>
        </div>

        <input type="email" name="email" placeholder="Username or mail" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm_password" placeholder="Confirm password" required>
       

        <button type="submit" class="signup-btn">Sign Up</button>

    </form>

    <p class="login-link"><a href="login.php">Already have an account?</a></p>

</div>

</body>
</html>
