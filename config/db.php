<?php
// config/db.php

$host = '127.0.0.1';
$db   = 'attendance_system';
$user = 'root'; // default XAMPP user
$pass = '';     // default XAMPP empty password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // essential for security, uses real prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Return standard error avoiding revealing connection details
    error_log("Database Connection Error: " . $e->getMessage());
    die(json_encode(["error" => "Database connection failed."]));
}

// Ensure session starts securely
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'samesite' => 'Lax'
    ]);
    session_start();
}
