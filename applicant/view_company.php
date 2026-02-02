<?php
session_start();
require_once '../database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get company ID from URL
$companyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($companyId <= 0) {
    header('Location: companies.php');
    exit;
}

// Fetch company details
$companyStmt = $conn->prepare("SELECT 
    company_id,
    user_id,
    company_name,
    description,
    industry,
    location,
    website,
    logo,
    status
FROM company WHERE company_id = ?");

$company = null;
if ($companyStmt) {
    $companyStmt->bind_param('i', $companyId);
    $companyStmt->execute();
    $companyResult = $companyStmt->get_result();
    $company = $companyResult->fetch_assoc();
    $companyStmt->close();
}

// Redirect if company not found
if (!$company) {
    header('Location: companies.php');
    exit;
}

// Debug: Check company_id
error_log("View Company - Company ID: " . $companyId);
error_log("View Company - Company Name: " . ($company['company_name'] ?? 'N/A'));

// First, get the accurate count of jobs for this company
$jobCountQuery = $conn->prepare("
    SELECT COUNT(*) as total_jobs
    FROM job_post 
    WHERE company_id = ?
");
$activeJobCount = 0;
if ($jobCountQuery) {
    $jobCountQuery->bind_param('i', $companyId);
    $jobCountQuery->execute();
    $countResult = $jobCountQuery->get_result();
    if ($countRow = $countResult->fetch_assoc()) {
        $activeJobCount = (int)$countRow['total_jobs'];
    }
    $jobCountQuery->close();
    error_log("View Company - Total jobs found: " . $activeJobCount);
}

// Fetch job posts for this company - simplified query first
$jobsQuery = "
    SELECT 
        jp.job_post_id,
        jp.job_post_name,
        jp.job_description,
        jp.requirements,
        jp.budget,
        jp.benefits,
        jp.vacancies,
        jp.created_at,
        jp.job_status_id,
        jp.job_type_id,
        jp.job_location_id,
        jp.experience_level_id,
        jp.education_level_id,
        jp.work_setup_id,
        jp.job_category_id
    FROM job_post jp
    WHERE jp.company_id = ?
    ORDER BY jp.created_at DESC
";

$jobs = [];
$jobsStmt = $conn->prepare($jobsQuery);
if ($jobsStmt) {
    $jobsStmt->bind_param('i', $companyId);
    if ($jobsStmt->execute()) {
        $jobsResult = $jobsStmt->get_result();
        $jobs = $jobsResult->fetch_all(MYSQLI_ASSOC);
        error_log("View Company - Jobs fetched from query: " . count($jobs));
    } else {
        error_log("View Company - Query execution failed: " . $jobsStmt->error);
    }
    $jobsStmt->close();
} else {
    error_log("View Company - Query preparation failed: " . $conn->error);
}

// Use the accurate count we got earlier
$jobCount = $activeJobCount;
error_log("View Company - Final job count: " . $jobCount);

// Helper function to escape HTML
function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Prepare company data
// Handle logo path - check if it already contains the full path
$logoPath = $company['logo'] ?? '';
if (!empty($logoPath)) {
    // If logo already contains 'uploads/', use it as-is with ../ prefix
    if (strpos($logoPath, 'uploads/') === 0) {
        $logo = '../' . e($logoPath);
    } else {
        // Otherwise, it's just the filename
        $logo = '../uploads/company_logos/' . e($logoPath);
    }
} else {
    $logo = '../assets/company-placeholder.png';
}

$name = e($company['company_name'] ?? 'Unnamed Company');
$industryVal = e($company['industry'] ?? '');
$locationVal = e($company['location'] ?? '');
$desc = e($company['description'] ?? '');
$website = $company['website'] ?? '';
$status = e($company['status'] ?? '');
// Job count is now set above after the query
error_log("View Company - Using job count in template: " . $jobCount);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $name ?> - WorkMuna</title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="view-company-page">

<!-- ================= MOBILE HEADER ================= -->
<header class="mobile-header">
    <button class="back-btn" onclick="history.back()">
        <i class="fa-solid fa-arrow-left"></i>
    </button>
    <h2 class="page-title"><?= $name ?></h2>
    
    <div class="header-actions">
        <a href="/WORKSAD/applicant/notifications.php" class="notification-bell">
            <i class="fa-regular fa-bell"></i>
            <span class="badge">3</span>
        </a>
        <div class="menu-toggle" id="menu-toggle">☰</div>
    </div>

    <!-- Sidebar mobile only -->
    <aside class="sidebar" id="sidebar">
        <button class="close-btn" id="closeSidebar">&times;</button>
        <ul class="mobnav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="search_jobs.php">Jobs</a></li>
            <li><a href="companies.php">Companies</a></li>
            <li><a href="application.php">My Applications</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>

<!-- ======= DESKTOP HEADER ======= -->
<?php $activePage = 'companies'; include 'header.php'; ?>

<main class="view-company-main">
    <!-- Hero Banner Section -->
    <section class="vc-hero-banner" style="background-image: url('<?= $logo ?>'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <div class="vc-hero-gradient"></div>
        <div class="vc-hero-pattern"></div>
        <div class="vc-hero-content">
            <!-- Breadcrumb -->
            <nav class="vc-breadcrumb">
                <a href="companies.php"><i class="fa-solid fa-building"></i> Companies</a>
                <i class="fa-solid fa-chevron-right"></i>
                <span><?= $name ?></span>
            </nav>
            
            <div class="vc-company-intro">
                <div class="vc-intro-text">
                    <h1><?= $name ?></h1>
                    <?php if ($status === 'verified' || $status === 'active'): ?>
                        <span class="vc-verified-badge" title="Verified Company">
                            <i class="fa-solid fa-circle-check"></i>
                        </span>
                    <?php endif; ?>
                    <?php if ($industryVal): ?>
                        <span class="vc-industry-tag">
                            <i class="fa-solid fa-industry"></i> <?= $industryVal ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Bar -->
    <div class="vc-stats-bar">
        <div class="vc-stat-item">
            <i class="fa-solid fa-location-dot"></i>
            <span><?= $locationVal ?: 'Location not specified' ?></span>
        </div>
        <?php if ($website): ?>
        <div class="vc-stat-item">
            <i class="fa-solid fa-globe"></i>
            <a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= preg_replace('#^https?://(www\.)?#', '', e($website)) ?></a>
        </div>
        <?php endif; ?>
        <div class="vc-stat-item highlight">
            <i class="fa-solid fa-briefcase"></i>
            <span><?= $jobCount ?> Open Position<?= $jobCount !== 1 ? 's' : '' ?></span>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="vc-content-wrapper">
        <!-- Left Column - About -->
        <div class="vc-main-content">
            <!-- About Card -->
            <section class="vc-card vc-about-card">
                <div class="vc-card-header">
                    <div class="vc-card-icon">
                        <i class="fa-solid fa-building"></i>
                    </div>
                    <h2>About the Company</h2>
                </div>
                <div class="vc-card-body">
                    <?php if ($desc): ?>
                        <p class="vc-description"><?= nl2br($desc) ?></p>
                    <?php else: ?>
                        <div class="vc-empty-state small">
                            <i class="fa-solid fa-file-lines"></i>
                            <p>No company description available yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Job Openings Card -->
            <section class="vc-card vc-jobs-card">
                <div class="vc-card-header">
                    <div class="vc-card-icon jobs">
                        <i class="fa-solid fa-briefcase"></i>
                    </div>
                    <h2>Job Openings</h2>
                    <span class="vc-jobs-count"><?= $jobCount ?></span>
                </div>
                <div class="vc-card-body">
                    <?php if ($jobCount > 0): ?>
                        <div class="vc-jobs-list">
                            <?php foreach ($jobs as $index => $job): 
                                $jobId = intval($job['job_post_id']);
                                $jobTitle = e($job['job_post_name'] ?? 'Untitled Position');
                                $jobDesc = e($job['job_description'] ?? '');
                                $jobRequirements = e($job['requirements'] ?? '');
                                $jobBenefits = e($job['benefits'] ?? '');
                                $jobSalary = e($job['budget'] ?? '');
                                $jobVacancies = intval($job['vacancies'] ?? 0);
                                $jobStatusId = intval($job['job_status_id'] ?? 0);
                                $createdAt = $job['created_at'] ? date('M d, Y', strtotime($job['created_at'])) : '';
                                $daysAgo = $job['created_at'] ? floor((time() - strtotime($job['created_at'])) / 86400) : null;
                            ?>
                                <article class="vc-job-item" style="animation-delay: <?= $index * 0.05 ?>s">
                                    <div class="vc-job-main">
                                        <div class="vc-job-info">
                                            <h3 class="vc-job-title">
                                                <a href="search_jobs.php?job_id=<?= $jobId ?>"><?= $jobTitle ?></a>
                                            </h3>
                                            <div class="vc-job-badges">
                                                <?php if ($daysAgo !== null && $daysAgo <= 7): ?>
                                                    <span class="vc-badge new">New</span>
                                                <?php endif; ?>
                                                <?php
                                                    // Map job status ID to name and color
                                                    $statusMap = [
                                                        1 => ['name' => 'Open', 'color' => '#10b981'],
                                                        2 => ['name' => 'Close', 'color' => '#f59e0b'],
                                                        3 => ['name' => 'Archive', 'color' => '#6b7280'],
                                                        4 => ['name' => 'Deactivated', 'color' => '#ef4444']
                                                    ];
                                                    $statusInfo = $statusMap[$jobStatusId] ?? ['name' => 'Unknown', 'color' => '#9ca3af'];
                                                ?>
                                                <span class="vc-badge" style="background: <?= $statusInfo['color'] ?>; color: #fff;"><?= $statusInfo['name'] ?></span>
                                            </div>
                                        </div>
                                        <?php if ($jobSalary): ?>
                                            <div class="vc-job-salary">
                                                <span class="vc-salary-label">Salary</span>
                                                <span class="vc-salary-value">₱<?= $jobSalary ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="vc-job-meta">
                                        <span><i class="fa-solid fa-location-dot"></i> <?= $locationVal ?></span>
                                        <?php if ($jobVacancies > 0): ?>
                                            <span><i class="fa-solid fa-users"></i> <?= $jobVacancies ?> opening<?= $jobVacancies !== 1 ? 's' : '' ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($jobDesc): ?>
                                        <p class="vc-job-desc"><?= strlen($jobDesc) > 120 ? substr($jobDesc, 0, 120) . '...' : $jobDesc ?></p>
                                    <?php endif; ?>

                                    <?php if ($jobRequirements): ?>
                                        <div class="vc-job-details">
                                            <strong><i class="fa-solid fa-list-check"></i> Requirements:</strong>
                                            <p><?= strlen($jobRequirements) > 100 ? substr($jobRequirements, 0, 100) . '...' : $jobRequirements ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($jobBenefits): ?>
                                        <div class="vc-job-details">
                                            <strong><i class="fa-solid fa-gift"></i> Benefits:</strong>
                                            <p><?= strlen($jobBenefits) > 100 ? substr($jobBenefits, 0, 100) . '...' : $jobBenefits ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="vc-job-footer">
                                        <span class="vc-job-date">
                                            <i class="fa-regular fa-clock"></i>
                                            <?php if ($daysAgo === 0): ?>
                                                Posted today
                                            <?php elseif ($daysAgo === 1): ?>
                                                Posted yesterday
                                            <?php elseif ($daysAgo !== null): ?>
                                                Posted <?= $daysAgo ?> days ago
                                            <?php else: ?>
                                                Posted <?= $createdAt ?>
                                            <?php endif; ?>
                                        </span>
                                        <a href="search_jobs.php?job_id=<?= $jobId ?>" class="vc-view-job-btn">
                                            View Job <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="vc-empty-state">
                            <div class="vc-empty-icon">
                                <i class="fa-solid fa-folder-open"></i>
                            </div>
                            <h3>No Open Positions</h3>
                            <p>This company doesn't have any open positions at the moment.</p>
                            <a href="companies.php" class="vc-back-btn">
                                <i class="fa-solid fa-arrow-left"></i> Browse Other Companies
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <!-- Right Sidebar -->
        <aside class="vc-sidebar">
            <!-- Quick Actions Card -->
            <div class="vc-sidebar-card vc-actions-card">
                <h3>Quick Actions</h3>
                <?php if ($website): ?>
                <a href="<?= e($website) ?>" target="_blank" class="vc-action-btn website">
                    <i class="fa-solid fa-globe"></i> Visit Website
                </a>
                <?php endif; ?>
                <a href="search_jobs.php?company=<?= urlencode($name) ?>" class="vc-action-btn jobs">
                    <i class="fa-solid fa-magnifying-glass"></i> Search Jobs
                </a>
                <a href="companies.php" class="vc-action-btn back">
                    <i class="fa-solid fa-arrow-left"></i> Back to Companies
                </a>
            </div>

            <!-- Company Info Card -->
            <div class="vc-sidebar-card vc-info-card">
                <h3>Company Info</h3>
                <ul class="vc-info-list">
                    <li>
                        <span class="vc-info-label"><i class="fa-solid fa-industry"></i> Industry</span>
                        <span class="vc-info-value"><?= $industryVal ?: 'Not specified' ?></span>
                    </li>
                    <li>
                        <span class="vc-info-label"><i class="fa-solid fa-location-dot"></i> Location</span>
                        <span class="vc-info-value"><?= $locationVal ?: 'Not specified' ?></span>
                    </li>
                    <li>
                        <span class="vc-info-label"><i class="fa-solid fa-briefcase"></i> Open Positions</span>
                        <span class="vc-info-value"><?= $jobCount ?> job<?= $jobCount !== 1 ? 's' : '' ?></span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</main>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav">
    <a href="home.php"><i class="fa-solid fa-house"></i><span>Home</span></a>
    <a href="search_jobs.php"><i class="fa-solid fa-magnifying-glass"></i><span>Jobs</span></a>
    <a href="companies.php" class="active"><i class="fa-solid fa-building"></i><span>Companies</span></a>
    <a href="application.php"><i class="fa-solid fa-file-lines"></i><span>Applications</span></a>
    <a href="profile.php"><i class="fa-solid fa-user"></i><span>Profile</span></a>
</nav>

<script>
// Sidebar toggle
const menuToggle = document.getElementById("menu-toggle");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");
const closeBtn = document.getElementById("closeSidebar");

if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", (e) => {
        e.stopPropagation();
        sidebar.classList.toggle("active");
        if (overlay) overlay.classList.toggle("active");
    });

    document.addEventListener("click", (e) => {
        if (sidebar.classList.contains("active") &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)) {
            sidebar.classList.remove("active");
            if (overlay) overlay.classList.remove("active");
        }
    });

    if (overlay) {
        overlay.addEventListener("click", () => {
            sidebar.classList.remove("active");
            overlay.classList.remove("active");
        });
    }

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sidebar.classList.contains("active")) {
            sidebar.classList.remove("active");
            if (overlay) overlay.classList.remove("active");
        }
    });
}

if (closeBtn) {
    closeBtn.addEventListener("click", () => {
        sidebar.classList.remove("active");
        if (overlay) overlay.style.display = "none";
    });
}
</script>

</body>
</html>
