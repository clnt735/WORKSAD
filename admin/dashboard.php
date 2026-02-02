<?php
// Connect to the database
include '../database.php';
require_once __DIR__ . '/session_guard.php';

$currentAdminName = 'Administrator';
$currentAdminFirstName = 'Administrator';
$currentAdminEmail = '';

// Determine role name based on user_type_id
$userRoleName = isSuperAdmin() ? 'Super Admin' : 'Admin';
$isSuperAdminUser = isSuperAdmin();

if (!empty($_SESSION['user_id'])) {
  $adminSql = "SELECT 
      u.user_email,
      up.user_profile_first_name AS profile_first,
      up.user_profile_middle_name AS profile_middle,
      up.user_profile_last_name AS profile_last
    FROM user u
    LEFT JOIN user_profile up ON up.user_id = u.user_id
    WHERE u.user_id = ?
    LIMIT 1";
  $adminQuery = $conn->prepare($adminSql);
  if ($adminQuery) {
    $adminQuery->bind_param('i', $_SESSION['user_id']);
    if ($adminQuery->execute()) {
      $adminResult = $adminQuery->get_result();
      if ($adminRow = $adminResult->fetch_assoc()) {
        $profileFirst = trim($adminRow['profile_first'] ?? '');
        $profileMiddle = trim($adminRow['profile_middle'] ?? '');
        $profileLast = trim($adminRow['profile_last'] ?? '');

        $profileParts = array_filter([
          $profileFirst,
          $profileMiddle,
          $profileLast
        ], function ($part) {
          return $part !== '';
        });

        $profileFullName = trim(implode(' ', $profileParts));
        if ($profileFullName !== '') {
          $currentAdminName = $profileFullName;
          if ($profileFirst !== '') {
            $currentAdminFirstName = $profileFirst;
          } else {
            $nameSegments = preg_split('/\s+/', $profileFullName);
            $currentAdminFirstName = $nameSegments && $nameSegments[0] !== '' ? $nameSegments[0] : 'Administrator';
          }
        }
        $currentAdminEmail = $adminRow['user_email'] ?? '';
      }
    }
    $adminQuery->close();
  }
}

// Initialize variables
$totalJobs = 0;
$activeJobs = 0;
$pendingApprovals = 0;
$totalApplicants = 0;
$totalEmployers = 0;
$jobLikes = 0;
$pendingReports = 0;
$uncategorizedJobs = 0;
$recentJobs = [];
$adminInitials = 'AD';
$lastLoginDisplay = date('M d, Y');

// Fetch data from the database
$totalJobsQuery = "SELECT COUNT(*) AS total_jobs FROM job_post";
$activeJobsQuery = "SELECT COUNT(*) AS active_jobs FROM job_post WHERE job_status_id = '1'";
$pendingApprovalsQuery = "SELECT COUNT(*) AS pending_approvals FROM job_post WHERE job_status_id = '2'";
$totalApplicantsQuery = "SELECT COUNT(*) AS total_applicants FROM user WHERE user_type_id = 2";
$totalEmployersQuery = "SELECT COUNT(*) AS total_employers FROM user WHERE user_type_id = 3";
$jobLikesQuery = "SELECT COUNT(*) AS total_likes FROM job_likes";
$pendingReportsQuery = "SELECT COUNT(*) AS pending_reports FROM report WHERE status = 'pending'";
$uncategorizedJobsQuery = "SELECT COUNT(*) AS uncategorized_jobs FROM job_post WHERE job_category_id IS NULL";
$recentJobsQuery = "SELECT jp.job_post_id, jp.job_description, jp.created_at, jc.job_category_name, js.job_status_name
                    FROM job_post jp
                    LEFT JOIN job_category jc ON jp.job_category_id = jc.job_category_id
                    LEFT JOIN job_status js ON jp.job_status_id = js.job_status_id
                    ORDER BY jp.created_at DESC
                    LIMIT 5";

$totalJobsResult = mysqli_query($conn, $totalJobsQuery);
$activeJobsResult = mysqli_query($conn, $activeJobsQuery);
$pendingApprovalsResult = mysqli_query($conn, $pendingApprovalsQuery);
$totalApplicantsResult = mysqli_query($conn, $totalApplicantsQuery);
$totalEmployersResult = mysqli_query($conn, $totalEmployersQuery);
$jobLikesResult = mysqli_query($conn, $jobLikesQuery);
$pendingReportsResult = mysqli_query($conn, $pendingReportsQuery);
$uncategorizedJobsResult = mysqli_query($conn, $uncategorizedJobsQuery);
$recentJobsResult = mysqli_query($conn, $recentJobsQuery);

if ($totalJobsResult) {
    $totalJobs = mysqli_fetch_assoc($totalJobsResult)['total_jobs'];
}
if ($activeJobsResult) {
    $activeJobs = mysqli_fetch_assoc($activeJobsResult)['active_jobs'];
}
if ($pendingApprovalsResult) {
    $pendingApprovals = mysqli_fetch_assoc($pendingApprovalsResult)['pending_approvals'];
}
if ($totalApplicantsResult) {
  $totalApplicants = mysqli_fetch_assoc($totalApplicantsResult)['total_applicants'];
}
if ($totalEmployersResult) {
  $totalEmployers = mysqli_fetch_assoc($totalEmployersResult)['total_employers'];
}
if ($jobLikesResult) {
  $jobLikes = mysqli_fetch_assoc($jobLikesResult)['total_likes'];
}
if ($pendingReportsResult) {
  $pendingReports = mysqli_fetch_assoc($pendingReportsResult)['pending_reports'];
}
if ($uncategorizedJobsResult) {
  $uncategorizedJobs = mysqli_fetch_assoc($uncategorizedJobsResult)['uncategorized_jobs'];
}
if ($recentJobsResult) {
  while ($row = mysqli_fetch_assoc($recentJobsResult)) {
    $recentJobs[] = $row;
  }
}

$nameParts = preg_split('/\s+/', trim($currentAdminName));
$firstInitial = ($nameParts && !empty($nameParts[0])) ? substr($nameParts[0], 0, 1) : 'A';
$lastInitial = ($nameParts && count($nameParts) > 1 && !empty($nameParts[count($nameParts) - 1]))
    ? substr($nameParts[count($nameParts) - 1], 0, 1)
    : substr($currentAdminFirstName, 0, 1);
$adminInitials = strtoupper(($firstInitial ?: 'A') . (($lastInitial ?: $firstInitial) ?: 'D'));

$snapshotCards = [
  [
    'label' => 'Applicants',
    'value' => $totalApplicants,
    'icon' => 'fa-users'
  ],
  [
    'label' => 'Employers',
    'value' => $totalEmployers,
    'icon' => 'fa-building'
  ],
  [
    'label' => 'Job Likes',
    'value' => $jobLikes,
    'icon' => 'fa-heart'
  ],
  [
    'label' => 'Pending Reports',
    'value' => $pendingReports,
    'icon' => 'fa-flag'
  ]
];

$alertItems = [
  [
    'label' => 'Pending job approvals',
    'count' => $pendingApprovals,
    'icon' => 'fa-clipboard-check'
  ],
  [
    'label' => 'Uncategorized jobs',
    'count' => $uncategorizedJobs,
    'icon' => 'fa-tags'
  ],
  [
    'label' => 'Reports awaiting review',
    'count' => $pendingReports,
    'icon' => 'fa-triangle-exclamation'
  ]
];

$summaryCardsData = [
  [
    'label' => 'Total Jobs',
    'value' => $totalJobs,
    'icon' => 'fa-briefcase',
    'hint' => 'Open requisitions across the network.'
  ],
  [
    'label' => 'Active Jobs',
    'value' => $activeJobs,
    'icon' => 'fa-bolt',
    'hint' => 'Currently visible to applicants.'
  ],
  [
    'label' => 'Pending Approvals',
    'value' => $pendingApprovals,
    'icon' => 'fa-clipboard-check',
    'hint' => 'Requires admin review.'
  ],
  [
    'label' => 'Applicants',
    'value' => $totalApplicants,
    'icon' => 'fa-user-graduate',
    'hint' => 'Registered talents in the platform.'
  ],
  [
    'label' => 'Employers',
    'value' => $totalEmployers,
    'icon' => 'fa-building',
    'hint' => 'Partner companies recruiting now.'
  ],
  [
    'label' => 'Pending Reports',
    'value' => $pendingReports,
    'icon' => 'fa-flag',
    'hint' => 'Content awaiting investigation.'
  ],
];

$primarySummaryCards = array_slice($summaryCardsData, 0, 4);

?>
<?php include '../admin/sidebar.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WorkMuna Admin Dashboard</title>
    <link rel="stylesheet" href="../admin/styles.css">
    <link rel="stylesheet" href="../assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.min.css">
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
</head>
<body class="admin-page dashboard-page">
  <?php renderAdminSidebar(); ?>
  <main class="content">
    <section class="dashboard-hero compact-hero">
      <div class="hero-text">
        <h1>Welcome Back, <?php echo $isSuperAdminUser ? 'Super Admin' : htmlspecialchars($currentAdminFirstName); ?></h1>
        <p class="page-subtitle">Here’s a condensed look at today’s platform activity.</p>
        <div class="hero-meta">
          <span class="hero-pill"><i class="fas fa-bolt"></i><?php echo number_format($activeJobs); ?> active jobs</span>
          <span class="hero-pill"><i class="fas fa-flag"></i><?php echo number_format($pendingReports); ?> open reports</span>
          <span class="hero-pill"><i class="fas fa-calendar-day"></i><?php echo date('M d, Y'); ?></span>
        </div>
      </div>
      <div class="profile-card slim-profile">
        <div class="profile-details">
          <p class="profile-name"><?php echo htmlspecialchars($currentAdminName); ?></p>
          <p class="profile-email"><?php echo htmlspecialchars($currentAdminEmail !== '' ? $currentAdminEmail : 'admin@workmuna.local'); ?></p>
          <div class="profile-meta-row">
            <span><strong>Role:</strong> <?php echo htmlspecialchars($userRoleName); ?></span>
            <span><strong>Last login:</strong> <?php echo htmlspecialchars($lastLoginDisplay); ?></span>
          </div>
        </div>
      </div>
    </section>

    <section class="summary-grid compact-grid">
      <?php foreach ($primarySummaryCards as $card): ?>
        <article class="summary-card">
          <div class="summary-icon">
            <i class="fas <?php echo $card['icon']; ?>"></i>
          </div>
          <div>
            <p class="summary-label"><?php echo htmlspecialchars($card['label']); ?></p>
            <p class="summary-value"><?php echo number_format($card['value']); ?></p>
            <p class="summary-hint"><?php echo htmlspecialchars($card['hint']); ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="dashboard-panels">
      <article class="panel notifications-panel compact-panel">
        <div class="panel-header">
          <div>
            <h2>Notifications</h2>
            <p>Items needing attention.</p>
          </div>
        </div>
        <?php if (count($alertItems)): ?>
          <ul class="alert-list">
            <?php foreach ($alertItems as $alert): ?>
              <li>
                <div class="alert-icon <?php echo $alert['count'] > 0 ? 'has-alert' : ''; ?>">
                  <i class="fas <?php echo $alert['icon']; ?>"></i>
                </div>
                <div class="alert-content">
                  <p class="alert-label"><?php echo htmlspecialchars($alert['label']); ?></p>
                  <p class="alert-value">
                    <?php if ($alert['count'] > 0): ?>
                      <?php echo number_format($alert['count']); ?> open
                    <?php else: ?>
                      All caught up
                    <?php endif; ?>
                  </p>
                </div>
                <span class="notification-status <?php echo $alert['count'] > 0 ? 'pending' : 'clear'; ?>">
                  <?php echo $alert['count'] > 0 ? 'Action needed' : 'Clear'; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <p>No notifications right now.</p>
          </div>
        <?php endif; ?>
      </article>

      <section class="panel recent-panel">
        <div class="panel-header">
          <h2>Latest Job Posts</h2>
          <p>Most recent opportunities added to the platform.</p>
        </div>
        <?php if (count($recentJobs)): ?>
          <ul class="recent-job-list">
            <?php foreach ($recentJobs as $job): ?>
              <?php
                $jobDate = $job['created_at'] ? date('M d, Y', strtotime($job['created_at'])) : '—';
                $categoryName = $job['job_category_name'] ? $job['job_category_name'] : 'Uncategorized';
                $statusName = $job['job_status_name'] ? $job['job_status_name'] : 'Unknown';
                $description = $job['job_description'];
                $snippet = strlen($description) > 120 ? substr($description, 0, 117) . '…' : $description;
              ?>
              <li class="recent-job-item">
                <div class="recent-job-top">
                  <h3>Job #<?php echo htmlspecialchars($job['job_post_id']); ?></h3>
                  <span class="recent-job-date"><?php echo htmlspecialchars($jobDate); ?></span>
                </div>
                <p class="recent-job-snippet"><?php echo htmlspecialchars($snippet); ?></p>
                <div class="recent-job-meta">
                  <span class="badge badge-category"><?php echo htmlspecialchars($categoryName); ?></span>
                  <span class="badge badge-status"><?php echo htmlspecialchars($statusName); ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="empty-state">
            <p>No job activity yet.</p>
          </div>
        <?php endif; ?>
      </section>
    </section>
  </main>
</body>
</html>
