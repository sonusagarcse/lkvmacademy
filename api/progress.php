<?php
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/certificate_helper.php';

$data = get_request_data();
$user = get_auth_user($con);

if (!$user || $user['user_type'] !== 'student') {
    $uid = intval($data['user_id'] ?? ($_GET['user_id'] ?? 0));
    if ($uid > 0) {
        $q = mysqli_query($con, "SELECT id, name, email FROM lms_students WHERE id = $uid AND status = 1");
        if ($q && $found = mysqli_fetch_assoc($q)) {
            $user = $found;
            $user['user_type'] = 'student';
        }
    }
}

if (!$user) {
    json_res(false, 'Unauthorized student request.', null, 401);
}

$userId = intval($user['id']);
$lessonId = intval($data['lesson_id'] ?? 0);
$courseId = intval($data['course_id'] ?? 0);

if ($lessonId <= 0) {
    json_res(false, 'Valid lesson_id is required.', null, 400);
}

// If course_id is not passed, find from lms_lessons
if ($courseId <= 0) {
    $lQ = mysqli_query($con, "SELECT course_id FROM lms_lessons WHERE id = $lessonId");
    if ($lQ && $lr = mysqli_fetch_assoc($lQ)) {
        $courseId = intval($lr['course_id']);
    }
}

$isCompleted = isset($data['completed']) ? (!empty($data['completed'])) : true;

if ($isCompleted) {
    // Insert if not exists
    $stmt = $con->prepare("INSERT IGNORE INTO lms_progress (user_id, lesson_id, course_id) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iii", $userId, $lessonId, $courseId);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Unmark
    $stmt = $con->prepare("DELETE FROM lms_progress WHERE user_id = ? AND lesson_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $userId, $lessonId);
        $stmt->execute();
        $stmt->close();
    }
}

// Calculate updated progress
$totQ = mysqli_query($con, "SELECT COUNT(*) as c FROM lms_lessons WHERE course_id = $courseId");
$totalLessons = ($totQ && $tr = mysqli_fetch_assoc($totQ)) ? intval($tr['c']) : 0;

$doneQ = mysqli_query($con, "SELECT COUNT(*) as c FROM lms_progress WHERE user_id = $userId AND course_id = $courseId");
$doneLessons = ($doneQ && $dr = mysqli_fetch_assoc($doneQ)) ? intval($dr['c']) : 0;

$percent = $totalLessons > 0 ? min(100, round(($doneLessons / $totalLessons) * 100)) : 0;
$certCode = null;

if ($percent >= 100) {
    // Issue or get certificate
    lms_ensure_certificates_table($con);
    $certCode = lms_award_certificate_if_eligible($con, $userId, $courseId);
}

json_res(true, $isCompleted ? 'Lesson marked as completed!' : 'Lesson unmarked.', [
    'lesson_id' => $lessonId,
    'course_id' => $courseId,
    'is_completed' => $isCompleted,
    'completed_lessons' => $doneLessons,
    'total_lessons' => $totalLessons,
    'progress_percent' => $percent,
    'certificate_awarded' => ($percent >= 100),
    'certificate_code' => $certCode
]);
