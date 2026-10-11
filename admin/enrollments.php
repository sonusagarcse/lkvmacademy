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

// Handle Delete Enrollment
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    mysqli_query($con, "DELETE FROM lms_enrollments WHERE id = $del_id");
    lms_redirect("enrollments?msg=" . urlencode("Enrollment record removed!"));
}

// Handle Status Change (e.g. approve pending payment)
if (isset($_GET['set_status']) && isset($_GET['id'])) {
    $eid = intval($_GET['id']);
    $new_status = mysqli_real_escape_string($con, $_GET['set_status']);
    mysqli_query($con, "UPDATE lms_enrollments SET status = '$new_status' WHERE id = $eid");
    lms_redirect("enrollments?msg=" . urlencode("Enrollment status updated to $new_status!"));
}

// Handle Manual Enrollment
if (isset($_POST['manual_enroll'])) {
    $student_id = intval($_POST['student_id'] ?? 0);
    $course_id = intval($_POST['course_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'offline');
    $pay_id = trim($_POST['payment_id'] ?? ('MANUAL_' . time()));
    $status = trim($_POST['status'] ?? 'completed');

    if ($student_id <= 0 || $course_id <= 0) {
        $msg = "Error: Please select both a student and a course.";
    } else {
        // Check if already enrolled
        $check = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $student_id AND course_id = $course_id");
        if (mysqli_num_rows($check) > 0) {
            $msg = "Notice: This student is already enrolled in this course. You can update their enrollment status below.";
        } else {
            $stmt = $con->prepare("INSERT INTO lms_enrollments (user_id, course_id, amount, payment_id, payment_method, status) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iidsss", $student_id, $course_id, $amount, $pay_id, $method, $status);
                $stmt->execute();
                $stmt->close();
                lms_redirect("enrollments?msg=" . urlencode("Student enrolled successfully!"));
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Filters
$status_filter = trim($_GET['status'] ?? '');
$user_filter = intval($_GET['user_id'] ?? 0);
$where_clauses = ["1=1"];
if (!empty($status_filter)) {
    $status_esc = mysqli_real_escape_string($con, $status_filter);
    $where_clauses[] = "e.status = '$status_esc'";
}
if ($user_filter > 0) {
    $where_clauses[] = "e.user_id = $user_filter";
}
$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Metrics
$stats = mysqli_fetch_assoc(mysqli_query($con, "
    SELECT 
        COUNT(CASE WHEN status='completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status='pending' THEN 1 END) as pending_count,
        SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) as total_revenue
    FROM lms_enrollments
"));

$page_title = 'Manage Enrollments & Orders';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-info alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-info-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Toolbar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-800 mb-1">Enrollments & Orders</h4>
        <p class="text-muted small mb-0">Track course sales, verify offline student payments, and manage admissions</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#manualEnrollModal">
        <i class="fa fa-plus-circle me-2"></i> Manual Student Enrollment
    </button>
</div>

<!-- Stats Strip -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="admin-card p-4 d-flex align-items-center gap-4">
            <div class="stat-icon-box bg-gradient-success mb-0">
                <i class="fa fa-check-circle"></i>
            </div>
            <div>
                <span class="text-muted small fw-bold text-uppercase">Completed Admissions</span>
                <h3 class="fw-800 mb-0"><?php echo $stats['completed_count'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-4 d-flex align-items-center gap-4">
            <div class="stat-icon-box bg-gradient-warning mb-0">
                <i class="fa fa-hourglass-half"></i>
            </div>
            <div>
                <span class="text-muted small fw-bold text-uppercase">Pending Verification</span>
                <h3 class="fw-800 mb-0"><?php echo $stats['pending_count'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-card p-4 d-flex align-items-center gap-4">
            <div class="stat-icon-box bg-gradient-primary mb-0">
                <i class="fa fa-wallet"></i>
            </div>
            <div>
                <span class="text-muted small fw-bold text-uppercase">Total Collections</span>
                <h3 class="fw-800 mb-0">₹<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="admin-card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-4">
            <select name="status" class="form-select rounded-pill px-3" onchange="this.form.submit()">
                <option value="">All Payment Statuses</option>
                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed (Active Learners)</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                <option value="failed" <?php echo $status_filter == 'failed' ? 'selected' : ''; ?>>Failed / Cancelled</option>
            </select>
        </div>
        <div class="col-md-4">
            <?php if ($user_filter > 0): ?>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    Filtered by Student #<?php echo $user_filter; ?>
                    <a href="enrollments" class="text-danger ms-2"><i class="fa fa-times"></i></a>
                </span>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if (!empty($status_filter) || $user_filter > 0): ?>
                <a href="enrollments" class="btn btn-light rounded-pill px-4">Reset Filter</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Enrollments Table -->
<div class="admin-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-premium mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Order / Enr ID</th>
                    <th>Student Info</th>
                    <th>Enrolled Course</th>
                    <th>Amount & Mode</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $enroll_q = mysqli_query($con, "
                    SELECT e.*, c.title as course_title, c.slug as course_slug,
                           s.name as student_name, s.email as student_email, s.phone as student_phone 
                    FROM lms_enrollments e 
                    LEFT JOIN lms_courses c ON e.course_id = c.id 
                    LEFT JOIN lms_students s ON e.user_id = s.id 
                    $where_sql 
                    ORDER BY e.id DESC
                ");

                if ($enroll_q && mysqli_num_rows($enroll_q) > 0):
                    while ($enr = mysqli_fetch_assoc($enroll_q)):
                ?>
                <tr>
                    <td class="ps-4">
                        <span class="fw-bold text-dark">#LMS-<?php echo str_pad($enr['id'], 4, '0', STR_PAD_LEFT); ?></span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($enr['student_name'] ?? 'User'); ?>&background=random" class="rounded-circle" style="width: 32px; height: 32px;">
                            <div>
                                <div class="fw-bold small text-dark"><?php echo htmlspecialchars($enr['student_name'] ?? 'Student #' . $enr['user_id']); ?></div>
                                <small class="text-muted" style="font-size: 0.72rem;"><?php echo htmlspecialchars($enr['student_email'] ?? ''); ?></small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="../details?slug=<?php echo urlencode($enr['course_slug'] ?? ''); ?>" target="_blank" class="fw-semibold text-primary text-decoration-none small">
                            <?php echo htmlspecialchars($enr['course_title'] ?? 'Course #' . $enr['course_id']); ?>
                        </a>
                    </td>
                    <td>
                        <div class="fw-800 text-dark">₹<?php echo number_format($enr['amount'] ?? 0, 0); ?></div>
                        <small class="text-muted text-uppercase" style="font-size:0.7rem;">
                            <?php echo htmlspecialchars($enr['payment_method'] ?? 'online'); ?>
                            <?php if (!empty($enr['payment_id'])): ?>
                                • <span title="<?php echo htmlspecialchars($enr['payment_id']); ?>"><?php echo substr($enr['payment_id'], 0, 12); ?>...</span>
                            <?php endif; ?>
                        </small>
                    </td>
                    <td>
                        <small class="text-muted"><?php echo date('M d, Y', strtotime($enr['created_at'])); ?></small>
                    </td>
                    <td>
                        <?php if ($enr['status'] == 'completed'): ?>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">
                                <i class="fa fa-check me-1"></i> Completed
                            </span>
                        <?php elseif ($enr['status'] == 'pending'): ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 py-1 small fw-bold">
                                <i class="fa fa-clock me-1"></i> Pending
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 small fw-bold">
                                <?php echo ucfirst($enr['status']); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <div class="btn-group">
                            <?php if ($enr['status'] != 'completed'): ?>
                                <a href="enrollments?id=<?php echo $enr['id']; ?>&set_status=completed" class="btn btn-sm btn-success rounded-3 me-1" title="Approve & Grant Full Access">
                                    <i class="fa fa-check"></i> Approve
                                </a>
                            <?php endif; ?>
                            <a href="enrollments?delete=<?php echo $enr['id']; ?>" class="btn btn-sm btn-light text-danger" title="Delete Enrollment" onclick="return confirm('Delete this enrollment record?')">
                                <i class="fa fa-trash-alt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="fa fa-receipt fs-1 d-block mb-3 opacity-25"></i>
                        No enrollments found matching the criteria.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Manual Enrollment -->
<div class="modal fade" id="manualEnrollModal" tabindex="-1" aria-labelledby="manualEnrollModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-bottom p-4">
                <h5 class="modal-title fw-800" id="manualEnrollModalLabel">Enroll Student Manually</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Select Student *</label>
                        <select name="student_id" class="form-select" required>
                            <option value="">Choose Student</option>
                            <?php
                            $students_opt = mysqli_query($con, "SELECT id, name, email FROM lms_students ORDER BY name ASC");
                            while ($s = mysqli_fetch_assoc($students_opt)) {
                                echo "<option value='{$s['id']}'>" . htmlspecialchars($s['name']) . " (" . htmlspecialchars($s['email']) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Select Course *</label>
                        <select name="course_id" id="manual_course_id" class="form-select" required onchange="updatePrice(this)">
                            <option value="">Choose Course</option>
                            <?php
                            $courses_opt = mysqli_query($con, "SELECT id, title, price, is_free FROM lms_courses ORDER BY title ASC");
                            while ($co = mysqli_fetch_assoc($courses_opt)) {
                                $price_val = $co['is_free'] ? 0 : $co['price'];
                                echo "<option value='{$co['id']}' data-price='{$price_val}'>" . htmlspecialchars($co['title']) . " (₹" . number_format($price_val, 0) . ")</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Amount Paid (₹)</label>
                        <input type="number" step="0.01" name="amount" id="manual_amount" class="form-control" value="0.00">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Payment Mode</label>
                            <select name="payment_method" class="form-select">
                                <option value="offline_cash">Counter Cash</option>
                                <option value="upi">UPI / QR Code</option>
                                <option value="bank_transfer">Bank NEFT/IMPS</option>
                                <option value="scholarship">Free / Scholarship</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="completed">Completed (Grant Access)</option>
                                <option value="pending">Pending Verification</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Receipt / Transaction Ref (Optional)</label>
                        <input type="text" name="payment_id" class="form-control" placeholder="e.g. REC-2026-001 or UTR#">
                    </div>
                </div>
                <div class="modal-footer border-top p-3 bg-light">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="manual_enroll" class="btn btn-primary rounded-pill px-4 fw-bold">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePrice(sel) {
    const selectedOption = sel.options[sel.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || '0.00';
    document.getElementById('manual_amount').value = price;
}
</script>

<?php include 'includes/footer.php'; ?>
