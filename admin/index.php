<?php
$page_title = 'Dashboard Overview';
include 'includes/header.php';

// Fetch stats directly from lkvm_courses
$total_courses = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM lms_courses"))['total'] ?? 0;
$total_lessons = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM lms_lessons"))['total'] ?? 0;
$total_students = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM lms_students"))['total'] ?? 0;
$total_enrolls = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM lms_enrollments WHERE status = 'completed'"))['total'] ?? 0;
$total_revenue = mysqli_fetch_assoc(mysqli_query($con, "SELECT SUM(amount) as total FROM lms_enrollments WHERE status = 'completed'"))['total'] ?? 0;
?>

<!-- Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-premium">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Courses</span>
                    <h2 class="fw-800 mb-0 mt-2"><?php echo $total_courses; ?></h2>
                </div>
                <div class="stat-icon-box bg-gradient-primary">
                    <i class="fa fa-book-open"></i>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <a href="courses" class="text-primary text-decoration-none fw-semibold">
                    Manage Courses <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-premium">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Total Lessons</span>
                    <h2 class="fw-800 mb-0 mt-2"><?php echo $total_lessons; ?></h2>
                </div>
                <div class="stat-icon-box bg-gradient-info">
                    <i class="fa fa-circle-play"></i>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <a href="lessons" class="text-info text-decoration-none fw-semibold">
                    Manage Lessons <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-premium">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Learners</span>
                    <h2 class="fw-800 mb-0 mt-2"><?php echo $total_students; ?></h2>
                </div>
                <div class="stat-icon-box bg-gradient-success">
                    <i class="fa fa-users"></i>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <a href="students" class="text-success text-decoration-none fw-semibold">
                    View Students <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="stat-card-premium">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase">Revenue</span>
                    <h2 class="fw-800 mb-0 mt-2">₹<?php echo number_format($total_revenue, 0); ?></h2>
                </div>
                <div class="stat-icon-box bg-gradient-warning">
                    <i class="fa fa-wallet"></i>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <span class="text-success fw-bold"><i class="fa fa-check-circle me-1"></i><?php echo $total_enrolls; ?> paid orders</span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Action Strip -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <a href="courses?action=add" class="admin-card p-3 text-decoration-none text-dark d-flex align-items-center gap-3 h-100">
            <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa fa-plus fs-5"></i>
            </div>
            <div>
                <div class="fw-bold">New Course</div>
                <small class="text-muted">Launch course</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="lessons" class="admin-card p-3 text-decoration-none text-dark d-flex align-items-center gap-3 h-100">
            <div class="bg-info text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa fa-video fs-5"></i>
            </div>
            <div>
                <div class="fw-bold">Add Lesson</div>
                <small class="text-muted">Upload video/doc</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="categories" class="admin-card p-3 text-decoration-none text-dark d-flex align-items-center gap-3 h-100">
            <div class="bg-success text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa fa-folder-plus fs-5"></i>
            </div>
            <div>
                <div class="fw-bold">Categories</div>
                <small class="text-muted">Organize topics</small>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="enrollments" class="admin-card p-3 text-decoration-none text-dark d-flex align-items-center gap-3 h-100">
            <div class="bg-warning text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="fa fa-user-check fs-5"></i>
            </div>
            <div>
                <div class="fw-bold">Enroll Student</div>
                <small class="text-muted">Manual enrollment</small>
            </div>
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recent Enrollments -->
    <div class="col-xl-8">
        <div class="admin-card overflow-hidden">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                <div>
                    <h5 class="fw-800 mb-0">Recent Enrollments</h5>
                    <small class="text-muted">Latest course purchases and student admissions</small>
                </div>
                <a href="enrollments" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_enrolls = mysqli_query($con, "
                            SELECT e.*, c.title as course_title, s.name as student_name, s.email as student_email 
                            FROM lms_enrollments e 
                            LEFT JOIN lms_courses c ON e.course_id = c.id 
                            LEFT JOIN lms_students s ON e.user_id = s.id 
                            ORDER BY e.id DESC LIMIT 5
                        ");
                        if ($recent_enrolls && mysqli_num_rows($recent_enrolls) > 0):
                            while ($row = mysqli_fetch_assoc($recent_enrolls)):
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['student_name'] ?? 'User'); ?>&background=random" class="rounded-circle" style="width: 32px; height: 32px;">
                                    <div>
                                        <div class="fw-bold small text-dark"><?php echo htmlspecialchars($row['student_name'] ?? 'Student #' . $row['user_id']); ?></div>
                                        <small class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($row['student_email'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($row['course_title'] ?? 'Course #' . $row['course_id']); ?></span>
                            </td>
                            <td>
                                <span class="fw-800 text-dark">₹<?php echo number_format($row['amount'] ?? 0, 0); ?></span>
                            </td>
                            <td>
                                <?php if (($row['status'] ?? '') == 'completed'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 small fw-bold"><?php echo ucfirst($row['status'] ?? 'pending'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></small>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="fa fa-receipt fs-3 d-block mb-2 opacity-25"></i>
                                No enrollments yet. Share your courses to start enrolling students!
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Active Courses Summary -->
    <div class="col-xl-4">
        <div class="admin-card overflow-hidden">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center bg-white">
                <div>
                    <h5 class="fw-800 mb-0">Active Courses</h5>
                    <small class="text-muted">Live courses on academy</small>
                </div>
                <a href="courses" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">Manage</a>
            </div>
            <div class="p-3">
                <?php
                $courses_q = mysqli_query($con, "
                    SELECT c.*, 
                           (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as lesson_count,
                           (SELECT COUNT(*) FROM lms_enrollments WHERE course_id = c.id AND status = 'completed') as student_count
                    FROM lms_courses c 
                    ORDER BY c.id DESC LIMIT 4
                ");
                if ($courses_q && mysqli_num_rows($courses_q) > 0):
                    while ($c = mysqli_fetch_assoc($courses_q)):
                ?>
                <div class="d-flex align-items-center gap-3 p-2 rounded-3 mb-2 hover-bg">
                    <img src="<?php echo htmlspecialchars($c['thumbnail'] ?: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=200'); ?>" 
                         class="rounded-3 object-fit-cover" style="width: 52px; height: 52px;">
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="fw-bold small text-truncate text-dark mb-0"><?php echo htmlspecialchars($c['title']); ?></div>
                        <div class="text-muted d-flex gap-2" style="font-size:0.75rem;">
                            <span><i class="fa fa-play-circle text-primary"></i> <?php echo $c['lesson_count']; ?> lessons</span>
                            <span><i class="fa fa-users text-success"></i> <?php echo $c['student_count']; ?> enrolled</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-bold">
                            <?php echo $c['is_free'] ? 'FREE' : '₹' . number_format($c['price'], 0); ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <p class="text-muted text-center py-4">No courses created yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
