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
    // Total sessions for this root course+group
    $s_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM attendance_sessions sess
        JOIN course_groups cg ON sess.course_group_id = cg.id
        WHERE cg.course_id = ? AND cg.group_id = ? AND sess.status='closed'
    ");
    $s_stmt->execute([$course['course_id'], $course['group_id']]);
    $total_sessions = $s_stmt->fetch()['total'];
    
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
    
    $percentage = $total_sessions > 0 ? round(($attended / $total_sessions) * 100) : 100;
    
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
        'total' => $total_sessions
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
        <div class="row">
            <div class="col-md-5">
                <div class="card dashboard-card p-4 mb-4">
                    <h4 class="mb-3">Mark Attendance</h4>
                    
                    <div id="alert-container"></div>

                    <form id="attendance-form">
                        <div class="mb-3">
                            <label class="form-label">6-Digit Session Code</label>
                            <input type="text" class="form-control" id="attendance_code" maxlength="6" pattern="\d{6}" placeholder="123456" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="mark-btn">Verify GPS & Mark Present</button>
                    </form>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="card dashboard-card p-4 mb-4">
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
                                            <small class="text-muted"><?php echo $stat['attended']; ?>/<?php echo $stat['total']; ?> sessions</small>
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
        </div>
    </div>
    <script src="assets/js/main.js?v=4"></script>
</body>
</html>
