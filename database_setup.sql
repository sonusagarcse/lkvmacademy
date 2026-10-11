-- Dedicated LMS Courses Database Schema Export
-- Database: `lkvm_courses`
-- Fully independent from root school database

CREATE DATABASE IF NOT EXISTS `lkvm_courses` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lkvm_courses`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Table: lms_admins
CREATE TABLE IF NOT EXISTS `lms_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `avatar` varchar(255) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_categories
CREATE TABLE IF NOT EXISTS `lms_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `icon` varchar(100) DEFAULT 'fa-laptop-code',
  `description` text DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_courses
CREATE TABLE IF NOT EXISTS `lms_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `short_desc` text DEFAULT NULL,
  `long_desc` longtext DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `preview_video` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_free` tinyint(1) DEFAULT 0,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `level` varchar(50) DEFAULT 'All Levels',
  `instructor` varchar(255) DEFAULT 'LKVM Faculty',
  `category_id` int(11) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_lessons
CREATE TABLE IF NOT EXISTS `lms_lessons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content_type` enum('video','pdf','text','youtube') DEFAULT 'youtube',
  `content_url` text DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `is_preview` tinyint(4) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_students
CREATE TABLE IF NOT EXISTS `lms_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 1,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_certificates
CREATE TABLE IF NOT EXISTS `lms_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_uid` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `student_email` varchar(255) DEFAULT NULL,
  `course_title` varchar(255) NOT NULL,
  `instructor_name` varchar(255) DEFAULT NULL,
  `certificate_template` varchar(50) NOT NULL DEFAULT 'classic_gold',
  `issue_date` date NOT NULL,
  `completion_percent` decimal(5,2) DEFAULT 100.00,
  `status` enum('active','revoked','expired') DEFAULT 'active',
  `revoked_reason` text DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_uid` (`certificate_uid`),
  UNIQUE KEY `unique_user_course` (`user_id`, `course_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_enrollments
CREATE TABLE IF NOT EXISTS `lms_enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) DEFAULT 0.00,
  `payment_id` varchar(255) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'online',
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_progress
CREATE TABLE IF NOT EXISTS `lms_progress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `lesson_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: lms_settings
CREATE TABLE IF NOT EXISTS `lms_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Default Admin: Username `admin`, Password `admin123`
INSERT IGNORE INTO `lms_admins` (`username`, `email`, `password`, `name`, `role`) VALUES
('admin', 'admin@lkvm.org', '$2y$10$w3U6sY0Hq68bQkY6j1fO.ep8p8M7bF7kK9U7m5ZfP6QdJ4o0e5e.W', 'LMS Administrator', 'superadmin');

-- Seed Default Settings
INSERT IGNORE INTO `lms_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'LKVM Academy'),
('site_tagline', 'Transforming Skills & Careers with Practical Learning'),
('contact_email', 'info@lkvm.org'),
('contact_phone', '+91 9801664500'),
('currency_symbol', '₹'),
('currency_code', 'INR'),
('smtp_enabled', '0'),
('smtp_host', 'smtp.gmail.com'),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_encryption', 'tls'),
('smtp_from_email', 'info@lkvmbihar.in'),
('smtp_from_name', 'LKVM Academy'),
('enable_upi', '1'),
('enable_razorpay', '1'),
('enable_card', '0'),
('upi_id', 'lkvm@upi'),
('upi_name', 'LKVM Computer Education');

COMMIT;
