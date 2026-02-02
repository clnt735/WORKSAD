<?php
session_start();
require_once '../database.php';

// Ensure employer is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;

if ($application_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid application ID']);
    exit;
}

// Fetch answers from job_post_answer table with question details
$answers = [];
$sql = "SELECT 
    dq.question_text,
    dq.question_type,
    jpa.answer_text
    FROM job_post_answer jpa
    INNER JOIN default_questions dq ON dq.default_question_id = jpa.default_question_id
    WHERE jpa.application_id = ?
    ORDER BY dq.default_question_id ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $application_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $answers[] = [
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'answer_text' => $row['answer_text']
        ];
    }
    
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'answers' => $answers
]);
?>
