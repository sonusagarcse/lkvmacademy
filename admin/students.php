<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$msg = "";
$view_student_id = intval($_GET['view'] ?? 0);

// Handle Delete Student
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    mysqli_query($con, "DELETE FROM lms_students WHERE id = $del_id");
    mysqli_query($con, "DELETE FROM lms_enrollments WHERE user_id = $del_id");
    mysqli_query($con, "DELETE FROM lms_progress WHERE user_id = $del_id");
    lms_redirect("students?msg=" . urlencode("Student and all related enrollment data removed!"));
}

// Handle Toggle Status
if (isset($_GET['toggle_status'])) {
    $tid = intval($_GET['toggle_status']);
    mysqli_query($con, "UPDATE lms_students SET status = IF(status=1, 0, 1) WHERE id = $tid");
    lms_redirect("students?msg=" . urlencode("Student status updated!"));
}

// Handle Add Student Manually
if (isset($_POST['add_student'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password_raw = trim($_POST['password'] ?? '123456');
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // Check email uniqueness
    $check = mysqli_query($con, "SELECT id FROM lms_students WHERE email = '" . mysqli_real_escape_string($con, $email) . "'");
    if (mysqli_num_rows($check) > 0) {
        $msg = "Error: A student with this email address already exists!";
    } else {
        $stmt = $con->prepare("INSERT INTO lms_students (name, email, password, phone, status) VALUES (?, ?, ?, ?, 1)");
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $email, $password, $phone);
            $stmt->execute();
            $new_sid = $stmt->insert_id;
            $stmt->close();

            // Optional: Auto-enroll in a course if selected
            $initial_course_id = intval($_POST['course_id'] ?? 0);
            if ($initial_course_id > 0) {
                $c_res = mysqli_query($con, "SELECT price FROM lms_courses WHERE id = $initial_course_id");
                $c_price = mysqli_fetch_assoc($c_res)['price'] ?? 0.00;
                $pay_id = "MANUAL_ADMISSION_" . time();
                mysqli_query($con, "INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) 
                                    VALUES ($new_sid, $initial_course_id, $c_price, '$pay_id', 'manual', 'completed')");
            }

            lms_redirect("students?msg=" . urlencode("Student registered successfully!"));
        }
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$search = trim($_GET['s'] ?? '');
$search_sql = "";
if (!empty($search)) {
    $s_esc = mysqli_real_escape_string($con, $search);
    $search_sql = "WHERE (name LIKE '%$s_esc%' OR email LIKE '%$s_esc%' OR phone LIKE '%$s_esc%')";
}

$page_title = 'Manage Students';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-info alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-info-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Bar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-800 mb-1">Student Directory</h4>
        <p class="text-muted small mb-0">View registered students, enrollments, and learning activity</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fa fa-user-plus me-2"></i> Register Student Manually
        </button>
    </div>
</div>

<!-- Search Bar -->
<div class="admin-card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                <input type="text" name="s" class="form-control border-start-0" placeholder="Search students by name, email, or mobile number..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-dark w-100 rounded-pill fw-semibold">Search</button>
            <?php if (!empty($search)): ?>
                <a href="students" class="btn btn-light rounded-pill px-3">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="admin-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-premium mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Student</th>
                    <th>Contact Info</th>
                    <th>Courses Enrolled</th>
                    <th>Joined On</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $students_q = mysqli_query($con, "
                    SELECT s.*, 
                           (SELECT COUNT(*) FROM lms_enrollments WHERE user_id = s.id AND status = 'completed') as enroll_count,
                           (SELECT COUNT(*) FROM lms_progress WHERE user_id = s.id) as completed_lessons
                    FROM lms_students s 
                    $search_sql
                    ORDER BY s.id DESC
                ");

                if ($students_q && mysqli_num_rows($students_q) > 0):
                    while ($st = mysqli_fetch_assoc($students_q)):
                ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($st['name'] ?? 'User'); ?>&background=6366f1&color=fff&bold=true" class="rounded-circle shadow-sm" style="width: 40px; height: 40px;">
                            <div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($st['name']); ?></div>
                                <small class="text-muted">Student ID: #<?php echo str_pad($st['id'], 4, '0', STR_PAD_LEFT); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark"><i class="fa fa-envelope text-muted me-2"></i><?php echo htmlspecialchars($st['email']); ?></div>
                        <div class="small text-muted mt-1"><i class="fa fa-phone text-muted me-2"></i><?php echo htmlspecialchars($st['phone'] ?: 'N/A'); ?></div>
                    </td>
                    <td>
                        <a href="enrollments?user_id=<?php echo $st['id']; ?>" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 rounded-pill text-decoration-none">
                            <i class="fa fa-graduation-cap me-1"></i> <?php echo $st['enroll_count']; ?> Courses
                        </a>
                        <div class="text-muted mt-1" style="font-size:0.75rem;">
                            <i class="fa fa-check text-success me-1"></i><?php echo $st['completed_lessons']; ?> lessons finished
                        </div>
                    </td>
                    <td>
                        <span class="small text-muted"><?php echo date('M d, Y', strtotime($st['created_at'])); ?></span>
                    </td>
                    <td>
                        <a href="students?toggle_status=<?php echo $st['id']; ?>" class="text-decoration-none" title="Click to toggle status">
                            <?php if ($st['status'] == 1): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 small fw-bold">Suspended</span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group">
                            <a href="enrollments?action=manual&user_id=<?php echo $st['id']; ?>" class="btn btn-sm btn-light text-primary" title="Enroll in Course">
                                <i class="fa fa-cart-plus"></i>
                            </a>
                            <a href="students?delete=<?php echo $st['id']; ?>" class="btn btn-sm btn-light text-danger" title="Remove Student" onclick="return confirm('Delete this student and their enrollment records?')">
                                <i class="fa fa-trash-alt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fa fa-users fs-1 d-block mb-3 opacity-25"></i>
                        No students found. Use "Register Student Manually" or share course links for public signups!
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Student Manually -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-800" id="addStudentModalLabel">Add Student Manually</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Student Name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Email Address *</label>
                        <input type="email" name="email" class="form-control" required placeholder="student@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Mobile / WhatsApp Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+91 9801664500">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Default Password</label>
                        <input type="text" name="password" class="form-control" value="123456">
                        <small class="text-muted">Student can change this later in their profile.</small>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Enroll in Course Immediately (Optional)</label>
                        <select name="course_id" class="form-select">
                            <option value="0">None (Student will enroll themselves)</option>
                            <?php
                            $c_opts = mysqli_query($con, "SELECT id, title, price FROM lms_courses ORDER BY title ASC");
                            while ($co = mysqli_fetch_assoc($c_opts)) {
                                echo "<option value='{$co['id']}'>" . htmlspecialchars($co['title']) . " (₹" . number_format($co['price'], 0) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_student" class="btn btn-primary rounded-pill px-4 fw-bold">Register Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
