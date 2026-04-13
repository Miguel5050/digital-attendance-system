<?php
// admin_dashboard.php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get Statistics
$stats = [
    'students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    'lecturers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role='lecturer'")->fetchColumn(),
    'courses' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'groups' => $pdo->query("SELECT COUNT(*) FROM groups")->fetchColumn(),
    'enrollments' => $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn(),
    'sessions' => $pdo->query("SELECT COUNT(*) FROM attendance_sessions")->fetchColumn(),
    'records' => $pdo->query("SELECT COUNT(*) FROM attendance_records")->fetchColumn()
];

// Get recent sync logs
$logs = $pdo->query("SELECT * FROM sync_logs ORDER BY timestamp DESC LIMIT 5")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">System Administrator</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card dashboard-card p-3 text-center">
                    <h6 class="text-muted text-uppercase">Students</h6>
                    <div class="stat-value"><?php echo $stats['students']; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card dashboard-card p-3 text-center">
                    <h6 class="text-muted text-uppercase">Lecturers</h6>
                    <div class="stat-value"><?php echo $stats['lecturers']; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card dashboard-card p-3 text-center">
                    <h6 class="text-muted text-uppercase">Courses & Groups</h6>
                    <div class="stat-value"><?php echo $stats['courses'] . ' / ' . $stats['groups']; ?></div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card dashboard-card p-3 text-center">
                    <h6 class="text-muted text-uppercase">Total Attendance Records</h6>
                    <div class="stat-value"><?php echo $stats['records']; ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card dashboard-card p-4 h-100">
                    <h4 class="mb-3">CSV Upload</h4>
                    <p class="text-muted">Upload student enrollment data format: <code>student_id, full_name, email, course_code, group_code, lecturer_name</code></p>
                    <div id="admin-alert"></div>
                    <form id="csv-upload-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            <input class="form-control" type="file" id="csv_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary" id="upload-btn">Upload & Sync Data</button>
                    </form>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card dashboard-card p-4 h-100">
                    <h4 class="mb-3">Recent Sync Logs</h4>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>File</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($logs)): ?>
                                    <tr><td colspan="3" class="text-center">No logs found.</td></tr>
                                <?php else: ?>
                                    <?php foreach($logs as $log): ?>
                                    <tr>
                                        <td><small><?php echo date('M d, H:i', strtotime($log['timestamp'])); ?></small></td>
                                        <td><?php echo htmlspecialchars($log['filename']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                <?php echo $log['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 text-end">
                        <button class="btn btn-outline-primary btn-sm" onclick="window.location.reload()">Refresh Data</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=4"></script>
</body>
</html>
