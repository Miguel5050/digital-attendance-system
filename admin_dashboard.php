<?php
// admin_dashboard.php
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

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

// Get recent standard sync logs
$logs = $pdo->query("SELECT * FROM sync_logs WHERE filename NOT LIKE 'Final_Report_%' ORDER BY timestamp DESC LIMIT 5")->fetchAll();

// Get Final Reports
$reports = $pdo->query("SELECT id, filename, timestamp, logs FROM sync_logs WHERE filename LIKE 'Final_Report_%' ORDER BY timestamp DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print {
            body * { visibility: hidden; }
            .printable-report, .printable-report * { visibility: visible; }
            .printable-report { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4 no-print">
        <div class="container">
            <a class="navbar-brand" href="#">System Administrator</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container no-print">
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

        <!-- TABS -->
        <ul class="nav nav-pills mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#overview" type="button" role="tab">Data & Logs</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#reports-tab" type="button" role="tab">Exam Eligibility Reports <span class="badge bg-danger"><?php echo count($reports); ?></span></button>
            </li>
        </ul>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- OVERVIEW TAB -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
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
                            <h4 class="mb-3">Recent CSV Sync Logs</h4>
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
                        </div>
                    </div>
                </div>
            </div>

            <!-- REPORTS TAB -->
            <div class="tab-pane fade" id="reports-tab" role="tabpanel">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card dashboard-card p-3 h-100">
                            <h5 class="mb-3">Submitted Reports</h5>
                            <ul class="list-group list-group-flush report-list">
                                <?php if(empty($reports)): ?>
                                    <li class="list-group-item text-muted">No finished course reports available.</li>
                                <?php endif; ?>
                                <?php foreach($reports as $r): ?>
                                    <li class="list-group-item cursor-pointer text-primary" onclick='loadReport(<?php echo $r['id']; ?>)'>
                                        <?php echo date('M d, H:i', strtotime($r['timestamp'])); ?> - <?php echo str_replace('Final_Report_', '', htmlspecialchars($r['filename'])); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <div id="hidden-report-data" class="d-none">
                                <?php foreach($reports as $r): ?>
                                    <textarea id="report-json-<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['logs']); ?></textarea>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card dashboard-card p-4" id="report-view">
                            <div class="text-muted text-center my-5 py-5">Select a report from the list to view eligibility logic.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRINTABLE CONTAINER (HIDDEN UNLESS PRINTING) -->
    <div id="print-area" class="printable-report container bg-white p-5 d-none">
    </div>

    <!-- Bootstrap bundle JS for tabs -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function loadReport(id) {
            const raw = document.getElementById('report-json-' + id).value;
            let data;
            try { data = JSON.parse(raw); } catch(e) { alert("Invalid report JSON"); return; }
            
            let tbody = '';
            let eligibleCount = 0;
            let ineligibleCount = 0;

            data.students.forEach(s => {
                const isEligible = s.percentage >= 70;
                if(isEligible) eligibleCount++; else ineligibleCount++;

                tbody += `
                    <tr class="${!isEligible ? 'table-danger' : ''}">
                        <td>${s.student_id ? s.student_id : 'N/A'}</td>
                        <td>${s.name}</td>
                        <td>${s.attended}</td>
                        <td><strong>${s.percentage}%</strong></td>
                        <td>${isEligible ? '<span class="badge bg-success">Eligible</span>' : '<span class="badge bg-danger">NOT Eligible (< 70%)</span>'}</td>
                    </tr>
                `;
            });

            const html = `
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">${data.course_name} (${data.group_code})</h4>
                    <button class="btn btn-primary" onclick="printReport()">Download / Print PDF</button>
                </div>
                <hr>
                <div class="row mb-4">
                    <div class="col-4"><strong>Total Conducted Sessions:</strong> ${data.total_taught}</div>
                    <div class="col-4 text-success"><strong>Eligible Students:</strong> ${eligibleCount}</div>
                    <div class="col-4 text-danger"><strong>Ineligible Students:</strong> ${ineligibleCount}</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Sessions Attended</th>
                                <th>Percentage</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>${tbody}</tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('report-view').innerHTML = html;

            // Also populate print view
            document.getElementById('print-area').innerHTML = `
                <h1 class="text-center mb-1">Official Attendance Report</h1>
                <h3 class="text-center mb-3 text-muted">Eligibility List for End of Semester Exams</h3>
                <h4 class="mb-3">${data.course_name} - Group ${data.group_code}</h4>
                <div class="mb-4 d-flex justify-content-between">
                    <span><strong>Conducted Sessions:</strong> ${data.total_taught}</span>
                    <span><strong>Date Generated:</strong> ${new Date().toLocaleDateString()}</span>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Attended</th>
                            <th>Percentage</th>
                            <th>Exam Eligibility</th>
                        </tr>
                    </thead>
                    <tbody>${tbody}</tbody>
                </table>
            `;
        }

        function printReport() {
            document.getElementById('print-area').classList.remove('d-none');
            window.print();
            document.getElementById('print-area').classList.add('d-none');
        }
    </script>
    <script src="assets/js/main.js?v=4"></script>
</body>
</html>
