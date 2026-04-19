<?php
// api/get_unread.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get count of unread messages where the current user is the receiver
$stmt = $pdo->prepare("SELECT COUNT(*) FROM communications WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$user_id]);
$count = $stmt->fetchColumn();

echo json_encode(['success' => true, 'count' => $count]);
?>
