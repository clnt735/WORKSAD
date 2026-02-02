
<?php 
session_start();
include '../database.php';


//handle form submission 
$error = ""; 
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);     
    $password = trim($_POST['password']); 

    
    $sql = "SELECT * FROM user WHERE user_username = ?"; 
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
                // ✅ Employer login success
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['user_type_id'] = $row['user_type_id'];
                header("Location: home.php");
                exit();
            } elseif ($row['user_type_id'] == 2) {
                // ❌ Applicant trying to log in on applicant page
                $error = "This login page is for employers only. Please use the Applicant's Login.";
            } elseif ($row['user_type_id'] == 1) {
                // ❌ Admin trying to log in on applicant page
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
    <title>Applicant Login</title>
    <link rel="stylesheet" href="../styles.css">
</head>


    <?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
        <script>
        alert("Registered successfully! Please login.");
        </script>
    <?php endif; ?>

    
<body class="login-page">

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

