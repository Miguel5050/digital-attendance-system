<?php
// api/clear_sessions.php
// Can be run via cron or manually by admin/lecturer to close expired sessions
require_once '../config/db.php';

header('Content-Type: application/json');

// Only allow admin or lecturer, though for cron jobs we might use a secret token
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'lecturer')) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$stmt = $pdo->prepare("UPDATE attendance_sessions SET status = 'closed' WHERE expires_at < NOW() AND status = 'active'");
if ($stmt->execute()) {
    $count = $stmt->rowCount();
    echo json_encode(['success' => true, 'message' => "Automatically closed $count expired sessions."]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to perform cleanup.']);
}
?>
