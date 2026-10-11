<?php
require_once __DIR__ . '/includes/db.php';

$slug = trim($_GET['slug'] ?? '');
$id = intval($_GET['id'] ?? 0);

// Fetch course details
if (!empty($slug)) {
    $stmt = $con->prepare("
        SELECT c.*, cat.name as category_name 
        FROM lms_courses c 
        LEFT JOIN lms_categories cat ON c.category_id = cat.id 
        WHERE c.slug = ? AND c.status = 1 LIMIT 1
    ");
    $stmt->bind_param("s", $slug);
} else {
    $stmt = $con->prepare("
        SELECT c.*, cat.name as category_name 
        FROM lms_courses c 
        LEFT JOIN lms_categories cat ON c.category_id = cat.id 
        WHERE c.id = ? AND c.status = 1 LIMIT 1
    ");
    $stmt->bind_param("i", $id);
}

$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    header("Location: index");
    exit();
}

$page_title = $course['title'];
include 'includes/header.php';

// Check if currently logged in student is enrolled
$is_enrolled = false;
if ($is_logged_in) {
    $chk_sql = "SELECT id FROM lms_enrollments WHERE user_id = $student_id AND course_id = {$course['id']} AND status = 'completed'";
    $chk_res = mysqli_query($con, $chk_sql);
    $is_enrolled = (mysqli_num_rows($chk_res) > 0);
}

// Fetch Lessons
$lessons_q = mysqli_query($con, "SELECT * FROM lms_lessons WHERE course_id = {$course['id']} ORDER BY sort_order ASC, id ASC");
$lessons_count = mysqli_num_rows($lessons_q);
?>

<div class="course-details-wrap py-5 bg-light min-vh-100">
    <div class="container py-2">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index" class="text-decoration-none">Courses</a></li>
                <li class="breadcrumb-item"><a href="categories?id=<?php echo $course['category_id']; ?>" class="text-decoration-none"><?php echo htmlspecialchars($course['category_name'] ?? 'Category'); ?></a></li>
                <li class="breadcrumb-item active text-truncate" style="max-width: 300px;"><?php echo htmlspecialchars($course['title']); ?></li>
            </ol>
        </nav>

        <div class="row g-5">
            <!-- Main Content Column -->
            <div class="col-lg-8 order-2 order-lg-1">
                <div class="bg-white p-4 p-md-5 rounded-4 border shadow-sm mb-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-2 fw-bold">
                            <?php echo htmlspecialchars($course['category_name'] ?? 'Computer Education'); ?>
                        </span>
                        <?php if ($is_enrolled): ?>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-bold">
                                <i class="fa fa-circle-check me-1"></i> YOU ARE ENROLLED
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_GET['notice']) && $_GET['notice'] === 'enroll_required'): ?>
                        <div class="alert alert-warning border-0 rounded-3 shadow-sm d-flex align-items-center gap-3 mb-4 p-3" role="alert">
                            <i class="fa fa-lock fs-4 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-0">Enrollment Required</h6>
                                <small>This lecture is locked. Please enroll in the course to unlock full access to all lectures, notes, and certificates.</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h1 class="fw-800 display-6 mb-3 text-dark"><?php echo htmlspecialchars($course['title']); ?></h1>
                    <p class="lead text-muted mb-4" style="font-size: 1.1rem; line-height: 1.7;"><?php echo htmlspecialchars($course['short_desc']); ?></p>

                    <!-- Instructor & Level Strip -->
                    <div class="d-flex flex-wrap gap-4 py-3 border-top border-bottom mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa fa-user-tie fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">INSTRUCTOR</small>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($course['instructor']); ?></span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info bg-opacity-10 text-info p-2 rounded-circle" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa fa-signal fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">LEVEL</small>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($course['level']); ?></span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-success bg-opacity-10 text-success p-2 rounded-circle" style="width: 44px; height: 44px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa fa-clock fs-5"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size: 0.75rem;">DURATION</small>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($course['duration'] ?: 'Self-Paced'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Long Description -->
                    <div class="mb-5">
                        <h4 class="fw-800 mb-3 text-dark">About This Course</h4>
                        <div class="text-muted" style="line-height: 1.8; font-size: 1rem;">
                            <?php echo !empty($course['long_desc']) ? nl2br(htmlspecialchars($course['long_desc'])) : nl2br(htmlspecialchars($course['short_desc'])); ?>
                        </div>
                    </div>

                    <!-- Curriculum List -->
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h4 class="fw-800 mb-0 text-dark">Course Curriculum</h4>
                                <small class="text-muted"><?php echo $lessons_count; ?> lectures in this program</small>
                            </div>
                            <?php if ($is_enrolled): ?>
                                <a href="learn?slug=<?php echo urlencode($course['slug']); ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                                    <i class="fa fa-play me-1"></i> Open Classroom
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="border rounded-4 overflow-hidden bg-white">
                            <?php if ($lessons_count > 0): 
                                $idx = 1;
                                while ($lesson = mysqli_fetch_assoc($lessons_q)):
                                    $is_preview = ($lesson['is_preview'] == 1);
                            ?>
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center hover-bg">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="rounded-circle <?php echo ($is_enrolled || $is_preview) ? 'bg-primary bg-opacity-10 text-primary' : 'bg-light text-muted'; ?> d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <?php 
                                            $l_type = $lesson['content_type'];
                                            if ($l_type == 'pdf' || preg_match('/\.pdf(\?.*)?$/i', $lesson['content_url'] ?? '')): ?>
                                                <i class="fa fa-file-pdf <?php echo ($is_enrolled || $is_preview) ? 'text-danger' : 'text-muted'; ?>"></i>
                                            <?php elseif ($l_type == 'image' || preg_match('/\.(png|jpe?g|webp|gif)(\?.*)?$/i', $lesson['content_url'] ?? '')): ?>
                                                <i class="fa fa-image <?php echo ($is_enrolled || $is_preview) ? 'text-success' : 'text-muted'; ?>"></i>
                                            <?php elseif ($l_type == 'file'): ?>
                                                <i class="fa fa-paperclip <?php echo ($is_enrolled || $is_preview) ? 'text-info' : 'text-muted'; ?>"></i>
                                            <?php elseif ($l_type == 'text'): ?>
                                                <i class="fa fa-file-lines <?php echo ($is_enrolled || $is_preview) ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php else: ?>
                                                <i class="fa fa-play-circle <?php echo ($is_enrolled || $is_preview) ? 'text-primary' : 'text-muted'; ?>"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small mb-0">
                                                <?php echo htmlspecialchars($lesson['title']); ?>
                                                <?php if ($is_preview && !$is_enrolled): ?>
                                                    <span class="badge bg-success bg-opacity-10 text-success ms-1 small">Trial Lecture</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($lesson['duration'])): ?>
                                                <small class="text-muted" style="font-size:0.75rem;"><i class="fa fa-clock me-1"></i><?php echo htmlspecialchars($lesson['duration']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div>
                                        <?php if ($is_enrolled): ?>
                                            <a href="learn?slug=<?php echo urlencode($course['slug']); ?>&lesson=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold">
                                                <i class="fa fa-play me-1"></i> Watch
                                            </a>
                                        <?php elseif ($is_preview): ?>
                                            <a href="learn?slug=<?php echo urlencode($course['slug']); ?>&lesson=<?php echo $lesson['id']; ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold shadow-sm">
                                                <i class="fa fa-play-circle me-1"></i> Start Trial
                                            </a>
                                        <?php else: ?>
                                            <a href="checkout?id=<?php echo $course['id']; ?>" class="badge bg-light text-muted border text-decoration-none px-3 py-2 rounded-pill fw-semibold" title="Enroll to unlock this lecture">
                                                <i class="fa fa-lock me-1 text-danger"></i> Locked
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php $idx++; endwhile; else: ?>
                                <div class="p-4 text-center text-muted">
                                    <i class="fa fa-circle-play fs-2 mb-2 d-block opacity-25"></i>
                                    Curriculum lectures are being updated by the faculty.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Sidebar Card -->
            <div class="col-lg-4 order-1 order-lg-2">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden sticky-top" style="top: 90px;">
                    <div class="position-relative ratio ratio-16x9">
                        <img src="<?php echo htmlspecialchars($course['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=600'); ?>" 
                             alt="<?php echo htmlspecialchars($course['title']); ?>" class="object-fit-cover">
                        
                        <?php if (!empty($course['preview_video'])): ?>
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-25">
                                <a href="<?php echo htmlspecialchars($course['preview_video']); ?>" target="_blank" class="btn btn-light rounded-circle shadow-lg d-flex align-items-center justify-content-center text-danger" style="width: 58px; height: 58px;">
                                    <i class="fa fa-play fs-4 ps-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <?php if ($course['is_free']): ?>
                                <h2 class="fw-800 text-success mb-0">FREE</h2>
                            <?php else: ?>
                                <h2 class="fw-800 text-dark mb-0">₹<?php echo number_format($course['price'], 0); ?></h2>
                                <?php if ($course['discount_price'] > 0): ?>
                                    <del class="text-muted fs-5">₹<?php echo number_format($course['discount_price'], 0); ?></del>
                                    <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-2 py-1 small fw-bold">SAVE ₹<?php echo number_format($course['discount_price'] - $course['price'], 0); ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_enrolled): ?>
                            <a href="learn?slug=<?php echo urlencode($course['slug']); ?>" class="btn btn-success w-100 py-3 rounded-pill fw-bold shadow mb-3 fs-5">
                                <i class="fa fa-play-circle me-2"></i> Go to Classroom
                            </a>
                            <p class="text-center text-success small mb-0"><i class="fa fa-check-circle me-1"></i> You have lifetime access</p>
                        <?php else: ?>
                            <a href="checkout?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow mb-3 fs-5" style="background: var(--primary); border:none;">
                                <i class="fa fa-shopping-cart me-2"></i> <?php echo $course['is_free'] ? 'Enroll for Free' : 'Enroll Now'; ?>
                            </a>
                            <p class="text-center text-muted small mb-0"><i class="fa fa-shield-alt me-1"></i> Instant Access & 100% Verified Certificate</p>
                        <?php endif; ?>

                        <hr class="my-4">

                        <h6 class="fw-800 mb-3 text-dark">This course includes:</h6>
                        <ul class="list-unstyled d-grid gap-2 small text-muted mb-0">
                            <li><i class="fa fa-play-circle text-primary me-2"></i> <?php echo $lessons_count; ?> on-demand video lectures</li>
                            <li><i class="fa fa-file-arrow-down text-info me-2"></i> Practical downloadable files & notes</li>
                            <li><i class="fa fa-mobile-screen text-success me-2"></i> Mobile phone & computer access</li>
                            <li><i class="fa fa-certificate text-warning me-2"></i> Verifiable completion certificate</li>
                            <li><i class="fa fa-infinity text-dark me-2"></i> Lifetime unrestricted access</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>