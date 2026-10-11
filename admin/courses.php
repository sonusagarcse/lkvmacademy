<?php
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
lms_ensure_certificates_table($con);
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$action = $_GET['action'] ?? 'list';
$msg = "";
$msg_type = "success";

// Ensure uploads directory exists
$upload_dir = dirname(__DIR__) . '/uploads/courses/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    // Delete associated lessons
    mysqli_query($con, "DELETE FROM lms_lessons WHERE course_id = $del_id");
    // Delete course
    $stmt = $con->prepare("DELETE FROM lms_courses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
    }
    lms_redirect("courses?msg=" . urlencode("Course deleted successfully!"));
}

// Handle Status Toggle
if (isset($_GET['toggle_status'])) {
    $id = intval($_GET['toggle_status']);
    mysqli_query($con, "UPDATE lms_courses SET status = IF(status=1, 0, 1) WHERE id = $id");
    lms_redirect("courses?msg=" . urlencode("Status updated!"));
}

// Handle Save (Add or Edit)
if (isset($_POST['save_course'])) {
    $cid = intval($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }
    
    // Check slug uniqueness
    $slug_check = mysqli_query($con, "SELECT id FROM lms_courses WHERE slug = '" . mysqli_real_escape_string($con, $slug) . "' AND id != $cid");
    if (mysqli_num_rows($slug_check) > 0) {
        $slug = $slug . '-' . time();
    }

    $category_id = intval($_POST['category_id'] ?? 0);
    $instructor = trim($_POST['instructor'] ?? 'LKVM Faculty');
    $level = trim($_POST['level'] ?? 'All Levels');
    $duration = trim($_POST['duration'] ?? '');
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $price = $is_free ? 0.00 : floatval($_POST['price'] ?? 0);
    $discount_price = floatval($_POST['discount_price'] ?? 0);
    if ($discount_price <= 0) $discount_price = null;
    $short_desc = trim($_POST['short_desc'] ?? '');
    $long_desc = trim($_POST['long_desc'] ?? '');
    $preview_video = trim($_POST['preview_video'] ?? '');
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $has_certificate = isset($_POST['has_certificate']) ? 1 : 0;
    $certificate_title = trim($_POST['certificate_title'] ?? '');
    $certificate_signatory_name = trim($_POST['certificate_signatory_name'] ?? '');
    $certificate_signatory_title = trim($_POST['certificate_signatory_title'] ?? '');
    $certificate_template = trim($_POST['certificate_template'] ?? 'classic_gold');

    // Handle Thumbnail
    $thumbnail = trim($_POST['old_thumbnail'] ?? '');
    if (!empty($_POST['thumbnail_url'])) {
        $thumbnail = trim($_POST['thumbnail_url']);
    }

    if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['thumbnail_file']['tmp_name'];
        $file_name = basename($_FILES['thumbnail_file']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($file_ext, $allowed)) {
            $new_name = 'course_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_name)) {
                $thumbnail = 'uploads/courses/' . $new_name;
            }
        }
    }

    if ($cid > 0) {
        // Update
        $stmt = $con->prepare("UPDATE lms_courses SET 
            title = ?, slug = ?, category_id = ?, instructor = ?, level = ?, 
            duration = ?, is_free = ?, price = ?, discount_price = ?, 
            thumbnail = ?, preview_video = ?, short_desc = ?, long_desc = ?, 
            status = ?, is_featured = ?, has_certificate = ?, certificate_title = ?, 
            certificate_signatory_name = ?, certificate_signatory_title = ?, certificate_template = ? 
            WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ssisssidsssssiiissssi", 
                $title, $slug, $category_id, $instructor, $level, 
                $duration, $is_free, $price, $discount_price, 
                $thumbnail, $preview_video, $short_desc, $long_desc, 
                $status, $is_featured, $has_certificate, $certificate_title, 
                $certificate_signatory_name, $certificate_signatory_title, $certificate_template, $cid
            );
            $stmt->execute();
            $stmt->close();
            lms_redirect("courses?msg=" . urlencode("Course updated successfully!"));
        }
    } else {
        // Insert
        $stmt = $con->prepare("INSERT INTO lms_courses 
            (title, slug, category_id, instructor, level, duration, is_free, price, discount_price, thumbnail, preview_video, short_desc, long_desc, status, is_featured, has_certificate, certificate_title, certificate_signatory_name, certificate_signatory_title, certificate_template) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssisssidsssssiiissss", 
                $title, $slug, $category_id, $instructor, $level, 
                $duration, $is_free, $price, $discount_price, 
                $thumbnail, $preview_video, $short_desc, $long_desc, 
                $status, $is_featured, $has_certificate, $certificate_title, 
                $certificate_signatory_name, $certificate_signatory_title, $certificate_template
            );
            $stmt->execute();
            $new_cid = $stmt->insert_id;
            $stmt->close();
            lms_redirect("lessons?course_id=$new_cid&msg=" . urlencode("Course created! Now add your first lessons."));
        }
    }
}

// Fetch Course Data for Edit
$course_data = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'category_id' => 0,
    'instructor' => 'LKVM Faculty',
    'level' => 'All Levels',
    'duration' => '40 Hours',
    'is_free' => 0,
    'price' => '1499.00',
    'discount_price' => '',
    'thumbnail' => '',
    'preview_video' => '',
    'short_desc' => '',
    'long_desc' => '',
    'status' => 1,
    'is_featured' => 0,
    'has_certificate' => 1,
    'certificate_title' => '',
    'certificate_signatory_name' => '',
    'certificate_signatory_title' => 'Director & Academic Head',
    'certificate_template' => 'classic_gold'
];

if ($action == 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $res = mysqli_query($con, "SELECT * FROM lms_courses WHERE id = $edit_id LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $course_data = mysqli_fetch_assoc($res);
    }
}

// Flash message
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$page_title = 'Manage Courses';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($action == 'list'): ?>
    <!-- Header with Search & Add button -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-800 mb-1">Course Catalog</h4>
            <p class="text-muted small mb-0">Create, manage and publish courses for students</p>
        </div>
        <div class="d-flex gap-2">
            <a href="courses?action=add" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa fa-plus me-2"></i> Launch New Course
            </a>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <?php
    $cat_filter = intval($_GET['cat_id'] ?? 0);
    $search_query = trim($_GET['s'] ?? '');
    
    $where = "WHERE 1=1";
    if ($cat_filter > 0) {
        $where .= " AND c.category_id = $cat_filter";
    }
    if (!empty($search_query)) {
        $s_esc = mysqli_real_escape_string($con, $search_query);
        $where .= " AND (c.title LIKE '%$s_esc%' OR c.instructor LIKE '%$s_esc%')";
    }

    $courses_sql = "
        SELECT c.*, cat.name as category_name,
               (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as lesson_count,
               (SELECT COUNT(*) FROM lms_enrollments WHERE course_id = c.id AND status = 'completed') as student_count
        FROM lms_courses c 
        LEFT JOIN lms_categories cat ON c.category_id = cat.id 
        $where
        ORDER BY c.id DESC
    ";
    $courses_list = mysqli_query($con, $courses_sql);
    ?>

    <div class="admin-card p-3 mb-4">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="s" class="form-control border-start-0" placeholder="Search by course title or instructor..." value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="cat_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Categories</option>
                    <?php
                    $all_cats = mysqli_query($con, "SELECT id, name FROM lms_categories ORDER BY name ASC");
                    while ($cat_row = mysqli_fetch_assoc($all_cats)) {
                        $sel = ($cat_filter == $cat_row['id']) ? 'selected' : '';
                        echo "<option value='{$cat_row['id']}' $sel>" . htmlspecialchars($cat_row['name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100 rounded-pill fw-semibold">Filter</button>
                <?php if ($cat_filter > 0 || !empty($search_query)): ?>
                    <a href="courses" class="btn btn-light rounded-pill px-3">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Courses Table -->
    <div class="admin-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Course Info</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Lessons</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($courses_list && mysqli_num_rows($courses_list) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($courses_list)): 
                            $thumb = $row['thumbnail'];
                            if (empty($thumb)) {
                                $thumb = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=250';
                            } elseif (strpos($thumb, 'uploads/') === 0) {
                                $thumb = '../' . $thumb;
                            }
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?php echo htmlspecialchars($thumb); ?>" class="rounded-3 object-fit-cover shadow-sm" style="width: 60px; height: 45px;">
                                    <div>
                                        <div class="fw-800 text-dark mb-0"><?php echo htmlspecialchars($row['title']); ?></div>
                                        <div class="text-muted small" style="font-size:0.75rem;">
                                            <span><i class="fa fa-user text-muted me-1"></i><?php echo htmlspecialchars($row['instructor']); ?></span>
                                            <span class="mx-1">•</span>
                                            <span><i class="fa fa-clock text-muted me-1"></i><?php echo htmlspecialchars($row['duration']); ?></span>
                                            <?php if ($row['is_featured']): ?>
                                                <span class="badge bg-warning text-dark ms-2 fw-bold" style="font-size:0.65rem;">FEATURED</span>
                                            <?php endif; ?>
                                            <?php if (isset($row['has_certificate']) && $row['has_certificate'] == 0): ?>
                                                <span class="badge bg-secondary bg-opacity-10 text-muted ms-1 fw-bold" style="font-size:0.65rem;"><i class="fa fa-ban me-1"></i>NO CERT</span>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success ms-1 fw-bold" style="font-size:0.65rem;"><i class="fa fa-certificate me-1"></i>CERTIFICATE</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-3 py-1 rounded-pill small">
                                    <?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['is_free']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">FREE</span>
                                <?php else: ?>
                                    <div class="fw-800 text-dark">₹<?php echo number_format($row['price'], 0); ?></div>
                                    <?php if ($row['discount_price'] > 0): ?>
                                        <del class="text-muted" style="font-size:0.75rem;">₹<?php echo number_format($row['discount_price'], 0); ?></del>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="lessons?course_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light rounded-pill px-3 fw-bold text-primary">
                                    <i class="fa fa-circle-play me-1"></i> <?php echo $row['lesson_count']; ?> Lessons
                                </a>
                            </td>
                            <td>
                                <span class="fw-bold text-dark"><i class="fa fa-users text-muted me-1"></i><?php echo $row['student_count']; ?></span>
                            </td>
                            <td>
                                <a href="courses?toggle_status=<?php echo $row['id']; ?>" class="text-decoration-none" title="Click to toggle status">
                                    <?php if ($row['status'] == 1): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">
                                            <i class="fa fa-check me-1"></i> Published
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary rounded-pill px-3 py-1 small fw-bold">
                                            <i class="fa fa-eye-slash me-1"></i> Draft
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="../details?slug=<?php echo urlencode($row['slug']); ?>" target="_blank" class="btn btn-sm btn-light text-muted" title="View Course Page">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                    <a href="lessons?course_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-primary" title="Manage Lessons">
                                        <i class="fa fa-video"></i>
                                    </a>
                                    <a href="courses?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-dark" title="Edit Course">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="courses?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-danger" title="Delete Course" onclick="return confirm('Are you sure you want to delete this course and all its lessons?')">
                                        <i class="fa fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="fa fa-book-open fs-1 d-block mb-3 opacity-25"></i>
                                No courses found matching your criteria.
                                <div class="mt-3">
                                    <a href="courses?action=add" class="btn btn-primary rounded-pill px-4">Create First Course</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Add / Edit Course Form -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-800 mb-1"><?php echo $action == 'edit' ? 'Edit Course: ' . htmlspecialchars($course_data['title']) : 'Launch New Course'; ?></h4>
            <p class="text-muted small mb-0">Fill in the details below to publish or update course information</p>
        </div>
        <a href="courses" class="btn btn-light rounded-pill px-4 fw-bold">
            <i class="fa fa-arrow-left me-1"></i> Back to Courses
        </a>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="course_id" value="<?php echo $course_data['id']; ?>">
        <input type="hidden" name="old_thumbnail" value="<?php echo htmlspecialchars($course_data['thumbnail']); ?>">

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Basic Course Info -->
                <div class="admin-card p-4 mb-4">
                    <h5 class="fw-800 mb-3 text-dark">Course Overview</h5>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Course Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. DCA (Diploma in Computer Applications)" value="<?php echo htmlspecialchars($course_data['title']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">URL Slug (leave blank to auto-generate)</label>
                        <input type="text" name="slug" class="form-control" placeholder="diploma-in-computer-applications" value="<?php echo htmlspecialchars($course_data['slug']); ?>">
                        <small class="text-muted">Slug forms the URL: <code>lkvmbihar.in/courses/details.php?slug=...</code></small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Short Tagline / Summary *</label>
                        <textarea name="short_desc" class="form-control" rows="2" placeholder="Brief 1-2 sentence highlight for course card" required><?php echo htmlspecialchars($course_data['short_desc']); ?></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Full Description & Syllabus</label>
                        <textarea name="long_desc" class="form-control" rows="8" placeholder="Detailed curriculum breakdown, objectives, eligibility, career prospects..."><?php echo htmlspecialchars($course_data['long_desc']); ?></textarea>
                    </div>
                </div>

                <!-- Media Details -->
                <div class="admin-card p-4 mb-4">
                    <h5 class="fw-800 mb-3 text-dark">Media & Video Preview</h5>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">Upload Course Thumbnail</label>
                            <input type="file" name="thumbnail_file" class="form-control" accept="image/*">
                            <small class="text-muted">Recommended: 1280x720px (JPG, PNG, WebP)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-uppercase text-muted">OR Image Web URL</label>
                            <input type="url" name="thumbnail_url" class="form-control" placeholder="https://images.unsplash.com/..." value="<?php echo (strpos($course_data['thumbnail'], 'http') === 0) ? htmlspecialchars($course_data['thumbnail']) : ''; ?>">
                        </div>
                    </div>

                    <?php if (!empty($course_data['thumbnail'])): 
                        $cur_thumb = $course_data['thumbnail'];
                        if (strpos($cur_thumb, 'uploads/') === 0) $cur_thumb = '../' . $cur_thumb;
                    ?>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Current Thumbnail Preview:</label>
                            <div>
                                <img src="<?php echo htmlspecialchars($cur_thumb); ?>" class="rounded-3 border shadow-sm object-fit-cover" style="max-height: 120px;">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Demo / Preview Video URL (YouTube, Vimeo or MP4)</label>
                        <input type="text" name="preview_video" class="form-control" placeholder="https://www.youtube.com/watch?v=..." value="<?php echo htmlspecialchars($course_data['preview_video'] ?? ''); ?>">
                        <small class="text-muted">Students can watch this preview video on the course landing page before buying.</small>
                    </div>
                </div>

                <!-- Certificate Module -->
                <div class="admin-card p-4 mb-4" style="border-left: 4px solid #c9a84c;">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa fa-award text-warning fs-4"></i>
                            <div>
                                <h5 class="fw-800 mb-0 text-dark">Course Certificate Module</h5>
                                <small class="text-muted">Configure automated certificate issuance and custom signatory details</small>
                            </div>
                        </div>
                        <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill fw-bold small">
                            <i class="fa fa-shield-halved me-1"></i> Tamper-Proof QR
                        </span>
                    </div>

                    <div class="form-check form-switch mb-3 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="has_certificate" id="has_certificate_toggle" value="1" <?php echo (!isset($course_data['has_certificate']) || $course_data['has_certificate'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark" for="has_certificate_toggle">
                            Enable Certificate for this Course
                        </label>
                        <div class="text-muted small ps-5">When enabled, students who complete 100% of the lessons in this course will be automatically issued a verified digital certificate.</div>
                    </div>

                    <div id="cert_settings_fields" style="<?php echo (isset($course_data['has_certificate']) && $course_data['has_certificate'] == 0) ? 'display:none;' : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Custom Certificate Title (Optional)</label>
                            <input type="text" name="certificate_title" class="form-control" placeholder="Leave empty to use Course Title: <?php echo htmlspecialchars($course_data['title'] ?: 'Course Title'); ?>" value="<?php echo htmlspecialchars($course_data['certificate_title'] ?? ''); ?>">
                            <small class="text-muted">Leave blank to use the standard course title on the certificate.</small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-uppercase text-muted">Signatory / Instructor Name</label>
                                <input type="text" name="certificate_signatory_name" class="form-control" placeholder="e.g. Dr. Sonu Sagar (Defaults to course instructor)" value="<?php echo htmlspecialchars($course_data['certificate_signatory_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-uppercase text-muted">Signatory Designation / Role</label>
                                <input type="text" name="certificate_signatory_title" class="form-control" placeholder="Director & Academic Head" value="<?php echo htmlspecialchars($course_data['certificate_signatory_title'] ?? 'Director & Academic Head'); ?>">
                            </div>
                        </div>

                        <!-- Certificate Design Template Picker -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label small fw-bold text-uppercase text-muted mb-0">Choose Certificate Design Template *</label>
                                <a href="certificate-templates" target="_blank" class="small fw-bold text-primary text-decoration-none">
                                    <i class="fa fa-palette me-1"></i> Preview Template Studio
                                </a>
                            </div>

                            <?php 
                            $available_templates = lms_get_certificate_templates();
                            $selected_template = $course_data['certificate_template'] ?? 'classic_gold';
                            ?>
                            <div class="row g-2">
                                <?php foreach ($available_templates as $tpl_k => $tpl_v): 
                                    $is_checked = ($selected_template === $tpl_k);
                                ?>
                                    <div class="col-md-6">
                                        <label class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-white cursor-pointer w-100 position-relative" style="cursor: pointer; <?php echo $is_checked ? 'border-color: ' . $tpl_v['primary_color'] . ' !important; background: #fafafa;' : ''; ?>">
                                            <input type="radio" name="certificate_template" value="<?php echo $tpl_k; ?>" class="form-check-input mt-0 flex-shrink-0" <?php echo $is_checked ? 'checked' : ''; ?>>
                                            <div class="overflow-hidden flex-grow-1">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span class="fw-bold text-dark small"><?php echo htmlspecialchars($tpl_v['name']); ?></span>
                                                    <span class="badge rounded-pill text-white small" style="background: <?php echo $tpl_v['primary_color']; ?>; font-size: 0.65rem;">
                                                        <i class="fa <?php echo $tpl_v['icon']; ?> me-1"></i> <?php echo $tpl_v['badge']; ?>
                                                    </span>
                                                </div>
                                                <div class="text-muted text-truncate" style="font-size: 0.75rem;">
                                                    <?php echo htmlspecialchars($tpl_v['desc']); ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mt-3 p-3 rounded-3 bg-light border d-flex align-items-center gap-3">
                            <i class="fa fa-circle-info text-primary fs-5"></i>
                            <div class="small text-muted">
                                <strong>Certificate Features:</strong> Unique LKVM ID (e.g. <code>LKVM-202610-XXXXX</code>), instant QR-code public verification at <code>/certificate-verify</code>, high-resolution PNG/PDF download & print, and anti-counterfeiting security seal.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Publishing & Meta -->
                <div class="admin-card p-4 mb-4">
                    <h5 class="fw-800 mb-3 text-dark">Category & Instructor</h5>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Category *</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php
                            $cat_q = mysqli_query($con, "SELECT id, name FROM lms_categories ORDER BY name ASC");
                            while ($cat_item = mysqli_fetch_assoc($cat_q)) {
                                $sel = ($course_data['category_id'] == $cat_item['id']) ? 'selected' : '';
                                echo "<option value='{$cat_item['id']}' $sel>" . htmlspecialchars($cat_item['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Instructor Name *</label>
                        <input type="text" name="instructor" class="form-control" required value="<?php echo htmlspecialchars($course_data['instructor']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Difficulty Level</label>
                        <select name="level" class="form-select">
                            <option value="Beginner" <?php echo $course_data['level'] == 'Beginner' ? 'selected' : ''; ?>>Beginner</option>
                            <option value="Intermediate" <?php echo $course_data['level'] == 'Intermediate' ? 'selected' : ''; ?>>Intermediate</option>
                            <option value="Advanced" <?php echo $course_data['level'] == 'Advanced' ? 'selected' : ''; ?>>Advanced</option>
                            <option value="All Levels" <?php echo $course_data['level'] == 'All Levels' ? 'selected' : ''; ?>>All Levels</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small fw-bold text-uppercase text-muted">Estimated Duration</label>
                        <input type="text" name="duration" class="form-control" placeholder="e.g. 60 Hours, 3 Months" value="<?php echo htmlspecialchars($course_data['duration']); ?>">
                    </div>
                </div>

                <!-- Pricing & Visibility -->
                <div class="admin-card p-4 mb-4">
                    <h5 class="fw-800 mb-3 text-dark">Pricing & Visibility</h5>

                    <div class="form-check form-switch mb-3 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="is_free" id="is_free_toggle" value="1" <?php echo $course_data['is_free'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-success" for="is_free_toggle">
                            100% Free Course
                        </label>
                    </div>

                    <div id="price_fields" style="<?php echo $course_data['is_free'] ? 'display:none;' : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Course Price (INR ₹) *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">₹</span>
                                <input type="number" step="0.01" name="price" id="course_price" class="form-control" value="<?php echo htmlspecialchars($course_data['price']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Regular / Strike Price (INR ₹)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">₹</span>
                                <input type="number" step="0.01" name="discount_price" class="form-control" placeholder="Optional strikethrough price" value="<?php echo htmlspecialchars($course_data['discount_price'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3 p-3 bg-light rounded-3">
                        <input class="form-check-input ms-0 me-3" type="checkbox" name="is_featured" id="is_featured" value="1" <?php echo $course_data['is_featured'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold text-dark" for="is_featured">
                            Featured on Homepage
                        </label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-uppercase text-muted">Publish Status</label>
                        <select name="status" class="form-select">
                            <option value="1" <?php echo $course_data['status'] == 1 ? 'selected' : ''; ?>>Published (Live on Website)</option>
                            <option value="0" <?php echo $course_data['status'] == 0 ? 'selected' : ''; ?>>Draft (Hidden)</option>
                        </select>
                    </div>

                    <button type="submit" name="save_course" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg">
                        <i class="fa fa-save me-2"></i> <?php echo $action == 'edit' ? 'Update Course' : 'Publish & Continue'; ?>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        const freeToggle = document.getElementById('is_free_toggle');
        const priceFields = document.getElementById('price_fields');
        if (freeToggle && priceFields) {
            freeToggle.addEventListener('change', function() {
                priceFields.style.display = this.checked ? 'none' : 'block';
            });
        }

        const certToggle = document.getElementById('has_certificate_toggle');
        const certFields = document.getElementById('cert_settings_fields');
        if (certToggle && certFields) {
            certToggle.addEventListener('change', function() {
                certFields.style.display = this.checked ? 'block' : 'none';
            });
        }
    </script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
