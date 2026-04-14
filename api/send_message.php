<?php
// api/send_message.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$receiver_id = $data['receiver_id'] ?? null;
$message = trim($data['message'] ?? '');
$course_group_id = $data['course_group_id'] ?? null;
$sender_id = $_SESSION['user_id'];

if (!$receiver_id || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Missing message content or recipient.']);
    exit();
}

$ins = $pdo->prepare("INSERT INTO communications (sender_id, receiver_id, course_group_id, message) VALUES (?, ?, ?, ?)");
if ($ins->execute([$sender_id, $receiver_id, $course_group_id, $message])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
}
?>
