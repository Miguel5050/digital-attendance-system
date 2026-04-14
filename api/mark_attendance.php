<?php
// api/mark_attendance.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => "Session Role Mismatch: Please relogin as Student."]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? null;
$course_group_id = $data['course_group_id'] ?? null;
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;
$student_id = $_SESSION['user_id'];

if (!$code || !$course_group_id || !$lat || !$lng) {
    echo json_encode(['success' => false, 'error' => 'Missing required data (Location, Code, or Course).']);
    exit();
}

// 1. Get Active Session Context purely by code AND course
$stmt = $pdo->prepare("
    SELECT s.id, s.expires_at, s.lecturer_lat, s.lecturer_lng, cg.room_lat, cg.room_lng, cg.course_id, cg.group_id
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_group_id = cg.id
    WHERE s.code = ? AND cg.id = ? AND s.status = 'active'
");
$stmt->execute([$code, $course_group_id]);
$session = $stmt->fetch();

if (!$session) {
    echo json_encode(['success' => false, 'error' => 'Invalid active attendance code for this selected course.']);
    exit();
}

// 2. Validate Student is actually enrolled in this Course+Group
$checkE = $pdo->prepare("
    SELECT e.id FROM enrollments e
    JOIN course_groups cg ON e.course_group_id = cg.id
    WHERE e.student_id = ? AND cg.course_id = ? AND cg.group_id = ?
");
$checkE->execute([$student_id, $session['course_id'], $session['group_id']]);
if (!$checkE->fetch()) {
    $pdo->prepare("INSERT INTO failed_attempts (student_id, session_id, reason) VALUES (?, ?, 'Not enrolled')")->execute([$student_id, $session['id']]);
    echo json_encode(['success' => false, 'error' => 'You are not enrolled in this active class.']);
    exit();
}

// 3. Validation Expiration
if (strtotime($session['expires_at']) < time()) {
    echo json_encode(['success' => false, 'error' => 'Session has expired.']);
    exit();
}

// 4. Duplicate Check
$dup = $pdo->prepare("
    SELECT ar.id FROM attendance_records ar
    JOIN attendance_sessions s ON ar.session_id = s.id
    JOIN course_groups cg_s ON s.course_group_id = cg_s.id
    WHERE s.code = ? AND ar.student_id = ?
");
$dup->execute([$code, $student_id]);
if ($dup->fetch()) {
    echo json_encode(['success' => false, 'error' => 'You have already marked attendance for this session.']);
    exit();
}

// 5. GPS Verification Check (Haversine Formula) Function
function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
    if (!$latitudeTo || !$longitudeTo) return 0; // If room has no GPS constraints defined
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
      cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

$targetLat = $session['lecturer_lat'] ?? $session['room_lat'];
$targetLng = $session['lecturer_lng'] ?? $session['room_lng'];

$distance = haversineGreatCircleDistance($lat, $lng, $targetLat, $targetLng);
// If room has coordinates constraint, ensure < 50 meters
if ($targetLat && $targetLng) {
    if ($distance > 50) {
        $pdo->prepare("INSERT INTO failed_attempts (student_id, session_id, reason) VALUES (?, ?, 'Out of 50m range')")->execute([$student_id, $session['id']]);
        echo json_encode(['success' => false, 'error' => 'GPS Verification failed. You are ' . round($distance) . 'm away from the classroom.', 'needs_claim' => true, 'session_id' => $session['id']]);
        exit();
    }
}

// 6. Insert Record
$ins = $pdo->prepare("INSERT INTO attendance_records (session_id, student_id, status, location_lat, location_lng) VALUES (?, ?, 'present', ?, ?)");
if ($ins->execute([$session['id'], $student_id, $lat, $lng])) {
    echo json_encode(['success' => true, 'message' => 'Attendance marked successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to mark attendance.']);
}

?>
