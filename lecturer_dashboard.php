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
    SELECT cg.id as course_group_id, c.code, c.name, g.group_code, t.day_of_week, t.start_time, t.end_time, cg.total_sessions,
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

// Get Enrolled Master Roster
$rosterStmt = $pdo->prepare("
    SELECT u.student_id, u.name, c.code as course_code, c.name as course_name, g.group_code, 
           cg.id as course_group_id, u.id as user_id,
           (SELECT COUNT(DISTINCT ar.session_id) 
            FROM attendance_records ar 
            JOIN attendance_sessions sess ON ar.session_id = sess.id 
            WHERE sess.course_group_id = cg.id AND ar.student_id = u.id AND ar.status = 'present' AND sess.status = 'closed') as attended_count
    FROM enrollments e
    JOIN users u ON e.student_id = u.id
    JOIN course_groups cg ON e.course_group_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    WHERE cg.lecturer_id = ?
    ORDER BY c.code ASC, g.group_code ASC, u.name ASC
");
$rosterStmt->execute([$lecturer_id]);
$roster = $rosterStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for group taught counts
$group_taught = [];
foreach($assigned_courses as $ac) {
    $group_taught[$ac['course_group_id']] = $ac['sessions_taught'];
}

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
        .chat-container { height: 500px; display: flex; flex-direction: column; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; }
        .message-bubble { max-width: 80%; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .message-sent { background: #007bff; color: white; align-self: flex-end; margin-left: auto; }
        .message-received { background: #e9ecef; color: black; align-self: flex-start; margin-right: auto; }
        .session-block { background: #fdfdfd; border-left: 4px solid #007bff; margin-bottom: 10px; padding: 10px; }
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
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#students-tab" type="button" role="tab">Enrolled Data Analytics</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#messages" type="button" role="tab">Direct Messages <span class="badge bg-danger d-none" id="msg-badge">0</span></button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- OVERVIEW TAB -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="card dashboard-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="mb-0">My Classes & Timeline</h4>
                    </div>
                    <div id="lecture-alert-container"></div>
                    
                    <div class="accordion" id="classesAccordion">
                        <?php if(empty($assigned_courses)): ?>
                            <div class="alert alert-info">No assigned courses.</div>
                        <?php endif; ?>
                        
                        <?php foreach($assigned_courses as $idx => $c): ?>
                            <?php 
                                $cgid = $c['course_group_id'];
                                
                                // Fetch all enrolled students to map Attendance securely
                                $stuStmt = $pdo->prepare("SELECT u.id, u.name, u.student_id FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_group_id = ?");
                                $stuStmt->execute([$cgid]);
                                $enrolled = $stuStmt->fetchAll(PDO::FETCH_ASSOC);

                                // Fetch past closed sessions
                                $sessStmt = $pdo->prepare("SELECT id, created_at, duration_minutes FROM attendance_sessions WHERE course_group_id = ? AND status='closed' ORDER BY created_at ASC");
                                $sessStmt->execute([$cgid]);
                                $past_sessions = $sessStmt->fetchAll();
                                $taughtCount = count($past_sessions);
                                
                                // Generate title
                                $title = $c['code'] . ' - ' . $c['name'] . ' (' . $c['group_code'] . ')';
                            ?>
                            <div class="accordion-item mb-3 border rounded">
                                <h2 class="accordion-header" id="heading<?php echo $idx; ?>">
                                    <button class="accordion-button <?php echo $idx===0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $idx; ?>">
                                        <div class="w-100 d-flex justify-content-between pe-3">
                                            <strong><?php echo htmlspecialchars($title); ?></strong>
                                            <span class="badge bg-secondary"><?php echo $taughtCount; ?> / <?php echo $c['total_sessions']; ?> Sessions Conducted</span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $idx; ?>" class="accordion-collapse collapse <?php echo $idx===0 ? 'show' : ''; ?>" data-bs-parent="#classesAccordion">
                                    <div class="accordion-body bg-light">
                                        
                                        <!-- Actions Row -->
                                        <div class="row mb-4 bg-white p-3 border rounded align-items-end shadow-sm mx-0">
                                            <div class="col-md-5">
                                                <small class="text-muted d-block mb-1">Start New Session</small>
                                                <div class="input-group">
                                                    <span class="input-group-text">Duration</span>
                                                    <select class="form-select session-duration-select" id="duration-<?php echo $cgid; ?>">
                                                        <option value="10">10 mins</option>
                                                        <option value="30" selected>30 mins</option>
                                                        <option value="60">60 mins</option>
                                                        <option value="120">120 mins</option>
                                                    </select>
                                                    <button class="btn btn-primary start-session-btn" data-cgid="<?php echo $cgid; ?>">Acquire GPS & Start</button>
                                                </div>
                                            </div>
                                            <div class="col-md-7 text-end">
                                                <small class="text-muted d-block mb-1">Finalize Module (Pushes detailed timeline to Admin)</small>
                                                <button class="btn btn-warning send-final-report-btn" data-cgid="<?php echo $cgid; ?>">Send Final Report & Statistics</button>
                                            </div>
                                        </div>

                                        <!-- Timeline -->
                                        <h6 class="text-muted mb-3 text-uppercase fw-bold">Session History Timeline</h6>
                                        <?php if(empty($past_sessions)): ?>
                                            <p class="small text-muted ms-2">No completed sessions recorded yet.</p>
                                        <?php else: ?>
                                            <?php foreach($past_sessions as $sIndex => $sess): ?>
                                                <?php
                                                    $recStmt = $pdo->prepare("SELECT student_id FROM attendance_records WHERE session_id = ? AND status='present'");
                                                    $recStmt->execute([$sess['id']]);
                                                    $presentIds = $recStmt->fetchAll(PDO::FETCH_COLUMN);

                                                    $presentNames = [];
                                                    $absentNames = [];

                                                    foreach($enrolled as $stu) {
                                                        if (in_array($stu['id'], $presentIds)) {
                                                            $presentNames[] = $stu['name'] . ' (' . $stu['student_id'] . ')';
                                                        } else {
                                                            $absentNames[] = $stu['name'] . ' (' . $stu['student_id'] . ')';
                                                        }
                                                    }
                                                ?>
                                                <div class="session-block shadow-sm">
                                                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                                        <strong class="text-primary">Session <?php echo ($sIndex + 1); ?> &mdash; <?php echo date('l, d M Y - H:i', strtotime($sess['created_at'])); ?></strong>
                                                        <span class="text-muted small">Duration: <?php echo $sess['duration_minutes']; ?>m | Attended: <?php echo count($presentNames); ?>/<?php echo count($enrolled); ?></span>
                                                    </div>
                                                    <div class="row small">
                                                        <div class="col-md-6 border-end">
                                                            <strong class="text-success mb-1 d-block"><i class="text-success">&check;</i> Present</strong>
                                                            <div class="text-muted" style="max-height:100px; overflow-y:auto; line-height: 1.2;">
                                                                <?php echo empty($presentNames) ? 'None' : implode('<br>', array_map('htmlspecialchars', $presentNames)); ?>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <strong class="text-danger mb-1 d-block"><i class="text-danger">&cross;</i> Absent</strong>
                                                            <div class="text-muted" style="max-height:100px; overflow-y:auto; line-height: 1.2;">
                                                                <?php echo empty($absentNames) ? 'None' : implode('<br>', array_map('htmlspecialchars', $absentNames)); ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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

            <!-- ENROLLED STUDENTS ANALYTICS TAB -->
            <div class="tab-pane fade" id="students-tab" role="tabpanel">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-3">Aggregate Students Roster & Statistics</h4>
                    <p class="text-muted small">This list contains all students registered under your assigned modules. Data updates automatically when sessions are closed.</p>
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle" style="font-size:0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>Enrolled Module</th>
                                    <th>Progress</th>
                                    <th>Eligibility</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($roster)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No students are currently enrolled in your courses.</td></tr>
                                <?php else: ?>
                                    <?php foreach($roster as $stu): 
                                        $taught = $group_taught[$stu['course_group_id']] ?? 0;
                                        $attended = $stu['attended_count'];
                                        $percentage = $taught > 0 ? round(($attended / $taught) * 100) : 100;
                                    ?>
                                        <tr class="<?php echo ($taught > 0 && $percentage < 70) ? 'table-danger' : ''; ?>">
                                            <td><strong><?php echo htmlspecialchars($stu['student_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($stu['name']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($stu['course_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($stu['course_name']); ?> (<?php echo htmlspecialchars($stu['group_code']); ?>)</small>
                                            </td>
                                            <td>
                                                <div class="d-flex justify-content-between mb-1 small">
                                                    <span>Attended: <?php echo $attended; ?> / <?php echo $taught; ?> conducted</span>
                                                    <span class="fw-bold"><?php echo $percentage; ?>%</span>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar <?php echo $percentage < 70 ? 'bg-danger' : 'bg-success'; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if($taught == 0): ?>
                                                    <span class="badge bg-secondary">Awaiting Data</span>
                                                <?php elseif($percentage >= 70): ?>
                                                    <span class="badge bg-success">Status Normal</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Critical Risk</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CHAT MESSAGES TAB -->
            <div class="tab-pane fade" id="messages" role="tabpanel">
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
    
    <!-- Bootstrap bundle JS for tabs -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const USER_ID = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="assets/js/main.js?v=7"></script>
</body>
</html>
