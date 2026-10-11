<?php
/**
 * LKVM LMS - Student Learning Dashboard
 * Unified hub for Courses, PDF Study Notes, Certificates, Payment History, and Profile Settings.
 */
$page_title = 'My Learning Classroom';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['student_id'])) {
    header("Location: login?redirect=" . urlencode("dashboard"));
    exit();
}

$user_id = intval($_SESSION['student_id']);
$student_name = $_SESSION['student_name'] ?? 'Learner';
$msg = '';
$msg_type = 'success';

// Handle Profile Updates
if (isset($_POST['update_profile'])) {
    $new_name = trim($_POST['name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $old_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($new_name)) {
        $msg = "Please enter your full name.";
        $msg_type = "danger";
    } else {
        // Fetch current user record
        $u_stmt = $con->prepare("SELECT password FROM lms_students WHERE id = ? LIMIT 1");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $curr_user = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();

        $pass_update_sql = "";
        $can_update = true;

        if (!empty($new_pass)) {
            if (empty($old_pass)) {
                $msg = "Please enter your current password to change it.";
                $msg_type = "danger";
                $can_update = false;
            } elseif (!password_verify($old_pass, $curr_user['password'])) {
                $msg = "Incorrect current password.";
                $msg_type = "danger";
                $can_update = false;
            } elseif (strlen($new_pass) < 6) {
                $msg = "New password must be at least 6 characters long.";
                $msg_type = "danger";
                $can_update = false;
            } elseif ($new_pass !== $confirm_pass) {
                $msg = "New passwords do not match.";
                $msg_type = "danger";
                $can_update = false;
            } else {
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $pass_stmt = $con->prepare("UPDATE lms_students SET password = ? WHERE id = ?");
                $pass_stmt->bind_param("si", $hashed_pass, $user_id);
                $pass_stmt->execute();
                $pass_stmt->close();
            }
        }

        // Profile Picture Upload
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowed_pic_exts = ['jpg', 'jpeg', 'png', 'webp'];
            $pic_ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($pic_ext, $allowed_pic_exts)) {
                $prof_dir = __DIR__ . '/uploads/profiles/';
                if (!is_dir($prof_dir)) {
                    @mkdir($prof_dir, 0777, true);
                }
                $pic_name = 'profile_' . $user_id . '_' . time() . '.' . $pic_ext;
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $prof_dir . $pic_name)) {
                    $pic_path = 'uploads/profiles/' . $pic_name;
                    $p_stmt = $con->prepare("UPDATE lms_students SET profile_pic = ? WHERE id = ?");
                    $p_stmt->bind_param("si", $pic_path, $user_id);
                    $p_stmt->execute();
                    $p_stmt->close();
                    $_SESSION['student_avatar'] = $pic_path;
                }
            }
        }

        if ($can_update) {
            $stmt = $con->prepare("UPDATE lms_students SET name = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_name, $new_phone, $user_id);
            if ($stmt->execute()) {
                $_SESSION['student_name'] = $new_name;
                $student_name = $new_name;
                $msg = "Your profile has been updated successfully!";
                $msg_type = "success";
            }
            $stmt->close();
        }
    }
}

// Fetch Student Profile Record
$user_info = null;
$u_res = mysqli_query($con, "SELECT id, name, email, phone, profile_pic, created_at, status FROM lms_students WHERE id = $user_id LIMIT 1");
if ($u_res && mysqli_num_rows($u_res) > 0) {
    $user_info = mysqli_fetch_assoc($u_res);
}

$student_avatar = (!empty($user_info['profile_pic']) && file_exists(__DIR__ . '/' . $user_info['profile_pic']))
    ? $user_info['profile_pic']
    : "https://ui-avatars.com/api/?name=" . urlencode($student_name) . "&background=6366f1&color=fff&size=128&bold=true";

// Fetch all enrolled courses
$query = "
    SELECT c.*, e.created_at as enrollment_date, e.payment_method, e.amount as paid_amount, e.id as enrollment_id
    FROM lms_enrollments e 
    JOIN lms_courses c ON e.course_id = c.id 
    WHERE e.user_id = ? AND e.status = 'completed'
    ORDER BY e.id DESC
";
$stmt = $con->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$enrolled_courses = [];
while ($row = $result->fetch_assoc()) {
    $enrolled_courses[] = $row;
}
$enrolled_count = count($enrolled_courses);

// Fetch all PDF resources for enrolled courses
$pdf_query = "
    SELECT l.*, c.title as course_title, c.slug as course_slug
    FROM lms_lessons l
    JOIN lms_courses c ON l.course_id = c.id
    JOIN lms_enrollments e ON e.course_id = c.id
    WHERE e.user_id = ? AND e.status = 'completed' 
      AND (l.content_type = 'pdf' OR l.content_url LIKE '%.pdf%')
    ORDER BY c.title ASC, l.sort_order ASC
";
$pdf_stmt = $con->prepare($pdf_query);
$pdf_stmt->bind_param("i", $user_id);
$pdf_stmt->execute();
$pdf_res = $pdf_stmt->get_result();
$pdf_stmt->close();

$pdf_list = [];
while ($row = $pdf_res->fetch_assoc()) {
    $pdf_list[] = $row;
}
$pdf_count = count($pdf_list);

// Fetch earned certificates
lms_ensure_certificates_table($con);
$my_certs = lms_get_student_certificates($con, $user_id);
$cert_count = count($my_certs);

// Fetch payment / order transactions
$pay_query = "
    SELECT e.*, c.title as course_title, c.thumbnail as course_thumbnail, c.slug as course_slug
    FROM lms_enrollments e
    LEFT JOIN lms_courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    ORDER BY e.id DESC
";
$pay_stmt = $con->prepare($pay_query);
$pay_stmt->bind_param("i", $user_id);
$pay_stmt->execute();
$pay_res = $pay_stmt->get_result();
$pay_stmt->close();

$payment_history = [];
$total_spent = 0;
while ($row = $pay_res->fetch_assoc()) {
    $payment_history[] = $row;
    if ($row['status'] === 'completed') {
        $total_spent += floatval($row['amount'] ?? 0);
    }
}
$payment_count = count($payment_history);

// Active Tab
$active_tab = trim($_GET['tab'] ?? 'courses');
if (!in_array($active_tab, ['courses', 'pdfs', 'certificates', 'payments', 'profile'])) {
    $active_tab = 'courses';
}

include 'includes/header.php';
?>

<style>
    .dash-nav-pill {
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #475569;
        font-weight: 700;
        font-size: 0.92rem;
        padding: 10px 20px;
        border-radius: 50px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .dash-nav-pill:hover {
        background: #f8fafc;
        color: var(--primary, #0056D2);
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .dash-nav-pill.active {
        background: var(--primary, #0056D2);
        color: #fff;
        border-color: var(--primary, #0056D2);
        box-shadow: 0 4px 12px rgba(0, 86, 210, 0.25);
    }
    .dash-nav-pill .badge {
        font-size: 0.75rem;
    }
    .dash-tab-pane {
        display: none;
    }
    .dash-tab-pane.active {
        display: block;
        animation: fadeIn 0.25s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="dashboard-wrap py-5 bg-light min-vh-100">
    <div class="container py-2">

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
                <i class="fa fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="bg-white p-4 p-md-5 rounded-4 border shadow-sm mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-4">
            <div class="d-flex align-items-center gap-4">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student_name); ?>&background=6366f1&color=fff&size=128&bold=true" class="rounded-circle shadow" style="width: 72px; height: 72px;">
                <div>
                    <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1 fw-bold small mb-2">STUDENT PORTAL</span>
                    <h2 class="fw-800 mb-1 text-dark">Welcome back, <?php echo htmlspecialchars($student_name); ?>!</h2>
                    <p class="text-muted small mb-0"><i class="fa fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($user_info['email'] ?? ''); ?> • Member since <?php echo date('M Y', strtotime($user_info['created_at'] ?? 'now')); ?></p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="./" class="btn btn-outline-primary rounded-pill px-4 fw-bold d-flex align-items-center">
                    <i class="fa fa-compass me-2"></i> Browse Courses
                </a>
            </div>
        </div>

        <!-- Small Navigation Options Bar -->
        <div class="bg-white p-3 rounded-4 border shadow-sm mb-4">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-start">
                <span class="text-muted small fw-bold px-2 d-none d-md-inline text-uppercase" style="letter-spacing: 1px;">Menu:</span>
                
                <a href="dashboard?tab=courses" class="dash-nav-pill <?php echo $active_tab == 'courses' ? 'active' : ''; ?>" data-target="tab-courses">
                    <i class="fa fa-graduation-cap"></i> My Classroom
                    <span class="badge <?php echo $active_tab == 'courses' ? 'bg-white text-primary' : 'bg-light text-dark border'; ?> rounded-pill"><?php echo $enrolled_count; ?></span>
                </a>

                <a href="dashboard?tab=pdfs" class="dash-nav-pill <?php echo $active_tab == 'pdfs' ? 'active' : ''; ?>" data-target="tab-pdfs">
                    <i class="fa fa-file-pdf text-danger"></i> PDF Notes
                    <span class="badge <?php echo $active_tab == 'pdfs' ? 'bg-white text-danger' : 'bg-light text-dark border'; ?> rounded-pill"><?php echo $pdf_count; ?></span>
                </a>

                <a href="dashboard?tab=certificates" class="dash-nav-pill <?php echo $active_tab == 'certificates' ? 'active' : ''; ?>" data-target="tab-certificates">
                    <i class="fa fa-award text-warning"></i> Certificates
                    <span class="badge <?php echo $active_tab == 'certificates' ? 'bg-white text-warning' : 'bg-light text-dark border'; ?> rounded-pill"><?php echo $cert_count; ?></span>
                </a>

                <a href="dashboard?tab=payments" class="dash-nav-pill <?php echo $active_tab == 'payments' ? 'active' : ''; ?>" data-target="tab-payments">
                    <i class="fa fa-receipt text-success"></i> Payment History
                    <span class="badge <?php echo $active_tab == 'payments' ? 'bg-white text-success' : 'bg-light text-dark border'; ?> rounded-pill"><?php echo $payment_count; ?></span>
                </a>

                <a href="dashboard?tab=profile" class="dash-nav-pill <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" data-target="tab-profile">
                    <i class="fa fa-user-circle text-info"></i> Profile & Settings
                </a>
            </div>
        </div>

        <!-- ================= TAB 1: MY CLASSROOM ================= -->
        <div class="dash-tab-pane <?php echo $active_tab == 'courses' ? 'active' : ''; ?>" id="tab-courses">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 text-dark mb-0"><i class="fa fa-graduation-cap text-primary me-2"></i>My Classroom & Active Courses</h4>
                    <small class="text-muted">Track your progress and continue learning your enrolled courses</small>
                </div>
                <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold small"><?php echo $enrolled_count; ?> Active Program(s)</span>
            </div>

            <?php if ($enrolled_count > 0): ?>
                <div class="row g-4 mb-4">
                    <?php foreach ($enrolled_courses as $course): 
                        $cid = $course['id'];
                        // Total lessons
                        $tot_res = mysqli_query($con, "SELECT COUNT(*) as total FROM lms_lessons WHERE course_id = $cid");
                        $total_lessons = mysqli_fetch_assoc($tot_res)['total'] ?? 0;

                        // Completed lessons by this student
                        $comp_res = mysqli_query($con, "
                            SELECT COUNT(*) as total 
                            FROM lms_progress p 
                            JOIN lms_lessons l ON p.lesson_id = l.id 
                            WHERE p.user_id = $user_id AND l.course_id = $cid
                        ");
                        $completed_lessons = mysqli_fetch_assoc($comp_res)['total'] ?? 0;

                        $prog_pct = ($total_lessons > 0) ? round(($completed_lessons / $total_lessons) * 100) : 0;

                        // Find first incomplete lesson ID
                        $next_l_q = mysqli_query($con, "
                            SELECT id FROM lms_lessons 
                            WHERE course_id = $cid AND id NOT IN (SELECT lesson_id FROM lms_progress WHERE user_id = $user_id) 
                            ORDER BY sort_order ASC, id ASC LIMIT 1
                        ");
                        $target_lesson_param = "";
                        if ($next_l_q && mysqli_num_rows($next_l_q) > 0) {
                            $target_lid = mysqli_fetch_assoc($next_l_q)['id'];
                            $target_lesson_param = "&lesson=" . $target_lid;
                        }

                        $thumb = $course['thumbnail'];
                        if (empty($thumb)) {
                            $thumb = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=600';
                        }
                    ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="lms-card shadow-sm h-100 d-flex flex-column bg-white rounded-4 overflow-hidden border">
                                <div class="lms-card-img position-relative overflow-hidden" style="height: 190px;">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" class="w-100 h-100 object-fit-cover">
                                    
                                    <!-- Progress line -->
                                    <div class="progress" style="height: 6px; border-radius: 0; position: absolute; bottom: 0; width: 100%; background: rgba(0,0,0,0.3);">
                                        <div class="progress-bar bg-success" style="width: <?php echo $prog_pct; ?>%;"></div>
                                    </div>
                                </div>

                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill small fw-bold">ENROLLED</span>
                                        <span class="small fw-bold <?php echo $prog_pct == 100 ? 'text-success' : 'text-primary'; ?>"><?php echo $prog_pct; ?>% COMPLETED</span>
                                    </div>

                                    <h5 class="fw-800 text-dark mb-2" style="line-height: 1.4;">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </h5>

                                    <p class="text-muted small mb-3 flex-grow-1">
                                        <?php echo htmlspecialchars($course['short_desc']); ?>
                                    </p>

                                    <div class="d-flex align-items-center justify-content-between py-2 border-top border-bottom mb-3 text-muted small">
                                        <span><i class="fa fa-play-circle text-primary me-1"></i> <?php echo $completed_lessons; ?> / <?php echo $total_lessons; ?> Lectures</span>
                                        <span><i class="fa fa-calendar-alt text-muted me-1"></i> <?php echo date('M Y', strtotime($course['enrollment_date'])); ?></span>
                                    </div>

                                    <a href="learn?slug=<?php echo urlencode($course['slug']) . $target_lesson_param; ?>" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" style="background: var(--primary); border:none;">
                                        <i class="fa fa-play me-2"></i> <?php echo $prog_pct == 100 ? 'Review Lectures' : ($prog_pct > 0 ? 'Continue Learning' : 'Start First Lesson'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5 bg-white rounded-4 border p-5 shadow-sm mb-4">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-4 mb-4">
                        <i class="fa fa-graduation-cap fs-1"></i>
                    </div>
                    <h3 class="fw-800 text-dark mb-2">You haven't enrolled in any courses yet</h3>
                    <p class="text-muted mx-auto mb-4" style="max-width: 500px;">
                        Explore our practical computer applications, accounting, and development courses. Start learning today and earn recognized certification.
                    </p>
                    <a href="./" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                        Browse All Courses
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================= TAB 2: PDF STUDY NOTES ================= -->
        <div class="dash-tab-pane <?php echo $active_tab == 'pdfs' ? 'active' : ''; ?>" id="tab-pdfs">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 text-dark mb-1"><i class="fa fa-file-pdf text-danger me-2"></i>Course PDF Notes & Reference Materials</h4>
                    <p class="text-muted small mb-0">Open, read, and download all reference materials attached to your enrolled courses</p>
                </div>
                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-2 fw-bold small"><?php echo $pdf_count; ?> Available PDF(s)</span>
            </div>

            <?php if ($pdf_count > 0): ?>
                <div class="row g-3 mb-4">
                    <?php foreach ($pdf_list as $pdf): 
                        $pdf_url = $pdf['content_url'];
                        if (!preg_match('/^https?:\/\//i', $pdf_url)) {
                            $pdf_url = $coursesBaseUrl . '/' . ltrim($pdf_url, '/');
                        }
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border rounded-4 shadow-sm p-3 bg-white">
                                <div class="d-flex gap-3 align-items-start mb-3">
                                    <div class="bg-danger bg-opacity-10 text-danger rounded-3 p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                        <i class="fa fa-file-pdf fs-4"></i>
                                    </div>
                                    <div class="overflow-hidden flex-grow-1">
                                        <div class="fw-bold text-dark text-truncate mb-1" title="<?php echo htmlspecialchars($pdf['title']); ?>">
                                            <?php echo htmlspecialchars($pdf['title']); ?>
                                        </div>
                                        <span class="badge bg-light text-muted border text-truncate d-inline-block small" style="max-width: 100%;">
                                            <?php echo htmlspecialchars($pdf['course_title']); ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if (!empty($pdf['description'])): ?>
                                    <p class="text-muted small mb-3 text-truncate-2" style="font-size: 0.82rem; line-height: 1.5;">
                                        <?php echo htmlspecialchars(mb_strimwidth($pdf['description'], 0, 90, '...')); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="mt-auto d-flex gap-2">
                                    <button type="button" class="btn btn-primary rounded-pill flex-grow-1 fw-bold small py-2 open-pdf-viewer" 
                                            data-url="<?php echo htmlspecialchars($pdf_url); ?>" 
                                            data-title="<?php echo htmlspecialchars($pdf['title']); ?>"
                                            data-course="<?php echo htmlspecialchars($pdf['course_title']); ?>"
                                            data-lesson="learn?slug=<?php echo urlencode($pdf['course_slug']); ?>&lesson=<?php echo $pdf['id']; ?>">
                                        <i class="fa fa-book-open me-1"></i> Read PDF
                                    </button>
                                    <a href="<?php echo htmlspecialchars($pdf_url); ?>" download class="btn btn-outline-secondary rounded-pill px-3 py-2 small" title="Download PDF">
                                        <i class="fa fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border rounded-4 p-5 text-center bg-white shadow-sm mb-4">
                    <i class="fa fa-file-pdf fs-1 text-danger mb-3 opacity-50"></i>
                    <h5 class="fw-bold text-dark mb-2">No PDF study materials available yet</h5>
                    <p class="text-muted mx-auto mb-0" style="max-width: 480px;">
                        When instructors attach PDF lecture notes, assignments, or cheat sheets to your enrolled courses, they will automatically appear here.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================= TAB 3: CERTIFICATES ================= -->
        <div class="dash-tab-pane <?php echo $active_tab == 'certificates' ? 'active' : ''; ?>" id="tab-certificates">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 text-dark mb-1"><i class="fa fa-award text-warning me-2"></i>My Earned Certificates</h4>
                    <p class="text-muted small mb-0">Official completion credentials with instant QR verification and PDF download</p>
                </div>
                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-2 fw-bold small"><?php echo $cert_count; ?> Earned Certificate(s)</span>
            </div>

            <?php if ($cert_count > 0): ?>
                <div class="row g-4 mb-4">
                    <?php foreach ($my_certs as $cert): 
                        $is_active = ($cert['status'] === 'active');
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border rounded-4 shadow-sm bg-white overflow-hidden">
                                <div class="text-center py-4 position-relative" style="background: linear-gradient(135deg, #fefce8, #fffbeb); border-bottom: 2px solid #f59e0b;">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-warning rounded-circle mb-2" style="width: 52px; height: 52px; box-shadow: 0 4px 12px rgba(245,158,11,0.35);">
                                        <i class="fa fa-award text-white fs-4"></i>
                                    </div>
                                    <div class="fw-800 text-dark">Certificate of Completion</div>
                                    <small class="text-muted">Verified LKVM Credential</small>
                                </div>
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <h6 class="fw-800 text-dark mb-2" style="font-size: 1rem; line-height: 1.4;"><?php echo htmlspecialchars($cert['course_title']); ?></h6>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3 text-muted small">
                                        <span><i class="fa fa-user-tie text-muted me-1"></i><?php echo htmlspecialchars($cert['instructor_name'] ?? 'Faculty'); ?></span>
                                        <span><i class="fa fa-calendar-check text-muted me-1"></i><?php echo date('d M Y', strtotime($cert['issue_date'])); ?></span>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span class="badge <?php echo $is_active ? 'bg-success bg-opacity-10 text-success' : 'bg-danger bg-opacity-10 text-danger'; ?> rounded-pill px-3 py-1 small fw-bold">
                                            <i class="fa fa-circle-check me-1"></i><?php echo $is_active ? 'Verified & Active' : 'Revoked'; ?>
                                        </span>
                                        <span class="badge bg-light text-muted border px-2 py-1 small fw-semibold">100% Complete</span>
                                    </div>

                                    <div class="bg-light rounded-3 p-2 text-center mb-3" style="font-family: 'Courier New', monospace; font-size: 0.82rem; font-weight: 700; color: #475569; letter-spacing: 1.5px;">
                                        ID: <?php echo htmlspecialchars($cert['certificate_uid']); ?>
                                    </div>

                                    <div class="mt-auto d-flex gap-2">
                                        <?php if ($is_active): ?>
                                            <a href="certificate-view?uid=<?php echo urlencode($cert['certificate_uid']); ?>" class="btn btn-primary rounded-pill flex-grow-1 fw-bold small py-2">
                                                <i class="fa fa-eye me-1"></i> View Certificate
                                            </a>
                                            <a href="certificate-view?uid=<?php echo urlencode($cert['certificate_uid']); ?>&download=1" class="btn btn-outline-secondary rounded-pill px-3 py-2 small" title="Download Image">
                                                <i class="fa fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary rounded-pill w-100 small" disabled>Revoked</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border rounded-4 p-5 text-center bg-white shadow-sm mb-4">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-inline-flex p-4 mb-3">
                        <i class="fa fa-award fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">No certificates earned yet</h5>
                    <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                        Complete 100% of all lectures in any enrolled course to automatically receive your verifiable digital certificate!
                    </p>
                    <a href="dashboard?tab=courses" class="btn btn-primary rounded-pill px-4 fw-bold">
                        Go to My Classroom
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================= TAB 4: PAYMENT HISTORY ================= -->
        <div class="dash-tab-pane <?php echo $active_tab == 'payments' ? 'active' : ''; ?>" id="tab-payments">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 text-dark mb-1"><i class="fa fa-receipt text-success me-2"></i>Payment & Enrollment History</h4>
                    <p class="text-muted small mb-0">View all your course orders, invoices, transaction IDs, and payment statuses</p>
                </div>
                <div class="bg-light border px-3 py-2 rounded-pill small fw-bold text-dark">
                    Total Invested: <span class="text-success">₹<?php echo number_format($total_spent, 2); ?></span>
                </div>
            </div>

            <?php if ($payment_count > 0): ?>
                <div class="card border rounded-4 shadow-sm bg-white overflow-hidden mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3 text-muted small fw-bold text-uppercase">Order Details</th>
                                    <th class="py-3 text-muted small fw-bold text-uppercase">Date</th>
                                    <th class="py-3 text-muted small fw-bold text-uppercase">Amount</th>
                                    <th class="py-3 text-muted small fw-bold text-uppercase">Payment Mode</th>
                                    <th class="py-3 text-muted small fw-bold text-uppercase">Status</th>
                                    <th class="pe-4 py-3 text-end text-muted small fw-bold text-uppercase">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_history as $pay): 
                                    $is_paid = ($pay['status'] === 'completed');
                                    $pay_thumb = $pay['course_thumbnail'];
                                    if (empty($pay_thumb)) {
                                        $pay_thumb = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=250';
                                    } elseif (strpos($pay_thumb, 'uploads/') === 0) {
                                        $pay_thumb = $pay_thumb;
                                    }
                                ?>
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="d-flex align-items-center gap-3">
                                                <img src="<?php echo htmlspecialchars($pay_thumb); ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 50px; height: 38px;">
                                                <div>
                                                    <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($pay['course_title'] ?? 'Course Enrollment'); ?></div>
                                                    <small class="text-muted">Order #ORD-<?php echo str_pad($pay['id'], 5, '0', STR_PAD_LEFT); ?> • Ref: <code><?php echo htmlspecialchars($pay['payment_id'] ?: 'N/A'); ?></code></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3">
                                            <span class="small fw-semibold text-dark"><?php echo date('d M Y', strtotime($pay['created_at'])); ?></span>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($pay['created_at'])); ?></div>
                                        </td>
                                        <td class="py-3">
                                            <?php if (floatval($pay['amount']) == 0): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold small">FREE</span>
                                            <?php else: ?>
                                                <div class="fw-800 text-dark">₹<?php echo number_format($pay['amount'], 2); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3">
                                            <span class="badge bg-light text-dark border px-3 py-1 rounded-pill small fw-semibold text-uppercase">
                                                <?php echo htmlspecialchars($pay['payment_method'] ?? 'online'); ?>
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <?php if ($is_paid): ?>
                                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">
                                                    <i class="fa fa-check-circle me-1"></i> Paid / Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 small fw-bold">
                                                    <i class="fa fa-clock me-1"></i> <?php echo ucfirst($pay['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-4 py-3 text-end">
                                            <?php if (!empty($pay['course_slug'])): ?>
                                                <a href="learn?slug=<?php echo urlencode($pay['course_slug']); ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                                    <i class="fa fa-play me-1"></i> Open Course
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border rounded-4 p-5 text-center bg-white shadow-sm mb-4">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex p-4 mb-3">
                        <i class="fa fa-receipt fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">No payment transactions found</h5>
                    <p class="text-muted mx-auto mb-4" style="max-width: 480px;">
                        When you enroll in premium courses or free programs, your complete invoice and order history will appear here.
                    </p>
                    <a href="./" class="btn btn-primary rounded-pill px-4 fw-bold">Explore Courses</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ================= TAB 5: PROFILE & SETTINGS ================= -->
        <div class="dash-tab-pane <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" id="tab-profile">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-800 text-dark mb-1"><i class="fa fa-user-circle text-info me-2"></i>My Profile & Account Settings</h4>
                    <p class="text-muted small mb-0">Manage your personal credentials, contact info, and security password</p>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="card border rounded-4 shadow-sm bg-white p-4 text-center mb-4">
                        <img src="<?php echo htmlspecialchars($student_avatar); ?>" class="rounded-circle shadow mb-3 mx-auto object-fit-cover" style="width: 90px; height: 90px; border: 3px solid #6366f1;">
                        <h5 class="fw-800 text-dark mb-1"><?php echo htmlspecialchars($user_info['name'] ?? $student_name); ?></h5>
                        <p class="text-muted small mb-3"><?php echo htmlspecialchars($user_info['email'] ?? ''); ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">Active Student</span>
                        </div>

                        <div class="border-top pt-3 text-start small">
                            <div class="d-flex justify-content-between py-1 text-muted">
                                <span><i class="fa fa-phone me-1"></i> Phone:</span>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($user_info['phone'] ?: 'Not Provided'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1 text-muted">
                                <span><i class="fa fa-calendar me-1"></i> Joined:</span>
                                <span class="fw-bold text-dark"><?php echo date('d M Y', strtotime($user_info['created_at'] ?? 'now')); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1 text-muted">
                                <span><i class="fa fa-award me-1"></i> Certificates:</span>
                                <span class="fw-bold text-warning"><?php echo $cert_count; ?> Earned</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <!-- Edit Profile Form -->
                    <div class="card border rounded-4 shadow-sm bg-white p-4 p-md-5">
                        <h5 class="fw-800 text-dark mb-4 pb-2 border-bottom">Edit Profile & Passport Photo</h5>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Full Name *</label>
                                    <input type="text" name="name" class="form-control rounded-3" required value="<?php echo htmlspecialchars($user_info['name'] ?? $student_name); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Email Address (Registered)</label>
                                    <input type="email" class="form-control rounded-3 bg-light" disabled value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>">
                                    <small class="text-muted" style="font-size: 0.75rem;">Email cannot be changed directly for security purposes.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Phone / Mobile Number</label>
                                    <input type="text" name="phone" class="form-control rounded-3" placeholder="+91 9801664500" value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Upload Passport / Profile Photo</label>
                                    <input type="file" name="profile_pic" class="form-control rounded-3" accept="image/png,image/jpeg,image/webp">
                                    <small class="text-muted" style="font-size: 0.75rem;">JPG/PNG/WebP. Photo will appear on your verified certificates.</small>
                                </div>
                            </div>

                            <h5 class="fw-800 text-dark mb-3 pt-3 border-top">Change Password (Optional)</h5>
                            <p class="text-muted small mb-3">Leave blank if you do not wish to update your password.</p>

                            <div class="row g-3 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Current Password</label>
                                    <input type="password" name="current_password" class="form-control rounded-3" placeholder="••••••••">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">New Password</label>
                                    <input type="password" name="new_password" class="form-control rounded-3" placeholder="At least 6 chars">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-uppercase text-muted">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control rounded-3" placeholder="••••••••">
                                </div>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                                <i class="fa fa-save me-2"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Interactive In-Dashboard PDF Viewer Modal -->
<div class="modal fade" id="dashboardPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 90vw;">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white border-0 py-3 px-4">
                <div class="d-flex align-items-center gap-3 overflow-hidden">
                    <span class="badge bg-danger rounded-pill px-3 py-1"><i class="fa fa-file-pdf me-1"></i> PDF DOCUMENT</span>
                    <div>
                        <h5 class="modal-title fw-bold text-truncate text-white mb-0" id="dashPdfModalTitle">PDF Reader</h5>
                        <small class="text-white-50" id="dashPdfModalCourse"></small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <a href="#" target="_blank" id="dashPdfModalFullscreen" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="fa fa-expand me-1"></i> Fullscreen
                    </a>
                    <a href="#" download id="dashPdfModalDownload" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                        <i class="fa fa-download me-1"></i> Download
                    </a>
                    <a href="#" id="dashPdfModalGoLesson" class="btn btn-sm btn-outline-light rounded-pill px-3">
                        <i class="fa fa-chalkboard-user me-1"></i> Classroom
                    </a>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0" style="height: 82vh; background: #525659;">
                <iframe id="dashPdfModalIframe" src="" class="w-100 h-100 border-0" title="PDF Viewer"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Client-side Tab Switcher with URL hash/query sync
    const pills = document.querySelectorAll('.dash-nav-pill');
    const panes = document.querySelectorAll('.dash-tab-pane');

    pills.forEach(pill => {
        pill.addEventListener('click', (e) => {
            const targetId = pill.dataset.target;
            if (!targetId) return;

            // Update pills
            pills.forEach(p => {
                p.classList.remove('active');
                const badge = p.querySelector('.badge');
                if (badge) {
                    badge.className = 'badge bg-light text-dark border rounded-pill';
                }
            });
            pill.classList.add('active');
            const activeBadge = pill.querySelector('.badge');
            if (activeBadge) {
                activeBadge.className = 'badge bg-white text-primary rounded-pill';
            }

            // Update panes
            panes.forEach(pane => pane.classList.remove('active'));
            const targetPane = document.getElementById(targetId);
            if (targetPane) targetPane.classList.add('active');

            // Update URL without full refresh
            const tabName = targetId.replace('tab-', '');
            const newUrl = window.location.pathname + '?tab=' + tabName;
            window.history.pushState({ path: newUrl }, '', newUrl);
            e.preventDefault();
        });
    });

    // PDF Viewer Modal setup
    const pdfButtons = document.querySelectorAll('.open-pdf-viewer');
    const modalEl = document.getElementById('dashboardPdfModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl);
        const iframe = document.getElementById('dashPdfModalIframe');
        const titleEl = document.getElementById('dashPdfModalTitle');
        const courseEl = document.getElementById('dashPdfModalCourse');
        const fullscreenLink = document.getElementById('dashPdfModalFullscreen');
        const downloadLink = document.getElementById('dashPdfModalDownload');
        const lessonLink = document.getElementById('dashPdfModalGoLesson');

        pdfButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const url = btn.dataset.url;
                const title = btn.dataset.title;
                const course = btn.dataset.course;
                const lesson = btn.dataset.lesson;

                titleEl.textContent = title;
                courseEl.textContent = course;
                fullscreenLink.href = url;
                downloadLink.href = url;
                lessonLink.href = lesson;
                iframe.src = url + '#toolbar=1&navpanes=1';

                modal.show();
            });
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            iframe.src = '';
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
