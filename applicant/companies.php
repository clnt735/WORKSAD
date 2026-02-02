<?php
session_start();
require_once '../database.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$q = trim($_GET['q'] ?? '');
$industry_id = trim($_GET['industry'] ?? '');

// Query: select all companies with correct column names
$sql = "SELECT 
    c.company_id,
    c.user_id,
    c.company_name,
    c.description,
    c.industry,
    c.location,
    c.website,
    c.logo,
    c.status,
    i.industry_id,
    i.industry_name
FROM company c
LEFT JOIN industry i ON c.industry = i.industry_name";
$params = [];
$where = [];

if($q !== ''){ 
    $where[] = "(c.company_name LIKE ? OR c.description LIKE ? OR c.location LIKE ?)"; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
    $params[] = "%$q%"; 
}
if($industry_id !== '' && is_numeric($industry_id)){ 
    $where[] = "i.industry_id = ?"; 
    $params[] = intval($industry_id); 
}

if(count($where) > 0) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$stmt = $conn->prepare($sql);
if($stmt){
    if(count($params)){
        // create types string - 's' for strings, 'i' for industry_id (last param if filtering by industry)
        $types = '';
        foreach($params as $p) {
            $types .= is_int($p) ? 'i' : 's';
        }
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $companies = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $companies = [];
}

// Helper function to escape HTML
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Companies - WorkMuna</title>
  <link rel="stylesheet" href="../styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
  <style>
  /* ==============================
     COMPANIES PAGE - PROFESSIONAL DESIGN
     ============================== */
  :root {
    --cp-primary: #1a73e8;
    --cp-primary-dark: #1557b0;
    --cp-surface: #ffffff;
    --cp-bg: #f8f9fa;
    --cp-border: #dadce0;
    --cp-border-light: #e8eaed;
    --cp-text-primary: #202124;
    --cp-text-secondary: #5f6368;
    --cp-text-tertiary: #80868b;
    --cp-success: #1e8e3e;
    --cp-shadow-sm: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
    --cp-shadow-md: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
    --cp-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .companies-page {
    background: var(--cp-bg);
    min-height: 100vh;
    font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
  }

  main {
    padding: 100px 24px 90px;
    max-width: 1200px;
    margin: 0 auto;
  }

  /* Header Section */
  .companies-header {
    background: var(--cp-surface);
    border-radius: 8px;
    padding: 32px;
    margin-bottom: 24px;
    box-shadow: var(--cp-shadow-sm);
    border: 1px solid var(--cp-border-light);
  }

  .companies-title {
    font-size: clamp(1.75rem, 3vw, 2rem);
    font-weight: 500;
    color: var(--cp-text-primary);
    margin: 0 0 8px 0;
    letter-spacing: -0.01em;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .companies-title i {
    color: var(--cp-primary);
    font-size: 0.875em;
  }

  .companies-sub {
    color: var(--cp-text-secondary);
    margin: 0 0 20px 0;
    font-size: 0.9375rem;
    line-height: 1.5;
  }

  .companies-stats {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid var(--cp-border-light);
  }

  .stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.875rem;
    color: var(--cp-text-secondary);
  }

  .stat-item i {
    color: var(--cp-primary);
    font-size: 1rem;
  }

  .stat-item strong {
    color: var(--cp-text-primary);
    font-weight: 500;
  }

  /* Search Filters */
  .search-filters {
    background: var(--cp-surface);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 24px;
    box-shadow: var(--cp-shadow-sm);
    border: 1px solid var(--cp-border-light);
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
  }

  .search-filters input[type="text"] {
    flex: 1;
    min-width: 240px;
    border: 1px solid var(--cp-border);
    border-radius: 4px;
    padding: 10px 14px;
    font-size: 0.875rem;
    font-family: inherit;
    transition: var(--cp-transition);
    background: var(--cp-surface);
    color: var(--cp-text-primary);
  }

  .search-filters input[type="text"]:hover {
    border-color: var(--cp-text-secondary);
  }

  .search-filters input[type="text"]:focus {
    outline: none;
    border-color: var(--cp-primary);
    box-shadow: 0 0 0 1px var(--cp-primary);
  }

  .search-filters select {
    border: 1px solid var(--cp-border);
    border-radius: 4px;
    padding: 10px 14px;
    font-size: 0.875rem;
    font-family: inherit;
    background: var(--cp-surface);
    color: var(--cp-text-primary);
    cursor: pointer;
    min-width: 160px;
    transition: var(--cp-transition);
  }

  .search-filters select:hover {
    border-color: var(--cp-text-secondary);
  }

  .search-filters select:focus {
    outline: none;
    border-color: var(--cp-primary);
    box-shadow: 0 0 0 1px var(--cp-primary);
  }

  .company-search-btn {
    padding: 10px 24px;
    background: var(--cp-primary);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.875rem;
    cursor: pointer;
    transition: var(--cp-transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  }

  .company-search-btn:hover {
    background: var(--cp-primary-dark);
    box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  }

  .company-search-btn:active {
    background: var(--cp-primary-dark);
    box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
  }

  .company-search-btn i {
    font-size: 0.875rem;
  }

  .results-count {
    margin: 20px 0 12px 0;
    color: var(--cp-text-secondary);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .results-count i {
    color: var(--cp-primary);
  }

  /* Company Cards Grid */
  .companies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .company-card {
    background: var(--cp-surface);
    border-radius: 8px;
    padding: 20px;
    border: 1px solid var(--cp-border-light);
    box-shadow: var(--cp-shadow-sm);
    transition: var(--cp-transition);
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .company-card:hover {
    box-shadow: var(--cp-shadow-md);
    transform: translateY(-2px);
  }

  .logo-wrap {
    display: flex;
    align-items: flex-start;
    gap: 16px;
  }

  .logo-wrap img.logo {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    object-fit: contain;
    background: var(--cp-surface);
    padding: 6px;
    border: 2px solid var(--cp-border-light);
    flex-shrink: 0;
    transition: var(--cp-transition);
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  }

  .company-card:hover .logo {
    border-color: var(--cp-primary);
    box-shadow: 0 2px 6px rgba(26, 115, 232, 0.2);
  }

  .company-logo-link,
  .company-name-link {
    text-decoration: none;
    color: inherit;
  }

  .company-card h3 {
    margin: 0 0 4px 0;
    font-size: 1rem;
    font-weight: 500;
    color: var(--cp-text-primary);
    line-height: 1.4;
    transition: var(--cp-transition);
  }

  .company-name-link:hover h3 {
    color: var(--cp-primary);
  }

  .company-card .meta {
    color: var(--cp-text-tertiary);
    font-size: 0.8125rem;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .company-card .meta i {
    color: var(--cp-text-secondary);
    font-size: 0.75rem;
  }

  .company-card .body {
    color: var(--cp-text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .company-card .info-row {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--cp-border-light);
    font-size: 0.8125rem;
    color: var(--cp-text-secondary);
  }

  .company-card .info-row > div {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .company-card .info-row i {
    color: var(--cp-text-tertiary);
    font-size: 0.8125rem;
  }

  /* Job count badge styling */
  .company-card .info-row .job-count-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #e8f0fe;
    color: var(--cp-primary);
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 500;
  }

  .company-card .info-row .job-count-badge i {
    color: var(--cp-primary);
    font-size: 0.75rem;
  }

  .company-card .info-row .job-count-badge span {
    font-weight: 600;
  }

  .company-card .info-row a {
    color: var(--cp-primary);
    text-decoration: none;
    font-weight: 400;
    transition: var(--cp-transition);
  }

  .company-card .info-row a:hover {
    text-decoration: underline;
  }

  /* Slide Panel Styling */
  .company-slide-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(32, 33, 36, 0.6);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: var(--cp-transition);
  }

  .company-slide-overlay.active {
    opacity: 1;
    visibility: visible;
  }

  .company-slide-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 90%;
    max-width: 480px;
    height: 100%;
    background: var(--cp-surface);
    box-shadow: 0 8px 10px 1px rgba(0, 0, 0, 0.14), 0 3px 14px 2px rgba(0, 0, 0, 0.12), 0 5px 5px -3px rgba(0, 0, 0, 0.2);
    z-index: 9999;
    transform: translateX(100%);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
  }

  .company-slide-panel.active {
    transform: translateX(0);
  }

  .slide-panel-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--cp-border-light);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--cp-surface);
  }

  .slide-panel-header h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 500;
    color: var(--cp-text-primary);
  }

  .slide-panel-close {
    background: transparent;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--cp-transition);
    color: var(--cp-text-secondary);
    font-size: 1.5rem;
  }

  .slide-panel-close:hover {
    background: rgba(95, 99, 104, 0.1);
  }

  .slide-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
  }

  .slide-company-header {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--cp-border-light);
  }

  .slide-company-logo {
    width: 72px;
    height: 72px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--cp-border-light);
    background: var(--cp-bg);
    padding: 8px;
  }

  .slide-company-info {
    flex: 1;
  }

  .slide-company-info h3 {
    margin: 0 0 8px 0;
    font-size: 1.375rem;
    font-weight: 500;
    color: var(--cp-text-primary);
  }

  .slide-company-industry {
    display: inline-block;
    background: #e8f0fe;
    color: var(--cp-primary);
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 0.8125rem;
    font-weight: 500;
  }

  .slide-company-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
  }

  .slide-detail-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    background: var(--cp-bg);
    border-radius: 8px;
  }

  .slide-detail-item i {
    color: var(--cp-text-secondary);
    font-size: 1rem;
    margin-top: 2px;
  }

  .slide-detail-item a {
    color: var(--cp-primary);
    text-decoration: none;
    font-weight: 400;
    word-break: break-all;
    font-size: 0.875rem;
  }

  .slide-detail-item a:hover {
    text-decoration: underline;
  }

  .slide-company-description {
    margin-bottom: 24px;
  }

  .slide-company-description h4 {
    margin: 0 0 12px 0;
    font-size: 1rem;
    font-weight: 500;
    color: var(--cp-text-primary);
  }

  .slide-company-description p {
    color: var(--cp-text-secondary);
    line-height: 1.6;
    font-size: 0.875rem;
  }

  .slide-panel-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
  }

  .slide-btn {
    padding: 10px 24px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.875rem;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: var(--cp-transition);
    border: 1px solid;
  }

  .slide-btn.primary {
    background: var(--cp-primary);
    border-color: var(--cp-primary);
    color: white;
    box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  }

  .slide-btn.primary:hover {
    background: var(--cp-primary-dark);
    box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  }

  .slide-btn.secondary {
    background: var(--cp-surface);
    border-color: var(--cp-border);
    color: var(--cp-text-primary);
  }

  .slide-btn.secondary:hover {
    background: var(--cp-bg);
    border-color: var(--cp-text-secondary);
  }

  /* Empty State */
  .empty-companies {
    grid-column: 1 / -1;
    text-align: center;
    padding: 64px 32px;
    background: var(--cp-surface);
    border-radius: 8px;
    border: 1px solid var(--cp-border-light);
  }

  .empty-companies i {
    font-size: 3.5rem;
    color: var(--cp-text-tertiary);
    margin-bottom: 16px;
    opacity: 0.5;
  }

  .empty-companies h3 {
    margin: 16px 0 8px 0;
    color: var(--cp-text-primary);
    font-size: 1.25rem;
    font-weight: 500;
  }

  .empty-companies p {
    color: var(--cp-text-secondary);
    margin: 0;
    font-size: 0.9375rem;
  }

  /* Mobile Responsiveness */
  @media (max-width: 768px) {
    main {
      padding: 90px 16px 100px;
    }

    .companies-header {
      padding: 24px;
    }

    .companies-title {
      font-size: 1.5rem;
    }

    .companies-stats {
      flex-direction: column;
      gap: 12px;
    }

    .search-filters {
      flex-direction: column;
      padding: 16px;
      gap: 12px;
    }

    .search-filters input[type="text"],
    .search-filters select {
      width: 100%;
      min-width: 0;
    }

    .companies-grid {
      grid-template-columns: 1fr;
      gap: 16px;
    }

    .company-card {
      padding: 16px;
    }

    .company-card h3 {
      font-size: 1.125rem;
    }

    .company-card .body {
      font-size: 0.875rem;
    }

    .company-slide-panel {
      width: 100%;
      max-width: 100%;
    }

    .slide-panel-content {
      padding: 20px;
    }

    .slide-company-header {
      flex-direction: column;
      align-items: flex-start;
      text-align: left;
      gap: 12px;
    }

    .slide-panel-actions {
      flex-direction: column;
    }

    .slide-btn {
      width: 100%;
    }
  }

  @media (max-width: 600px) {
    main {
      padding: 80px 12px 90px;
    }

    .companies-header {
      padding: 16px 14px;
      border-radius: 6px;
      margin-bottom: 16px;
      margin-top: -25px;
    }

    .companies-title {
      font-size: 1.25rem;
      gap: 8px;
      margin-bottom: 6px;
    }

    .companies-title i {
      font-size: 1rem;
    }

    .companies-sub {
      font-size: 0.8125rem;
      margin-bottom: 12px;
      line-height: 1.4;
    }

    .companies-stats {
      gap: 16px;
      padding-top: 12px;
    }

    .stat-item {
      font-size: 0.75rem;
    }

    .stat-item i {
      font-size: 0.875rem;
    }

    .search-filters {
      padding: 10px;
      gap: 8px;
      margin-bottom: 16px;
      display: flex !important;
      flex-direction: column !important;
      align-items: stretch !important;
    }

    .search-filters input[type="text"],
    .search-filters select {
      width: 100% !important;
      max-width: 100% !important;
      min-width: unset !important;
      flex: none !important;
      display: block !important;
      font-size: 13px !important;
      padding: 8px 12px !important;
      height: 36px !important;
      line-height: 1.2 !important;
      box-sizing: border-box !important;
    }

    .company-search-btn {
      width: 100%;
      padding: 9px 20px;
      font-size: 0.875rem;
      height: 38px;
      justify-content: center;
    }

    .results-count {
      font-size: 0.8125rem;
      margin: 12px 0 10px 0;
    }

    .companies-grid {
      grid-template-columns: 1fr;
      gap: 12px;
    }

    .company-card {
      padding: 16px;
      gap: 14px;
      width: 350px;
    }

    .logo-wrap {
      gap: 12px;
    }

    .logo-wrap img.logo {
      width: 48px;
      height: 48px;
      padding: 6px;
    }

    .company-card h3 {
      font-size: 0.9375rem;
      line-height: 1.3;
      margin-bottom: 4px;
    }

    .company-card .meta {
      font-size: 0.75rem;
      gap: 4px;
    }

    .company-card .meta i {
      font-size: 0.6875rem;
    }

    .company-card .body {
      font-size: 0.8125rem;
      line-height: 1.5;
      -webkit-line-clamp: 3;
    }

    .company-card .info-row > div {
      gap: 4px;
    }

    .company-card .info-row i {
      font-size: 0.75rem;
    }

    .slide-company-logo {
      width: 64px;
      height: 64px;
    }

    .slide-company-info h3 {
      font-size: 1.125rem;
    }

    .slide-company-industry {
      font-size: 0.75rem;
      padding: 3px 10px;
    }

    .slide-detail-item {
      padding: 10px;
      font-size: 0.8125rem;
    }

    .slide-detail-item i {
      font-size: 0.875rem;
    }

    .slide-company-description h4 {
      font-size: 0.9375rem;
    }

    .slide-company-description p {
      font-size: 0.8125rem;
    }

    .empty-companies {
      padding: 48px 24px;
    }

    .empty-companies i {
      font-size: 2.5rem;
    }

    .empty-companies h3 {
      font-size: 1.125rem;
    }

    .empty-companies p {
      font-size: 0.8125rem;
    }

    .company-card .logo-wrap {
        align-items: center;
    }

    .company-card .logo-wrap img.logo {
        width: 52px;
        height: 52px;
        padding: 6px;
        border: 1.5px solid var(--cp-border);
    }

    .company-card .info-row {
        padding-top: 12px;
        gap: 10px;
        flex-direction: row;
        flex-wrap: wrap;
    }

    .company-card .info-row > div {
        font-size: 0.75rem;
        font-weight: 500;
    }

    .company-card .info-row > div:last-child {
        color: var(--cp-primary);
        font-weight: 600;
    }
  }

  /* Animations */
  @keyframes fadeInUp {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .company-card {
    animation: fadeInUp 0.4s ease forwards;
  }

  .company-card:nth-child(1) { animation-delay: 0.05s; }
  .company-card:nth-child(2) { animation-delay: 0.1s; }
  .company-card:nth-child(3) { animation-delay: 0.15s; }
  .company-card:nth-child(4) { animation-delay: 0.2s; }
  .company-card:nth-child(5) { animation-delay: 0.25s; }
  .company-card:nth-child(6) { animation-delay: 0.3s; }
  </style>
</head>
<body class="companies-page">


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
    
    <!-- <div class="header-actions">
      
      <a class="search-bar">
        <i class="fa-solid fa-magnifying-glass"></i>
      </a>

      <a href="/WORKSAD/applicant/notifications.php" class="notification-bell">
        <i class="fa-regular fa-bell"></i>
        <span class="badge">3</span>
      </a>

    
        <div class="menu-toggle" id="menu-toggle">☰</div>
    </div> -->

    <!-- Sidebar mobile only -->
    <aside class="sidebar" id="sidebar">
    <!-- Close Button -->
    <button class="close-btn" id="closeSidebar">&times;</button>
        <ul class="mobnav-links">
            <li><a href="home.php">Home</a></li>
            <li><a href="search_jobs.php">Jobs</a></li>
            <li><a href="companies.php">Companies</a></li>
            <li><a href="application.php">Applications</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
</header>

<div class="mobile-search-bar" id="mobileSearchBar">
    <input type="text" placeholder="Search jobs, companies..." />
</div>


 <!-- ======= DESKTOP HEADER ======= -->
<?php $activePage = 'companies'; include 'header.php'; ?>

<main>
  <div class="companies-header">
    <h1 class="companies-title">
      <i class="fas fa-building"></i>
      Companies in Your Area
    </h1>
    <p class="companies-sub">Discover companies hiring in your area and explore exciting career opportunities</p>
    
    <div class="companies-stats">
      <div class="stat-item">
        <i class="fas fa-building"></i>
        <span><strong><?= count($companies) ?></strong> Companies</span>
      </div>
      <div class="stat-item">
        <i class="fas fa-briefcase"></i>
        <span><strong>
          <?php
            $totalJobs = 0;
            foreach($companies as $c) {
              $userId = intval($c['user_id'] ?? 0);
              $jobCountStmt = $conn->prepare("SELECT COUNT(*) as job_count FROM job_post WHERE user_id = ? AND (job_status_id = 1 OR job_status_id IS NULL)");
              if($jobCountStmt){
                  $jobCountStmt->bind_param('i', $userId);
                  $jobCountStmt->execute();
                  $jobCountRes = $jobCountStmt->get_result();
                  $totalJobs += ($jobCountRow = $jobCountRes->fetch_assoc()) ? (int)$jobCountRow['job_count'] : 0;
                  $jobCountStmt->close();
              }
            }
            echo $totalJobs;
          ?>
        </strong> Open Positions</span>
      </div>
      <div class="stat-item">
        <i class="fas fa-calendar"></i>
        <span><?= date('F j, Y') ?></span>
      </div>
    </div>
  </div>

  <form class="search-filters" id="companySearchForm">
    <input type="text" name="q" id="searchInput" placeholder="Search companies by name, location..." value="<?= htmlspecialchars($q) ?>" autocomplete="off">
    <select name="industry" id="industryFilter">
      <option value="">All Industries</option>
      <?php
        $indRes = $conn->query("SELECT industry_id, industry_name FROM industry WHERE is_archived = 0 ORDER BY industry_name");
        if($indRes){ 
          while($row = $indRes->fetch_assoc()){ 
            $ind_id = $row['industry_id']; 
            $ind_name = $row['industry_name']; 
            $selected = ($industry_id !== '' && $industry_id == $ind_id) ? ' selected' : '';
            echo '<option value="'.htmlspecialchars($ind_id).'"'.$selected.'>'.htmlspecialchars($ind_name).'</option>'; 
          } 
        }
      ?>
    </select>
  </form>

  <div class="companies-grid">
    <?php if (empty($companies)): ?>
      <div class="empty-companies">
        <i class="fas fa-building-slash"></i>
        <h3>No Companies Found</h3>
        <p>Try adjusting your search filters or check back later for new companies.</p>
      </div>
    <?php else: ?>
    <?php foreach($companies as $c):
      $logo = !empty($c['logo']) ? '../'.e($c['logo']) : '../assets/company-placeholder.png';
      $name = e($c['company_name'] ?? 'Unnamed');
      // Use industry_name from joined industry table, fallback to company.industry
      $industryVal = e($c['industry_name'] ?? $c['industry'] ?? '');
      $locationVal = e($c['location'] ?? '');
      $desc = e($c['description'] ?? '');
      $website = e($c['website'] ?? '');
      $companyId = intval($c['company_id'] ?? 0);
      $userId = intval($c['user_id'] ?? 0);
      $status = e($c['status'] ?? '');
      
      // Count open positions for this company from job_post table
      // Note: job_post links to employer via user_id, not company_id (which is often NULL)
      $jobCountStmt = $conn->prepare("SELECT COUNT(*) as job_count FROM job_post WHERE user_id = ? AND (job_status_id = 1 OR job_status_id IS NULL)");
      if($jobCountStmt){
          $jobCountStmt->bind_param('i', $userId);
          $jobCountStmt->execute();
          $jobCountRes = $jobCountStmt->get_result();
          $open = ($jobCountRow = $jobCountRes->fetch_assoc()) ? (int)$jobCountRow['job_count'] : 0;
          $jobCountStmt->close();
      } else {
          $open = 0;
      }
    ?>
    <div class="company-card" 
         data-company-id="<?= $companyId ?>"
         data-company-name="<?= $name ?>"
         data-company-logo="<?= $logo ?>"
         data-company-industry="<?= $industryVal ?>"
         data-company-location="<?= $locationVal ?>"
         data-company-description="<?= $desc ?>"
         data-company-website="<?= $website ?>"
         data-company-status="<?= $status ?>"
         data-company-jobs="<?= $open ?>">
      <div class="logo-wrap">
        <a href="view_company.php?id=<?= $companyId ?>" class="company-logo-link" onclick="event.stopPropagation();">
          <img src="<?= $logo ?>" alt="<?= $name ?>" class="logo">
        </a>
        <div style="flex:1;">
          <a href="view_company.php?id=<?= $companyId ?>" class="company-name-link" onclick="event.stopPropagation();">
            <h3><?= $name ?></h3>
          </a>
          <?php if($industryVal): ?>
          <div class="meta">
            <i class="fas fa-industry"></i>
            <?= $industryVal ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="body"><?= strlen($desc) > 180 ? substr($desc,0,180).'...' : ($desc ?: 'No description available.') ?></div>
      <div class="info-row">
        <?php if($locationVal): ?>
        <div>
          <i class="fa-solid fa-location-dot"></i>
          <?= $locationVal ?>
        </div>
        <?php endif; ?>
        <?php if($website): ?>
        <div>
          <i class="fa-solid fa-globe"></i>
          <a href="<?= $website ?>" target="_blank" onclick="event.stopPropagation();">Website</a>
        </div>
        <?php endif; ?>
        <div class="job-count-badge">
          <i class="fas fa-briefcase"></i>
          <span><?= $open ?></span> opening<?= $open !== 1 ? 's' : '' ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Company Slide Panel -->
  <div class="company-slide-overlay" id="companySlideOverlay"></div>
  <div class="company-slide-panel" id="companySlidePanel">
    <div class="slide-panel-header">
      <button class="slide-panel-close" id="slidePanelClose">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <h2>Company Details</h2>
    </div>
    <div class="slide-panel-content" id="slidePanelContent">
      <!-- Content will be loaded dynamically -->
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

    <a href="#" class="<?= ($activePage==='companies') ? 'active' : '' ?>">
      <span class="material-symbols-outlined">business</span>
      Company
    </a>

    <a href="profile.php" class="<?= ($activePage==='profile') ? 'active' : '' ?>">
      <span class="material-symbols-outlined">person</span>
      Profile
    </a>
  </nav>








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

    // Move panel back to original position
    document.querySelector(".resume-card").appendChild(panel);
  });
});








// Sidebar toggle
const menuToggle = document.getElementById("menu-toggle");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");
const closeBtn = document.getElementById("closeSidebar");

if (menuToggle && sidebar) {
  // Toggle sidebar when hamburger is clicked
  menuToggle.addEventListener("click", (e) => {
    e.stopPropagation(); // prevent click from bubbling to document
    sidebar.classList.toggle("active");
    if (overlay) overlay.classList.toggle("active"); // toggle overlay
  });

  // Close sidebar if clicked outside
  document.addEventListener("click", (e) => {
    if (
      sidebar.classList.contains("active") &&
        !sidebar.contains(e.target) &&
        !menuToggle.contains(e.target)
      )
       {
      sidebar.classList.remove("active");
    }
  });

   // Close sidebar when clicking overlay directly
  if (overlay) {
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("active");
      overlay.classList.remove("active");
    });
  }


  // Close sidebar with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar.classList.contains("active")) {
      sidebar.classList.remove("active");
      if (overlay) overlay.classList.remove("active"); // hide overlay
    }
  });
}


  // Close sidebar with x
  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      sidebar.classList.remove("active");
      overlay.style.display = "none";
    });
  }



    //Open search bar on mobile
const searchBarBtn = document.querySelector(".search-bar");
if (searchBarBtn) {
  searchBarBtn.addEventListener("click", function (e) {
    if (window.innerWidth <= 600) {
        e.preventDefault(); 
        
        const bar = document.getElementById("mobileSearchBar");
        bar.classList.toggle("open");
    }
  });
}


// =============================================
// Company Slide Panel Functionality
// =============================================
(function() {
    const slidePanel = document.getElementById('companySlidePanel');
    const slideOverlay = document.getElementById('companySlideOverlay');
    const slidePanelClose = document.getElementById('slidePanelClose');
    const slidePanelContent = document.getElementById('slidePanelContent');
    const companyCards = document.querySelectorAll('.company-card');

    // Open slide panel when clicking on company card
    companyCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't open panel if clicking on logo/name links (they go to view_company.php)
            if (e.target.closest('.company-logo-link') || e.target.closest('.company-name-link')) {
                return;
            }
            
            const companyId = this.dataset.companyId;
            const companyName = this.dataset.companyName;
            const companyLogo = this.dataset.companyLogo;
            const companyIndustry = this.dataset.companyIndustry;
            const companyLocation = this.dataset.companyLocation;
            const companyDescription = this.dataset.companyDescription;
            const companyWebsite = this.dataset.companyWebsite;
            const companyStatus = this.dataset.companyStatus;
            const companyJobs = this.dataset.companyJobs;

            // Build panel content
            let content = `
                <div class="slide-company-header">
                    <a href="view_company.php?id=${companyId}" class="slide-logo-link">
                        <img src="${companyLogo}" alt="${companyName}" class="slide-company-logo">
                    </a>
                    <div class="slide-company-info">
                        <a href="view_company.php?id=${companyId}" class="slide-company-name-link">
                            <h3>${companyName}</h3>
                        </a>
                        <span class="slide-company-industry">${companyIndustry}</span>
                    </div>
                </div>
                
                <div class="slide-company-details">
                    <div class="slide-detail-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>${companyLocation || 'Location not specified'}</span>
                    </div>
                    ${companyWebsite ? `
                    <div class="slide-detail-item">
                        <i class="fa-solid fa-globe"></i>
                        <a href="${companyWebsite}" target="_blank">${companyWebsite}</a>
                    </div>
                    ` : ''}
                    <div class="slide-detail-item">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>${companyJobs} open position${companyJobs != 1 ? 's' : ''}</span>
                    </div>
                    ${companyStatus ? `
                    
                    ` : ''}
                </div>

                <div class="slide-company-description">
                    <h4>About</h4>
                    <p>${companyDescription || 'No description available.'}</p>
                </div>

                <div class="slide-panel-actions">
                    <a href="view_company.php?id=${companyId}" class="slide-btn primary">
                        <i class="fa-solid fa-building"></i> View Full Profile
                    </a>
                    <a href="search_jobs.php?company=${encodeURIComponent(companyName)}" class="slide-btn secondary">
                        <i class="fa-solid fa-magnifying-glass"></i> View Jobs
                    </a>
                </div>
            `;

            slidePanelContent.innerHTML = content;
            
            // Open panel
            slidePanel.classList.add('active');
            slideOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close panel when clicking close button
    if (slidePanelClose) {
        slidePanelClose.addEventListener('click', closeSlidePanel);
    }

    // Close panel when clicking overlay
    if (slideOverlay) {
        slideOverlay.addEventListener('click', closeSlidePanel);
    }

    // Close panel with ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && slidePanel.classList.contains('active')) {
            closeSlidePanel();
        }
    });

    function closeSlidePanel() {
        slidePanel.classList.remove('active');
        slideOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
})();

// =============================================
// Live Search Functionality
// =============================================
(function() {
    const searchInput = document.getElementById('searchInput');
    const industryFilter = document.getElementById('industryFilter');
    const companiesGrid = document.querySelector('.companies-grid');
    const searchForm = document.getElementById('companySearchForm');
    
    let searchTimeout = null;
    
    // Debounce function to limit API calls
    function debounce(func, delay) {
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => func.apply(context, args), delay);
        };
    }
    
    // Perform live search
    function performLiveSearch() {
        const searchQuery = searchInput.value.trim();
        const selectedIndustry = industryFilter.value;
        
        // Build query parameters
        const params = new URLSearchParams();
        if (searchQuery) params.append('q', searchQuery);
        if (selectedIndustry) params.append('industry', selectedIndustry);
        
        // Update URL without page reload
        const newUrl = params.toString() ? `?${params.toString()}` : 'companies.php';
        window.history.pushState({}, '', newUrl);
        
        // Fetch filtered results
        fetch(`companies.php?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            // Parse the response and extract companies grid
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newGrid = doc.querySelector('.companies-grid');
            
            if (newGrid) {
                companiesGrid.innerHTML = newGrid.innerHTML;
                
                // Re-initialize slide panel functionality for new cards
                reinitializeSlidePanel();
            }
        })
        .catch(error => {
            console.error('Live search error:', error);
        });
    }
    
    // Re-initialize slide panel for newly loaded cards
    function reinitializeSlidePanel() {
        const companyCards = document.querySelectorAll('.company-card');
        const slidePanel = document.getElementById('companySlidePanel');
        const slideOverlay = document.getElementById('companySlideOverlay');
        const slidePanelContent = document.getElementById('slidePanelContent');
        
        companyCards.forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.closest('a')) return;
                
                const companyId = this.dataset.companyId;
                const companyName = this.querySelector('h3').textContent;
                const companyIndustry = this.querySelector('.meta').textContent;
                const companyLogo = this.querySelector('.logo')?.src || '';
                const companyDescription = this.querySelector('.body')?.textContent || 'No description available.';
                const companyLocation = this.querySelector('.info-row i.fa-location-dot')?.parentElement?.textContent?.trim() || '';
                const companyWebsite = this.querySelector('.info-row i.fa-globe')?.parentElement?.querySelector('a')?.href || '';
                
                // Populate slide panel
                slidePanelContent.innerHTML = `
                    <div class=\"slide-company-header\">
                        <img src=\"${companyLogo}\" alt=\"${companyName}\" class=\"slide-company-logo\">
                        <div class=\"slide-company-info\">
                            <h3>${companyName}</h3>
                            <span class=\"slide-company-industry\"><i class=\"fas fa-industry\"></i> ${companyIndustry}</span>
                        </div>
                    </div>
                    <div class=\"slide-company-details\">
                        ${companyLocation ? `<div class=\"slide-detail-item\"><i class=\"fas fa-location-dot\"></i> <span>${companyLocation}</span></div>` : ''}
                        ${companyWebsite ? `<div class=\"slide-detail-item\"><i class=\"fas fa-globe\"></i> <a href=\"${companyWebsite}\" target=\"_blank\">${companyWebsite}</a></div>` : ''}
                    </div>
                    <div class=\"slide-company-description\">
                        <h4>About Company</h4>
                        <p>${companyDescription}</p>
                    </div>
                    <div class=\"slide-panel-actions\">
                        <a href=\"view_company.php?id=${companyId}\" class=\"slide-btn primary\"><i class=\"fas fa-briefcase\"></i> View Open Positions</a>
                        ${companyWebsite ? `<a href=\"${companyWebsite}\" target=\"_blank\" class=\"slide-btn secondary\"><i class=\"fas fa-external-link-alt\"></i> Visit Website</a>` : ''}
                    </div>
                `;
                
                slidePanel.classList.add('active');
                slideOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });
    }
    
    // Prevent form submission
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
        });
    }
    
    // Attach live search to input (with debouncing)
    if (searchInput) {
        searchInput.addEventListener('input', debounce(performLiveSearch, 500));
    }
    
    // Attach live search to industry filter
    if (industryFilter) {
        industryFilter.addEventListener('change', performLiveSearch);
    }
})();
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
