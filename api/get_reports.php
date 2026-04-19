<?php
// api/get_reports.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $reports = $pdo->query("SELECT id, filename, timestamp, logs FROM sync_logs WHERE filename LIKE 'Final_Report_%' ORDER BY timestamp DESC")->fetchAll();
    echo json_encode(['success' => true, 'reports' => $reports]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
