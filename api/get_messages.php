<?php
// api/get_messages.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$with_user_id = $_GET['with_id'] ?? null;

if (!$with_user_id) {
    echo json_encode(['success' => false, 'error' => 'Missing chat context (with_id).']);
    exit();
}

// Fetch messages between logged in user and target user
$stmt = $pdo->prepare("
    SELECT c.*, s.name as sender_name, r.name as receiver_name 
    FROM communications c
    JOIN users s ON c.sender_id = s.id
    JOIN users r ON c.receiver_id = r.id
    WHERE (c.sender_id = ? AND c.receiver_id = ?) OR (c.sender_id = ? AND c.receiver_id = ?)
    ORDER BY c.timestamp ASC
");
$stmt->execute([$user_id, $with_user_id, $with_user_id, $user_id]);
$messages = $stmt->fetchAll();

echo json_encode(['success' => true, 'data' => $messages]);
?>
