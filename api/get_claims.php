<?php
// api/get_claims.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Fetch pending claims for this lecturer's courses
$stmt = $pdo->prepare("
    SELECT ac.id as claim_id, ac.reason, ac.created_at, u.name as student_name, u.student_id, c.code as course_code, g.group_code
    FROM attendance_claims ac
    JOIN users u ON ac.student_id = u.id
    JOIN attendance_sessions s ON ac.session_id = s.id
    JOIN course_groups cg ON s.course_group_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    WHERE cg.lecturer_id = ? AND ac.status = 'pending'
    ORDER BY ac.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$claims = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $claims]);
?>
