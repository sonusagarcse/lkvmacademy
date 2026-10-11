<?php
/**
 * LKVM Courses (LMS) - Dedicated Database Connection & Config
 * Connects to the separate LMS database: lkvm_courses
 */

// Buffer output to avoid "headers already sent" on redirects
if (ob_get_level() === 0) {
    ob_start();
}

// Load security core if available
if (file_exists(dirname(dirname(__DIR__)) . '/includes/security.php')) {
    require_once dirname(dirname(__DIR__)) . '/includes/security.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!function_exists('e')) {
        function e($data) {
            return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
        }
    }
}

// Load .env if present
$rootEnv = dirname(dirname(__DIR__)) . '/.env';
if (file_exists($rootEnv)) {
    $lines = file($rootEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Dedicated LMS Database Credentials
$lmsDbHost = getenv('COURSES_DB_HOST') ?: (getenv('DB_HOST') ?: 'localhost');
$lmsDbUser = getenv('COURSES_DB_USER') ?: (getenv('DB_USER') ?: 'root');
$lmsDbPass = getenv('COURSES_DB_PASS') !== false ? getenv('COURSES_DB_PASS') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$lmsDbName = getenv('COURSES_DB_NAME') ?: 'lkvm_courses';

try {
    // Connect to MySQL server
    $con = mysqli_connect($lmsDbHost, $lmsDbUser, $lmsDbPass);
    if (!$con) {
        throw new Exception("MySQL Server Connection Error: " . mysqli_connect_error());
    }

    // Ensure database exists
    mysqli_query($con, "CREATE DATABASE IF NOT EXISTS `$lmsDbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    mysqli_select_db($con, $lmsDbName);
    mysqli_set_charset($con, 'utf8mb4');

} catch (Exception $e) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px; background:#f8fafc; color:#0f172a;'>
            <h2>LMS Database Connection Error</h2>
            <p>Could not connect to courses database (<strong>$lmsDbName</strong>). Please verify database configuration.</p>
            <small style='color:#64748b;'>" . htmlspecialchars($e->getMessage()) . "</small>
         </div>");
}

/**
 * Auto-migration & Schema Initializer
 * Automatically creates all LMS tables and default seed data if missing
 */
function lms_ensure_schema($con) {
    // Check if lms_courses exists
    $check = mysqli_query($con, "SHOW TABLES LIKE 'lms_courses'");
    if (mysqli_num_rows($check) == 0) {
        $schema = "
        CREATE TABLE IF NOT EXISTS `lms_admins` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(100) NOT NULL,
            `email` varchar(191) NOT NULL,
            `password` varchar(255) NOT NULL,
            `name` varchar(255) NOT NULL,
            `role` varchar(50) DEFAULT 'admin',
            `avatar` varchar(255) DEFAULT NULL,
            `status` tinyint(4) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `lms_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `slug` varchar(255) NOT NULL,
            `icon` varchar(100) DEFAULT 'fa-laptop-code',
            `description` text DEFAULT NULL,
            `status` tinyint(4) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `lms_students` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `email` varchar(191) DEFAULT NULL,
            `password` varchar(255) DEFAULT NULL,
            `phone` varchar(20) DEFAULT NULL,
            `profile_pic` varchar(255) DEFAULT NULL,
            `status` tinyint(4) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `lms_enrollments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `course_id` int(11) NOT NULL,
            `amount` decimal(10,2) DEFAULT 0.00,
            `payment_id` varchar(255) DEFAULT NULL,
            `payment_method` varchar(50) DEFAULT 'online',
            `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `lms_progress` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `lesson_id` int(11) NOT NULL,
            `course_id` int(11) DEFAULT NULL,
            `completed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        CREATE TABLE IF NOT EXISTS `lms_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        mysqli_multi_query($con, $schema);
        while (mysqli_more_results($con) && mysqli_next_result($con)) {;}

        // Seed Default Admin (admin / admin123)
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
        mysqli_query($con, "INSERT IGNORE INTO `lms_admins` (`username`, `email`, `password`, `name`, `role`) 
                            VALUES ('admin', 'admin@lkvm.org', '$defaultPass', 'LMS Administrator', 'superadmin')");

        // Seed Default Settings
        $settings = [
            'site_name' => 'LKVM Academy',
            'site_tagline' => 'Transforming Skills & Careers with Practical Learning',
            'contact_email' => 'info@lkvm.org',
            'contact_phone' => '+91 9801664500',
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'razorpay_key_id' => '',
            'razorpay_key_secret' => '',
            'upi_id' => 'lkvm@upi',
            'upi_name' => 'LKVM Computer Education'
        ];
        foreach ($settings as $k => $v) {
            $k_esc = mysqli_real_escape_string($con, $k);
            $v_esc = mysqli_real_escape_string($con, $v);
            mysqli_query($con, "INSERT IGNORE INTO `lms_settings` (`setting_key`, `setting_value`) VALUES ('$k_esc', '$v_esc')");
        }

        // Seed Sample Categories
        mysqli_query($con, "INSERT IGNORE INTO `lms_categories` (`name`, `slug`, `icon`) VALUES 
            ('Web & Software Development', 'web-development', 'fa-code'),
            ('Basic Computer & Office Applications', 'basic-computer', 'fa-desktop'),
            ('Accounting & Financial Tally', 'accounting-tally', 'fa-file-invoice-dollar'),
            ('Graphic Design & Multimedia', 'graphic-design', 'fa-palette')");

        // Seed Starter Courses
        mysqli_query($con, "INSERT IGNORE INTO `lms_courses` 
            (`title`, `slug`, `short_desc`, `long_desc`, `thumbnail`, `preview_video`, `price`, `discount_price`, `duration`, `level`, `instructor`, `category_id`, `status`, `is_featured`) 
            VALUES 
            ('Certificate in DCA (Diploma in Computer Applications)', 'diploma-in-computer-applications', 
             'Master Fundamental Computer Concepts, MS Office (Word, Excel, PowerPoint), Internet operations, and IT essentials.', 
             'Comprehensive DCA program designed to build strong computing skills for students and job seekers. Covers computer fundamentals, operating systems, full Microsoft Office suite with practical exercises, typing, and everyday business IT tasks.', 
             'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&q=80&w=700', 
             'https://www.youtube.com/watch?v=kqtD5dpn9C8', 
             1499.00, 2499.00, '60', 'Beginner', 'Rakesh Kumar', 2, 1, 1),

            ('Tally Prime & GST Professional Accounting', 'tally-prime-gst-accounting', 
             'Learn professional business accounting, GST billing, inventory management, and taxation in Tally Prime.', 
             'Complete accounting course with real-world case studies. Learn ledger management, voucher entry, inventory, payroll, GST reports, e-way bill generation, and financial reporting for businesses.', 
             'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&q=80&w=700', 
             'https://www.youtube.com/watch?v=d_H3c43XQEI', 
             1999.00, 3499.00, '45', 'Intermediate', 'Amit Sharma', 3, 1, 1),

            ('Full Stack Web Development (HTML, CSS, JS, PHP)', 'full-stack-web-development', 
             'Build dynamic, production-ready web apps from scratch using modern frontend and backend technologies.', 
             'Hands-on full stack web development roadmap. Learn HTML5, CSS3, JavaScript, PHP, MySQL, REST APIs, and how to build, test, and deploy secure web applications.', 
             'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=700', 
             'https://www.youtube.com/watch?v=zJSY8tbf_ys', 
             2999.00, 4999.00, '75', 'All Levels', 'Sonu Sagar', 1, 1, 1)
        ");

        // Seed Sample Lessons for DCA
        $dca_id = 1;
        mysqli_query($con, "INSERT IGNORE INTO `lms_lessons` 
            (`course_id`, `title`, `description`, `content_type`, `content_url`, `duration`, `is_preview`, `sort_order`) 
            VALUES 
            ($dca_id, '1. Introduction to Computer Architecture', 'Overview of hardware, CPU, memory, and peripheral devices.', 'youtube', 'https://www.youtube.com/watch?v=kqtD5dpn9C8', '18 mins', 1, 1),
            ($dca_id, '2. Windows Operating System & File Management', 'Understanding files, folders, shortcuts, and settings.', 'youtube', 'https://www.youtube.com/watch?v=VznoKpPzOeo', '22 mins', 0, 2),
            ($dca_id, '3. Microsoft Word Mastery: Formatting & Documents', 'Creating official letters, reports, tables, and styles.', 'youtube', 'https://www.youtube.com/watch?v=fUkh3RerndQ', '35 mins', 0, 3),
            ($dca_id, '4. Microsoft Excel Formulas & Practical Sheets', 'SUM, VLOOKUP, IF conditions, and data analysis formulas.', 'youtube', 'https://www.youtube.com/watch?v=rwbho0CgEAE', '40 mins', 0, 4)
        ");
    }
}

// Ensure schema is up to date
lms_ensure_schema($con);

// Ensure certificate system tables
if (file_exists(__DIR__ . '/certificate_helper.php')) {
    require_once __DIR__ . '/certificate_helper.php';
    lms_ensure_certificates_table($con);
}

// Also check and add missing columns/tables if schema was partially created earlier
$checkProgress = mysqli_query($con, "SHOW TABLES LIKE 'lms_progress'");
if (mysqli_num_rows($checkProgress) == 0) {
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `lms_progress` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `lesson_id` int(11) NOT NULL,
        `course_id` int(11) DEFAULT NULL,
        `completed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_lesson` (`user_id`, `lesson_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$checkAdmins = mysqli_query($con, "SHOW TABLES LIKE 'lms_admins'");
if (mysqli_num_rows($checkAdmins) == 0) {
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `lms_admins` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(100) NOT NULL,
        `email` varchar(191) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `role` varchar(50) DEFAULT 'admin',
        `avatar` varchar(255) DEFAULT NULL,
        `status` tinyint(4) DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);
    mysqli_query($con, "INSERT IGNORE INTO `lms_admins` (`username`, `email`, `password`, `name`, `role`) 
                        VALUES ('admin', 'admin@lkvm.org', '$defaultPass', 'LMS Administrator', 'superadmin')");
}

$checkSettings = mysqli_query($con, "SHOW TABLES LIKE 'lms_settings'");
if (mysqli_num_rows($checkSettings) == 0) {
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS `lms_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Helper: Get LMS setting
function lms_get_setting($con, $key, $default = '') {
    $key_esc = mysqli_real_escape_string($con, $key);
    $res = mysqli_query($con, "SELECT setting_value FROM lms_settings WHERE setting_key = '$key_esc' LIMIT 1");
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row['setting_value'] !== null ? $row['setting_value'] : $default;
    }
    return $default;
}

// Helper: Set LMS setting
function lms_set_setting($con, $key, $value) {
    $key_esc = mysqli_real_escape_string($con, $key);
    $val_esc = mysqli_real_escape_string($con, $value);
    return mysqli_query($con, "INSERT INTO lms_settings (setting_key, setting_value) 
                               VALUES ('$key_esc', '$val_esc') 
                               ON DUPLICATE KEY UPDATE setting_value = '$val_esc'");
}

// Base URL helper
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$coursesBaseUrl = $protocol . $host . '/lkvm/courses';
// If domain is directly lkvmbihar.in or similar without /lkvm/ folder
// Safe redirect helper that works even if HTML has begun rendering
function lms_redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo "<script>window.location.href=" . json_encode($url) . ";</script>";
    }
    exit();
}
