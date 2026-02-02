
<?php 
session_start();
include '../database.php';


//handle form submission 
$error = ""; 
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);     
    $password = trim($_POST['password']); 

    
    $sql = "SELECT * FROM user WHERE user_email = ?"; 
    $stmt = $conn->prepare($sql); // 
    $stmt->bind_param("s", $email); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        // verify hashed password
        if (password_verify($password, $row['user_password'])) { 

            // Check user type
            if ($row['user_type_id'] == 3) {
                // Employer - check dual verification requirements
                $userId = $row['user_id'];
                
                // Check 1: Email verified (activation_token is NULL)
                $emailVerified = ($row['activation_token'] === null || $row['activation_token'] === '');
                
                // Check 2: Admin approved (verification status = 'approved')
                $adminApproved = false;
                $verificationStatus = 'pending';
                
                $verifyStmt = $conn->prepare("
                    SELECT evr.status 
                    FROM employer_verification_requests evr
                    INNER JOIN employer_profiles ep ON evr.employer_id = ep.employer_id
                    WHERE ep.user_id = ?
                    ORDER BY evr.submitted_at DESC
                    LIMIT 1
                ");
                $verifyStmt->bind_param('i', $userId);
                $verifyStmt->execute();
                $verifyResult = $verifyStmt->get_result();
                
                if ($verifyResult->num_rows > 0) {
                    $verifyRow = $verifyResult->fetch_assoc();
                    $verificationStatus = $verifyRow['status'];
                    $adminApproved = ($verificationStatus === 'approved');
                }
                $verifyStmt->close();
                
                // Determine login outcome
                if (!$emailVerified) {
                    $error = "Please verify your email address first. Check your inbox for the verification code.";
                } elseif ($verificationStatus === 'rejected') {
                    $error = "Your employer verification was rejected. Please contact support for assistance.";
                } elseif (!$adminApproved) {
                    $error = "Your account is pending admin approval. You'll receive an email once your verification is reviewed.";
                } elseif ($row['user_status_id'] != 1) {
                    $error = "Your account is not active. Please contact support.";
                } else {
                    // ✅ Both verified - Employer login success
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['user_type_id'] = $row['user_type_id'];
                    header("Location: home.php");
                    exit();
                }
            } elseif ($row['user_type_id'] == 2) {
                // ❌ Applicant trying to log in on employer page
                $error = "This login page is for employers only.";
            } elseif ($row['user_type_id'] == 1 || $row['user_type_id'] == 4) {
                // ❌ Admin/SuperAdmin trying to log in on employer page
                $error = "Admins cannot log in here. Please use the Admin Login.";
            } else {
                $error = "Unknown user type.";
            }

        } else {
            $error = "Invalid email or password."; 
        }
    } else {
        $error = "Invalid email or password."; 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Login</title>
    <link rel="stylesheet" href="../styles.css">
    <?php if (isset($_GET['status']) && $_GET['status'] === 'email_verified'): ?>
    <style>
        .status-banner {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-left: 4px solid #4CAF50;
            padding: 15px 20px;
            margin: 0 0 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .status-banner h3 {
            margin: 0 0 8px 0;
            color: #2e7d32;
            font-size: 16px;
        }
        .status-banner p {
            margin: 0;
            color: #555;
            font-size: 14px;
            line-height: 1.5;
        }
        .status-banner .icon {
            font-size: 20px;
            margin-right: 8px;
        }
    </style>
    <?php endif; ?>
</head>

<body class="login-page">

<?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
    <script>
    alert("Registered successfully! Please login.");
    </script>
<?php endif; ?>

<?php if (isset($_GET['status']) && $_GET['status'] === 'activated'): ?>
    <script>alert("Account activated! You can now log in.");</script>
<?php endif; ?>

<header>
    <div class="navbar">
        <div class="nav-left">
            <a href="../index.php" class="logo">
                <img src="../images/workmunalogo2-removebg.png" alt="WorkMuna Logo">
            </a>
        </div>
    </div>
</header>


    <div class="login-container">
        <h2>Employer Login</h2>

        <?php if (isset($_GET['status']) && $_GET['status'] === 'email_verified'): ?>
            <div class="status-banner">
                <h3><span class="icon">✅</span> Email Verified Successfully!</h3>
                <p>Your email has been verified. Your account is now <strong>pending admin approval</strong>. You'll receive an email once an admin reviews your verification documents. This typically takes 1-2 business days.</p>
            </div>
        <?php endif; ?>
        <!-- if statement to display error message -->               
        <?php if($error != ""): ?>
            <p style="color: red;"><?= $error ?></p>
        <?php endif; ?>
        
        <!-- login form -->    
        <!-- added method="POST" -->
        <form method="POST" action=""> 
            
            <label for="email">Email Address</label>
            <!-- added name="email" -->
            <input type="email" id="email" name="email" placeholder="Enter your email" required>

            <label for="password">Password</label>
            <div class="password-field">
                <!-- added name="password" -->
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit">LOGIN</button>
        </form>

        <div class="register"> 
            Not yet registered? <a href="register.php">Create a free account.</a> 
        </div>  


        <div class="redirect"> 
        <a href="../applicant/login.php">Are you an applicant?</a> 
    </div> 
    </div>



</body>
</html>

