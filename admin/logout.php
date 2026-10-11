<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset only LMS admin session variables
unset($_SESSION['lms_admin_id']);
unset($_SESSION['lms_admin_name']);
unset($_SESSION['lms_admin_email']);
unset($_SESSION['lms_admin_role']);
unset($_SESSION['lms_admin_username']);

header("Location: login");
exit();
