<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();
include '../database.php';
header('Content-Type: application/json');

function error_json($msg, $debug = null) {
    // Log server side
    error_log("autosave_resume.php error: " . $msg . ($debug ? " -- " . substr($debug,0,512) : ''));
    echo json_encode(['success'=>false, 'msg'=>$msg, 'debug'=> ($debug ? substr($debug,0,512) : null)]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'msg'=>'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$raw_input = file_get_contents('php://input');
$body = json_decode($raw_input, true);

// Validate JSON
if (json_last_error() !== JSON_ERROR_NONE) {
    error_json('JSON decode error', json_last_error_msg() . ' raw:' . substr($raw_input,0,200));
}

// Action
$action = $body['action'] ?? 'save';

// Detect resume table columns once
$resumeCols = [];
$rcRes = $conn->query("SHOW COLUMNS FROM resume");
if ($rcRes) {
    while ($r = $rcRes->fetch_assoc()) {
        $resumeCols[] = $r['Field'];
    }
}
$has_professional_summary = in_array('professional_summary', $resumeCols);
$has_preferences = in_array('preferences', $resumeCols);

// Helper: check if a table has a column
function table_has_column($conn, $table, $column) {
    $safeTable = $conn->real_escape_string($table);
    $safeCol = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$safeTable` LIKE '$safeCol'");
    return ($res && $res->num_rows > 0);
}

// Detect presence (resume_id vs user_id) on target tables
$exp_anchor_is_resume = table_has_column($conn, 'applicant_experience', 'resume_id');
$exp_anchor_is_user   = table_has_column($conn, 'applicant_experience', 'user_id');

$edu_anchor_is_resume = table_has_column($conn, 'applicant_education', 'resume_id');
$edu_anchor_is_user   = table_has_column($conn, 'applicant_education', 'user_id');

$loc_anchor_is_resume = table_has_column($conn, 'applicant_location', 'resume_id');
$loc_anchor_is_user   = table_has_column($conn, 'applicant_location', 'user_id');

$skills_anchor_is_resume = table_has_column($conn, 'applicant_skills', 'resume_id');
$skills_anchor_is_user   = table_has_column($conn, 'applicant_skills', 'user_id');

$ach_anchor_is_resume = table_has_column($conn, 'applicant_achievements', 'resume_id');
$ach_anchor_is_user   = table_has_column($conn, 'applicant_achievements', 'user_id');

// ==================== LOAD OPERATION ====================
if ($action === 'load') {
    // Build SELECT dynamically so we don't SELECT non-existent columns
    $selectFields = ['resume_id', 'updated_at'];
    if ($has_professional_summary) $selectFields[] = 'professional_summary';
    if ($has_preferences) $selectFields[] = 'preferences';
    $sqlSelect = "SELECT " . implode(', ', $selectFields) . " FROM resume WHERE user_id = ?";

    $stmt = $conn->prepare($sqlSelect);
    if (!$stmt) error_json("Prepare failed (resume select): " . $conn->error, $sqlSelect);
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) error_json("Execute failed (resume select): " . $stmt->error);
    $resume = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $response = [
        'success' => true,
        'work_experience' => [],
        'education' => [],
        'skills' => [],
        'achievements' => [],
        // Only return these keys if the columns exist (otherwise return empty defaults)
        'professional_summary' => $has_professional_summary ? ($resume['professional_summary'] ?? '') : '',
        'preferences' => $has_preferences ? (json_decode($resume['preferences'] ?? 'null', true) ?? []) : []
    ];

    if ($resume) {
        $resume_id = (int)$resume['resume_id'];

        // Work experience - bind either resume_id or user_id
        $anchorParam = $exp_anchor_is_resume ? $resume_id : $user_id;
        $anchorColumn = $exp_anchor_is_resume ? 'ae.resume_id' : ( $exp_anchor_is_user ? 'ae.user_id' : null );
        if ($anchorColumn) {
            $stmt = $conn->prepare("
                SELECT ae.*, el.experience_level_name
                FROM applicant_experience ae
                LEFT JOIN experience_level el ON ae.experience_level_id = el.experience_level_id
                WHERE {$anchorColumn} = ?
                ORDER BY ae.start_date DESC
            ");
            if (!$stmt) error_json("Prepare failed (work_select): " . $conn->error);
            $stmt->bind_param('i', $anchorParam);
            if (!$stmt->execute()) error_json("Execute failed (work_select): " . $stmt->error);
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $response['work_experience'][] = [
                    'id' => $row['experience_id'] ?? $row['id'] ?? null,
                    'job_title' => $row['experience_name'] ?? '',
                    'company' => $row['experience_company'] ?? '',
                    'company_name' => $row['experience_company'] ?? '',
                    'experience_level_id' => $row['experience_level_id'] ?? '',
                    'experience_level_name' => $row['experience_level_name'] ?? '',
                    'start_date' => $row['start_date'] ?? '',
                    'end_date' => $row['end_date'] ?? '',
                    'description' => $row['experience_description'] ?? ''
                ];
            }
            $stmt->close();
        }

        // Education
        $anchorParam = $edu_anchor_is_resume ? $resume_id : $user_id;
        $anchorColumn = $edu_anchor_is_resume ? 'ae.resume_id' : ( $edu_anchor_is_user ? 'ae.user_id' : null );
        if ($anchorColumn) {
            $stmt = $conn->prepare("
                SELECT ae.*, el.education_level_name
                FROM applicant_education ae
                LEFT JOIN education_level el ON ae.education_level_id = el.education_level_id
                WHERE {$anchorColumn} = ?
                ORDER BY ae.start_date DESC
            ");
            if (!$stmt) error_json("Prepare failed (edu_select): " . $conn->error);
            $stmt->bind_param('i', $anchorParam);
            if (!$stmt->execute()) error_json("Execute failed (edu_select): " . $stmt->error);
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $response['education'][] = [
                    'id' => $row['applicant_education_id'] ?? null,
                    'education_level_id' => $row['education_level_id'] ?? '',
                    'education_level_name' => $row['education_level_name'] ?? '',
                    'school_name' => $row['school_name'] ?? '',
                    'institution' => $row['school_name'] ?? '',
                    'start_date' => $row['start_date'] ?? '',
                    'end_date' => $row['end_date'] ?? ''
                ];
            }
            $stmt->close();
        }

        // Skills
        $anchorParam = $skills_anchor_is_resume ? $resume_id : $user_id;
        $anchorColumn = $skills_anchor_is_resume ? 'aps.resume_id' : ( $skills_anchor_is_user ? 'aps.user_id' : null );
        if ($anchorColumn) {
            $stmt = $conn->prepare("
                SELECT aps.*, s.name AS skill_name, jc.job_category_name, i.industry_id, i.industry_name
                FROM applicant_skills aps
                LEFT JOIN skills s ON aps.skill_id = s.skill_id
                LEFT JOIN job_category jc ON aps.job_category_id = jc.job_category_id
                LEFT JOIN industry i ON jc.industry_id = i.industry_id
                WHERE {$anchorColumn} = ?
            ");
            if (!$stmt) error_json("Prepare failed (skills_select): " . $conn->error);
            $stmt->bind_param('i', $anchorParam);
            if (!$stmt->execute()) error_json("Execute failed (skills_select): " . $stmt->error);
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $response['skills'][] = [
                    'id' => $row['applicant_skills_id'] ?? null,
                    'industry_id' => $row['industry_id'] ?? '',
                    'industry_name' => $row['industry_name'] ?? '',
                    'job_category_id' => $row['job_category_id'] ?? '',
                    'category_id' => $row['job_category_id'] ?? '',
                    'category_name' => $row['job_category_name'] ?? '',
                    'skill_id' => $row['skill_id'] ?? '',
                    'skill_name' => $row['skill_name'] ?? ''
                ];
            }
            $stmt->close();
        }

        // Achievements
        $anchorParam = $ach_anchor_is_resume ? $resume_id : $user_id;
        $anchorColumn = $ach_anchor_is_resume ? 'resume_id' : ( $ach_anchor_is_user ? 'user_id' : null );
        if ($anchorColumn) {
            $stmt = $conn->prepare("SELECT * FROM applicant_achievements WHERE {$anchorColumn} = ?");
            if (!$stmt) error_json("Prepare failed (ach_select): " . $conn->error);
            $stmt->bind_param('i', $anchorParam);
            if (!$stmt->execute()) error_json("Execute failed (ach_select): " . $stmt->error);
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $response['achievements'][] = [
                    'id' => $row['achievement_id'] ?? null,
                    'title' => $row['achievement_name'] ?? '',
                    'achievement_name' => $row['achievement_name'] ?? '',
                    'organization' => $row['achievement_organization'] ?? '',
                    'achievement_organization' => $row['achievement_organization'] ?? '',
                    'date_received' => $row['date_received'] ?? '',
                    'description' => $row['description'] ?? ''
                ];
            }
            $stmt->close();
        }
        
        // Load preferences from resume_preference table (preferred) or resume.preferences column (fallback)
        $stmt = $conn->prepare("SELECT job_type_id, industry_id FROM resume_preference WHERE resume_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $resume_id);
            if ($stmt->execute()) {
                $pref = $stmt->get_result()->fetch_assoc();
                if ($pref) {
                    $response['preferences'] = [
                        'job_type_id' => $pref['job_type_id'] ?? null,
                        'industry_id' => $pref['industry_id'] ?? null
                    ];
                }
            }
            $stmt->close();
        }
        // Ensure preferences always has the correct structure even if not found in DB
        if (!isset($response['preferences']['job_type_id'])) {
            $response['preferences'] = [
                'job_type_id' => null,
                'industry_id' => null
            ];
        }
        // If no data in resume_preference, check resume.preferences column (already loaded in $response)
    }

    echo json_encode($response);
    exit;
}

// ==================== SAVE OPERATION ====================
if (!$body) {
    error_json('Bad payload');
}

// Extract data (body may already include arrays)
$work = is_string($body['work_experience'] ?? '') ? json_decode($body['work_experience'], true) : ($body['work_experience'] ?? []);
$education = is_string($body['education'] ?? '') ? json_decode($body['education'], true) : ($body['education'] ?? []);
$skills = is_string($body['skills'] ?? '') ? json_decode($body['skills'], true) : ($body['skills'] ?? []);
$achievements = is_string($body['achievements'] ?? '') ? json_decode($body['achievements'], true) : ($body['achievements'] ?? []);

// Get or create resume record
$stmt = $conn->prepare("SELECT resume_id FROM resume WHERE user_id = ?");
if (!$stmt) error_json('Prepare select failed: ' . $conn->error);
$stmt->bind_param('i', $user_id);
if (!$stmt->execute()) error_json('Execute select resume failed: ' . $stmt->error);
$resume = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$resume) {
    $stmt = $conn->prepare("INSERT INTO resume (user_id, updated_at) VALUES (?, NOW())");
    if (!$stmt) error_json('Prepare insert failed: ' . $conn->error);
    $stmt->bind_param('i', $user_id);
    if (!$stmt->execute()) error_json('Insert failed: ' . $stmt->error);
    $resume_id = $conn->insert_id;
    $stmt->close();
} else {
    $resume_id = (int)$resume['resume_id'];
}

$saved_ids = ['resume_id' => $resume_id, 'skill_ids' => []];
$saved_exp_ids = [];
$saved_edu_ids = [];
$saved_ach_ids = [];

// SAVE Work Experience (multiple)
if (!empty($work) && is_array($work)) {
    foreach ($work as $w) {
        $exp_id = $w['id'] ?? null;
        $job_title = $w['job_title'] ?? $w['experience_name'] ?? '';
        $company = $w['company'] ?? $w['company_name'] ?? $w['experience_company'] ?? '';
        $experience_level_id = isset($w['experience_level_id']) ? (int)$w['experience_level_id'] : null;
        $start_date = $w['start_date'] ?? null;
        $end_date = $w['end_date'] ?? null;
        $description = $w['description'] ?? $w['experience_description'] ?? '';

        // Normalize dates
        if (!empty($start_date) && preg_match('/^\d{4}-\d{2}$/', $start_date)) $start_date .= '-01';
        if (!empty($end_date) && preg_match('/^\d{4}-\d{2}$/', $end_date)) $end_date .= '-01';
        if ($end_date === 'Present') $end_date = null;

        if ($job_title) {
            if ($exp_id) {
                // Update existing - use proper WHERE clause based on available columns
                if ($exp_anchor_is_resume) {
                    $stmt = $conn->prepare("UPDATE applicant_experience SET experience_name=?, experience_company=?, experience_level_id=?, start_date=?, end_date=?, experience_description=? WHERE experience_id=? AND resume_id=?");
                    if (!$stmt) error_json('Prepare update exp failed: ' . $conn->error);
                    $stmt->bind_param('ssisssii', $job_title, $company, $experience_level_id, $start_date, $end_date, $description, $exp_id, $resume_id);
                } else {
                    $stmt = $conn->prepare("UPDATE applicant_experience SET experience_name=?, experience_company=?, experience_level_id=?, start_date=?, end_date=?, experience_description=? WHERE experience_id=? AND user_id=?");
                    if (!$stmt) error_json('Prepare update exp failed: ' . $conn->error);
                    $stmt->bind_param('ssisssii', $job_title, $company, $experience_level_id, $start_date, $end_date, $description, $exp_id, $user_id);
                }
                $stmt->execute();
                $stmt->close();
                $saved_exp_ids[] = (int)$exp_id;
            } else {
                // Insert new - use proper anchor column
                if ($exp_anchor_is_resume) {
                    $stmt = $conn->prepare("INSERT INTO applicant_experience (resume_id, experience_name, experience_company, experience_level_id, start_date, end_date, experience_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt) error_json('Prepare insert exp failed: ' . $conn->error);
                    $stmt->bind_param('ississs', $resume_id, $job_title, $company, $experience_level_id, $start_date, $end_date, $description);
                } else {
                    $stmt = $conn->prepare("INSERT INTO applicant_experience (user_id, experience_name, experience_company, experience_level_id, start_date, end_date, experience_description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if (!$stmt) error_json('Prepare insert exp failed: ' . $conn->error);
                    $stmt->bind_param('ississs', $user_id, $job_title, $company, $experience_level_id, $start_date, $end_date, $description);
                }
                $stmt->execute();
                $stmt->close();
                $saved_exp_ids[] = $conn->insert_id ?? null;
            }
        }
    }
}

// SAVE Education (multiple)
if (!empty($education) && is_array($education)) {
    foreach ($education as $e) {
        $edu_id = $e['id'] ?? null;
        $education_level_id = $e['education_level_id'] ?? null;
        $school_name = $e['school_name'] ?? $e['institution'] ?? '';
        $start_date = $e['start_date'] ?? $e['start_year'] ?? null;
        $end_date = $e['end_date'] ?? $e['end_year'] ?? null;

        if (!empty($start_date) && preg_match('/^\d{4}-\d{2}$/', $start_date)) $start_date .= '-01';
        if (!empty($end_date) && preg_match('/^\d{4}-\d{2}$/', $end_date)) $end_date .= '-01';

        if ($school_name && $education_level_id) {
            if ($edu_id) {
                // Update existing
                if ($edu_anchor_is_resume) {
                    $stmt = $conn->prepare("UPDATE applicant_education SET education_level_id=?, school_name=?, start_date=?, end_date=? WHERE applicant_education_id=? AND resume_id=?");
                    if (!$stmt) error_json('Prepare update edu failed: ' . $conn->error);
                    $stmt->bind_param('isssii', $education_level_id, $school_name, $start_date, $end_date, $edu_id, $resume_id);
                } else {
                    $stmt = $conn->prepare("UPDATE applicant_education SET education_level_id=?, school_name=?, start_date=?, end_date=? WHERE applicant_education_id=? AND user_id=?");
                    if (!$stmt) error_json('Prepare update edu failed: ' . $conn->error);
                    $stmt->bind_param('isssii', $education_level_id, $school_name, $start_date, $end_date, $edu_id, $user_id);
                }
                $stmt->execute();
                $stmt->close();
                $saved_edu_ids[] = (int)$edu_id;
            } else {
                // Insert new
                if ($edu_anchor_is_resume) {
                    $stmt = $conn->prepare("INSERT INTO applicant_education (resume_id, education_level_id, school_name, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt) error_json('Prepare insert edu failed: ' . $conn->error);
                    $stmt->bind_param('iisss', $resume_id, $education_level_id, $school_name, $start_date, $end_date);
                } else {
                    $stmt = $conn->prepare("INSERT INTO applicant_education (user_id, education_level_id, school_name, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
                    if (!$stmt) error_json('Prepare insert edu failed: ' . $conn->error);
                    $stmt->bind_param('iisss', $user_id, $education_level_id, $school_name, $start_date, $end_date);
                }
                $stmt->execute();
                $stmt->close();
                $saved_edu_ids[] = $conn->insert_id ?? null;
            }
        }
    }
}

// SAVE Skills (multiple)
$saved_skill_ids = [];
if (!empty($skills) && is_array($skills)) {
    foreach ($skills as $s) {
        $skill_record_id = $s['id'] ?? null;
        $job_category_id = $s['job_category_id'] ?? $s['category_id'] ?? null;
        $skill_id_fk = $s['skill_id'] ?? $s['skills_id'] ?? null;

        if ($job_category_id && $skill_id_fk) {
            if ($skill_record_id) {
                $stmt = $conn->prepare("UPDATE applicant_skills SET job_category_id=?, skill_id=? WHERE applicant_skills_id=? AND resume_id=?");
                if (!$stmt) error_json('Prepare update skill failed: ' . $conn->error);
                $stmt->bind_param('iiii', $job_category_id, $skill_id_fk, $skill_record_id, $resume_id);
                $stmt->execute();
                $saved_skill_ids[] = $skill_record_id;
                $stmt->close();
            } else {
                // Check existing
                $stmt = $conn->prepare("SELECT applicant_skills_id FROM applicant_skills WHERE resume_id=? AND skill_id=?");
                if (!$stmt) error_json('Prepare select existing skill failed: ' . $conn->error);
                $stmt->bind_param('ii', $resume_id, $skill_id_fk);
                $stmt->execute();
                $existing_skill = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing_skill) {
                    $existing_id = (int)$existing_skill['applicant_skills_id'];
                    $stmt = $conn->prepare("UPDATE applicant_skills SET job_category_id=? WHERE applicant_skills_id=?");
                    if (!$stmt) error_json('Prepare update existing skill failed: ' . $conn->error);
                    $stmt->bind_param('ii', $job_category_id, $existing_id);
                    $stmt->execute();
                    $saved_skill_ids[] = $existing_id;
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO applicant_skills (resume_id, job_category_id, skill_id) VALUES (?, ?, ?)");
                    if (!$stmt) error_json('Prepare insert skill failed: ' . $conn->error);
                    $stmt->bind_param('iii', $resume_id, $job_category_id, $skill_id_fk);
                    $stmt->execute();
                    $saved_skill_ids[] = $conn->insert_id;
                    $stmt->close();
                }
            }
        }
    }
}

// Delete removed skills (safe sanitized ints)
if (!empty($saved_skill_ids)) {
    $safeIds = array_map('intval', $saved_skill_ids);
    $inList = implode(',', $safeIds);
    $sql = "DELETE FROM applicant_skills WHERE resume_id = ? AND applicant_skills_id NOT IN ($inList)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) error_json('Prepare delete skills failed: ' . $conn->error);
    $stmt->bind_param('i', $resume_id);
    $stmt->execute();
    $stmt->close();
} else {
    // if client submits empty skills and we want to clear them, uncomment:
    // $stmt = $conn->prepare("DELETE FROM applicant_skills WHERE resume_id = ?");
    // if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
}

$saved_ids['skill_ids'] = $saved_skill_ids;

// SAVE Achievements (multiple) - use user_id as achievements are profile-level
if (!empty($achievements) && is_array($achievements)) {
    foreach ($achievements as $a) {
        $ach_id = $a['id'] ?? null;
        $title = $a['title'] ?? $a['achievement_name'] ?? '';
        $organization = $a['organization'] ?? $a['achievement_organization'] ?? '';
        $date_received = $a['date_received'] ?? null;
        $description = $a['description'] ?? '';

        if ($ach_id) {
            // Update existing - prefer user_id over resume_id
            if ($ach_anchor_is_user) {
                $stmt = $conn->prepare("UPDATE applicant_achievements SET achievement_name=?, achievement_organization=?, date_received=?, description=? WHERE achievement_id=? AND user_id=?");
                if (!$stmt) error_json('Prepare update ach failed: ' . $conn->error);
                $stmt->bind_param('ssssii', $title, $organization, $date_received, $description, $ach_id, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE applicant_achievements SET achievement_name=?, achievement_organization=?, date_received=?, description=? WHERE achievement_id=? AND resume_id=?");
                if (!$stmt) error_json('Prepare update ach failed: ' . $conn->error);
                $stmt->bind_param('ssssii', $title, $organization, $date_received, $description, $ach_id, $resume_id);
            }
            $stmt->execute();
            $stmt->close();
            $saved_ach_ids[] = (int)$ach_id;
        } else {
            // Insert new - prefer user_id over resume_id
            if ($ach_anchor_is_user) {
                $stmt = $conn->prepare("INSERT INTO applicant_achievements (user_id, achievement_name, achievement_organization, date_received, description) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) error_json('Prepare insert ach failed: ' . $conn->error);
                $stmt->bind_param('issss', $user_id, $title, $organization, $date_received, $description);
            } else {
                $stmt = $conn->prepare("INSERT INTO applicant_achievements (resume_id, achievement_name, achievement_organization, date_received, description) VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) error_json('Prepare insert ach failed: ' . $conn->error);
                $stmt->bind_param('issss', $resume_id, $title, $organization, $date_received, $description);
            }
            $stmt->execute();
            $stmt->close();
            $saved_ach_ids[] = $conn->insert_id ?? null;
        }
    }
}

// Delete experience rows NOT IN saved_exp_ids (using proper anchor)
if ($exp_anchor_is_resume && isset($resume_id)) {
    if (!empty($saved_exp_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_exp_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_experience WHERE resume_id = ? AND experience_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_experience WHERE resume_id = ?");
        if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
    }
} elseif ($exp_anchor_is_user) {
    if (!empty($saved_exp_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_exp_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_experience WHERE user_id = ? AND experience_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_experience WHERE user_id = ?");
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
    }
}

// education deletion (using proper anchor)
if ($edu_anchor_is_resume && isset($resume_id)) {
    if (!empty($saved_edu_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_edu_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_education WHERE resume_id = ? AND applicant_education_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_education WHERE resume_id = ?");
        if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
    }
} elseif ($edu_anchor_is_user) {
    if (!empty($saved_edu_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_edu_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_education WHERE user_id = ? AND applicant_education_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_education WHERE user_id = ?");
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
    }
}

// achievements deletion (prefer user_id)
if ($ach_anchor_is_user) {
    if (!empty($saved_ach_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_ach_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_achievements WHERE user_id = ? AND achievement_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_achievements WHERE user_id = ?");
        if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $stmt->close(); }
    }
} elseif ($ach_anchor_is_resume && isset($resume_id)) {
    if (!empty($saved_ach_ids)) {
        $safeIds = array_filter(array_map('intval', $saved_ach_ids));
        if (count($safeIds)) {
            $inList = implode(',', $safeIds);
            $sql = "DELETE FROM applicant_achievements WHERE resume_id = ? AND achievement_id NOT IN ($inList)";
            $stmt = $conn->prepare($sql);
            if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
        }
    } else {
        $stmt = $conn->prepare("DELETE FROM applicant_achievements WHERE resume_id = ?");
        if ($stmt) { $stmt->bind_param('i', $resume_id); $stmt->execute(); $stmt->close(); }
    }
}

// Read optional fields from request body, but only use them in UPDATE if columns exist
$prof = $body['professional_summary'] ?? null;
$prefs = $body['preferences'] ?? null;

// Save preferences to resume_preference table if provided
if ($prefs !== null && isset($resume_id)) {
    $prefArray = is_string($prefs) ? json_decode($prefs, true) : $prefs;
    if (is_array($prefArray)) {
        $job_type_id = isset($prefArray['job_type_id']) && $prefArray['job_type_id'] !== '' ? (int)$prefArray['job_type_id'] : null;
        $industry_id = isset($prefArray['industry_id']) && $prefArray['industry_id'] !== '' ? (int)$prefArray['industry_id'] : null;
        
        // Check if a preference record exists for this resume
        $stmt = $conn->prepare("SELECT resume_preference_id FROM resume_preference WHERE resume_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $resume_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                // Update existing preference
                $stmt = $conn->prepare("UPDATE resume_preference SET job_type_id = ?, industry_id = ?, updated_at = NOW() WHERE resume_id = ?");
                if ($stmt) {
                    $stmt->bind_param('iii', $job_type_id, $industry_id, $resume_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // Insert new preference
                $stmt = $conn->prepare("INSERT INTO resume_preference (resume_id, job_type_id, industry_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                if ($stmt) {
                    $stmt->bind_param('iii', $resume_id, $job_type_id, $industry_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

// Build update query dynamically to only update columns that exist and that the payload included
$updateCols = ['updated_at = NOW()'];
$params = [];
$types = '';

if ($has_professional_summary && $prof !== null) {
    $updateCols[] = 'professional_summary = ?';
    $params[] = $prof;
    $types .= 's';
}
if ($has_preferences && $prefs !== null) {
    $updateCols[] = 'preferences = ?';
    $params[] = is_string($prefs) ? $prefs : json_encode($prefs);
    $types .= 's';
}

$sql = "UPDATE resume SET " . implode(', ', $updateCols) . " WHERE resume_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) error_json('Prepare resume update failed: ' . $conn->error, $sql);

// Bind params dynamically (safely)
if ($types !== '') {
    // build bind array
    $paramsAll = array_merge($params, [$resume_id]);
    $typesAll = $types . 'i';
    $bind_names = [];
    $bind_names[] = $typesAll;
    foreach ($paramsAll as $i => $val) {
        $bindVar = 'b' . $i;
        $$bindVar = $val;
        $bind_names[] = &$$bindVar;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
} else {
    $stmt->bind_param('i', $resume_id);
}

if (!$stmt->execute()) {
    error_json('Resume update failed: ' . $stmt->error);
}
$stmt->close();

echo json_encode([
    'success' => true,
    'saved_at' => date('c'),
    'ids' => $saved_ids,
    'message' => 'Resume saved successfully'
]);

$conn->close();
exit;
