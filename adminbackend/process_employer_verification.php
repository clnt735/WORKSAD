<?php
/**
 * Employer Verification Processing Backend
 * Handles: approve, reject actions for employer verification requests
 */

session_start();
include '../database.php';
require_once __DIR__ . '/log_admin_action.php';

// Load PHPMailer if available
$phpMailerAvailable = false;
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    $phpMailerAvailable = true;
}

// Load session guard for role checking
require_once __DIR__ . '/../admin/session_guard.php';

// Security check: Must be logged into admin panel
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: ../admin/login.php');
    exit();
}

// Only Admins (not Super Admins) can approve/reject employer verifications
if (!isAdmin()) {
    header('Location: ../admin/employer_verification.php?flash_status=error&flash_message=' . urlencode('Only Admins can process employer verifications.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/employer_verification.php');
    exit();
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$verificationId = isset($_POST['verification_id']) ? intval($_POST['verification_id']) : 0;
$employerId = isset($_POST['employer_id']) ? intval($_POST['employer_id']) : 0;
$rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : null;

$redirectUrl = '../admin/employer_verification.php';
$adminUserId = (int) $_SESSION['user_id'];

if ($verificationId <= 0 || $employerId <= 0) {
    header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Invalid verification request.'));
    exit();
}

// Verify the verification request exists and is pending
// employer_id in employer_verification_requests references employer_profiles.employer_id
$verifyStmt = $conn->prepare("
    SELECT evr.verification_id, evr.status, evr.employer_id,
           ep.user_id, u.user_email, u.activation_token, c.company_name
    FROM employer_verification_requests evr
    INNER JOIN employer_profiles ep ON evr.employer_id = ep.employer_id
    INNER JOIN user u ON ep.user_id = u.user_id
    LEFT JOIN company c ON ep.user_id = c.user_id
    WHERE evr.verification_id = ? AND evr.employer_id = ?
");
$verifyStmt->bind_param('ii', $verificationId, $employerId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Verification request not found.'));
    exit();
}

$verification = $verifyResult->fetch_assoc();
$verifyStmt->close();

if ($verification['status'] !== 'pending') {
    header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('This verification request has already been processed.'));
    exit();
}

$employerEmail = $verification['user_email'];
$companyName = $verification['company_name'];
$actualUserId = $verification['user_id']; // The user.user_id (not employer_profiles.employer_id)
$emailVerified = ($verification['activation_token'] === null || $verification['activation_token'] === '');

switch ($action) {
    case 'approve':
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update verification request
            $updateVerificationStmt = $conn->prepare("
                UPDATE employer_verification_requests 
                SET status = 'approved', reviewed_at = NOW(), reviewed_by = ?
                WHERE verification_id = ?
            ");
            $updateVerificationStmt->bind_param('ii', $adminUserId, $verificationId);
            
            if (!$updateVerificationStmt->execute()) {
                throw new Exception('Failed to update verification status.');
            }
            $updateVerificationStmt->close();
            
            // Update employer_profiles verification status
            $updateEmpProfileStmt = $conn->prepare("
                UPDATE employer_profiles 
                SET verification_status = 'approved', is_verified = 1, updated_at = NOW()
                WHERE employer_id = ?
            ");
            $updateEmpProfileStmt->bind_param('i', $employerId);
            $updateEmpProfileStmt->execute();
            $updateEmpProfileStmt->close();
            
            // Only set user as active (user_status_id=1) if email is also verified
            if ($emailVerified) {
                // Update user status to active (1)
                $updateUserStmt = $conn->prepare("UPDATE user SET user_status_id = 1 WHERE user_id = ?");
                $updateUserStmt->bind_param('i', $actualUserId);
                
                if (!$updateUserStmt->execute()) {
                    throw new Exception('Failed to update user status.');
                }
                $updateUserStmt->close();
                
                // Update user_profile status as well
                $updateProfileStmt = $conn->prepare("UPDATE user_profile SET user_status_id = 1 WHERE user_id = ?");
                $updateProfileStmt->bind_param('i', $actualUserId);
                $updateProfileStmt->execute();
                $updateProfileStmt->close();
                
                $successMessage = "Employer verification approved. $companyName is now active.";
            } else {
                // Email not verified yet - employer will become active once they verify email
                $successMessage = "Employer verification approved. $companyName will become active once they verify their email.";
            }
            
            $conn->commit();
            
            log_admin_action($conn, $adminUserId, "Approved employer verification #$verificationId for $companyName ($employerEmail)");
            
            // Send approval email (optional - using PHPMailer if available)
            sendVerificationEmail($employerEmail, $companyName, 'approved');
            
            header("Location: $redirectUrl?flash_status=success&flash_message=" . urlencode($successMessage));
            
        } catch (Exception $e) {
            $conn->rollback();
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode($e->getMessage()));
        }
        break;

    case 'reject':
        if (empty($rejectionReason)) {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Rejection reason is required.'));
            exit();
        }
        
        // Update verification request
        $updateStmt = $conn->prepare("
            UPDATE employer_verification_requests 
            SET status = 'rejected', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
            WHERE verification_id = ?
        ");
        $updateStmt->bind_param('isi', $adminUserId, $rejectionReason, $verificationId);
        
        if ($updateStmt->execute()) {
            log_admin_action($conn, $adminUserId, "Rejected employer verification #$verificationId for $companyName ($employerEmail). Reason: $rejectionReason");
            
            // Send rejection email (optional)
            sendVerificationEmail($employerEmail, $companyName, 'rejected', $rejectionReason);
            
            header("Location: $redirectUrl?flash_status=success&flash_message=" . urlencode("Employer verification rejected for $companyName."));
        } else {
            header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Failed to update verification status.'));
        }
        $updateStmt->close();
        break;

    default:
        header("Location: $redirectUrl?flash_status=error&flash_message=" . urlencode('Invalid action.'));
        break;
}

$conn->close();

/**
 * Send verification result email to employer
 */
function sendVerificationEmail($email, $companyName, $status, $rejectionReason = null) {
    global $phpMailerAvailable;
    
    if (!$phpMailerAvailable) {
        return; // PHPMailer not available, skip email
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'workmuna310@gmail.com';
        $mail->Password   = 'ialjpczhkbfbigfq';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        
        $mail->setFrom('workmuna310@gmail.com', 'WorkMuna Support');
        $mail->addAddress($email);
        
        $mail->isHTML(true);
        
        if ($status === 'approved') {
            $mail->Subject = "Your WorkMuna Employer Account is Approved!";
            $mail->Body = "
                <h2>Congratulations!</h2>
                <p>Your employer account for <strong>$companyName</strong> has been verified and approved.</p>
                <p>You can now log in and start posting job opportunities on WorkMuna.</p>
                <p><a href='http://localhost/WORKSAD/employercontent/login.php' style='display:inline-block;padding:12px 24px;background:#16a34a;color:#fff;text-decoration:none;border-radius:6px;'>Log In Now</a></p>
                <p>Thank you for choosing WorkMuna!</p>
            ";
        } else {
            $mail->Subject = "WorkMuna Employer Verification Update";
            $mail->Body = "
                <h2>Verification Update</h2>
                <p>We regret to inform you that your employer verification for <strong>$companyName</strong> was not approved.</p>
                <p><strong>Reason:</strong> $rejectionReason</p>
                <p>You may submit a new verification request with the correct documents.</p>
                <p>If you believe this was an error, please contact our support team.</p>
                <p>Thank you for your understanding.</p>
            ";
        }
        
        $mail->send();
    } catch (\Exception $e) {
        // Silently fail - email is optional
        error_log("Verification email failed: " . $e->getMessage());
    }
}
