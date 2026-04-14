<?php
// api/send_final_report.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$cgid = $data['course_group_id'] ?? null;

if (!$cgid) {
    echo json_encode(['success' => false, 'error' => 'Missing course grouping ID.']);
    exit();
}

// Ensure lecturer owns it
$check = $pdo->prepare("SELECT c.code, c.name, g.group_code, cg.total_sessions FROM course_groups cg JOIN courses c ON cg.course_id = c.id JOIN groups g ON cg.group_id = g.id WHERE cg.id = ? AND cg.lecturer_id = ?");
$check->execute([$cgid, $_SESSION['user_id']]);
$course = $check->fetch();

if (!$course) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid course.']);
    exit();
}

// Generate the Statistics JSON
$enrolledStmt = $pdo->prepare("
    SELECT u.id, u.name, u.student_id 
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    WHERE e.course_group_id = ?
");
$enrolledStmt->execute([$cgid]);
$students = $enrolledStmt->fetchAll();

$taught_sessions = $pdo->prepare("SELECT id FROM attendance_sessions WHERE course_group_id = ? AND status='closed'")->execute([$cgid]);
$taughtCount = $pdo->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE course_group_id = ? AND status='closed'");
$taughtCount->execute([$cgid]);
$totalTaught = $taughtCount->fetchColumn();

// If no sessions, cannot send report
if ($totalTaught == 0) {
    echo json_encode(['success' => false, 'error' => 'Cannot send report, no sessions have been finalized.']);
    exit();
}

$reportData = [];

foreach ($students as $stu) {
    $attCount = $pdo->prepare("
        SELECT COUNT(DISTINCT ar.session_id) 
        FROM attendance_records ar 
        JOIN attendance_sessions s ON ar.session_id = s.id
        WHERE s.course_group_id = ? AND ar.student_id = ? AND ar.status = 'present' AND s.status = 'closed'
    ");
    $attCount->execute([$cgid, $stu['id']]);
    $attended = $attCount->fetchColumn();

    $percentage = round(($attended / $totalTaught) * 100);
    $reportData[] = [
        'student_id' => $stu['student_id'],
        'name' => $stu['name'],
        'attended' => $attended,
        'percentage' => $percentage
    ];
}

$fullPayload = json_encode([
    'course_name' => $course['code'] . ' - ' . $course['name'],
    'group_code' => $course['group_code'],
    'total_taught' => $totalTaught,
    'total_expected' => $course['total_sessions'],
    'students' => $reportData
]);

// Since Admin role is ID 1 (default from DB), we insert into sync_logs or a new dedicated table.
// `sync_logs` was designed for CSV sync data, but we can repurpose it safely for this.
// "logs" will store the json string.
$admin_id = 1;

$ins = $pdo->prepare("INSERT INTO sync_logs (admin_id, filename, status, logs) VALUES (?, ?, 'success', ?)");
if ($ins->execute([$admin_id, 'Final_Report_' . $course['code'] . '_' . date('Ymd_Hi'), $fullPayload])) {
    echo json_encode(['success' => true, 'message' => 'Attendance statistics correctly sent to Administrator!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error while saving report.']);
}

?>
