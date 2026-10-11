<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/certificate_helper.php';

$user = get_auth_user($con);
$type = $_GET['type'] ?? ($user ? $user['user_type'] : 'student');
$uid = $user ? intval($user['id']) : intval($_GET['user_id'] ?? 0);

// Admin / Instructor Stats
if ($type === 'admin' || $type === 'instructor') {
    $totCourses = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_courses"))['c'] ?? 0;
    $totStudents = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_students"))['c'] ?? 0;
    $totEnrollments = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_enrollments WHERE status = 'completed'"))['c'] ?? 0;
    $totRevenue = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(SUM(amount), 0) as s FROM lms_enrollments WHERE status = 'completed'"))['s'] ?? 0;

    $recentEnrollmentsQ = mysqli_query($con, "
        SELECT e.*, c.title as course_title, s.name as student_name, s.email as student_email
        FROM lms_enrollments e
        JOIN lms_courses c ON e.course_id = c.id
        JOIN lms_students s ON e.user_id = s.id
        ORDER BY e.id DESC LIMIT 10
    ");
    $recent = [];
    if ($recentEnrollmentsQ) {
        while ($r = mysqli_fetch_assoc($recentEnrollmentsQ)) {
            $recent[] = $r;
        }
    }

    json_res(true, 'Instructor dashboard metrics', [
        'total_courses' => intval($totCourses),
        'total_students' => intval($totStudents),
        'total_enrollments' => intval($totEnrollments),
        'total_revenue' => floatval($totRevenue),
        'recent_enrollments' => $recent
    ]);
}

// Student Stats
lms_ensure_certificates_table($con);

$enrolledCount = 0;
$completedCount = 0;
$inProgressCount = 0;
$certificatesCount = 0;

if ($uid > 0) {
    $eQ = mysqli_query($con, "SELECT course_id FROM lms_enrollments WHERE user_id = $uid AND status = 'completed'");
    if ($eQ) {
        while ($er = mysqli_fetch_assoc($eQ)) {
            $enrolledCount++;
            $cid = intval($er['course_id']);
            $totL = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_lessons WHERE course_id = $cid"))['c'] ?? 0;
            $donL = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as c FROM lms_progress WHERE user_id = $uid AND course_id = $cid"))['c'] ?? 0;
            if ($totL > 0 && $donL >= $totL) {
                $completedCount++;
            } else {
                $inProgressCount++;
            }
        }
    }

    $cQ = mysqli_query($con, "SELECT COUNT(*) as c FROM lms_certificates WHERE user_id = $uid");
    $certificatesCount = ($cQ && $cr = mysqli_fetch_assoc($cQ)) ? intval($cr['c']) : 0;
}

json_res(true, 'Student dashboard metrics', [
    'enrolled_courses' => $enrolledCount,
    'completed_courses' => $completedCount,
    'in_progress_courses' => $inProgressCount,
    'certificates_earned' => $certificatesCount
]);
