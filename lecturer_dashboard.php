<?php
// lecturer_dashboard.php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    header("Location: login.php");
    exit();
}

$lecturer_id = $_SESSION['user_id'];

// Get assigned courses and semester progress
$stmt = $pdo->prepare("
    SELECT cg.id as course_group_id, c.code, c.name, g.group_code, t.day_of_week, t.start_time, t.end_time, cg.total_sessions,
    (SELECT COUNT(*) FROM enrollments e WHERE e.course_group_id = cg.id) as enrolled_count,
    (SELECT COUNT(*) FROM attendance_sessions s WHERE s.course_group_id = cg.id AND s.status='closed') as sessions_taught
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
    SELECT s.id, s.code, s.expires_at, s.duration_minutes, cg.id as course_group_id, c.code as course_code, g.group_code,
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
    <style>
        .chat-container { height: 400px; display: flex; flex-direction: column; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; }
        .message-bubble { max-width: 80%; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .message-sent { background: #007bff; color: white; align-self: flex-end; margin-left: auto; }
        .message-received { background: #e9ecef; color: black; align-self: flex-start; margin-right: auto; }
        .qr-placeholder { background: white; padding: 10px; border-radius: 10px; display: inline-block; }
    </style>
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
        <!-- TABS -->
        <ul class="nav nav-pills mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#overview" type="button" role="tab">Course Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#live-sessions" type="button" role="tab">Live Sessions <span class="badge bg-danger"><?php echo count($active_sessions); ?></span></button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#inbox" type="button" role="tab">Claims & inbox</button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- OVERVIEW TAB -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-3">My Classes & Semester Progress</h4>
                    <div id="lecture-alert-container"></div>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Schedule</th>
                                    <th>Enrolled</th>
                                    <th>Progress</th>
                                    <th>Start Session</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($assigned_courses as $c): ?>
                                <?php 
                                    $remaining = max(0, $c['total_sessions'] - $c['sessions_taught']);
                                    $progressPercent = $c['total_sessions'] > 0 ? round(($c['sessions_taught'] / $c['total_sessions']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($c['code']); ?></strong><br><small><?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['group_code']); ?>)</small></td>
                                    <td><?php echo htmlspecialchars($c['day_of_week'] ?? 'N/A') . ' ' . substr($c['start_time'] ?? '', 0, 5) . '-' . substr($c['end_time'] ?? '', 0, 5); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo $c['enrolled_count']; ?> Students</span></td>
                                    <td>
                                        <div class="small mb-1">Taught: <?php echo $c['sessions_taught']; ?> / <?php echo $c['total_sessions']; ?> (<?php echo $remaining; ?> remaining)</div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $progressPercent; ?>%"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm mb-2">
                                            <span class="input-group-text">Duration</span>
                                            <select class="form-select session-duration-select mb-2" id="duration-<?php echo $c['course_group_id']; ?>">
                                                <option value="10">10 mins</option>
                                                <option value="30" selected>30 mins</option>
                                                <option value="60">60 mins</option>
                                                <option value="120">120 mins</option>
                                            </select>
                                        </div>
                                        <div class="d-flex flex-column gap-2">
                                            <button class="btn btn-primary btn-sm start-session-btn w-100" data-cgid="<?php echo $c['course_group_id']; ?>">Acquire GPS & Start</button>
                                            <button class="btn btn-warning btn-sm send-final-report-btn w-100" data-cgid="<?php echo $c['course_group_id']; ?>">Send Final Report & Statistics</button>
                                        </div>
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

            <!-- LIVE SESSIONS TAB -->
            <div class="tab-pane fade" id="live-sessions" role="tabpanel">
                <?php if(empty($active_sessions)): ?>
                    <div class="alert alert-info">No active sessions. Start a session from the Course Overview tab.</div>
                <?php else: ?>
                    <div class="row">
                    <?php foreach($active_sessions as $sess): ?>
                        <div class="col-md-12 mb-4 live-session-container" data-session-id="<?php echo $sess['id']; ?>">
                            <div class="card dashboard-card border-success border-2 p-4">
                                <div class="row">
                                    <div class="col-md-4 text-center border-end">
                                        <h4 class="text-success mb-2"><?php echo htmlspecialchars($sess['course_code'] . ' - ' . $sess['group_code']); ?></h4>
                                        <div class="attendance-code-display my-3"><?php echo htmlspecialchars($sess['code']); ?></div>
                                        
                                        <div class="text-muted small mb-3">
                                            Expires: <?php echo date('H:i', strtotime($sess['expires_at'])); ?> <br>
                                            <span class="live-present-counter">Attendees: <?php echo $sess['present_count']; ?></span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-center gap-2">
                                            <button class="btn btn-danger btn-sm close-session-btn" data-id="<?php echo $sess['id']; ?>">Close Session</button>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8">
                                        <h5 class="mb-3 d-flex justify-content-between">
                                            Live Attendance Viewer <span class="badge bg-primary live-percentage-badge">0%</span>
                                        </h5>
                                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                            <table class="table table-sm table-striped" id="live-table-<?php echo $sess['id']; ?>">
                                                <thead style="position: sticky; top: 0; background: white;">
                                                    <tr>
                                                        <th>Student ID</th>
                                                        <th>Name</th>
                                                        <th>Status</th>
                                                        <th>Time</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td colspan="4" class="text-center text-muted">Loading live data...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- INBOX TAB -->
            <div class="tab-pane fade" id="inbox" role="tabpanel">
                <div class="row">
                    <div class="col-md-5">
                        <div class="card dashboard-card p-3 mb-3">
                            <h5 class="mb-3">Pending Claims</h5>
                            <div id="claims-list-container">Loading claims...</div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="card dashboard-card p-3">
                            <h5 class="mb-3">Direct Messages</h5>
                            <div class="row">
                                <div class="col-4 border-end" id="chat-contacts-container">
                                    Loading contacts...
                                </div>
                                <div class="col-8">
                                    <div class="chat-container">
                                        <div class="chat-messages" id="chat-messages-container">
                                            <div class="text-muted text-center mt-5">Select a student to chat</div>
                                        </div>
                                        <form id="chat-form" class="mt-2 d-none">
                                            <input type="hidden" id="chat-receiver-id">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="chat-input" placeholder="Type a message..." required>
                                                <button class="btn btn-primary" type="submit">Send</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap bundle JS for tabs -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const USER_ID = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="assets/js/main.js?v=5"></script>
</body>
</html>
