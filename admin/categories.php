<?php
require_once dirname(__DIR__) . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['lms_admin_id']) && (!isset($_SESSION['admin_id']) || ($_SESSION['admin_type'] ?? 0) != 1)) {
    header("Location: login");
    exit();
}

$edit_id = intval($_GET['edit_id'] ?? 0);
$msg = "";

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    // Check if courses are using this category
    $check = mysqli_query($con, "SELECT COUNT(*) as count FROM lms_courses WHERE category_id = $del_id");
    $cnt = mysqli_fetch_assoc($check)['count'] ?? 0;
    if ($cnt > 0) {
        $msg = "Cannot delete category: $cnt courses are assigned to it. Please reassign those courses first.";
    } else {
        mysqli_query($con, "DELETE FROM lms_categories WHERE id = $del_id");
        lms_redirect("categories?msg=" . urlencode("Category deleted successfully!"));
    }
}

// Handle Add / Edit Category
if (isset($_POST['save_category'])) {
    $cid = intval($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }
    $icon = trim($_POST['icon'] ?? 'fa-laptop-code');
    $description = trim($_POST['description'] ?? '');

    if ($cid > 0) {
        $stmt = $con->prepare("UPDATE lms_categories SET name=?, slug=?, icon=?, description=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("ssssi", $name, $slug, $icon, $description, $cid);
            $stmt->execute();
            $stmt->close();
            lms_redirect("categories?msg=" . urlencode("Category updated successfully!"));
        }
    } else {
        $stmt = $con->prepare("INSERT INTO lms_categories (name, slug, icon, description) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $name, $slug, $icon, $description);
            $stmt->execute();
            $stmt->close();
            lms_redirect("categories?msg=" . urlencode("New category added successfully!"));
        }
    }
}

// Fetch Category Data for Edit
$cat_data = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'icon' => 'fa-laptop-code',
    'description' => ''
];

if ($edit_id > 0) {
    $res = mysqli_query($con, "SELECT * FROM lms_categories WHERE id = $edit_id LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $cat_data = mysqli_fetch_assoc($res);
    }
}

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

$page_title = 'Manage Categories';
include 'includes/header.php';
?>

<?php if (!empty($msg)): ?>
    <div class="alert alert-success alert-dismissible fade show rounded-4 py-3 px-4 mb-4 shadow-sm" role="alert">
        <i class="fa fa-info-circle me-2"></i> <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Category Form -->
    <div class="col-lg-4">
        <div class="admin-card p-4">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4">
                    <i class="fa <?php echo $edit_id > 0 ? 'fa-edit' : 'fa-folder-plus'; ?> fs-4"></i>
                </div>
                <div>
                    <h5 class="fw-800 mb-0"><?php echo $edit_id > 0 ? 'Edit Category' : 'Create Category'; ?></h5>
                    <small class="text-muted">Group your courses by stream</small>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="category_id" value="<?php echo $cat_data['id']; ?>">

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Category Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. DCA & Office Apps" value="<?php echo htmlspecialchars($cat_data['name']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Slug (URL friendly)</label>
                    <input type="text" name="slug" class="form-control" placeholder="dca-office-apps" value="<?php echo htmlspecialchars($cat_data['slug']); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">FontAwesome Icon Class</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa <?php echo htmlspecialchars($cat_data['icon']); ?>"></i></span>
                        <input type="text" name="icon" class="form-control" placeholder="fa-code, fa-laptop, fa-chart-line" value="<?php echo htmlspecialchars($cat_data['icon']); ?>">
                    </div>
                    <small class="text-muted">Examples: <code>fa-code</code>, <code>fa-desktop</code>, <code>fa-file-invoice-dollar</code></small>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief outline of topics in this stream..."><?php echo htmlspecialchars($cat_data['description'] ?? ''); ?></textarea>
                </div>

                <button type="submit" name="save_category" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow-lg">
                    <i class="fa fa-save me-2"></i> <?php echo $edit_id > 0 ? 'Update Category' : 'Add Category'; ?>
                </button>

                <?php if ($edit_id > 0): ?>
                    <a href="categories" class="btn btn-light w-100 rounded-pill mt-2 fw-semibold">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Category List Table -->
    <div class="col-lg-8">
        <div class="admin-card overflow-hidden">
            <div class="p-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-800 mb-0">Course Categories</h5>
                    <small class="text-muted">All active streams</small>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Category</th>
                            <th>Slug</th>
                            <th>Courses Count</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cats_res = mysqli_query($con, "
                            SELECT c.*, (SELECT COUNT(*) FROM lms_courses WHERE category_id = c.id) as course_count 
                            FROM lms_categories c 
                            ORDER BY c.id DESC
                        ");
                        if ($cats_res && mysqli_num_rows($cats_res) > 0):
                            while ($cat = mysqli_fetch_assoc($cats_res)):
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                        <i class="fa <?php echo htmlspecialchars($cat['icon'] ?: 'fa-folder'); ?> fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($cat['name']); ?></div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <small class="text-muted text-truncate d-block" style="max-width: 250px;"><?php echo htmlspecialchars($cat['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="small text-primary bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($cat['slug']); ?></code>
                            </td>
                            <td>
                                <a href="courses?cat_id=<?php echo $cat['id']; ?>" class="badge bg-light text-dark border px-3 py-2 rounded-pill text-decoration-none">
                                    <i class="fa fa-book-open text-primary me-1"></i> <?php echo $cat['course_count']; ?> Courses
                                </a>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="categories?edit_id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-light text-dark" title="Edit">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                    <a href="categories?delete=<?php echo $cat['id']; ?>" class="btn btn-sm btn-light text-danger" title="Delete" onclick="return confirm('Delete this category?')">
                                        <i class="fa fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="fa fa-folder-open fs-1 d-block mb-3 opacity-25"></i>
                                No categories added yet.
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
