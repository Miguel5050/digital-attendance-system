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

$sessionsStmt = $pdo->prepare("SELECT id, created_at, duration_minutes FROM attendance_sessions WHERE course_group_id = ? AND status='closed' ORDER BY created_at ASC");
$sessionsStmt->execute([$cgid]);
$sessions = $sessionsStmt->fetchAll();

$totalTaught = count($sessions);

// If no sessions, cannot send report
if ($totalTaught == 0) {
    echo json_encode(['success' => false, 'error' => 'Cannot send report, no sessions have been finalized.']);
    exit();
}

$reportData = [];

// For detailed breakdown list per student
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
    
    // Per-session boolean map
    $sessionMap = [];
    foreach($sessions as $sess) {
        $checkPresent = $pdo->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ? AND status = 'present'");
        $checkPresent->execute([$sess['id'], $stu['id']]);
        $sessionMap[$sess['id']] = $checkPresent->fetch() ? true : false;
    }

    $reportData[] = [
        'student_id' => $stu['student_id'],
        'name' => $stu['name'],
        'attended' => $attended,
        'percentage' => $percentage,
        'sessions_detailed' => $sessionMap
    ];
}

$sessionDetails = [];
$sessionNumber = 1;
foreach($sessions as $sess) {
    $sessionDetails[$sess['id']] = [
        'name' => 'Session ' . $sessionNumber . ' (' . date('D, jS M Y', strtotime($sess['created_at'])) . ')',
        'duration' => $sess['duration_minutes']
    ];
    $sessionNumber++;
}

$fullPayload = json_encode([
    'course_name' => $course['code'] . ' - ' . $course['name'],
    'group_code' => $course['group_code'],
    'total_taught' => $totalTaught,
    'total_expected' => $course['total_sessions'],
    'students' => $reportData,
    'session_meta' => $sessionDetails
]);

// Since Admin role is ID 1 (default from DB), we insert into sync_logs 
// We MUST make sure admin_id is valid. Let's find an active admin to assign it to loosely, or 1.
$adminCheck = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn() ?? 1;

$ins = $pdo->prepare("INSERT INTO sync_logs (admin_id, filename, status, logs) VALUES (?, ?, 'success', ?)");
if ($ins->execute([$adminCheck, 'Final_Report_' . $course['code'] . '_' . date('Ymd_Hi'), $fullPayload])) {
    echo json_encode(['success' => true, 'message' => 'Attendance statistics correctly sent to Administrator!']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error while saving report.']);
}

?>
