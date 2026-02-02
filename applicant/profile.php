<?php
session_start();


include '../database.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }
$user_id = $_SESSION['user_id'];

// First, get basic user info
$sql = "SELECT u.user_id, up.user_profile_first_name AS first_name, up.user_profile_middle_name AS middle_name, up.user_profile_last_name AS last_name,
  up.user_profile_email_address AS email, up.user_profile_contact_no AS phone, up.user_profile_photo AS photo,
  up.user_profile_gender AS gender, up.user_profile_dob AS dob
  FROM user u LEFT JOIN user_profile up ON u.user_id = up.user_id WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param('i',$user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch location data via resume_id relationship
$user['city_mun_id'] = null;
$user['barangay_id'] = null;

// Helper: detect column
function table_has_column($conn, $table, $column) {
    $safeTable = $conn->real_escape_string($table);
    $safeCol = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'");
    return ($res && $res->num_rows > 0);
}

$resume_sql = "SELECT resume_id FROM resume WHERE user_id = ?";
$resume_stmt = $conn->prepare($resume_sql);
$resume_id = null;
if ($resume_stmt) {
    $resume_stmt->bind_param('i', $user_id);
    $resume_stmt->execute();
    $resume_row = $resume_stmt->get_result()->fetch_assoc();
    if ($resume_row) {
        $resume_id = $resume_row['resume_id'];
    }
    $resume_stmt->close();
}

$loc_anchor = table_has_column($conn, 'applicant_location', 'resume_id') ? 'resume_id' :
              (table_has_column($conn, 'applicant_location', 'user_id') ? 'user_id' : null);

if ($resume_id && $loc_anchor) {
    if ($loc_anchor === 'resume_id') {
        $loc_sql = "SELECT city_mun_id, barangay_id FROM applicant_location WHERE resume_id = ? LIMIT 1";
        $loc_stmt = $conn->prepare($loc_sql);
        if ($loc_stmt) {
            $loc_stmt->bind_param('i', $resume_id);
            $loc_stmt->execute();
            $loc_result = $loc_stmt->get_result()->fetch_assoc();
            if ($loc_result) {
                $user['city_mun_id'] = $loc_result['city_mun_id'];
                $user['barangay_id'] = $loc_result['barangay_id'];
            }
            $loc_stmt->close();
        }
    } else {
        // fallback to query by user_id
        $loc_sql = "SELECT city_mun_id, barangay_id FROM applicant_location WHERE user_id = ? LIMIT 1";
        $loc_stmt = $conn->prepare($loc_sql);
        if ($loc_stmt) {
            $loc_stmt->bind_param('i', $user_id);
            $loc_stmt->execute();
            $loc_result = $loc_stmt->get_result()->fetch_assoc();
            if ($loc_result) {
                $user['city_mun_id'] = $loc_result['city_mun_id'];
                $user['barangay_id'] = $loc_result['barangay_id'];
            }
            $loc_stmt->close();
        }
    }
}

// Fetch all municipalities
$municipalities = [];
$mun_query = $conn->query("SELECT city_mun_id, city_mun_name FROM city_mun ORDER BY city_mun_name ASC");
if ($mun_query) {
    while ($row = $mun_query->fetch_assoc()) {
        $municipalities[] = $row;
    }
}

// Fetch all barangays
$barangays = [];
$brgy_query = $conn->query("SELECT barangay_id, barangay_name, city_mun_id FROM barangay ORDER BY barangay_name ASC");
if ($brgy_query) {
    while ($row = $brgy_query->fetch_assoc()) {
        $barangays[] = $row;
    }
}

// Fetch education levels for dropdown
$education_levels = [];
$edu_query = $conn->query("SELECT education_level_id, education_level_name FROM education_level ORDER BY education_level_id ASC");
if ($edu_query) {
    while ($row = $edu_query->fetch_assoc()) {
        $education_levels[] = $row;
    }
}

// Fetch experience levels for dropdown
$experience_levels = [];
$exp_query = $conn->query("SELECT experience_level_id, experience_level_name FROM experience_level ORDER BY experience_level_id ASC");
if ($exp_query) {
    while ($row = $exp_query->fetch_assoc()) {
        $experience_levels[] = $row;
    }
}

// Fetch industries for skills dropdown and preferences
$industries = [];
$ind_query = $conn->query("SELECT industry_id, industry_name FROM industry ORDER BY industry_name ASC");
if ($ind_query) {
    while ($row = $ind_query->fetch_assoc()) {
        $industries[] = $row;
    }
}

// Fetch job types for preferences dropdown
$job_types = [];
$jt_query = $conn->query("SELECT job_type_id, job_type_name FROM job_type ORDER BY job_type_name ASC");
if ($jt_query) {
    while ($row = $jt_query->fetch_assoc()) {
        $job_types[] = $row;
    }
}

// Load resume (if exists)
$rq = $conn->prepare("SELECT * FROM resume WHERE user_id = ?");
$rq->bind_param('i',$user_id);
$rq->execute();
$resume = $rq->get_result()->fetch_assoc();

// Remove initialization from JSON columns, as data is now stored in separate tables
// Defaults
$summary = '';
$work_json = '[]';
$education_json = '[]';
$skills_json = '[]';
$achievements_json = '[]';

$fullname = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['email'] ?? 'Your Name');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Resume Builder</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
/* ---------- Theme ---------- */
:root{ --primary:#2f80ed; --accent:#10b981; --muted:#6b7280; --bg:#f4f6f9; --card:#fff; --radius:12px; }
body {  font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; background:var(--bg); margin:0; color:#111827; }
.container { max-width:1100px; margin:28px auto; padding:16px; }

/* Profile header */
.profile-card { display:flex; align-items:center; justify-content:space-between; gap:16px; background:var(--card); border-radius:var(--radius); padding:18px; box-shadow:0 6px 18px rgba(20,20,30,0.06);}
.profile-left { display:flex; gap:14px; align-items:center; }
.profile-pic { width:88px; height:88px; border-radius:50%; object-fit:cover; border:3px solid #e6eefc; background:#e9eefb; }
.profile-info h2 { margin:0; font-size:20px; }
.profile-info p { margin:0; color:var(--muted); font-size:14px; }

/* Resume builder shell */
.resume-card { margin-top:18px; background:var(--card); border-radius:var(--radius); padding:18px; box-shadow:0 6px 18px rgba(20,20,30,0.04); }
.header-row { display:flex; justify-content:space-between; align-items:center; gap:12px; }
.progress-wrap { display:flex; gap:10px; align-items:center; }
.progress { width:320px; height:10px; background:#f1f5f9; border-radius:999px; overflow:hidden; }
.progress > i { display:block; height:100%; width:70%; background:linear-gradient(90deg,var(--primary),#6eb7ff); }

/* controls */
.controls { display:flex; gap:8px; align-items:center; }

.tabs { display:flex; gap:6px; margin-top:12px; background:#f8fafc; padding:6px; border-radius:9999px; align-items:center; }
.tab { flex:1; min-width:0; padding:8px 10px; border-radius:9999px; color:var(--muted); cursor:pointer; font-weight:600; text-align:center; display:inline-flex; align-items:center; justify-content:center; font-size:13px; }
.tab.active { background:var(--primary); color:white; box-shadow:0 4px 10px rgba(47,128,237,0.12); }


/* content layout */
.section { margin-top:14px; }
.cards { display:flex; flex-direction:column; gap:12px; }
.card { background:#fff; border:1px solid #eef2ff; border-radius:10px; padding:14px; display:flex; justify-content:space-between; gap:12px; align-items:flex-start; }
.card .left { max-width:78%; }
.card .left h4 { margin:0 0 6px 0; font-size:16px; }
.card .left .meta { color:var(--muted); font-size:13px; margin-bottom:8px; }
.card .left p { margin:0; color:#374151; font-size:14px; line-height:1.45; }
.card .actions { display:flex; gap:8px; }

/* editor (add/edit) form card */
.form-card { margin-top:12px; border:2px solid #d6eaff; border-radius:10px; padding:14px; background:#fff; }
.row { display:flex; gap:12px; }
.col { flex:1; }
.field { margin-bottom:10px; }
label { display:block; font-weight:600; margin-bottom:6px; color:#111827; font-size:13px; }
input[type="text"], input[type="month"], input[type="date"], input[type="number"], textarea, select { width:100%; padding:10px; border-radius:8px; border:1px solid #e6eefc; background:#fbfdff; font-size:14px; }
textarea { min-height:110px; resize:vertical; }

/* skill tags */
.tags { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.tag { background:#e8f1ff; color:#1b4f9c; padding:6px 8px; border-radius:999px; font-weight:600; display:inline-flex; gap:8px; align-items:center; }

/* suggested row */
.suggested { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }

/* certificate upload area */
.upload-drop { border:2px dashed #e6eefc; padding:18px; border-radius:10px; text-align:center; color:var(--muted); }

/* small */
.small { font-size:13px; color:var(--muted); }

/* responsive */
@media (max-width:1023px) {
  .row { flex-direction:column; }
  .profile-pic { width:70px; height:70px; }
}
@media (max-width:600px){
  .container{ padding:12px; }
  .progress { width:160px; }
  .profile-pic { width:60px; height:60px; }
  .tabs { overflow:auto; }
}

/* ============= EDIT PROFILE MODAL ============= */
.profile-modal-overlay {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.65);
  z-index: 9998;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.profile-modal-overlay.active {
  display: block;
  opacity: 1;
}

.profile-modal {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) scale(0.9);
  width: 90%;
  max-width: 680px;
  max-height: 90vh;
  background: white;
  border-radius: 12px;
  box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
  z-index: 9999;
  overflow: hidden;
  opacity: 0;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.profile-modal.active {
  display: flex;
  flex-direction: column;
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}

.profile-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: white;
  position: sticky;
  top: 0;
  z-index: 10;
}

.profile-modal-header h2 {
  font-size: 20px;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.profile-modal-close {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #f3f4f6;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-size: 20px;
  transition: all 0.2s;
}

.profile-modal-close:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-modal-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.profile-modal-body::-webkit-scrollbar {
  width: 8px;
}

.profile-modal-body::-webkit-scrollbar-track {
  background: #f3f4f6;
}

.profile-modal-body::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 4px;
}

.profile-modal-body::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}

.profile-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 18px 20px;
  margin-bottom: 16px;
  transition: all 0.2s;
}

.profile-section:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.profile-section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 4px;
}

.profile-section-header h3 {
  font-size: 17px;
  font-weight: 600;
  color: #111827;
  margin: 0;
}

.profile-edit-icon {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #f3f4f6;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b7280;
  font-size: 14px;
  transition: all 0.2s;
}

.profile-edit-icon:hover {
  background: #e5e7eb;
  color: #2f80ed;
}

.profile-section-content {
  color: #6b7280;
  font-size: 15px;
  line-height: 1.6;
}

.profile-section-content.editing {
  display: none;
}

.profile-section-form {
  display: none;
  margin-top: 16px;
}

.profile-section-form.active {
  display: block;
}

.profile-form-group {
  margin-bottom: 14px;
}

.profile-form-group:last-child {
  margin-bottom: 0;
}

.profile-form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

.profile-form-group input,
.profile-form-group select,
.profile-form-group textarea {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 15px;
  color: #111827;
  background: white;
  transition: all 0.2s;
}

.profile-form-group input:focus,
.profile-form-group select:focus,
.profile-form-group textarea:focus {
  outline: none;
  border-color: #2f80ed;
  box-shadow: 0 0 0 3px rgba(47, 128, 237, 0.1);
}

.profile-form-group textarea {
  resize: vertical;
  min-height: 80px;
  font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
}

.profile-form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.profile-form-actions {
  display: flex;
  gap: 8px;
  margin-top: 14px;
  justify-content: flex-end;
}

.profile-form-actions button {
  padding: 8px 20px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.profile-form-cancel {
  background: #f3f4f6;
  color: #6b7280;
}

.profile-form-cancel:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-form-save {
  background: #2f80ed;
  color: white;
}

.profile-form-save:hover {
  background: #1d6fd8;
}

.profile-modal-footer {
  padding: 16px 24px;
  border-top: 1px solid #e5e7eb;
  background: #f9fafb;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.profile-modal-footer button {
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: none;
}

.profile-modal-cancel {
  background: #f3f4f6;
  color: #6b7280;
}

.profile-modal-cancel:hover {
  background: #e5e7eb;
  color: #111827;
}

.profile-modal-submit {
  background: #2f80ed;
  color: white;
}

.profile-modal-submit:hover {
  background: #1d6fd8;
}

@media (max-width: 768px) {
  .profile-modal {
    width: 95%;
    max-height: 95vh;
  }
  
  .profile-form-row {
    grid-template-columns: 1fr;
  }
}
</style>
</head>
<body class="applicant-profile">
<div class="container">


<!-- ================= MOBILE HEADER ================= -->
<header class="mobile-header">
    <h2 class="logo">WorkMuna</h2>
    
    <!-- Mobile Notification Bell -->
    <div class="wm-notification-wrapper mobile-notification">
        <button class="wm-notification-btn" id="mobileNotificationBtn" aria-label="Notifications" aria-expanded="false">
            <i class="fa-solid fa-bell"></i>
            <span class="notification-badge" id="mobileNotificationBadge" style="display: none;">0</span>
        </button>
        <div class="wm-notification-dropdown" id="mobileNotificationDropdown" aria-hidden="true">
            <div class="notification-header">
                <h3>Notifications</h3>
                <button class="notification-close" id="mobileNotificationClose">&times;</button>
            </div>
            <div class="notification-list" id="mobileNotificationList">
                <div class="notification-loading">Loading...</div>
            </div>
        </div>
    </div>
    
  <div class="header-actions">
 

      <!-- Settings icon -->
      <button id="openSettings" class="settings-btn">
          <i class="fa-solid fa-gear"></i>
      </button>
  </div>
</header>


<!-- Slide-up settings panel -->
<div class="settings-panel" id="settingsPanel">
    <div class="settings-panel-inner">


      <div class="settings-header">
        <button class="close-settings" id="closeSettings"> <i class="fa-solid fa-arrow-left"></i></button>

        <h2 class="settings-title">Settings</h2>
      </div>


        <ul class="settings-list">
            <li class="active" data-tab="account"> 
                <i class="fa-regular fa-user"></i> 
                Account Settings
            </li>

            <li data-tab="privacy"> 
                <i class="fa-regular fa-eye-slash"></i> 
                Privacy Settings
            </li>

            <li data-tab="notifications"> 
                <i class="fa-regular fa-bell"></i> 
                Notification Settings
            </li>

            <li data-tab="preferences"> 
                <i class="fa-regular fa-id-badge"></i> 
                Job Preferences
            </li>

            <li data-tab="ui"> 
                <i class="fa-regular fa-image"></i> 
                App / UI Settings
            </li>

            <li data-tab="security"> 
                <i class="fa-solid fa-shield-halved"></i> 
                Data & Security
            </li>
        </ul>

        <a href="logout.php" class="logout-link">Logout</a>
    </div>
</div>

<div class="settings-overlay" id="settingsOverlay"></div>





  <!-- ======= DESKTOP HEADER ======= -->
  <!-- ======= DESKTOP HEADER ======= -->
  <?php $activePage = 'profile'; include 'header.php'; ?>

<main>
  <div class="profile-head">
    <h1 class="profile-title">My Profile</h1>
    <p class="profile-sub">Manage your profile and resume</p>
  </div>

  <!-- Profile -->
  <div class="profile-card">
      <div class="profile-left">
        <div style="position:relative;cursor:pointer;" onclick="document.getElementById('profilePicInput').click()">
      <img class="profile-pic" id="profilePreview"
          src="<?= !empty($user['photo']) ? '../uploads/profile_pics/'.htmlspecialchars($user['photo']) : 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 150 150%22%3E%3Crect fill=%22%23e4e6eb%22 width=%22150%22 height=%22150%22/%3E%3Ccircle cx=%2275%22 cy=%2252%22 r=%2230%22 fill=%22%23bcc0c4%22/%3E%3Cellipse cx=%2275%22 cy=%22127%22 rx=%2249%22 ry=%2238%22 fill=%22%23bcc0c4%22/%3E%3C/svg%3E' ?>"
          alt="avatar">
<div style="
    position:absolute;
    bottom:0;
    right:0;
    background:#2f80ed;
    color:white;
    padding:6px 7px;
    border-radius:50%;
    font-size:13px;
    display:flex;
    align-items:center;
    justify-content:center;
">
    <i class="fa-solid fa-camera"></i>
</div>

  </div>

<input type="file" id="profilePicInput" accept="image/*" style="display:none;">

  <div class="profile-info">
      <h2><?= htmlspecialchars($fullname) ?></h2>

      <p style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
          <i class="fa-regular fa-envelope" style="color:#6b7280;"></i>
          <?= htmlspecialchars($user['email'] ?? '') ?>
      </p>

      <p class="small" style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
          <i class="fa-solid fa-phone" style="color:#6b7280;"></i>
          <?= htmlspecialchars($user['phone'] ?? 'Not set') ?>
      </p>

      <!-- Location -->
      <p class="small" style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
          <i class="fa-solid fa-location-dot" style="color:#6b7280;"></i>
          <span id="mainDisplayLocation"><?php 
            $locParts = [];
            $street = '';
            if ($resume_id && $loc_anchor) {
              $streetQuery = $conn->prepare("SELECT street FROM applicant_location WHERE resume_id = ? LIMIT 1");
              $streetQuery->bind_param('i', $resume_id);
              $streetQuery->execute();
              $streetResult = $streetQuery->get_result()->fetch_assoc();
              if (!empty($streetResult['street'])) $street = $streetResult['street'];
              $streetQuery->close();
            }
            if ($street) $locParts[] = $street;
            
            if (!empty($user['barangay_id'])) {
              $brgyQuery = $conn->query("SELECT barangay_name FROM barangay WHERE barangay_id = " . (int)$user['barangay_id']);
              if ($brgyRow = $brgyQuery->fetch_assoc()) $locParts[] = $brgyRow['barangay_name'];
            }
            
            if (!empty($user['city_mun_id'])) {
              $cityQuery = $conn->query("SELECT city_mun_name FROM city_mun WHERE city_mun_id = " . (int)$user['city_mun_id']);
              if ($cityRow = $cityQuery->fetch_assoc()) $locParts[] = $cityRow['city_mun_name'];
            }
            
            echo !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : 'Not set';
          ?></span>
      </p>

      <!-- Social Media -->
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;" id="mainDisplaySocials">
          <?php
          // Check if social columns exist
          $social_check = $conn->query("SHOW COLUMNS FROM user_profile LIKE 'facebook'");
          if ($social_check && $social_check->num_rows > 0) {
            $social_query = $conn->prepare("SELECT facebook, linkedin FROM user_profile WHERE user_id = ?");
            $social_query->bind_param('i', $user_id);
            $social_query->execute();
            $social_result = $social_query->get_result()->fetch_assoc();
            $social_query->close();
            
            if (!empty($social_result['facebook'])) {
              echo '<a href="' . htmlspecialchars($social_result['facebook']) . '" target="_blank" style="color:#1877f2;"><i class="fa-brands fa-facebook"></i></a>';
            }
            if (!empty($social_result['linkedin'])) {
              echo '<a href="' . htmlspecialchars($social_result['linkedin']) . '" target="_blank" style="color:#0a66c2;"><i class="fa-brands fa-linkedin"></i></a>';
            }
            if (empty($social_result['facebook']) && empty($social_result['linkedin'])) {
              echo '<span class="small" style="color:#9ca3af;">No social links</span>';
            }
          }
          ?>
      </div>

      <!-- Bio -->
      <p class="small" style="color:#6b7280;font-style:italic;margin-top:4px;" id="mainDisplayBio">
          <?php
            if ($resume && !empty($resume['bio'])) {
              echo htmlspecialchars($resume['bio']);
            } else {
              echo 'No bio added yet';
            }
          ?>
      </p>
  </div>

  </div>
    <div class="controls">
      <button class="editProfileBtn" onclick="openProfileModal()"><i class="fa-regular fa-pen-to-square"></i>&nbsp;Edit Profile</button>
    </div>
  </div>

  <!-- Profile Edit Modal -->
  <div class="profile-modal-overlay" id="profileModalOverlay" onclick="closeProfileModal()"></div>
  <div class="profile-modal" id="profileModal">
    <div class="profile-modal-header">
      <h2>Edit Profile</h2>
      <button class="profile-modal-close" onclick="closeProfileModal()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    
    <div class="profile-modal-body">
      <!-- Personal Details Section -->
      <div class="profile-section" id="personalSection">
        <div class="profile-section-header">
          <h3>Personal Details</h3>
          <button class="profile-edit-icon" onclick="editSection('personal')">
            <i class="fa-solid fa-pen"></i>
          </button>
        </div>
        <div class="profile-section-content" id="personalContent">
          <div><strong>Name:</strong> <span id="displayFullName"><?= htmlspecialchars($fullname) ?></span></div>
          <div><strong>Contact:</strong> <span id="displayPhone"><?= htmlspecialchars($user['phone'] ?? 'Not set') ?></span></div>
        </div>
        <div class="profile-section-form" id="personalForm">
          <div class="profile-form-row">
            <div class="profile-form-group">
              <label>First Name</label>
              <input type="text" id="modal_first" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
            </div>
            <div class="profile-form-group">
              <label>Middle Name</label>
              <input type="text" id="modal_middle" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
            </div>
          </div>
          <div class="profile-form-group">
            <label>Last Name</label>
            <input type="text" id="modal_last" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
          </div>
          <div class="profile-form-group">
            <label>Contact Number</label>
            <input type="text" id="modal_phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" maxlength="11" placeholder="09XXXXXXXXX">
          </div>
          <div class="profile-form-actions">
            <button class="profile-form-cancel" onclick="cancelEdit('personal')">Cancel</button>
            <button class="profile-form-save" onclick="saveEdit('personal')">Save</button>
          </div>
        </div>
      </div>

      <!-- Location Section -->
      <div class="profile-section" id="locationSection">
        <div class="profile-section-header">
          <h3>Location</h3>
          <button class="profile-edit-icon" onclick="editSection('location')">
            <i class="fa-solid fa-pen"></i>
          </button>
        </div>
        <div class="profile-section-content" id="locationContent">
          <span id="displayLocation"><?php 
            $locParts = [];
            // Get street first
            $street = '';
            if ($resume_id && $loc_anchor) {
              $streetQuery = $conn->prepare("SELECT street FROM applicant_location WHERE resume_id = ? LIMIT 1");
              $streetQuery->bind_param('i', $resume_id);
              $streetQuery->execute();
              $streetResult = $streetQuery->get_result()->fetch_assoc();
              if (!empty($streetResult['street'])) $street = $streetResult['street'];
              $streetQuery->close();
            }
            if ($street) $locParts[] = $street;
            
            // Get barangay
            if (!empty($user['barangay_id'])) {
              $brgyQuery = $conn->query("SELECT barangay_name FROM barangay WHERE barangay_id = " . (int)$user['barangay_id']);
              if ($brgyRow = $brgyQuery->fetch_assoc()) $locParts[] = $brgyRow['barangay_name'];
            }
            
            // Get city
            if (!empty($user['city_mun_id'])) {
              $cityQuery = $conn->query("SELECT city_mun_name FROM city_mun WHERE city_mun_id = " . (int)$user['city_mun_id']);
              if ($cityRow = $cityQuery->fetch_assoc()) $locParts[] = $cityRow['city_mun_name'];
            }
            
            echo !empty($locParts) ? htmlspecialchars(implode(', ', $locParts)) : 'Not set';
          ?></span>
        </div>
        <div class="profile-section-form" id="locationForm">
          <div class="profile-form-group">
            <label>Municipality</label>
            <select id="modal_municipality">
              <option value="">Select Municipality</option>
              <?php foreach($municipalities as $mun): ?>
              <option value="<?= $mun['city_mun_id'] ?>" <?= ($user['city_mun_id'] == $mun['city_mun_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($mun['city_mun_name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="profile-form-group">
            <label>Barangay</label>
            <select id="modal_barangay" <?= empty($user['city_mun_id']) ? 'disabled' : '' ?>>
              <option value="">Select Barangay</option>
              <?php 
              if (!empty($user['city_mun_id'])) {
                foreach($barangays as $brgy) {
                  if ($brgy['city_mun_id'] == $user['city_mun_id']) {
                    echo '<option value="' . $brgy['barangay_id'] . '"' . 
                         (($user['barangay_id'] == $brgy['barangay_id']) ? ' selected' : '') . '>' .
                         htmlspecialchars($brgy['barangay_name']) . '</option>';
                  }
                }
              }
              ?>
            </select>
          </div>
          <div class="profile-form-group">
            <label>House No. / Street (Optional)</label>
            <input type="text" id="modal_street" value="<?php
              if ($resume_id && $loc_anchor) {
                $streetQuery = $conn->prepare("SELECT street FROM applicant_location WHERE resume_id = ? LIMIT 1");
                $streetQuery->bind_param('i', $resume_id);
                $streetQuery->execute();
                $streetResult = $streetQuery->get_result()->fetch_assoc();
                echo htmlspecialchars($streetResult['street'] ?? '');
                $streetQuery->close();
              }
            ?>" placeholder="e.g., 123 Main Street">
          </div>
          <div class="profile-form-actions">
            <button class="profile-form-cancel" onclick="cancelEdit('location')">Cancel</button>
            <button class="profile-form-save" onclick="saveEdit('location')">Save</button>
          </div>
        </div>
      </div>

      <!-- Socials Section -->
      <div class="profile-section" id="socialsSection">
        <div class="profile-section-header">
          <h3>Social Media</h3>
          <button class="profile-edit-icon" onclick="editSection('socials')">
            <i class="fa-solid fa-pen"></i>
          </button>
        </div>
        <div class="profile-section-content" id="socialsContent">
          <div id="displaySocials">No social media links added</div>
        </div>
        <div class="profile-section-form" id="socialsForm">
          <div class="profile-form-group">
            <label><i class="fa-brands fa-facebook"></i> Facebook Profile URL</label>
            <input type="url" id="modal_facebook" placeholder="https://facebook.com/yourprofile">
          </div>
          <div class="profile-form-group">
            <label><i class="fa-brands fa-linkedin"></i> LinkedIn Profile URL</label>
            <input type="url" id="modal_linkedin" placeholder="https://linkedin.com/in/yourprofile">
          </div>
          <div class="profile-form-actions">
            <button class="profile-form-cancel" onclick="cancelEdit('socials')">Cancel</button>
            <button class="profile-form-save" onclick="saveEdit('socials')">Save</button>
          </div>
        </div>
      </div>

      <!-- Personal Summary Section -->
      <div class="profile-section" id="summarySection">
        <div class="profile-section-header">
          <h3>About Me</h3>
          <button class="profile-edit-icon" onclick="editSection('summary')">
            <i class="fa-solid fa-pen"></i>
          </button>
        </div>
        <div class="profile-section-content" id="summaryContent">
          <span id="displayBio"><?php
            if ($resume && !empty($resume['bio'])) {
              echo htmlspecialchars($resume['bio']);
            } else {
              echo 'Tell us about yourself...';
            }
          ?></span>
        </div>
        <div class="profile-section-form" id="summaryForm">
          <div class="profile-form-group">
            <label>Bio / About Me</label>
            <textarea id="modal_bio" rows="5" placeholder="Write a brief introduction about yourself..."><?= htmlspecialchars($resume['bio'] ?? '') ?></textarea>
          </div>
          <div class="profile-form-actions">
            <button class="profile-form-cancel" onclick="cancelEdit('summary')">Cancel</button>
            <button class="profile-form-save" onclick="saveEdit('summary')">Save</button>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-modal-footer">
      <button class="profile-modal-cancel" onclick="closeProfileModal()">Close</button>
      <button class="profile-modal-submit" onclick="saveAllProfileChanges()">Save All Changes</button>
    </div>
  </div>

  <!-- Profile edit hidden (OLD - kept for compatibility but hidden) -->
  <div id="profileEdit" style="display:none;">
      <div class="row">
        <div class="col">
          <label>First name</label>
          <input id="p_first" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
        </div>
        <div class="col">
          <label>Middle name</label>
          <input id="p_middle" value="<?= htmlspecialchars($user['middle_name'] ?? '') ?>">
        </div>
        <div class="col">
          <label>Last name</label>
          <input id="p_last" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
        </div>
      </div>

      <div style="margin-top:10px;">
          <label>Phone</label>
          <input id="p_phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      </div>

      <div class="row" style="margin-top:10px;">
        <div class="col">
          <label>Municipality</label>
          <select id="p_municipality">
            <option value="">Select Municipality</option>
            <?php foreach ($municipalities as $mun): ?>
              <option value="<?= $mun['city_mun_id'] ?>" <?= ($user['city_mun_id'] ?? '') == $mun['city_mun_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($mun['city_mun_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col">
          <label>Barangay</label>
          <select id="p_barangay" <?= empty($user['city_mun_id']) ? 'disabled' : '' ?>>
            <option value="">Select Barangay</option>
            <?php 
            if (!empty($user['city_mun_id'])) {
              foreach ($barangays as $brgy) {
                if ($brgy['city_mun_id'] == $user['city_mun_id']) {
                  echo '<option value="' . $brgy['barangay_id'] . '" ' . 
                       (($user['barangay_id'] ?? '') == $brgy['barangay_id'] ? 'selected' : '') . '>' . 
                       htmlspecialchars($brgy['barangay_name']) . '</option>';
                }
              }
            }
            ?>
          </select>
        </div>
      </div>

      <div class="savecancelButton">
        <button class="cancelBtn" onclick="cancelProfile()">Cancel</button>
        <button class="saveBtn" onclick="saveProfile()">Save Changes</button>
      </div>
  </div>


  <!-- Resume builder -->
  <div class="resume-card">
    <div class="header-row">
      <div>
        <div><strong>Resume Builder</strong></div>
        <div class="progress-wrap">
          <div class="progress"><i></div>
          <div class="small">Complete</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn" id="downloadResumeBtn" style="background:var(--accent);color:white;"><i class="fa-solid fa-download"></i>&nbsp;Download Resume</button>
      </div>
    </div>

    <div class="tabs" role="tablist" style="margin-top:12px;">
      <div class="tab active" data-tab="work">Work Experience 
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </div>
      
      <div class="tab" data-tab="education">Education
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </div>

      <div class="tab" data-tab="skills">Skills
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </div>

      <div class="tab" data-tab="achievements">Achievements
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </div>

      <div class="tab" data-tab="preferences">Preferences
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </div>
    </div>

    <!-- WORK -->
    <div class="section" id="panel-work" style="display:block;">
      <div class="cards" id="workCards"></div>



      <!-- Work form card (hidden until add/edit) -->
      <div id="workFormCard" class="form-card" style="display:none;">

        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="workFormTitle">Add Work Experience</strong>
        </div>

        <div class="row" style="margin-top:12px;">
          <div class="col field">
            <label>Job Title *</label>
            <input id="w_job" type="text" placeholder="e.g., Senior Product Designer">
          </div>
          <div class="col field">
            <label>Company </label>
            <input id="w_company" type="text" placeholder="e.g., Tech Innovations Inc.">
          </div>
        </div>

        <div class="row">
          <div class="col field">
            <label>Experience Level *</label>
            <select id="w_experience_level">
              <option value="">Select Experience Level</option>
              <?php foreach ($experience_levels as $level): ?>
              <option value="<?= $level['experience_level_id'] ?>"><?= htmlspecialchars($level['experience_level_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col field"></div>
        </div>

        <div class="row">
          <div class="col field">
            <label>Start Date</label>
            <input id="w_start" type="month">
          </div>
          <div class="col field">
            <label>End Date</label>
            <input id="w_end" type="month">
          </div>
        </div>

        <div class="field">
          <label>Description</label>
          <textarea id="w_desc" placeholder="Describe your responsibilities and role..."></textarea>
        </div>

        <div class="work-save-cancel-btn">
            <button class="cancelWorkBtn" onclick="cancelWorkForm()">Cancel</button>
            <button class="saveWorkBtn" id="saveWorkBtn">Save</button>
        </div>
      </div>



      <div style="margin-top:12px;">
        <button class="btn ghost" id="addWorkBtn"><i class="fa-solid fa-plus"></i>&nbsp; Add Work Experience</button>
      </div>
    </div>

    <!-- EDUCATION -->
    <div class="section" id="panel-education" style="display:none;">
      <div class="cards" id="eduCards"></div>

      <div id="eduFormCard" class="form-card" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="eduFormTitle">Add Education</strong>
        </div>

        <div class="row" style="margin-top:12px;">
          <div class="col field">
            <label>Education Level *</label>
            <select id="e_education_level">
              <option value="">Select Education Level</option>
              <?php foreach ($education_levels as $level): ?>
              <option value="<?= $level['education_level_id'] ?>"><?= htmlspecialchars($level['education_level_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col field"><label>Institution *</label><input id="e_institution" type="text" placeholder="e.g., Stanford University"></div>
        </div>

        <div class="row">
          <div class="col field"><label>Start Date</label><input id="e_start" type="month" placeholder="YYYY-MM"></div>
          <div class="col field"><label>End Date</label><input id="e_end" type="month" placeholder="YYYY-MM"></div>
        </div>

        <div class="edu-save-cancel-btn">
          <button class="cancelEduBtn" onclick="cancelEduForm()">Cancel</button>
          <button class="saveEduBtn" id="saveEduBtn">Save</button>
        </div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn ghost" id="addEduBtn"><i class="fa-solid fa-plus"></i>&nbsp; Add Education</button>
      </div>

    </div>




    <!-- SKILLS -->
    <div class="section" id="panel-skills" style="display:none;">
      <div style="max-width:920px;">
        <h3 style="margin:0 0 16px 0;font-size:18px;">Skills</h3>
        
        <!-- Skills list -->
        <div class="cards" id="skillCards"></div>
        
        <!-- Skills form card -->
        <div id="skillFormCard" class="form-card" style="display:none;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <strong id="skillFormTitle">Add Skill</strong>
          </div>
          
          <div class="row" style="margin-top:12px;">
            <div class="col field">
              <label>Industry *</label>
              <select id="s_industry">
                <option value="">Select Industry</option>
                <?php foreach ($industries as $ind): ?>
                <option value="<?= $ind['industry_id'] ?>"><?= htmlspecialchars($ind['industry_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col field">
              <label>Category *</label>
              <select id="s_category" disabled>
                <option value="">Select Category</option>
              </select>
            </div>
          </div>
          
          <div class="row">
            <div class="col field">
              <label>Skill *</label>
              <select id="s_skill" disabled>
                <option value="">Select Skill</option>
              </select>
            </div>
            <div class="col field"></div>
          </div>
          
          <div class="skill-save-cancel-btn">
            <button class="cancelSkillBtn" onclick="cancelSkillForm()">Cancel</button>
            <button class="saveSkillBtn" id="saveSkillBtn">Save</button>
          </div>
        </div>
        
        <div style="margin-top:12px;">
          <button class="btn ghost" id="addSkillBtn"><i class="fa-solid fa-plus"></i>&nbsp; Add Skill</button>
        </div>
      </div>
    </div>



    <!-- ACHIEVEMENTS (certifications) -->
    <div class="section" id="panel-achievements" style="display:none;">
      <div class="cards" id="certCards"></div>

      <div id="certFormCard" class="form-card" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="certFormTitle">Add Achievement</strong>
        </div>

        <div class="row" style="margin-top:12px;">
          <div class="col field"><label>Title *</label><input id="c_title" type="text" placeholder="e.g., Certified UX Professional"></div>
          <div class="col field"><label>Issuing Organization *</label><input id="c_org" type="text" placeholder="e.g., Nielsen Norman Group"></div>
        </div>

        <div class="row">
          <div class="col field"><label>Date Received</label><input id="c_date" type="date"></div>
          <div class="col field"></div>
        </div>

        <div class="field"><label>Description (Optional)</label><textarea id="c_desc" placeholder="Brief description..."></textarea></div>

        <div class="field">
          <label>Upload Certificate (Optional)</label>
          <div class="upload-drop">
            <input id="c_file" type="file" accept=".pdf,image/*">
            <div class="small">PDF or image, up to 5MB</div>
          </div>
        </div>

        <div class="cert-save-cancel-btn">
          <button class="cancelCertBtn" onclick="cancelCertForm()">Cancel</button>
          <button class="saveCertBtn" id="saveCertBtn">Save</button>
        </div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn ghost" id="addCertBtn"><i class="fa-solid fa-plus"></i>&nbsp; Add Achievement</button>
      </div>
    </div>

    <!-- PREFERENCES -->
    <div class="section" id="panel-preferences" style="display:none;">
      <div class="cards" id="prefCards"></div>

      <div id="prefFormCard" class="form-card" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <strong id="prefFormTitle">Add Preference</strong>
        </div>

        <div class="row" style="margin-top:12px;">
          <div class="col field">
            <label>Preferred Job Type</label>
            <select id="pref_job_type">
              <option value="">Select Job Type</option>
            </select>
          </div>
          <div class="col field">
            <label>Preferred Industry</label>
            <select id="pref_industry">
              <option value="">Select Industry</option>
            </select>
          </div>
        </div>

        <div class="pref-save-cancel-btn">
          <button class="cancelPrefBtn" onclick="cancelPrefForm()">Cancel</button>
          <button class="savePrefBtn" id="savePrefBtn">Save</button>
        </div>
      </div>

      <div style="margin-top:12px;">
        <button class="btn ghost" id="addPrefBtn"><i class="fa-solid fa-plus"></i>&nbsp; Add Preference</button>
      </div>
    </div>





      <div style="margin-top:12px;color:var(--muted)" id="autosaveText">Auto-saving your changes...</div>

    </div>
</div>
</main>


<!-- ================= MOBILE BOTTOM NAV ================= -->
<nav class="bottom-nav">
  <a href="search_jobs.php" class="<?= ($activePage==='job') ? 'active' : '' ?>">
    <span class="material-symbols-outlined">home</span>
    Home 
  </a>

  <a href="application.php" class="<?= ($activePage==='myapplications') ? 'active' : '' ?>">
    <span class="material-symbols-outlined">description</span>
    Applications
  </a>

  <a href="interactions.php" class="<?= ($activePage==='interactions') ? 'active' : '' ?>">
    <span class="material-symbols-outlined">bookmark</span>
    Interactions
  </a>

  <a href="companies.php" class="<?= ($activePage==='companies') ? 'active' : '' ?>">
    <span class="material-symbols-outlined">business</span>
    Company
  </a>

  <a href="profile.php" class="<?= ($activePage==='profile') ? 'active' : '' ?>">
    <span class="material-symbols-outlined">person</span>
    Profile
  </a>
</nav>















<script>
/* ---------- Initialization ---------- */
const userId = <?= json_encode($user_id) ?>;
let workData = <?= json_encode(json_decode($work_json, true) ?: []) ?>;
let eduData  = <?= json_encode(json_decode($education_json, true) ?: []) ?>;
let skillsData = <?= json_encode(json_decode($skills_json, true) ?: []) ?>;
let certData = <?= json_encode(json_decode($achievements_json, true) ?: []) ?>;
<?php
  // Initialize preferences with correct field names (will be overwritten by loadResumeData)
  $initial_prefs = [
    'job_type_id' => null,
    'industry_id' => null
  ];
?>
let preferencesData = <?= json_encode($initial_prefs) ?>;
let originalProfile = {};
let jobTypes = <?= json_encode($job_types) ?>; 
let industries = <?= json_encode($industries) ?>; 

// Barangay data for filtering
const allBarangays = <?= json_encode($barangays) ?>;

// DOM references
const workCards = document.getElementById('workCards');
const eduCards = document.getElementById('eduCards');
const certCards = document.getElementById('certCards');
const prefCards = document.getElementById('prefCards');
const autosaveText = document.getElementById('autosaveText');

// Tabs
document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', (e) => {
  document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
  t.classList.add('active');
  const tab = t.dataset.tab;
  document.querySelectorAll('.section').forEach(s=>s.style.display='none');
  document.getElementById('panel-'+tab).style.display='block';
}));

// Municipality change handler - filter barangays
const municipalitySelect = document.getElementById('p_municipality');
const barangaySelect = document.getElementById('p_barangay');

if (municipalitySelect && barangaySelect) {
  municipalitySelect.addEventListener('change', function() {
    const selectedMunId = this.value;
    
    // Clear and disable barangay if no municipality selected
    if (!selectedMunId) {
      barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
      barangaySelect.disabled = true;
      return;
    }
    
    // Enable and populate barangay dropdown
    barangaySelect.disabled = false;
    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    
    // Filter barangays by selected municipality
    const filteredBarangays = allBarangays.filter(brgy => brgy.city_mun_id == selectedMunId);
    
    filteredBarangays.forEach(brgy => {
      const option = document.createElement('option');
      option.value = brgy.barangay_id;
      option.textContent = brgy.barangay_name;
      barangaySelect.appendChild(option);
    });
  });
}


/* ---------- Render functions ---------- */
function renderWork(){
  workCards.innerHTML = '';
  if(!workData.length) workCards.innerHTML = '<div class="small" style="padding:8px 0;">No work experience yet.</div>';
  workData.forEach((w, idx)=>{
    const div = document.createElement('div'); div.className='card';
    const expLevelName = w.experience_level_name || getExperienceLevelName(w.experience_level_id) || '';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(w.job_title || '')}</h4>
      <div class="meta">${escapeHtml(w.company_name || '')} ${expLevelName ? '• ' + escapeHtml(expLevelName) : ''} • ${escapeHtml(w.start_date||'')} - ${escapeHtml(w.end_date||'')}</div>
      <p>${escapeHtml(w.description || '')}</p>
    </div>
    <div class="actions">
      <button class="editWorkBtn" onclick="editWork(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteWorkBtn" onclick="deleteWork(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    workCards.appendChild(div);
  });
}

// Experience levels lookup for rendering
const experienceLevels = <?= json_encode($experience_levels) ?>;
function getExperienceLevelName(id) {
  const level = experienceLevels.find(l => l.experience_level_id == id);
  return level ? level.experience_level_name : '';
}

// Education levels lookup for rendering
const educationLevels = <?= json_encode($education_levels) ?>;
function getEducationLevelName(id) {
  const level = educationLevels.find(l => l.education_level_id == id);
  return level ? level.education_level_name : '';
}

function renderEdu(){
  eduCards.innerHTML = '';
  if(!eduData.length) eduCards.innerHTML = '<div class="small" style="padding:8px 0;">No education yet.</div>';
  eduData.forEach((e, idx)=>{
    const div = document.createElement('div'); div.className='card';
    // Get education level name from ID or use existing name
    const levelName = e.education_level_name || getEducationLevelName(e.education_level_id) || '';
    const schoolName = e.school_name || e.institution || '';
    const startDate = e.start_date || e.start_year || '';
    const endDate = e.end_date || e.end_year || '';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(levelName)}</h4>
      <div class="meta">${escapeHtml(schoolName)} • ${escapeHtml(startDate)} - ${escapeHtml(endDate)}</div>
    </div>
    <div class="actions">
      <button class="editEduBtn" onclick="editEdu(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteEduBtn" onclick="deleteEdu(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    eduCards.appendChild(div);
  });
}


// Industries data for cascading dropdowns
const industriesData = <?= json_encode($industries) ?>;

function renderSkills(){
  const skillCards = document.getElementById('skillCards');
  if(!skillCards) return;
  skillCards.innerHTML = '';
  if(!skillsData.length) {
    skillCards.innerHTML = '<div class="small" style="padding:8px 0;">No skills yet.</div>';
    return;
  }
  skillsData.forEach((s, idx)=>{
    const div = document.createElement('div'); div.className='card';
    // Handle both object format and legacy string format
    if(typeof s === 'object') {
      const industryName = s.industry_name || '';
      const categoryName = s.category_name || s.job_category_name || '';
      const skillName = s.skill_name || '';
      div.innerHTML = `<div class="left">
        <h4>${escapeHtml(skillName)}</h4>
        <div class="meta">${escapeHtml(industryName)} • ${escapeHtml(categoryName)}</div>
      </div>
      <div class="actions">
        <button class="editSkillBtn" onclick="editSkill(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
        <button class="deleteSkillBtn" onclick="deleteSkill(${idx})"><i class="far fa-trash-alt"></i></button>
      </div>`;
    } else {
      // Legacy string format
      div.innerHTML = `<div class="left">
        <h4>${escapeHtml(s)}</h4>
      </div>
      <div class="actions">
        <button class="deleteSkillBtn" onclick="deleteSkill(${idx})"><i class="far fa-trash-alt"></i></button>
      </div>`;
    }
    skillCards.appendChild(div);
  });
}


function renderCerts(){
  certCards.innerHTML = '';
  if(!certData.length) certCards.innerHTML = '<div class="small" style="padding:8px 0;">No achievements yet.</div>';
  certData.forEach((c, idx)=>{
    const div = document.createElement('div'); div.className='card';
    div.innerHTML = `<div class="left">
      <h4>${escapeHtml(c.title || '')}</h4>
      <div class="meta">${escapeHtml(c.organization || '')} • ${escapeHtml(c.date_received || '')}</div>
      <p>${escapeHtml(c.description || '')}</p>
    </div>
    <div class="actions">
      ${c.file ? `<a class="btn ghost" href="${escapeHtml(c.file)}" target="_blank">View</a>` : ''}
      <button class="editCertBtn" onclick="editCert(${idx})"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deleteCertBtn" onclick="deleteCert(${idx})"><i class="far fa-trash-alt"></i></button>
    </div>`;
    certCards.appendChild(div);
  });
}


function renderPreferences(){
  if (!prefCards) return;
  prefCards.innerHTML = '';
  
  console.log('Rendering preferences:', preferencesData);
  
  // Show card if preferences exist (check for valid IDs, not just truthy values)
  const hasJobType = preferencesData && preferencesData.job_type_id && preferencesData.job_type_id !== null;
  const hasIndustry = preferencesData && preferencesData.industry_id && preferencesData.industry_id !== null;
  
  if (hasJobType || hasIndustry) {
    const div = document.createElement('div');
    div.className = 'card';
    
    // Get job type name
    const jobType = jobTypes.find(jt => jt.job_type_id == preferencesData.job_type_id);
    const jobTypeName = jobType ? jobType.job_type_name : 'Not set';
    
    // Get industry name
    const industry = industries.find(ind => ind.industry_id == preferencesData.industry_id);
    const industryName = industry ? industry.industry_name : 'Not set';
    
    div.innerHTML = `<div class="left">
      <h4>Job Preferences</h4>
      <div class="meta">Job Type: ${escapeHtml(jobTypeName)} • Industry: ${escapeHtml(industryName)}</div>
    </div>
    <div class="actions">
      <button class="editPrefBtn" onclick="editPref()"><i class="fa-regular fa-pen-to-square"></i></button>
      <button class="deletePrefBtn" onclick="deletePref()"><i class="far fa-trash-alt"></i></button>
    </div>`;
    prefCards.appendChild(div);
  } else {
    prefCards.innerHTML = '<div class="small" style="padding:8px 0;">No preferences set yet.</div>';
  }
  
  // Populate dropdowns for form
  const jobTypeSelect = document.getElementById('pref_job_type');
  if (jobTypeSelect && jobTypes.length > 0) {
    jobTypeSelect.innerHTML = '<option value="">Select Job Type</option>';
    jobTypes.forEach(jt => {
      const option = document.createElement('option');
      option.value = jt.job_type_id;
      option.textContent = jt.job_type_name;
      if (preferencesData.job_type_id == jt.job_type_id) {
        option.selected = true;
      }
      jobTypeSelect.appendChild(option);
    });
  }
  
  // Populate industry dropdown  
  const industrySelect = document.getElementById('pref_industry');
  if (industrySelect && industries.length > 0) {
    industrySelect.innerHTML = '<option value="">Select Industry</option>';
    industries.forEach(ind => {
      const option = document.createElement('option');
      option.value = ind.industry_id;
      option.textContent = ind.industry_name;
      if (preferencesData.industry_id == ind.industry_id) {
        option.selected = true;
      }
      industrySelect.appendChild(option);
    });
  }
}

/* ---------- Form actions for Preferences ---------- */
const prefForm = document.getElementById('prefFormCard');
function showPrefForm(isEdit=false){
  document.getElementById('prefFormTitle').innerText = isEdit ? 'Edit Preference' : 'Add Preference';
  prefForm.style.display = 'block';
  window.scrollTo({ top: prefForm.offsetTop - 20, behavior:'smooth' });
}

function cancelPrefForm(){ 
  prefForm.style.display='none'; 
  // Reset to current saved values
  renderPreferences();
}

const addPrefBtn = document.getElementById('addPrefBtn');
if (addPrefBtn) addPrefBtn.addEventListener('click', ()=>{
  showPrefForm(false);
});

function editPref(){
  showPrefForm(true);
}

function deletePref(){
  if(!confirm('Delete preferences?')) return;
  preferencesData = { job_type_id: null, industry_id: null };
  renderAll();
  triggerAutoSave();
}

// Store original preference values for cancel functionality
let originalPreferences = {};

// Save button handler
const savePrefBtn = document.getElementById('savePrefBtn');
if (savePrefBtn) {
  savePrefBtn.addEventListener('click', async () => {
    const jobTypeSelect = document.getElementById('pref_job_type');
    const industrySelect = document.getElementById('pref_industry');
    
    // Update preferences data (convert empty string to null)
    preferencesData.job_type_id = jobTypeSelect.value ? parseInt(jobTypeSelect.value) : null;
    preferencesData.industry_id = industrySelect.value ? parseInt(industrySelect.value) : null;
    
    console.log('Saving preferences:', preferencesData);
    
    // Update original preferences
    originalPreferences = {
      job_type_id: preferencesData.job_type_id,
      industry_id: preferencesData.industry_id
    };
    
    // Hide form and re-render
    prefForm.style.display = 'none';
    renderAll();
    
    // Save to database
    await doAutoSave();
    alert('Preferences saved successfully!');
  });
}


/* ---------- Form actions for Work ---------- */
let editingWorkIndex = -1;
const workForm = document.getElementById('workFormCard');
function showWorkForm(isEdit=false){
  document.getElementById('workFormTitle').innerText = isEdit? 'Edit Work Experience' : 'Add Work Experience';
  workForm.style.display = 'block';
  window.scrollTo({ top: workForm.offsetTop - 20, behavior:'smooth' });
}
function cancelWorkForm(){ workForm.style.display='none'; editingWorkIndex=-1; clearWorkForm(); }
function clearWorkForm(){
  document.getElementById('w_job').value=''; document.getElementById('w_company').value=''; document.getElementById('w_experience_level').value=''; document.getElementById('w_start').value=''; document.getElementById('w_end').value=''; document.getElementById('w_desc').value='';
}
const addWorkBtnEl = document.getElementById('addWorkBtn');
if(addWorkBtnEl) addWorkBtnEl.addEventListener('click', ()=>{
  editingWorkIndex = -1; clearWorkForm(); showWorkForm(false);
});

function editWork(i){
  editingWorkIndex = i;
  const w = workData[i];
  document.getElementById('w_job').value = w.job_title || '';
  document.getElementById('w_company').value = w.company_name || '';
  document.getElementById('w_experience_level').value = w.experience_level_id || '';
  document.getElementById('w_start').value = w.start_date || '';
  document.getElementById('w_end').value = w.end_date || '';
  document.getElementById('w_desc').value = w.description || '';
  showWorkForm(true);
}

function collectWorkForm(){
  const job = document.getElementById('w_job').value.trim();
  const comp = document.getElementById('w_company').value.trim();
  const expLevelId = document.getElementById('w_experience_level').value;
  if(!job || !comp) { alert('Job title and Company are required'); return null; }
  if(!expLevelId) { alert('Experience Level is required'); return null; }
  const start = document.getElementById('w_start').value || '';
  const end = document.getElementById('w_end').value || '';
  const desc = document.getElementById('w_desc').value.trim();
  
  // Get experience level name for display
  const expSelect = document.getElementById('w_experience_level');
  const expLevelName = expSelect.options[expSelect.selectedIndex].text;
  
  return { job_title: job, company_name: comp, experience_level_id: parseInt(expLevelId), experience_level_name: expLevelName, start_date: start, end_date: end, description: desc };
}

const saveWorkBtnEl = document.getElementById('saveWorkBtn');
if(saveWorkBtnEl) saveWorkBtnEl.addEventListener('click', async ()=>{
  const obj = collectWorkForm();
  if(!obj) return;
  
  // Preserve existing ID if editing
  if(editingWorkIndex >= 0 && workData[editingWorkIndex]) {
    if(workData[editingWorkIndex].id) obj.id = workData[editingWorkIndex].id;
    if(workData[editingWorkIndex].experience_id) obj.experience_id = workData[editingWorkIndex].experience_id;
  }
  
  if(editingWorkIndex >= 0) workData[editingWorkIndex] = obj;
  else workData.push(obj);
  
  // hide form & rerender
  workForm.style.display='none';
  clearWorkForm();
  editingWorkIndex = -1;
  renderAll();
  
  // Save immediately to database
  await doAutoSave();
  alert('Work experience saved successfully!');
});

function deleteWork(i){
  if(!confirm('Delete this entry?')) return;
  // if the item has an ID, attempt server delete
  const item = workData[i];
  const id = item?.id;
  if (id) {
    fetch('delete_resume_item.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ section: 'work', id: id })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert('Delete failed: ' + (data.msg || 'Unknown error'));
        console.error('Delete work error', data);
        return;
      }
      workData.splice(i,1);
      renderAll(); triggerAutoSave();
    })
    .catch(err => { console.error(err); alert('Delete failed'); });
  } else {
    // not saved on server yet: just remove locally
    workData.splice(i,1);
    renderAll();
    triggerAutoSave();
  }
}



/* ---------- Education form ---------- */
let editingEduIndex = -1;
const eduForm = document.getElementById('eduFormCard');
function showEduForm(isEdit=false){ document.getElementById('eduFormTitle').innerText = isEdit? 'Edit Education' : 'Add Education'; eduForm.style.display='block'; window.scrollTo({ top: eduForm.offsetTop - 20, behavior:'smooth' }); }
function cancelEduForm(){ eduForm.style.display='none'; editingEduIndex=-1; clearEduForm(); }
function clearEduForm(){ document.getElementById('e_education_level').value=''; document.getElementById('e_institution').value=''; document.getElementById('e_start').value=''; document.getElementById('e_end').value=''; }
const addEduBtnEl = document.getElementById('addEduBtn');
if(addEduBtnEl) addEduBtnEl.addEventListener('click', ()=>{ editingEduIndex=-1; clearEduForm(); showEduForm(false); });

function editEdu(i){
  editingEduIndex = i;
  const e = eduData[i];
  document.getElementById('e_education_level').value = e.education_level_id || '';
  document.getElementById('e_institution').value = e.school_name || e.institution || '';
  document.getElementById('e_start').value = e.start_date || e.start_year || '';
  document.getElementById('e_end').value = e.end_date || e.end_year || '';
  showEduForm(true);
}

const saveEduBtnEl = document.getElementById('saveEduBtn');
if(saveEduBtnEl) saveEduBtnEl.addEventListener('click', async ()=>{
  const educationLevelId = document.getElementById('e_education_level').value;
  const inst = document.getElementById('e_institution').value.trim();
  if(!educationLevelId || !inst){ alert('Education Level and Institution are required'); return; }
  
  // Get education level name for display
  const levelSelect = document.getElementById('e_education_level');
  const levelName = levelSelect.options[levelSelect.selectedIndex].text;
  
  // Build object with correct field names for database
  const obj = { 
    education_level_id: parseInt(educationLevelId),
    education_level_name: levelName,
    school_name: inst,
    institution: inst,
    start_date: document.getElementById('e_start').value || '', 
    end_date: document.getElementById('e_end').value || ''
  };
  
  // Preserve existing ID if editing
  if(editingEduIndex >= 0 && eduData[editingEduIndex]) {
    if(eduData[editingEduIndex].id) obj.id = eduData[editingEduIndex].id;
    if(eduData[editingEduIndex].applicant_education_id) obj.applicant_education_id = eduData[editingEduIndex].applicant_education_id;
  }
  
  if(editingEduIndex>=0) eduData[editingEduIndex] = obj; else eduData.push(obj);
  cancelEduForm(); 
  renderAll(); 
  
  // Save immediately to database
  await doAutoSave();
  alert('Education saved successfully!');
});

function deleteEdu(i){
  if(!confirm('Delete?')) return;
  const item = eduData[i];
  const id = item?.id;
  if (id) {
    fetch('delete_resume_item.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ section: 'education', id: id })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert('Delete failed: ' + (data.msg || 'Unknown error'));
        console.error('Delete edu error', data);
        return;
      }
      eduData.splice(i,1);
      renderAll(); triggerAutoSave();
    })
    .catch(err => { console.error(err); alert('Delete failed'); });
  } else {
    eduData.splice(i,1);
    renderAll(); triggerAutoSave();
  }
}



/* ---------- Skills (Cascading Dropdowns) ---------- */
let editingSkillIndex = -1;
const skillForm = document.getElementById('skillFormCard');
const industrySelect = document.getElementById('s_industry');
const categorySelect = document.getElementById('s_category');
const skillSelect = document.getElementById('s_skill');

function showSkillForm(isEdit=false){ 
  document.getElementById('skillFormTitle').innerText = isEdit ? 'Edit Skill' : 'Add Skill'; 
  skillForm.style.display='block'; 
  window.scrollTo({ top: skillForm.offsetTop - 20, behavior:'smooth' }); 
}
function cancelSkillForm(){ 
  skillForm.style.display='none'; 
  editingSkillIndex=-1; 
  clearSkillForm(); 
}
function clearSkillForm(){ 
  if(industrySelect) industrySelect.value=''; 
  if(categorySelect) { categorySelect.innerHTML='<option value="">Select Category</option>'; categorySelect.disabled=true; }
  if(skillSelect) { skillSelect.innerHTML='<option value="">Select Skill</option>'; skillSelect.disabled=true; }
}

const addSkillBtnEl = document.getElementById('addSkillBtn');
if(addSkillBtnEl) addSkillBtnEl.addEventListener('click', ()=>{ editingSkillIndex=-1; clearSkillForm(); showSkillForm(false); });

// Industry change -> load categories
if(industrySelect) {
  industrySelect.addEventListener('change', async function() {
    const industryId = this.value;
    categorySelect.innerHTML = '<option value="">Select Category</option>';
    skillSelect.innerHTML = '<option value="">Select Skill</option>';
    categorySelect.disabled = true;
    skillSelect.disabled = true;
    
    if(!industryId) return;
    
    try {
      const res = await fetch(`get_categories.php?industry_id=${industryId}`);
      const data = await res.json();
      if(data.success && data.categories) {
        data.categories.forEach(cat => {
          const opt = document.createElement('option');
          opt.value = cat.job_category_id;
          opt.textContent = cat.job_category_name;
          categorySelect.appendChild(opt);
        });
        categorySelect.disabled = false;
      }
    } catch(e) { console.error('Failed to load categories:', e); }
  });
}

// Category change -> load skills
if(categorySelect) {
  categorySelect.addEventListener('change', async function() {
    const categoryId = this.value;
    skillSelect.innerHTML = '<option value="">Select Skill</option>';
    skillSelect.disabled = true;
    
    if(!categoryId) return;
    
    try {
      const res = await fetch(`get_skills.php?category_id=${categoryId}`);
      const text = await res.text();
      console.log('Skills response:', text); // Debug log
      
      // Try to parse JSON, handling potential PHP errors
      let data;
      try {
        data = JSON.parse(text);
      } catch(parseErr) {
        console.error('JSON parse error for skills:', parseErr, 'Response:', text);
        return;
      }
      
      if(data.success && data.skills) {
        data.skills.forEach(sk => {
          const opt = document.createElement('option');
          opt.value = sk.skill_id;
          opt.textContent = sk.skill_name;
          skillSelect.appendChild(opt);
        });
        skillSelect.disabled = false;
      } else {
        console.error('Skills API error:', data.msg || data.error);
      }
    } catch(e) { console.error('Failed to load skills:', e); }
  });
}

function editSkill(i){
  editingSkillIndex = i;
  const s = skillsData[i];
  if(typeof s !== 'object') return; // Can't edit legacy string skills
  
  // Set industry and trigger category load
  if(industrySelect && s.industry_id) {
    industrySelect.value = s.industry_id;
    // Manually trigger change to load categories, then set category
    fetch(`get_categories.php?industry_id=${s.industry_id}`)
      .then(r => r.json())
      .then(data => {
        if(data.success && data.categories) {
          categorySelect.innerHTML = '<option value="">Select Category</option>';
          data.categories.forEach(cat => {
            const opt = document.createElement('option');
            opt.value = cat.job_category_id;
            opt.textContent = cat.job_category_name;
            categorySelect.appendChild(opt);
          });
          categorySelect.disabled = false;
          if(s.job_category_id) {
            categorySelect.value = s.job_category_id;
            // Load skills for this category
            return fetch(`get_skills.php?category_id=${s.job_category_id}`);
          }
        }
      })
      .then(r => r ? r.json() : null)
      .then(data => {
        if(data && data.success && data.skills) {
          skillSelect.innerHTML = '<option value="">Select Skill</option>';
          data.skills.forEach(sk => {
            const opt = document.createElement('option');
            opt.value = sk.skill_id;
            opt.textContent = sk.skill_name;
            skillSelect.appendChild(opt);
          });
          skillSelect.disabled = false;
          if(s.skill_id) skillSelect.value = s.skill_id;
        }
      });
  }
  showSkillForm(true);
}

const saveSkillBtnEl = document.getElementById('saveSkillBtn');
if(saveSkillBtnEl) saveSkillBtnEl.addEventListener('click', async ()=>{
  const industryId = industrySelect ? industrySelect.value : '';
  const categoryId = categorySelect ? categorySelect.value : '';
  const skillId = skillSelect ? skillSelect.value : '';
  
  if(!industryId || !categoryId || !skillId) {
    alert('Please select Industry, Category, and Skill');
    return;
  }
  
  // Prevent duplicate skill addition (except when editing)
  const duplicate = skillsData.some((s, idx) => s.skill_id === parseInt(skillId) && idx !== editingSkillIndex);
  if(duplicate){
    alert('This skill is already added.');
    return;
  }
  
  // Get display names
  const industryName = industrySelect.options[industrySelect.selectedIndex].text;
  const categoryName = categorySelect.options[categorySelect.selectedIndex].text;
  const skillName = skillSelect.options[skillSelect.selectedIndex].text;
  
  const obj = {
    industry_id: parseInt(industryId),
    industry_name: industryName,
    job_category_id: parseInt(categoryId),
    category_id: parseInt(categoryId),
    category_name: categoryName,
    skill_id: parseInt(skillId),
    skill_name: skillName
  };
  
  // Preserve existing ID if editing
  if(editingSkillIndex >= 0 && skillsData[editingSkillIndex]) {
    if(skillsData[editingSkillIndex].id) obj.id = skillsData[editingSkillIndex].id;
    if(skillsData[editingSkillIndex].applicant_skills_id) obj.applicant_skills_id = skillsData[editingSkillIndex].applicant_skills_id;
  }
  
  if(editingSkillIndex >= 0) skillsData[editingSkillIndex] = obj;
  else skillsData.push(obj);
  
  cancelSkillForm(); 
  renderAll(); 
  
  // Save immediately to database
  await doAutoSave();
  alert('Skill saved successfully!');
});

function deleteSkill(i){
  if(!confirm('Delete this skill?')) return;
  const item = skillsData[i];
  const id = item?.id;
  if (id) {
    fetch('delete_resume_item.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ section: 'skill', id: id })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert('Delete failed: ' + (data.msg || 'Unknown error'));
        console.error('Delete skill error', data);
        return;
      }
      skillsData.splice(i,1);
      renderAll(); triggerAutoSave();
    })
    .catch(err => { console.error(err); alert('Delete failed'); });
  } else {
    skillsData.splice(i,1);
    renderAll(); triggerAutoSave();
  }
}



/* ---------- Achievements (certs) ---------- */
let editingCertIndex = -1;
const certForm = document.getElementById('certFormCard');
function showCertForm(isEdit=false){ 
  document.getElementById('certFormTitle').innerText = isEdit? 'Edit Achievement' : 'Add Achievement'; 
  certForm.style.display='block'; 
  window.scrollTo({ top: certForm.offsetTop - 20, behavior:'smooth' }); 
}
function cancelCertForm(){ 
  certForm.style.display='none'; 
  editingCertIndex=-1; 
  clearCertForm(); 
}
function clearCertForm(){ 
  document.getElementById('c_title').value=''; 
  document.getElementById('c_org').value=''; 
  document.getElementById('c_date').value=''; 
  document.getElementById('c_desc').value=''; 
  document.getElementById('c_file').value=''; 
}

const addCertBtnEl = document.getElementById('addCertBtn');
if(addCertBtnEl) addCertBtnEl.addEventListener('click', ()=>{ 
  editingCertIndex=-1; 
  clearCertForm(); 
  showCertForm(false); 
});

function editCert(i){
  editingCertIndex = i; 
  const c = certData[i];
  document.getElementById('c_title').value = c.title || c.achievement_name || ''; 
  document.getElementById('c_org').value = c.organization || c.achievement_organization || ''; 
  document.getElementById('c_date').value = c.date_received || ''; 
  document.getElementById('c_desc').value = c.description || '';
  showCertForm(true);
}

const saveCertBtnEl = document.getElementById('saveCertBtn');
if(saveCertBtnEl) saveCertBtnEl.addEventListener('click', async ()=>{
  const title = document.getElementById('c_title').value.trim();
  const org = document.getElementById('c_org').value.trim();
  if(!title || !org){ 
    alert('Title and Organization are required'); 
    return; 
  }
  
  const datev = document.getElementById('c_date').value || '';
  const desc = document.getElementById('c_desc').value || '';
  const fileInput = document.getElementById('c_file');
  let fileUrl = '';
  
  if(fileInput.files.length){
    const fd = new FormData(); 
    fd.append('file', fileInput.files[0]); 
    fd.append('user_id', userId);
    const res = await fetch('upload_certificate.php',{ method:'POST', body: fd });
    const j = await res.json();
    if(!j.success){ 
      alert('File upload failed'); 
      return; 
    }
    fileUrl = j.url;
  }

  const obj = { 
    title, 
    achievement_name: title,
    organization: org, 
    achievement_organization: org,
    date_received: datev, 
    description: desc, 
    file: fileUrl 
  };
  
  if(editingCertIndex >= 0){
    // Preserve ID if editing existing achievement
    if(certData[editingCertIndex].id) {
      obj.id = certData[editingCertIndex].id;
    }
    if(certData[editingCertIndex].achievement_id) {
      obj.achievement_id = certData[editingCertIndex].achievement_id;
    }
    // Keep old file if no new file uploaded
    if(!obj.file && certData[editingCertIndex].file) {
      obj.file = certData[editingCertIndex].file;
    }
    certData[editingCertIndex] = obj;
  } else {
    certData.push(obj);
  }

  cancelCertForm(); 
  renderAll(); 
  
  // Save immediately to database
  await doAutoSave();
  alert('Achievement saved successfully!');
});





function deleteCert(i){
  if(!confirm('Delete?')) return;
  const item = certData[i];
  const id = item?.id;
  if (id) {
    fetch('delete_resume_item.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ section: 'achievement', id: id })
    })
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        alert('Delete failed: ' + (data.msg || 'Unknown error'));
        console.error('Delete cert error', data);
        return;
      }
      certData.splice(i,1);
      renderAll(); triggerAutoSave();
    })
    .catch(err => { console.error(err); alert('Delete failed'); });
  } else {
    certData.splice(i,1);
    renderAll(); triggerAutoSave();
  }
}



function calculateCompletion() {
    let score = 0;
    let required = 100;

    // PROFILE completeness (20%)
    const hasName = <?= json_encode(!empty($fullname)) ?>;
    const hasEmail = <?= json_encode(!empty($user['email'])) ?>;
    const hasPhone = <?= json_encode(!empty($user['phone'])) ?>;

    if (hasName && hasEmail && hasPhone) score += 20;

    // WORK EXPERIENCE (30%)
    if (workData.length > 0) score += 30;

    // EDUCATION (20%)
    if (eduData.length > 0) score += 20;

    // SKILLS (20%)
    if (skillsData.length >= 3) score += 20;

    // ACHIEVEMENTS (10%)
    if (certData.length > 0) score += 10;

    return score;
}






function updateProgressBar() {
    const percent = calculateCompletion();

    const progressBar = document.querySelector('.progress > i');
    const percentTextEls = document.querySelectorAll('.small');

    if (progressBar) {
        progressBar.style.width = percent + "%";
    }

    // Update all text that shows "70% Complete"
    percentTextEls.forEach(el => {
        if (el.innerText.includes("Complete")) {
            el.innerText = percent + "% Complete";
        }
    });
}






/* ---------- Render all ---------- */
function renderAll(){ 
  renderWork(); 
  renderEdu(); 
  renderSkills(); 
  renderCerts(); 
  renderPreferences();
  updateProgressBar(); }

/* ---------- Escape helpers ---------- */
function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]; }); }
function escapeHtmlAttr(s){ return (s||'').replace(/"/g,'&quot;'); }

/* ---------- Autosave (debounced) ---------- */
let saveTimer = null;
function triggerAutoSave(){
  autosaveText.innerText = 'Auto-saving your changes...';
  clearTimeout(saveTimer);
  saveTimer = setTimeout(doAutoSave, 700);
}

async function doAutoSave(){
  const payload = {
    action: 'save',
    user_id: userId,
    professional_summary: '',
    work_experience: workData,
    education: eduData,
    skills: skillsData,
    achievements: certData,
    preferences: preferencesData
  };
  try{
    const res = await fetch('autosave_resume.php', {
      method:'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',         // <-- added
      body: JSON.stringify(payload)
    });
    const j = await res.json();
    if(j.success) {
      autosaveText.innerText = 'All changes saved';
      // Update IDs returned from save
      if(j.ids) {
        if(workData.length > 0 && j.ids.experience_id) workData[0].id = j.ids.experience_id;
        if(eduData.length > 0 && j.ids.applicant_education_id) eduData[0].id = j.ids.applicant_education_id;
        if(certData.length > 0 && j.ids.achievement_id) certData[0].id = j.ids.achievement_id;
        // Update skill IDs for multiple skills
        if(j.ids.skill_ids && Array.isArray(j.ids.skill_ids)) {
          j.ids.skill_ids.forEach((skillId, idx) => {
            if(skillsData[idx]) skillsData[idx].id = skillId;
          });
        }
      }
    }
    else autosaveText.innerText = 'Save failed: ' + (j.msg || 'Unknown error');
  } catch(e){ autosaveText.innerText = 'Save error'; console.error(e); }
}

// Optionally ensure preview/download use same-origin as well
async function openPreview(){
  const payload = { user_id: userId, professional_summary:'', work_experience: JSON.stringify(workData), education: JSON.stringify(eduData), skills: JSON.stringify(skillsData), achievements: JSON.stringify(certData), preferences: JSON.stringify(preferencesData) };
  const res = await fetch('preview_resume.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials: 'same-origin',         // <-- added
    body: JSON.stringify(payload)
  });
  const html = await res.text();
  document.getElementById('previewHtml').innerHTML = html;
  document.getElementById('previewModal').style.display='flex';
}
function closePreview(){ document.getElementById('previewModal').style.display='none'; }

const previewBtnEl = document.getElementById('previewBtn');
if(previewBtnEl) previewBtnEl.addEventListener('click', openPreview);
const previewBtnTopEl = document.getElementById('previewBtnTop');
if(previewBtnTopEl) previewBtnTopEl.addEventListener('click', openPreview);

const downloadBtnEl = document.getElementById('downloadBtn');
if(downloadBtnEl) downloadBtnEl.addEventListener('click', downloadPdf);
const downloadBtnTopEl = document.getElementById('downloadBtnTop');
if(downloadBtnTopEl) downloadBtnTopEl.addEventListener('click', downloadPdf);

async function downloadPdf(){
  const payload = { user_id: userId, professional_summary:'', work_experience: JSON.stringify(workData), education: JSON.stringify(eduData), skills: JSON.stringify(skillsData), achievements: JSON.stringify(certData), preferences: JSON.stringify(preferencesData) };
  const res = await fetch('download_resume.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials: 'same-origin',         // <-- added
    body: JSON.stringify(payload)
  });
  if(res.status===200){
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'resume.pdf'; document.body.appendChild(a); a.click(); a.remove();
  } else {
    const text = await res.text(); alert('Download failed: '+text);
  }
}

/* ---------- Suggestions for job / skills (optional) ---------- */
// This implementation shows static suggested skills; you can enhance to fetch from suggestions.php

/* ---------- Load Saved Resume Data ---------- */
async function loadResumeData() {
  try {
    const res = await fetch('autosave_resume.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({action: 'load'})
    });
    if (!res.ok) {
      console.error('Load request failed:', res.status, res.statusText);
      return;
    }
    const data = await res.json();

    if (data.success) {
      // Load Work Experience - handle both response formats
      const workExp = data.work_experience || data.work || [];
      if (workExp.length > 0) {
        workData = workExp;
      }
      
      // Load Education
      const education = data.education || [];
      if (education.length > 0) {
        eduData = education;
      }
      
      // Load Skills
      const skills = data.skills || [];
      if (skills.length > 0) {
        skillsData = skills;
      }
      
      // Load Achievements
      const achievements = data.achievements || [];
      if (achievements.length > 0) {
        certData = achievements;
      }
      
      // Load Professional Summary
      if (data.professional_summary) {
        const summaryEl = document.getElementById('professional_summary');
        if (summaryEl) summaryEl.value = data.professional_summary;
      }
      
      // Load Preferences
      if (data.preferences) {
        preferencesData = data.preferences;
        console.log('Preferences loaded:', preferencesData);
      }
      
      console.log('Resume data loaded successfully:', {
        work: workData.length,
        education: eduData.length,
        skills: skillsData.length,
        achievements: certData.length,
        preferences: preferencesData
      });
    } else {
      console.error('Failed to load resume data:', data);
    }
  } catch (e) {
    console.error('Failed to load resume data:', e);
  }
}

/* ---------- Start ---------- */
// Load data first, then render
loadResumeData().then(() => {
  renderAll();
  // Store original preferences for cancel functionality
  originalPreferences = {
    job_type_id: preferencesData.job_type_id || null,
    industry_id: preferencesData.industry_id || null
  };
  console.log('Resume data loaded and rendered');
});

/* ========== PROFILE MODAL FUNCTIONS ========== */

// Modal barangay data
const modalAllBarangays = <?= json_encode($barangays) ?>;

// Open profile modal
function openProfileModal() {
  const overlay = document.getElementById('profileModalOverlay');
  const modal = document.getElementById('profileModal');
  
  if (overlay && modal) {
    // Show elements
    overlay.classList.add('active');
    modal.classList.add('active');
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
    
    // Load current data into modal
    loadModalData();
    
    // Setup municipality change handler for modal
    setupModalMunicipalityHandler();
  }
}

// Close profile modal
function closeProfileModal() {
  const overlay = document.getElementById('profileModalOverlay');
  const modal = document.getElementById('profileModal');
  
  if (overlay && modal) {
    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    
    // Close all open sections
    document.querySelectorAll('.profile-section-form.active').forEach(form => {
      form.classList.remove('active');
    });
    document.querySelectorAll('.profile-section-content.editing').forEach(content => {
      content.classList.remove('editing');
    });
  }
}

// Load current data into modal
function loadModalData() {
  // Personal details (already populated via PHP)
  // Location (already populated via PHP)
  // Socials - need to fetch
  fetch('get_profile_data.php')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Update socials display
        if (data.facebook || data.linkedin) {
          let socialsHTML = '';
          if (data.facebook) {
            socialsHTML += `<div><i class="fa-brands fa-facebook"></i> ${escapeHtml(data.facebook)}</div>`;
            document.getElementById('modal_facebook').value = data.facebook;
          }
          if (data.linkedin) {
            socialsHTML += `<div><i class="fa-brands fa-linkedin"></i> ${escapeHtml(data.linkedin)}</div>`;
            document.getElementById('modal_linkedin').value = data.linkedin;
          }
          document.getElementById('displaySocials').innerHTML = socialsHTML;
        }
      }
    })
    .catch(e => console.error('Failed to load profile data:', e));
}

// Setup municipality dropdown handler for modal
function setupModalMunicipalityHandler() {
  const munSelect = document.getElementById('modal_municipality');
  const brgySelect = document.getElementById('modal_barangay');
  
  if (munSelect && brgySelect) {
    munSelect.addEventListener('change', function() {
      const selectedMunId = this.value;
      
      // Clear and disable barangay if no municipality selected
      if (!selectedMunId) {
        brgySelect.innerHTML = '<option value="">Select Barangay</option>';
        brgySelect.disabled = true;
        return;
      }
      
      // Enable and populate barangay dropdown
      brgySelect.disabled = false;
      brgySelect.innerHTML = '<option value="">Select Barangay</option>';
      
      // Filter barangays by selected municipality
      const filteredBarangays = modalAllBarangays.filter(brgy => brgy.city_mun_id == selectedMunId);
      
      filteredBarangays.forEach(brgy => {
        const option = document.createElement('option');
        option.value = brgy.barangay_id;
        option.textContent = brgy.barangay_name;
        brgySelect.appendChild(option);
      });
    });
  }
}

// Edit a specific section
function editSection(section) {
  // Close other open sections first
  document.querySelectorAll('.profile-section-form.active').forEach(form => {
    if (form.id !== section + 'Form') {
      form.classList.remove('active');
      const sectionId = form.id.replace('Form', '');
      const content = document.getElementById(sectionId + 'Content');
      if (content) content.classList.remove('editing');
    }
  });
  
  // Toggle current section
  const content = document.getElementById(section + 'Content');
  const form = document.getElementById(section + 'Form');
  
  if (content && form) {
    content.classList.add('editing');
    form.classList.add('active');
  }
}

// Cancel editing a section
function cancelEdit(section) {
  const content = document.getElementById(section + 'Content');
  const form = document.getElementById(section + 'Form');
  
  if (content && form) {
    content.classList.remove('editing');
    form.classList.remove('active');
    
    // Reload original values (re-populate from PHP data)
    loadModalData();
  }
}

// Save individual section
async function saveEdit(section) {
  let data = { user_id: userId };
  
  switch(section) {
    case 'personal':
      data.first_name = document.getElementById('modal_first').value.trim();
      data.middle_name = document.getElementById('modal_middle').value.trim();
      data.last_name = document.getElementById('modal_last').value.trim();
      data.phone = document.getElementById('modal_phone').value.trim();
      
      // Validate phone
      if (data.phone) {
        const digitsOnly = String(data.phone).replace(/\D/g,'');
        if (digitsOnly.length !== 11) {
          alert('Phone number must be exactly 11 digits');
          return;
        }
        data.phone = digitsOnly;
      }
      
      // Update display
      document.getElementById('displayFullName').innerText = 
        data.first_name + (data.middle_name ? ' ' + data.middle_name : '') + ' ' + data.last_name;
      document.getElementById('displayPhone').innerText = data.phone || 'Not set';
      
      // Update main profile card
      document.querySelector('.profile-info h2').innerText = 
        data.first_name + (data.middle_name ? ' ' + data.middle_name : '') + ' ' + data.last_name;
      
      // Update phone in main profile card
      const phoneElements = document.querySelectorAll('.profile-info p');
      phoneElements.forEach(p => {
        if (p.innerHTML.includes('fa-phone')) {
          const icon = p.querySelector('i');
          p.innerHTML = '';
          p.appendChild(icon);
          p.appendChild(document.createTextNode(' ' + (data.phone || 'Not set')));
        }
      });
      break;
      
    case 'location':
      data.municipality = document.getElementById('modal_municipality').value;
      data.barangay = document.getElementById('modal_barangay').value;
      data.street = document.getElementById('modal_street').value.trim();
      
      // Build address line for display
      const locParts = [];
      if (data.street) locParts.push(data.street);
      if (data.barangay) {
        const brgySelect = document.getElementById('modal_barangay');
        const brgyText = brgySelect.options[brgySelect.selectedIndex].text;
        locParts.push(brgyText);
      }
      if (data.municipality) {
        const munSelect = document.getElementById('modal_municipality');
        const munText = munSelect.options[munSelect.selectedIndex].text;
        locParts.push(munText);
      }
      
      const locationText = locParts.length > 0 ? locParts.join(', ') : 'Not set';
      document.getElementById('displayLocation').innerText = locationText;
      
      // Update main profile card location
      const mainLocationEl = document.getElementById('mainDisplayLocation');
      if (mainLocationEl) mainLocationEl.innerText = locationText;
      break;
      
    case 'socials':
      data.facebook = document.getElementById('modal_facebook').value.trim();
      data.linkedin = document.getElementById('modal_linkedin').value.trim();
      
      // Update display in modal
      let socialsHTML = '';
      if (data.facebook) socialsHTML += `<div><i class="fa-brands fa-facebook"></i> ${escapeHtml(data.facebook)}</div>`;
      if (data.linkedin) socialsHTML += `<div><i class="fa-brands fa-linkedin"></i> ${escapeHtml(data.linkedin)}</div>`;
      if (!data.facebook && !data.linkedin) socialsHTML = 'No social media links added';
      document.getElementById('displaySocials').innerHTML = socialsHTML;
      
      // Update main profile card socials
      const mainSocialsEl = document.getElementById('mainDisplaySocials');
      if (mainSocialsEl) {
        let mainSocialsHTML = '';
        if (data.facebook) {
          mainSocialsHTML += `<a href="${escapeHtml(data.facebook)}" target="_blank" style="color:#1877f2;"><i class="fa-brands fa-facebook"></i></a>`;
        }
        if (data.linkedin) {
          mainSocialsHTML += `<a href="${escapeHtml(data.linkedin)}" target="_blank" style="color:#0a66c2;"><i class="fa-brands fa-linkedin"></i></a>`;
        }
        if (!data.facebook && !data.linkedin) {
          mainSocialsHTML = '<span class="small" style="color:#9ca3af;">No social links</span>';
        }
        mainSocialsEl.innerHTML = mainSocialsHTML;
      }
      break;
      
    case 'summary':
      data.bio = document.getElementById('modal_bio').value.trim();
      const bioText = data.bio || 'Tell us about yourself...';
      document.getElementById('displayBio').innerText = bioText;
      
      // Update main profile card bio
      const mainBioEl = document.getElementById('mainDisplayBio');
      if (mainBioEl) mainBioEl.innerText = data.bio || 'No bio added yet';
      break;
  }
  
  // Send to server
  try {
    const res = await fetch('update_profile_ajax.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(data)
    });
    
    const result = await res.json();
    
    if (result.success) {
      // Close the form
      cancelEdit(section);
      
      // Show success message
      const sectionHeader = document.querySelector(`#${section}Section h3`);
      const originalText = sectionHeader.innerText;
      sectionHeader.innerHTML = `<i class="fa-solid fa-check" style="color: #22c55e;"></i> ${originalText}`;
      setTimeout(() => {
        sectionHeader.innerHTML = originalText;
      }, 2000);
    } else {
      alert('Failed to save: ' + (result.error || 'Unknown error'));
    }
  } catch(err) {
    console.error(err);
    alert('Save error. Please try again.');
  }
}

// Save all changes at once
async function saveAllProfileChanges() {
  // Collect all data using FormData to support file upload
  const formData = new FormData();
  
  formData.append('user_id', userId);
  formData.append('first_name', document.getElementById('modal_first').value.trim());
  formData.append('middle_name', document.getElementById('modal_middle').value.trim());
  formData.append('last_name', document.getElementById('modal_last').value.trim());
  formData.append('phone', document.getElementById('modal_phone').value.trim());
  formData.append('municipality', document.getElementById('modal_municipality').value);
  formData.append('barangay', document.getElementById('modal_barangay').value);
  formData.append('street', document.getElementById('modal_street').value.trim());
  formData.append('facebook', document.getElementById('modal_facebook').value.trim());
  formData.append('linkedin', document.getElementById('modal_linkedin').value.trim());
  formData.append('bio', document.getElementById('modal_bio').value.trim());
  
  // Include profile photo if one was selected
  if (window.pendingProfileFile) {
    formData.append('profile_pic', window.pendingProfileFile);
  }
  
  // Validate phone
  const phone = document.getElementById('modal_phone').value.trim();
  if (phone) {
    const digitsOnly = String(phone).replace(/\D/g,'');
    if (digitsOnly.length !== 11) {
      alert('Phone number must be exactly 11 digits');
      return;
    }
    formData.set('phone', digitsOnly);
  }
  
  try {
    const res = await fetch('update_profile_ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData  // Send as FormData instead of JSON
    });
    
    const result = await res.json();
    
    if (result.success) {
      alert('Profile updated successfully!');
      
      // Clear pending file
      window.pendingProfileFile = null;
      
      // Update profile photo display if new photo was uploaded
      if (result.filename) {
        const prev = document.getElementById('profilePreview');
        if (prev) prev.src = '../uploads/profile_pics/' + result.filename;
      }
      
      closeProfileModal();
      
      // Refresh page to show updated data
      location.reload();
    } else {
      alert('Failed to save: ' + (result.error || 'Unknown error'));
    }
  } catch(err) {
    console.error(err);
    alert('Save error. Please try again.');
  }
}

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const modal = document.getElementById('profileModal');
    if (modal && modal.classList.contains('active')) {
      closeProfileModal();
    }
  }
});

/* ========== END PROFILE MODAL FUNCTIONS ========== */


function toggleProfileEdit(){
  const p = document.getElementById('profileEdit');
  if(!p) return;
  const isHidden = window.getComputedStyle(p).display === 'none';
  p.style.display = isHidden ? 'flex' : 'none';
  p.setAttribute('aria-hidden', isHidden ? 'false' : 'true');
  if(isHidden){
    // capture current values so Cancel can restore them
    originalProfile.first_name = document.getElementById('p_first') ? document.getElementById('p_first').value : '';
    originalProfile.middle_name = document.getElementById('p_middle') ? document.getElementById('p_middle').value : '';
    originalProfile.last_name = document.getElementById('p_last') ? document.getElementById('p_last').value : '';
    originalProfile.phone = document.getElementById('p_phone') ? document.getElementById('p_phone').value : '';
    originalProfile.municipality = document.getElementById('p_municipality') ? document.getElementById('p_municipality').value : '';
    originalProfile.barangay = document.getElementById('p_barangay') ? document.getElementById('p_barangay').value : '';
  }
}







// When a file is chosen, only preview it and store it in memory.
// The actual upload will happen when the user clicks "Save Changes".
window.pendingProfileFile = null;
const profilePicInputEl = document.getElementById('profilePicInput');
if(profilePicInputEl) profilePicInputEl.addEventListener('change', function(){
  const file = this.files[0];
  if(!file) { window.pendingProfileFile = null; return; }

  // preview immediately
  const reader = new FileReader();
  reader.onload = e => {
    const prev = document.getElementById('profilePreview');
    if(prev) prev.src = e.target.result;
  };
  reader.readAsDataURL(file);

  // store the file and wait for explicit Save action
  window.pendingProfileFile = file;
});

// Helper: upload profile picture file (returns Promise)
function uploadProfilePicture(file){
  if(!file) return Promise.resolve({ success: true });
  const fd = new FormData();
  fd.append('profile_pic', file);
  fd.append('user_id', userId);
  return fetch('update_profile_pic.php', { method: 'POST', body: fd }).then(r => r.json());
}







async function saveProfile(){
  const data = {
    user_id: userId,
    first_name: document.getElementById('p_first').value.trim(),
    middle_name: document.getElementById('p_middle') ? document.getElementById('p_middle').value.trim() : '',
    last_name: document.getElementById('p_last').value.trim(),
    phone: document.getElementById('p_phone').value.trim(),
    municipality: document.getElementById('p_municipality') ? document.getElementById('p_municipality').value : '',
    barangay: document.getElementById('p_barangay') ? document.getElementById('p_barangay').value : ''
  };

  try{
    // Validate phone: allow only digits and require exactly 11 digits
    if(data.phone){
      const digitsOnly = String(data.phone).replace(/\D/g,'');
      if(digitsOnly.length !== 11){
        alert('Phone number must contain exactly 11 digits.');
        return;
      }
      data.phone = digitsOnly; // normalize to digits only
    }
    // If the user selected a new profile image, upload it first
    if(window.pendingProfileFile){
      const up = await uploadProfilePicture(window.pendingProfileFile);
      if(!up || !up.success){
        alert('Profile picture upload failed. Please try again.');
        return;
      }
      // clear pending file on success
      window.pendingProfileFile = null;

      // If server returned a filename, update preview to the uploaded path
      if(up.filename){
        const prev = document.getElementById('profilePreview');
        if(prev) prev.src = '../uploads/profile_pics/' + up.filename;
      }
    }

    // Now save profile fields
    const res = await fetch("update_profile_ajax.php", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify(data)
    });
    
    // Debug: log raw response
    const responseText = await res.text();
    console.log('Raw response:', responseText);
    
    const j = JSON.parse(responseText);
    if(j.success){
      // Update UI instantly
      const nameEl = document.querySelector(".profile-info h2");
    if(nameEl) nameEl.innerText = data.first_name + (data.middle_name ? ' ' + data.middle_name : '') + ' ' + data.last_name;

      const phoneEl = document.querySelector(".profile-info p:nth-child(2)");
      if(phoneEl) phoneEl.innerText = data.phone;

      alert("Profile updated!");
      toggleProfileEdit();
    } else {
      alert("Update failed");
    }
  } catch(err){
    console.error(err);
    alert('Save error. Check console for details.');
  }
}

function toggleProfileEdit(){
    const p = document.getElementById('profileEdit');
    
    const isOpening = p.style.display === 'none';
    p.style.display = isOpening ? 'block' : 'none';

    if(isOpening){
        // store current values for cancel
    originalProfile.first_name = document.getElementById('p_first').value;
    originalProfile.middle_name = document.getElementById('p_middle') ? document.getElementById('p_middle').value : '';
    originalProfile.last_name = document.getElementById('p_last').value;
    originalProfile.phone = document.getElementById('p_phone').value;
    }
}
function cancelProfile(){
    // restore previous values
    document.getElementById('p_first').value = originalProfile.first_name;
    if(document.getElementById('p_middle')) document.getElementById('p_middle').value = originalProfile.middle_name || '';
    document.getElementById('p_last').value = originalProfile.last_name;
    document.getElementById('p_phone').value = originalProfile.phone;
    if(document.getElementById('p_municipality')) document.getElementById('p_municipality').value = originalProfile.municipality || '';
    if(document.getElementById('p_barangay')) document.getElementById('p_barangay').value = originalProfile.barangay || '';

  // close modal
  const p = document.getElementById('profileEdit');
  if(p) { p.style.display = 'none'; p.setAttribute('aria-hidden','true'); }
}

/* Phone input: numeric-only enforcement (max 11 digits) and paste sanitization */
(function(){
  const phone = document.getElementById('p_phone');
  if(!phone) return;

  // On input: strip non-digits and limit to 11 characters
  phone.addEventListener('input', function(e){
    const start = this.selectionStart;
    const cleaned = this.value.replace(/\D/g,'').slice(0,11);
    this.value = cleaned;
    // attempt to restore caret position
    try{ this.setSelectionRange(Math.min(start, this.value.length), Math.min(start, this.value.length)); }catch(err){}
  });

  // On paste: sanitize
  phone.addEventListener('paste', function(e){
    e.preventDefault();
    const text = (e.clipboardData || window.clipboardData).getData('text') || '';
    const cleaned = text.replace(/\D/g,'').slice(0,11);
    this.value = cleaned;
  });

  // Prevent non-numeric keys (allow navigation keys)
  phone.addEventListener('keydown', function(e){
    const allowed = ['Backspace','ArrowLeft','ArrowRight','Delete','Tab','Home','End'];
    if(allowed.includes(e.key)) return;
    // allow ctrl/cmd combos
    if(e.ctrlKey || e.metaKey) return;
    if(/\d/.test(e.key)) return;
    e.preventDefault();
  });
})();








//Function to show search bar under
function isMobile() {
  return window.innerWidth <= 600;
}

document.querySelectorAll(".tab").forEach(tab => {
  tab.addEventListener("click", function () {
    const target = this.dataset.tab;
    const panel = document.getElementById("panel-" + target);

    if (isMobile()) {
      // Close all other tabs and panels
      document.querySelectorAll(".tab").forEach(t => {
        if (t !== this) t.classList.remove("open");
      });
      document.querySelectorAll(".section").forEach(p => {
        if (p !== panel) p.classList.remove("open");
      });

      // Move panel under this tab safely
      if (!this.nextElementSibling || !this.nextElementSibling.isSameNode(panel)) {
        this.insertAdjacentElement("afterend", panel);
      }
      // Toggle clicked tab panel
      this.classList.toggle("open");
      panel.classList.toggle("open");
      return;
    }

    // DESKTOP — original tab switching
    document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
    document.querySelectorAll(".section").forEach(sec => sec.style.display = "none");

    this.classList.add("active");
    panel.style.display = "block";

    // Move panel back to original position
    document.querySelector(".resume-card").appendChild(panel);
  });
});






// -----------------------------
// Settings panel (slide-up) JS
// -----------------------------
const settingsBtnEl = document.getElementById('openSettings');
const settingsPanelEl = document.getElementById('settingsPanel');
const settingsOverlayEl = document.getElementById('settingsOverlay');
const closeSettingsEl = document.getElementById('closeSettings');

function openSettings(){
  if(!settingsPanelEl || !settingsOverlayEl) return;
  settingsPanelEl.classList.add('open');
  settingsOverlayEl.classList.add('open');
  // prevent background scroll when panel is open
  document.documentElement.style.overflow = 'hidden';
  document.body.style.overflow = 'hidden';
}

function closeSettings(){
  if(!settingsPanelEl || !settingsOverlayEl) return;
  settingsPanelEl.classList.remove('open');
  settingsOverlayEl.classList.remove('open');
  document.documentElement.style.overflow = '';
  document.body.style.overflow = '';
}

if(settingsBtnEl){
  settingsBtnEl.addEventListener('click', function(e){ e.stopPropagation(); openSettings(); });
}
if(closeSettingsEl){
  closeSettingsEl.addEventListener('click', function(e){ e.stopPropagation(); closeSettings(); });
}
if(settingsOverlayEl){
  settingsOverlayEl.addEventListener('click', function(){ closeSettings(); });
}

// Close with ESC
document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && settingsPanelEl && settingsPanelEl.classList.contains('open')){ closeSettings(); } });



    //Open search bar on mobile
// document.querySelector(".search-bar").addEventListener("click", function (e) {
//     if (window.innerWidth <= 600) {
//         e.preventDefault(); 
        
//         const bar = document.getElementById("mobileSearchBar");
//         bar.classList.toggle("open");
//     }
// });

// Download Resume button handler
document.getElementById('downloadResumeBtn').addEventListener('click', function() {
    window.location.href = 'download-my-resume.php';
});
</script>

<!-- Mobile Notification Styles -->
<style>
.mobile-notification { position: relative; margin-left: auto; }
.mobile-notification .wm-notification-btn { background: #1c0966; border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; transition: background 0.2s; }
.mobile-notification .wm-notification-btn:hover { background: #e5e5e5; }
.mobile-notification .wm-notification-btn i { font-size: 1.1rem; color: #ffffffff; }
.mobile-notification .notification-badge { position: absolute; top: -4px; right: -4px; background: #e74c3c; color: white; font-size: 0.65rem; min-width: 18px; height: 18px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-weight: 600; padding: 0 4px; }
.mobile-notification .wm-notification-dropdown { position: fixed; top: 60px; right: 10px; width: calc(100vw - 20px); max-width: 360px; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 1000; max-height: 500px; display: flex; flex-direction: column; }
.mobile-notification.active .wm-notification-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
.mobile-notification .notification-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
.mobile-notification .notification-header h3 { margin: 0; font-size: 1.1rem; font-weight: 600; color: #111827; }
.mobile-notification .notification-close { background: none; border: none; font-size: 1.5rem; color: #6b7280; cursor: pointer; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background 0.2s; }
.mobile-notification .notification-close:hover { background: #f3f4f6; }
.mobile-notification .notification-list { overflow-y: auto; max-height: 400px; padding: 8px 0; }
.mobile-notification .notification-list::-webkit-scrollbar { width: 6px; }
.mobile-notification .notification-list::-webkit-scrollbar-track { background: #f3f4f6; }
.mobile-notification .notification-list::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.mobile-notification .notification-item { padding: 12px 20px; border-bottom: 1px solid #f3f4f6; cursor: pointer; transition: background 0.2s; display: flex; gap: 12px; align-items: flex-start; }
.mobile-notification .notification-item:hover { background: #f9fafb; }
.mobile-notification .notification-item.unread { background: #eff6ff; }
.mobile-notification .notification-item.unread:hover { background: #dbeafe; }
.mobile-notification .notification-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 0.9rem; }
.mobile-notification .notification-icon.like { background: #fce7f3; color: #ec4899; }
.mobile-notification .notification-icon.match { background: #dcfce7; color: #22c55e; }
.mobile-notification .notification-icon.interview { background: #dbeafe; color: #3b82f6; }
.mobile-notification .notification-content { flex: 1; min-width: 0; }
.mobile-notification .notification-title { font-weight: 600; font-size: 0.9rem; color: #111827; margin: 0 0 4px 0; }
.mobile-notification .notification-message { font-size: 0.85rem; color: #6b7280; margin: 0 0 4px 0; line-height: 1.4; }
.mobile-notification .notification-time { font-size: 0.75rem; color: #9ca3af; margin: 0; }
.mobile-notification .notification-loading, .mobile-notification .notification-empty { padding: 40px 20px; text-align: center; color: #6b7280; font-size: 0.9rem; }
.mobile-notification .notification-empty i { font-size: 2.5rem; color: #d1d5db; margin-bottom: 12px; display: block; }
.mobile-notification .notification-empty p { margin: 0; }
</style>
<script>
(function(){const e=document.getElementById('mobileNotificationBtn'),t=document.querySelector('.mobile-notification'),n=document.getElementById('mobileNotificationClose'),o=document.getElementById('mobileNotificationList');if(!e||!t)return;function i(e){if(!e)return'';const t=document.createElement('div');return t.textContent=e,t.innerHTML}function c(){t.classList.toggle('active'),t.classList.contains('active')&&s()}function a(){t.classList.remove('active')}async function s(){if(!o)return;o.innerHTML='<div class="notification-loading">Loading...</div>';try{const e=await fetch('api/get_notifications.php'),t=await e.json();t.success?(l(t.notifications),r(t.unread_count)):o.innerHTML='<div class="notification-empty">Failed to load notifications</div>'}catch(e){o.innerHTML='<div class="notification-empty">Error loading notifications</div>'}}function l(e){0===e.length?o.innerHTML='<div class="notification-empty"><i class="fa-solid fa-bell-slash"></i><p>No notifications yet</p></div>':o.innerHTML=e.map(e=>{const t='like'===e.type?'fa-heart':'match'===e.type?'fa-handshake':'fa-calendar-check';return'<div class="notification-item '+(e.is_read?'':'unread')+'" data-id="'+e.id+'" onclick="handleMobileNotificationClick('+e.id+')"><div class="notification-icon '+e.type+'"><i class="fa-solid '+t+'"></i></div><div class="notification-content"><p class="notification-title">'+i(e.title)+'</p><p class="notification-message">'+i(e.message)+'</p><p class="notification-time">'+i(e.time_ago)+'</p></div></div>'}).join('')}function r(e){const t=document.getElementById('mobileNotificationBadge');t&&(e>0?(t.textContent=e>99?'99+':e,t.style.display='flex'):t.style.display='none')}window.handleMobileNotificationClick=async function(e){try{await fetch('api/mark_notification_read.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({notification_id:e})}),s()}catch(e){console.error('Error:',e)}},e.addEventListener('click',function(e){e.stopPropagation(),c()}),n&&n.addEventListener('click',function(e){e.stopPropagation(),a()}),document.addEventListener('click',function(e){t.contains(e.target)||a()}),document.addEventListener('keydown',function(e){'Escape'===e.key&&a()}),s(),setInterval(s,6e4)})();
</script>

</body>
</html>
