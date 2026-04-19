<?php
// student_dashboard.php
require_once 'config/db.php';

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get Enrolled Courses (Aggregated to hide manual duplicate imports)
$stmt = $pdo->prepare("
    SELECT MIN(cg.id) as course_group_id, c.id as course_id, c.code, c.name, g.id as group_id, g.group_code 
    FROM enrollments e
    JOIN course_groups cg ON e.course_group_id = cg.id
    JOIN courses c ON cg.course_id = c.id
    JOIN groups g ON cg.group_id = g.id
    WHERE e.student_id = ?
    GROUP BY c.id, c.code, c.name, g.id, g.group_code
");
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll();

// Get Attendance Summary (calculated across merged imports)
$attendance_stats = [];
foreach ($courses as $course) {
    // Total expected sessions for this course
    $s_stmt = $pdo->prepare("SELECT total_sessions FROM course_groups WHERE course_id = ? AND group_id = ?");
    $s_stmt->execute([$course['course_id'], $course['group_id']]);
    $total_expected = $s_stmt->fetch()['total_sessions'] ?? 15;
    
    // Total attended
    $a_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sess.id) as total 
        FROM attendance_records ar
        JOIN attendance_sessions as sess ON ar.session_id = sess.id
        JOIN course_groups cg ON sess.course_group_id = cg.id
        WHERE cg.course_id = ? AND cg.group_id = ? AND ar.student_id = ? AND ar.status = 'present' AND sess.status='closed'
    ");
    $a_stmt->execute([$course['course_id'], $course['group_id'], $student_id]);
    $attended = $a_stmt->fetch()['total'];
    
    $percentage = $total_expected > 0 ? round(($attended / $total_expected) * 100) : 100;
    
    $status_label = "NOT ELIGIBLE FOR EXAMS";
    $status_class = "percentage-NOT-ELIGIBLE";
    if ($percentage === 100) { $status_label = "PERFECT ATTENDANCE"; $status_class = "percentage-PERFECT"; }
    elseif ($percentage >= 90) { $status_label = "GREAT"; $status_class = "percentage-GREAT"; }
    elseif ($percentage >= 80) { $status_label = "NICE"; $status_class = "percentage-NICE"; }
    elseif ($percentage >= 70) { $status_label = "SATISFACTORY"; $status_class = "percentage-SATISFACTORY"; }

    $attendance_stats[] = [
        'course' => $course['code'] . ' - ' . $course['name'] . ' (' . $course['group_code'] . ')',
        'percentage' => $percentage,
        'label' => $status_label,
        'class' => $status_class,
        'attended' => $attended,
        'total_expected' => $total_expected
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-container { height: 400px; display: flex; flex-direction: column; }
        .chat-messages { flex-grow: 1; overflow-y: auto; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; }
        .message-bubble { max-width: 80%; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .message-sent { background: #007bff; color: white; align-self: flex-end; margin-left: auto; }
        .message-received { background: #e9ecef; color: black; align-self: flex-start; margin-right: auto; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
        <div class="container">
            <a class="navbar-brand" href="#">Digital Attendance</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (<?php echo htmlspecialchars($_SESSION['student_id'] ?? ''); ?>)</span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- TABS -->
        <ul class="nav nav-pills mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#attendance-tab" type="button" role="tab">Mark Attendance</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#overview-tab" type="button" role="tab">My Overview</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#chat-tab" type="button" role="tab">Message Lecturer <span class="badge bg-danger d-none" id="msg-badge">0</span></button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- ATTENDANCE TAB -->
            <div class="tab-pane fade show active" id="attendance-tab" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card dashboard-card p-4 mx-auto mb-4">
                            <h4 class="mb-3 text-center">Mark Attendance</h4>
                            
                            <div id="alert-container"></div>

                            <form id="attendance-form" class="mt-4">
                                <div class="mb-3">
                                    <label class="form-label">Select Course</label>
                                    <select class="form-select mb-3" id="course_select" required>
                                        <option value="" disabled selected>-- Select Enrolled Course --</option>
                                        <?php foreach($courses as $c): ?>
                                            <option value="<?php echo $c['course_group_id']; ?>"><?php echo htmlspecialchars($c['code'] . ' - ' . $c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">6-Digit Session Code</label>
                                    <input type="text" class="form-control form-control-lg text-center fw-bold" id="attendance_code" maxlength="6" pattern="\d{6}" placeholder="123456" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2" id="mark-btn">Verify Course, GPS & Mark Present</button>
                            </form>
                            
                                <!-- Claim container removed natively as it's merged into the generic Messaging UI flow -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- OVERVIEW TAB -->
            <div class="tab-pane fade" id="overview-tab" role="tabpanel">
                <div class="card dashboard-card p-4">
                    <h4 class="mb-3">My Attendance Overview</h4>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Course</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($attendance_stats)): ?>
                                    <tr><td colspan="3" class="text-center">No enrolled courses found.</td></tr>
                                <?php endif; ?>
                                <?php foreach($attendance_stats as $stat): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($stat['course']); ?></strong><br>
                                            <small class="text-muted"><?php echo $stat['attended']; ?> / <?php echo $stat['total_expected']; ?> Sessions</small>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 10px;">
                                              <div class="progress-bar <?php echo $stat['percentage'] < 70 ? 'bg-danger' : 'bg-success'; ?>" role="progressbar" style="width: <?php echo $stat['percentage']; ?>%" aria-valuenow="<?php echo $stat['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <small class="fw-bold"><?php echo $stat['percentage']; ?>%</small>
                                        </td>
                                        <td class="<?php echo $stat['class']; ?>">
                                            <?php echo htmlspecialchars($stat['label']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- CHAT TAB -->
            <div class="tab-pane fade" id="chat-tab" role="tabpanel">
                <div class="card dashboard-card p-4 mx-auto" style="max-width: 800px;">
                    <h5 class="mb-3">Message Lecturer</h5>
                    <div class="mb-3">
                        <select class="form-select form-select-lg shadow-sm" id="student-chat-lecturer-select">
                            <option value="">Select a lecturer to converse with...</option>
                        </select>
                    </div>
                    
                    <div class="chat-container mt-2">
                        <div class="chat-messages" id="chat-messages-container" style="min-height: 400px;">
                            <div class="text-center text-muted mt-5">Please pick a lecturer from the dropdown above.</div>
                        </div>
                        <form id="chat-form" class="mt-3 d-none">
                            <input type="hidden" id="chat-receiver-id">
                            <div class="input-group">
                                <input type="text" class="form-control" id="chat-input" placeholder="Explain your issue clearly..." required>
                                <button class="btn btn-primary px-5" type="submit">Send</button>
                            </div>
                        </form>
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
