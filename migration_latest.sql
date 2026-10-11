-- ==========================================================
-- LKVM LMS - Complete Incremental Database Migration Script
-- Safe to execute on both Localhost and Production cPanel DB
-- ==========================================================

-- 1. ADD OTP & EMAIL VERIFICATION COLUMNS TO lms_students
ALTER TABLE `lms_students` 
  ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`,
  ADD COLUMN IF NOT EXISTS `otp_code` VARCHAR(10) DEFAULT NULL AFTER `email_verified`,
  ADD COLUMN IF NOT EXISTS `otp_expires_at` DATETIME DEFAULT NULL AFTER `otp_code`;

-- 2. CREATE CERTIFICATES TABLE (IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS `lms_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `certificate_uid` varchar(64) NOT NULL COMMENT 'Unique public certificate ID for verification',
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

-- 3. ADD CERTIFICATE COLUMNS TO lms_courses (IF NOT EXISTS)
ALTER TABLE `lms_courses` 
  ADD COLUMN IF NOT EXISTS `has_certificate` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_featured`,
  ADD COLUMN IF NOT EXISTS `certificate_title` VARCHAR(255) DEFAULT NULL AFTER `has_certificate`,
  ADD COLUMN IF NOT EXISTS `certificate_signatory_name` VARCHAR(255) DEFAULT NULL AFTER `certificate_title`,
  ADD COLUMN IF NOT EXISTS `certificate_signatory_title` VARCHAR(255) DEFAULT NULL AFTER `certificate_signatory_name`,
  ADD COLUMN IF NOT EXISTS `certificate_template` VARCHAR(50) NOT NULL DEFAULT 'classic_gold' AFTER `certificate_signatory_title`;

-- 4. INSERT DEFAULT SMTP & GATEWAY SETTINGS (INSERT IGNORE TO NOT OVERWRITE EXISTING CONFIG)
INSERT IGNORE INTO `lms_settings` (`setting_key`, `setting_value`) VALUES
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
