<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['student_id']);
$student_name = $_SESSION['student_name'] ?? 'Student';
$student_id = $_SESSION['student_id'] ?? 0;

$site_name = lms_get_setting($con, 'site_name', 'LKVM Academy');
$site_tagline = lms_get_setting($con, 'site_tagline', 'Master Skills of the Future');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Explore Courses'; ?> - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg lms-navbar sticky-top bg-white shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand fw-800 fs-4 d-flex align-items-center gap-2" href="./">
            <span class="bg-primary text-white rounded-3 p-2 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                <i class="fa fa-graduation-cap fs-5"></i>
            </span>
            <div>
                <span class="text-primary fw-800">LKVM</span>
                <span class="text-dark fw-bold">Academy</span>
            </div>
        </a>
        
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#lmsNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="lmsNav">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link px-3 fw-semibold" href="./">All Courses</a></li>
                <li class="nav-item"><a class="nav-link px-3 fw-semibold" href="categories">Categories</a></li>
                <li class="nav-item"><a class="nav-link px-3 fw-semibold" href="dashboard">My Classroom</a></li>
                <li class="nav-item"><a class="nav-link px-3 fw-semibold text-muted" href="../" target="_blank"><i class="fa fa-school me-1"></i> Main School Portal</a></li>
            </ul>
            
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_logged_in): 
                    $header_avatar = "https://ui-avatars.com/api/?name=" . urlencode($student_name) . "&background=6366f1&color=fff&bold=true";
                    if (!empty($_SESSION['student_id'])) {
                        $av_res = mysqli_query($con, "SELECT profile_pic FROM lms_students WHERE id = " . intval($_SESSION['student_id']) . " LIMIT 1");
                        if ($av_res && $av_row = mysqli_fetch_assoc($av_res)) {
                            if (!empty($av_row['profile_pic']) && file_exists(__DIR__ . '/../' . $av_row['profile_pic'])) {
                                $header_avatar = $av_row['profile_pic'];
                            }
                        }
                    }
                ?>
                    <div class="dropdown">
                        <button class="btn btn-light rounded-pill px-3 py-2 fw-bold dropdown-toggle d-flex align-items-center gap-2 border shadow-sm" data-bs-toggle="dropdown">
                            <img src="<?php echo htmlspecialchars($header_avatar); ?>" class="rounded-circle object-fit-cover" style="width: 28px; height: 28px;">
                            <span><?php echo htmlspecialchars($student_name); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 mt-2 p-2" style="min-width: 220px;">
                            <li><a class="dropdown-item rounded-3 py-2 fw-semibold" href="dashboard?tab=courses"><i class="fa fa-th-large me-2 text-primary"></i> My Classroom</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-semibold" href="dashboard?tab=pdfs"><i class="fa fa-file-pdf me-2 text-danger"></i> PDF Study Notes</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-semibold" href="dashboard?tab=certificates"><i class="fa fa-award me-2 text-warning"></i> My Certificates</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-semibold" href="dashboard?tab=payments"><i class="fa fa-receipt me-2 text-success"></i> Payment History</a></li>
                            <li><a class="dropdown-item rounded-3 py-2 fw-semibold" href="dashboard?tab=profile"><i class="fa fa-user-circle me-2 text-info"></i> My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item rounded-3 py-2 text-danger fw-semibold" href="logout"><i class="fa fa-sign-out-alt me-2"></i> Log Out</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login" class="btn btn-light rounded-pill px-4 fw-bold">Sign In</a>
                    <a href="signup" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">Join Academy</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
