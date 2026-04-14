<?php
// api/submit_claim.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$session_id = $data['session_id'] ?? null;
$reason = trim($data['reason'] ?? '');
$student_id = $_SESSION['user_id'];

if (!$session_id || empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Session ID and context reason are required.']);
    exit();
}

// Ensure no existing approved claim or attendance record exists
$checkAtt = $pdo->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
$checkAtt->execute([$session_id, $student_id]);
if ($checkAtt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You are already marked as present.']);
    exit();
}

// Ensure no pending claim already exists
$checkClaim = $pdo->prepare("SELECT id FROM attendance_claims WHERE session_id = ? AND student_id = ? AND status = 'pending'");
$checkClaim->execute([$session_id, $student_id]);
if ($checkClaim->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You already have a pending claim for this session.']);
    exit();
}

$ins = $pdo->prepare("INSERT INTO attendance_claims (student_id, session_id, reason) VALUES (?, ?, ?)");
if ($ins->execute([$student_id, $session_id, $reason])) {
    echo json_encode(['success' => true, 'message' => 'Claim successfully submitted for lecturer review.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to submit claim.']);
}
?>
