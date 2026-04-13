<?php
// api/send_report.php
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

// In a real system, you would generate a PDF/CSV and send via email (e.g. PHPMailer to 'admin@system.edu')
// For this prototype, we'll log it as a sync log / system notification

$stmt = $pdo->prepare("
    SELECT c.code, g.group_code, s.created_at,
    (SELECT COUNT(*) FROM attendance_records WHERE session_id = s.id) as attended
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_group_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    WHERE s.id = ? AND cg.lecturer_id = ?
");
$stmt->execute([$session_id, $_SESSION['user_id']]);
$info = $stmt->fetch();

if (!$info) {
    echo json_encode(['success' => false, 'error' => 'Invalid session.']);
    exit();
}

$reportMsg = "Session Report: " . $info['code'] . " - " . $info['group_code'] . " on " . $info['created_at'] . ". Total Present: " . $info['attended'];

// Notify Admin by logging
$adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$admin = $adminStmt->fetch();

if ($admin) {
    $ins = $pdo->prepare("INSERT INTO sync_logs (admin_id, filename, status, logs) VALUES (?, ?, 'success', ?)");
    $ins->execute([$admin['id'], 'Report_Dispatch', $reportMsg]);
}

echo json_encode(['success' => true, 'message' => 'Report sent successfully.']);
?>
