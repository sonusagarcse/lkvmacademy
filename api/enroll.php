<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = get_request_data();
$user = get_auth_user($con);

if (!$user || $user['user_type'] !== 'student') {
    // If not authenticated via token/session, check if user_id passed
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
    json_res(false, 'Unauthorized. Please login as a student to access enrollments.', null, 401);
}

$userId = intval($user['id']);

// ==========================================
// 1. POST: PURCHASE / ENROLL IN COURSE
// ==========================================
if ($method === 'POST') {
    $courseId = intval($data['course_id'] ?? 0);
    if ($courseId <= 0) {
        json_res(false, 'Valid Course ID is required.', null, 400);
    }

    // Verify course exists
    $cQ = mysqli_query($con, "SELECT * FROM lms_courses WHERE id = $courseId AND status = 1");
    if (!$cQ || mysqli_num_rows($cQ) == 0) {
        json_res(false, 'Course not found or inactive.', null, 404);
    }
    $course = mysqli_fetch_assoc($cQ);

    // Check if already enrolled
    $chk = mysqli_query($con, "SELECT id, status FROM lms_enrollments WHERE user_id = $userId AND course_id = $courseId AND status = 'completed'");
    if (mysqli_num_rows($chk) > 0) {
        json_res(true, 'You are already enrolled in this course!', [
            'already_enrolled' => true,
            'course_id' => $courseId,
            'slug' => $course['slug']
        ]);
    }

    $paymentMethod = trim($data['payment_method'] ?? ($course['is_free'] == 1 ? 'free' : 'razorpay'));
    $paymentId = trim($data['payment_id'] ?? ($data['utr_number'] ?? ''));

    if (empty($paymentId)) {
        if ($course['is_free'] == 1 || $paymentMethod === 'free') {
            $paymentId = 'FREE_' . time() . '_' . rand(1000, 9999);
        } else {
            $paymentId = 'PAY_' . strtoupper(substr(md5(uniqid()), 0, 10));
        }
    }

    $amount = ($course['is_free'] == 1 || $paymentMethod === 'free') ? 0.00 : floatval($course['discount_price'] ?: $course['price']);

    $stmt = $con->prepare("INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) VALUES (?, ?, ?, ?, ?, 'completed')");
    if ($stmt) {
        $stmt->bind_param("iidss", $userId, $courseId, $amount, $paymentId, $paymentMethod);
        if ($stmt->execute()) {
            $enrollmentId = $stmt->insert_id;
            $stmt->close();

            // Dispatch Course Purchase / Enrollment Confirmation Email
            if (!empty($user['email'])) {
                lms_send_enrollment_email($con, $user['email'], $user['name'], $course['title'], $amount, $paymentId, $course['slug']);
            }

            json_res(true, 'Enrollment successful! Welcome to the course.', [
                'enrollment_id' => $enrollmentId,
                'course_id' => $courseId,
                'course_title' => $course['title'],
                'slug' => $course['slug'],
                'amount_paid' => $amount,
                'payment_id' => $paymentId,
                'payment_method' => $paymentMethod
            ], 201);
        } else {
            json_res(false, 'Failed to complete enrollment.', null, 500);
        }
    } else {
        json_res(false, 'Database preparation error.', null, 500);
    }
}

// ==========================================
// 2. GET: LIST STUDENT'S ENROLLED COURSES
// ==========================================
$query = "SELECT e.id as enrollment_id, e.amount as paid_amount, e.payment_method, e.created_at as enrolled_at,
                 c.id as course_id, c.title, c.slug, c.thumbnail, c.instructor, c.level, c.duration,
                 (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as total_lessons,
                 (SELECT COUNT(*) FROM lms_progress WHERE user_id = $userId AND course_id = c.id) as completed_lessons
          FROM lms_enrollments e
          JOIN lms_courses c ON e.course_id = c.id
          WHERE e.user_id = $userId AND e.status = 'completed'
          ORDER BY e.id DESC";

$res = mysqli_query($con, $query);
$enrolled = [];

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $total = intval($row['total_lessons']);
        $completed = intval($row['completed_lessons']);
        $percent = $total > 0 ? min(100, round(($completed / $total) * 100)) : 0;

        $row['total_lessons'] = $total;
        $row['completed_lessons'] = $completed;
        $row['progress_percent'] = $percent;
        $row['is_completed'] = ($percent >= 100);
        $enrolled[] = $row;
    }
}

json_res(true, 'Enrolled courses retrieved', [
    'count' => count($enrolled),
    'courses' => $enrolled
]);
