<?php
session_start();
require_once __DIR__ . '/../database.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

// counts
$counts = [ 'total'=>0, 'in_progress'=>0, 'interviews'=>0, 'accepted'=>0, 'rejected'=>0, 'shortlisted'=>0, 'interview'=>0 ];
$count_sql = "SELECT COUNT(*) as total,
    SUM(LOWER(status) = 'in_progress' OR LOWER(status) = 'pending') AS in_progress,
    SUM(LOWER(status) = 'interviewing') AS interviews,
    SUM(LOWER(status) = 'accepted') AS accepted,
    SUM(LOWER(status) = 'rejected') AS rejected,
    SUM(LOWER(status) = 'shortlisted') AS shortlisted,
    SUM(LOWER(status) = 'interview') AS interview
    FROM application WHERE applicant_id = ?";
$stmt = $conn->prepare($count_sql);
if($stmt){
    $stmt->bind_param('i',$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res && ($row = $res->fetch_assoc())){
        $counts['total'] = (int)$row['total'];
        $counts['in_progress'] = (int)$row['in_progress'];
        $counts['interviews'] = (int)$row['interviews'];
        $counts['accepted'] = (int)$row['accepted'];
        $counts['rejected'] = (int)$row['rejected'];
        $counts['shortlisted'] = (int)$row['shortlisted'];
        $counts['interview'] = (int)$row['interview'];
    }
    $stmt->close();
}

// fetch applications (with job details and interview schedule)
$rows = [];
$sql = "SELECT a.*, 
    jp.job_post_name AS job_title, 
    c.company_name AS company_name, 
    c.logo AS company_logo, 
    c.location AS location, 
    a.created_at AS applied_at,
    i.interview_date,
    i.interview_month,
    i.interview_day,
    i.interview_time,
    i.status AS interview_status
    FROM application a
    LEFT JOIN job_post jp ON jp.job_post_id = a.job_post_id
    LEFT JOIN company c ON c.company_id = jp.company_id
    LEFT JOIN interview_schedule i ON i.application_id = a.application_id AND i.status = 'scheduled'
    WHERE a.applicant_id = ?
    ORDER BY a.updated_at DESC";
$stmt = $conn->prepare($sql);
if($stmt){
    $stmt->bind_param('i',$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res) $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Applications</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="css/job-slide-panel.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
</head>
<body class="applications-page">

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
    
    <!-- <div class="header-actions"> -->
      
      <!-- <a class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
      </a>

      <a href="/WORKSAD/applicant/notifications.php" class="notification-bell">
        <i class="fa-regular fa-bell"></i>
        <span class="badge">3</span>
      </a> -->

      <!-- Hamburger icon -->
        <!-- <div class="menu-toggle" id="menu-toggle">☰</div>
    </div> -->

    <!-- Sidebar mobile only -->
    <!-- <aside class="sidebar" id="sidebar">
   
    <button class="close-btn" id="closeSidebar">&times;</button>
        <ul class="mobnav-links">
            <li>    
              <a href="interactions.php">
                <i class="fa-regular fa-bookmark"></i> 
                Interactions
            </a>
          </li>

            <li>
              <a href="settings.php">
                  <i class="fa-solid fa-gear"></i> 
                  Settings
              </a>
          </li>

            <li>
              <a href="logout.php">
                  <i class="fa-solid fa-arrow-right-from-bracket"></i> 
                  Logout
              </a>
          </li>
        </ul>
    </aside> -->

    <div class="overlay" id="overlay"></div>
</header>

<!-- <div class="mobile-search-bar" id="mobileSearchBar">
    <input type="text" placeholder="Search jobs, companies..." />
</div> -->


<!-- ======= DESKTOP HEADER ======= -->
<?php $activePage = 'myapplications'; include 'header.php'; ?>

<main class="apps-container">
  <div class="apps-header">
    <h1 class="apps-title">My Applications</h1>
    <p class="apps-sub">Track and manage your job applications</p>
  </div>

  <!-- <div class="apps-stats">
    <div class="stat-card">
      <h4>Total Applications</h4>
      <div class="stat-value"><?php echo $counts['total']; ?></div>
    </div>
    <div class="stat-card">
      <h4>In Progress</h4>
      <div class="stat-value"><?php echo $counts['in_progress']; ?></div>
    </div>
    <div class="stat-card">
      <h4>Interviews</h4>
      <div class="stat-value"><?php echo $counts['interviews']; ?></div>
    </div>
    <div class="stat-card">
      <h4>Accepted</h4>
      <div class="stat-value"><?php echo $counts['accepted']; ?></div>
    </div>
    <div class="stat-card">
      <h4>Rejected</h4>
      <div class="stat-value"><?php echo $counts['rejected']; ?></div>
    </div>
  </div> -->

<div class="applications-tabs" role="tablist">
    <div class="tab active" data-status="all">All <span></span></div>
    <div class="tab" data-status="pending">Pending <span><?php echo $counts['in_progress']; ?></span></div>
    <div class="tab" data-status="shortlisted">Shortlisted <span><?php echo $counts['shortlisted']; ?></span></div>
    <div class="tab" data-status="interview">Interview <span><?php echo $counts['interview']; ?></span></div>
    <div class="tab" data-status="accepted">Accepted <span><?php echo $counts['accepted']; ?></span></div>
    <div class="tab" data-status="rejected">Rejected <span><?php echo $counts['rejected']; ?></span></div>

    <!-- Underline -->
    <div class="tab-underline"></div>
</div>


    <!-- Added for mobile dropdown (hidden on desktop) -->
    <select id="applications-dropdown">
        <option value="all">All Applications</option>
        <option value="pending">Pending</option>
        <option value="shortlisted">Shortlisted</option>
        <option value="interview">Interview</option>
        <option value="accepted">Accepted</option>
        <option value="rejected">Rejected</option>
    </select>


  <div class="application-list" id="applicationList">
    <?php foreach($rows as $r):
      $status = strtolower(trim($r['status'] ?? 'applied'));
      $job_title = $r['job_title'] ?? ($r['title'] ?? 'Job');
      $company = $r['company_name'] ?? ($r['company'] ?? 'Company');
      // Handle logo path - remove leading slash if present and prepend ../
      $logoPath = $r['company_logo'] ?? '';
      $logoPath = ltrim($logoPath, '/');
      $logo = !empty($logoPath) ? '../' . $logoPath : '../assets/company-placeholder.png';
      $location = $r['location'] ?? '';
      $applied_at = $r['applied_at'] ?? $r['created_at'] ?? '';
    ?>
    <div class="application-card" data-status="<?php echo e($status); ?>">
      <div class="app-left">
        <img src="<?php echo e($logo); ?>" alt="" class="app-logo">
        <div class="app-info">
          <h3><?php echo e($job_title); ?></h3>
          <div class="company"><?php echo e($company); ?></div>
          <div class="app-meta"><i class="fa-solid fa-location-dot"></i> <?php echo e($location); ?> &nbsp; • &nbsp; Applied on <?php echo e($applied_at ? date('F j, Y', strtotime($applied_at)) : '—'); ?></div>
          
          <?php 
          // Only show interview details when status is exactly 'interview' and interview_date exists
          if (strtolower($status) === 'interview' && !empty($r['interview_date'])): 
            // Parse the interview datetime
            $interview_datetime = $r['interview_date'];
            $interview_time_str = $r['interview_time'] ?? '';
            
            // Format date
            $date_obj = new DateTime($interview_datetime);
            $formatted_date = $date_obj->format('F j, Y');
            $weekday = $date_obj->format('l');
            
            // Format time - use interview_time field if available
            if (!empty($interview_time_str)) {
              $time_obj = DateTime::createFromFormat('H:i:s', $interview_time_str);
              if ($time_obj) {
                $formatted_time = $time_obj->format('g:i A');
              } else {
                $formatted_time = $date_obj->format('g:i A');
              }
            } else {
              $formatted_time = $date_obj->format('g:i A');
            }
          ?>
          <div class="interview-card">
            <div class="interview-header">
              <i class="fa-solid fa-calendar-check"></i>
              <span>Interview Scheduled</span>
            </div>
            <div class="interview-company">
              <i class="fa-solid fa-building"></i>
              <strong><?php echo e($company); ?></strong> is interviewing you for <strong><?php echo e($job_title); ?></strong>
            </div>
            <div class="interview-details" style="margin-top: 10px;">
              <div class="interview-detail-item" style="margin-bottom: 6px;">
                <i class="fa-regular fa-calendar"></i>
                <span><strong><?php echo e($weekday); ?>,</strong> <?php echo e($formatted_date); ?></span>
              </div>
              <div class="interview-detail-item">
                <i class="fa-regular fa-clock"></i>
                <span><?php echo e($formatted_time); ?></span>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="app-actions">
        <span class="status-badge status-<?php echo e(str_replace(' ', '_', $status)); ?>"><?php echo ucfirst($status); ?></span>
        <a class="btn-green" href="#" data-job-id="<?php echo intval($r['job_post_id'] ?? 0); ?>" data-application-id="<?php echo intval($r['application_id'] ?? $r['id'] ?? 0); ?>"><i class="fa-solid fa-eye"></i> View Details</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</main>

<!-- Job Slide Panel -->
<div class="job-slide-overlay" id="jobSlideOverlay"></div>
<div class="job-slide-panel" id="jobSlidePanel">
    <div class="slide-panel-header">
        <h2>Job Details</h2>
        <button class="slide-panel-close" id="slidePanelClose">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="slide-panel-content" id="slidePanelContent">
        <!-- Content will be loaded dynamically -->
    </div>
</div>








<script src="js/job-slide-panel.js"></script>
<script>
// Desktop tab filtering
document.querySelectorAll('.applications-tabs .tab').forEach(tab => {
  tab.addEventListener('click', function(){
    document.querySelectorAll('.applications-tabs .tab').forEach(t=>t.classList.remove('active'));
    this.classList.add('active');
    filterApplications(this.dataset.status);
  });
});

// Mobile dropdown filtering
document.getElementById('applications-dropdown').addEventListener('change', function() {
  filterApplications(this.value);
});

// Filter function
function filterApplications(status) {
  document.querySelectorAll('#applicationList .application-card').forEach(card => {
    if(status === 'all') { 
      card.style.display = ''; 
      return; 
    }
    const cardStatus = card.dataset.status;
    
    // Handle pending status (includes 'in_progress' and 'pending')
    if(status === 'pending' && (cardStatus === 'pending' || cardStatus === 'in_progress')) {
      card.style.display = ''; 
    } else if(status === 'shortlisted' && cardStatus === 'shortlisted') {
      card.style.display = ''; 
    } else if(status === 'interview' && (cardStatus === 'interview' || cardStatus === 'interviewing')) {
      card.style.display = ''; 
    } else if(cardStatus === status) {
      card.style.display = ''; 
    } else {
      card.style.display = 'none';
    }
  });
}

// View Details button functionality
document.querySelectorAll('.btn-green').forEach(btn => {
  btn.addEventListener('click', function(e){
    e.preventDefault();
    const jobId = this.dataset.jobId;
    if(jobId) {
      openJobSlidePanel(jobId);
    }
  });
});

// Tab underline animation (desktop only)
const tabs = document.querySelectorAll(".applications-tabs .tab");
const underline = document.querySelector(".tab-underline");

if (underline && tabs.length > 0) {
  tabs.forEach((tab, i) => {
    tab.addEventListener("click", () => {
      const tabWidth = 100 / tabs.length;
      underline.style.left = (i * tabWidth) + "%";
      underline.style.width = tabWidth + "%";
    });
  });
}
</script>

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
