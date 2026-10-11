<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['student_id'])) {
    header("Location: login");
    exit();
}

$user_id = $_SESSION['student_id'];
$course_id = intval($_GET['course_id'] ?? 0);
$payment_id = trim($_GET['payment_id'] ?? '');

if ($course_id > 0 && !empty($payment_id)) {
    // Fetch course details
    $course_q = mysqli_query($con, "SELECT slug, price FROM lms_courses WHERE id = $course_id");
    if ($course_q && $course = mysqli_fetch_assoc($course_q)) {
        $amount = $course['price'] ?? 0.00;

        // Check if already enrolled
        $check = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $user_id AND course_id = $course_id");
        if (mysqli_num_rows($check) == 0) {
            $stmt = $con->prepare("INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) VALUES (?, ?, ?, ?, 'razorpay', 'completed')");
            if ($stmt) {
                $stmt->bind_param("iids", $user_id, $course_id, $amount, $payment_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        header("Location: learn?slug=" . urlencode($course['slug']) . "&enrolled=success");
        exit();
    }
}

header("Location: dashboard");
exit();
