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
        SELECT u.id, u.name, c.code as course_code
        FROM enrollments e
        JOIN course_groups cg ON e.course_group_id = cg.id
        JOIN users u ON cg.lecturer_id = u.id
        JOIN courses c ON cg.course_id = c.id
        WHERE e.student_id = ?
        GROUP BY u.id, u.name, c.code
    ");
    $stmt->execute([$user_id]);
    $contacts = $stmt->fetchAll();
} else if ($role === 'lecturer') {
    // Students enrolled in lecturer's courses
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.student_id
        FROM course_groups cg
        JOIN enrollments e ON cg.id = e.course_group_id
        JOIN users u ON e.student_id = u.id
        WHERE cg.lecturer_id = ?
        GROUP BY u.id, u.name, u.student_id
    ");
    $stmt->execute([$user_id]);
    $contacts = $stmt->fetchAll();
}

echo json_encode(['success' => true, 'data' => $contacts]);
?>
