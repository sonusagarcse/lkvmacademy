<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$email = trim($_GET['email'] ?? ($_SESSION['pending_otp_email'] ?? ''));
$redirect = trim($_GET['redirect'] ?? ($_SESSION['pending_otp_redirect'] ?? 'dashboard'));

if (empty($email)) {
    header("Location: login.php");
    exit();
}

$error = "";
$success_msg = "";

// Handle Resend OTP Request
if (isset($_POST['resend_otp'])) {
    $stmt = $con->prepare("SELECT id, name, email FROM lms_students WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $user = $res->fetch_assoc()) {
        $otp = strval(rand(100000, 999999));
        $exp = date('Y-m-d H:i:s', time() + 900);
        $uid = intval($user['id']);
        mysqli_query($con, "UPDATE lms_students SET otp_code = '$otp', otp_expires_at = '$exp' WHERE id = $uid");

        $mailRes = lms_send_otp_email($con, $user['email'], $user['name'], $otp);
        if ($mailRes['success']) {
            $success_msg = "A fresh 6-digit OTP has been sent to your Gmail inbox (" . htmlspecialchars($email) . ").";
        } else {
            $error = "Mail delivery notice: " . htmlspecialchars($mailRes['message']);
        }
    }
}

// Handle OTP Verification Submission
if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (empty($entered_otp) || strlen($entered_otp) < 6) {
        $error = "Please enter the complete 6-digit OTP.";
    } else {
        $stmt = $con->prepare("SELECT * FROM lms_students WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res && $user = $res->fetch_assoc()) {
            if (empty($user['otp_code']) || $user['otp_code'] !== $entered_otp) {
                $error = "Incorrect verification code. Please check your Gmail and try again.";
            } elseif (!empty($user['otp_expires_at']) && strtotime($user['otp_expires_at']) < time()) {
                $error = "Your OTP has expired. Please click 'Resend OTP' below to get a fresh code.";
            } else {
                // Verification Successful
                $uid = intval($user['id']);
                mysqli_query($con, "UPDATE lms_students SET email_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = $uid");

                $_SESSION['student_id'] = $user['id'];
                $_SESSION['student_name'] = $user['name'];
                $_SESSION['student_email'] = $user['email'];
                unset($_SESSION['pending_otp_email']);
                unset($_SESSION['pending_otp_redirect']);

                header("Location: $redirect");
                exit();
            }
        } else {
            $error = "Account not found. Please try registering again.";
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
    <title>Verify Email with OTP - <?php echo htmlspecialchars($site_name); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #0062ff; --dark: #0f172a; }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .auth-card { background: #ffffff; border-radius: 28px; padding: 45px 36px; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25); width: 100%; max-width: 460px; border: 1px solid rgba(255,255,255,0.1); }
        .otp-input { letter-spacing: 12px; font-size: 2rem; font-weight: 800; text-align: center; border-radius: 16px; border: 2px solid #e2e8f0; padding: 14px 10px; font-family: monospace; color: #0062ff; }
        .otp-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(0, 98, 255, 0.15); outline: none; }
        .btn-primary { padding: 14px; border-radius: 14px; font-weight: 800; background: linear-gradient(135deg, #0052cc 0%, #0062ff 100%); border: none; font-size: 1rem; }
        .btn-primary:hover { opacity: 0.95; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="text-center mb-4">
        <div class="d-inline-flex p-3 rounded-circle mb-3" style="background: rgba(0, 98, 255, 0.1); color: #0062ff;">
            <i class="fa fa-shield-halved fs-2"></i>
        </div>
        <h3 class="fw-800 text-dark mb-1">Verify Your Email</h3>
        <p class="text-muted small mb-0">We sent a 6-digit verification code to:</p>
        <span class="badge bg-primary bg-opacity-10 text-primary mt-1 px-3 py-2 rounded-pill fw-bold">
            <?php echo htmlspecialchars($email); ?>
        </span>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-4 py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-circle-exclamation fs-5"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success rounded-4 py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-circle-check fs-5"></i>
            <div><?php echo htmlspecialchars($success_msg); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4 text-center">
            <label class="form-label small fw-bold text-muted text-uppercase d-block mb-2">Enter 6-Digit Code</label>
            <input type="text" name="otp" class="form-control otp-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="••••••" autofocus required autocomplete="one-time-code">
            <small class="text-muted d-block mt-2">Code expires in 15 minutes</small>
        </div>

        <button type="submit" name="verify_otp" class="btn btn-primary w-100 mb-3 shadow">
            Verify & Continue to Learning →
        </button>
    </form>

    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
        <form method="POST" class="d-inline">
            <button type="submit" name="resend_otp" class="btn btn-link text-decoration-none p-0 small fw-bold text-primary">
                <i class="fa fa-rotate-right me-1"></i> Resend OTP Code
            </button>
        </form>
        <a href="login.php" class="text-muted small text-decoration-none">
            <i class="fa fa-arrow-left me-1"></i> Back to Sign In
        </a>
    </div>
</div>

</body>
</html>
