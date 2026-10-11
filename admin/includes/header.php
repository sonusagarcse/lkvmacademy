<?php
require_once dirname(dirname(__DIR__)) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check: allow LMS Admin OR root Super Admin
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$admin_name = $_SESSION['lms_admin_name'] ?? ($_SESSION['admin_name'] ?? 'Administrator');
$admin_role = $_SESSION['lms_admin_role'] ?? 'Super Admin';
$admin_email = $_SESSION['lms_admin_email'] ?? 'admin@lkvm.org';
$currentPage = basename($_SERVER['PHP_SELF']);
$currBase = preg_replace('/\.php$/', '', $currentPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - LKVM Academy LMS</title>
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
            --primary-light: #eef2ff;
            --dark: #0f172a;
            --bg: #f8fafc;
            --sidebar-w: 270px;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: #1e293b;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .sidebar {
            width: var(--sidebar-w);
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1030;
            display: flex;
            flex-direction: column;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar-brand {
            padding: 24px 24px 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .sidebar-nav {
            padding: 16px 12px;
            overflow-y: auto;
            flex: 1;
        }
        .nav-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #94a3b8;
            font-weight: 800;
            padding: 14px 14px 6px;
        }
        .sidebar-nav .nav-link {
            padding: 11px 16px;
            border-radius: 12px;
            margin: 2px 0;
            color: #475569;
            font-weight: 600;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .sidebar-nav .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary);
            transform: translateX(3px);
        }
        .sidebar-nav .nav-link.active {
            background: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
        }
        .sidebar-nav .nav-link i {
            width: 22px;
            font-size: 1.1rem;
            text-align: center;
        }

        .main-content {
            margin-left: var(--sidebar-w);
            padding: 30px 40px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        .top-navbar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 40px;
            position: sticky;
            top: 0;
            z-index: 1020;
            margin: -30px -40px 30px -40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.2s ease;
        }

        .stat-card-premium {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            position: relative;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.25s ease;
        }
        .stat-card-premium:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }
        .stat-icon-box {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 16px;
        }
        .bg-gradient-primary { background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); color: #fff; }
        .bg-gradient-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; }
        .bg-gradient-info { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; }
        .bg-gradient-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; }

        .table-premium thead th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.8px;
            font-weight: 800;
            color: #64748b;
            padding: 14px 18px;
            border: none;
        }
        .table-premium tbody td {
            padding: 16px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 0.92rem;
        }

        @media (max-width: 992px) {
            .sidebar {
                left: calc(-1 * var(--sidebar-w));
            }
            .sidebar.active {
                left: 0;
                box-shadow: 20px 0 50px rgba(0, 0, 0, 0.15);
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .top-navbar {
                margin: -20px -20px 20px -20px;
                padding: 14px 20px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-brand d-flex justify-content-between align-items-center">
        <a href="index" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
            <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fa fa-graduation-cap"></i>
            </div>
            <div>
                <span class="fw-800 fs-5 text-primary">LKVM</span>
                <span class="fw-bold fs-5 text-dark">Academy</span>
            </div>
        </a>
        <button class="btn d-lg-none p-0 border-0" id="closeSidebar"><i class="fa fa-times fs-5 text-muted"></i></button>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section-title">Overview</div>
        <a href="index" class="nav-link <?php echo $currBase == 'index' ? 'active' : ''; ?>">
            <i class="fa fa-th-large"></i> Dashboard
        </a>
        
        <div class="nav-section-title">Course Management</div>
        <a href="categories" class="nav-link <?php echo $currBase == 'categories' ? 'active' : ''; ?>">
            <i class="fa fa-folder-tree"></i> Categories
        </a>
        <a href="courses" class="nav-link <?php echo $currBase == 'courses' ? 'active' : ''; ?>">
            <i class="fa fa-book-open"></i> All Courses
        </a>
        <a href="lessons" class="nav-link <?php echo $currBase == 'lessons' ? 'active' : ''; ?>">
            <i class="fa fa-circle-play"></i> Lessons / Content
        </a>
        
        <div class="nav-section-title">Students & Sales</div>
        <a href="students" class="nav-link <?php echo $currBase == 'students' ? 'active' : ''; ?>">
            <i class="fa fa-users"></i> Students
        </a>
        <a href="enrollments" class="nav-link <?php echo $currBase == 'enrollments' ? 'active' : ''; ?>">
            <i class="fa fa-receipt"></i> Enrollments & Orders
        </a>

        <div class="nav-section-title">Certification</div>
        <a href="certificates" class="nav-link <?php echo $currBase == 'certificates' ? 'active' : ''; ?>">
            <i class="fa fa-award"></i> Issued Certificates
        </a>
        <a href="certificate-templates" class="nav-link <?php echo $currBase == 'certificate-templates' ? 'active' : ''; ?>">
            <i class="fa fa-palette"></i> Certificate Studio & Templates
        </a>

        <div class="nav-section-title">System</div>
        <a href="settings" class="nav-link <?php echo $currBase == 'settings' ? 'active' : ''; ?>">
            <i class="fa fa-sliders"></i> Settings & Payments
        </a>
    </nav>

    <div class="p-3 border-top bg-light">
        <div class="d-flex align-items-center justify-content-between">
            <a href="../" target="_blank" class="btn btn-sm btn-white border rounded-pill px-3 fw-bold small text-muted">
                <i class="fa fa-eye me-1"></i> Academy
            </a>
            <a href="logout" class="btn btn-sm btn-outline-danger rounded-pill px-3 fw-bold small">
                <i class="fa fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>
</aside>

<!-- Main Wrapper -->
<div class="main-content">
    <!-- Top Bar -->
    <header class="top-navbar">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light border d-lg-none rounded-3" id="openSidebar">
                <i class="fa fa-bars text-primary"></i>
            </button>
            <div>
                <h4 class="fw-800 mb-0"><?php echo $page_title ?? 'Dashboard'; ?></h4>
                <small class="text-muted">Separate LMS Database: <code>lkvm_courses</code></small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <div class="d-none d-md-flex align-items-center gap-2 bg-light px-3 py-2 rounded-pill border">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($admin_name); ?>&background=6366f1&color=fff&bold=true" class="rounded-circle" style="width: 32px; height: 32px;">
                <div>
                    <div class="fw-bold small text-dark leading-tight"><?php echo htmlspecialchars($admin_name); ?></div>
                    <div class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($admin_role); ?></div>
                </div>
            </div>
            <a href="../" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold d-none d-sm-inline-flex align-items-center gap-1">
                <i class="fa fa-arrow-up-right-from-square"></i> Visit Academy
            </a>
        </div>
    </header>
