<?php
// api/get_live_attendance.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$session_id = $_GET['session_id'] ?? null;

if (!$session_id) {
    echo json_encode(['success' => false, 'error' => 'Missing session ID']);
    exit();
}

// Verify this is an active session owned by the lecturer
$check = $pdo->prepare("
    SELECT s.id, cg.course_id, cg.group_id 
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_group_id = cg.id
    WHERE s.id = ? AND cg.lecturer_id = ? AND s.status = 'active'
");
$check->execute([$session_id, $_SESSION['user_id']]);
$session = $check->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit();
}

// Get enrolled students for the group
$enrolled = $pdo->prepare("
    SELECT u.id, u.name, u.student_id 
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN course_groups cg ON e.course_group_id = cg.id
    WHERE cg.course_id = ? AND cg.group_id = ?
");
$enrolled->execute([$session['course_id'], $session['group_id']]);
$students = $enrolled->fetchAll();

// Get attendance records for this session
$att_records = $pdo->prepare("
    SELECT student_id, status, timestamp 
    FROM attendance_records 
    WHERE session_id = ?
");
$att_records->execute([$session_id]);
$recordsObj = [];
foreach ($att_records->fetchAll() as $r) {
    $recordsObj[$r['student_id']] = $r;
}

$response = [
    'present' => [],
    'absent' => []
];

foreach ($students as $stu) {
    if (isset($recordsObj[$stu['id']])) {
        if ($recordsObj[$stu['id']]['status'] === 'present') {
            $response['present'][] = [
                'name' => $stu['name'],
                'student_id' => $stu['student_id'],
                'time' => date('H:i:s', strtotime($recordsObj[$stu['id']]['timestamp'])),
                'user_id' => $stu['id']
            ];
        } else {
            $response['absent'][] = [
                'name' => $stu['name'],
                'student_id' => $stu['student_id'],
                'user_id' => $stu['id']
            ];
        }
    } else {
        $response['absent'][] = [
            'name' => $stu['name'],
            'student_id' => $stu['student_id'],
            'user_id' => $stu['id']
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => $response
]);
