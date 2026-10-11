<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$course_id = intval($_GET['course_id'] ?? 0);
$edit_id = intval($_GET['edit_id'] ?? 0);
$msg = "";

// Handle Delete Lesson
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    mysqli_query($con, "DELETE FROM lms_lessons WHERE id = $del_id");
    // Also remove progress records for this lesson
    mysqli_query($con, "DELETE FROM lms_progress WHERE lesson_id = $del_id");
    lms_redirect("lessons?course_id=$course_id&msg=" . urlencode("Lesson removed successfully!"));
}

// Handle Add / Edit Lesson
if (isset($_POST['save_lesson'])) {
    $lid = intval($_POST['lesson_id'] ?? 0);
    $cid = intval($_POST['course_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $type = trim($_POST['content_type'] ?? 'youtube');
    $url = trim($_POST['content_url'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $is_preview = isset($_POST['is_preview']) ? 1 : 0;

    // Handle Uploaded File (Image, PDF, Document, or Video)
    if (isset($_FILES['lesson_file']) && $_FILES['lesson_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['lesson_file']['tmp_name'];
        $file_name = $_FILES['lesson_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_exts = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'mp4', 'webm', 'zip'];
        if (in_array($file_ext, $allowed_exts)) {
            $upload_dir = dirname(__DIR__) . '/uploads/lessons/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $clean_basename = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
            $new_filename = 'lesson_' . time() . '_' . substr(md5(uniqid()), 0, 6) . '.' . $file_ext;
            $dest = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $dest)) {
                $url = 'uploads/lessons/' . $new_filename;
                // Auto-detect and align content type if needed
                if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                    $type = 'image';
                } elseif ($file_ext === 'pdf') {
                    $type = 'pdf';
                } elseif (in_array($file_ext, ['doc', 'docx', 'zip'])) {
                    $type = 'file';
                } elseif (in_array($file_ext, ['mp4', 'webm'])) {
                    $type = 'video';
                }
            }
        }
    }

    if ($lid > 0) {
        $stmt = $con->prepare("UPDATE lms_lessons SET course_id=?, title=?, description=?, content_type=?, content_url=?, duration=?, sort_order=?, is_preview=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("isssssiii", $cid, $title, $desc, $type, $url, $duration, $sort_order, $is_preview, $lid);
            $stmt->execute();
            $stmt->close();
            lms_redirect("lessons?course_id=$cid&msg=" . urlencode("Lesson updated successfully!"));
        }
    } else {
        $stmt = $con->prepare("INSERT INTO lms_lessons (course_id, title, description, content_type, content_url, duration, sort_order, is_preview) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssssii", $cid, $title, $desc, $type, $url, $duration, $sort_order, $is_preview);
            $stmt->execute();
            $stmt->close();
            lms_redirect("lessons?course_id=$cid&msg=" . urlencode("New lesson added successfully!"));
        }
    }
}

$page_title = 'Manage Lessons';
include 'includes/header.php';

// Fetch Lesson Data for Editing
$lesson_data = [
    'id' => 0,
    'course_id' => $course_id,
    'title' => '',
    'description' => '',
    'content_type' => 'youtube',
    'content_url' => '',
    'duration' => '15 mins',
    'sort_order' => 1,
    'is_preview' => 0
];

if ($edit_id > 0) {
    $l_q = mysqli_query($con, "SELECT * FROM lms_lessons WHERE id = $edit_id LIMIT 1");
    if ($l_q && mysqli_num_rows($l_q) > 0) {
        $lesson_data = mysqli_fetch_assoc($l_q);
        $course_id = $lesson_data['course_id'];
    }
}

// Fetch active course details if selected
$active_course = null;
if ($course_id > 0) {
    $c_q = mysqli_query($con, "SELECT * FROM lms_courses WHERE id = $course_id LIMIT 1");
    if ($c_q) $active_course = mysqli_fetch_assoc($c_q);
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-circle-check me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Top Toolbar -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h4 class="fw-800 mb-1">
            <?php if ($active_course): ?>
                Lessons for: <span class="text-primary"><?php echo htmlspecialchars($active_course['title']); ?></span>
            <?php else: ?>
                Course Lessons & Lectures
            <?php endif; ?>
        </h4>
        <p class="text-muted small mb-0">Organize video lectures, PDF notes, and previewable trial chapters</p>
    </div>

    <!-- Course Switcher -->
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" class="d-flex gap-2">
            <select name="course_id" class="form-select rounded-pill px-4" onchange="this.form.submit()">
                <option value="0">All Courses (Overview)</option>
                <?php
                $courses_list = mysqli_query($con, "SELECT id, title FROM lms_courses ORDER BY title ASC");
                while ($c = mysqli_fetch_assoc($courses_list)):
                    $sel = ($c['id'] == $course_id) ? 'selected' : '';
                ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['title']); ?></option>
                <?php endwhile; ?>
            </select>
        </form>
        <?php if ($active_course): ?>
            <a href="../details?slug=<?php echo urlencode($active_course['slug']); ?>" target="_blank" class="btn btn-outline-primary rounded-pill px-3 fw-bold small text-nowrap">
                <i class="fa fa-eye me-1"></i> Preview Course
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Form: Add or Edit Lesson -->
    <div class="col-xl-4">
        <div class="admin-card p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4">
                    <i class="fa <?php echo $edit_id > 0 ? 'fa-edit' : 'fa-plus-circle'; ?> fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-800 mb-0"><?php echo $edit_id > 0 ? 'Edit Lesson' : 'Add New Lesson'; ?></h5>
                    <small class="text-muted"><?php echo $edit_id > 0 ? 'Update lesson details' : 'Attach video or notes'; ?></small>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="lesson_id" value="<?php echo $lesson_data['id']; ?>">

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Target Course *</label>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select Course</option>
                        <?php
                        $courses = mysqli_query($con, "SELECT id, title FROM lms_courses ORDER BY title ASC");
                        while ($c = mysqli_fetch_assoc($courses)) {
                            $sel = ($c['id'] == $lesson_data['course_id']) ? 'selected' : '';
                            echo "<option value='{$c['id']}' $sel>" . htmlspecialchars($c['title']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Lesson Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. 1. Introduction to Microsoft Word" value="<?php echo htmlspecialchars($lesson_data['title']); ?>">
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Content Type</label>
                        <select name="content_type" id="content_type_select" class="form-select">
                            <option value="youtube" <?php echo $lesson_data['content_type'] == 'youtube' ? 'selected' : ''; ?>>YouTube Video</option>
                            <option value="video" <?php echo $lesson_data['content_type'] == 'video' ? 'selected' : ''; ?>>Direct Video (MP4)</option>
                            <option value="pdf" <?php echo $lesson_data['content_type'] == 'pdf' ? 'selected' : ''; ?>>PDF Document</option>
                            <option value="image" <?php echo $lesson_data['content_type'] == 'image' ? 'selected' : ''; ?>>Image / Diagram</option>
                            <option value="file" <?php echo $lesson_data['content_type'] == 'file' ? 'selected' : ''; ?>>File / Downloadable</option>
                            <option value="text" <?php echo $lesson_data['content_type'] == 'text' ? 'selected' : ''; ?>>Article / Notes</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Duration / Est.</label>
                        <input type="text" name="duration" class="form-control" placeholder="e.g. 25 mins, 10 Pages" value="<?php echo htmlspecialchars($lesson_data['duration'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Local File / Image / PDF Upload Option -->
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <label class="form-label small fw-bold text-muted text-uppercase d-flex justify-content-between mb-1">
                        <span><i class="fa fa-cloud-arrow-up text-primary me-1"></i> Upload File / Image / PDF</span>
                        <span class="text-muted fw-normal small">(Max: 100MB)</span>
                    </label>
                    <input type="file" name="lesson_file" id="lesson_file_input" class="form-control bg-white" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.webp,.gif,.mp4,.webm,.zip">
                    <?php if (!empty($lesson_data['content_url']) && strpos($lesson_data['content_url'], 'uploads/') === 0): ?>
                        <div class="mt-2 p-2 bg-white rounded-2 border d-flex align-items-center justify-content-between small">
                            <span class="text-truncate me-2"><i class="fa fa-file text-primary me-1"></i> Current: <strong><?php echo htmlspecialchars(basename($lesson_data['content_url'])); ?></strong></span>
                            <a href="../<?php echo htmlspecialchars($lesson_data['content_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill small">Preview</a>
                        </div>
                    <?php endif; ?>
                    <small class="text-muted d-block mt-1">Upload a PDF, image diagram, presentation, or video directly from your computer</small>
                </div>

                <!-- URL Alternative -->
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Or External Video / Resource URL</label>
                    <input type="text" name="content_url" class="form-control" placeholder="https://youtube.com/watch?v=... or direct link" value="<?php echo htmlspecialchars($lesson_data['content_url'] ?? ''); ?>">
                    <small class="text-muted">Use this for YouTube links or externally hosted resources.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Sort Sequence</label>
                    <input type="number" name="sort_order" class="form-control" placeholder="1, 2, 3..." value="<?php echo htmlspecialchars($lesson_data['sort_order']); ?>">
                    <small class="text-muted">Lessons will appear in ascending order (1, 2, 3...)</small>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Lesson Notes / Summary</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Key topics covered in this lesson..."><?php echo htmlspecialchars($lesson_data['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-check form-switch mb-4 p-3 bg-light rounded-3">
                    <input class="form-check-input ms-0 me-3" type="checkbox" name="is_preview" id="is_preview" value="1" <?php echo $lesson_data['is_preview'] ? 'checked' : ''; ?>>
                    <label class="form-check-label fw-bold text-primary" for="is_preview">
                        Free Trial / Demo Chapter
                    </label>
                    <div class="text-muted small ps-5">Unenrolled students can watch/read this lesson as a demo</div>
                </div>

                <button type="submit" name="save_lesson" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg">
                    <i class="fa fa-save me-2"></i> <?php echo $edit_id > 0 ? 'Update Lesson' : 'Add Lesson to Course'; ?>
                </button>
                
                <?php if ($edit_id > 0): ?>
                    <a href="lessons?course_id=<?php echo $course_id; ?>" class="btn btn-light w-100 rounded-pill mt-2 fw-semibold">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Table: Lessons List -->
    <div class="col-xl-8">
        <div class="admin-card overflow-hidden">
            <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-800 mb-0">Curriculum Structure</h5>
                    <small class="text-muted">
                        <?php echo $course_id > 0 ? "Showing lessons for selected course" : "Showing all lessons across all courses"; ?>
                    </small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th class="ps-2">Lesson Title</th>
                            <th>Course</th>
                            <th>Type</th>
                            <th>Trial</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $filter_sql = ($course_id > 0) ? "WHERE l.course_id = $course_id" : "";
                        $lessons_res = mysqli_query($con, "
                            SELECT l.*, c.title as course_title, c.slug as course_slug 
                            FROM lms_lessons l 
                            JOIN lms_courses c ON l.course_id = c.id 
                            $filter_sql 
                            ORDER BY l.course_id ASC, l.sort_order ASC, l.id ASC
                        ");
                        if ($lessons_res && mysqli_num_rows($lessons_res) > 0):
                            while ($row = mysqli_fetch_assoc($lessons_res)):
                        ?>
                        <tr>
                            <td class="fw-bold text-muted"><?php echo $row['sort_order']; ?></td>
                            <td class="ps-2">
                                <div class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($row['title']); ?></div>
                                <?php if (!empty($row['duration'])): ?>
                                    <small class="text-muted"><i class="fa fa-clock me-1"></i><?php echo htmlspecialchars($row['duration']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 small text-truncate" style="max-width: 150px; display:inline-block;" title="<?php echo htmlspecialchars($row['course_title']); ?>">
                                    <?php echo htmlspecialchars($row['course_title']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark rounded-pill px-3 py-1 border small">
                                    <?php if ($row['content_type'] == 'youtube'): ?>
                                        <i class="fab fa-youtube text-danger me-1"></i> YouTube
                                    <?php elseif ($row['content_type'] == 'video'): ?>
                                        <i class="fa fa-play-circle text-primary me-1"></i> MP4 Video
                                    <?php elseif ($row['content_type'] == 'pdf'): ?>
                                        <i class="fa fa-file-pdf text-danger me-1"></i> PDF Doc
                                    <?php elseif ($row['content_type'] == 'image'): ?>
                                        <i class="fa fa-image text-success me-1"></i> Image
                                    <?php elseif ($row['content_type'] == 'file'): ?>
                                        <i class="fa fa-paperclip text-info me-1"></i> File
                                    <?php else: ?>
                                        <i class="fa fa-file-lines text-warning me-1"></i> Article
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['is_preview']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1 small fw-bold">FREE PREVIEW</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-muted rounded-pill px-3 py-1 small">Locked</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="../learn?slug=<?php echo urlencode($row['course_slug']); ?>&lesson=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-light text-muted" title="Preview in Classroom">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                    <a href="lessons?course_id=<?php echo $row['course_id']; ?>&edit_id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-dark" title="Edit Lesson">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="lessons?course_id=<?php echo $row['course_id']; ?>&delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-light text-danger" title="Delete Lesson" onclick="return confirm('Delete this lesson?')">
                                        <i class="fa fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fa fa-circle-play fs-1 d-block mb-3 opacity-25"></i>
                                No lessons added for this selection yet. Use the form on the left to add your first lecture.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
