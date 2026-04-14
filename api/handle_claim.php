<?php
// api/handle_claim.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$claim_id = $data['claim_id'] ?? null;
$action = $data['action'] ?? null; // 'approve' or 'reject'

if (!$claim_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action or missing claim ID.']);
    exit();
}

// Verify this claim belongs to a session from this lecturer
$check = $pdo->prepare("
    SELECT ac.id, ac.student_id, ac.session_id, ac.status 
    FROM attendance_claims ac
    JOIN attendance_sessions s ON ac.session_id = s.id
    JOIN course_groups cg ON s.course_group_id = cg.id
    WHERE ac.id = ? AND cg.lecturer_id = ?
");
$check->execute([$claim_id, $_SESSION['user_id']]);
$claim = $check->fetch();

if (!$claim) {
    echo json_encode(['success' => false, 'error' => 'Invalid claim or unauthorized.']);
    exit();
}

if ($claim['status'] !== 'pending') {
    echo json_encode(['success' => false, 'error' => 'Claim has already been processed.']);
    exit();
}

$statusToSet = $action === 'approve' ? 'approved' : 'rejected';

$pdo->beginTransaction();
try {
    $upd = $pdo->prepare("UPDATE attendance_claims SET status = ? WHERE id = ?");
    $upd->execute([$statusToSet, $claim_id]);

    if ($action === 'approve') {
        // According to user requirements: mark them 'present'
        $ins = $pdo->prepare("INSERT INTO attendance_records (session_id, student_id, status) VALUES (?, ?, 'present') ON DUPLICATE KEY UPDATE status='present'");
        $ins->execute([$claim['session_id'], $claim['student_id']]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Claim $statusToSet successfully."]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Failed to process claim.']);
}
?>
