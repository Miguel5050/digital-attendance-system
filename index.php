<?php
// index.php
session_start();

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            exit();
        case 'lecturer':
            header('Location: lecturer_dashboard.php');
            exit();
        case 'student':
            header('Location: student_dashboard.php');
            exit();
        default:
            header('Location: login.php');
            exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>
