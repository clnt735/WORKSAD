<?php
session_start();
require_once __DIR__ . '/../database.php';
include 'navbar.php';

// require authentication
if (empty($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = 'SELECT user_email FROM user WHERE user_id = ? LIMIT 1';
$stmt = $conn->prepare($sql);
if ($stmt === false) {
  error_log("settings.php prepare failed: " . $conn->error . " -- SQL: " . $sql);
  $res = $conn->query("SELECT user_email FROM user WHERE user_id = " . (int)$user_id . " LIMIT 1");
  $me = $res ? $res->fetch_assoc() : ['user_email'=>''];
} else {
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
  $me = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// Include navbar

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Account Settings - WorkMuna Employer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        box-sizing: border-box;
    }

    body.employer-settings {
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fb;
        color: #1a1a2e;
        min-height: 100vh;
    }

    .settings-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 2rem 1.5rem 3rem;
    }

    .settings-layout {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .settings-main h2 {
        font-size: 1.75rem;
        font-weight: 600;
        color: #1a1a2e;
        margin: 0 0 1.5rem 0;
    }

    .card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 1rem;
        color: #1a1a2e;
    }

    .card-header i {
        color: #1f7bff;
        font-size: 1.1rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    .card-body label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: #4a5568;
        margin-bottom: 0.5rem;
        margin-top: 1rem;
    }

    .card-body label:first-child {
        margin-top: 0;
    }

    .card-body input[type="email"],
    .card-body input[type="password"],
    .card-body input[type="text"] {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #1a1a2e;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .card-body input:focus {
        outline: none;
        border-color: #1f7bff;
        box-shadow: 0 0 0 3px rgba(31, 123, 255, 0.1);
    }

    .card-body input::placeholder {
        color: #a0aec0;
    }

    .field.readonly {
        padding: 0.75rem 1rem;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.95rem;
        color: #64748b;
    }

    .otp-row {
        margin-top: 1rem;
        padding: 1rem;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px dashed #cbd5e1;
    }

    .otp-row label {
        margin-top: 0 !important;
    }

    .card-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.7rem 1.25rem;
        font-size: 0.9rem;
        font-weight: 500;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn.secondary,
    .btn:not(.btn-primary) {
        background: #1f7bff;
        color: #fff;
    }

    .btn.secondary:hover,
    .btn:not(.btn-primary):hover {
        background: #1a6de0;
    }

    .btn-primary {
        background: #1f7bff;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1a6de0;
    }

    .btn:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
    }

    .form-message {
        margin-top: 1rem;
        font-size: 0.875rem;
        color: #4a5568;
    }

    .form-message:empty {
        display: none;
    }

    @media (max-width: 600px) {
        .settings-container {
            padding: 1rem;
        }

        .settings-main h2 {
            font-size: 1.5rem;
        }

        .card-header {
            padding: 0.875rem 1rem;
        }

        .card-body {
            padding: 1rem;
        }

        .card-actions {
            flex-direction: column;
        }

        .btn {
            width: 100%;
        }
    }
    </style>
</head>
<body class="employer-settings">

<main class="settings-container">
  <div class="settings-layout">

    <section class="settings-main">
      <h2>Account Settings</h2>

      <div class="card">
        <div class="card-header"><i class="fa-solid fa-envelope"></i> Change Email Address</div>
        <div class="card-body">
          <label>Current Email</label>
          <div id="currentEmail" class="field readonly">
            <?php echo e($me['user_email'] ?? $_SESSION['user_email'] ?? ''); ?>
        </div>


          <label>New Email Address</label>
          <input id="newEmail" type="email" placeholder="Enter new email address">

          <div class="otp-row" id="emailOtpRow" style="display:none">
            <label>Verification Code</label>
            <input id="emailOtp" type="text" maxlength="6" placeholder="6-digit code">
          </div>

          <div class="card-actions">
            <button id="sendEmailOtpBtn" class="btn secondary">Send Verification</button>
            <button id="verifyEmailBtn" class="btn" style="display:none">Verify & Save</button>
          </div>

          <div id="emailMsg" class="form-message"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><i class="fa-solid fa-lock"></i> Change Password</div>
        <div class="card-body">
          <label>Current Password</label>
          <input id="currentPass" type="password" placeholder="Enter current password">

          <label>New Password</label>
          <input id="newPass" type="password" placeholder="Enter new password">

          <label>Confirm New Password</label>
          <input id="confirmPass" type="password" placeholder="Confirm new password">

          <div id="passReq" class="form-message" style="color:#b91c1c; display:none; margin-top:6px; font-size:13px"></div>

          <div class="card-actions">
            <button id="changePassBtn" class="btn btn-primary">Update Password</button>
          </div>

          <div id="passMsg" class="form-message"></div>
        </div>
      </div>

    </section>
  </div>
</main>








<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.settings-nav ul li').forEach(li=>{
    li.addEventListener('click', ()=>{
      document.querySelectorAll('.settings-nav ul li').forEach(x=>x.classList.remove('active'));
      li.classList.add('active');
      // tabs content omitted for brevity; only account tab built
    });
  });

  // email change flow
  const sendEmailBtn = document.getElementById('sendEmailOtpBtn');
  const verifyEmailBtn = document.getElementById('verifyEmailBtn');
  const emailOtpRow = document.getElementById('emailOtpRow');
  const emailMsg = document.getElementById('emailMsg');

  sendEmailBtn.addEventListener('click', async ()=>{
    emailMsg.textContent='';
    const newEmail = document.getElementById('newEmail').value.trim();
    if (!newEmail) { emailMsg.textContent='Enter a new email address.'; return; }
    const fd = new FormData(); fd.append('action','send_email_otp'); fd.append('new_email', newEmail);
    const res = await fetch('settings_api.php', { method:'POST', body: fd });
    const j = await res.json();
    if (j.success) {
      emailMsg.textContent = j.message;
      emailOtpRow.style.display = 'block';
      verifyEmailBtn.style.display = 'inline-block';
    } else {
      emailMsg.textContent = j.message || 'Error';
    }
    if (j.debug_code) console.log('debug code:', j.debug_code);
  });

  verifyEmailBtn.addEventListener('click', async ()=>{
    emailMsg.textContent='';
    const code = document.getElementById('emailOtp').value.trim();
    if (!code) { emailMsg.textContent='Enter the verification code.'; return; }
    const fd = new FormData(); fd.append('action','verify_email_otp'); fd.append('code', code);
    const res = await fetch('settings_api.php', { method:'POST', body: fd });
    const j = await res.json();
    if (j.success) {
      emailMsg.textContent = j.message;
      // reflect new email in UI
      const newEmail = document.getElementById('newEmail').value.trim();
      const cur = document.getElementById('currentEmail');
      if (cur) cur.textContent = newEmail;
      emailOtpRow.style.display='none'; verifyEmailBtn.style.display='none';
    } else { emailMsg.textContent = j.message || 'Verification failed'; }
  });

  // password flow — direct change (no OTP)
  const changePassBtn = document.getElementById('changePassBtn');
  const passMsg = document.getElementById('passMsg');

  changePassBtn.addEventListener('click', async ()=>{
    passMsg.textContent='';
    const current = document.getElementById('currentPass').value.trim();
    const newp = document.getElementById('newPass').value;
    const conf = document.getElementById('confirmPass').value;
    if (!current) { passMsg.textContent='Enter your current password'; return; }
    if (!newp || !conf) { passMsg.textContent='Enter new password and confirmation'; return; }
    if (newp !== conf) { passMsg.textContent='Passwords do not match'; return; }

    const fd = new FormData();
    fd.append('action','change_password');
    fd.append('current_password', current);
    fd.append('new_password', newp);
    fd.append('confirm_password', conf);

    const res = await fetch('settings_api.php', { method:'POST', body: fd });
    const j = await res.json();
    if (j.success) {
      passMsg.textContent = j.message || 'Password updated';
      // clear inputs
      document.getElementById('currentPass').value='';
      document.getElementById('newPass').value='';
      document.getElementById('confirmPass').value='';
    } else {
      passMsg.textContent = j.message || 'Error';
    }
  });

  // Password strength validation
  const newPassInput = document.getElementById('newPass');
  const confirmPassInput = document.getElementById('confirmPass');
  const passReq = document.getElementById('passReq');
  const updateBtn = document.getElementById('changePassBtn');

  function checkPasswordStrength(pw){
    // At least 8 chars, 1 uppercase, 1 number, 1 special
    const re = /^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>\/?`~]).{8,}$/;
    return re.test(pw);
  }

  function updatePassUI(){
    const pw = newPassInput.value;
    const conf = confirmPassInput.value;
    if (!pw) {
      passReq.style.display='none';
      updateBtn.disabled = false;
      return;
    }
    if (!checkPasswordStrength(pw)){
      passReq.style.display='block';
      passReq.textContent = 'Password must be at least 8 characters and include 1 uppercase letter, 1 number and 1 symbol.';
      updateBtn.disabled = true;
      return;
    }
    if (conf && pw !== conf){
      passReq.style.display='block';
      passReq.textContent = 'Passwords do not match.';
      updateBtn.disabled = true;
      return;
    }
    passReq.style.display='none';
    updateBtn.disabled = false;
  }

  newPassInput.addEventListener('input', updatePassUI);
  confirmPassInput.addEventListener('input', updatePassUI);

  // ready
  document.querySelector('.settings-container').style.visibility = 'visible';
});
</script>

</body>
</html>
