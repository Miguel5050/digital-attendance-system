<?php
// api/create_session.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$course_group_id = $data['course_group_id'] ?? null;
$duration = $data['duration_minutes'] ?? 30; // default to 30 mins
$lat = $data['lat'] ?? null;
$lng = $data['lng'] ?? null;

if (!$course_group_id) {
    echo json_encode(['success' => false, 'error' => 'Missing course group ID']);
    exit();
}

// Check if an active session already exists for this group today
$stmt = $pdo->prepare("SELECT id FROM attendance_sessions WHERE course_group_id = ? AND DATE(created_at) = CURDATE() AND status = 'active'");
$stmt->execute([$course_group_id]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'An active session already exists for this class today.']);
    exit();
}

// Generate unique 6 digit code
$code = sprintf("%06d", mt_rand(100000, 999999));

// Ensure duration is an integer
$duration = (int)$duration;
$expires_at = date('Y-m-d H:i:s', strtotime("+$duration minutes"));

$insert = $pdo->prepare("INSERT INTO attendance_sessions (course_group_id, code, expires_at, duration_minutes, lecturer_lat, lecturer_lng) VALUES (?, ?, ?, ?, ?, ?)");
if ($insert->execute([$course_group_id, $code, $expires_at, $duration, $lat, $lng])) {
    echo json_encode(['success' => true, 'code' => $code]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to create session.']);
}
?>
