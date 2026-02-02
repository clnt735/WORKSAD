<?php
/*
README — Mobile swipe deck (Dec 2025)
- CSS overrides live in the inline <style> block under “Mobile swipe deck”.
- JavaScript controller sits at the bottom script tagged “Swipe Deck Controller”.
- To test: load at ≤600px, verify stacked cards, swipe left/right, use the action buttons, undo inside 5s, and confirm the browser posts to interactions.php for each action.
- Desktop (width >600px) should render unchanged grid/list layouts.
- SQL helper (only if missing):
    CREATE TABLE IF NOT EXISTS applicant_job_swipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT NOT NULL,
        job_post_id INT NOT NULL,
        swipe_type ENUM('like','dislike') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_applicant_job (applicant_id, job_post_id)
    );
*/

session_start();

$user_id = $_SESSION['user_id'] ?? null;

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/job_matching.php';
// `database.php` exposes a mysqli instance in `$conn`.
// Use that existing connection instead of a missing `db_connect()` function.


// =======================================
// GET MATCHED JOB IDs (IN MATCH ORDER)
// =======================================

$matchData = getMatchedJobs($conn, $user_id);
$matchedJobs = $matchData['jobs'];

// Create lookup array for match percentages  
$matchPercentages = [];
foreach ($matchedJobs as $job) {
    $matchPercentages[$job['job_post_id']] = $job['match_percentage'];
}

// This returns: [ [job_post_id, match_score], .... ]
$matchedJobIds = array_column($matchData['jobs'], 'job_post_id');


// Input filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$location = isset($_GET['location']) ? trim($_GET['location']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
// Additional server-side filters (keep in sync with client-side URL params)
$industry = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$minSalaryParam = isset($_GET['minSalary']) ? trim($_GET['minSalary']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 4;
$offset = ($page - 1) * $perPage;

// Build where clauses
$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(job_post_name LIKE ? OR job_description LIKE ? OR company_name LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}
if ($location !== '') {
    $where[] = "location LIKE ?";
    $locLike = "%$location%";
    $params[] = $locLike;
    $types .= 's';
}
if ($type !== '') {
    // job_post stores a numeric job_type_id; filter by that column
    $where[] = "jp.job_type_id = ?";
    $params[] = (int)$type;
    $types .= 'i';
}

if ($industry !== '') {
    // company.industry exists in the company table (joined below)
    $where[] = "c.industry = ?";
    $params[] = $industry;
    $types .= 's';
}

if ($minSalaryParam !== '') {
    // Filter posts where either salary_max, salary_min, or budget meets the minimum
    $minSalary = (int)$minSalaryParam;
    $where[] = "( (jp.salary_max IS NOT NULL AND jp.salary_max >= ?) OR (jp.salary_min IS NOT NULL AND jp.salary_min >= ?) OR (jp.budget IS NOT NULL AND jp.budget >= ?) )";
    $params[] = $minSalary; $params[] = $minSalary; $params[] = $minSalary;
    $types .= 'iii';
}

$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM job_post");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}

// Rebuild WHERE/params/types using actual available columns to avoid referencing missing columns
$where = [];
$params = [];
$types = '';

if ($q !== '') {
    $where[] = "(jp.job_post_name LIKE ? OR jp.job_description LIKE ? OR c.company_name LIKE ?)";
    $like = "%$q%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= 'sss';
}

if ($location !== '') {
    // job_post may use 'location' or 'job_location'
    if (in_array('location', $cols)) {
        $where[] = "jp.location LIKE ?";
    } elseif (in_array('job_location', $cols)) {
        $where[] = "jp.job_location LIKE ?";
    } else {
        // fallback to company.location if job location not present
        $where[] = "c.location LIKE ?";
    }
    $locLike = "%$location%";
    $params[] = $locLike;
    $types .= 's';
}

if ($type !== '') {
    if (in_array('job_type_id', $cols)) {
        $where[] = "jp.job_type_id = ?";
        $params[] = (int)$type;
        $types .= 'i';
    } elseif (in_array('job_type', $cols)) {
        $where[] = "jp.job_type = ?";
        $params[] = $type;
        $types .= 's';
    }
}

if ($industry !== '') {
    // company.industry exists in the company table (joined below)
    $where[] = "c.industry = ?";
    $params[] = $industry;
    $types .= 's';
}

if ($minSalaryParam !== '') {
    $minSalary = (int)$minSalaryParam;
    // prefer salary_min/salary_max if present
    if (in_array('salary_max', $cols) || in_array('salary_min', $cols)) {
        $smax = in_array('salary_max', $cols);
        $smin = in_array('salary_min', $cols);
        $clauses = [];
        if ($smax) $clauses[] = "(jp.salary_max IS NOT NULL AND jp.salary_max >= ?)";
        if ($smin) $clauses[] = "(jp.salary_min IS NOT NULL AND jp.salary_min >= ?)";
        if (in_array('budget', $cols)) $clauses[] = "(jp.budget IS NOT NULL AND jp.budget >= ?)";
        if (count($clauses)) {
            $where[] = '(' . implode(' OR ', $clauses) . ')';
            // add one param per clause
            for ($i = 0; $i < count($clauses); $i++) { $params[] = $minSalary; $types .= 'i'; }
        }
    } elseif (in_array('salary', $cols)) {
        $where[] = "(jp.salary IS NOT NULL AND jp.salary >= ?)";
        $params[] = $minSalary;
        $types .= 'i';
    } elseif (in_array('budget', $cols)) {
        $where[] = "(jp.budget IS NOT NULL AND jp.budget >= ?)";
        $params[] = $minSalary;
        $types .= 'i';
    }
}

// ============================================
// FILTER OUT JOBS ALREADY LIKED BY THIS USER
// Jobs in applicant_job_swipes should not appear in the swipe deck
// ============================================
if ($user_id) {
    $where[] = "jp.job_post_id NOT IN (SELECT job_post_id FROM applicant_job_swipes WHERE applicant_id = ?)";
    $params[] = (int)$user_id;
    $types .= 'i';
}

// ============================================
// FILTER OUT CLOSED JOBS AND JOBS WITH NO VACANCIES
// Only show jobs where:
// - job_status_id = 1 (Open) 
// - AND vacancy > 0
// job_status_id: 1=Open, 2=Closed, 3=Archived, 4=Deactivated
// ============================================
$where[] = "(jp.job_status_id IS NULL OR jp.job_status_id = 1)";
$where[] = "(jp.vacancies IS NULL OR jp.vacancies > 0)";

$where_sql = '';
if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

// ============================================
// 1) COUNT TOTAL JOBS (with all filters applied)
// ============================================
$countSql = "SELECT COUNT(*) AS total
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
$where_sql";

$countStmt = $conn->prepare($countSql);
if ($countStmt === false) {
    die("SQL ERROR: " . $conn->error . "<br>Query: " . $countSql);
}
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countRes = $countStmt->get_result();
$total = ($countRow = $countRes->fetch_assoc()) ? (int)$countRow['total'] : 0;
$countStmt->close();

// Calculate total pages
$totalPages = ($total > 0) ? (int)ceil($total / $perPage) : 1;

// ============================================
// 2) FETCH ALL MATCHING JOBS (for match sorting)
// ============================================
$dataSql = "
SELECT 
    jp.job_post_id,
    jp.job_post_name,
    jp.job_description,
    jp.requirements,
    jp.benefits,
    jp.budget,
    jp.vacancies,
    jp.job_location_id,
    jp.job_type_id,
    jp.job_status_id,
    jp.experience_level_id,
    jp.education_level_id,
    jp.work_setup_id,
    jp.created_at,
    jp.updated_at,
    c.company_id,
    c.company_name,
    c.industry,
    c.location AS company_location,
    c.logo AS company_logo,
    c.website,
    jt.job_type_name,
    ws.work_setup_name,
    el.experience_level_name,
    jpl.address_line AS job_address_line
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
LEFT JOIN job_type jt ON jp.job_type_id = jt.job_type_id
LEFT JOIN work_setup ws ON jp.work_setup_id = ws.work_setup_id
LEFT JOIN experience_level el ON jp.experience_level_id = el.experience_level_id
LEFT JOIN job_post_location jpl ON jp.job_post_id = jpl.job_post_id
$where_sql
ORDER BY jp.created_at DESC
";

$dataStmt = $conn->prepare($dataSql);
if ($dataStmt === false) {
    die("SQL ERROR: " . $conn->error . "<br>Query: " . $dataSql);
}

if ($types !== '') {
    $dataStmt->bind_param($types, ...$params);
}
$dataStmt->execute();
$dataRes = $dataStmt->get_result();
$allRows = $dataRes->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// ============================================
// 3) ADD MATCH PERCENTAGES & SORT
// ============================================
foreach ($allRows as &$row) {
    $row['match_percent'] = $matchPercentages[$row['job_post_id']] ?? 0;
}
unset($row);

// Sort by match percentage (highest first)
usort($allRows, function($a, $b) {
    return ($b['match_percent'] ?? 0) - ($a['match_percent'] ?? 0);
});

// ============================================
// 4) APPLY PAGINATION (slice the sorted array)
// ============================================
$rows = array_slice($allRows, $offset, $perPage);

// helper: escape
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Search Jobs</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
</head>
<style>
    
/* ==============================
   JOB SEEKER JOB SEARCH SECTION
============================== */

    .search-jobs-page {
    background-color: #fbf7fb;
    }

    body.search-jobs-page .page-layout.swipe-view ~ .pagination {
      display: none !important;
    }
    
    .search-jobs-page .layout { 
      display:flex; gap:20px; margin-top:18px; 
    }

    /* Sidebar filters */
    /* .filters-sidebar { 
      width:260px; 
      display:flex; 
      flex-direction:column; 
      gap:14px; 
    }

    .filter-card { 
      background:#fff; 
      border-radius:10px; 
      padding:12px; 
      border:1px solid #eef2ff; 
    }

    .filter-card h4 { 
      margin:0 0 8px; 
      font-size:15px; 
    }

    .filter-list { 
      display:flex; 
      flex-direction:column; 
      gap:8px; 
    } */

    .search-jobs-title {
    font-size: 22px;
    margin: 0 0 6px;
    color: #111827;
    margin-top: -20px;
    font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
    }
    
    .filters-panel {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* hides filters panel when hide filter is clicked */    
    .filters-panel.hidden {
    display: none;
    }

    .filter-box {
        background: #fff;
        border: 1px solid #e8e8e8;
        padding: 18px;
        border-radius: 12px;
    }

    .filter-box h4 {
        margin-bottom: 10px;
        font-weight: 600;
    }

    .filter-box label {
        display: flex;          /* aligns checkbox + text */
        align-items: center;
        gap: 10px;               /* spacing between checkbox & text */
        margin: 6px 0;
        font-size: 15px;
        cursor: pointer;
    }

    .filter-box label input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin: 0;              /* removes default weird left indent */
        accent-color: #444;
    }

    .salary-minmax {
        display: flex;
        justify-content: space-between;
        margin-top: 4px;
        font-size: 13px;
        color: #555;
    }

    .salary-value {
    margin-top: 6px;
    font-weight: 600;
    font-size: 14px;  
    }
      
    /* FULL WIDTH RANGE SLIDER (Edge/Chrome/Safari) */
    input[type="range"] {
        -webkit-appearance: none;
        appearance: none;
        width: 100%;
        background: transparent;
        padding: 0;
        margin: 0;
    }

    /* Track */
    input[type="range"]::-webkit-slider-runnable-track {
        height: 6px;
        background: linear-gradient(to right, #007bff var(--pos, 0%), #e6eefc var(--pos, 0%));
        border-radius: 6px;
    }

    /* Thumb */
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #007bff;
        cursor: pointer;
        margin-top: -6px; /* center thumb */
    }

    .clear-filters {
        margin-top: 10px;
        background: none;
        border: none;
        color: #2980b9;
        cursor: pointer;
    }


    /* Compact grid for job cards: use smaller, modern cards */
    .job-grid {
        position: relative;
        display: grid;
        /* Use compact card width and allow many columns as space permits */
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 10px;
        align-items: start;} /* prevent taller cards from stretching neighbors */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .job-card .swipe-overlay {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 18px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .job-card.dragging .swipe-overlay {
            transition: opacity 0.08s ease;
        }

        .job-card .swipe-label {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.04em;
            padding: 8px 14px;
            border-radius: 12px;
            text-transform: uppercase;
            border: 2px solid transparent;
            background: rgba(255,255,255,0.76);
            box-shadow: 0 12px 28px rgba(15,23,42,0.1);
        }

        .job-card .swipe-like {
            color: #047857;
            border-color: rgba(16,185,129,0.55);
        }

        .job-card .swipe-pass {
            color: #b91c1c;
            border-color: rgba(239,68,68,0.6);
        }

        .swipe-toast {
            position: fixed;
            left: 50%;
            bottom: 24px;
            transform: translateX(-50%) translateY(16px);
            background: rgba(17,24,39,0.95);
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 14px 35px rgba(15,23,42,0.32);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
            z-index: 1200;
        }

        .swipe-toast.visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        .mobile-swipe-actions {
            display: none !important;
        }

       

    .search-jobs-page .filters-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 20px 10px;
    }

    .search-jobs-page .view-toggle {  
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      
    }
    
    .search-jobs-page .view-label {
      color: #666;
      font-size: 14px;
    } 

    .search-top-row {
      display: flex;
      gap: 10px;
      margin: 20px 5px;
      max-width: 960px; 
      width: 100%;
      align-items: center; 
    }

    .search-input,
    .search-location {
        flex: 1;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #ddd;
    } 

    .search-location { 
      max-width: 240px; 
      padding: 13px 12px;
      height: 44px;
    }

    .search-btn {
        padding: 0 20px;
        background: #2ecc71;
        color: white;
        border-radius: 8px;
        height:42px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: -10px;
        
    }

    .btn-toggle-filters {
      padding: 10px 10px;
      background: #ffffff;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      display: flex;        /* aligns icon + text */
      align-items: center;  /* vertical centering */
      gap: 10px;
      margin-left: -5px;
    }


    .search-jobs-page .view-btn {
      width: 34px;
      height: 34px;
      border-radius: 8px;
      border: 1px solid #e5e5e5;
      background: #fff;
      color: black;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .search-jobs-page .view-btn i {
        font-size: 16px;
        color: #555;
    }

    /* Active style (green like screenshot) */
    .search-jobs-page .view-btn.active {
      background: #10b981; /* primary green */
      border-color: #10b981;
      color: #7e6969;
    }

    .search-jobs-page .view-btn.active i {
      color: #ffffff; /* active icon should be white on green */
    }

    /* Swipe-view layout: ensure no clipping on ancestors */
    .page-layout.swipe-view {
        overflow: visible;
    }

    /* Grid / List view helpers */
    /* Default: grid — compact card layout */
    .page-layout.grid-view .job-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-left: 0; }
    .page-layout.grid-view .job-card { display: flex; flex-direction: column; width: 100%; }
    .page-layout.grid-view .swipe-wrapper { display: contents; } /* unwrap in grid view */
    /* .page-layout.grid-view .swipe-actions { display: none; }  */

    /* Grid-specific card-photo sizing and inner padding to keep cards compact */
    .page-layout.grid-view .job-card.card-modern .card-photo {
        height: 60px;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
        background-size: cover;
        background-position: center;
    }
    .page-layout.grid-view .job-card.card-modern .card-content {
        padding: 8px;
        gap: 4px;
    }
    .page-layout.grid-view .job-card .info-grid { gap: 6px; }



    /* List view: compact horizontal rows */
    .page-layout.list-view .job-grid { display: block; gap: 8px; }
    .page-layout.list-view .job-card { 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        padding: 8px 10px;
        margin-bottom: 15px;
    }
    .page-layout.list-view .job-card .card-photo { 
        display: none;
    }
    .page-layout.list-view .job-card .match-badge {
        display: none;
    }
    .page-layout.list-view .job-card .card-content { 
        padding: 15px; 
        display: flex; 
        flex-direction: column; 
        gap: 4px; 
        width: 100%; 
    }
    .page-layout.list-view .job-card .card-header { 
        display: flex; 
        align-items: center; 
        gap: 6px; 
    }
    .page-layout.list-view .job-card .card-header h3 { 
        font-size: 15px; 
        margin: 0; 
    }
    .page-layout.list-view .job-card .meta-row { 
        font-size: 12px; 
        gap: 6px; 
        align-items: center; 
    }
    /* Push budget/salary to the far right in list rows (keeps layout compact) */
    .page-layout.list-view .job-card .meta-row .budget { 
        margin-left: auto; 
        font-weight: 700; 
    }
    .page-layout.list-view .job-card .info-grid { 
        display: flex; 
        gap: 8px; 
        align-items: center; 
    }
    .page-layout.list-view .swipe-wrapper { display: contents; } /* unwrap in list view */
    .page-layout.list-view .swipe-actions { display: none; } /* hide in list view */

    /* Make sure page layout and grid do not clip swipe area */
    .page-layout, .job-grid { overflow: visible; }

    /* Filters pagination styling */
    .filters-pagination {
        margin-top: 18px;
        text-align: center;
    }
    .filters-pagination .pagination { display: inline-flex; gap: 8px; }

    /* Grid tweaks: 4 columns on desktop, responsive on smaller screens */
    /* @media (min-width: 900px) {
        .page-layout.grid-view .job-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 899px) {
        .page-layout.grid-view .job-grid { grid-template-columns: 1fr; }
    } */

    /* Compact card styling */
    .job-card {
        padding: 10px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(15,23,42,0.08);
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-height: auto;
        overflow: visible;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }

    .job-card:hover {
        box-shadow: 0 4px 16px rgba(15,23,42,0.12);
        transform: translateY(-2px);
    }

    .job-card .card-photo { 
        height: 60px; 
        border-radius: 6px; 
        background-size: cover; 
        background-position: center; 
        margin-bottom: 4px;
    }

    .job-card .meta-badges { 
        display: flex; 
        gap: 6px; 
        font-size: 12px; 
    }

    .job-card .view-btn { 
        padding: 6px 12px; 
        font-size: 13px; 
    }

    /* Swipe-actions: float slightly below cards and centered */
    .page-layout.swipe-view { position: relative; }
    .swipe-actions {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: 36px;
        z-index: 120;
        display: flex;
        gap: 12px;
    }


    /* Swipe wrapper: establishes positioning context for swipe-actions */
    .page-layout.swipe-view .swipe-wrapper {
        position: absolute;
        left: 50%;
        top: 0;
        width: min(420px, 86%);
        transform: translateX(-50%);
        display: flex;
        flex-direction: column;
        align-items: center;
        z-index: 30;
    }

    /* Swipe view: stacked card deck */
    .page-layout.swipe-view .job-grid {
        position: relative;
        overflow: visible;
        display: block;
        height: 700px;
        margin-bottom: 150px;
    }

    .page-layout.swipe-view .job-card {
        position: static;
        width: 100%;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(15,23,42,0.12);
        transition: transform 0.35s ease, opacity 0.35s ease;
        opacity: 1;
        pointer-events: auto;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        transform-origin: center center;
        touch-action: none;
        cursor: grab;
    }

    .page-layout.swipe-view .job-card.dragging {
        cursor: grabbing;
        transition: none !important;
        box-shadow: 0 30px 80px rgba(15,23,42,0.25);
    }

    .page-layout.swipe-view .swipe-wrapper.dragging {
        transition: none !important;
        z-index: 40 !important;
    }

    /* Top card - active and draggable */
    .page-layout.swipe-view .swipe-wrapper:nth-child(1) {
        z-index: 30;
        transform: translateX(-50%) translateY(0) scale(1);
        opacity: 1;
        pointer-events: auto;
    }

    /* Top card with active attribute */
    .page-layout.swipe-view .job-card[data-active="true"] {
        pointer-events: auto;
        touch-action: none;
    }   

    /* Second card peeking behind */
    .page-layout.swipe-view .swipe-wrapper:nth-child(2) {
        z-index: 20;
        transform: translateX(-50%) translateY(20px) scale(0.98);
        opacity: 1;
    }

    /* Third card even lower */
    .page-layout.swipe-view .swipe-wrapper:nth-child(3) {
        z-index: 10;
        transform: translateX(-50%) translateY(40px) scale(0.96);
        opacity: 0.6;
    }

    /* hide the rest for clean UI */
    .page-layout.swipe-view .swipe-wrapper:nth-child(n+4) {
        opacity: 0;
        pointer-events: none;
    }

    .page-layout.swipe-view .job-card.card-modern .card-photo {
        height: 220px;
        border-bottom-left-radius: 0;
        border-bottom-right-radius: 0;
    }

    .page-layout.swipe-view .job-card.card-modern .card-content {
        padding: 18px;
    }

    .page-layout.swipe-view .job-card .match-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 50;
        background: #0ea5e9;
        color: #fff;
        padding: 6px 10px;
        border-radius: 999px;
        font-weight: 700;
        font-size: 12px;
        box-shadow: 0 6px 18px rgba(14,165,233,0.18);
    }





    /* When filter panel is hidden, collapse the grid to 1 column */
    .page-layout.hide-filters {
        grid-template-columns: 1fr !important;
    }

    /* Optional: hide the aside completely */
    .page-layout.hide-filters .filters-panel {
        display: none !important;
    }

    /* @media (max-width:900px){
      .page-layout.grid-view .job-grid{ grid-template-columns: 1fr; }
    } */


    .job-info h3 {
        margin: 0;
        font-size: 16px;
    }

    .budget {
        font-weight: 600;
        color: #1f8b4c;
    }

    .details-btn {
        background: #2ecc71;
        padding: 10px 15px;
        color: white;
        border-radius: 6px;
        border: none;
        cursor: pointer;
    }


  /* ====== Pagination ====== */
    .pagination {
    margin-top: 24px;
    display: flex;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
  }

    .page-btn {
        padding: 6px 12px;
        border: 1px solid #ddd;
        background: #fff;
        border-radius: 6px;
        text-decoration: none;
        color: #333;
        font-size: 14px;
    }

    .page-btn:hover {
        background: #f0f0f0;
    }

    .page-btn.active {
        background: #007bff;
        color: white;
        border-color: #007bff;
    }

    .page-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }


    .save-job-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    border: none;
    background: rgba(255,255,255,0.9);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    cursor: pointer;
    color: #888;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    }

    .save-job-btn:hover {
        color: #333;
        transform: scale(1.1);
    }

    .save-job-btn.saved i {
        color: #ffb200;
    }

    .job-card .save-job-btn {
    display: block !important;
    opacity: 1 !important;
    visibility: visible !important;
  }

  /* ===== NEW CARD DESIGN - Matching find_talent.php ===== */
  .job-card.card-modern {
    background: #fff;
    border-radius: 1.25rem;
    box-shadow: 0 4px 20px rgba(15,23,42,0.06);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #dfe6ef;
  }

  .job-card.card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(15,23,42,0.12);
  }

  /* Card header with company logo */
  .job-card.card-modern .card-photo {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    min-height: 180px;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    width: 100%;
    overflow: hidden;
  }

  .job-card.card-modern .card-photo .company-logo {
    width: 100%;
    height: 100%;
    min-height: 180px;
    object-fit: cover;
    border-radius: 0;
    background: white;
    padding: 0;
    box-shadow: none;
  }

  .job-card.card-modern .card-photo .company-logo-placeholder {
    width: 100%;
    height: 100%;
    min-height: 180px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 0;
    box-shadow: none;
  }

  .job-card.card-modern .card-photo .company-logo-placeholder i {
    font-size: 64px;
    color: #94a3b8;
  }

  /* Match badge */
  .job-card.card-modern .match-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 2;
    background: #10b981;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
  
  }

  .job-card.card-modern .match-badge i {
    font-size: 10px;
  }

  /* Card content */
  .job-card.card-modern .card-content {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    flex: 1;
  }

  /* Job header info */
  .job-card.card-modern .job-header-info {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
  }

  .job-card.card-modern .job-name {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .job-card.card-modern .company-name {
    margin: 0;
    font-size: 0.9rem;
    color: #2563eb;
    font-weight: 500;
  }

  /* Meta row: vacancies + location */
  .job-card.card-modern .job-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.85rem;
    color: #5f6b7a;
    margin-top: 0.25rem;
  }

  .job-card.card-modern .job-meta-row span {
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .job-card.card-modern .job-meta-row i {
    font-size: 12px;
    color: #2563eb;
  }

  /* Job summary/description */
  .job-card.card-modern .job-summary {
    margin: 0;
    font-size: 0.9rem;
    color: #5f6b7a;
    line-height: 1.5;
    font-style: italic;
  }

  /* Info boxes stacked */
  .job-card.card-modern .info-boxes-stacked {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .job-card.card-modern .info-box-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    background: #f8fafc;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
  }

  .job-card.card-modern .info-box-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #dbeafe;
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }

  .job-card.card-modern .info-box-icon.available-icon {
    background: #dbeafe;
    color: #2563eb;
  }

  .job-card.card-modern .info-box-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .job-card.card-modern .info-box-label {
    font-size: 0.7rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 500;
  }

  .job-card.card-modern .info-box-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
  }

  /* Skills section */
  .job-card.card-modern .job-skills-section {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }

  /* .job-card.card-modern .skills-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 6px;
  } */

  /* .job-card.card-modern .skills-label i {
    color: #f59e0b;
    font-size: 12px;
  } */

  .job-card.card-modern .skills-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .job-card.card-modern .skill-tag {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    background: #e0f2fe;
    color: #2563eb;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 500;
  }

  /* Action buttons */
  .job-card.card-modern .card-action-buttons {
    display: flex;
    gap: 10px;
    margin-top: auto;
    padding-top: 0.5rem;
  }

  .job-card.card-modern .btn-skip {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
    border: 1px solid #dfe6ef;
    color: #ef4444;
  }

  .job-card.card-modern .btn-skip:hover {
    border-color: #ef4444;
    background: #fef2f2;
  }

  .job-card.card-modern .btn-save {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 10px 16px;
    border-radius: 999px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #10b981;
    border: none;
    color: #fff;
  }

  .job-card.card-modern .btn-save:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }

  /* Swipe overlay styles */
  .job-card.card-modern .swipe-overlay {
    position: absolute;
    inset: 0;
    z-index: 10;
    opacity: 0;
    pointer-events: none;
    border-radius: 1.25rem;
  }

  .job-card.card-modern .swipe-label {
    position: absolute;
    top: 140px;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 20px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    opacity: 0;
  }

  .job-card.card-modern .swipe-label.swipe-like {
    left: 20px;
    color: #10b981;
    border: 3px solid #10b981;
    background: rgba(255,255,255,0.95);
    transform: rotate(-15deg);
  }

  .job-card.card-modern .swipe-label.swipe-pass {
    right: 20px;
    color: #ef4444;
    border: 3px solid #ef4444;
    background: rgba(255,255,255,0.95);
    transform: rotate(15deg);
  }

  /* Swipe animations */
  .job-card.swipe-left {
    animation: swipe-left 0.35s forwards;
  }

  .job-card.swipe-right {
    animation: swipe-right 0.35s forwards;
  }

  @keyframes swipe-left {
    to {
      transform: translateX(-200%) rotate(-12deg);
      opacity: 0;
    }
  }

  @keyframes swipe-right {
    to {
      transform: translateX(100%) rotate(12deg);
      opacity: 0;
    }
  }

  /* Swipe action buttons - positioned directly under card stack */
  .swipe-actions {
    position: absolute;
    left: 50%;
    top: 100%;
    transform: translateX(-50%);
    display: flex;
    gap: 12px;
    justify-content: center;
    align-items: center;
    z-index: 90;
    margin-top: 50px;
    white-space: nowrap;
  }

  /* Hide swipe-actions in grid and list views */
  .page-layout.grid-view .swipe-actions,
  .page-layout.list-view .swipe-actions {
    display: none !important;
  }

  /* Show swipe-actions in swipe-view for both desktop and mobile */
  .page-layout.swipe-view .swipe-actions {
    display: flex !important;
    position: absolute !important;
    bottom: 120px;
    margin-top: 0;
    z-index: 100 !important;
    pointer-events: auto !important;
    visibility: visible !important;
    margin-top: 40px;
  }

  .swipe-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.55rem 1.1rem;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    min-width: 90px;
  }

  .swipe-btn.ghost {
    background: #fff;
    color: #ef4444;
    border: 2px solid #e1e6f0;
  }

  .swipe-btn.ghost:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
  }

  .swipe-btn.primary {
    background: #dc2626;
    color: #fff;
    box-shadow: 0 15px 35px rgba(220, 38, 38, 0.35);
    padding: 0.55rem 1.1rem;
  }

  .swipe-btn.primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 45px rgba(220, 38, 38, 0.45);
  }

  /* Responsive tweaks */
  @media (max-width: 700px) {
    .job-card.card-modern .card-photo { min-height: 140px; padding: 0; }
    .job-card.card-modern .card-photo .company-logo { min-height: 140px; }
    .job-card.card-modern .card-photo .company-logo-placeholder { min-height: 140px; }
    .job-card.card-modern .card-photo .company-logo-placeholder i { font-size: 48px; }
    .job-card.card-modern .card-content { padding: 1rem; gap: 0.6rem; }
    .job-card.card-modern .job-name { font-size: 1rem; }
    .job-card.card-modern .job-meta-row { font-size: 0.8rem; }
    .job-card.card-modern .job-summary { font-size: 0.85rem; }
  }


  /* Hide view toggle in mobile - swipe mode only */
@media (max-width: 600px) {
    .view-toggle {
        display: none !important;
    }
    
    .filters-header {
        justify-content: flex-start !important;
    }
    
    .btn-toggle-filters {
        margin-left: 0 !important;
    }

        /* CRITICAL: Force swipe mode layout */
    .page-layout {
        display: block !important;
        position: relative !important;
        min-height: 600px;
    }
    
    /* CRITICAL: Job grid positioning for swipe deck */
    .job-grid {
        position: relative !important;
        display: block !important;
        min-height: 500px;
        width: 100%;
        max-width: 420px;
        margin: 0 auto !important;
        overflow: visible !important;
    }
    
    /* CRITICAL: Swipe wrapper absolute positioning */
    .swipe-wrapper {
        position: absolute !important;
        left: 50% !important;
        width: 90% !important;
        max-width: 380px !important;
        top: 0 !important;
        transform: translateX(-50%) !important;
    }
    
    /* Stack cards with data-stack attributes */
    .swipe-wrapper[data-stack="1"] {
        z-index: 30 !important;
        transform: translateX(-50%) translateY(0px) scale(1) !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }
    
    .swipe-wrapper[data-stack="2"] {
        z-index: 20 !important;
        transform: translateX(-50%) translateY(24px) scale(0.97) !important;
        opacity: 0.8 !important;
    }
    
    .swipe-wrapper[data-stack="3"] {
        z-index: 10 !important;
        transform: translateX(-50%) translateY(48px) scale(0.94) !important;
        opacity: 0.5 !important;
    }
    
    .swipe-wrapper:not([data-stack="1"]):not([data-stack="2"]):not([data-stack="3"]) {
        opacity: 0 !important;
        pointer-events: none !important;
    }
    
    /* Show swipe actions under cards */
    .swipe-wrapper[data-stack="1"] .swipe-actions {
        display: flex !important;
        position: fixed !important;
        bottom: 100px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 100;
        gap: 18px !important;
        justify-content: center;
    }
    
    /* Hide centralized swipe-actions bar */
    /* .job-grid > .swipe-actions {
        display: none !important;
    } */
    
    /* Card styling */
    .job-card {
        width: 100% !important;
        max-width: 100% !important;
        pointer-events: auto;
        touch-action: none;
    }
    
            .mobile-swipe-actions {
                display: flex !important;
            }

            .view-toggle { display: none !important; }

            .filters-header { justify-content: flex-start; }

            .btn-toggle-filters { margin-left: 0; }

            .page-layout {
                display: block;
                position: relative;
            }

            .job-grid {
                position: relative;
                max-width: 88vw;
                margin: 0 auto;
                min-height: 420px;
                overflow: visible;
                padding-top: 12px;
            }

            .job-grid .swipe-wrapper {
                position: absolute;
                top: 0;
                left: 50%;
                width: 100%;
                transform: translate(-50%, 0) scale(1);
                transform-origin: center top;
                transition: transform 0.28s ease, opacity 0.25s ease;
                will-change: transform, opacity;
            }

            .job-grid .swipe-wrapper[data-stack="1"] {
                z-index: 30;
                opacity: 1;
                pointer-events: auto;
            }

            .job-grid .swipe-wrapper[data-stack="2"] {
                z-index: 20;
                transform: translate(-50%, 12px) scale(0.97);
                opacity: 0.85;
                pointer-events: none;
            }

            .job-grid .swipe-wrapper[data-stack="3"] {
                z-index: 10;
                transform: translate(-50%, 24px) scale(0.94);
                opacity: 0.65;
                pointer-events: none;
            }

            .job-grid .swipe-wrapper[data-stack="4"] {
                z-index: 5;
                transform: translate(-50%, 36px) scale(0.9);
                opacity: 0;
                pointer-events: none;
            }

            .job-grid .job-card.card-modern {
                box-shadow: 0 18px 38px rgba(15,23,42,0.2);
                border-radius: 18px;
                touch-action: pan-y;
                overflow: hidden;
                margin-left: 20px;
            }

            .job-card .swipe-overlay.show-like {
                opacity: 1;
                background: linear-gradient(120deg, rgba(16,185,129,0.24), rgba(16,185,129,0));
            }

            .job-card .swipe-overlay.show-pass {
                opacity: 1;
                background: linear-gradient(60deg, rgba(239,68,68,0.24), rgba(239,68,68,0));
            }

            .mobile-swipe-actions {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 18px;
                margin: 18px auto 0;
                max-width: 320px;
            }

            .mobile-swipe-actions .swipe-btn {
                width: 58px;
                height: 58px;
                border-radius: 50%;
                border: none;
                background: #fff;
                box-shadow: 0 12px 28px rgba(15,23,42,0.18);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                cursor: pointer;
                transition: transform 0.18s ease, box-shadow 0.18s ease, opacity 0.18s ease;
            }

            .mobile-swipe-actions .swipe-btn:active {
                transform: scale(0.94);
            }

            .mobile-swipe-actions .swipe-btn.pass { color: #ef4444; }
            .mobile-swipe-actions .swipe-btn.like { color: #10b981; }

            .mobile-swipe-actions .swipe-btn.undo {
                color: #2563eb;
                opacity: 0.45;
                cursor: not-allowed;
            }

            .mobile-swipe-actions .swipe-btn.undo.enabled {
                opacity: 1;
                cursor: pointer;
            }
        .page-layout.swipe-view .swipe-actions {
            margin-top: -10px;
            margin-left: 10px;
        }


    
}

/* =============================================
   JOB SLIDE PANEL STYLES
   ============================================= */

/* Overlay background */
.job-slide-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 9998;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}

.job-slide-overlay.active {
  opacity: 1;
  visibility: visible;
}

/* Slide panel */
.job-slide-panel {
  position: fixed;
  top: 0;
  right: 0;
  width: 90%;
  max-width: 700px;
  height: 100vh;
  background: #ffffff;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  transform: translateX(100%);
  transition: transform 0.3s ease;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.job-slide-panel.active {
  transform: translateX(0);
}

/* Panel header */
.slide-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid #e5e7eb;
  background: #f9fafb;
  flex-shrink: 0;
}

.slide-panel-header h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
}

.slide-panel-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  color: #6b7280;
}

.slide-panel-close:hover {
  background: #f3f4f6;
  color: #374151;
  transform: scale(1.05);
}

.slide-panel-close i {
  font-size: 14px;
}

/* Panel content */
.slide-panel-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
  font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
}

/* Job header in panel */
.slide-job-header {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  padding-bottom: 20px;
  border-bottom: 1px solid #f3f4f6;
}

.slide-company-logo {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  object-fit: cover;
  border: 1px solid #e5e7eb;
  flex-shrink: 0;
}

.slide-job-info h3 {
  margin: 0 0 4px 0;
  font-size: 20px;
  font-weight: 600;
  color: #111827;
  line-height: 1.3;
}

.slide-company-name {
  color: #6b7280;
  font-size: 16px;
  font-weight: 500;
  margin-bottom: 8px;
  display: block;
}

.slide-match-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #3b82f6;
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.slide-match-badge i {
  font-size: 10px;
}

/* Job details sections */
.slide-job-details {
  margin-bottom: 24px;
}

.slide-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.slide-detail-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 10px;
  font-size: 14px;
}

.slide-detail-item i {
  color: #3b82f6;
  font-size: 16px;
  width: 20px;
  text-align: center;
}

.slide-detail-item span {
  color: #374151;
  font-weight: 500;
}

/* Job description */
.slide-job-description h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.slide-job-description p {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.6;
  margin: 0 0 16px 0;
}

.slide-job-requirements {
  margin-bottom: 20px;
}

.slide-job-requirements h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.slide-job-requirements ul {
  margin: 0;
  padding-left: 20px;
  color: #6b7280;
  font-size: 14px;
  line-height: 1.6;
}

.slide-job-requirements li {
  margin-bottom: 6px;
}

/* Skills tags in panel */
.slide-skills-section {
  margin-bottom: 24px;
}

.slide-skills-section h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
}

.slide-skills-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.slide-skill-tag {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 6px 12px;
  border-radius: 16px;
  font-size: 12px;
  font-weight: 500;
}

/* Panel action buttons */
.slide-panel-actions {
  display: flex;
  gap: 12px;
  padding-top: 20px;
  border-top: 1px solid #f3f4f6;
  margin-top: 20px;
}

.slide-btn {
  flex: 1;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s ease;
  border: none;
  cursor: pointer;
}

.slide-btn.primary {
  background: #16a34a;
  color: white;
}

.slide-btn.primary:hover {
  background: #15803d;
  transform: translateY(-1px);
}

.slide-btn.secondary {
  background: #f3f4f6;
  color: #374151;
}

.slide-btn.secondary:hover {
  background: #e5e7eb;
  transform: translateY(-1px);
}

.slide-btn i {
  font-size: 12px;
}

/* Make job cards clickable */
.job-card {
  cursor: pointer !important;
}

.job-card:hover {
  transform: translateY(-2px) !important;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1) !important;
}

/* Enhanced job details styles */
.slide-job-specs {
  margin-bottom: 24px;
}

.slide-job-specs h4 {
  margin: 0 0 16px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 8px;
}

.specs-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.spec-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: #f9fafb;
  border-radius: 8px;
  font-size: 14px;
}

.spec-label {
  color: #6b7280;
  font-weight: 500;
}

.spec-value {
  color: #374151;
  font-weight: 600;
  text-align: right;
}

.status-active {
  color: #16a34a !important;
  background: #dcfce7;
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 12px;
}

.slide-benefits-section {
  margin-bottom: 24px;
}

.slide-benefits-section h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 8px;
}

.slide-benefits-section p {
  color: #6b7280;
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
  background: #f9fafb;
  padding: 16px;
  border-radius: 8px;
  border-left: 4px solid #3b82f6;
}

.slide-company-info {
  margin-bottom: 24px;
  padding: 20px;
  background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
  border-radius: 12px;
  border: 1px solid #e0f2fe;
}

.slide-company-info h4 {
  margin: 0 0 12px 0;
  font-size: 16px;
  font-weight: 600;
  color: #0f172a;
  display: flex;
  align-items: center;
  gap: 8px;
}

.slide-company-info p {
  color: #475569;
  font-size: 14px;
  line-height: 1.6;
  margin: 0 0 8px 0;
}

.slide-company-info p:last-child {
  margin-bottom: 0;
}

.slide-company-info i {
  color: #3b82f6;
  margin-right: 6px;
}

/* Enhanced section headers */
.slide-job-description h4,
.slide-job-requirements h4 {
  display: flex;
  align-items: center;
  gap: 8px;
}

.slide-job-description h4 i,
.slide-job-requirements h4 i,
.slide-skills-section h4 i {
  color: #3b82f6;
  font-size: 14px;
}

/* Loading and error states */
.slide-panel-content .error-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
  flex-direction: column;
  gap: 16px;
}

.slide-panel-content .loading-state {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 300px;
  flex-direction: column;
  gap: 16px;
}

/* Mobile responsiveness for slide panel */
@media (max-width: 600px) {
  .job-slide-panel {
    width: 100%;
    max-width: none;
  }

  .slide-panel-content {
    padding: 20px;
  }

  .slide-detail-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .slide-job-header {
    flex-direction: column;
    text-align: center;
  }

  .slide-company-logo {
    width: 80px;
    height: 80px;
    margin: 0 auto 12px;
  }

  .slide-panel-actions {
    flex-direction: column;
  }

  .slide-btn {
    padding: 14px 20px;
  }
}

</style>





<body class="search-jobs-page">

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
            <li><i class="fa-regular fa-bookmark"></i> Interactions</li>
            <li><i class="fa-solid fa-gear"></i> Settings</li>
            <li><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</li>
        </ul>
    </aside> -->

    <div class="overlay" id="overlay"></div>
</header>

<div class="mobile-search-bar" id="mobileSearchBar">
    <input type="text" placeholder="Search jobs, companies..." />
</div>


  <!-- ======= DESKTOP HEADER ======= -->
<?php 
$activePage = 'job';
include 'header.php'; ?>



    <!-- ======= MAIN CONTENT ======= -->

<main style="padding:110px 24px 90px; max-width:1100px;margin:0 auto; font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif; position: relative;">
<h1 class="search-jobs-title">Jobs For You</h1>
<p class="job-count"><?php echo $total; ?> jobs found</p>

<!-- SEARCH BAR -->
<form method="GET" class="search-top-row">

    <!-- TOP: Search input -->
    <input type="text"  id="searchInput"  name="q" class="search-input" placeholder="Job title, keywords..."
           value="<?php echo e($q); ?>">

    <!-- BOTTOM: Location + button -->
    <!-- <input type="text" id="locationInput" name="location" class="search-location" placeholder="Location"
           value="<?php echo e($location); ?>"> -->

    <!-- <button class="btn search-btn" type="submit">
        Search
    </button> -->
</form>


<div class="filters-header">
    <button class="btn-toggle-filters">
        <i class="fa-solid fa-sliders"></i> Show Filters
    </button>

    <div class="view-toggle">
        <span class="view-label">View:</span>
        <button class="view-btn active" data-view="grid" title="Grid view"><i class="fa-solid fa-table-cells"></i></button>
         <button class="view-btn" data-view="list" title="List view"><i class="fa-solid fa-list"></i></button>
         <button class="view-btn" data-view="swipe" title="Swipe view"><i class="fa-solid fa-layer-group"></i></button>
    </div>
</div>

<div class="page-layout">

    <!-- LEFT FILTERS -->
    <aside class="filters-panel hidden">    

        <!-- INDUSTRY -->
        <!-- <div class="filter-box">
            <h4>Industry</h4>
            <label><input type="checkbox" value="Agriculture"> Agriculture</label>
            <label><input type="checkbox" value="Manufacturing"> Manufacturing</label>
            <label><input type="checkbox" value="Services"> Services</label>
            <label><input type="checkbox" value="Retail"> Retail</label>
            <label><input type="checkbox" value="Technology"> Technology</label>
        </div> -->

        <!-- JOB TYPE -->
        <div class="filter-box" id="filter-job-type">
            <h4>Job Type</h4>
            <?php
            // Render job types from DB so checkboxes use job_type_id
            // Exclude archived job types (is_archived = 1)
            $jt_res = $conn->query("SELECT job_type_id, job_type_name FROM job_type WHERE is_archived = 0 ORDER BY job_type_name");
            if($jt_res){
                while($jt = $jt_res->fetch_assoc()){
                    echo '<label><input type="checkbox" value="'.htmlspecialchars($jt['job_type_id']).'"> '.htmlspecialchars($jt['job_type_name']).'</label>';
                }
            }
            ?>
        </div>

        <!-- SALARY RANGE -->
        <!-- <div class="filter-box">
            <h4>Salary Range</h4>
            <input id="salaryRange" type="range" min="10000" max="100000" value="10000" step="1000">
            <div id="salaryValue" class="salary-value">₱10,000</div>
            <div class="salary-minmax">
                <span>₱10,000</span>
                <span>₱100,000</span>
            </div>
        </div> -->

        <button class="clear-filters">Clear All Filters</button>

        <?php
        // Pagination moved into filters panel so it stays visible and centered under filters.
        // Use $totalPages calculated from COUNT query (already set at line ~210)
        if ($totalPages > 1): ?>
        <div class="filters-pagination">
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">« Prev</a>
                <?php else: ?>
                    <span class="page-btn disabled">« Prev</span>
                <?php endif; ?>

                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])); ?>"
                       class="page-btn <?= ($p == $page) ? 'active' : '' ?>">
                       <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">Next »</a>
                <?php else: ?>
                    <span class="page-btn disabled">Next »</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </aside>

    <!-- JOB GRID -->
    <section class="job-grid" style="position: relative; min-height: 400px;">
    <?php
    // No server-side rows -> show friendly message. When rows exist, keep the element hidden; JS will toggle it
    $noMatchesVisible = (count($rows) === 0);
    ?>

   <div id="noMatches" class="no-jobs" style="<?= $noMatchesVisible ? 'display:flex;flex-direction:column;justify-content:center;align-items:center;padding:48px 16px;position:absolute;top:0;left:0;right:0;bottom:0;min-height:400px;text-align:center;color:#6b7280;font-size:16px;z-index:10;' : 'display:none;'; ?>">No job posts match your filters. Try adjusting your search criteria.</div>

    <?php foreach($rows as $row): ?>
        <?php 
        /* Budget / Salary logic using correct schema */
        $budget = null;
        if (isset($row['budget']) && $row['budget'] !== null && $row['budget'] !== '') {
            $budget = number_format((float)$row['budget'], 2);
        }
        $salaryLabel = $budget ? "₱" . $budget : 'Negotiable';
        
        // Location display: prefer address_line from job_post_location, fallback to company_location
        $locationDisplay = $row['job_address_line'] ?? ($row['company_location'] ?? ($row['job_location_id'] ? 'Location ID: ' . $row['job_location_id'] : 'Not specified'));
        
        // Calculate match percentage (placeholder - you can implement real logic)
        $matchPercent = $row['match_percent'] ?? 0; // Always use computed match
        ?>  
        <div class="swipe-wrapper">
            <div
                class="job-card card-modern"
                data-job-id="<?php echo (int)($row['job_post_id'] ?? 0); ?>"
                data-job-type="<?php echo e($row['job_type_id'] ?? ''); ?>"
                data-industry="<?php echo e($row['industry'] ?? ''); ?>"
                data-salary="<?php echo e($row['budget'] ?? ''); ?>"
                data-location="<?php echo e($row['company_location'] ?? ''); ?>"
                data-company="<?php echo e($row['company_name'] ?? ''); ?>"
                tabindex="0"
                role="group"
                aria-label="<?php echo e(($row['job_post_name'] ?? 'Role') . ' at ' . ($row['company_name'] ?? '')); ?>"
            >
                <!-- Swipe overlay -->
                <div class="swipe-overlay" aria-hidden="true">
                    <span class="swipe-label swipe-like">LIKE</span>
                    <span class="swipe-label swipe-pass">PASS</span>
                </div>

                <!-- Card Header with company logo -->
                <div class="card-photo">
                    <?php if (!empty($row['company_logo'])): ?>
                        <img src="../<?php echo e($row['company_logo']); ?>" alt="<?php echo e($row['company_name']); ?>" class="company-logo">
                    <?php else: ?>
                        <div class="company-logo-placeholder">
                            <i class="fa-solid fa-building"></i>
                        </div>
                    <?php endif; ?>
                    <!-- Match badge -->
                    <div class="match-badge">
                        <i class="fa-solid fa-lock"></i> <?= $matchPercent ?>% Match
                    </div>
                </div>

                <!-- Card content -->
                <div class="card-content">
                    <!-- Job title and company -->
                    <div class="job-header-info">
                        <h3 class="job-name"><?php echo e($row['job_post_name']); ?></h3>
                        <p class="company-name"><?php echo e($row['company_name'] ?? 'Company'); ?></p>
                    </div>

                    <!-- Meta info: vacancies + location -->
                    <div class="job-meta-row">
                        <span><i class="fa-solid fa-users"></i> <?= e($row['vacancies'] ?? 1); ?> vacancies</span>
                        <span><i class="fa-solid fa-location-dot"></i> <?php echo e($locationDisplay); ?></span>
                    </div>

                    <!-- Job description snippet -->
                    <p class="job-summary"><?php 
                        $desc = $row['job_description'] ?? '';
                        echo e(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : ($desc ?: 'No description provided.'));
                    ?></p>

                    <!-- Info boxes (stacked) -->
                    <div class="info-boxes-stacked">
                        <div class="info-box-item">
                            <div class="info-box-icon">
                                <i class="fa-solid fa-peso-sign"></i>
                            </div>
                            <div class="info-box-text">
                                <span class="info-box-label">SALARY</span>
                                <span class="info-box-value"><?= $salaryLabel; ?></span>
                            </div>
                        </div>
                        <div class="info-box-item">
                            <div class="info-box-icon available-icon">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <div class="info-box-text">
                                <span class="info-box-label">AVAILABLE</span>
                                <span class="info-box-value">Open</span>
                            </div>
                        </div>
                    </div>

                    <!-- Tags section -->
                    <div class="job-skills-section">
                        <!-- <p class="skills-label"><i class="fa-solid fa-star"></i> Top Skills</p> -->
                        <div class="skills-tags">
                            <?php if (!empty($row['job_type_name'])): ?>
                                <span class="skill-tag"><?= e($row['job_type_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['experience_level_name'])): ?>
                                <span class="skill-tag"><?= e($row['experience_level_name']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($row['work_setup_name'])): ?>
                                <span class="skill-tag"><?= e($row['work_setup_name']); ?></span>
                            <?php endif; ?>
                            <?php if (empty($row['job_type_name']) && empty($row['experience_level_name']) && empty($row['work_setup_name'])): ?>
                                <span class="skill-tag">General</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action buttons -->
               
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Desktop/Swipe-view action buttons (always present for swipe-view mode) -->
    <div class="swipe-actions" role="group" aria-label="Job swipe actions">
        <button type="button" class="swipe-btn ghost" data-action="dislike" aria-label="Pass this job">
            <i class="fa-solid fa-xmark"></i> Pass
        </button>
        <button type="button" class="swipe-btn undo" data-action="undo" aria-label="Undo last action" disabled>
            <i class="fa-solid fa-rotate-left"></i> Undo
        </button>
        <button type="button" class="swipe-btn primary" data-action="like" aria-label="Like this job">
            <i class="fa-solid fa-heart"></i> Like
        </button>
    </div>
    </section>

    <!-- <div class="mobile-swipe-actions" role="group" aria-label="Swipe actions">
        <button type="button" class="swipe-btn pass" data-action="dislike" aria-label="Pass job">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <button type="button" class="swipe-btn undo" data-action="undo" aria-label="Undo last swipe" disabled>
            <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
        </button>
        <button type="button" class="swipe-btn like" data-action="like" aria-label="Like job">
            <i class="fa-solid fa-heart" aria-hidden="true"></i>
        </button>
    </div>



    <!-- ================= MOBILE BOTTOM NAV ================= -->
  <nav class="bottom-nav">
    <a href="#" class="<?= ($activePage==='job') ? 'active' : '' ?>">
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




<!-- PAGINATION moved into filters panel (see above) -->

<!-- Job Slide Panel -->
<div class="job-slide-overlay" id="jobSlideOverlay"></div>
<div class="job-slide-panel" id="jobSlidePanel">
    <div class="slide-panel-header">
        <button class="slide-panel-close" id="slidePanelClose">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2>Job Details</h2>
    </div>
    <div class="slide-panel-content" id="slidePanelContent">
        <!-- Content will be loaded dynamically -->
    </div>
</div>

</main>





<script>
// View toggle: grid vs list
(function(){
    const pageLayout = document.querySelector('.page-layout');
    const viewBtns = document.querySelectorAll('.view-toggle .view-btn');
    if(!pageLayout || !viewBtns || viewBtns.length === 0) return;

    function setView(mode){
        if(mode === 'list'){
            pageLayout.classList.remove('grid-view', 'swipe-view');
            pageLayout.classList.add('list-view');
        } 
        else if (mode === 'swipe'){
            pageLayout.classList.remove('grid-view', 'list-view');
            pageLayout.classList.add('swipe-view');
        }
        else {
            pageLayout.classList.remove('list-view', 'swipe-view');
            pageLayout.classList.add('grid-view');
        }

        // update active button
        viewBtns.forEach(btn => {
            if (btn.dataset.view === mode) btn.classList.add("active");
            else btn.classList.remove("active");
        });
    }


    // initial state: prefer existing HTML active button, otherwise default to grid
    const activeBtn = Array.from(viewBtns).find(b => b.classList.contains('active'));
    if(activeBtn){
        // determine index of active button
        const idx = Array.from(viewBtns).indexOf(activeBtn);
        setView(idx === 0 ? 'grid' : 'list');
    } else {
        setView('grid');
    }

viewBtns.forEach(btn => {
    btn.addEventListener("click", function (e) {
        e.preventDefault();

        const mode = this.dataset.view || 
                     (this.innerHTML.includes("table-cells") ? "grid" : "list");

        setView(mode);
    });
});

})();
</script>








<script>
 /* hides filters panel when hide filter is clicked */ 

document.addEventListener("DOMContentLoaded", function () {
    const toggleBtn = document.querySelector(".btn-toggle-filters");
    const filtersPanel = document.querySelector(".filters-panel");
    const pageLayout = document.querySelector(".page-layout"); 

    // Set initial state: filters hidden, grid expanded
    pageLayout.classList.add("hide-filters");

    toggleBtn.addEventListener("click", function () {

        // Hide/show the filters panel
        filtersPanel.classList.toggle("hidden");

        // Collapse the grid layout when filters are hidden
        pageLayout.classList.toggle("hide-filters"); 

        // Change button text
        if (filtersPanel.classList.contains("hidden")) {
            toggleBtn.innerHTML = '<i class="fa-solid fa-sliders"></i> Show Filters';
        } else {
            toggleBtn.innerHTML = '<i class="fa-solid fa-sliders"></i> Hide Filters';
        }
    });
});
</script>






<script>
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

    // Move panel back to original position (if the container exists)
    const resumeCard = document.querySelector(".resume-card");
    if (resumeCard) resumeCard.appendChild(panel);
  });
});















//Update salary range value dynamically

    const range = document.getElementById("salaryRange");
    const output = document.getElementById("salaryValue");

    function formatMoney(num) {
        return "₱" + Number(num).toLocaleString();
    }

    // Initial load + guard: only run if elements exist
    if (range && output) {
        output.textContent = formatMoney(range.value);

        // Update when slider moves
        range.addEventListener("input", () => {
            output.textContent = formatMoney(range.value);
        });
    }



    const slider = document.getElementById('salaryRange');


// --- SELECT ELEMENTS ---
const salarySlider = document.getElementById("salaryRange");
const salaryValue = document.querySelector(".salary-value");
const clearBtn = document.querySelector(".clear-filters");
const checkboxes = document.querySelectorAll(".filters-panel input[type='checkbox']");

// Format salary
function formatMoney(num) {
    return "₱" + Number(num).toLocaleString();
}

// Update slider UI (value + filled track)
function updateTrack() {
    if (!salarySlider) return; // Guard against null
    const min = salarySlider.min;
    const max = salarySlider.max;
    const val = salarySlider.value;

    // Fill percentage
    const percent = ((val - min) / (max - min)) * 100;

    salarySlider.style.setProperty('--pos', percent + "%");
    if (salaryValue) salaryValue.textContent = formatMoney(val);
}

// Initialize slider
if (salarySlider) updateTrack();

// Update slider on drag
if (salarySlider) salarySlider.addEventListener("input", updateTrack);


// CLEAR FILTERS
function resetFilters() {
    // Uncheck all checkboxes
    checkboxes.forEach(cb => cb.checked = false);

    // Reset slider to minimum
    if (salarySlider) {
        salarySlider.value = salarySlider.min;
        updateTrack();
    }
}

// Bind click event and re-apply filters after clearing
if (clearBtn) clearBtn.addEventListener("click", function(e){ e.preventDefault(); resetFilters(); applyFilters(); });







//Function to show search using enter key
document.querySelector(".search-top-row").addEventListener("keypress", function(e){
    if (e.key === "Enter") {
        this.submit();
    }
});





//LIVE SEARCH — SHOW MATCHING JOBS AS USER TYPES
document.addEventListener("DOMContentLoaded", function () {

    const searchInput = document.getElementById("searchInput");
    const jobCards = document.querySelectorAll(".job-card");

    if (!searchInput || jobCards.length === 0) return;

    searchInput.addEventListener("input", function () {
        const keyword = searchInput.value.toLowerCase().trim();
        // central filter invocation (keyword is part of filters)
        applyFilters();
    });
});

// Central filtering function that combines text, checkboxes, and salary
function applyFilters(){
    const keyword = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
    const location = (document.getElementById('locationInput')?.value || '').toLowerCase().trim();
    const minSalary = Number(document.getElementById('salaryRange')?.value || 0);

    // selected industries and job types (use specific ID selectors for reliability)
    const industryEls = Array.from(document.querySelectorAll('#filter-industry input[type=checkbox]:checked')).map(i=>i.value.toLowerCase());
    const typeEls = Array.from(document.querySelectorAll('#filter-job-type input[type=checkbox]:checked')).map(i=>i.value);

    const cards = document.querySelectorAll('.job-card');
    cards.forEach(card=>{
        let visible = true;

        const text = card.innerText.toLowerCase();
        if(keyword && !text.includes(keyword)) visible = false;
        if(location){
            const loc = (card.dataset.location || '').toLowerCase();
            if(!loc.includes(location)) visible = false;
        }

        // industry filter
        const industry = (card.dataset.industry || '').toLowerCase();
        if(industryEls.length && (!industry || !industryEls.includes(industry))) visible = false;

        // job type - compare job_type_id values (numeric strings)
        const jtype = card.dataset.jobType || '';
        if(typeEls.length && (!jtype || !typeEls.includes(jtype))) visible = false;

        // salary filter (card has min/max values)
        const smin = Number(card.dataset.salaryMin || 0);
        const smax = Number(card.dataset.salaryMax || 0) || smin;
        // if both zero, skip salary check
        if(minSalary > 0 && ( (smax && smax < minSalary) || (smin && smin < minSalary && !smax) )) visible = false;

        card.style.display = visible ? 'flex' : 'none';
        const wrapper = card.closest('.swipe-wrapper');
        if(wrapper){
            if(visible){
                wrapper.dataset.filterHidden = '';
                wrapper.style.display = '';
            } else {
                wrapper.dataset.filterHidden = 'true';
                wrapper.style.display = 'none';
            }
        }
    });

    // Show no-results message when none visible
    const noMatches = document.getElementById('noMatches');
    if (noMatches) {
        const anyVisible = Array.from(cards).some(c => c.style.display !== 'none');
        noMatches.style.display = anyVisible ? 'none' : 'flex';
    }

    if (typeof window.__scheduleJobDeckUpdate === 'function') {
        window.__scheduleJobDeckUpdate();
    }
}

// Wire up filter controls
// Keep checkbox appearance but limit to one selection per filter-box
document.querySelectorAll('.filters-panel .filter-box').forEach(box => {
    const inputs = box.querySelectorAll('input[type="checkbox"]');
    if(!inputs || inputs.length === 0) return;

    inputs.forEach(input => {
        input.addEventListener('change', function () {
            // If this checkbox was checked, uncheck all others in the same box
            if (this.checked) {
                inputs.forEach(i => { if (i !== this) i.checked = false; });
            }
            // Re-apply filters after the change
            applyFilters();
        });
    });
});

document.getElementById('salaryRange')?.addEventListener('input', function(){ applyFilters(); });
// Use the earlier `clearBtn` reference (class-based) rather than a non-existent id
if (typeof clearBtn !== 'undefined' && clearBtn) {
    clearBtn.addEventListener('click', function(e){ e.preventDefault(); resetFilters(); applyFilters(); });
}

// Ensure initial filters apply on load
applyFilters();

// --- Persist filters to URL and restore on navigation ---
function getSelectedFilters() {
    const keyword = (document.getElementById('searchInput')?.value || '').trim();
    const location = (document.getElementById('locationInput')?.value || '').trim();

    const industryEl = Array.from(document.querySelectorAll('#filter-industry input[type=checkbox]:checked'))[0];
    const jobTypeEl = Array.from(document.querySelectorAll('#filter-job-type input[type=checkbox]:checked'))[0];

    const salary = (document.getElementById('salaryRange')?.value || '');

    return {
        q: keyword,
        location: location,
        industry: industryEl ? industryEl.value : '',
        type: jobTypeEl ? jobTypeEl.value : '',
        minSalary: salary
    };
}

function applyFiltersToUrl(pushState = false) {
    // update current url to reflect filter state without reloading (optional)
    const params = new URLSearchParams(window.location.search);
    const f = getSelectedFilters();

    // q and location belong to the search form; keep them in URL
    if (f.q) params.set('q', f.q); else params.delete('q');
    if (f.location) params.set('location', f.location); else params.delete('location');

    // industry and type (job_type id)
    if (f.industry) params.set('industry', f.industry); else params.delete('industry');
    if (f.type) params.set('type', f.type); else params.delete('type');

    // salary (only include if different than min)
    if (f.minSalary) params.set('minSalary', f.minSalary); else params.delete('minSalary');

    const newUrl = window.location.pathname + '?' + params.toString();
    if (pushState) window.history.pushState({}, '', newUrl);
    else window.history.replaceState({}, '', newUrl);
}

function restoreFiltersFromUrl(){
    const params = new URLSearchParams(window.location.search);
    const q = params.get('q');
    const location = params.get('location');
    const industry = params.get('industry');
    const type = params.get('type');
    const minSalary = params.get('minSalary');

    if (q && document.getElementById('searchInput')) document.getElementById('searchInput').value = q;
    if (location && document.getElementById('locationInput')) document.getElementById('locationInput').value = location;

    // set industry checkbox (single-select)
    if (industry) {
        document.querySelectorAll('#filter-industry input[type=checkbox]').forEach(cb => cb.checked = (cb.value === industry));
    }

    // set job type checkbox (single-select)
    if (type) {
        document.querySelectorAll('#filter-job-type input[type=checkbox]').forEach(cb => cb.checked = (cb.value === type));
    }

    // set salary slider
    if (minSalary && document.getElementById('salaryRange')) {
        document.getElementById('salaryRange').value = minSalary;
        updateTrack();
    }

    // apply after restoring
    applyFilters();
}

// Attach click handlers for pagination links to carry current filters
document.querySelectorAll('.pagination a.page-btn').forEach(a => {
    a.addEventListener('click', function(e){
        if (this.classList.contains('disabled')) return;
        e.preventDefault();

        // Build URL preserving page from the clicked link but adding current filters
        const clickedUrl = new URL(this.href, window.location.origin);
        const params = new URLSearchParams(clickedUrl.search);

        const f = getSelectedFilters();
        if (f.q) params.set('q', f.q); else params.delete('q');
        if (f.location) params.set('location', f.location); else params.delete('location');
        if (f.industry) params.set('industry', f.industry); else params.delete('industry');
        if (f.type) params.set('type', f.type); else params.delete('type');
        if (f.minSalary) params.set('minSalary', f.minSalary); else params.delete('minSalary');

        // Navigate to the assembled URL
        window.location.href = clickedUrl.origin + clickedUrl.pathname + '?' + params.toString();
    });
});

// When user clears filters, also remove filter params from URL
if (clearBtn) {
    clearBtn.addEventListener('click', function(e){
        // remove filter-related params from URL (q/location left as-is if present)
        const params = new URLSearchParams(window.location.search);
        params.delete('industry');
        params.delete('type');
        params.delete('minSalary');
        // replace state so back button doesn't keep old filters
        const newUrl = window.location.pathname + '?' + params.toString();
        window.history.replaceState({}, '', newUrl);
    });
}

// On initial load, restore filters from URL (this will call applyFilters())
document.addEventListener('DOMContentLoaded', function(){
    restoreFiltersFromUrl();
});

</script>









<!-- Swipe functionality for job cards -->
<script>
window.addEventListener('DOMContentLoaded', () => {
    const pageLayout = document.querySelector('.page-layout');
    const jobGrid = document.querySelector('.job-grid');
    
    // Get both action bars (desktop swipe-actions and mobile-swipe-actions)
    const desktopActionBar = document.querySelector('.swipe-actions');
    const mobileActionBar = document.querySelector('.mobile-swipe-actions');
    
    // Use the appropriate action bar based on screen size
    const actionBar = window.innerWidth <= 600 ? mobileActionBar : desktopActionBar;
    
    const undoBtn = actionBar?.querySelector('[data-action="undo"]');
    const dislikeBtn = actionBar?.querySelector('[data-action="dislike"]');
    const likeBtn = actionBar?.querySelector('[data-action="like"]');
    const noMatchesEl = document.getElementById('noMatches');
    const currentUserId = <?= json_encode($user_id) ?>;
    const undoWindowMs = 5000;

    if (!pageLayout || !jobGrid) {
        window.__scheduleJobDeckUpdate = () => {};
        return;
    }

    const state = {
        isMobile: window.innerWidth <= 600,
        dragging: null,
        pending: null,
        lastAction: null,
        undoTimer: null
    };

    const mobileMedia = window.matchMedia('(max-width: 600px)');
    mobileMedia.addEventListener('change', onBreakpointChange);

    window.__scheduleJobDeckUpdate = () => requestAnimationFrame(updateDeckState);

    setupActionButtons();
    updateDeckState();

    window.addEventListener('resize', () => {
        state.isMobile = window.innerWidth <= 600;
        if (state.isMobile) {
            pageLayout.classList.remove('grid-view', 'list-view');
            pageLayout.classList.add('swipe-view');
        }
        updateDeckState();
    });

    document.addEventListener('keydown', handleKeydown);

    const observer = new MutationObserver(() => updateDeckState());
    observer.observe(jobGrid, {childList: true, subtree: true});

    function onBreakpointChange(evt) {
        state.isMobile = evt.matches;
        if (state.isMobile) {
            pageLayout.classList.remove('grid-view', 'list-view');
            pageLayout.classList.add('swipe-view');
        } else {
            pageLayout.classList.remove('swipe-view');
            resetTransforms();
        }
        updateDeckState();
    }

    function setupActionButtons() {
        // Setup event listeners for both desktop and mobile action bars
        [desktopActionBar, mobileActionBar].forEach(bar => {
            if (!bar) return;
            const dislike = bar.querySelector('[data-action="dislike"]');
            const like = bar.querySelector('[data-action="like"]');
            const undo = bar.querySelector('[data-action="undo"]');
            
            if (dislike) dislike.addEventListener('click', () => programmaticSwipe('dislike'));
            if (like) like.addEventListener('click', () => programmaticSwipe('like'));
            if (undo) undo.addEventListener('click', () => undoLastAction());
        });
        
        // Setup event listeners for card-level Skip and Save buttons
        jobGrid.addEventListener('click', function(e) {
            const skipBtn = e.target.closest('.btn-skip');
            const saveBtn = e.target.closest('.btn-save');
            
            if (skipBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrapper = skipBtn.closest('.swipe-wrapper');
                if (wrapper && wrapper.dataset.swiped !== 'true') {
                    const overlay = wrapper.querySelector('.swipe-overlay');
                    commitSwipe(wrapper, 'dislike', wrapper.dataset.baseTransform || 'translate(-50%, 0)', overlay);
                }
            }
            
            if (saveBtn) {
                e.preventDefault();
                e.stopPropagation();
                const wrapper = saveBtn.closest('.swipe-wrapper');
                if (wrapper && wrapper.dataset.swiped !== 'true') {
                    const overlay = wrapper.querySelector('.swipe-overlay');
                    commitSwipe(wrapper, 'like', wrapper.dataset.baseTransform || 'translate(-50%, 0)', overlay);
                }
            }
        });
    }

    function getAllWrappers() {
        return Array.from(jobGrid.querySelectorAll('.swipe-wrapper'));
    }

    function getActiveWrappers() {
        return getAllWrappers().filter(wrapper => {
            if (wrapper.dataset.swiped === 'true') return false;
            if (wrapper.dataset.filterHidden === 'true') return false;
            if (wrapper.style.display === 'none') return false;
            const card = wrapper.querySelector('.job-card');
            if (!card || card.style.display === 'none') return false;
            return true;
        });
    }

    function resetTransforms() {
        getAllWrappers().forEach(wrapper => {
            wrapper.style.transition = '';
            wrapper.style.transform = '';
            wrapper.style.opacity = '';
        });
    }

    function updateDeckState() {
        const active = getActiveWrappers();
        const isSwipeView = pageLayout.classList.contains('swipe-view');

        if (!state.isMobile && !isSwipeView) {
            if (actionBar) actionBar.style.display = 'none';
            if (noMatchesEl) {
                const anyVisible = active.length > 0;
                noMatchesEl.style.display = anyVisible ? 'none' : 'flex';
            }
            return;
        }

        pageLayout.classList.remove('grid-view', 'list-view');
        pageLayout.classList.add('swipe-view');

        active.forEach((wrapper, idx) => {
            const card = wrapper.querySelector('.job-card');
            const transforms = [
                'translate(-50%, 0) scale(1)',
                'translate(-50%, 12px) scale(0.97)',
                'translate(-50%, 24px) scale(0.94)',
                'translate(-50%, 36px) scale(0.9)'
            ];
            const transform = transforms[Math.min(idx, transforms.length - 1)];
            wrapper.dataset.stack = idx + 1;
            wrapper.style.transition = state.dragging && state.dragging.wrapper === wrapper ? 'none' : 'transform 0.28s ease, opacity 0.25s ease';
            wrapper.style.transform = transform;
            wrapper.dataset.baseTransform = transform;
            wrapper.style.opacity = idx === 0 ? 1 : idx === 1 ? 0.85 : idx === 2 ? 0.65 : 0;
            wrapper.style.pointerEvents = idx === 0 ? 'auto' : 'none';
            if (!wrapper.__swipeBound) {
                wrapper.__swipeBound = true;
                wrapper.addEventListener('pointerdown', pointerStart);
            }
            if (card) {
                const overlay = card.querySelector('.swipe-overlay');
                if (overlay && wrapper.dataset.stack !== '1' && !state.dragging) {
                    overlay.style.opacity = 0;
                    overlay.classList.remove('show-like', 'show-pass');
                }
            }
        });

        if (actionBar) {
            actionBar.style.display = active.length ? 'flex' : 'none';
        }

        updateActionBar();

        if (noMatchesEl) {
            const anyVisible = active.length > 0;
            noMatchesEl.style.display = anyVisible ? 'none' : 'flex';
        }
    }

    function pointerStart(event) {
        const isSwipeView = pageLayout.classList.contains('swipe-view');
        if (!isSwipeView) return;
        if (state.pending) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        
        const wrapper = event.currentTarget;
        const active = getActiveWrappers();
        if (active[0] !== wrapper) return;
        
        const card = wrapper.querySelector('.job-card');
        if (!card) return;
        
        // Don't start drag if clicking on interactive elements
        if (event.target.closest('button') || event.target.closest('a')) return;

        event.preventDefault();
        
        state.dragging = {
            startX: event.clientX,
            startY: event.clientY,
            currentX: event.clientX,
            currentY: event.clientY,
            wrapper,
            card,
            baseTransform: wrapper.dataset.baseTransform || 'translate(-50%, 0) scale(1)',
            overlay: card.querySelector('.swipe-overlay'),
            deltaX: 0,
            deltaY: 0,
            pointerId: event.pointerId,
            directionLocked: false,
            isVerticalScroll: false
        };

        // Add dragging state
        wrapper.classList.add('dragging');
        card.classList.add('dragging');
        
        // Capture pointer for smooth tracking
        wrapper.setPointerCapture(event.pointerId);
        
        // Remove transitions for immediate response
        wrapper.style.transition = 'none';
        card.style.transition = 'none';

        wrapper.addEventListener('pointermove', pointerMove);
        wrapper.addEventListener('pointerup', pointerEnd);
        wrapper.addEventListener('pointercancel', pointerEnd);
    }

    function pointerMove(event) {
        if (!state.dragging) return;
        
        const { card, wrapper, overlay, startX, startY } = state.dragging;
        
        // Calculate drag distance
        const dx = event.clientX - startX;
        const dy = event.clientY - startY;
        
        // Detect if user is scrolling vertically (mobile only)
        const isMobile = window.innerWidth <= 600;
        if (isMobile && !state.dragging.directionLocked) {
            const absDx = Math.abs(dx);
            const absDy = Math.abs(dy);
            
            // If moved more than 10px, lock direction
            if (absDx > 10 || absDy > 10) {
                state.dragging.directionLocked = true;
                state.dragging.isVerticalScroll = absDy > absDx;
            }
        }
        
        // If vertical scroll detected, don't prevent default and don't process horizontal drag
        if (state.dragging.isVerticalScroll) {
            return;
        }
        
        event.preventDefault();
        
        // Update current position
        state.dragging.currentX = event.clientX;
        state.dragging.currentY = event.clientY;
        state.dragging.deltaX = dx;
        state.dragging.deltaY = dy;
        
        // Calculate rotation based on horizontal drag (more subtle)
        const maxRotation = 15;
        const rotationFactor = dx / (wrapper.offsetWidth * 0.5);
        const rotation = Math.max(-maxRotation, Math.min(maxRotation, rotationFactor * maxRotation));
        
        // Apply transform to CARD - card follows cursor exactly
        // Vertical movement is slightly dampened for better feel
        const verticalDamping = 0.3;
        card.style.transform = `translate(${dx}px, ${dy * verticalDamping}px) rotate(${rotation}deg)`;
        
        // Update overlay opacity and visibility based on drag direction
        if (overlay) {
            const dragDistance = Math.abs(dx);
            const threshold = wrapper.offsetWidth * 0.15;
            
            if (dragDistance > 10) {
                // Calculate opacity based on drag distance (0 to 1)
                const intensity = Math.min(dragDistance / threshold, 1);
                overlay.style.opacity = intensity;
                
                // Show appropriate label
                if (dx > 0) {
                    overlay.classList.add('show-like');
                    overlay.classList.remove('show-pass');
                } else {
                    overlay.classList.add('show-pass');
                    overlay.classList.remove('show-like');
                }
            } else {
                // Reset overlay when drag is minimal
                overlay.style.opacity = 0;
                overlay.classList.remove('show-like', 'show-pass');
            }
        }
    }

    function pointerEnd(event) {
        if (!state.dragging) return;
        
        const { wrapper, card, overlay, baseTransform, deltaX, pointerId } = state.dragging;

        // Release pointer capture
        if (pointerId !== undefined) {
            wrapper.releasePointerCapture(pointerId);
        }
        
        // Remove event listeners
        wrapper.removeEventListener('pointermove', pointerMove);
        wrapper.removeEventListener('pointerup', pointerEnd);
        wrapper.removeEventListener('pointercancel', pointerEnd);
        
        // Remove dragging state
        wrapper.classList.remove('dragging');
        card.classList.remove('dragging');

        // Determine if swipe threshold was reached
        // Higher threshold on mobile to prevent accidental swipes while scrolling
        const isMobile = window.innerWidth <= 600;
        const thresholdMultiplier = isMobile ? 0.45 : 0.3;
        const threshold = wrapper.offsetWidth * thresholdMultiplier;
        const shouldSwipe = Math.abs(deltaX) >= threshold;
        
        if (shouldSwipe) {
            // User dragged far enough - commit the swipe
            // Right swipe (positive deltaX) = LIKE, Left swipe (negative deltaX) = DISLIKE
            const direction = deltaX > 0 ? 'like' : 'dislike';
            
            // Re-enable transitions for exit animation
            wrapper.style.transition = 'transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.3s ease';
            
            commitSwipe(wrapper, direction, baseTransform, overlay);
        } else {
            // User didn't drag far enough - snap back to original position
            card.style.transition = 'transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1)';
            card.style.transform = '';
            
            // Fade out overlay
            if (overlay) {
                overlay.style.transition = 'opacity 0.2s ease';
                overlay.style.opacity = 0;
                overlay.classList.remove('show-like', 'show-pass');
            }
            
            // Clean up transitions after animation
            setTimeout(() => {
                if (!state.dragging) {
                    wrapper.style.transition = '';
                    if (card) card.style.transition = '';
                    if (overlay) overlay.style.transition = '';
                }
            }, 360);
        }

        state.dragging = null;
    }

    function commitSwipe(wrapper, direction, baseTransform, overlay) {
        if (state.pending) return;
        const card = wrapper.querySelector('.job-card');
        if (!card) return;
        const jobId = Number(card.dataset.jobId || 0);
        if (!jobId) {
            revertSwipe(wrapper, baseTransform, overlay);
            return;
        }
        if (!currentUserId) {
            revertSwipe(wrapper, baseTransform, overlay);
            showToast('Please sign in to swipe jobs.');
            return;
        }

        const exitTransform = direction === 'like'
            ? 'translate(-50%, 0) translate(-120vw, -28px) rotate(-18deg)'
            : 'translate(-50%, 0) translate(120vw, -28px) rotate(18deg)';

        wrapper.style.transition = 'transform 0.35s ease, opacity 0.3s ease';
        wrapper.style.transform = exitTransform;
        wrapper.style.opacity = 0;

        if (overlay) {
            overlay.style.opacity = 1;
            overlay.classList.toggle('show-like', direction === 'like');
            overlay.classList.toggle('show-pass', direction === 'dislike');
        }

        state.pending = { wrapper, baseTransform, overlay };

        persistSwipe(jobId, direction)
            .then(({ interactionId, swipeType }) => finalizeSwipe(wrapper, card, swipeType, interactionId))
            .catch(error => {
                console.error(error);
                revertSwipe(wrapper, baseTransform, overlay);
                showToast('Unable to save. Try again.');
            });
    }

    function finalizeSwipe(wrapper, card, swipeType, interactionId) {
        const jobId = Number(card.dataset.jobId || 0);
        setTimeout(() => {
            wrapper.dataset.swiped = 'true';
            wrapper.style.display = 'none';
            wrapper.style.opacity = 1;
            wrapper.style.transition = '';
            wrapper.style.transform = wrapper.dataset.baseTransform || 'translate(-50%, 0)';

            const overlay = card.querySelector('.swipe-overlay');
            if (overlay) {
                overlay.style.opacity = 0;
                overlay.classList.remove('show-like', 'show-pass');
            }

            state.pending = null;
            storeLastAction({ wrapper, card, jobId, swipeType, interactionId });
            updateDeckState();
        }, 360);
    }

    function revertSwipe(wrapper, baseTransform, overlay) {
        state.pending = null;
        wrapper.style.transition = 'transform 0.3s ease, opacity 0.2s ease';
        wrapper.style.transform = baseTransform;
        wrapper.style.opacity = 1;
        setTimeout(() => { wrapper.style.transition = ''; }, 320);
        if (overlay) {
            overlay.style.opacity = 0;
            overlay.classList.remove('show-like', 'show-pass');
        }
    }

    function programmaticSwipe(type) {
        // Allow swipe in both mobile and desktop swipe-view mode
        const isSwipeView = pageLayout.classList.contains('swipe-view');
        if ((!state.isMobile && !isSwipeView) || state.pending) return;
        
        const wrappers = getActiveWrappers();
        if (!wrappers.length) return;
        const top = wrappers[0];
        commitSwipe(top, type === 'like' ? 'like' : 'dislike', top.dataset.baseTransform || 'translate(-50%, 0)', top.querySelector('.swipe-overlay'));
    }

    function persistSwipe(jobId, direction) {
        const swipeType = direction === 'like' ? 'like' : 'dislike';
        const payload = {
            action: 'swipe',
            swipe_type: swipeType,
            job_post_id: jobId,
            user_id: currentUserId
        };

        return fetch('interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
        .then(async (res) => {
            const text = await res.text();
            let data;
            try { data = JSON.parse(text); } catch (err) { throw new Error('Invalid response'); }
            if (!res.ok || !data.success) {
                throw new Error(data?.error || 'Request failed');
            }
            return { interactionId: data.id ?? data.interaction_id ?? null, swipeType };
        });
    }

    function storeLastAction(info) {
        if (state.undoTimer) {
            clearTimeout(state.undoTimer);
            state.undoTimer = null;
        }

        // Only enable undo for "like" swipes that have been stored in the database
        // Pass/dislike swipes are NOT stored, so they cannot be undone
        // The job will simply disappear from the deck (but can reappear on page reload)
        if (!info.interactionId || info.swipeType !== 'like') {
            state.lastAction = null;
            updateActionBar();
            return;
        }

        state.lastAction = info;
        updateActionBar();
        state.undoTimer = setTimeout(() => {
            state.lastAction = null;
            state.undoTimer = null;
            updateActionBar();
        }, undoWindowMs);
    }

    function updateActionBar() {
        const isSwipeView = pageLayout.classList.contains('swipe-view');
        const wrappers = getActiveWrappers();
        const hasCards = wrappers.length > 0;
        
        // Update desktop action bar (for swipe-view on desktop)
        if (desktopActionBar) {
            if (isSwipeView && !state.isMobile && hasCards) {
                desktopActionBar.style.display = 'flex';
            } else {
                desktopActionBar.style.display = 'none';
            }
            
            // Update undo button state in desktop bar
            const desktopUndo = desktopActionBar.querySelector('[data-action="undo"]');
            if (desktopUndo) {
                const enabled = Boolean(state.lastAction);
                desktopUndo.disabled = !enabled;
                desktopUndo.classList.toggle('enabled', enabled);
                desktopUndo.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            }
        }
        
        // Update mobile action bar (for mobile only)
        if (mobileActionBar) {
            if (state.isMobile && hasCards) {
                mobileActionBar.style.display = 'flex';
            } else {
                mobileActionBar.style.display = 'none';
            }
            
            // Update undo button state in mobile bar
            const mobileUndo = mobileActionBar.querySelector('[data-action="undo"]');
            if (mobileUndo) {
                const enabled = Boolean(state.lastAction);
                mobileUndo.disabled = !enabled;
                mobileUndo.classList.toggle('enabled', enabled);
                mobileUndo.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            }
        }
    }

    async function undoLastAction() {
        if (!state.lastAction) return;
        const { wrapper, card, jobId, interactionId, swipeType } = state.lastAction;
        try {
            const payload = {
                action: 'undo',
                interaction_id: interactionId,
                job_post_id: jobId,
                swipe_type: swipeType
            };
            const res = await fetch('interactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data?.error || 'Undo failed');
            }

            wrapper.dataset.swiped = '';
            wrapper.style.display = '';
            wrapper.style.opacity = 1;
            wrapper.style.transition = 'transform 0.28s ease, opacity 0.25s ease';
            wrapper.style.transform = 'translate(-50%, -10px) scale(0.98)';
            jobGrid.insertBefore(wrapper, jobGrid.firstChild);

            const overlay = card.querySelector('.swipe-overlay');
            if (overlay) {
                overlay.style.opacity = 0;
                overlay.classList.remove('show-like', 'show-pass');
            }

            state.lastAction = null;
            if (state.undoTimer) {
                clearTimeout(state.undoTimer);
                state.undoTimer = null;
            }
            updateDeckState();
            showToast('Swipe undone');
        } catch (error) {
            console.error(error);
            showToast('Unable to undo. Try again.');
        }
    }

    function handleKeydown(event) {
        const tag = (document.activeElement?.tagName || '').toLowerCase();
        if (['input', 'textarea', 'select'].includes(tag)) return;
        const withinDeck = jobGrid.contains(document.activeElement);
        const allow = state.isMobile || withinDeck;
        if (!allow) return;

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            programmaticSwipe('dislike');
        } else if (event.key === 'ArrowRight' || event.key === 'Enter') {
            event.preventDefault();
            programmaticSwipe('like');
        } else if (event.key.toLowerCase() === 'u') {
            event.preventDefault();
            undoLastAction();
        }
    }

    let toastTimer;
    function showToast(message) {
        let toast = document.querySelector('.swipe-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'swipe-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.add('visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('visible'), 2400);
    }

    // ===================================================================
    // JOB SLIDE PANEL FUNCTIONALITY
    // ===================================================================

    let jobSlidePanel = null;
    let jobSlideOverlay = null;

    function initJobSlidePanel() {
        jobSlideOverlay = document.getElementById('jobSlideOverlay');
        jobSlidePanel = document.getElementById('jobSlidePanel');

        if (!jobSlideOverlay || !jobSlidePanel) {
            console.error('Job slide panel elements not found');
            return;
        }

        // Close panel when clicking overlay
        jobSlideOverlay.addEventListener('click', closeJobSlidePanel);

        // Close panel when clicking close button
        const closeBtn = document.querySelector('.slide-panel-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeJobSlidePanel);
        }

        // Close panel with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && jobSlidePanel.classList.contains('active')) {
                closeJobSlidePanel();
            }
        });

        // Prevent panel from closing when clicking inside the panel
        jobSlidePanel.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        // Make job cards clickable
        attachJobCardClickListeners();
    }

    function attachJobCardClickListeners() {
        // Attach click listeners to all job cards
        document.querySelectorAll('.job-card').forEach(card => {
            // Remove existing click listeners to prevent duplicates
            card.removeEventListener('click', handleJobCardClick);
            
            // Add click listener 
            card.addEventListener('click', handleJobCardClick);
        });
    }

    function handleJobCardClick(e) {
        e.preventDefault();
        e.stopPropagation();

        // Don't open panel during swipe actions
        if (e.currentTarget.classList.contains('dragging')) {
            return;
        }

        const jobCard = e.currentTarget;
        const jobId = jobCard.dataset.jobId;

        if (jobId) {
            openJobSlidePanel(jobId, jobCard);
        }
    }

    function openJobSlidePanel(jobId, jobCard) {
        if (!jobSlidePanel || !jobSlideOverlay) {
            console.error('Job slide panel not initialized');
            return;
        }

        // Show loading state
        showLoadingInPanel();
        
        // Show panel with animation
        jobSlideOverlay.classList.add('active');
        jobSlidePanel.classList.add('active');
        
        // Prevent body scrolling
        document.body.style.overflow = 'hidden';

        // Fetch real job data from database
        fetch(`get_job_details.php?job_post_id=${jobId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    populateJobSlidePanel(data.job);
                } else {
                    showErrorInPanel(data.error || 'Failed to load job details');
                }
            })
            .catch(error => {
                console.error('Error fetching job details:', error);
                showErrorInPanel('Network error occurred');
            });
    }

    function closeJobSlidePanel() {
        if (!jobSlidePanel || !jobSlideOverlay) return;

        jobSlideOverlay.classList.remove('active');
        jobSlidePanel.classList.remove('active');
        
        // Restore body scrolling
        document.body.style.overflow = '';
    }

    function showLoadingInPanel() {
        const content = jobSlidePanel.querySelector('.slide-panel-content');
        if (content) {
            content.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 300px; flex-direction: column; gap: 16px;">
                    <div style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <p style="color: #6b7280; font-size: 14px;">Loading job details...</p>
                </div>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            `;
        }
    }

    function showErrorInPanel(message) {
        const content = jobSlidePanel.querySelector('.slide-panel-content');
        if (content) {
            content.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: center; height: 300px; flex-direction: column; gap: 16px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #ef4444;"></i>
                    <p style="color: #374151; font-size: 16px; text-align: center;">${message}</p>
                    <button class="slide-btn secondary" onclick="closeJobSlidePanel()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }
    }

    function populateJobSlidePanel(jobData) {
        try {
            // Update header title
            const headerTitle = jobSlidePanel.querySelector('.slide-panel-header h2');
            if (headerTitle) {
                headerTitle.textContent = jobData.job_post_name || 'Job Details';
            }

            // Generate comprehensive job details content
            const content = jobSlidePanel.querySelector('.slide-panel-content');
            if (content) {
                content.innerHTML = generateJobDetailsHTML(jobData);
            }

        } catch (error) {
            console.error('Error populating slide panel:', error);
            showErrorInPanel('Error displaying job details');
        }
    }

    function generateJobDetailsHTML(job) {
        const formatSalary = (budget) => {
            if (!budget) return 'Salary negotiable';
            return `₱${parseFloat(budget).toLocaleString()}`;
        };

        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        };

        const formatRequirements = (requirements) => {
            if (!requirements) return '<li>No specific requirements listed</li>';
            const reqList = requirements.split('\n').filter(req => req.trim());
            return reqList.map(req => `<li>${req.trim()}</li>`).join('');
        };

        const formatSkills = (skills) => {
            if (!skills) return '<span class="slide-skill-tag">Not specified</span>';
            const skillList = skills.split(',').map(s => s.trim()).filter(s => s);
            return skillList.map(skill => `<span class="slide-skill-tag">${skill}</span>`).join('');
        };

        const logoSrc = job.company_logo ? 
            (job.company_logo.startsWith('http') ? job.company_logo : `../${job.company_logo}`) :
            '../images/default-company.png';

        return `
            <!-- Job Header -->
            <div class="slide-job-header">
                <img src="${logoSrc}" alt="${job.company_name || 'Company'} logo" class="slide-company-logo" 
                     onerror="this.src='../images/default-company.png'">
                <div class="slide-job-info">
                    <h3>${job.job_post_name || 'Job Position'}</h3>
                    <span class="slide-company-name">${job.company_name || 'Company Name'}</span>
                    ${job.match_score > 0 ? `
                    <div class="slide-match-badge">
                        <i class="fas fa-star"></i>${Math.round(job.match_score)}% Match
                    </div>` : ''}
                </div>
            </div>

            <!-- Quick Info Grid -->
            <div class="slide-job-details">
                <div class="slide-detail-grid">
                    <div class="slide-detail-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>${job.job_location_name || job.city || 'Location TBD'}</span>
                    </div>
                    <div class="slide-detail-item">
                        <span class="peso-sign"></span>
                        <span>${formatSalary(job.budget)}</span>
                    </div>

                    <div class="slide-detail-item">
                        <i class="fas fa-briefcase"></i>
                        <span>${job.job_category_name || 'General'}</span>
                    </div>
                    <div class="slide-detail-item">
                        <i class="fas fa-clock"></i>
                        <span>${job.job_type_name || 'Full-time'}</span>
                    </div>
                    <div class="slide-detail-item">
                        <i class="fas fa-users"></i>
                        <span>${job.vacancies || 1} Position(s)</span>
                    </div>
                    <div class="slide-detail-item">
                        <i class="fas fa-laptop"></i>
                        <span>${job.work_setup_name || 'On-site'}</span>
                    </div>
                </div>
            </div>

            <!-- Job Description -->
            <div class="slide-job-description">
                <h4><i class="fas fa-file-alt"></i> Job Description</h4>
                <p>${job.job_description || 'No description provided.'}</p>
            </div>

            <!-- Requirements -->
            <div class="slide-job-requirements">
                <h4><i class="fas fa-list-check"></i> Requirements</h4>
                <ul>${formatRequirements(job.requirements)}</ul>
            </div>

            <!-- Required Skills -->
            <div class="slide-skills-section">
                <h4><i class="fas fa-cogs"></i> Required Skills</h4>
                <div class="slide-skills-tags">
                    ${formatSkills(job.required_skills)}
                </div>
            </div>

            <!-- Benefits -->
            ${job.benefits ? `
            <div class="slide-benefits-section">
                <h4><i class="fas fa-gift"></i> Benefits</h4>
                <p>${job.benefits}</p>
            </div>` : ''}

            <!-- Job Details Table -->
            <div class="slide-job-specs">
                <h4><i class="fas fa-info-circle"></i> Job Specifications</h4>
                <div class="specs-grid">
                    <div class="spec-row">
                        <span class="spec-label">Experience Level:</span>
                        <span class="spec-value">${job.experience_level_name || 'Not specified'}</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Education Level:</span>
                        <span class="spec-value">${job.education_level_name || 'Not specified'}</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Work Setup:</span>
                        <span class="spec-value">${job.work_setup_name || 'Not specified'}</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Job Status:</span>
                        <span class="spec-value status-active">${job.job_status_name || 'Active'}</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Posted Date:</span>
                        <span class="spec-value">${formatDate(job.created_at)}</span>
                    </div>
                    <div class="spec-row">
                        <span class="spec-label">Last Updated:</span>
                        <span class="spec-value">${formatDate(job.updated_at)}</span>
                    </div>
                </div>
            </div>

            <!-- Company Info -->
            ${job.company_description ? `
            <div class="slide-company-info">
                <h4><i class="fas fa-building"></i> About ${job.company_name}</h4>
                <p>${job.company_description}</p>
                ${job.company_address ? `<p><i class="fas fa-map-marker-alt"></i> ${job.company_address}</p>` : ''}
            </div>` : ''}

            <!-- Action Buttons -->
            <div class="slide-panel-actions">
                <button class="slide-btn primary" onclick="applyToJob('${job.job_post_id}')">
                    <i class="fas fa-paper-plane"></i>
                    Apply Now
                </button>
                <button class="slide-btn secondary" onclick="saveJob('${job.job_post_id}')">
                    <i class="fas fa-bookmark"></i>
                    Save Job
                </button>
            </div>
        `;
    }

    // Job action functions
    function applyToJob(jobId) {
        // Implement apply functionality
        showToast('Application submitted successfully!');
        closeJobSlidePanel();
    }

    function saveJob(jobId) {
        // Implement save functionality
        showToast('Job saved to your favorites!');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJobSlidePanel);
    } else {
        initJobSlidePanel();
    }

    // Re-attach listeners after pagination or filtering
    document.addEventListener('click', function(e) {
        if (e.target.matches('.pagination a, .filter-btn, .view-btn')) {
            setTimeout(attachJobCardClickListeners, 100);
        }
    });

});
</script>

<!-- Mobile Notification Styles -->
<style>
.mobile-notification {
    position: relative;
    margin-left: auto;
}

.mobile-notification .wm-notification-btn {
    background: #1c0966;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    transition: background 0.2s;
}

.mobile-notification .wm-notification-btn:hover {
    background: #e5e5e5;
}

.mobile-notification .wm-notification-btn i {
    font-size: 1.1rem;
    color: #ffffffff;
}

.mobile-notification .notification-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #e74c3c;
    color: white;
    font-size: 0.65rem;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    padding: 0 4px;
}

.mobile-notification .wm-notification-dropdown {
    position: fixed;
    top: 60px;
    right: 10px;
    width: calc(100vw - 20px);
    max-width: 360px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s;
    z-index: 1000;
    max-height: 500px;
    display: flex;
    flex-direction: column;
}

.mobile-notification.active .wm-notification-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.mobile-notification .notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.mobile-notification .notification-header h3 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #111827;
}

.mobile-notification .notification-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #6b7280;
    cursor: pointer;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background 0.2s;
}

.mobile-notification .notification-close:hover {
    background: #f3f4f6;
}

.mobile-notification .notification-list {
    overflow-y: auto;
    max-height: 400px;
    padding: 8px 0;
}

.mobile-notification .notification-list::-webkit-scrollbar {
    width: 6px;
}

.mobile-notification .notification-list::-webkit-scrollbar-track {
    background: #f3f4f6;
}

.mobile-notification .notification-list::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;
}

.mobile-notification .notification-item {
    padding: 12px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.2s;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.mobile-notification .notification-item:hover {
    background: #f9fafb;
}

.mobile-notification .notification-item.unread {
    background: #eff6ff;
}

.mobile-notification .notification-item.unread:hover {
    background: #dbeafe;
}

.mobile-notification .notification-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.9rem;
}

.mobile-notification .notification-icon.like {
    background: #fce7f3;
    color: #ec4899;
}

.mobile-notification .notification-icon.match {
    background: #dcfce7;
    color: #22c55e;
}

.mobile-notification .notification-icon.interview {
    background: #dbeafe;
    color: #3b82f6;
}

.mobile-notification .notification-content {
    flex: 1;
    min-width: 0;
}

.mobile-notification .notification-title {
    font-weight: 600;
    font-size: 0.9rem;
    color: #111827;
    margin: 0 0 4px 0;
}

.mobile-notification .notification-message {
    font-size: 0.85rem;
    color: #6b7280;
    margin: 0 0 4px 0;
    line-height: 1.4;
}

.mobile-notification .notification-time {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}

.mobile-notification .notification-loading,
.mobile-notification .notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #6b7280;
    font-size: 0.9rem;
}

.mobile-notification .notification-empty i {
    font-size: 2.5rem;
    color: #d1d5db;
    margin-bottom: 12px;
    display: block;
}

.mobile-notification .notification-empty p {
    margin: 0;
}
</style>

<!-- Mobile Notification Script -->
<script>
// Mobile Notification System
(function() {
    const mobileNotificationBtn = document.getElementById('mobileNotificationBtn');
    const mobileNotificationWrapper = document.querySelector('.mobile-notification');
    const mobileNotificationClose = document.getElementById('mobileNotificationClose');
    const mobileNotificationList = document.getElementById('mobileNotificationList');

    if (!mobileNotificationBtn || !mobileNotificationWrapper) return;

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Toggle dropdown
    function toggleDropdown() {
        mobileNotificationWrapper.classList.toggle('active');
        if (mobileNotificationWrapper.classList.contains('active')) {
            loadNotifications();
        }
    }

    // Close dropdown
    function closeDropdown() {
        mobileNotificationWrapper.classList.remove('active');
    }

    // Load notifications
    async function loadNotifications() {
        if (!mobileNotificationList) return;
        
        mobileNotificationList.innerHTML = '<div class="notification-loading">Loading...</div>';

        try {
            const response = await fetch('api/get_notifications.php');
            const data = await response.json();

            if (data.success) {
                displayNotifications(data.notifications);
                updateBadge(data.unread_count);
            } else {
                mobileNotificationList.innerHTML = '<div class="notification-empty">Failed to load notifications</div>';
            }
        } catch (error) {
            mobileNotificationList.innerHTML = '<div class="notification-empty">Error loading notifications</div>';
        }
    }

    // Display notifications
    function displayNotifications(notifications) {
        if (notifications.length === 0) {
            mobileNotificationList.innerHTML = '<div class="notification-empty"><i class="fa-solid fa-bell-slash"></i><p>No notifications yet</p></div>';
            return;
        }

        mobileNotificationList.innerHTML = notifications.map(notif => {
            const iconClass = notif.type === 'like' ? 'fa-heart' : 
                             notif.type === 'match' ? 'fa-handshake' : 
                             'fa-calendar-check';
            
            return '<div class="notification-item ' + (!notif.is_read ? 'unread' : '') + '" data-id="' + notif.id + '" onclick="handleMobileNotificationClick(' + notif.id + ')"><div class="notification-icon ' + notif.type + '"><i class="fa-solid ' + iconClass + '"></i></div><div class="notification-content"><p class="notification-title">' + escapeHtml(notif.title) + '</p><p class="notification-message">' + escapeHtml(notif.message) + '</p><p class="notification-time">' + escapeHtml(notif.time_ago) + '</p></div></div>';
        }).join('');
    }

    // Update badge
    function updateBadge(count) {
        const badge = document.getElementById('mobileNotificationBadge');
        if (!badge) return;
        
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    // Handle notification click
    window.handleMobileNotificationClick = async function(notificationId) {
        try {
            await fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });
            loadNotifications();
        } catch (error) {
            console.error('Error:', error);
        }
    };

    // Event listeners
    mobileNotificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleDropdown();
    });

    if (mobileNotificationClose) {
        mobileNotificationClose.addEventListener('click', function(e) {
            e.stopPropagation();
            closeDropdown();
        });
    }

    document.addEventListener('click', function(e) {
        if (!mobileNotificationWrapper.contains(e.target)) {
            closeDropdown();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    // Initial load and auto-refresh
    loadNotifications();
    setInterval(loadNotifications, 60000);
})();
</script>

</body>
</html>
