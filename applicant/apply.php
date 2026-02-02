<?php
session_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$job_post_id = isset($_GET['job_post_id']) ? (int)$_GET['job_post_id'] : 0;

// DEBUG: Log the job_post_id value
error_log("DEBUG apply.php: job_post_id = " . $job_post_id);
error_log("DEBUG apply.php: GET params = " . print_r($_GET, true));

if ($job_post_id === 0) {
    error_log("DEBUG apply.php: Redirecting to interactions.php because job_post_id is 0");
    header('Location: interactions.php');
    exit;
}

// Fetch job post details with company info and experience level
$job = null;
$sql = "SELECT 
    jp.job_post_id,
    jp.job_post_name,
    jp.job_description,
    jp.requirements,
    jp.benefits,
    jp.budget,
    jp.vacancies,
    jp.created_at,
    jp.experience_level_id,
    el.experience_level_name,
    c.company_id,
    c.company_name,
    c.logo,
    c.location AS company_location,
    c.industry
FROM job_post jp
LEFT JOIN company c ON jp.company_id = c.company_id
LEFT JOIN experience_level el ON jp.experience_level_id = el.experience_level_id
WHERE jp.job_post_id = ?
LIMIT 1";

error_log("DEBUG apply.php: About to prepare SQL query");
$stmt = $conn->prepare($sql);
if ($stmt) {
    error_log("DEBUG apply.php: SQL prepared successfully, binding job_post_id = " . $job_post_id);
    $stmt->bind_param('i', $job_post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $job = $result->fetch_assoc();
        error_log("DEBUG apply.php: Job fetched = " . ($job ? "YES (job_post_id: " . $job['job_post_id'] . ")" : "NO (query returned no results)"));
    } else {
        error_log("DEBUG apply.php: get_result() failed");
    }
    $stmt->close();
} else {
    error_log("DEBUG apply.php: Failed to prepare statement: " . $conn->error);
}

if (!$job) {
    error_log("DEBUG apply.php: Redirecting to interactions.php because job not found in database");
    header('Location: interactions.php');
    exit;
}

// Fetch default questions (applicable to all job applications)
$questions = [];
$q_sql = "SELECT default_question_id, question_text, question_type, options_json, is_required FROM default_questions ORDER BY default_question_id ASC";
$q_stmt = $conn->prepare($q_sql);
if ($q_stmt) {
    $q_stmt->execute();
    $q_res = $q_stmt->get_result();
    if ($q_res) {
        $questions = $q_res->fetch_all(MYSQLI_ASSOC);
    }
    $q_stmt->close();
}

// Fetch applicant's existing resume (uploaded file)
$resume = null;
$r_sql = "SELECT resume_id, file_path, created_at FROM resume WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$r_stmt = $conn->prepare($r_sql);
if ($r_stmt) {
    $r_stmt->bind_param('i', $user_id);
    $r_stmt->execute();
    $r_res = $r_stmt->get_result();
    if ($r_res) {
        $resume = $r_res->fetch_assoc();
    }
    $r_stmt->close();
}

// Fetch applicant's built-in resume profile data (for display purposes only)
$builtInResume = null;
$profile_sql = "SELECT up.user_profile_first_name, up.user_profile_last_name, up.user_profile_bio, r.resume_id
    FROM user_profile up
    LEFT JOIN resume r ON r.user_id = up.user_id
    WHERE up.user_id = ?
    LIMIT 1";
$p_stmt = $conn->prepare($profile_sql);
if ($p_stmt) {
    $p_stmt->bind_param('i', $user_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    if ($p_res) {
        $builtInResume = $p_res->fetch_assoc();
    }
    $p_stmt->close();
}

// Handle form submission
$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    // Read resume_type DIRECTLY from the radio button selection - NO DEFAULTS
    $resume_type_input = $_POST['resume_type'] ?? '';
    $resume_file_path = null;
    $db_resume_type = null;
    
    // STRICT VALIDATION: resume_type must be exactly 'builtin' or 'file'
    if ($resume_type_input !== 'builtin' && $resume_type_input !== 'file') {
        $error_message = 'Please select a resume option (builtin or file upload).';
    } elseif ($resume_type_input === 'file') {
        // FILE RESUME: File upload is REQUIRED
        if (!isset($_FILES['new_resume']) || $_FILES['new_resume']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Please upload a resume file when selecting "Upload resume file".';
        } else {
            // Process file upload
            $upload_dir = __DIR__ . '/../uploads/resumes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = strtolower(pathinfo($_FILES['new_resume']['name'], PATHINFO_EXTENSION));
            
            // Validate file type
            $allowed_extensions = ['pdf', 'doc', 'docx'];
            if (!in_array($file_ext, $allowed_extensions)) {
                $error_message = 'Invalid file type. Please upload PDF, DOC, or DOCX.';
            } else {
                $new_filename = 'resume_' . $user_id . '_' . time() . '.' . $file_ext;
                $new_resume_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['new_resume']['tmp_name'], $new_resume_path)) {
                    $rel_path = 'uploads/resumes/' . $new_filename;
                    $db_resume_type = 'file';
                    $resume_file_path = $rel_path;
                } else {
                    $error_message = 'Failed to upload file. Please try again.';
                }
            }
        }
    } elseif ($resume_type_input === 'builtin') {
        // BUILTIN RESUME: No file needed, ignore any uploaded file
        $db_resume_type = 'builtin';
        $resume_file_path = null;
        
        // Create a resume record if one doesn't exist (for resume builder data linkage)
        if (!$builtInResume || !$builtInResume['resume_id']) {
            $create_resume = $conn->prepare("INSERT INTO resume (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())");
            if ($create_resume) {
                $create_resume->bind_param('i', $user_id);
                $create_resume->execute();
                $create_resume->close();
            }
        }
    }
    
    // Only proceed if no errors
    if (!$error_message) {
    
    // Collect answers from default questions
    $answers = [];
    foreach ($questions as $q) {
        $qid = $q['default_question_id'];
        $q_type = $q['question_type'];
        
        if ($q_type === 'salary_range') {
            // Combine min and max salary into one answer
            $min = $_POST['answer_' . $qid . '_min'] ?? '';
            $max = $_POST['answer_' . $qid . '_max'] ?? '';
            $answers[$qid] = $min . ' - ' . $max;
        } else {
            $answers[$qid] = $_POST['answer_' . $qid] ?? '';
        }
    }
    
    // Insert application with resume_type from radio selection
    $app_sql = "INSERT INTO application (applicant_id, job_post_id, status, resume_type, resume_file_path, created_at) VALUES (?, ?, 'pending', ?, ?, NOW())";
    $app_stmt = $conn->prepare($app_sql);
    if ($app_stmt) {
        $app_stmt->bind_param('iiss', $user_id, $job_post_id, $db_resume_type, $resume_file_path);
        if ($app_stmt->execute()) {
            $application_id = $app_stmt->insert_id;
            
            // Store answers in job_post_answer table (only if they provided answers)
            foreach ($answers as $qid => $answer) {
                // Skip empty answers
                if (empty(trim($answer))) {
                    continue;
                }
                
                $ans_sql = "INSERT INTO job_post_answer (application_id, default_question_id, answer_text) VALUES (?, ?, ?)";
                $ans_stmt = $conn->prepare($ans_sql);
                if ($ans_stmt) {
                    $ans_stmt->bind_param('iis', $application_id, $qid, $answer);
                    $ans_stmt->execute();
                    $ans_stmt->close();
                }
            }
            
            // Redirect to success page
            header('Location: apply.php?job_post_id=' . $job_post_id . '&success=1');
            exit;
        }
        $app_stmt->close();
    }
    } // End of validation (no errors) block
} // End of POST handler

// Check if showing success message
$success = isset($_GET['success']) && $_GET['success'] == '1';

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?= e($job['job_post_name']) ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===================================
           MODERN JOB APPLICATION PAGE
           Professional Jobstreet-inspired UI
           =================================== */

        :root {
            --primary: #ef4444;
            --primary-hover: #dc2626;
            --secondary: #10b981;
            --gray-50: #fafafa;
            --gray-100: #f5f5f5;
            --gray-200: #e5e5e5;
            --gray-300: #d4d4d4;
            --gray-600: #525252;
            --gray-800: #262626;
            --surface: #ffffff;
            --text-primary: #171717;
            --text-secondary: #525252;
            --shadow-sm: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--gray-50);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text-primary);
        }

        /* Back Button */
        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            width: 48px;
            height: 48px;
            background: var(--surface);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            z-index: 100;
            text-decoration: none;
            color: var(--text-primary);
        }

        .back-button:hover {
            background: var(--gray-100);
            transform: translateX(-4px);
            box-shadow: var(--shadow-md);
        }

        /* Container */
        .apply-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 100px 24px 60px;
        }

        /* Job Header Card */
        .job-header-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .job-header-top {
            display: flex;
            gap: 24px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .company-logo-box {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--gray-50);
            flex-shrink: 0;
            overflow: hidden;
        }

        .company-logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .job-header-info {
            flex: 1;
        }

        .job-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--text-primary);
        }

        .company-name {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .job-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 15px;
            color: var(--text-secondary);
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .job-meta-item i {
            color: var(--primary);
            font-size: 14px;
        }

        .job-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            padding-top: 24px;
            border-top: 1px solid var(--gray-200);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .detail-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Stepper */
        .stepper {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
            background: var(--surface);
            padding: 24px;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .stepper::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 60px;
            right: 60px;
            height: 2px;
            background: var(--gray-200);
            transform: translateY(-50%);
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gray-200);
            color: var(--gray-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .step.active .step-circle {
            background: var(--primary);
            color: white;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
        }

        .step.completed .step-circle {
            background: var(--secondary);
            color: white;
        }

        .step-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-align: center;
        }

        .step.active .step-label {
            color: var(--primary);
        }

        /* Step Content Cards */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        .content-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .content-card h2 {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--text-primary);
        }

        .content-card .subtitle {
            font-size: 15px;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }

        /* Resume Options */
        .resume-options {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .resume-option {
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .resume-option:hover {
            border-color: var(--primary);
            background: var(--gray-50);
        }

        .resume-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .resume-option input[type="radio"]:checked + .option-content {
            border-color: var(--primary);
        }

        .resume-option.selected {
            border-color: var(--primary);
            background: rgba(239, 68, 68, 0.05);
        }

        .option-content {
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }

        .option-icon {
            width: 48px;
            height: 48px;
            background: var(--gray-100);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary);
            flex-shrink: 0;
        }

        .option-text {
            flex: 1;
        }

        .option-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .option-desc {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .file-upload-box {
            margin-top: 16px;
            padding: 24px;
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            text-align: center;
            background: var(--gray-50);
        }

        .file-upload-box input[type="file"] {
            display: none;
        }

        .file-upload-label {
            display: inline-block;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: var(--primary-hover);
        }

        /* Questions */
        .question-item {
            margin-bottom: 24px;
        }

        .question-label {
            display: block;
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .question-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s ease;
            resize: vertical;
            min-height: 0;
        }

        .question-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* Review Section */
        .review-section {
            margin-bottom: 24px;
        }

        .review-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .review-title i {
            color: var(--primary);
        }

        .review-item {
            background: var(--gray-50);
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .review-item-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 600;
            margin-bottom: 4px;
        }

        .review-item-value {
            font-size: 15px;
            color: var(--text-primary);
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 32px;
        }

        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        /* Success Page */
        .success-container {
            text-align: center;
            padding: 60px 20px;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: var(--secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: successPulse 0.6s ease;
        }

        .success-icon i {
            font-size: 48px;
            color: white;
        }

        @keyframes successPulse {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .success-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 16px;
            color: var(--text-primary);
        }

        .success-message {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 32px;
        }

        .success-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Responsive */
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .apply-container {
                padding: 80px 16px 40px;
            }

            .job-header-card,
            .stepper,
            .content-card {
                padding: 20px;
            }

            .job-header-top {
                flex-direction: column;
                gap: 16px;
            }

            .company-logo-box {
                width: 60px;
                height: 60px;
            }

            .job-title {
                font-size: 22px;
            }

            .company-name {
                font-size: 16px;
            }

            .stepper {
                padding: 16px;
            }

            .stepper::before {
                left: 40px;
                right: 40px;
            }

            .step-circle {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .step-label {
                font-size: 11px;
            }

            .resume-option {
                padding: 16px;
            }

            .option-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .button-group {
                flex-direction: column;
                gap: 12px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .apply-container {
                padding: 70px 12px 30px;
            }

            .back-button {
                width: 40px;
                height: 40px;
                top: 15px;
                left: 15px;
            }

            .job-header-card {
                padding: 16px;
            }

            .job-title {
                font-size: 20px;
            }

            .job-details-grid {
                grid-template-columns: 1fr;
            }

            .stepper {
                flex-direction: column;
                gap: 16px;
            }

            .stepper::before {
                display: none;
            }

            .step {
                flex-direction: row;
                width: 100%;
                gap: 12px;
            }

            .step-circle {
                flex-shrink: 0;
            }

            .step-label {
                text-align: left;
                font-size: 13px;
            }

            .content-card h2 {
                font-size: 20px;
            }

            .question-input {
                min-height: 80px;
            }
        }

        @media (max-width: 768px) {
            .apply-container {
                padding: 80px 16px 40px;
            }

            .back-button {
                top: 16px;
                left: 16px;
                width: 44px;
                height: 44px;
            }

            .job-header-card {
                padding: 24px 20px;
            }

            .job-header-top {
                flex-direction: column;
                gap: 16px;
            }

            .company-logo-box {
                width: 64px;
                height: 64px;
            }

            .job-title {
                font-size: 22px;
            }

            .company-name {
                font-size: 16px;
            }

            .stepper {
                padding: 16px;
                overflow-x: auto;
            }

            .stepper::before {
                display: none;
            }

            .step {
                min-width: 80px;
            }

            .step-circle {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .step-label {
                font-size: 11px;
            }

            .content-card {
                padding: 24px 20px;
            }

            .button-group {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .success-title {
                font-size: 24px;
            }

            .success-actions {
                flex-direction: column;
            }

            .success-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .job-details-grid {
                grid-template-columns: 1fr;
            }

            .stepper {
                flex-wrap: nowrap;
                justify-content: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- Back Button -->
    <a href="interactions.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="apply-container">

        <?php if ($success): ?>
            <!-- Success Page (Step 4) -->
            <div class="content-card success-container">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="success-title">Application Submitted!</h1>
                <p class="success-message">
                    Your application for <strong><?= e($job['job_post_name']) ?></strong> at <strong><?= e($job['company_name']) ?></strong> has been successfully submitted. The employer will review your application soon.
                </p>
                <div class="success-actions">
                    <a href="application.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View My Applications
                    </a>
                    <a href="search_jobs.php" class="btn btn-secondary">
                        <i class="fas fa-search"></i> Browse More Jobs
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- Job Header -->
            <div class="job-header-card">
                <div class="job-header-top">
                    <div class="company-logo-box">
                        <?php if (!empty($job['logo'])): ?>
                            <img src="../<?= e($job['logo']) ?>" alt="<?= e($job['company_name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-building" style="font-size: 32px; color: var(--gray-300);"></i>
                        <?php endif; ?>
                    </div>
                    <div class="job-header-info">
                        <h1 class="job-title"><?= e($job['job_post_name']) ?></h1>
                        <div class="company-name">
                            <i class="fas fa-building"></i>
                            <?= e($job['company_name']) ?>
                        </div>
                        <div class="job-meta-row">
                            <?php if (!empty($job['company_location'])): ?>
                                <span class="job-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= e($job['company_location']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($job['industry'])): ?>
                                <span class="job-meta-item">
                                    <i class="fas fa-industry"></i>
                                    <?= e($job['industry']) ?>
                                </span>
                            <?php endif; ?>
                            <span class="job-meta-item">
                                <i class="fas fa-calendar"></i>
                                Posted <?= date('F j, Y', strtotime($job['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="job-details-grid">
                    <?php if (!empty($job['budget'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Salary</span>
                            <span class="detail-value">₱<?= number_format($job['budget'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($job['vacancies'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Vacancies</span>
                            <span class="detail-value"><?= (int)$job['vacancies'] ?> position(s)</span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($job['experience_level_name'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Experience Level</span>
                            <span class="detail-value"><?= e($job['experience_level_name']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stepper -->
            <div class="stepper">
                <div class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <div class="step-label">Choose Documents</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <div class="step-label">Answer Questions</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <div class="step-label">Review & Submit</div>
                </div>
            </div>

            <!-- Application Form -->
            <form id="applicationForm" method="POST" enctype="multipart/form-data">
                
                <!-- Step 1: Choose Documents -->
                <div class="step-content active" data-content="1">
                    <div class="content-card">
                        <h2>Choose Your Resume</h2>
                        <p class="subtitle">Select how you'd like to submit your resume (Required)</p>

                        <!-- Error Message -->
                        <?php if ($error_message): ?>
                        <div id="server-error" style="background: #fee; border: 1px solid #fcc; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; color: #c33;">
                            <i class="fas fa-exclamation-circle"></i> <strong><?= e($error_message) ?></strong>
                        </div>
                        <?php endif; ?>
                        <div id="step1-error" style="display: none; background: #fee; border: 1px solid #fcc; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; color: #c33;">
                            <i class="fas fa-exclamation-circle"></i> <strong>Please select a resume option to continue</strong>
                        </div>

                        <!-- Resume Type Selection - ALWAYS visible, REQUIRED -->
                        <div class="resume-options">
                            <!-- Option 1: Use current resume (resume builder) - DEFAULT -->
                            <label class="resume-option selected" id="option-builtin">
                                <input type="radio" name="resume_type" value="builtin" checked>
                                <div class="option-content">
                                    <div class="option-icon">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="option-text">
                                        <div class="option-title">Use current resume (resume builder)</div>
                                        <div class="option-desc">
                                            Use your profile data from the resume builder
                                            <?php if ($builtInResume && !empty($builtInResume['user_profile_first_name'])): ?>
                                                <br><span style="color: #10b981;"><i class="fas fa-check-circle"></i> <?= e($builtInResume['user_profile_first_name'] . ' ' . $builtInResume['user_profile_last_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </label>

                            <!-- Option 2: Upload resume file -->
                            <label class="resume-option" id="option-file">
                                <input type="radio" name="resume_type" value="file">
                                <div class="option-content">
                                    <div class="option-icon">
                                        <i class="fas fa-upload"></i>
                                    </div>
                                    <div class="option-text">
                                        <div class="option-title">Upload resume file</div>
                                        <div class="option-desc">PDF or DOC format, max 5MB</div>
                                    </div>
                                </div>
                                <!-- File upload section - only enabled when this option is selected -->
                                <div class="file-upload-box" id="file-upload-section" style="display: none; margin-top: 16px; padding: 16px; background: #f9fafb; border-radius: 8px; border: 2px dashed #d1d5db;">
                                    <label for="new_resume" class="file-upload-label" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: var(--primary); color: white; border-radius: 8px; cursor: pointer; font-weight: 600;">
                                        <i class="fas fa-cloud-upload-alt"></i> Choose File
                                    </label>
                                    <input type="file" id="new_resume" name="new_resume" accept=".pdf,.doc,.docx" style="display: none;" disabled>
                                    <p style="margin-top: 12px; font-size: 14px; color: var(--text-secondary);" id="file-name">
                                        No file chosen <span style="color: #ef4444;">(required when this option is selected)</span>
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="button-group">
                            <button type="button" class="btn btn-primary" id="step1-next" onclick="validateAndProceed(2)">
                                Next: Answer Questions <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Answer Employer Questions -->
                <div class="step-content" data-content="2">
                    <div class="content-card">
                        <h2>Answer Employer Questions</h2>
                        <p class="subtitle">The employer has some questions for you</p>

                        <?php if (empty($questions)): ?>
                            <p style="padding: 40px 20px; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-info-circle" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.5;"></i>
                                No questions for this position. You can proceed to review.
                            </p>
                        <?php else: ?>
                            <?php foreach ($questions as $index => $q): 
                                $qid = (int)$q['default_question_id'];
                                $q_text = e($q['question_text']);
                                $q_type = $q['question_type'];
                                $is_required = (int)$q['is_required'] === 1;
                                $options = !empty($q['options_json']) ? json_decode($q['options_json'], true) : [];
                            ?>
                                <div class="question-item">
                                    <label class="question-label">
                                        Question <?= $index + 1 ?>: <?= $q_text ?> 
                                        <?php if ($is_required): ?>
                                            <span style="color: var(--primary);">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($q_type === 'text'): ?>
                                        <textarea 
                                            name="answer_<?= $qid ?>" 
                                            class="question-input"
                                            placeholder="Type your answer here..."
                                            <?= $is_required ? 'required' : '' ?>
                                        ></textarea>
                                    
                                    <?php elseif ($q_type === 'select'): ?>
                                        <select 
                                            name="answer_<?= $qid ?>" 
                                            class="question-input"
                                            <?= $is_required ? 'required' : '' ?>
                                        >
                                            <option value="">-- Select an option --</option>
                                            <?php foreach ($options as $option): ?>
                                                <option value="<?= e($option) ?>"><?= e($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    
                                    <?php elseif ($q_type === 'salary_range'): ?>
                                        <div style="display: flex; gap: 16px; align-items: center;">
                                            <div style="flex: 1;">
                                                <label style="font-size: 13px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Minimum</label>
                                                <select 
                                                    name="answer_<?= $qid ?>_min" 
                                                    class="question-input"
                                                    <?= $is_required ? 'required' : '' ?>
                                                >
                                                    <option value="">-- Min --</option>
                                                    <option value="15000">₱15,000</option>
                                                    <option value="20000">₱20,000</option>
                                                    <option value="25000">₱25,000</option>
                                                    <option value="30000">₱30,000</option>
                                                    <option value="35000">₱35,000</option>
                                                    <option value="40000">₱40,000</option>
                                                    <option value="50000">₱50,000</option>
                                                    <option value="60000">₱60,000</option>
                                                    <option value="75000">₱75,000</option>
                                                    <option value="100000">₱100,000+</option>
                                                </select>
                                            </div>
                                            <span style="color: var(--text-secondary);">to</span>
                                            <div style="flex: 1;">
                                                <label style="font-size: 13px; color: var(--text-secondary); display: block; margin-bottom: 4px;">Maximum</label>
                                                <select 
                                                    name="answer_<?= $qid ?>_max" 
                                                    class="question-input"
                                                    <?= $is_required ? 'required' : '' ?>
                                                >
                                                    <option value="">-- Max --</option>
                                                    <option value="20000">₱20,000</option>
                                                    <option value="25000">₱25,000</option>
                                                    <option value="30000">₱30,000</option>
                                                    <option value="35000">₱35,000</option>
                                                    <option value="40000">₱40,000</option>
                                                    <option value="50000">₱50,000</option>
                                                    <option value="60000">₱60,000</option>
                                                    <option value="75000">₱75,000</option>
                                                    <option value="100000">₱100,000</option>
                                                    <option value="150000">₱150,000+</option>
                                                </select>
                                            </div>
                                        </div>
                                    
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="button-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(1)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="button" class="btn btn-primary" onclick="validateAndProceed(3)">
                                Next: Review <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review and Submit -->
                <div class="step-content" data-content="3">
                    <div class="content-card">
                        <h2>Review Your Application</h2>
                        <p class="subtitle">Please review your application before submitting</p>

                        <div class="review-section">
                            <div class="review-title">
                                <i class="fas fa-briefcase"></i>
                                Job Details
                            </div>
                            <div class="review-item">
                                <div class="review-item-label">Position</div>
                                <div class="review-item-value"><?= e($job['job_post_name']) ?></div>
                            </div>
                            <div class="review-item">
                                <div class="review-item-label">Company</div>
                                <div class="review-item-value"><?= e($job['company_name']) ?></div>
                            </div>
                        </div>

                        <div class="review-section">
                            <div class="review-title">
                                <i class="fas fa-file-alt"></i>
                                Resume
                            </div>
                            <div class="review-item">
                                <div class="review-item-label">Resume Choice</div>
                                <div class="review-item-value" id="resume-review">
                                    <?= $resume ? 'Using current resume' : 'Uploading new resume' ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($questions)): ?>
                            <div class="review-section">
                                <div class="review-title">
                                    <i class="fas fa-question-circle"></i>
                                    Your Answers
                                </div>
                                <?php foreach ($questions as $index => $q): 
                                    $qid = (int)$q['default_question_id'];
                                ?>
                                    <div class="review-item">
                                        <div class="review-item-label">Q<?= $index + 1 ?>: <?= e($q['question_text']) ?></div>
                                        <div class="review-item-value" id="answer-review-<?= $qid ?>">
                                            —
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="button-group">
                            <button type="button" class="btn btn-secondary" onclick="prevStep(2)">
                                <i class="fas fa-arrow-left"></i> Back
                            </button>
                            <button type="submit" name="submit_application" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Application
                            </button>
                        </div>
                    </div>
                </div>

            </form>

        <?php endif; ?>

    </div>

    <script>
        let currentStep = 1;
        let step1Completed = false;
        let step2Completed = false;

        // Initialize resume type selection on page load
        document.addEventListener('DOMContentLoaded', function() {
            const builtinRadio = document.querySelector('input[name="resume_type"][value="builtin"]');
            const fileRadio = document.querySelector('input[name="resume_type"][value="file"]');
            const fileUploadSection = document.getElementById('file-upload-section');
            const fileInput = document.getElementById('new_resume');
            
            // Handle radio button changes
            function updateFileUploadState() {
                if (fileRadio && fileRadio.checked) {
                    // Show and enable file upload
                    if (fileUploadSection) fileUploadSection.style.display = 'block';
                    if (fileInput) fileInput.disabled = false;
                } else {
                    // Hide and disable file upload
                    if (fileUploadSection) fileUploadSection.style.display = 'none';
                    if (fileInput) {
                        fileInput.disabled = true;
                        fileInput.value = ''; // Clear any selected file
                    }
                    document.getElementById('file-name').textContent = 'No file chosen (required when this option is selected)';
                }
                
                // Update visual selection state
                document.querySelectorAll('.resume-option').forEach(opt => opt.classList.remove('selected'));
                const checkedRadio = document.querySelector('input[name="resume_type"]:checked');
                if (checkedRadio) {
                    checkedRadio.closest('.resume-option').classList.add('selected');
                }
                
                // Clear error message
                const errorDiv = document.getElementById('step1-error');
                if (errorDiv) errorDiv.style.display = 'none';
            }
            
            // Attach event listeners to radio buttons
            if (builtinRadio) {
                builtinRadio.addEventListener('change', updateFileUploadState);
            }
            if (fileRadio) {
                fileRadio.addEventListener('change', updateFileUploadState);
            }
            
            // Also handle clicking on the label/option-content
            document.querySelectorAll('.resume-option').forEach(option => {
                option.addEventListener('click', function(e) {
                    // Don't double-trigger if clicking directly on radio
                    if (e.target.type === 'radio') return;
                    
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change'));
                    }
                });
            });
            
            // File input change handler
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const fileName = this.files[0]?.name || 'No file chosen';
                    document.getElementById('file-name').textContent = fileName;
                });
            }
            
            // Initialize state on page load (builtin is default checked)
            updateFileUploadState();
        });

        function validateAndProceed(step) {
            // Validate Step 1 - Resume selection is REQUIRED
            if (currentStep === 1 && step === 2) {
                const resumeType = document.querySelector('input[name="resume_type"]:checked');
                const errorDiv = document.getElementById('step1-error');
                
                if (!resumeType) {
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>Please select a resume option</strong>';
                    errorDiv.style.display = 'block';
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                
                // Check if 'file' is selected and file is uploaded
                if (resumeType.value === 'file') {
                    const fileInput = document.getElementById('new_resume');
                    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> <strong>Please upload a resume file when selecting "Upload resume file"</strong>';
                        errorDiv.style.display = 'block';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }
                
                errorDiv.style.display = 'none';
                step1Completed = true;
            }
            
            // Mark Step 2 as completed when moving to Step 3
            if (currentStep === 2 && step === 3) {
                step2Completed = true;
            }
            
            nextStep(step);
        }

        function nextStep(step) {
            // Enforce step-by-step navigation
            if (step === 2 && !step1Completed) {
                alert('Please complete Step 1 first');
                return;
            }
            if (step === 3 && !step2Completed) {
                step2Completed = true; // Allow skipping questions
            }
            
            // Hide current step
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            
            // Mark previous steps as completed
            for (let i = 1; i < step; i++) {
                document.querySelector(`.step[data-step="${i}"]`).classList.add('completed');
            }
            
            // Show new step
            document.querySelector(`.step-content[data-content="${step}"]`).classList.add('active');
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            currentStep = step;
            
            // Update review section if going to step 3
            if (step === 3) {
                updateReview();
            }
            
            // Scroll to top
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function prevStep(step) {
            nextStep(step);
        }

        function updateReview() {
            // Update resume choice display
            const resumeType = document.querySelector('input[name="resume_type"]:checked')?.value;
            const fileName = document.getElementById('new_resume')?.files[0]?.name;
            const resumeReviewEl = document.getElementById('resume-review');
            if (resumeReviewEl) {
                if (resumeType === 'builtin') {
                    resumeReviewEl.textContent = 'Using resume builder profile data';
                } else if (resumeType === 'file') {
                    resumeReviewEl.textContent = fileName ? `Uploading: ${fileName}` : 'File upload selected';
                } else {
                    resumeReviewEl.textContent = 'No option selected';
                }
            }
            
            // Update answers for all question types
            document.querySelectorAll('.question-item').forEach(item => {
                // Check for text or select inputs
                const textInput = item.querySelector('textarea[name^="answer_"]');
                const selectInput = item.querySelector('select[name^="answer_"]');
                
                if (textInput) {
                    const qid = textInput.name.replace('answer_', '');
                    const reviewEl = document.getElementById(`answer-review-${qid}`);
                    if (reviewEl) {
                        reviewEl.textContent = textInput.value || '—';
                    }
                } else if (selectInput && !selectInput.name.includes('_min') && !selectInput.name.includes('_max')) {
                    const qid = selectInput.name.replace('answer_', '');
                    const reviewEl = document.getElementById(`answer-review-${qid}`);
                    if (reviewEl) {
                        const selectedText = selectInput.options[selectInput.selectedIndex]?.text || '—';
                        reviewEl.textContent = selectedText;
                    }
                }
                
                // Handle salary range (two dropdowns)
                const minSelect = item.querySelector('select[name$="_min"]');
                const maxSelect = item.querySelector('select[name$="_max"]');
                if (minSelect && maxSelect) {
                    const qid = minSelect.name.replace('answer_', '').replace('_min', '');
                    const reviewEl = document.getElementById(`answer-review-${qid}`);
                    if (reviewEl) {
                        const minVal = minSelect.options[minSelect.selectedIndex]?.text || '—';
                        const maxVal = maxSelect.options[maxSelect.selectedIndex]?.text || '—';
                        reviewEl.textContent = `${minVal} to ${maxVal}`;
                    }
                }
            });
        }
    </script>

</body>
</html>