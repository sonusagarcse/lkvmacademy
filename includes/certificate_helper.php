<?php
/**
 * LKVM LMS - Certificate System Helper
 * Industry-grade certificate issuance, verification, and management.
 * 
 * All certificate logic is centralized here for consistency and testability.
 */

/**
 * Ensure the lms_certificates table exists in the database.
 * Safe to call multiple times (idempotent).
 */
if (!function_exists('lms_ensure_certificates_table')) {
function lms_ensure_certificates_table($con) {
    $check = mysqli_query($con, "SHOW TABLES LIKE 'lms_certificates'");
    if (mysqli_num_rows($check) == 0) {
        $sql = "CREATE TABLE `lms_certificates` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `certificate_uid` varchar(64) NOT NULL COMMENT 'Unique public certificate ID for verification',
            `user_id` int(11) NOT NULL,
            `course_id` int(11) NOT NULL,
            `student_name` varchar(255) NOT NULL,
            `student_email` varchar(255) DEFAULT NULL,
            `course_title` varchar(255) NOT NULL,
            `instructor_name` varchar(255) DEFAULT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        mysqli_query($con, $sql);
    }

    // Ensure certificate-related columns exist in lms_courses
    $course_cols = mysqli_query($con, "SHOW COLUMNS FROM lms_courses LIKE 'has_certificate'");
    if (mysqli_num_rows($course_cols) == 0) {
        mysqli_query($con, "ALTER TABLE lms_courses 
            ADD COLUMN `has_certificate` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_featured`,
            ADD COLUMN `certificate_title` VARCHAR(255) DEFAULT NULL AFTER `has_certificate`,
            ADD COLUMN `certificate_signatory_name` VARCHAR(255) DEFAULT NULL AFTER `certificate_title`,
            ADD COLUMN `certificate_signatory_title` VARCHAR(255) DEFAULT NULL AFTER `certificate_signatory_name`,
            ADD COLUMN `certificate_template` VARCHAR(50) NOT NULL DEFAULT 'classic_gold' AFTER `certificate_signatory_title`
        ");
    }

    $c_tpl = mysqli_query($con, "SHOW COLUMNS FROM lms_courses LIKE 'certificate_template'");
    if (mysqli_num_rows($c_tpl) == 0) {
        mysqli_query($con, "ALTER TABLE lms_courses ADD COLUMN `certificate_template` VARCHAR(50) NOT NULL DEFAULT 'classic_gold' AFTER `certificate_signatory_title`");
    }

    $cert_tpl = mysqli_query($con, "SHOW COLUMNS FROM lms_certificates LIKE 'certificate_template'");
    if (mysqli_num_rows($cert_tpl) == 0) {
        mysqli_query($con, "ALTER TABLE lms_certificates ADD COLUMN `certificate_template` VARCHAR(50) NOT NULL DEFAULT 'classic_gold' AFTER `instructor_name`");
    }

    return true;
}
}

if (!function_exists('lms_get_certificate_templates')) {
function lms_get_certificate_templates() {
    return [
        'classic_gold' => [
            'id' => 'classic_gold',
            'name' => 'Classic Gold Luxury',
            'badge' => 'Traditional & Prestigious',
            'desc' => 'Timeless ivy filigree gold border, royal crest, vintage serif typography & parchment tint.',
            'primary_color' => '#c9a84c',
            'secondary_color' => '#1e3a5f',
            'bg_gradient' => 'linear-gradient(180deg, #fffef5 0%, #ffffff 30%, #ffffff 70%, #fffef5 100%)',
            'seal_color' => 'linear-gradient(135deg, #c9a84c, #a17d2e)',
            'icon' => 'fa-crown'
        ],
        'modern_blue' => [
            'id' => 'modern_blue',
            'name' => 'Modern Royal Blue',
            'badge' => 'High-Tech & Contemporary',
            'desc' => 'Geometric technical border, cobalt & cyan gradient accents, sleek Plus Jakarta typography.',
            'primary_color' => '#0056D2',
            'secondary_color' => '#0f172a',
            'bg_gradient' => 'linear-gradient(180deg, #f0f7ff 0%, #ffffff 35%, #ffffff 70%, #f0f7ff 100%)',
            'seal_color' => 'linear-gradient(135deg, #0056D2, #0284c7)',
            'icon' => 'fa-certificate'
        ],
        'emerald_prestige' => [
            'id' => 'emerald_prestige',
            'name' => 'Emerald Prestige',
            'badge' => 'Executive & Corporate',
            'desc' => 'Rich dark emerald filigree border, platinum medal, corporate luxury serif layout.',
            'primary_color' => '#059669',
            'secondary_color' => '#064e3b',
            'bg_gradient' => 'linear-gradient(180deg, #f0fdf4 0%, #ffffff 35%, #ffffff 70%, #f0fdf4 100%)',
            'seal_color' => 'linear-gradient(135deg, #059669, #047857)',
            'icon' => 'fa-shield-halved'
        ],
        'academic_crimson' => [
            'id' => 'academic_crimson',
            'name' => 'Academic Crimson Crest',
            'badge' => 'University & Diploma',
            'desc' => 'University-style heraldic crimson border, dual gold pinstripes, classic collegiate look.',
            'primary_color' => '#991b1b',
            'secondary_color' => '#7f1d1d',
            'bg_gradient' => 'linear-gradient(180deg, #fef2f2 0%, #ffffff 35%, #ffffff 70%, #fef2f2 100%)',
            'seal_color' => 'linear-gradient(135deg, #991b1b, #c2410c)',
            'icon' => 'fa-graduation-cap'
        ],
        'minimal_dark' => [
            'id' => 'minimal_dark',
            'name' => 'Minimalist Executive',
            'badge' => 'Clean & Modernist',
            'desc' => 'Sharp charcoal dual borders, minimalist layout, elegant monochrome luxury vibe.',
            'primary_color' => '#0f172a',
            'secondary_color' => '#334155',
            'bg_gradient' => 'linear-gradient(180deg, #f8fafc 0%, #ffffff 35%, #ffffff 70%, #f8fafc 100%)',
            'seal_color' => 'linear-gradient(135deg, #0f172a, #334155)',
            'icon' => 'fa-award'
        ],
        'custom_bg' => [
            'id' => 'custom_bg',
            'name' => 'Custom Uploaded Template',
            'badge' => 'Manual Canvas Upload',
            'desc' => 'Your own uploaded certificate artwork/border frame with dynamic text, signatures & verification QR code overlays.',
            'primary_color' => '#4f46e5',
            'secondary_color' => '#1e1b4b',
            'bg_gradient' => 'linear-gradient(180deg, #f5f3ff 0%, #ffffff 35%, #ffffff 70%, #f5f3ff 100%)',
            'seal_color' => 'linear-gradient(135deg, #4f46e5, #4338ca)',
            'icon' => 'fa-file-image'
        ]
    ];
}
}

/**
 * Generate a unique, human-readable certificate UID.
 * Format: LKVM-YYYYMM-XXXXX (e.g., LKVM-202610-A7K3F)
 */
if (!function_exists('lms_generate_certificate_uid')) {
function lms_generate_certificate_uid($con) {
    $prefix = 'LKVM';
    $date_part = date('Ym');
    $max_attempts = 10;
    
    for ($i = 0; $i < $max_attempts; $i++) {
        // Generate 5-char alphanumeric (uppercase, no confusing chars like 0/O, 1/I/L)
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $random = '';
        for ($j = 0; $j < 5; $j++) {
            $random .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $uid = "{$prefix}-{$date_part}-{$random}";
        
        // Check uniqueness
        $stmt = $con->prepare("SELECT id FROM lms_certificates WHERE certificate_uid = ?");
        $stmt->bind_param("s", $uid);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows == 0) {
            return $uid;
        }
    }
    
    // Fallback: use timestamp-based UID
    return "{$prefix}-{$date_part}-" . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));
}
}

/**
 * Check if a student has completed 100% of a course.
 * Returns ['completed' => bool, 'total' => int, 'done' => int, 'percent' => float]
 */
if (!function_exists('lms_check_course_completion')) {
function lms_check_course_completion($con, $user_id, $course_id) {
    $user_id = intval($user_id);
    $course_id = intval($course_id);
    
    // Total lessons in course
    $total_res = mysqli_query($con, "SELECT COUNT(*) as total FROM lms_lessons WHERE course_id = $course_id");
    $total = intval(mysqli_fetch_assoc($total_res)['total'] ?? 0);
    
    if ($total == 0) {
        return ['completed' => false, 'total' => 0, 'done' => 0, 'percent' => 0];
    }
    
    // Completed lessons by this student
    $done_res = mysqli_query($con, "
        SELECT COUNT(*) as done 
        FROM lms_progress p 
        JOIN lms_lessons l ON p.lesson_id = l.id 
        WHERE p.user_id = $user_id AND l.course_id = $course_id
    ");
    $done = intval(mysqli_fetch_assoc($done_res)['done'] ?? 0);
    
    $percent = round(($done / $total) * 100, 2);
    
    return [
        'completed' => ($done >= $total),
        'total' => $total,
        'done' => $done,
        'percent' => $percent
    ];
}
}

/**
 * Check if a certificate already exists for user + course.
 * Returns the certificate row or null.
 */
if (!function_exists('lms_get_certificate')) {
function lms_get_certificate($con, $user_id, $course_id) {
    $stmt = $con->prepare("SELECT * FROM lms_certificates WHERE user_id = ? AND course_id = ? LIMIT 1");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cert = $result->fetch_assoc();
    $stmt->close();
    return $cert;
}
}

/**
 * Fetch certificate by its unique public ID.
 */
if (!function_exists('lms_get_certificate_by_uid')) {
function lms_get_certificate_by_uid($con, $uid) {
    $stmt = $con->prepare("SELECT * FROM lms_certificates WHERE certificate_uid = ? LIMIT 1");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $cert = $result->fetch_assoc();
    $stmt->close();
    return $cert;
}
}

/**
 * Issue a certificate for a student upon course completion.
 * Returns the certificate row on success, or null if already exists or not eligible.
 */
if (!function_exists('lms_issue_certificate')) {
function lms_issue_certificate($con, $user_id, $course_id) {
    $user_id = intval($user_id);
    $course_id = intval($course_id);
    
    // Ensure table exists
    lms_ensure_certificates_table($con);
    
    // Check if already issued
    $existing = lms_get_certificate($con, $user_id, $course_id);
    if ($existing) {
        return $existing; // Already issued
    }
    
    // Verify completion
    $completion = lms_check_course_completion($con, $user_id, $course_id);
    if (!$completion['completed']) {
        return null; // Not eligible
    }
    
    // Verify enrollment
    $enroll_check = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $user_id AND course_id = $course_id AND status = 'completed'");
    if (mysqli_num_rows($enroll_check) == 0) {
        return null; // Not enrolled
    }
    
    // Fetch student info
    $student_res = mysqli_query($con, "SELECT name, email FROM lms_students WHERE id = $user_id LIMIT 1");
    $student = mysqli_fetch_assoc($student_res);
    if (!$student) return null;
    
    // Fetch course info
    $course_res = mysqli_query($con, "SELECT title, instructor, has_certificate, certificate_title, certificate_signatory_name, certificate_template FROM lms_courses WHERE id = $course_id LIMIT 1");
    $course = mysqli_fetch_assoc($course_res);
    if (!$course) return null;

    // If certificates are disabled for this course, do not issue
    if (isset($course['has_certificate']) && (int)$course['has_certificate'] === 0) {
        return null;
    }
    
    // Determine certificate title, instructor/signatory name, and template
    $cert_title = !empty($course['certificate_title']) ? $course['certificate_title'] : $course['title'];
    $cert_instructor = !empty($course['certificate_signatory_name']) ? $course['certificate_signatory_name'] : ($course['instructor'] ?? 'LKVM Faculty');
    $cert_template = !empty($course['certificate_template']) ? $course['certificate_template'] : 'classic_gold';
    
    // Generate unique certificate ID
    $cert_uid = lms_generate_certificate_uid($con);
    $issue_date = date('Y-m-d');
    
    $stmt = $con->prepare("INSERT INTO lms_certificates 
        (certificate_uid, user_id, course_id, student_name, student_email, course_title, instructor_name, certificate_template, issue_date, completion_percent) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $percent = $completion['percent'];
    $stmt->bind_param("siissssssd", 
        $cert_uid, $user_id, $course_id, 
        $student['name'], $student['email'], 
        $cert_title, $cert_instructor, $cert_template,
        $issue_date, $percent
    );
    
    if ($stmt->execute()) {
        $cert_id = $stmt->insert_id;
        $stmt->close();
        return lms_get_certificate($con, $user_id, $course_id);
    }
    
    $stmt->close();
    return null;
}
}

/**
 * Try to auto-issue certificate when a lesson is marked complete.
 * Call this after every progress update.
 * Returns the certificate if newly issued, null otherwise.
 */
if (!function_exists('lms_try_auto_issue_certificate')) {
function lms_try_auto_issue_certificate($con, $user_id, $course_id) {
    lms_ensure_certificates_table($con);
    
    $completion = lms_check_course_completion($con, $user_id, $course_id);
    if ($completion['completed']) {
        return lms_issue_certificate($con, $user_id, $course_id);
    }
    return null;
}
}

/**
 * Get all certificates for a given student.
 */
if (!function_exists('lms_get_student_certificates')) {
function lms_get_student_certificates($con, $user_id) {
    $user_id = intval($user_id);
    lms_ensure_certificates_table($con);
    
    $res = mysqli_query($con, "
        SELECT c.*, co.slug as course_slug, co.thumbnail as course_thumbnail, co.duration as course_duration
        FROM lms_certificates c
        LEFT JOIN lms_courses co ON c.course_id = co.id
        WHERE c.user_id = $user_id
        ORDER BY c.issue_date DESC
    ");
    
    $certs = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $certs[] = $row;
    }
    return $certs;
}
}

/**
 * Revoke a certificate by ID.
 */
if (!function_exists('lms_revoke_certificate')) {
function lms_revoke_certificate($con, $cert_id, $reason = '') {
    $cert_id = intval($cert_id);
    $reason_esc = mysqli_real_escape_string($con, $reason);
    return mysqli_query($con, "UPDATE lms_certificates SET status = 'revoked', revoked_reason = '$reason_esc', revoked_at = NOW() WHERE id = $cert_id");
}
}

/**
 * Reactivate a revoked certificate.
 */
if (!function_exists('lms_reactivate_certificate')) {
function lms_reactivate_certificate($con, $cert_id) {
    $cert_id = intval($cert_id);
    return mysqli_query($con, "UPDATE lms_certificates SET status = 'active', revoked_reason = NULL, revoked_at = NULL WHERE id = $cert_id");
}
}

/**
 * Get certificate statistics for admin dashboard.
 */
if (!function_exists('lms_certificate_stats')) {
function lms_certificate_stats($con) {
    lms_ensure_certificates_table($con);
    
    $total = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_certificates"))['c'] ?? 0;
    $active = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_certificates WHERE status = 'active'"))['c'] ?? 0;
    $revoked = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_certificates WHERE status = 'revoked'"))['c'] ?? 0;
    $this_month = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_certificates WHERE MONTH(issue_date) = MONTH(CURRENT_DATE()) AND YEAR(issue_date) = YEAR(CURRENT_DATE())"))['c'] ?? 0;
    
    return [
        'total' => $total,
        'active' => $active,
        'revoked' => $revoked,
        'this_month' => $this_month
    ];
}
}
