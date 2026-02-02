<?php
session_start();

$isJsonPost = $_SERVER['REQUEST_METHOD'] === 'POST'
    && strpos(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') === 0;

if (empty($_SESSION['user_id'])) {
    if ($isJsonPost) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$mysqli = new mysqli('127.0.0.1', 'root', '', 'worksad');

if ($mysqli->connect_errno) {
    if ($isJsonPost) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
    } else {
        http_response_code(500);
        echo 'Database connection failed.';
    }
    exit;
}

if ($isJsonPost) {
    header('Content-Type: application/json');
    handleSwipeApi($mysqli, $userId);
    exit;
}

/**
 * Handle swipe API requests (like, undo)
 */
function handleSwipeApi(mysqli $mysqli, int $userId): void {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        return;
    }

    $action = $payload['action'] ?? '';

    if ($action === 'swipe') {
        handleSwipeStore($mysqli, $userId, $payload);
    } elseif ($action === 'undo') {
        handleSwipeUndo($mysqli, $userId, $payload);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unsupported action']);
    }
}

/**
 * Handle storing a like swipe
 * Only "like" swipes are stored in applicant_job_swipes
 * "dislike/pass" swipes are NOT stored - the job simply disappears from deck
 */
function handleSwipeStore(mysqli $mysqli, int $userId, array $payload): void {
    $jobId = (int)($payload['job_post_id'] ?? 0);
    $swipeType = $payload['swipe_type'] ?? '';

    if (!$jobId || !in_array($swipeType, ['like', 'dislike'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid swipe payload']);
        return;
    }

    // Only store "like" swipes in applicant_job_swipes
    // "dislike/pass" swipes are NOT saved - they just remove the job from the deck view
    if ($swipeType === 'like') {
        $mysqli->begin_transaction();

        try {
            // Insert into applicant_job_swipes table (using MySQL NOW() for accurate server timestamp)
            $swipeId = insertApplicantSwipe($mysqli, $userId, $jobId);
            
            // Get employer_id for this job post to send notification
            $getEmployer = $mysqli->prepare("SELECT user_id FROM job_post WHERE job_post_id = ?");
            if ($getEmployer) {
                $getEmployer->bind_param('i', $jobId);
                $getEmployer->execute();
                $result = $getEmployer->get_result();
                if ($row = $result->fetch_assoc()) {
                    $employerId = (int)$row['user_id'];
                    // Create like notification for employer
                    require_once '../backend/create_like_notification.php';
                    createLikeNotification($mysqli, $swipeId, $employerId, 'employer', $userId, 'applicant');
                }
                $getEmployer->close();
            }

            // Check if employer also liked this applicant for this job post
            // If yes, create a mutual match
            $checkEmployerLike = $mysqli->prepare("
                SELECT eas.employer_id 
                FROM employer_applicant_swipes eas
                INNER JOIN job_post jp ON jp.job_post_id = eas.job_post_id
                WHERE eas.applicant_id = ? 
                AND eas.job_post_id = ? 
                AND eas.swipe_type = 'like'
            ");
            if ($checkEmployerLike) {
                $checkEmployerLike->bind_param('ii', $userId, $jobId);
                $checkEmployerLike->execute();
                $result = $checkEmployerLike->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $employerId = (int)$row['employer_id'];
                    $checkEmployerLike->close();
                    
                    // Check if match already exists
                    $checkMatch = $mysqli->prepare("SELECT match_id FROM matches WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?");
                    if ($checkMatch) {
                        $checkMatch->bind_param('iii', $employerId, $userId, $jobId);
                        $checkMatch->execute();
                        $checkMatch->store_result();
                        
                        if ($checkMatch->num_rows === 0) {
                            // Create new match
                            $checkMatch->close();
                            $createMatch = $mysqli->prepare("INSERT INTO matches (employer_id, applicant_id, job_post_id, matched_at) VALUES (?, ?, ?, NOW())");
                            if ($createMatch) {
                                $createMatch->bind_param('iii', $employerId, $userId, $jobId);
                                $createMatch->execute();
                                $matchId = $mysqli->insert_id;
                                $createMatch->close();
                                error_log("Match created (applicant side): employer=$employerId, applicant=$userId, job=$jobId");
                                
                                // Create match notifications for both parties
                                require_once '../backend/create_match_notification.php';
                                createMatchNotifications($mysqli, $matchId, $userId, $employerId);
                            }
                        } else {
                            $checkMatch->close();
                        }
                    }
                } else {
                    $checkEmployerLike->close();
                }
            }

            $mysqli->commit();
            echo json_encode([
                'success' => true,
                'id' => (int)$swipeId,
                'swipe_type' => $swipeType
            ]);
        } catch (Throwable $e) {
            $mysqli->rollback();
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Unable to save like: ' . $e->getMessage()]);
        }
    } else {
        // For "dislike/pass", we don't store anything
        // The job will still appear on next page reload (unless we track it)
        // Per requirement: only likes are stored
        echo json_encode([
            'success' => true,
            'id' => 0,
            'swipe_type' => $swipeType,
            'message' => 'Pass action not stored'
        ]);
    }
}

/**
 * Handle undoing a like swipe
 * Removes the record from applicant_job_swipes, allowing the job to reappear
 */
function handleSwipeUndo(mysqli $mysqli, int $userId, array $payload): void {
    $swipeId = (int)($payload['interaction_id'] ?? $payload['swipe_id'] ?? 0);
    $jobId = (int)($payload['job_post_id'] ?? 0);

    // Try to find by swipe_id first, then by job_post_id
    if ($swipeId > 0) {
        $stmt = $mysqli->prepare("SELECT swipe_id, job_post_id FROM applicant_job_swipes WHERE swipe_id = ? AND applicant_id = ? LIMIT 1");
        $stmt->bind_param('ii', $swipeId, $userId);
    } elseif ($jobId > 0) {
        $stmt = $mysqli->prepare("SELECT swipe_id, job_post_id FROM applicant_job_swipes WHERE job_post_id = ? AND applicant_id = ? LIMIT 1");
        $stmt->bind_param('ii', $jobId, $userId);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid undo payload - need swipe_id or job_post_id']);
        return;
    }

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to process undo.']);
        return;
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $record = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$record) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Like record not found']);
        return;
    }

    $foundSwipeId = (int)$record['swipe_id'];
    $foundJobId = (int)$record['job_post_id'];

    $mysqli->begin_transaction();

    try {
        // Delete from applicant_job_swipes
        $del = $mysqli->prepare("DELETE FROM applicant_job_swipes WHERE swipe_id = ? AND applicant_id = ?");
        if ($del) {
            $del->bind_param('ii', $foundSwipeId, $userId);
            $del->execute();
            $del->close();
        }

        // Also remove any match that was created for this combination
        // Need to find the employer_id from the job_post table
        $getEmployer = $mysqli->prepare("SELECT user_id FROM job_post WHERE job_post_id = ?");
        if ($getEmployer) {
            $getEmployer->bind_param('i', $foundJobId);
            $getEmployer->execute();
            $empResult = $getEmployer->get_result();
            if ($empRow = $empResult->fetch_assoc()) {
                $employerId = (int)$empRow['user_id'];
                $getEmployer->close();
                
                // Remove the match
                $removeMatch = $mysqli->prepare("DELETE FROM matches WHERE employer_id = ? AND applicant_id = ? AND job_post_id = ?");
                if ($removeMatch) {
                    $removeMatch->bind_param('iii', $employerId, $userId, $foundJobId);
                    $removeMatch->execute();
                    $removeMatch->close();
                }
            } else {
                $getEmployer->close();
            }
        }

        $mysqli->commit();
        echo json_encode(['success' => true, 'job_post_id' => $foundJobId]);
    } catch (Throwable $e) {
        $mysqli->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to undo like.']);
    }
}

/**
 * Insert a like record into applicant_job_swipes
 * Uses ON DUPLICATE KEY to handle re-likes gracefully
 * Uses MySQL NOW() for accurate real-time server timestamp
 */
function insertApplicantSwipe(mysqli $mysqli, int $userId, int $jobId): int {
    // First try with swipe_type column - use NOW() for real server timestamp
    $stmt = $mysqli->prepare("INSERT INTO applicant_job_swipes (applicant_id, job_post_id, swipe_type, created_at) 
                              VALUES (?, ?, 'like', NOW()) 
                              ON DUPLICATE KEY UPDATE swipe_type = 'like', created_at = NOW()");
    
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $jobId);
        $stmt->execute();
        $insertId = (int)$stmt->insert_id;
        $stmt->close();
        
        // If insert_id is 0, it was an update - get the existing ID
        if ($insertId === 0) {
            $lookup = $mysqli->prepare("SELECT swipe_id FROM applicant_job_swipes WHERE applicant_id = ? AND job_post_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param('ii', $userId, $jobId);
                $lookup->execute();
                $res = $lookup->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $lookup->close();
                return (int)($row['swipe_id'] ?? 0);
            }
        }
        return $insertId;
    }

    // Fallback: try without swipe_type if column doesn't exist - use NOW() for real server timestamp
    $alt = $mysqli->prepare("INSERT INTO applicant_job_swipes (applicant_id, job_post_id, created_at) 
                             VALUES (?, ?, NOW()) 
                             ON DUPLICATE KEY UPDATE created_at = NOW()");
    if (!$alt) {
        throw new RuntimeException('Failed to prepare swipe insert: ' . $mysqli->error);
    }
    $alt->bind_param('ii', $userId, $jobId);
    $alt->execute();
    $insertId = (int)$alt->insert_id;
    $alt->close();
    
    if ($insertId === 0) {
        $lookup = $mysqli->prepare("SELECT swipe_id FROM applicant_job_swipes WHERE applicant_id = ? AND job_post_id = ? LIMIT 1");
        if ($lookup) {
            $lookup->bind_param('ii', $userId, $jobId);
            $lookup->execute();
            $res = $lookup->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $lookup->close();
            return (int)($row['swipe_id'] ?? 0);
        }
    }
    return $insertId;
}

/**
 * Fetch jobs/matches with a prepared statement
 */
function fetchJobs(mysqli $mysqli, int $userId, string $sql): array {
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

// ============================================
// FETCH DATA FOR LIKES AND MATCHES TABS
// ============================================

// Liked Jobs - from applicant_job_swipes table
$likedJobs = fetchJobs(
    $mysqli,
    $userId,
    "SELECT 
        jp.job_post_id, 
        jp.job_post_name, 
        COALESCE(c.company_name, 'Unknown Company') AS company_name,
        COALESCE(c.location, 'Location not specified') AS location, 
        DATE_FORMAT(ajs.created_at, '%b %d, %Y %h:%i %p') AS timestamp,
        c.logo AS company_logo
     FROM applicant_job_swipes ajs
     INNER JOIN job_post jp ON jp.job_post_id = ajs.job_post_id
     LEFT JOIN company c ON c.company_id = jp.company_id
     WHERE ajs.applicant_id = ? AND ajs.swipe_type = 'like'
     ORDER BY ajs.created_at DESC"
);

// Matches - from matches table (when both applicant liked job AND employer liked applicant)
$matches = fetchJobs(
    $mysqli,
    $userId,
    "SELECT 
        jp.job_post_id, 
        jp.job_post_name, 
        COALESCE(c.company_name, 'Unknown Company') AS company_name,
        COALESCE(c.location, 'Location not specified') AS location, 
        DATE_FORMAT(m.matched_at, '%b %d, %Y %h:%i %p') AS timestamp,
        c.logo AS company_logo,
        m.employer_id
     FROM matches m
     INNER JOIN job_post jp ON jp.job_post_id = m.job_post_id
     LEFT JOIN company c ON c.company_id = jp.company_id
     WHERE m.applicant_id = ?
     ORDER BY m.matched_at DESC"
);

// Calculate counts before any processing
$totalLiked = count($likedJobs);
$totalMatches = count($matches);

// Keep mysqli connection open for renderCard checks
// Will close after HTML rendering is complete

/**
 * Render a job card for the interactions list
 */
function renderCard(array $job, string $type = 'liked', ?mysqli $mysqli = null, int $userId = 0): string {
    $jobId = (int)($job['job_post_id'] ?? 0);
    $title = htmlspecialchars($job['job_post_name'] ?? 'Job Title');
    $company = htmlspecialchars($job['company_name'] ?? 'Unknown Company');
    $location = htmlspecialchars($job['location'] ?? 'Location not specified');
    $timestamp = htmlspecialchars($job['timestamp'] ?? '');
    // Use company logo if available, otherwise fallback to default
    // Handle logo path - remove leading slash if present and prepend ../
    $logoPath = $job['company_logo'] ?? '';
    $logoPath = ltrim($logoPath, '/');
    $logoUrl = !empty($logoPath) ? htmlspecialchars('../' . $logoPath) : '../assets/company-placeholder.png';
    
    $iconClass = $type === 'match' ? 'fa-handshake' : 'fa-bookmark';
    $iconColor = $type === 'match' ? 'color:#34a853;' : 'color:#1a73e8;';
    
    // Build action buttons HTML
    $buttonsHtml = '';
    
    // Show Apply button only for matches (not for likes)
    if ($type === 'match') {
        // Check if applicant has already applied to this job
        $hasApplied = false;
        if ($mysqli && $userId > 0) {
            $checkStmt = $mysqli->prepare("SELECT application_id FROM application WHERE job_post_id = ? AND applicant_id = ? LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $jobId, $userId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $hasApplied = ($checkResult && $checkResult->num_rows > 0);
                $checkStmt->close();
            }
        }
        
        // Render button based on application status
        if ($hasApplied) {
            $buttonsHtml .= '<button class="btn-apply btn-applied" disabled><i class="fa-solid fa-check-circle"></i> Applied</button>';
        } else {
            $buttonsHtml .= '<a href="apply.php?job_post_id=' . $jobId . '" class="btn-apply"><i class="fa-solid fa-paper-plane"></i> Apply Now</a>';
        }
    }
    
    $buttonsHtml .= '<button class="btn-view" data-job-id="' . $jobId . '"><i class="fa-solid fa-eye"></i> View Job</button>';
    
    return '<div class="interaction-card" data-job-id="' . $jobId . '">
        <div class="card-left">
            <img src="' . $logoUrl . '" alt="' . $company . '" class="card-logo">
            <div class="card-info">
                <h3 class="card-title">' . $title . '</h3>
                <div class="card-company"><i class="fas fa-building"></i> ' . $company . '</div>
                <div class="card-meta">
                    <span><i class="fa-solid fa-location-dot"></i> ' . $location . '</span>
                    <span><i class="fa-solid fa-clock"></i> ' . $timestamp . '</span>
                </div>
            </div>
        </div>
        <div class="card-actions">' . $buttonsHtml . '</div>
    </div>';
}

/**
 * Render a section with cards or empty state
 */
function renderSection(string $id, array $items, string $type = 'liked', ?mysqli $mysqli = null, int $userId = 0): string {
    if (empty($items)) {
        $emptyIcon = $type === 'match' ? 'fa-handshake-slash' : 'fa-bookmark';
        $emptyText = $type === 'match' ? 'No matches yet. Keep exploring opportunities!' : 'No saved jobs yet. Start browsing!';
        return '<div id="' . $id . '" data-section class="section-content" style="display:none;">
            <div class="empty-state">
                <i class="fa-solid ' . $emptyIcon . '"></i>
                <p>' . $emptyText . '</p>
            </div>
        </div>';
    }
    $cards = '';
    foreach ($items as $item) {
        $cards .= renderCard($item, $type, $mysqli, $userId);
    }
    return '<div id="' . $id . '" data-section class="section-content" style="display:none;">' . $cards . '</div>';
}

// Counts already calculated above before rendering
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Interactions - WorkMuna</title>
<link rel="stylesheet" href="../styles.css">
<link rel="stylesheet" href="css/job-slide-panel.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
<style>
/* ==============================
   INTERACTIONS PAGE - PROFESSIONAL DESIGN
   Corporate aesthetic for serious job platform
   ============================== */

:root {
  --ip-primary: #1a73e8;
  --ip-primary-dark: #1557b0;
  --ip-primary-light: #4285f4;
  --ip-secondary: #34a853;
  --ip-secondary-dark: #2d8e47;
  --ip-accent: #5f6368;
  --ip-accent-light: #80868b;
  --ip-success: #1e8e3e;
  --ip-success-light: #e8f5e9;
  --ip-warning: #f9ab00;
  --ip-surface: #ffffff;
  --ip-bg: #f8f9fa;
  --ip-border: #dadce0;
  --ip-border-light: #e8eaed;
  --ip-text-primary: #202124;
  --ip-text-secondary: #5f6368;
  --ip-text-tertiary: #80868b;
  --ip-shadow-sm: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
  --ip-shadow-md: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  --ip-transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.interactions-page {
  background: var(--ip-bg);
  min-height: 100vh;
  font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
  color: var(--ip-text-primary);
}

.interactions-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 100px 24px 120px;
}

/* ==================
   HEADER SECTION
   ================== */

.interactions-header {
  background: var(--ip-surface);
  border-radius: 8px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: var(--ip-shadow-sm);
  border: 1px solid var(--ip-border-light);
  position: relative;
  overflow: hidden;
}

.interactions-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--ip-primary);
}

.interactions-title {
  font-size: clamp(1.75rem, 3vw, 2rem);
  font-weight: 500;
  color: var(--ip-text-primary);
  margin: 0 0 8px 0;
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  gap: 12px;
  line-height: 1.2;
}

.interactions-title i {
  color: var(--ip-primary);
  font-size: 0.875em;
}

.interactions-sub {
  font-size: 0.9375rem;
  color: var(--ip-text-secondary);
  margin: 0;
  line-height: 1.5;
}

/* ==================
   TABS NAVIGATION
   ================== */

.interactions-tabs {
  display: flex;
  gap: 12px;
  background: var(--ip-surface);
  padding: 8px;
  border-radius: 8px;
  box-shadow: var(--ip-shadow-sm);
  margin-bottom: 24px;
  border: 1px solid var(--ip-border-light);
}

.interactions-tabs .tab {
  flex: 1;
  padding: 12px 20px;
  border-radius: 4px;
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--ip-text-secondary);
  cursor: pointer;
  text-align: center;
  transition: var(--ip-transition);
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: center;
  border: 1px solid transparent;
  background: transparent;
}

.interactions-tabs .tab:hover:not(.active) {
  background: var(--ip-bg);
}

.interactions-tabs .tab.active {
  background: var(--ip-primary);
  color: white;
  box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
}

.interactions-tabs .tab[data-target="matches"].active {
  background: var(--ip-secondary);
}

.interactions-tabs .tab .tab-icon {
  font-size: 1.5rem;
}

.interactions-tabs .tab .count {
  font-size: 0.75rem;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.2);
  padding: 2px 10px;
  border-radius: 12px;
  min-width: 1.75rem;
}

.interactions-tabs .tab:not(.active) .count {
  background: var(--ip-border-light);
  color: var(--ip-text-primary);
}

/* ==================
   SECTION CONTENT
   ================== */

.section-content {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* ==================
   INTERACTION CARDS
   ================== */

.interaction-card {
  background: var(--ip-surface);
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 16px;
  box-shadow: var(--ip-shadow-sm);
  border: 1px solid var(--ip-border-light);
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  transition: var(--ip-transition);
}

.interaction-card:hover {
  box-shadow: var(--ip-shadow-md);
  transform: translateY(-2px);
}

.card-left {
  display: flex;
  gap: 16px;
  align-items: center;
  flex: 1;
  min-width: 0;
}

.card-icon {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  flex-shrink: 0;
  background: var(--ip-bg);
  border: 1px solid var(--ip-border-light);
  color: var(--ip-text-secondary);
}

.card-logo {
  width: 56px;
  height: 56px;
  border-radius: 8px;
  object-fit: cover;
  background: var(--ip-bg);
  padding: 8px;
  border: 1px solid var(--ip-border-light);
  flex-shrink: 0;
  transition: var(--ip-transition);
}

.interaction-card:hover .card-logo {
  border-color: var(--ip-primary);
}

.card-info {
  flex: 1;
  min-width: 0;
}

.card-title {
  font-size: 1rem;
  font-weight: 500;
  color: var(--ip-text-primary);
  margin: 0 0 6px 0;
  line-height: 1.4;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.card-company {
  font-size: 0.875rem;
  color: var(--ip-text-secondary);
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 400;
}

.card-company i {
  font-size: 0.75rem;
  color: var(--ip-text-tertiary);
}

.card-meta {
  font-size: 0.8125rem;
  color: var(--ip-text-tertiary);
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.card-meta > span {
  display: flex;
  align-items: center;
  gap: 6px;
}

.card-meta i {
  font-size: 0.75rem;
}

.card-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn-view {
  padding: 10px 24px;
  background: var(--ip-primary);
  color: white;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: var(--ip-transition);
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
  white-space: nowrap;
}

.btn-view:hover {
  background: var(--ip-primary-dark);
  box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
}

.btn-view:active {
  background: var(--ip-primary-dark);
  box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
}

.btn-apply {
  padding: 10px 24px;
  background: var(--ip-secondary);
  color: white;
  border-radius: 4px;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: var(--ip-transition);
  border: none;
  cursor: pointer;
  box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
  white-space: nowrap;
}

.btn-apply:hover {
  background: var(--ip-secondary-dark);
  box-shadow: 0 1px 3px 0 rgba(60, 64, 67, 0.3), 0 4px 8px 3px rgba(60, 64, 67, 0.15);
}

.btn-apply:active {
  background: var(--ip-secondary-dark);
  box-shadow: 0 1px 2px 0 rgba(60, 64, 67, 0.3), 0 1px 3px 1px rgba(60, 64, 67, 0.15);
}

/* Applied button - disabled state */
.btn-apply.btn-applied {
  background: var(--ip-success-light);
  color: var(--ip-success);
  border: 1px solid rgba(30, 142, 62, 0.2);
  cursor: default;
  box-shadow: none;
  opacity: 1;
}

.btn-apply.btn-applied:hover {
  background: var(--ip-success-light);
  color: var(--ip-success);
  box-shadow: none;
  transform: none;
}

.btn-apply.btn-applied i {
  color: var(--ip-success);
}

/* ==================
   EMPTY STATE
   ================== */

.empty-state {
  text-align: center;
  padding: 64px 32px;
  color: var(--ip-text-tertiary);
  background: var(--ip-surface);
  border-radius: 8px;
  border: 1px solid var(--ip-border-light);
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 20px;
  opacity: 0.4;
  color: var(--ip-text-tertiary);
  display: block;
}

.empty-state p {
  font-size: 0.9375rem;
  margin: 0;
  line-height: 1.5;
  font-weight: 400;
  color: var(--ip-text-secondary);
}

/* ==================
   ANIMATIONS
   ================== */

@keyframes slideInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.interaction-card {
  animation: slideInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1) backwards;
}

.interaction-card:nth-child(1) { animation-delay: 0.05s; }
.interaction-card:nth-child(2) { animation-delay: 0.1s; }
.interaction-card:nth-child(3) { animation-delay: 0.15s; }
.interaction-card:nth-child(4) { animation-delay: 0.2s; }
.interaction-card:nth-child(5) { animation-delay: 0.25s; }
.interaction-card:nth-child(6) { animation-delay: 0.3s; }
.interaction-card:nth-child(7) { animation-delay: 0.35s; }
.interaction-card:nth-child(8) { animation-delay: 0.4s; }

/* ==================
   RESPONSIVE DESIGN
   ================== */

@media (max-width: 768px) {
  .interactions-container {
    padding: 90px 16px 100px;
  }

  .interactions-header {
    padding: 20px;
  }

  .interactions-title {
    font-size: 1.375rem;
    gap: 8px;
  }

  .interactions-sub {
    font-size: 0.8125rem;
  }

  .interactions-tabs {
    gap: 6px;
    padding: 6px;
  }

  .interactions-tabs .tab {
    font-size: 0.75rem;
    padding: 10px 8px;
    gap: 4px;
  }

  .interactions-tabs .tab .tab-icon {
    font-size: 1.125rem;
  }

  .interactions-tabs .tab .count {
    font-size: 0.6875rem;
    padding: 2px 8px;
  }

  .interaction-card {
    flex-direction: column;
    align-items: flex-start;
    padding: 16px;
    gap: 12px;
  }

  .card-left {
    width: 100%;
    gap: 12px;
  }

  .card-icon {
    width: 48px;
    height: 48px;
    font-size: 1.125rem;
  }

  .card-title {
    font-size: 0.9375rem;
    white-space: normal;
    line-height: 1.4;
  }

  .card-company {
    font-size: 0.8125rem;
    margin-bottom: 4px;
  }

  .card-meta {
    font-size: 0.75rem;
    gap: 8px;
  }

  .card-actions {
    width: 100%;
    flex-direction: column;
    gap: 8px;
  }

  .btn-view,
  .btn-apply {
    width: 100%;
    justify-content: center;
    padding: 12px 20px;
    font-size: 0.875rem;
  }

  .empty-state {
    padding: 48px 20px;
  }

  .empty-state i {
    font-size: 2.5rem;
    margin-bottom: 12px;
  }

  .empty-state p {
    font-size: 0.8125rem;
  }
}

@media (max-width: 600px) {
  .interactions-container {
    padding: 80px 12px 90px;
  }

  .interactions-header {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
  }

  .interactions-title {
    font-size: 1.25rem;
    gap: 6px;
  }

  .interactions-title i {
    font-size: 1rem;
  }

  .interactions-sub {
    font-size: 0.75rem;
    line-height: 1.4;
  }

  .interactions-tabs {
    border-radius: 8px;
    margin-bottom: 16px;
  }

  .interactions-tabs .tab {
    padding: 8px 6px;
    font-size: 0.6875rem;
  }

  .interactions-tabs .tab .tab-icon {
    font-size: 1rem;
  }

  .interaction-card {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
  }

  .card-icon {
    width: 44px;
    height: 44px;
    font-size: 1rem;
  }

  .card-left {
    gap: 10px;
  }

  .card-title {
    font-size: 0.875rem;
  }

  .card-company {
    font-size: 0.75rem;
  }

  .card-meta {
    font-size: 0.6875rem;
  }

  .btn-view,
  .btn-apply {
    padding: 10px 16px;
    font-size: 0.8125rem;
  }
}

/* ==================
   UTILITY CLASSES
   ================== */

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
</style>
</head>
<body class="interactions-page">

<?php 
$activePage = 'interactions';
include 'header.php'; ?>


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
</header>


<div class="interactions-container">
    <div class="interactions-header">
        <h1 class="interactions-title">
            <i class="fas fa-briefcase"></i>
            My Interactions
        </h1>
        <p class="interactions-sub">Manage your saved jobs and mutual matches with employers</p>
    </div>

    <!-- Only 2 tabs: Saved and Matches -->
    <div class="interactions-tabs" role="tablist">
        <button class="tab active" data-target="liked" type="button">
            <i class="fa-solid fa-bookmark tab-icon"></i>
            <span>Likes</span>
            <span class="count"><?= $totalLiked ?></span>
        </button>
        <button class="tab" data-target="matches" type="button">
            <i class="fa-solid fa-handshake tab-icon"></i>
            <span>Matches</span>
            <span class="count"><?= $totalMatches ?></span>
        </button>
    </div>

    <!-- Liked Jobs Section (default visible) -->
    <?= str_replace('display:none', 'display:block', renderSection('liked', $likedJobs, 'liked', $mysqli, $userId)) ?>
    
    <!-- Matches Section -->
    <?= renderSection('matches', $matches, 'match', $mysqli, $userId) ?>
</div>

<?php
// Close database connection after rendering
$mysqli->close();
?>

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


<script src="js/job-slide-panel.js"></script>
<script>
// Tab switching logic
const buttons = document.querySelectorAll('.interactions-tabs .tab');
const sections = document.querySelectorAll('[data-section]');

const activate = (target) => {
    sections.forEach(section => {
        section.style.display = section.id === target ? 'block' : 'none';
    });
    buttons.forEach(button => {
        button.classList.toggle('active', button.getAttribute('data-target') === target);
    });
};

buttons.forEach(button => {
    button.addEventListener('click', () => activate(button.getAttribute('data-target')));
});

// Default to 'liked' tab
activate('liked');

// View Details button functionality
document.querySelectorAll('.btn-view').forEach(btn => {
  btn.addEventListener('click', function(e){
    e.preventDefault();
    e.stopPropagation();
    const jobId = this.dataset.jobId;
    if(jobId) {
      openJobSlidePanel(jobId);
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
