<?php session_start(); ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Account - WorkMuna</title>
  <link rel="stylesheet" href="../styles.css">
  <style>
    .register-box {
      max-width: 480px;
      padding: 1.25rem 1.5rem;
    }
    .register-box h2 {
      font-size: 1.3rem;
      margin-bottom: 0.75rem;
    }
    .register-box form {
      gap: 0.65rem;
    }
    .label-small {
      font-size: 0.75rem;
      margin-bottom: 0.25rem;
    }
    .register-box input,
    .register-box select {
      padding: 0.5rem 0.65rem;
      font-size: 0.875rem;
    }
    .name-fields {
      gap: 0.5rem !important;
      margin-bottom: 0.65rem !important;
    }
    .dob-selection {
      gap: 0.5rem;
    }
    .dob-selection input {
      padding: 0.5rem 0.5rem;
    }
    .signup-btn {
      padding: 0.6rem 1.5rem;
      font-size: 0.9rem;
    }
    .alert {
      padding: 0.65rem;
      margin-bottom: 0.65rem;
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    .alert .msg-icon {
      flex-shrink: 0;
    }
    .alert .msg-body {
      flex: 1;
      min-width: 0;
      overflow: hidden;
    }

    .alert h4 {
      font-size: 0.8rem;
      margin: 0 0 0.35rem 0;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    .alert p, .alert ul {
      font-size: 0.75rem;
      margin: 0;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }
    .alert ul {
      padding-left: 1rem;
    }
    .login-link {
      font-size: 0.85rem;
      margin-top: 0.5rem;
    }
    .field-error {
      color: #ff4444;
      font-size: 11px;
      margin-top: 4px;
      display: none;
    }
    .input-error {
      border: 1px solid #ff4444 !important;
    }
    .input-valid {
      border: 1px solid #4CAF50 !important;
    }
    .password-wrapper {
      position: relative;
    }
    .password-wrapper input {
      width: 100%;
    }
    .password-strength {
      height: 3px;
      background: #e4e7ec;
      border-radius: 2px;
      margin-top: 6px;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }
    .strength-weak { width: 33%; background: #ff4444; }
    .strength-medium { width: 66%; background: #ff9800; }
    .strength-strong { width: 100%; background: #4CAF50; }
    .password-hint {
      font-size: 10px;
      color: #667085;
      margin-top: 5px;
      opacity: 0;
      transform: translateY(-5px);
      transition: all 0.2s ease;
    }
    .password-wrapper:focus-within .password-hint {
      opacity: 1;
      transform: translateY(0);
    }
    .show-password {
      position: absolute;
      right: 10px;
      top: 11px;
      background: none;
      border: none;
      color: #667085;
      cursor: pointer;
      font-size: 13px;
      padding: 5px;
      border-radius: 4px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .show-password:hover {
      background: #f1f4fa;
      color: #1f7bff;
    }
    .show-password svg {
      width: 16px;
      height: 16px;
    }
  </style>
</head>

<body class="register-page">

<div class="register-box">

    <h2>Create a new account</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error" role="alert" style="margin-bottom:12px;">
                <!-- <div class="msg-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 9v4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 17h.01" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div> -->
                <div class="msg-body">
                    <!-- <h4>Please correct the following errors:</h4> -->
                    <p><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                </div>
            </div>
        <?php endif; ?>

    <form action="register_process.php" method="POST">

        <label class="label-small">Full Name</label>
        <div class="name-fields" style="display:flex;gap:10px; margin-bottom:12px;">
            <div style="flex:1;">
                <input type="text" name="fname" placeholder="First name *" required style="width:100%">
            </div>
            <div style="flex:1;">
                <input type="text" name="mname" placeholder="Middle name" style="width:100%">
            </div>
            <div style="flex:1;">
                <input type="text" name="lname" placeholder="Last name *" required style="width:100%">
            </div>
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

        <!-- <label class="label-small">Gender</label>
        <div class="gender-row">
            <label class="gender-box">
                Female <input type="radio" name="gender" value="female" required>
            </label>
            <label class="gender-box">
                Male <input type="radio" name="gender" value="male" required>
            </label>
            <label class="gender-box">
                Custom <input type="radio" name="gender" value="notsay" required>
            </label>
        </div> -->
        

        <label class="label-small">Display Name</label>
        <input type="text" name="display_name" placeholder="Display Name (5-15 characters)" required>

        <label class="label-small">Email</label>
        <input type="email" name="email" placeholder="Email address" required>

        <label class="label-small">Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" placeholder="Password" required style="padding-right: 50px;">
            <button type="button" class="show-password" id="toggle-password" title="Show password">
                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <svg id="eye-slash-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
            </button>
            <div class="password-strength">
                <div class="password-strength-bar" id="strength-bar"></div>
            </div>
            <div class="password-hint" id="password-hint">
                Use 8+ characters with uppercase, lowercase, number & symbol
            </div>
        </div>
        <div style="position: relative;">
            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password" required>
            <span class="field-error" id="confirm-error">Passwords do not match</span>
        </div>
       

        <button type="submit" class="signup-btn">Register</button>

        <?php if (!empty($_SESSION['errors'])): ?>
            <div class="alert alert-error" role="alert" style="margin-top:12px;">
                <div class="msg-icon" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 9v4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 17h.01" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="12" r="9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="msg-body">
                    <!-- <h4>Please correct the following errors:</h4> -->
                    <ul>
                        <?php foreach($_SESSION['errors'] as $err){ echo '<li>' . htmlspecialchars($err) . '</li>'; } unset($_SESSION['errors']); ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
        

        
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success" role="status" style="margin-top:12px;">
                <!-- <div class="msg-icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div> -->
                <div class="msg-body">
                    <h4>Registration Successful!</h4>
                    <div class="success-content">
                        <?php echo nl2br(htmlspecialchars($_SESSION['success'])); unset($_SESSION['success']); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </form>

    <p class="login-link"><a href="login.php">Already have an account?</a></p>

</div>

<script>
(function() {
    // Password strength validation
    function validatePassword() {
        const password = document.getElementById('password').value;
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strength-bar');
        
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
        };
        
        const metCount = Object.values(requirements).filter(val => val === true).length;
        const isValid = metCount === 5;
        
        // Update strength bar
        strengthBar.className = 'password-strength-bar';
        if (password.length === 0) {
            strengthBar.className = 'password-strength-bar';
        } else if (metCount <= 2) {
            strengthBar.className = 'password-strength-bar strength-weak';
        } else if (metCount <= 4) {
            strengthBar.className = 'password-strength-bar strength-medium';
        } else {
            strengthBar.className = 'password-strength-bar strength-strong';
        }
        
        // Update input border
        if (password.length > 0) {
            if (isValid) {
                passwordInput.classList.remove('input-error');
                passwordInput.classList.add('input-valid');
            } else {
                passwordInput.classList.remove('input-valid');
                passwordInput.classList.add('input-error');
            }
        } else {
            passwordInput.classList.remove('input-error', 'input-valid');
        }
        
        return isValid;
    }
    
    // Toggle password visibility
    document.getElementById('toggle-password').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        const eyeSlashIcon = document.getElementById('eye-slash-icon');
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        
        if (type === 'password') {
            eyeIcon.style.display = 'block';
            eyeSlashIcon.style.display = 'none';
            this.setAttribute('title', 'Show password');
        } else {
            eyeIcon.style.display = 'none';
            eyeSlashIcon.style.display = 'block';
            this.setAttribute('title', 'Hide password');
        }
    });

    // Confirm password validation
    function validateConfirmPassword() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const errorSpan = document.getElementById('confirm-error');
        const confirmInput = document.getElementById('confirm_password');
        
        if (confirmPassword && password !== confirmPassword) {
            errorSpan.style.display = 'block';
            confirmInput.classList.add('input-error');
            return false;
        } else {
            errorSpan.style.display = 'none';
            confirmInput.classList.remove('input-error');
            return true;
        }
    }
    
    // Attach event listeners
    document.getElementById('password').addEventListener('input', validatePassword);
    document.getElementById('confirm_password').addEventListener('input', validateConfirmPassword);
})();
</script>

</body>
</html>
