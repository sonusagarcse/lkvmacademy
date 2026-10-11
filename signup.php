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
if (isset($_POST['signup'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password_raw = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password_raw)) {
        $error = "Please fill in all required fields.";
    } elseif (strlen($password_raw) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $check = mysqli_query($con, "SELECT id FROM lms_students WHERE email = '" . mysqli_real_escape_string($con, $email) . "'");
        if (mysqli_num_rows($check) > 0) {
            $error = "An account with this email address already exists. Please log in.";
        } else {
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $stmt = $con->prepare("INSERT INTO lms_students (name, email, password, phone, status) VALUES (?, ?, ?, ?, 1)");
            if ($stmt) {
                $stmt->bind_param("ssss", $name, $email, $password_hash, $phone);
                $stmt->execute();
                $new_id = $stmt->insert_id;
                $stmt->close();

                $_SESSION['student_id'] = $new_id;
                $_SESSION['student_name'] = $name;
                $_SESSION['student_email'] = $email;

                header("Location: $redirect");
                exit();
            } else {
                $error = "Database error. Please try again.";
            }
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
    <title>Create Free Student Account - <?php echo htmlspecialchars($site_name); ?></title>
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
        .auth-card { background: #ffffff; border-radius: 28px; padding: 45px 40px; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.05); width: 100%; max-width: 480px; border: 1px solid #e2e8f0; }
        .form-control { padding: 12px 18px; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.95rem; }
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
        <h3 class="fw-800 text-dark mb-1">Create Student Account</h3>
        <p class="text-muted small">Join verified students learning practical skills</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-4 py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-circle-exclamation fs-5"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="e.g. Ramesh Kumar" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="name@example.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Mobile / WhatsApp Number</label>
            <input type="text" name="phone" class="form-control" placeholder="+91 9801664500" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-muted text-uppercase">Password *</label>
            <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
        </div>

        <button type="submit" name="signup" class="btn btn-primary w-100 mb-3 shadow">
            Create Account & Start Learning
        </button>

        <p class="text-center text-muted small mb-0">
            Already have an account? <a href="login?redirect=<?php echo urlencode($redirect); ?>" class="text-primary fw-bold text-decoration-none">Sign In</a>
        </p>

        <hr class="my-4">

        <div class="d-flex justify-content-between text-muted small">
            <a href="./" class="text-muted text-decoration-none"><i class="fa fa-arrow-left me-1"></i> Back to Courses</a>
            <a href="../" class="text-muted text-decoration-none"><i class="fa fa-school me-1"></i> LKVM Main Portal</a>
        </div>
    </form>
</div>

</body>
</html>
