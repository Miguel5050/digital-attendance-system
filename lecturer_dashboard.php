<?php
// lecturer_dashboard.php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

$lecturer_id = $_SESSION['user_id'];

// Get assigned courses
$stmt = $pdo->prepare("
    SELECT cg.id as course_group_id, c.code, c.name, g.group_code, t.day_of_week, t.start_time, t.end_time,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_group_id = cg.id) as enrolled_count
    FROM course_groups cg
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    LEFT JOIN timetable t ON t.course_group_id = cg.id
    WHERE cg.lecturer_id = ?
");
$stmt->execute([$lecturer_id]);
$assigned_courses = $stmt->fetchAll();

// Get active sessions
$active_stmt = $pdo->prepare("
    SELECT s.id, s.code, s.expires_at, cg.id as course_group_id, c.code as course_code, g.group_code,
    (SELECT COUNT(*) FROM attendance_records ar WHERE ar.session_id = s.id AND ar.status='present') as present_count
    FROM attendance_sessions s
    JOIN course_groups cg ON s.course_group_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    WHERE cg.lecturer_id = ? AND s.status = 'active'
");
$active_stmt->execute([$lecturer_id]);
$active_sessions = $active_stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lecturer Dashboard - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">Digital Attendance - Lecturer</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <!-- Active Sessions Panel -->
            <div class="col-md-12 mb-4">
                <h4 class="mb-3">Active Sessions</h4>
                <?php if(empty($active_sessions)): ?>
                    <div class="alert alert-info">No active sessions. Start a session from your courses list below.</div>
                <?php else: ?>
                    <div class="row">
                    <?php foreach($active_sessions as $sess): ?>
                        <div class="col-md-4">
                            <div class="card dashboard-card border-success border-2 p-3 text-center">
                                <h5 class="text-muted"><?php echo htmlspecialchars($sess['course_code'] . ' - ' . $sess['group_code']); ?></h5>
                                <div class="attendance-code-display my-3"><?php echo htmlspecialchars($sess['code']); ?></div>
                                <div class="d-flex justify-content-between mb-3 text-muted small">
                                    <span>Attendees: <?php echo $sess['present_count']; ?></span>
                                    <span>Expires: <?php echo date('H:i', strtotime($sess['expires_at'])); ?></span>
                                </div>
                                <div class="btn-group w-100">
                                    <button class="btn btn-warning btn-sm send-report-btn" data-id="<?php echo $sess['id']; ?>">Send Report</button>
                                    <button class="btn btn-danger btn-sm close-session-btn" data-id="<?php echo $sess['id']; ?>">Close Session</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assigned Courses -->
            <div class="col-md-12">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-3">My Classes</h4>
                    <div id="lecture-alert-container"></div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Group</th>
                                    <th>Schedule</th>
                                    <th>Enrolled</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($assigned_courses as $c): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($c['code']); ?></strong><br><small><?php echo htmlspecialchars($c['name']); ?></small></td>
                                    <td><?php echo htmlspecialchars($c['group_code']); ?></td>
                                    <td><?php echo htmlspecialchars($c['day_of_week'] ?? 'N/A') . ' ' . substr($c['start_time'] ?? '', 0, 5) . '-' . substr($c['end_time'] ?? '', 0, 5); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $c['enrolled_count']; ?> Students</span></td>
                                    <td>
                                        <button class="btn btn-primary btn-sm start-session-btn" data-cgid="<?php echo $c['course_group_id']; ?>">Start Session</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($assigned_courses)): ?>
                                    <tr><td colspan="5" class="text-center">No assigned courses.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/js/main.js?v=4"></script>
</body>
</html>
