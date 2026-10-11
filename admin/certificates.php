<?php
/**
 * LKVM LMS Admin - Certificate Management
 * View, search, revoke, and reactivate certificates.
 */
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

lms_ensure_certificates_table($con);

$msg = '';
$msg_type = 'success';

// Handle Revoke
if (isset($_POST['revoke_cert'])) {
    $cert_id = intval($_POST['cert_id'] ?? 0);
    $reason = trim($_POST['revoke_reason'] ?? '');
    if ($cert_id > 0) {
        lms_revoke_certificate($con, $cert_id, $reason);
        $msg = "Certificate has been revoked.";
        $msg_type = "warning";
    }
}

// Handle Reactivate
if (isset($_GET['reactivate'])) {
    $cert_id = intval($_GET['reactivate']);
    if ($cert_id > 0) {
        lms_reactivate_certificate($con, $cert_id);
        $msg = "Certificate has been reactivated.";
        $msg_type = "success";
    }
}

// Handle Manual Issue
if (isset($_POST['manual_issue'])) {
    $mi_user_id = intval($_POST['m_user_id'] ?? 0);
    $mi_course_id = intval($_POST['m_course_id'] ?? 0);
    if ($mi_user_id > 0 && $mi_course_id > 0) {
        // Check enrollment
        $enroll_check = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $mi_user_id AND course_id = $mi_course_id AND status = 'completed'");
        if (mysqli_num_rows($enroll_check) > 0) {
            $existing = lms_get_certificate($con, $mi_user_id, $mi_course_id);
            if ($existing) {
                $msg = "Certificate already exists for this student-course combination (ID: " . $existing['certificate_uid'] . ").";
                $msg_type = "info";
            } else {
                // Force-issue even without 100% completion
                $student_res = mysqli_query($con, "SELECT name, email FROM lms_students WHERE id = $mi_user_id LIMIT 1");
                $student = mysqli_fetch_assoc($student_res);
                $course_res = mysqli_query($con, "SELECT title, instructor, certificate_title, certificate_signatory_name FROM lms_courses WHERE id = $mi_course_id LIMIT 1");
                $course_info = mysqli_fetch_assoc($course_res);
                
                if ($student && $course_info) {
                    $cert_uid = lms_generate_certificate_uid($con);
                    $completion = lms_check_course_completion($con, $mi_user_id, $mi_course_id);
                    $issue_date = date('Y-m-d');
                    $percent = $completion['percent'];
                    $c_title = !empty($course_info['certificate_title']) ? $course_info['certificate_title'] : $course_info['title'];
                    $c_instructor = !empty($course_info['certificate_signatory_name']) ? $course_info['certificate_signatory_name'] : ($course_info['instructor'] ?? 'LKVM Faculty');
                    
                    $stmt = $con->prepare("INSERT INTO lms_certificates 
                        (certificate_uid, user_id, course_id, student_name, student_email, course_title, instructor_name, issue_date, completion_percent) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("siisssssd", 
                        $cert_uid, $mi_user_id, $mi_course_id,
                        $student['name'], $student['email'],
                        $c_title, $c_instructor,
                        $issue_date, $percent
                    );
                    if ($stmt->execute()) {
                        $msg = "Certificate issued manually! ID: " . $cert_uid;
                    } else {
                        $msg = "Failed to issue certificate.";
                        $msg_type = "danger";
                    }
                    $stmt->close();
                } else {
                    $msg = "Invalid student or course.";
                    $msg_type = "danger";
                }
            }
        } else {
            $msg = "Student is not enrolled in this course.";
            $msg_type = "danger";
        }
    }
}

// Flash from GET
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Stats
$stats = lms_certificate_stats($con);

// Search & Filters
$search = trim($_GET['s'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$course_filter = intval($_GET['course_id'] ?? 0);

$where = "WHERE 1=1";
if (!empty($search)) {
    $s_esc = mysqli_real_escape_string($con, $search);
    $where .= " AND (c.certificate_uid LIKE '%$s_esc%' OR c.student_name LIKE '%$s_esc%' OR c.student_email LIKE '%$s_esc%' OR c.course_title LIKE '%$s_esc%')";
}
if (!empty($status_filter)) {
    $st_esc = mysqli_real_escape_string($con, $status_filter);
    $where .= " AND c.status = '$st_esc'";
}
if ($course_filter > 0) {
    $where .= " AND c.course_id = $course_filter";
}

$certs_res = mysqli_query($con, "
    SELECT c.*, co.slug as course_slug 
    FROM lms_certificates c 
    LEFT JOIN lms_courses co ON c.course_id = co.id
    $where 
    ORDER BY c.created_at DESC
    LIMIT 200
");

// Fetch courses for filter dropdown
$courses_list = mysqli_query($con, "SELECT id, title FROM lms_courses ORDER BY title ASC");

// Fetch enrolled students for manual issue
$enrolled_students = mysqli_query($con, "
    SELECT DISTINCT s.id, s.name, s.email 
    FROM lms_students s 
    JOIN lms_enrollments e ON e.user_id = s.id 
    WHERE e.status = 'completed' 
    ORDER BY s.name ASC
");

$page_title = 'Certificates';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-<?php echo $msg_type === 'success' ? 'circle-check' : ($msg_type === 'warning' ? 'exclamation-triangle' : ($msg_type === 'info' ? 'info-circle' : 'times-circle')); ?> me-2"></i> 
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-gradient-primary">
                <i class="fa fa-award"></i>
            </div>
            <div class="fs-3 fw-800 text-dark"><?php echo $stats['total']; ?></div>
            <div class="text-muted small fw-semibold">Total Certificates</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-gradient-success">
                <i class="fa fa-check-circle"></i>
            </div>
            <div class="fs-3 fw-800 text-dark"><?php echo $stats['active']; ?></div>
            <div class="text-muted small fw-semibold">Active Certificates</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-gradient-warning">
                <i class="fa fa-ban"></i>
            </div>
            <div class="fs-3 fw-800 text-dark"><?php echo $stats['revoked']; ?></div>
            <div class="text-muted small fw-semibold">Revoked</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card-premium">
            <div class="stat-icon-box bg-gradient-info">
                <i class="fa fa-calendar-check"></i>
            </div>
            <div class="fs-3 fw-800 text-dark"><?php echo $stats['this_month']; ?></div>
            <div class="text-muted small fw-semibold">This Month</div>
        </div>
    </div>
</div>

<!-- Toolbar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-800 mb-1">Certificate Records</h4>
        <p class="text-muted small mb-0">View, search, revoke, or manually issue certificates</p>
    </div>
    <button class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#manualIssueModal">
        <i class="fa fa-plus me-2"></i> Issue Certificate Manually
    </button>
</div>

<!-- Search & Filter -->
<div class="admin-card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="s" class="form-control rounded-3" placeholder="Search by ID, name, email, course..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select rounded-3">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="revoked" <?php echo $status_filter === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="course_id" class="form-select rounded-3">
                <option value="">All Courses</option>
                <?php 
                    mysqli_data_seek($courses_list, 0);
                    while ($c = mysqli_fetch_assoc($courses_list)): 
                ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $course_filter == $c['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary rounded-3 flex-fill"><i class="fa fa-search"></i></button>
            <a href="certificates" class="btn btn-outline-secondary rounded-3"><i class="fa fa-refresh"></i></a>
        </div>
    </form>
</div>

<!-- Certificates Table -->
<div class="admin-card overflow-auto">
    <table class="table table-premium mb-0">
        <thead>
            <tr>
                <th>Certificate ID</th>
                <th>Student</th>
                <th>Course</th>
                <th>Issued</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($certs_res) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($certs_res)): 
                    $is_active = ($row['status'] === 'active');
                ?>
                <tr>
                    <td>
                        <span class="fw-bold" style="font-family: 'Courier New', monospace; letter-spacing: 1px; font-size: 0.85rem;">
                            <?php echo htmlspecialchars($row['certificate_uid']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['student_name']); ?></div>
                        <small class="text-muted"><?php echo htmlspecialchars($row['student_email'] ?? ''); ?></small>
                    </td>
                    <td>
                        <div class="fw-semibold" style="max-width: 200px;"><?php echo htmlspecialchars($row['course_title']); ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?php echo date('d M Y', strtotime($row['issue_date'])); ?></div>
                    </td>
                    <td>
                        <?php if ($is_active): ?>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 fw-bold small">
                                <i class="fa fa-check-circle me-1"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1 fw-bold small">
                                <i class="fa fa-times-circle me-1"></i> Revoked
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <a href="../certificate-view?uid=<?php echo urlencode($row['certificate_uid']); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-3" title="View Certificate">
                                <i class="fa fa-eye"></i>
                            </a>
                            <?php if ($is_active): ?>
                                <button class="btn btn-sm btn-outline-danger rounded-3" title="Revoke" onclick="openRevokeModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['certificate_uid']); ?>', '<?php echo addslashes($row['student_name']); ?>')">
                                    <i class="fa fa-ban"></i>
                                </button>
                            <?php else: ?>
                                <a href="certificates?reactivate=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-success rounded-3" title="Reactivate" onclick="return confirm('Reactivate this certificate?')">
                                    <i class="fa fa-redo"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fa fa-award fs-1 mb-3 d-block opacity-25"></i>
                            <div class="fw-bold">No certificates found</div>
                            <small>Certificates are auto-issued when students complete 100% of a course.</small>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Revoke Modal -->
<div class="modal fade" id="revokeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-800 text-danger"><i class="fa fa-exclamation-triangle me-2"></i>Revoke Certificate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <p class="text-muted">You are about to revoke certificate <strong id="revokeUid"></strong> issued to <strong id="revokeName"></strong>. This action can be undone later.</p>
                    <input type="hidden" name="cert_id" id="revokeCertId">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Reason for Revocation</label>
                        <textarea name="revoke_reason" class="form-control rounded-3" rows="3" placeholder="Provide reason for audit trail..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="revoke_cert" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="fa fa-ban me-1"></i> Revoke Certificate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Manual Issue Modal -->
<div class="modal fade" id="manualIssueModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header border-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-800"><i class="fa fa-plus-circle text-primary me-2"></i>Issue Certificate Manually</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="alert alert-info rounded-3 small">
                        <i class="fa fa-info-circle me-1"></i> 
                        Manual issue is for special cases. Certificates are normally auto-issued when a student completes 100% of lessons.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Student (Enrolled Only)</label>
                        <select name="m_user_id" class="form-select rounded-3" required>
                            <option value="">-- Select Student --</option>
                            <?php 
                                mysqli_data_seek($enrolled_students, 0);
                                while ($s = mysqli_fetch_assoc($enrolled_students)): 
                            ?>
                                <option value="<?php echo $s['id']; ?>">
                                    <?php echo htmlspecialchars($s['name'] . ' (' . $s['email'] . ')'); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Select Course</label>
                        <select name="m_course_id" class="form-select rounded-3" required>
                            <option value="">-- Select Course --</option>
                            <?php 
                                mysqli_data_seek($courses_list, 0);
                                while ($c = mysqli_fetch_assoc($courses_list)): 
                            ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['title']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="manual_issue" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="fa fa-award me-1"></i> Issue Certificate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openRevokeModal(certId, uid, name) {
    document.getElementById('revokeCertId').value = certId;
    document.getElementById('revokeUid').textContent = uid;
    document.getElementById('revokeName').textContent = name;
    new bootstrap.Modal(document.getElementById('revokeModal')).show();
}
</script>

<?php include 'includes/footer.php'; ?>
