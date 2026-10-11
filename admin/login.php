<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in as LMS Admin
if (isset($_SESSION['lms_admin_id'])) {
    header("Location: index");
    exit();
}

$error = "";
$success = "";

if (isset($_POST['admin_login'])) {
    $login_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        $stmt = $con->prepare("SELECT * FROM lms_admins WHERE (username = ? OR email = ?) AND status = 1 LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $login_input, $login_input);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $admin = $res->fetch_assoc();
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['lms_admin_id'] = $admin['id'];
                    $_SESSION['lms_admin_name'] = $admin['name'];
                    $_SESSION['lms_admin_email'] = $admin['email'];
                    $_SESSION['lms_admin_role'] = $admin['role'];
                    $_SESSION['lms_admin_username'] = $admin['username'];

                    header("Location: index");
                    exit();
                } else {
                    $error = "Incorrect password! Please try again.";
                }
            } else {
                $error = "Administrator account not found or deactivated.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - LKVM Academy LMS</title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="icon" type="image/png" href="../favicon.png">
    <link rel="shortcut icon" href="../favicon.ico">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --bg: #0f172a;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #f8fafc;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 28px;
            padding: 45px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 460px;
            color: var(--dark);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .form-control {
            padding: 14px 18px;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }
        .form-control:focus {
            background-color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .btn-login {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: #ffffff;
            padding: 14px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.35);
            transition: all 0.25s ease;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.45);
            color: #ffffff;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #eef2ff;
            color: var(--primary);
            padding: 8px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }
        .credential-box {
            background: #f1f5f9;
            border-left: 4px solid var(--primary);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.82rem;
            margin-top: 25px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center mb-4">
        <div class="brand-badge">
            <i class="fa fa-graduation-cap fs-5"></i> LKVM LMS Admin
        </div>
        <h3 class="fw-800 mb-1">Course Management</h3>
        <p class="text-muted small">Sign in to manage courses, lessons, students & sales</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger rounded-4 py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="fa fa-circle-exclamation fs-5"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Username or Email</label>
            <div class="position-relative">
                <input type="text" name="username" class="form-control ps-4" placeholder="Enter your username or email" required autofocus value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold text-muted text-uppercase mb-1">Password</label>
            <div class="position-relative">
                <input type="password" name="password" id="adminPassword" class="form-control ps-4" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" name="admin_login" class="btn btn-login w-100 mb-3">
            <i class="fa fa-arrow-right-to-bracket me-2"></i> Access Admin Portal
        </button>

        <div class="d-flex justify-content-between align-items-center pt-2">
            <a href="../" class="text-decoration-none small text-muted">
                <i class="fa fa-arrow-left me-1"></i> Visit Academy
            </a>
            <a href="../../index.php" class="text-decoration-none small text-muted">
                <i class="fa fa-school me-1"></i> Main School Site
            </a>
        </div>
    </form>
</div>

</body>
</html>
