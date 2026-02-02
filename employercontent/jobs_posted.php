<?php
session_start();
require_once '../database.php';

$activePage = 'jobs_posted';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function wm_status_key(array $job): string
{
    $id = isset($job['job_status_id']) ? (int)$job['job_status_id'] : null;
    if ($id === null) {
        return 'open';
    }
    switch ($id) {
        case 1:
            return 'open';
        case 2:
            return 'closed';
        case 3:
            return 'archived';
        default:
            return 'open';
    }
}

function wm_status_class(array $job): string
{
    $map = [
        'open' => 'status-open',
        'closed' => 'status-closed',
        'archived' => 'status-archived',
    ];
    $key = wm_status_key($job);
    return 'status-pill ' . ($map[$key] ?? 'status-open');
}

function wm_status_label(array $job): string
{
    if (!empty($job['job_status_name'])) {
        return $job['job_status_name'];
    }
    $key = wm_status_key($job);
    return ucfirst(str_replace('-', ' ', $key));
}

function wm_format_budget($budget): string
{
    if ($budget === null || $budget === '') {
        return 'Budget TBD';
    }
    return 'PHP ' . number_format((float)$budget, 2);
}

function wm_format_setup(?string $setup): string
{
    $map = [
        'on_site' => 'On-site',
        'hybrid' => 'Hybrid',
        'remote' => 'Remote',
    ];
    $key = strtolower((string)$setup);
    return $map[$key] ?? 'Flexible';
}

function wm_format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('M d, Y', $timestamp) : '—';
}

function wm_filter_url(string $status, string $search): string
{
    $params = [];
    if ($status !== 'all') {
        $params['status'] = $status;
    }
    if ($search !== '') {
        $params['q'] = $search;
    }
    $query = http_build_query($params);
    return 'jobs_posted.php' . ($query ? '?' . $query : '');
}

$userId = (int)$_SESSION['user_id'];
$statusFilter = strtolower($_GET['status'] ?? 'all');
$allowedFilters = ['all', 'open', 'closed', 'archived'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}
$searchQuery = trim($_GET['q'] ?? '');

// Fetch total matches count
$matchesCount = 0;
if ($stmt = $conn->prepare('SELECT COUNT(*) FROM matches WHERE employer_id = ?')) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($matchesCount);
    $stmt->fetch();
    $stmt->close();
}

$jobs = [];
$queryError = null;
$jobsSql = "SELECT jp.*,
        COALESCE(js.job_status_name, 'Draft') AS job_status_name,
        COALESCE(jt.job_type_name, 'Unspecified') AS job_type_name,
        COALESCE(jc_from_skills.job_category_name, 'Uncategorized') AS job_category_name,
        COALESCE(c.company_name, 'Personal Listing') AS company_name,
        COALESCE(ws.work_setup_name, 'Flexible') AS work_setup_name,
        jpl.location_street,
        jpl.address_line,
        jpl.city_mun_id,
        jpl.barangay_id,
        cm.city_mun_name,
        br.barangay_name,
        ex.experience_level_name,
        ed.education_level_name,
        jp.requirements
    FROM job_post jp
    LEFT JOIN job_status js ON jp.job_status_id = js.job_status_id
    LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
    LEFT JOIN (
        SELECT jps.job_post_id, jc.job_category_name
        FROM job_post_skills jps
        INNER JOIN job_category jc ON jps.job_category_id = jc.job_category_id
        GROUP BY jps.job_post_id
        LIMIT 1
    ) jc_from_skills ON jp.job_post_id = jc_from_skills.job_post_id
    LEFT JOIN company c ON jp.company_id = c.company_id
    LEFT JOIN work_setup ws ON jp.work_setup_id = ws.work_setup_id
    LEFT JOIN job_post_location jpl ON jpl.job_post_id = jp.job_post_id
    LEFT JOIN city_mun cm ON jpl.city_mun_id = cm.city_mun_id
    LEFT JOIN barangay br ON jpl.barangay_id = br.barangay_id
    LEFT JOIN experience_level ex ON jp.experience_level_id = ex.experience_level_id
    LEFT JOIN education_level ed ON jp.education_level_id = ed.education_level_id
    WHERE (jp.user_id = ? OR c.user_id = ?)
    ORDER BY jp.created_at DESC";

$stmt = $conn->prepare($jobsSql);
if ($stmt === false) {
    $queryError = $conn->error;
} else {
    $stmt->bind_param('ii', $userId, $userId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
    } else {
        $queryError = $stmt->error;
    }
    $stmt->close();
}

$jobTypes = [];
$jobTypeLookup = [];
$jobTypeSql = "SELECT job_type_id, job_type_name FROM job_type ORDER BY job_type_name ASC";
if ($jobTypeResult = $conn->query($jobTypeSql)) {
    while ($row = $jobTypeResult->fetch_assoc()) {
        $jobTypes[] = $row;
        $jobTypeLookup[(int)$row['job_type_id']] = $row['job_type_name'];
    }
    $jobTypeResult->free();
}

$workSetupOptions = [];
$workSetupSql = "SELECT work_setup_id, work_setup_name FROM work_setup ORDER BY work_setup_name ASC";
if ($workSetupResult = $conn->query($workSetupSql)) {
    while ($row = $workSetupResult->fetch_assoc()) {
        $workSetupOptions[(int)$row['work_setup_id']] = $row['work_setup_name'];
    }
    $workSetupResult->free();
}

$cityOptions = [];
$cityLookup = [];
$citySql = "SELECT city_mun_id, city_mun_name FROM city_mun ORDER BY city_mun_name ASC";
if ($cityResult = $conn->query($citySql)) {
    while ($row = $cityResult->fetch_assoc()) {
        $cityOptions[] = $row;
        $cityLookup[(int)$row['city_mun_id']] = $row['city_mun_name'];
    }
    $cityResult->free();
}

$barangayOptions = [];
$barangaySql = "SELECT barangay_id, city_mun_id, barangay_name FROM barangay ORDER BY barangay_name ASC";
if ($barangayResult = $conn->query($barangaySql)) {
    while ($row = $barangayResult->fetch_assoc()) {
        $cityId = (int)$row['city_mun_id'];
        $barangayOptions[$cityId][] = [
            'id' => (int)$row['barangay_id'],
            'name' => $row['barangay_name'],
        ];
    }
    $barangayResult->free();
}

$experienceLevels = [];
$experienceLookup = [];
$expSql = "SELECT experience_level_id, experience_level_name FROM experience_level ORDER BY experience_level_id ASC";
if ($expResult = $conn->query($expSql)) {
    while ($row = $expResult->fetch_assoc()) {
        $experienceLevels[] = $row;
        $experienceLookup[(int)$row['experience_level_id']] = $row['experience_level_name'];
    }
    $expResult->free();
}

$educationLevels = [];
$educationLookup = [];
$eduSql = "SELECT education_level_id, education_level_name FROM education_level ORDER BY education_level_id ASC";
if ($eduResult = $conn->query($eduSql)) {
    while ($row = $eduResult->fetch_assoc()) {
        $educationLevels[] = $row;
        $educationLookup[(int)$row['education_level_id']] = $row['education_level_name'];
    }
    $eduResult->free();
}

$conn->close();

if (empty($workSetupOptions)) {
    $workSetupOptions = [
        1 => 'On-site',
        2 => 'Hybrid',
        3 => 'Remote',
    ];
}

$metrics = [
    'total_jobs' => count($jobs),
    'open_jobs' => 0,
    'total_vacancies' => 0,
    'total_matches' => $matchesCount,
    'recent_jobs' => 0,
];

$statusCounts = [
    'all' => count($jobs),
    'open' => 0,
    'closed' => 0,
    'archived' => 0,
];

foreach ($jobs as $job) {
    $statusKey = wm_status_key($job);
    if (isset($statusCounts[$statusKey])) {
        $statusCounts[$statusKey]++;
    }
    if ($statusKey === 'open') {
        $metrics['open_jobs']++;
    }
    $metrics['total_vacancies'] += (int)($job['vacancies'] ?? 0);
    if (!empty($job['created_at'])) {
        $createdTs = strtotime($job['created_at']);
        if ($createdTs && $createdTs >= strtotime('-30 days')) {
            $metrics['recent_jobs']++;
        }
    }
}

$filteredJobs = array_filter($jobs, function ($job) use ($statusFilter, $searchQuery) {
    if ($statusFilter !== 'all' && wm_status_key($job) !== $statusFilter) {
        return false;
    }
    if ($searchQuery !== '') {
        $needle = strtolower($searchQuery);
        $haystack = strtolower(
            ($job['job_post_name'] ?? '') . ' ' .
            ($job['job_category_name'] ?? '') . ' ' .
            ($job['job_type_name'] ?? '') . ' ' .
            ($job['address_line'] ?? '') . ' ' .
            ($job['location_street'] ?? '') . ' ' .
            ($job['city_mun_name'] ?? '') . ' ' .
            ($job['barangay_name'] ?? '')
        );
        return strpos($haystack, $needle) !== false;
    }
    return true;
});

$filteredJobs = array_values($filteredJobs);
$filterTabs = [
    'all' => 'All',
    'open' => 'Open',
    'closed' => 'Closed',
    'archived' => 'Archived',
];

include "navbar.php";
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<main class="jobs-dashboard">
    <!-- Floating Action Button for Mobile -->
    <a href="post_job.php" class="fab-button" aria-label="Post New Job">
        <i class="fas fa-plus"></i>
    </a>

    <section class="metrics-grid">
        <div class="jobs-dashboard__header-content">
            <div class="jobs-dashboard__header-title">My Jobs</div>
            <div class="jobs-dashboard__header-subtitle">Track every listing you publish and keep roles up to date.</div>
        </div>
        <div class="metric-item">
            <div class="metric-info">
                <p class="metric-label">Total Listings</p>
                <p class="metric-caption">All roles under your account</p>
            </div>
            <p class="metric-value"><?php echo number_format($metrics['total_jobs']); ?></p>
        </div>
        <div class="metric-item">
            <div class="metric-info">
                <p class="metric-label">Open Roles</p>
                <p class="metric-caption">Currently accepting applicants</p>
            </div>
            <p class="metric-value"><?php echo number_format($metrics['open_jobs']); ?></p>
        </div>
        <div class="metric-item">
            <div class="metric-info">
                <p class="metric-label">Total Vacancies</p>
                <p class="metric-caption">Headcount needed across jobs</p>
            </div>
            <p class="metric-value"><?php echo number_format($metrics['total_vacancies']); ?></p>
        </div>
        <div class="metric-item">
            <div class="metric-info">
                <p class="metric-label">Total Matches</p>
                <p class="metric-caption">Applicants matched to your jobs</p>
            </div>
            <p class="metric-value"><?php echo number_format($metrics['total_matches']); ?></p>
        </div>
    </section>

    <section class="jobs-dashboard__controls">
        <div class="search-field" aria-label="Search jobs">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="liveSearchInput" placeholder="Search by title, category, or location..." value="<?php echo htmlspecialchars($searchQuery); ?>" autocomplete="off" />
            <a class="clear-search" id="clearSearchBtn" style="display: none;" aria-label="Clear search">&times;</a>
        </div>
        <form class="status-dropdown" method="GET" aria-label="Filter by status">
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" />
            <label for="status-select">Status</label>
            <select id="status-select" name="status" onchange="this.form.submit()">
                <?php foreach ($filterTabs as $key => $label):
                    $count = $statusCounts[$key] ?? 0;
                ?>
                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label . ' (' . number_format($count) . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </section>

    <section class="jobs-table" aria-label="Job listings">
        <?php if ($queryError): ?>
            <div class="table-message error">Unable to load jobs right now. <?php echo htmlspecialchars($queryError); ?></div>
        <?php elseif (empty($jobs)): ?>
            <div class="empty-state">
                <h3>No job posts yet</h3>
                <p>Publish your first opening to start attracting applicants.</p>
                <a href="post_job.php" class="btn primary">Post a Job</a>
            </div>
        <?php else: ?>
            <?php if (empty($filteredJobs)): ?>
                <div class="empty-state subtle">
                    <h3>No matches found</h3>
                    <p>Try a different search or reset your filters.</p>
                    <a href="<?php echo htmlspecialchars(wm_filter_url('all', '')); ?>" class="btn ghost">Reset Filters</a>
                </div>
            <?php else: ?>
                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
                    <div class="bulk-actions-content">
                        <span class="bulk-selected-count" id="selectedCount">0 selected</span>
                        <div class="bulk-actions-buttons">
                            <?php if ($statusFilter === 'archived'): ?>
                            <button type="button" class="btn-bulk btn-bulk-restore" id="bulkRestoreBtn">
                                <i class="fa-solid fa-arrow-rotate-left"></i> Restore
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn-bulk btn-bulk-archive" id="bulkArchiveBtn">
                                <i class="fa-solid fa-box-archive"></i> Archive
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn-bulk btn-bulk-delete" id="bulkDeleteBtn">
                                <i class="fa-solid fa-trash-can"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Select All Header -->
                <div class="jobs-list-header">
                    <label class="select-all-control">
                        <input type="checkbox" id="selectAllCheckbox" />
                        <span class="checkbox-custom"></span>
                        <span class="select-all-text">Select All</span>
                    </label>
                </div>

                <div class="jobs-list">
                <?php foreach ($filteredJobs as $job): ?>
                <?php
                    $locationMeta = $job['address_line'] ?? '';
                    if ($locationMeta === '') {
                        $locationMeta = $job['location_street'] ?? '';
                    }
                    if ($locationMeta === '') {
                        $locationMeta = $job['barangay_name'] ?? '';
                    }
                    if ($locationMeta === '') {
                        $locationMeta = $job['city_mun_name'] ?? '';
                    }
                    $metaParts = array_filter([
                        $job['job_type_name'] ?? '',
                        $locationMeta,
                    ]);
                    $rawLocationStreet = $job['location_street'] ?? '';
                    $locationFieldValue = $rawLocationStreet;
                    if ($locationFieldValue !== '' && strpos($locationFieldValue, ',') !== false) {
                        $segments = array_map('trim', explode(',', $locationFieldValue));
                        $lastSegment = end($segments);
                        $locationFieldValue = is_string($lastSegment) ? trim($lastSegment) : '';
                    }
                ?>
                <article class="job-card" data-job-id="<?php echo (int)($job['job_post_id'] ?? 0); ?>">
                    <div class="job-card__top">
                        <div class="job-card__title-row">
                            <label class="job-checkbox-wrapper">
                                <input type="checkbox" class="job-checkbox" data-job-id="<?php echo (int)($job['job_post_id'] ?? 0); ?>" />
                                <span class="checkbox-custom"></span>
                            </label>
                            <div class="job-card__title">
                                <p class="job-title"><?php echo htmlspecialchars($job['job_post_name']); ?></p>
                                <p class="job-meta" data-field="job-meta"><?php echo htmlspecialchars(implode(' · ', $metaParts)); ?></p>
                            </div>
                        </div>
                        <span class="<?php echo htmlspecialchars(wm_status_class($job)); ?>"><?php echo htmlspecialchars(wm_status_label($job)); ?></span>
                    </div>
                    <div class="job-card__tags">
                        <span class="tag-chip" data-field="job-category"><?php echo htmlspecialchars($job['job_category_name']); ?></span>
                        <span class="tag-chip ghost"><?php echo htmlspecialchars($job['company_name']); ?></span>
                    </div>
                    <?php $workSetupLabel = $job['work_setup_name'] ?? ($workSetupOptions[(int)($job['work_setup_id'] ?? 0)] ?? 'Flexible'); ?>
                    <div class="job-card__grid">
                        <div class="job-stat">
                            <p class="job-stat__label">Vacancies</p>
                            <p class="job-stat__value" data-field="vacancies"><?php echo number_format((int)($job['vacancies'] ?? 0)); ?></p>
                            <span class="job-stat__hint">Positions open</span>
                        </div>
                        <div class="job-stat">
                            <p class="job-stat__label">Setup</p>
                            <p class="job-stat__value" data-field="setup"><?php echo htmlspecialchars($workSetupLabel); ?></p>
                            <span class="job-stat__hint">Work arrangement</span>
                        </div>
                        <div class="job-stat">
                            <p class="job-stat__label">Budget</p>
                            <p class="job-stat__value" data-field="budget"><?php echo htmlspecialchars(wm_format_budget($job['budget'])); ?></p>
                            <span class="job-stat__hint">Per role / range</span>
                        </div>
                        <div class="job-stat">
                            <p class="job-stat__label">Posted</p>
                            <p class="job-stat__value"><?php echo htmlspecialchars(wm_format_date($job['created_at'] ?? null)); ?></p>
                            <span class="job-stat__hint">Most recent update</span>
                        </div>
                    </div>
                    <?php
                        $jobPayload = [
                            'id' => (int)($job['job_post_id'] ?? 0),
                            'title' => $job['job_post_name'] ?? '',
                            'typeId' => (int)($job['job_type_id'] ?? 0),
                            'vacancies' => (int)($job['vacancies'] ?? 1),
                            'workSetupId' => (int)($job['work_setup_id'] ?? 0),
                            'workSetupName' => $job['work_setup_name'] ?? '',
                            'locationStreet' => $locationFieldValue,
                            'addressLine' => $job['address_line'] ?? '',
                            'cityMunId' => (int)($job['city_mun_id'] ?? 0),
                            'cityMunName' => $job['city_mun_name'] ?? '',
                            'barangayId' => (int)($job['barangay_id'] ?? 0),
                            'barangayName' => $job['barangay_name'] ?? '',
                            'budget' => isset($job['budget']) ? (float)$job['budget'] : null,
                            'description' => $job['job_description'] ?? '',
                            'requirements' => $job['requirements'] ?? '',
                            'benefits' => $job['benefits'] ?? '',
                            'experienceLevelId' => (int)($job['experience_level_id'] ?? 0),
                            'experienceLevelName' => $job['experience_level_name'] ?? '',
                            'educationLevelId' => (int)($job['education_level_id'] ?? 0),
                            'educationLevelName' => $job['education_level_name'] ?? '',
                            'job_category_name' => $job['job_category_name'] ?? '',
                        ];
                        $jobPayloadJson = htmlspecialchars(json_encode($jobPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="job-card__actions">
                        <div class="job-card__left-actions">
                            <?php if (wm_status_key($job) === 'archived'): ?>
                            <button type="button" class="job-action-button is-restore js-restore-job" aria-label="Restore job">
                                <i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i>
                            </button>
                            <?php else: ?>
                            <button type="button" class="job-action-button js-edit-job" aria-label="Edit job" data-job="<?php echo $jobPayloadJson; ?>">
                                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                            <button type="button" class="job-action-button is-danger js-delete-job" aria-label="Delete job">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="job-action-button js-view-job" aria-label="View details" data-job="<?php echo $jobPayloadJson; ?>">
                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</main>

<!-- Modal remains the same -->
<div class="job-modal" id="editJobModal" aria-hidden="true">
    <div class="job-modal__backdrop" data-close-modal></div>
    <div class="job-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="editJobModalTitle">
        <header class="job-modal__header">
            <div>
                <p class="eyebrow">Quick Edit</p>
                <h2 id="editJobModalTitle">Update Job Details</h2>
                <p class="job-modal__subtitle">Keep openings up to date so applicants get accurate information.</p>
            </div>
            <button type="button" class="modal-close" aria-label="Close" data-close-modal>&times;</button>
        </header>
        <form id="editJobForm" class="job-modal__form" method="POST" novalidate>
            <input type="hidden" name="job_post_id" />
            <div class="modal-alert" role="alert" aria-live="polite"></div>
            <div class="modal-sections">
                <section>
                    <h3>Role Basics</h3>
                    <div class="form-grid">
                        <label class="form-field">
                            <span>Job Title <span class="required" title="Required">*</span></span>
                            <input type="text" name="job_post_name" required maxlength="150" />
                        </label>
                        <label class="form-field">
                            <span>Job Type <span class="required" title="Required">*</span></span>
                            <select name="job_type_id" required>
                                <option value="">Select job type</option>
                                <?php foreach ($jobTypes as $type): ?>
                                <option value="<?php echo (int)$type['job_type_id']; ?>"><?php echo htmlspecialchars($type['job_type_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Work Setup</span>
                            <select name="work_setup_id">
                                <?php foreach ($workSetupOptions as $setupId => $label): ?>
                                <option value="<?php echo (int)$setupId; ?>"><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>City / Municipality <span class="required">*</span></span>
                            <select name="city_mun_id" required>
                                <option value="">Select city / municipality</option>
                                <?php foreach ($cityOptions as $city): ?>
                                <option value="<?php echo (int)$city['city_mun_id']; ?>"><?php echo htmlspecialchars($city['city_mun_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Barangay</span>
                            <select name="barangay_id" disabled>
                                <option value="">Select barangay (optional)</option>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Location (street / building)</span>
                            <input type="text" name="location_street" maxlength="150" />
                        </label>
                        <label class="form-field">
                            <span>Vacancies</span>
                            <input type="number" name="vacancies" min="1" step="1" />
                        </label>
                    </div>
                </section>
                <section>
                    <h3>Requirements & Details</h3>
                    <div class="form-grid">
                        <label class="form-field">
                            <span>Budget (PHP)</span>
                            <input type="number" name="budget" min="0" step="0.01" inputmode="decimal" />
                        </label>
                        <label class="form-field">
                            <span>Experience Level <span class="required" title="Required">*</span></span>
                            <select name="experience_level_id" required>
                                <option value="">Select experience level</option>
                                <?php foreach ($experienceLevels as $level): ?>
                                <option value="<?php echo (int)$level['experience_level_id']; ?>"><?php echo htmlspecialchars($level['experience_level_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-field">
                            <span>Education Level <span class="required" title="Required">*</span></span>
                            <select name="education_level_id" required>
                                <option value="">Select education level</option>
                                <?php foreach ($educationLevels as $level): ?>
                                <option value="<?php echo (int)$level['education_level_id']; ?>"><?php echo htmlspecialchars($level['education_level_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="form-field form-field--stacked">
                            <span>Job Description</span>
                            <textarea name="job_description" rows="3"></textarea>
                        </label>
                        <label class="form-field form-field--stacked">
                            <span>Requirements</span>
                            <textarea name="requirements" rows="3"></textarea>
                        </label>
                        <label class="form-field form-field--stacked">
                            <span>Benefits</span>
                            <textarea name="benefits" rows="2"></textarea>
                        </label>
                    </div>
                </section>
            </div>
            <footer class="modal-actions">
                <button type="button" class="btn ghost" data-close-modal>Cancel</button>
                <button type="submit" class="btn primary">Save Changes</button>
            </footer>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div class="job-modal" id="viewJobModal" aria-hidden="true">
    <div class="job-modal__backdrop" data-close-view-modal></div>
    <div class="job-modal__dialog job-modal__dialog--view" role="dialog" aria-modal="true" aria-labelledby="viewJobModalTitle">
        <header class="job-modal__header">
            <div>
                <h2 id="viewJobModalTitle" class="view-job-title">Job Details</h2>
                <p class="job-modal__subtitle view-job-subtitle"></p>
            </div>
            <button type="button" class="modal-close" aria-label="Close" data-close-view-modal>&times;</button>
        </header>
        <div class="view-job-content">
            <div class="view-section">
                <h3>Basic Information</h3>
                <div class="view-grid">
                    <div class="view-field">
                        <span class="view-label">Job Type</span>
                        <span class="view-value" id="view-job-type">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Work Setup</span>
                        <span class="view-value" id="view-work-setup">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Vacancies</span>
                        <span class="view-value" id="view-vacancies">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Budget</span>
                        <span class="view-value" id="view-budget">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Category</span>
                        <span class="view-value" id="view-category">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Location</span>
                        <span class="view-value" id="view-location">—</span>
                    </div>
                </div>
            </div>
            
            <div class="view-section">
                <h3>Requirements</h3>
                <div class="view-grid">
                    <div class="view-field">
                        <span class="view-label">Experience Level</span>
                        <span class="view-value" id="view-experience">—</span>
                    </div>
                    <div class="view-field">
                        <span class="view-label">Education Level</span>
                        <span class="view-value" id="view-education">—</span>
                    </div>
                </div>
            </div>

            <div class="view-section">
                <h3>Skills Needed</h3>
                <div class="view-skills-container" id="view-skills">
                    <p class="view-loading">Loading skills...</p>
                </div>
            </div>

            <div class="view-section">
                <h3>Job Description</h3>
                <div class="view-text" id="view-description">—</div>
            </div>

            <div class="view-section">
                <h3>Requirements</h3>
                <div class="view-text" id="view-requirements">—</div>
            </div>

            <div class="view-section">
                <h3>Benefits</h3>
                <div class="view-text" id="view-benefits">—</div>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --jobs-bg: #f3f6fb;
    --jobs-surface: #ffffff;
    --jobs-border: #dfe6ef;
    --jobs-muted: #5f6b7a;
    --jobs-heading: #0f172a;
    --jobs-primary: #34a853;
    --jobs-accent: #176a8c;
    --jobs-warning: #f0a02f;
    --jobs-danger: #e45462;
}

body {
    background: var(--jobs-bg);
}

.jobs-dashboard {
    min-height: calc(100vh - 80px);
    background: var(--jobs-bg);
    padding: 1.5rem clamp(1rem, 6vw, 4rem) 3rem;
    font-family: "Roboto", "Segoe UI", Tahoma, sans-serif;
}

.jobs-dashboard section,
.jobs-dashboard__controls {
    max-width: 1120px;
    margin-left: auto;
    margin-right: auto;
}

.jobs-dashboard__header-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.3rem;
    grid-column: 1 / -1;
    padding: 1.25rem;
    border-bottom: 1px solid var(--jobs-border);
}

.jobs-dashboard__header-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    color: var(--jobs-heading);
}

.jobs-dashboard__header-subtitle {
    font-size: 1rem;
    color: var(--jobs-muted);
    margin: 0;
}

@media (max-width: 680px) {
  .jobs-dashboard__header-title {
    font-size: 1.5rem;
  }
  .jobs-dashboard__header-subtitle {
    font-size: 0.9rem;
  }
}

.bulk-actions-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1rem 1.5rem;
    border-radius: 12px 12px 0 0;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bulk-actions-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.bulk-selected-count {
    color: #ffffff;
    font-weight: 600;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.bulk-selected-count::before {
    content: '✓';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    font-size: 0.875rem;
}

.bulk-actions-buttons {
    display: flex;
    gap: 0.75rem;
}

.btn-bulk {
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-bulk:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.btn-bulk-archive {
    background: #f59e0b;
    color: #ffffff;
}

.btn-bulk-archive:hover {
    background: #d97706;
}

.btn-bulk-delete {
    background: #ef4444;
    color: #ffffff;
}

.btn-bulk-delete:hover {
    background: #dc2626;
}

.btn-bulk-restore {
    background: #10b981;
    color: #ffffff;
}

.btn-bulk-restore:hover {
    background: #059669;
}

/* Select All Header */
.jobs-list-header {
    background: #f8f9fc;
    padding: 1rem 1.5rem;
    border-bottom: 2px solid var(--jobs-border);
}

.select-all-control {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
}

.select-all-text {
    font-weight: 600;
    color: var(--jobs-heading);
    font-size: 0.95rem;
}

/* Custom Checkbox Styling */
.job-checkbox-wrapper,
.select-all-control {
    position: relative;
}

.job-checkbox,
#selectAllCheckbox {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}

.checkbox-custom {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--jobs-border);
    border-radius: 5px;
    background: #ffffff;
    transition: all 0.2s ease;
    position: relative;
}

.job-checkbox:checked + .checkbox-custom,
#selectAllCheckbox:checked + .checkbox-custom {
    background: var(--jobs-primary);
    border-color: var(--jobs-primary);
}

.job-checkbox:checked + .checkbox-custom::after,
#selectAllCheckbox:checked + .checkbox-custom::after {
    content: '';
    position: absolute;
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid #ffffff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

#selectAllCheckbox:indeterminate + .checkbox-custom {
    background: var(--jobs-primary);
    border-color: var(--jobs-primary);
}

#selectAllCheckbox:indeterminate + .checkbox-custom::after {
    content: '';
    position: absolute;
    left: 4px;
    top: 8px;
    width: 10px;
    height: 2px;
    background: #ffffff;
    border: none;
    transform: none;
}

.job-checkbox-wrapper {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem;
}

.job-card__title-row {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.metrics-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0;
    margin-bottom: 2.5rem;
    background: var(--jobs-surface);
    border-radius: 1rem;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.07);
    border: 1px solid var(--jobs-border);
    overflow: hidden;
}

@media (min-width: 681px) {
  .metrics-grid {
    grid-template-columns: repeat(4, 1fr);
  }
  
  .metric-item:nth-child(2),
  .metric-item:nth-child(3),
  .metric-item:nth-child(4),
  .metric-item:nth-child(5) {
    border-right: 1px solid var(--jobs-border);
  }
  
  .metric-item:nth-child(5) {
    border-right: none;
  }
}

.metric-item {
    padding: 1.25rem;
    display: flex;
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.metric-item:last-child {
    border-right: none;
}

.metric-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}

@media (max-width: 680px) {
  .metrics-grid {
    grid-template-columns: 1fr;
  }
  
  .jobs-dashboard__header-content {
    padding: 1.25rem;
    border-bottom: 1px solid var(--jobs-border);
  }
  
  .metric-item {
    border-right: none;
    border-bottom: 1px solid var(--jobs-border);
    padding: 1rem 1.25rem;
  }
  
  .metric-item:last-child {
    border-bottom: none;
  }
  
  .metric-item .metric-value {
    margin: 0;
    font-size: 1.35rem;
    flex-shrink: 0;
  }
}

.metric-label {
    font-size: 0.85rem;
    color: var(--jobs-muted);
    margin: 0;
}

.metric-value {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--jobs-heading);
    flex-shrink: 0;
}

.metric-caption {
    margin: 0.15rem 0 0;
    color: var(--jobs-muted);
    font-size: 0.85rem;
}

.jobs-dashboard__controls {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    align-items: stretch;
    margin-bottom: 1.5rem;
}

.search-field {
    flex: 1;
    min-width: 240px;
    background: var(--jobs-surface);
    border: 1px solid var(--jobs-border);
    border-radius: 999px;
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    position: relative;
    height: 42px;
    box-sizing: border-box;
}

.search-field i {
    font-size: 1rem;
    color: var(--jobs-muted);
}

.search-field input {
    border: none;
    flex: 1;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--jobs-heading);
    background: transparent;
}

.search-field input:focus {
    outline: none;
}

.clear-search {
    position: absolute;
    right: 1rem;
    color: var(--jobs-muted);
    text-decoration: none;
    font-size: 1.3rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}

.status-dropdown {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--jobs-surface);
    border: 1px solid var(--jobs-border);
    border-radius: 999px;
    padding: 0.5rem 1rem;
    height: 42px;
    box-sizing: border-box;
}

.status-dropdown label {
    font-size: 0.85rem;
    color: var(--jobs-muted);
    font-weight: 600;
    white-space: nowrap;
}

.status-dropdown select {
    border: none;
    background: transparent;
    font-size: 0.95rem;
    font-family: inherit;
    color: var(--jobs-heading);
    font-weight: 600;
    cursor: pointer;
    padding: 0;
}

.status-dropdown select:focus {
    outline: none;
}

.jobs-table {
    background: var(--jobs-surface);
    border-radius: 1.25rem;
    border: 1px solid var(--jobs-border);
    box-shadow: 0 22px 50px rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.jobs-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    padding: 1.25rem;
}

.job-card {
    background: var(--jobs-surface);
    border: 1px solid var(--jobs-border);
    border-radius: 1.1rem;
    padding: 1.2rem 1.4rem;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.07);
    display: flex;
    flex-direction: column;
    gap: 0.95rem;
    transition: all 0.2s ease;
}

.job-card:hover {
    border-color: var(--jobs-primary);
    box-shadow: 0 24px 50px rgba(52, 168, 83, 0.12);
}

.job-card__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
}

.job-card__title {
    flex: 1;
}

.job-card__title .job-title {
    margin: 0;
    font-weight: 600;
    font-size: 1.05rem;
    color: var(--jobs-heading);
}

.job-card__title .job-meta {
    margin: 0.25rem 0 0;
    color: var(--jobs-muted);
    font-size: 0.9rem;
}

.job-card__tags {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.tag-chip {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.9rem;
    border-radius: 999px;
    font-size: 0.8rem;
    background: #eef4ff;
    color: var(--jobs-accent);
}

.tag-chip.ghost {
    background: transparent;
    border: 1px dashed var(--jobs-border);
    color: var(--jobs-muted);
}

.job-card__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 0.9rem;
}

.job-stat {
    border: 1px solid var(--jobs-border);
    border-radius: 0.9rem;
    padding: 0.9rem 1rem;
    background: #f9fbff;
}

.job-stat__label {
    margin: 0;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--jobs-muted);
}

.job-stat__value {
    margin: 0.35rem 0 0;
    font-weight: 600;
    color: var(--jobs-heading);
}

.job-stat__hint {
    display: block;
    font-size: 0.8rem;
    color: var(--jobs-muted);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.3rem 0.9rem;
    border-radius: 999px;
    font-weight: 600;
    font-size: 0.85rem;
    white-space: nowrap;
}

.status-open {
    background: rgba(31, 157, 113, 0.1);
    color: var(--jobs-primary);
}

.status-closed {
    background: rgba(228, 84, 98, 0.12);
    color: var(--jobs-danger);
}

.status-archived {
    background: rgba(100, 116, 139, 0.15);
    color: #475569;
}

.job-action-button {
    border: 1px solid var(--jobs-border);
    background: #fff;
    border-radius: 999px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    margin-right: 0.4rem;
    transition: all 0.2s ease;
}

.job-action-button i {
    font-size: 1rem;
    color: var(--jobs-muted);
}

.job-action-button:hover {
    border-color: var(--jobs-primary);
    background: #f0fdf4;
}

.job-action-button.is-danger:hover {
    border-color: var(--jobs-danger);
    background: #fef2f2;
}

.job-action-button.is-danger i {
    color: var(--jobs-danger);
}

.job-action-button.is-restore:hover {
    border-color: var(--jobs-primary);
    background: #f0fdf4;
}

.job-action-button.is-restore i {
    color: var(--jobs-primary);
}

.job-card__actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    border-top: 1px dashed var(--jobs-border);
    padding-top: 0.9rem;
}

.job-card__left-actions {
    display: flex;
    gap: 0.45rem;
    flex-wrap: wrap;
}

.table-message {
    padding: 2rem;
    text-align: center;
    font-weight: 600;
}

.table-message.error {
    color: #dc2626;
}

.empty-state {
    text-align: center;
    padding: 3rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    align-items: center;
}

.empty-state h3 {
    margin: 0;
    font-size: 1.2rem;
    color: var(--jobs-heading);
}

.empty-state p {
    margin: 0;
    color: var(--jobs-muted);
}

.empty-state.subtle {
    padding: 2rem 1rem;
}

.btn.ghost {
    background: transparent;
    color: var(--jobs-accent);
    border: 1px solid var(--jobs-border);
}

.job-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.job-modal.is-active {
    display: flex;
}

.job-modal__backdrop {
    position: absolute;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(4px);
}

.job-modal__dialog {
	position: relative;
	background: #fff;
	border-radius: 1.25rem;
	box-shadow: 0 30px 100px rgba(15, 23, 42, 0.25);
	width: min(960px, 92%);
	max-height: 90vh;
	overflow-y: auto;
	padding: 1.75rem;
	z-index: 1;
	font-family: "Roboto", "Segoe UI", Tahoma, sans-serif;
}

.job-modal__dialog--view {
	width: min(1100px, 95%);
}

.view-job-title {
	color: var(--jobs-heading);
	font-size: 1.5rem;
	margin: 0;
}

.view-job-subtitle {
	color: var(--jobs-muted);
	font-size: 0.9rem;
	margin-top: 0.25rem;
}

.view-job-content {
	display: flex;
	flex-direction: column;
	gap: 2rem;
}

.view-section {
	border: 1px solid var(--jobs-border);
	border-radius: 1rem;
	padding: 1.5rem;
	background: #fafbfc;
}

.view-section h3 {
	margin: 0 0 1.25rem;
	color: var(--jobs-heading);
	font-size: 1.1rem;
	font-weight: 600;
	border-bottom: 2px solid var(--jobs-primary);
	padding-bottom: 0.5rem;
}

.view-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
	gap: 1.25rem;
}

.view-field {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

.view-label {
	font-size: 0.8rem;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	color: var(--jobs-muted);
	font-weight: 600;
}

.view-value {
	font-size: 1rem;
	color: var(--jobs-heading);
	font-weight: 500;
	padding: 0.75rem;
	background: #ffffff;
	border-radius: 0.5rem;
	border: 1px solid var(--jobs-border);
}

.view-text {
	padding: 1rem;
	background: #ffffff;
	border-radius: 0.75rem;
	border: 1px solid var(--jobs-border);
	color: var(--jobs-heading);
	line-height: 1.6;
	white-space: pre-wrap;
	min-height: 80px;
}

.view-skills-container {
	display: flex;
	flex-wrap: wrap;
	gap: 0.75rem;
	padding: 0.5rem;
	background: #ffffff;
	border-radius: 0.75rem;
	border: 1px solid var(--jobs-border);
	min-height: 60px;
}

.view-skill-tag {
	display: inline-flex;
	align-items: center;
	padding: 0.5rem 1rem;
	background: linear-gradient(135deg, var(--jobs-primary) 0%, #2d8a44 100%);
	color: #ffffff;
	border-radius: 999px;
	font-size: 0.9rem;
	font-weight: 500;
	box-shadow: 0 2px 8px rgba(52, 168, 83, 0.2);
	transition: all 0.2s ease;
}

.view-skill-tag:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(52, 168, 83, 0.3);
}

.view-loading {
	color: var(--jobs-muted);
	font-style: italic;
	margin: 0;
	padding: 0.5rem;
}

.view-no-skills {
	color: var(--jobs-muted);
	margin: 0;
	padding: 0.5rem;
}.job-modal__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.2rem;
}

.job-modal__header h2 {
    margin: 0;
    color: var(--jobs-heading);
}

.job-modal__subtitle {
    margin: 0.35rem 0 0;
    color: var(--jobs-muted);
}

.modal-close {
    border: none;
    background: transparent;
    font-size: 2rem;
    line-height: 1;
    color: var(--jobs-muted);
    cursor: pointer;
}

.job-modal__form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.modal-sections {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.modal-sections section {
    border: 1px solid var(--jobs-border);
    border-radius: 1rem;
    padding: 1.25rem;
    background: #fdfefe;
    box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.02);
}

.modal-sections h3 {
    margin: 0 0 1rem;
    color: var(--jobs-heading);
}

.modal-sections .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.modal-sections .form-field {
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}

.modal-sections input,
.modal-sections select,
.modal-sections textarea {
    border: 1px solid var(--jobs-border);
    border-radius: 0.85rem;
    padding: 0.8rem 0.95rem;
    font-family: inherit;
    font-size: 0.95rem;
}

.modal-sections textarea {
    resize: vertical;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.modal-alert {
    display: none;
    padding: 0.85rem 1rem;
    border-radius: 0.85rem;
    background: rgba(228, 84, 98, 0.12);
    color: var(--jobs-danger);
    font-weight: 600;
}

.modal-alert.is-visible {
    display: block;
}

.required {
    color: var(--jobs-danger);
}

.is-modal-open {
    overflow: hidden;
}

.search-field {
    position: relative;
}

.clear-search {
    position: absolute;
    right: 1rem;
    color: var(--jobs-muted);
    text-decoration: none;
    font-size: 1.3rem;
    top: 50%;
    transform: translateY(-50%);
}

/* Floating Action Button for Mobile */
.fab-button {
    display: none;
    position: fixed;
    bottom: 90px;
    right: 1.5rem;
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #34a853 0%, #2d9249 100%);
    border-radius: 50%;
    box-shadow: 0 4px 16px rgba(52, 168, 83, 0.4);
    color: #ffffff;
    font-size: 1.5rem;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}

.fab-button:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(52, 168, 83, 0.5);
}

.fab-button:active {
    transform: scale(0.95);
}

.fab-button i {
    display: flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 680px) {
  .fab-button {
    display: flex;
  }
  
  .jobs-dashboard__header-flex {
    flex-direction: column;
    align-items: stretch;
    gap: 0.8rem;
    margin-bottom: 1.2rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--jobs-border);
  }
  
  .bulk-actions-bar {
    padding: 0.7rem 0.7rem 0.5rem 0.7rem;
    border-radius: 16px 16px 0 0;
  }
  .bulk-actions-content {
    flex-direction: column;
    align-items: stretch;
    gap: 0.7rem;
  }
  .bulk-selected-count {
    font-size: 1rem;
    margin-bottom: 0.3rem;
    text-align: center;
  }
  .bulk-actions-buttons {
    flex-direction: row;
    gap: 0.5rem;
    width: 100%;
    justify-content: center;
    align-items: center;
  }
  .btn-bulk {
    width: 120px;
    font-size: 0.95rem;
    padding: 0.5rem 0.5rem;
    border-radius: 999px;
    box-shadow: 0 2px 8px rgba(102,126,234,0.10);
    min-width: 80px;
    max-width: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }
  .btn-bulk i {
    font-size: 1.1rem;
    margin-right: 0.4rem;
  }
  
  /* Improved Mobile Job Cards */
  .jobs-dashboard {
    padding: 1rem 0.75rem 3rem;
  }
  
  .jobs-list {
    padding: 0.75rem;
    gap: 1rem;
  }
  
  .job-card {
    padding: 1.25rem 1rem;
    gap: 1.15rem;
    border-radius: 1rem;
  }
  
  .job-card__top {
    flex-direction: column;
    gap: 0.85rem;
    align-items: stretch;
  }
  
  .job-card__title-row {
    flex-direction: row;
    align-items: flex-start;
    gap: 0.65rem;
  }
  
  .job-card__title .job-title {
    font-size: 1.1rem;
    line-height: 1.4;
  }
  
  .job-card__title .job-meta {
    margin-top: 0.4rem;
    font-size: 0.875rem;
    line-height: 1.5;
  }
  
  .status-pill {
    align-self: flex-start;
    font-size: 0.8rem;
    padding: 0.35rem 0.85rem;
  }
  
  .job-card__tags {
    gap: 0.5rem;
  }
  
  .tag-chip {
    font-size: 0.8rem;
    padding: 0.35rem 0.85rem;
  }
  
  .job-card__grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }
  
  .job-stat {
    padding: 1rem 0.85rem;
    border-radius: 0.85rem;
  }
  
  .job-stat__label {
    font-size: 0.7rem;
    letter-spacing: 0.08em;
  }
  
  .job-stat__value {
    margin: 0.4rem 0 0.25rem;
    font-size: 1.05rem;
  }
  
  .job-stat__hint {
    font-size: 0.75rem;
    margin-top: 0.15rem;
  }
  
  .job-card__actions {
    padding-top: 1rem;
    justify-content: center;
  }
  
  .job-action-button {
    width: 40px;
    height: 40px;
    margin-right: 0.5rem;
  }
  
  .job-action-button:last-child {
    margin-right: 0;
  }
  
  .job-action-button i {
    font-size: 1.1rem;
  }
  
  .checkbox-custom {
    width: 22px;
    height: 22px;
    border-width: 2.5px;
  }
  
  .job-checkbox:checked + .checkbox-custom::after {
    left: 6.5px;
    top: 2px;
    width: 6px;
    height: 11px;
  }
}

/* SweetAlert2 Custom Font */
.swal2-popup,
.swal2-title,
.swal2-html-container,
.swal2-confirm,
.swal2-cancel,
.swal2-actions button {
  font-family: "Roboto", "Segoe UI", Tahoma, sans-serif !important;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editJobModal');
    if (!modal) {
        return;
    }

    const formMeta = <?php 
        $metaData = [
            'types' => $jobTypeLookup,
            'workSetups' => $workSetupOptions,
            'cities' => $cityLookup,
            'barangays' => $barangayOptions,
            'experienceLevels' => $experienceLookup,
            'educationLevels' => $educationLookup,
        ];
        echo json_encode($metaData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    ?>;

    const form = document.getElementById('editJobForm');
    const alertBox = modal.querySelector('.modal-alert');
    const submitBtn = form.querySelector('button[type="submit"]');
    const defaultSubmitLabel = submitBtn.textContent;
    const body = document.body;
    const jobCards = new Map();
    const deleteButtons = document.querySelectorAll('.js-delete-job');

    document.querySelectorAll('.job-card[data-job-id]').forEach((card) => {
        jobCards.set(card.dataset.jobId, card);
    });

    function toggleAlert(message) {
        if (message) {
            alertBox.textContent = message;
            alertBox.classList.add('is-visible');
        } else {
            alertBox.textContent = '';
            alertBox.classList.remove('is-visible');
        }
    }

    function safeParse(payload) {
        try {
            return JSON.parse(payload);
        } catch (_) {
            return null;
        }
    }

    function setField(name, value) {
        const field = form.elements[name];
        if (!field) return;
        field.value = value ?? '';
    }

    function normalizeLocationStreet(value) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        const trimmed = value.trim();
        if (!trimmed.includes(',')) {
            return trimmed;
        }
        const segments = trimmed.split(',').map((segment) => segment.trim()).filter(Boolean);
        if (!segments.length) {
            return '';
        }
        return segments[segments.length - 1];
    }

    const citySelect = form.elements['city_mun_id'];
    const barangaySelect = form.elements['barangay_id'];

    function populateBarangays(cityId, selectedId = '') {
        if (!barangaySelect) return;
        const options = formMeta.barangays?.[String(cityId)] || [];
        barangaySelect.innerHTML = '<option value="">Select barangay (optional)</option>';
        if (!options.length) {
            barangaySelect.disabled = true;
            return;
        }
        options.forEach(({ id, name }) => {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            if (String(id) === String(selectedId)) {
                opt.selected = true;
            }
            barangaySelect.appendChild(opt);
        });
        barangaySelect.disabled = false;
    }

    if (citySelect) {
        citySelect.addEventListener('change', () => {
            populateBarangays(citySelect.value || '');
            if (barangaySelect) {
                barangaySelect.value = '';
            }
        });
    }

    function fillModal(job) {
        setField('job_post_id', job.id);
        setField('job_post_name', job.title);
        setField('job_type_id', job.typeId || '');
        setField('work_setup_id', job.workSetupId ? String(job.workSetupId) : '');
        setField('city_mun_id', job.cityMunId || '');
        populateBarangays(job.cityMunId || '', job.barangayId || '');
        setField('barangay_id', job.barangayId || '');
		const streetValue = normalizeLocationStreet(job.locationStreet ?? job.location_street ?? '');
		setField('location_street', streetValue);
        setField('vacancies', job.vacancies || 1);
        setField('budget', job.budget !== null && job.budget !== undefined ? job.budget : '');
        setField('experience_level_id', job.experienceLevelId ? String(job.experienceLevelId) : '');
        setField('education_level_id', job.educationLevelId ? String(job.educationLevelId) : '');
        setField('job_description', job.description);
		setField('requirements', job.requirements || '');
        setField('benefits', job.benefits);
    }

	function buildPayload(job) {
		const rawStreet = job.location_street ?? job.locationStreet ?? '';
		const addressLine = job.address_line ?? job.addressLine ?? '';
		return {
            id: Number(job.job_post_id),
            job_post_id: Number(job.job_post_id),
            title: job.job_post_name,
            job_post_name: job.job_post_name,
            typeId: Number(job.job_type_id),
            job_type_id: Number(job.job_type_id),
            job_type_name: job.job_type_name,
            vacancies: Number(job.vacancies),
            workSetupId: Number(job.work_setup_id ?? 0),
            work_setup_id: Number(job.work_setup_id ?? 0),
			workSetupName: job.work_setup_name ?? '',
			work_setup_name: job.work_setup_name ?? '',
			locationStreet: normalizeLocationStreet(rawStreet),
			location_street: rawStreet,
			addressLine,
			address_line: addressLine,
            cityMunId: Number(job.city_mun_id ?? 0),
            city_mun_id: Number(job.city_mun_id ?? 0),
            cityMunName: job.city_mun_name ?? '',
            city_mun_name: job.city_mun_name ?? '',
            barangayId: Number(job.barangay_id ?? 0),
            barangay_id: Number(job.barangay_id ?? 0),
            barangayName: job.barangay_name ?? '',
            barangay_name: job.barangay_name ?? '',
            budget: job.budget,
            description: job.job_description,
            job_description: job.job_description,
			requirements: job.requirements ?? '',
            benefits: job.benefits,
            experienceLevelId: Number(job.experience_level_id ?? 0),
            experience_level_id: Number(job.experience_level_id ?? 0),
            experienceLevelName: job.experience_level_name ?? '',
            experience_level_name: job.experience_level_name ?? '',
            educationLevelId: Number(job.education_level_id ?? 0),
            education_level_id: Number(job.education_level_id ?? 0),
            educationLevelName: job.education_level_name ?? '',
            education_level_name: job.education_level_name ?? '',
			job_category_name: job.job_category_name ?? '',
        };
    }

    function formatBudget(budget) {
        if (budget === null || budget === undefined || budget === '') {
            return 'Budget TBD';
        }
        return 'PHP ' + Number(budget).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatSetup(id) {
        if (id === null || id === undefined || id === '') {
            return 'Flexible';
        }
        const lookup = formMeta.workSetups || {};
        const key = String(id);
        return lookup[key] || lookup[id] || 'Flexible';
    }

    function openModal(job) {
        fillModal(job);
        modal.classList.add('is-active');
        body.classList.add('is-modal-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('is-active');
        body.classList.remove('is-modal-open');
        modal.setAttribute('aria-hidden', 'true');
        form.reset();
        toggleAlert();
    }

    function refreshCard(job) {
        const card = jobCards.get(String(job.job_post_id));
        if (!card) {
            return;
        }
        const titleEl = card.querySelector('.job-title');
        if (titleEl) {
            titleEl.textContent = job.job_post_name;
        }
		const metaEl = card.querySelector('[data-field="job-meta"]');
		if (metaEl) {
			const typeLabel = job.job_type_name || formMeta.types[job.job_type_id] || '';
			const locationLabel = job.address_line || job.location_street || job.city_mun_name || job.barangay_name || '';
			const metaParts = [];
			if (typeLabel) {
				metaParts.push(typeLabel);
			}
			if (locationLabel) {
				metaParts.push(locationLabel);
			}
			metaEl.textContent = metaParts.join(' · ');
        }
        const categoryEl = card.querySelector('[data-field="job-category"]');
		if (categoryEl && job.job_category_name) {
			categoryEl.textContent = job.job_category_name;
        }
        const setupEl = card.querySelector('[data-field="setup"]');
        if (setupEl) {
            setupEl.textContent = formatSetup(job.work_setup_id ?? job.workSetupId);
        }
        const vacancyEl = card.querySelector('[data-field="vacancies"]');
        if (vacancyEl) {
            vacancyEl.textContent = Number(job.vacancies || 0).toLocaleString();
        }
        const budgetEl = card.querySelector('[data-field="budget"]');
        if (budgetEl) {
            budgetEl.textContent = formatBudget(job.budget);
        }
        const editBtn = card.querySelector('.js-edit-job');
        if (editBtn) {
            editBtn.dataset.job = JSON.stringify(buildPayload(job));
        }
    }

	async function deleteJob(jobId, button) {
		button.disabled = true;
		Swal.fire({
			title: 'Deleting job...',
			allowOutsideClick: false,
			allowEscapeKey: false,
			showConfirmButton: false,
			didOpen: () => {
				Swal.showLoading();
			},
		});
		try {
			const formData = new FormData();
			formData.append('job_post_id', jobId);
			const response = await fetch('delete_job.php', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const result = await response.json();
			if (!result.success) {
				throw new Error(result.message || 'Unable to delete job.');
			}
			Swal.close();
			return result;
		} finally {
			button.disabled = false;
		}
	}

	function confirmDelete(button) {
		const card = button.closest('.job-card');
		const jobId = card ? card.dataset.jobId : null;
		if (!jobId) {
			return;
		}
		Swal.fire({
			title: 'Delete this job?',
			text: 'This action will permanently remove the job post.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#e45462',
			cancelButtonColor: '#6b7280',
			confirmButtonText: 'Yes, delete it',
			cancelButtonText: 'Cancel',
		}).then(async (result) => {
			if (!result.isConfirmed) {
				return;
			}
			try {
				await deleteJob(jobId, button);
				Swal.fire({
					icon: 'success',
					title: 'Job deleted',
					text: 'The posting has been removed successfully.',
				}).then(() => {
					window.location.reload();
				});
			} catch (error) {
				Swal.close();
				Swal.fire({
					icon: 'error',
					title: 'Delete failed',
					text: error.message || 'Unable to delete this job right now.',
				});
			}
		});
	}

	async function restoreJob(jobId, button) {
		button.disabled = true;
		Swal.fire({
			title: 'Restoring job...',
			allowOutsideClick: false,
			allowEscapeKey: false,
			showConfirmButton: false,
			didOpen: () => {
				Swal.showLoading();
			},
		});
		try {
			const formData = new FormData();
			formData.append('job_ids', JSON.stringify([jobId]));
			formData.append('action', 'restore');
			const response = await fetch('bulk_job_action.php', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const result = await response.json();
			if (!result.success) {
				throw new Error(result.message || 'Unable to restore job.');
			}
			Swal.close();
			return result;
		} finally {
			button.disabled = false;
		}
	}

	function confirmRestore(button) {
		const card = button.closest('.job-card');
		const jobId = card ? card.dataset.jobId : null;
		if (!jobId) {
			return;
		}
		Swal.fire({
			title: 'Restore this job?',
			text: 'This will move the job back to active listings.',
			icon: 'question',
		 showCancelButton: true,
			confirmButtonColor: '#10b981',
			cancelButtonColor: '#6b7280',
			confirmButtonText: 'Yes, restore it',
			cancelButtonText: 'Cancel',
		}).then(async (result) => {
			if (!result.isConfirmed) {
				return;
			}
			try {
				await restoreJob(jobId, button);
				Swal.fire({
					icon: 'success',
					title: 'Job restored',
					text: 'The job has been restored successfully.',
				}).then(() => {
					window.location.reload();
				});
			} catch (error) {
				Swal.close();
				Swal.fire({
					icon: 'error',
					title: 'Restore failed',
					text: error.message || 'Unable to restore this job right now.',
				});
			}
		});
	}

	async function handleSubmit(event) {
		event.preventDefault();
		if (!form.reportValidity()) {
			return;
		}
		const formData = new FormData(form);
		submitBtn.disabled = true;
		submitBtn.textContent = 'Saving...';
		toggleAlert();
		try {
			const response = await fetch('update_job_post.php', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});
			const result = await response.json();
			if (!result.success) {
				throw new Error(result.message || 'Unable to update job.');
			}
			try {
				refreshCard(result.job);
			} catch (refreshError) {
				console.error('Error refreshing card display:', refreshError);
			}
			closeModal();
		} catch (error) {
			toggleAlert(error.message || 'Something went wrong.');
		} finally {
			submitBtn.disabled = false;
			submitBtn.textContent = defaultSubmitLabel;
		}
	}

	form.addEventListener('submit', handleSubmit);

	deleteButtons.forEach((button) => {
		button.addEventListener('click', () => confirmDelete(button));
	});

	const restoreButtons = document.querySelectorAll('.js-restore-job');
	restoreButtons.forEach((button) => {
		button.addEventListener('click', () => confirmRestore(button));
	});

	document.querySelectorAll('.js-edit-job').forEach((button) => {
		button.addEventListener('click', () => {
			const payload = safeParse(button.dataset.job);
			if (!payload) {
				return;
			}
			openModal(payload);
		});
	});

	modal.querySelectorAll('[data-close-modal]').forEach((btn) => {
		btn.addEventListener('click', closeModal);
	});

	// ============================================
	// VIEW JOB DETAILS MODAL
	// ============================================
	const viewModal = document.getElementById('viewJobModal');
	
	function openViewModal(job) {
		// Set title and subtitle
		document.querySelector('.view-job-title').textContent = job.title || 'Job Details';
		document.querySelector('.view-job-subtitle').textContent = job.job_category_name || '';

		// Basic Information
		const jobTypeName = formMeta.types[job.typeId] || job.job_type_name || 'Unspecified';
		document.getElementById('view-job-type').textContent = jobTypeName;
		document.getElementById('view-work-setup').textContent = job.workSetupName || formatSetup(job.workSetupId);
		document.getElementById('view-vacancies').textContent = Number(job.vacancies || 0).toLocaleString();
		document.getElementById('view-budget').textContent = formatBudget(job.budget);
		document.getElementById('view-category').textContent = job.job_category_name || 'Uncategorized';
		
		// Location - handle both direct DB fields and normalized payload
		const locationParts = [];
		// Prefer addressLine if available (formatted address)
		if (job.addressLine) {
			locationParts.push(job.addressLine);
		} else {
			// Build from individual components
			if (job.locationStreet || job.location_street) {
				locationParts.push(job.locationStreet || job.location_street);
			}
			if (job.barangayName || job.barangay_name) {
				locationParts.push(job.barangayName || job.barangay_name);
			}
			if (job.cityMunName || job.city_mun_name) {
				locationParts.push(job.cityMunName || job.city_mun_name);
			}
		}
		document.getElementById('view-location').textContent = locationParts.length > 0 ? locationParts.join(', ') : '—';

		// Requirements - handle both camelCase and snake_case
		document.getElementById('view-experience').textContent = job.experienceLevelName || job.experience_level_name || '—';
		document.getElementById('view-education').textContent = job.educationLevelName || job.education_level_name || '—';

		// Descriptions - handle both property names
		const description = job.description || job.job_description || '';
		document.getElementById('view-description').textContent = description || 'No description provided.';
		document.getElementById('view-requirements').textContent = job.requirements || 'No requirements specified.';
		document.getElementById('view-benefits').textContent = job.benefits || 'No benefits listed.';

		// Fetch and display skills
		fetchJobSkills(job.id || job.job_post_id);

		viewModal.classList.add('is-active');
		body.classList.add('is-modal-open');
		viewModal.setAttribute('aria-hidden', 'false');
	}

	async function fetchJobSkills(jobPostId) {
		const skillsContainer = document.getElementById('view-skills');
		const categoryField = document.getElementById('view-category');
		const subtitleField = document.querySelector('.view-job-subtitle');
		skillsContainer.innerHTML = '<p class="view-loading">Loading skills...</p>';

		try {
			const response = await fetch(`get_job_skills.php?job_post_id=${jobPostId}`, {
				method: 'GET',
				headers: { 'X-Requested-With': 'XMLHttpRequest' },
			});

			const data = await response.json();

			if (!data.success) {
				throw new Error(data.message || 'Failed to load skills');
			}

			// Update job category if available from skills
			if (data.job_category_name && data.job_category_name !== 'Uncategorized') {
				categoryField.textContent = data.job_category_name;
				subtitleField.textContent = data.job_category_name;
			}

			if (data.skills.length === 0) {
				skillsContainer.innerHTML = '<p class="view-no-skills">No skills specified for this job.</p>';
			} else {
				skillsContainer.innerHTML = data.skills.map(skill => 
					`<span class="view-skill-tag" title="Category: ${skill.job_category_name}">${skill.skill_name}</span>`
				).join('');
			}
		} catch (error) {
			console.error('Error fetching skills:', error);
			skillsContainer.innerHTML = '<p class="view-no-skills">Unable to load skills.</p>';
		}
	}

	function closeViewModal() {
		viewModal.classList.remove('is-active');
		body.classList.remove('is-modal-open');
		viewModal.setAttribute('aria-hidden', 'true');
	}

	// View job button click handlers
	document.querySelectorAll('.js-view-job').forEach((button) => {
		button.addEventListener('click', () => {
			const payload = safeParse(button.dataset.job);
			if (!payload) {
				return;
			}
			openViewModal(payload);
		});
	});

	viewModal.querySelectorAll('[data-close-view-modal]').forEach((btn) => {
		btn.addEventListener('click', closeViewModal);
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && viewModal.classList.contains('is-active')) {
			closeViewModal();
		}
	});

	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && modal.classList.contains('is-active')) {
			closeModal();
		}
	});

    // ============================================
    // BULK ACTIONS FUNCTIONALITY
    // ============================================
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const jobCheckboxes = document.querySelectorAll('.job-checkbox');
    const bulkActionsBar = document.getElementById('bulkActionsBar');
    const selectedCountSpan = document.getElementById('selectedCount');
    const bulkArchiveBtn = document.getElementById('bulkArchiveBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    let selectedJobs = new Set();

    function updateBulkActionsBar() {
        const count = selectedJobs.size;
        selectedCountSpan.textContent = `${count} selected`;
        
        if (count > 0) {
            bulkActionsBar.style.display = 'block';
        } else {
            bulkActionsBar.style.display = 'none';
        }

        // Update "Select All" checkbox state
        if (count === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (count === jobCheckboxes.length) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Handle individual checkbox changes
    jobCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const jobId = checkbox.dataset.jobId;
            if (checkbox.checked) {
                selectedJobs.add(jobId);
            } else {
                selectedJobs.delete(jobId);
            }
            updateBulkActionsBar();
        });
    });

    // Handle "Select All" checkbox
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', () => {
            const isChecked = selectAllCheckbox.checked;
            jobCheckboxes.forEach((checkbox) => {
                checkbox.checked = isChecked;
                const jobId = checkbox.dataset.jobId;
                if (isChecked) {
                    selectedJobs.add(jobId);
                } else {
                    selectedJobs.delete(jobId);
                }
            });
            updateBulkActionsBar();
        });
    }

    // Bulk Archive
    if (bulkArchiveBtn) {
        bulkArchiveBtn.addEventListener('click', async () => {
            if (selectedJobs.size === 0) return;

            const result = await Swal.fire({
                title: `Archive ${selectedJobs.size} job(s)?`,
                text: 'Archived jobs will no longer appear in active listings.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, archive them',
                cancelButtonText: 'Cancel',
            });

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Archiving jobs...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const formData = new FormData();
                formData.append('job_ids', JSON.stringify([...selectedJobs]));
                formData.append('action', 'archive');

                const response = await fetch('bulk_job_action.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to archive jobs');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Jobs archived',
                    text: `${selectedJobs.size} job(s) have been archived successfully.`,
                }).then(() => {
                    window.location.reload();
                });
            } catch (error) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Archive failed',
                    text: error.message || 'Unable to archive jobs right now.',
                });
            }
        });
    }

    // Bulk Delete
    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', async () => {
            if (selectedJobs.size === 0) return;

            const result = await Swal.fire({
                title: `Delete ${selectedJobs.size} job(s)?`,
                text: 'This action will permanently remove the selected job posts.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e45462',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete them',
                cancelButtonText: 'Cancel',
            });

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Deleting jobs...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const formData = new FormData();
                formData.append('job_ids', JSON.stringify([...selectedJobs]));
                formData.append('action', 'delete');

                const response = await fetch('bulk_job_action.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to delete jobs');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Jobs deleted',
                    text: `${selectedJobs.size} job(s) have been deleted successfully.`,
                }).then(() => {
                    window.location.reload();
                });
            } catch (error) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Delete failed',
                    text: error.message || 'Unable to delete jobs right now.',
                });
            }
        });
    }

    // Bulk Restore
    const bulkRestoreBtn = document.getElementById('bulkRestoreBtn');
    if (bulkRestoreBtn) {
        bulkRestoreBtn.addEventListener('click', async () => {
            if (selectedJobs.size === 0) return;

            const result = await Swal.fire({
                title: `Restore ${selectedJobs.size} job(s)?`,
                text: 'These jobs will be moved back to active listings.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, restore them',
                cancelButtonText: 'Cancel',
            });

            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Restoring jobs...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
            });

            try {
                const formData = new FormData();
                formData.append('job_ids', JSON.stringify([...selectedJobs]));
                formData.append('action', 'restore');

                const response = await fetch('bulk_job_action.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to restore jobs');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Jobs restored',
                    text: `${selectedJobs.size} job(s) have been restored successfully.`,
                }).then(() => {
                    window.location.reload();
                });
            } catch (error) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Restore failed',
                    text: error.message || 'Unable to restore jobs right now.',
                });
            }
        });
    }

    // ============================================
    // LIVE SEARCH FUNCTIONALITY
    // ============================================
    const liveSearchInput = document.getElementById('liveSearchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const allJobCards = document.querySelectorAll('.job-card');
    const jobsList = document.querySelector('.jobs-list');

    function performLiveSearch() {
        const searchTerm = liveSearchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        // Show/hide clear button
        if (searchTerm.length > 0) {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }

        allJobCards.forEach((card) => {
            const title = (card.querySelector('.job-title')?.textContent || '').toLowerCase();
            const category = (card.querySelector('.job-meta')?.textContent || '').toLowerCase();
            const location = (card.dataset.location || '').toLowerCase();
            const company = (card.dataset.company || '').toLowerCase();
            
            const searchableText = `${title} ${category} ${location} ${company}`;
            
            if (searchTerm === '' || searchableText.includes(searchTerm)) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Handle empty state
        let emptyStateEl = document.getElementById('liveSearchEmpty');
        
        if (visibleCount === 0 && searchTerm !== '') {
            if (!emptyStateEl) {
                emptyStateEl = document.createElement('div');
                emptyStateEl.className = 'empty-state subtle';
                emptyStateEl.id = 'liveSearchEmpty';
                emptyStateEl.innerHTML = `
                    <h3>No matches found</h3>
                    <p>No jobs match "${searchTerm}". Try different keywords.</p>
                `;
                jobsList.appendChild(emptyStateEl);
            }
        } else {
            if (emptyStateEl) {
                emptyStateEl.remove();
            }
        }
    }

    if (liveSearchInput) {
        // Debounce search for better performance
        let searchTimeout;
        liveSearchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performLiveSearch, 300);
        });

        // Clear search button
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', (e) => {
                e.preventDefault();
                liveSearchInput.value = '';
                performLiveSearch();
                liveSearchInput.focus();
            });
        }

        // Initial search if there's a value
        if (liveSearchInput.value.trim() !== '') {
            clearSearchBtn.style.display = 'block';
        }
    }
});
</script>