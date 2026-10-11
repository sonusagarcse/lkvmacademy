<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear only student session variables
unset($_SESSION['student_id']);
unset($_SESSION['student_name']);
unset($_SESSION['student_email']);

header("Location: index");
exit();
