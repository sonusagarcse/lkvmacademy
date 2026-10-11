<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$redirect = trim($_GET['redirect'] ?? 'dashboard');

if (isset($_SESSION['student_id'])) {
    header("Location: $redirect");
    exit();
}

$error = "";
if (isset($_POST['student_login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $con->prepare("SELECT * FROM lms_students WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $user = $res->fetch_assoc();
                if ($user['status'] == 0) {
                    $error = "Your account has been deactivated. Please contact the academy.";
                } elseif (password_verify($password, $user['password'])) {
                    $smtp_config = lms_get_smtp_config($con);
                    if ($smtp_config['enabled'] && isset($user['email_verified']) && $user['email_verified'] == 0) {
                        $otp = strval(rand(100000, 999999));
                        $exp = date('Y-m-d H:i:s', time() + 900);
                        mysqli_query($con, "UPDATE lms_students SET otp_code = '$otp', otp_expires_at = '$exp' WHERE id = {$user['id']}");
                        lms_send_otp_email($con, $user['email'], $user['name'], $otp);

                        $_SESSION['pending_otp_email'] = $user['email'];
                        $_SESSION['pending_otp_redirect'] = $redirect;
                        header("Location: verify-otp.php?email=" . urlencode($user['email']));
                        exit();
                    }

                    $_SESSION['student_id'] = $user['id'];
                    $_SESSION['student_name'] = $user['name'];
                    $_SESSION['student_email'] = $user['email'];

                    header("Location: $redirect");
                    exit();
                } else {
                    $error = "Invalid password. Please check your credentials.";
                }
            } else {
                $error = "No student account found with this email.";
            }
            $stmt->close();
        }
    }
}

$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Sign In - <?php echo htmlspecialchars($site_name); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #6366f1; --dark: #0f172a; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .auth-card { background: #ffffff; border-radius: 28px; padding: 45px 40px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05); width: 100%; max-width: 450px; border: 1px solid #e2e8f0; }
        .form-control { padding: 13px 18px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.95rem; }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12); border-color: var(--primary); }
        .btn-primary { padding: 13px; border-radius: 12px; font-weight: 700; background: var(--primary); border: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="text-center mb-4">
        <a href="./" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3">
            <span class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fa fa-graduation-cap"></i>
            </span>
            <span class="fw-800 fs-5 text-primary">LKVM <span class="text-dark">Academy</span></span>
        </a>
        <h3 class="fw-800 text-dark mb-1">Student Sign In</h3>
        <p class="text-muted small">Log in to resume your lectures and course materials</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-4 py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-circle-exclamation fs-5"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required autofocus value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-muted text-uppercase">Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" name="student_login" class="btn btn-primary w-100 mb-3 shadow">
            Sign In & Continue
        </button>

        <p class="text-center text-muted small mb-0">
            Don't have an account? <a href="signup?redirect=<?php echo urlencode($redirect); ?>" class="text-primary fw-bold text-decoration-none">Create Free Account</a>
        </p>

        <hr class="my-4">

        <div class="d-flex justify-content-between text-muted small">
            <a href="./" class="text-muted text-decoration-none"><i class="fa fa-arrow-left me-1"></i> Back to Courses</a>
            <a href="admin/login" class="text-muted text-decoration-none"><i class="fa fa-lock me-1"></i> Admin Portal</a>
        </div>
    </form>
</div>

</body>
</html>
