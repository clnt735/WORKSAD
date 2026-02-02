<?php
session_start();
header('Content-Type: application/json');

require_once '../database.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$transactionStarted = false;

function wm_json_response(bool $success, string $message, array $extra = []): void
{
	$response = array_merge([
		'success' => $success,
		'message' => $message,
	], $extra);
	echo json_encode($response);
	exit;
}

try {
	if (!isset($_SESSION['user_id'])) {
		wm_json_response(false, 'You must be logged in to delete a job.');
	}

	$jobId = filter_input(INPUT_POST, 'job_post_id', FILTER_VALIDATE_INT);
	if (!$jobId) {
		wm_json_response(false, 'Invalid job selected.');
	}

	$userId = (int)$_SESSION['user_id'];

	$ownershipSql = 'SELECT job_post_id FROM job_post WHERE job_post_id = ? AND user_id = ? LIMIT 1';
	$ownershipStmt = $conn->prepare($ownershipSql);
	$ownershipStmt->bind_param('ii', $jobId, $userId);
	$ownershipStmt->execute();
	$ownershipStmt->store_result();

	if ($ownershipStmt->num_rows === 0) {
		$ownershipStmt->close();
		$conn->close();
		wm_json_response(false, 'Job not found or you do not have permission to delete it.');
	}
	$ownershipStmt->close();

	$conn->begin_transaction();
	$transactionStarted = true;

	// Delete all related records for this job post
	$dependentDeletes = [
		'DELETE FROM employer_applicant_swipes WHERE job_post_id = ?',
		'DELETE FROM applicant_job_swipes WHERE job_post_id = ?',
		'DELETE FROM matches WHERE job_post_id = ?',
		'DELETE FROM interactions WHERE job_post_id = ?',
		'DELETE FROM interaction_history WHERE job_post_id = ?',
		'DELETE FROM job_post_skills WHERE job_post_id = ?',
		'DELETE FROM job_post_location WHERE job_post_id = ?',
		'DELETE FROM job_likes WHERE job_post_id = ?',
		'DELETE FROM like_history WHERE job_id = ?',
	];

	foreach ($dependentDeletes as $sql) {
		$stmt = $conn->prepare($sql);
		$stmt->bind_param('i', $jobId);
		$stmt->execute();
		$stmt->close();
	}

	// Finally delete the job post itself
	$deleteStmt = $conn->prepare('DELETE FROM job_post WHERE job_post_id = ? LIMIT 1');
	$deleteStmt->bind_param('i', $jobId);
	$deleteStmt->execute();
	$deleteStmt->close();
	$conn->commit();
	$conn->close();

	wm_json_response(true, 'Job deleted successfully.', ['job_post_id' => $jobId]);
} catch (Throwable $error) {
	if (isset($conn) && $conn instanceof mysqli) {
		if ($transactionStarted) {
			$conn->rollback();
		}
		$conn->close();
	}
	http_response_code(500);
	wm_json_response(false, 'Unable to delete the job right now. ' . $error->getMessage());
}
