<?php
// api/upload_enrollment.php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit();
}

$filename = $_FILES['file']['name'];
$filetmp = $_FILES['file']['tmp_name'];

$handle = fopen($filetmp, "r");
if (!$handle) {
    echo json_encode(['success' => false, 'error' => 'Could not read file.']);
    exit();
}

$pdo->beginTransaction();
$log_msgs = [];
$success_count = 0;

try {
    $row = 0;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;
        // Skip header
        if ($row === 1 && strtolower(trim($data[0])) === 'student_id') {
            continue;
        }

        if (count($data) < 6) {
            $log_msgs[] = "Row $row skipped: missing columns.";
            continue;
        }

        $student_id_code = trim($data[0]);
        $full_name = trim($data[1]);
        $email = trim($data[2]);
        $course_code = trim($data[3]);
        $group_code = trim($data[4]);
        $lecturer_name = trim($data[5]);

        // 1. Create or Get Student
        $u_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $u_stmt->execute([$email]);
        $student = $u_stmt->fetch();
        if (!$student) {
            $passHash = password_hash('password123', PASSWORD_BCRYPT);
            $ins_u = $pdo->prepare("INSERT INTO users (student_id, name, email, password_hash, role) VALUES (?, ?, ?, ?, 'student')");
            $ins_u->execute([$student_id_code, $full_name, $email, $passHash]);
            $s_id = $pdo->lastInsertId();
        } else {
            $s_id = $student['id'];
        }

        // 2. Create or Get Course
        $c_stmt = $pdo->prepare("SELECT id FROM courses WHERE code = ?");
        $c_stmt->execute([$course_code]);
        $course = $c_stmt->fetch();
        if (!$course) {
            $pdo->prepare("INSERT INTO courses (code, name) VALUES (?, ?)")->execute([$course_code, $course_code . " Course"]);
            $c_id = $pdo->lastInsertId();
        } else {
            $c_id = $course['id'];
        }

        // 3. Create or Get Group
        $g_stmt = $pdo->prepare("SELECT id FROM groups WHERE group_code = ?");
        $g_stmt->execute([$group_code]);
        $group = $g_stmt->fetch();
        if (!$group) {
            $pdo->prepare("INSERT INTO groups (group_code) VALUES (?)")->execute([$group_code]);
            $g_id = $pdo->lastInsertId();
        } else {
            $g_id = $group['id'];
        }

        // 4. Create or Get Lecturer (Assume default email if missing, dummy approach for CSV)
        $l_email = strtolower(str_replace(' ', '.', $lecturer_name)) . '@system.edu';
        $l_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'lecturer'");
        $l_stmt->execute([$l_email]);
        $lecturer = $l_stmt->fetch();
        if (!$lecturer) {
            $passHash = password_hash('password123', PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'lecturer')")->execute([$lecturer_name, $l_email, $passHash]);
            $l_id = $pdo->lastInsertId();
        } else {
            $l_id = $lecturer['id'];
        }

        // 5. Create or Get Course Group (Ignore lecturer differences to prevent duplicates)
        $cg_stmt = $pdo->prepare("SELECT id FROM course_groups WHERE course_id = ? AND group_id = ?");
        $cg_stmt->execute([$c_id, $g_id]);
        $cg = $cg_stmt->fetch();
        if (!$cg) {
            // default lat/lng 0 for newly created to avoid GPS errors, or use dummy
            $pdo->prepare("INSERT INTO course_groups (course_id, group_id, lecturer_id, room_lat, room_lng) VALUES (?, ?, ?, 0, 0)")->execute([$c_id, $g_id, $l_id]);
            $cg_id = $pdo->lastInsertId();
        } else {
            $cg_id = $cg['id'];
        }

        // 6. Enroll Student
        $e_stmt = $pdo->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_group_id = ?");
        $e_stmt->execute([$s_id, $cg_id]);
        if (!$e_stmt->fetch()) {
            $pdo->prepare("INSERT INTO enrollments (student_id, course_group_id) VALUES (?, ?)")->execute([$s_id, $cg_id]);
            $success_count++;
        }

    }
    fclose($handle);

    // Save log
    $pdo->prepare("INSERT INTO sync_logs (admin_id, filename, status, logs) VALUES (?, ?, 'success', ?)")
        ->execute([$_SESSION['user_id'], $filename, "Processed $row rows. $success_count new enrollments added."]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Successfully processed $row rows. $success_count new enrollments."]);

} catch (Exception $e) {
    $pdo->rollBack();
    
    // Save failed log
    $pdo->prepare("INSERT INTO sync_logs (admin_id, filename, status, logs) VALUES (?, ?, 'failed', ?)")
        ->execute([$_SESSION['user_id'], $filename, $e->getMessage()]);

    echo json_encode(['success' => false, 'error' => 'Database error during upload.']);
}
?>
