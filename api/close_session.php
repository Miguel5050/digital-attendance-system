<?php
// api/close_session.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$session_id = $data['session_id'] ?? null;

if (!$session_id) {
    echo json_encode(['success' => false, 'error' => 'Missing session ID']);
    exit();
}

// Verify this session belongs to a course the lecturer is teaching
$stmt = $pdo->prepare("
    SELECT s.id FROM attendance_sessions s
    JOIN course_groups cg ON s.course_group_id = cg.id
    WHERE s.id = ? AND cg.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);

if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session or access denied.']);
    exit();
}

$update = $pdo->prepare("UPDATE attendance_sessions SET status = 'closed', expires_at = NOW() WHERE id = ?");
if ($update->execute([$session_id])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to close session.']);
}
?>
