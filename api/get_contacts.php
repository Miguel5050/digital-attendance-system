<?php
// api/get_contacts.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$contacts = [];

if ($role === 'student') {
    // Lecturers for student's enrolled courses
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, GROUP_CONCAT(DISTINCT c.code SEPARATOR ', ') as course_code,
               (SELECT COUNT(*) FROM communications com WHERE com.sender_id = u.id AND com.receiver_id = ? AND com.is_read = 0) as unread_count
        FROM enrollments e
        JOIN course_groups cg ON e.course_group_id = cg.id
        JOIN users u ON cg.lecturer_id = u.id
        JOIN courses c ON cg.course_id = c.id
        WHERE e.student_id = ?
        GROUP BY u.id, u.name
    ");
    $stmt->execute([$user_id, $user_id]);
    $contacts = $stmt->fetchAll();
} else if ($role === 'lecturer') {
    // Students enrolled in lecturer's courses
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.student_id,
               (SELECT COUNT(*) FROM communications com WHERE com.sender_id = u.id AND com.receiver_id = ? AND com.is_read = 0) as unread_count
        FROM course_groups cg
        JOIN enrollments e ON cg.id = e.course_group_id
        JOIN users u ON e.student_id = u.id
        WHERE cg.lecturer_id = ?
        AND EXISTS (SELECT 1 FROM communications c WHERE c.sender_id = u.id AND c.receiver_id = ?)
        GROUP BY u.id, u.name, u.student_id
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $contacts = $stmt->fetchAll();
}

echo json_encode(['success' => true, 'data' => $contacts]);
?>
