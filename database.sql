-- Digital Attendance Management System Database Schema

CREATE DATABASE IF NOT EXISTS attendance_system;
USE attendance_system;

-- Users table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` VARCHAR(50) DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'lecturer', 'student') NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Courses table
CREATE TABLE `courses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Groups table
CREATE TABLE `groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `group_code` VARCHAR(20) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Course Groups (Combines Course + Group + Lecturer + Room coordinates)
CREATE TABLE `course_groups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT NOT NULL,
  `group_id` INT NOT NULL,
  `lecturer_id` INT NOT NULL,
  `room_lat` DECIMAL(10, 8) DEFAULT NULL,
  `room_lng` DECIMAL(11, 8) DEFAULT NULL,
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timetable
CREATE TABLE `timetable` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_group_id` INT NOT NULL,
  `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  FOREIGN KEY (`course_group_id`) REFERENCES `course_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enrollments
CREATE TABLE `enrollments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `course_group_id` INT NOT NULL,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`course_group_id`) REFERENCES `course_groups`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_enrollment` (`student_id`, `course_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Sessions
CREATE TABLE `attendance_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `course_group_id` INT NOT NULL,
  `code` VARCHAR(6) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `status` ENUM('active', 'closed') DEFAULT 'active',
  FOREIGN KEY (`course_group_id`) REFERENCES `course_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Records
CREATE TABLE `attendance_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('present', 'absent') DEFAULT 'present',
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `location_lat` DECIMAL(10, 8) DEFAULT NULL,
  `location_lng` DECIMAL(11, 8) DEFAULT NULL,
  FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_record` (`session_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Failed login/attendance attempts
CREATE TABLE `failed_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT DEFAULT NULL,
  `session_id` INT DEFAULT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`session_id`) REFERENCES `attendance_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sync logs
CREATE TABLE `sync_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `status` ENUM('success', 'failed') NOT NULL,
  `logs` TEXT NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSERT DEFAULT DATA --

-- 1. Default Users - Password is 'password123'
INSERT INTO `users` (`student_id`, `name`, `email`, `password_hash`, `role`) VALUES
(NULL, 'System Admin', 'admin@system.edu', '$2y$10$SdAkvQ5MdwUAmSGGARtKOuARLlNdu5yVienUyLoge2giebdeDAlWm', 'admin'),
(NULL, 'Dr. Eric', 'eric@system.edu', '$2y$10$SdAkvQ5MdwUAmSGGARtKOuARLlNdu5yVienUyLoge2giebdeDAlWm', 'lecturer'),
('26449', 'Mihigo Tristan Miguel', 'mihigo@auca.edu.rw', '$2y$10$SdAkvQ5MdwUAmSGGARtKOuARLlNdu5yVienUyLoge2giebdeDAlWm', 'student');

-- 2. Default Courses & Groups
INSERT INTO `courses` (`code`, `name`) VALUES ('CS101', 'Introduction to Computer Science');
INSERT INTO `groups` (`group_code`) VALUES ('Group D');

-- 3. Link Course to Group, Assign Dr. Eric (User ID = 2)
-- Assume Coordinates for CS101 classroom (Dummy Coordinates for Kigali: -1.956699, 30.063162)
INSERT INTO `course_groups` (`course_id`, `group_id`, `lecturer_id`, `room_lat`, `room_lng`) VALUES 
((SELECT id FROM courses WHERE code='CS101'), (SELECT id FROM groups WHERE group_code='Group D'), (SELECT id FROM users WHERE email='eric@system.edu'), -1.95669900, 30.06316200);

-- 4. Timetable
INSERT INTO `timetable` (`course_group_id`, `day_of_week`, `start_time`, `end_time`) VALUES 
(1, 'Friday', '08:00:00', '10:00:00');

-- 5. Enroll Student to Course Group (User ID = 3)
INSERT INTO `enrollments` (`student_id`, `course_group_id`) VALUES 
((SELECT id FROM users WHERE email='mihigo@auca.edu.rw'), 1);
