<?php
$page_title = 'Browse Categories & Streams';
include 'includes/header.php';

$cat_id = intval($_GET['id'] ?? 0);
$filter_sql = ($cat_id > 0) ? "AND c.category_id = $cat_id" : "";

$query = "
    SELECT c.*, cat.name as category_name,
           (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as total_lessons
    FROM lms_courses c 
    LEFT JOIN lms_categories cat ON c.category_id = cat.id 
    WHERE c.status = 1 $filter_sql
    ORDER BY c.id DESC
";
$result = mysqli_query($con, $query);

// Selected category info
$current_category = null;
if ($cat_id > 0) {
    $c_q = mysqli_query($con, "SELECT * FROM lms_categories WHERE id = $cat_id");
    if ($c_q) $current_category = mysqli_fetch_assoc($c_q);
}
?>

<div class="py-5 bg-light min-vh-100">
    <div class="container py-3">
        <!-- Centered Header -->
        <div class="text-center mb-4">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold small text-uppercase" style="letter-spacing: 0.5px;">
                Explore Learning Streams
            </span>
            <h1 class="catalog-section-title mt-2 mb-2">
                <?php if ($current_category): ?>
                    <span class="brush-underline-wrap"><?php echo htmlspecialchars($current_category['name']); ?>
                        <svg class="brush-underline-svg" viewBox="0 0 200 12" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                            <path d="M2,9 C40,3 80,11 120,6 C155,2 180,10 198,7" stroke="#0056D2" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                <?php else: ?>
                    All Learning <span class="brush-underline-wrap">Streams
                        <svg class="brush-underline-svg" viewBox="0 0 200 12" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                            <path d="M2,9 C40,3 80,11 120,6 C155,2 180,10 198,7" stroke="#0056D2" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.95rem; max-width: 540px; margin: 0 auto;">
                <?php echo $current_category ? htmlspecialchars($current_category['description'] ?: 'Explore high quality courses in this category') : 'Choose a category to browse specialized career programs and professional training modules'; ?>
            </p>
        </div>

        <!-- Centered Category Pills -->
        <div class="d-flex flex-wrap justify-content-center align-items-center gap-2 mb-5">
            <a href="categories" class="btn rounded-pill px-4 fw-semibold shadow-sm <?php echo $cat_id == 0 ? 'btn-primary' : 'btn-white bg-white border text-dark'; ?>" style="<?php echo $cat_id == 0 ? 'background: #0056D2; border-color: #0056D2;' : ''; ?>">
                <i class="fa fa-layer-group me-1 small"></i> All Categories
            </a>
            <?php
            $cats_all = mysqli_query($con, "
                SELECT cat.*, COUNT(c.id) as course_count 
                FROM lms_categories cat 
                LEFT JOIN lms_courses c ON cat.id = c.category_id AND c.status = 1 
                GROUP BY cat.id 
                ORDER BY cat.name ASC
            ");
            while ($c = mysqli_fetch_assoc($cats_all)):
                $isActive = ($c['id'] == $cat_id);
                $btnClass = $isActive ? 'btn-primary' : 'btn-white bg-white border text-dark';
                $style = $isActive ? 'background: #0056D2; border-color: #0056D2;' : '';
            ?>
                <a href="categories?id=<?php echo $c['id']; ?>" class="btn rounded-pill px-4 fw-semibold shadow-sm <?php echo $btnClass; ?>" style="<?php echo $style; ?>">
                    <i class="fa <?php echo htmlspecialchars($c['icon'] ?: 'fa-folder'); ?> me-1 small"></i>
                    <?php echo htmlspecialchars($c['name']); ?> 
                    <span class="badge <?php echo $isActive ? 'bg-white text-primary' : 'bg-light text-muted border'; ?> ms-1 rounded-pill"><?php echo $c['course_count']; ?></span>
                </a>
            <?php endwhile; ?>
        </div>

        <!-- Course Cards Grid (Same as Homepage) -->
        <div class="row g-4 justify-content-center">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($course = mysqli_fetch_assoc($result)): 
                    $thumb = $course['thumbnail'];
                    if (empty($thumb)) {
                        $thumb = 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&q=80&w=600';
                    }
                ?>
                    <div class="col-xl-4 col-md-6">
                        <div class="coursera-card">
                            <!-- Thumbnail -->
                            <div class="coursera-thumb-wrap">
                                <span class="course-cat-badge">
                                    <?php echo htmlspecialchars($course['category_name'] ?? 'Computer'); ?>
                                </span>
                                <?php if (!empty($course['is_featured'])): ?>
                                    <span class="course-top-badge">★ Top Rated</span>
                                <?php endif; ?>
                                <div class="course-lessons-pill">
                                    <i class="fa fa-play-circle"></i>
                                    <?php echo $course['total_lessons']; ?> Lectures
                                </div>
                                <img src="<?php echo htmlspecialchars($thumb); ?>" alt="<?php echo htmlspecialchars($course['title']); ?>" loading="lazy">
                            </div>

                            <!-- Card Body -->
                            <div class="coursera-card-body">
                                <!-- Provider -->
                                <div class="coursera-partner-logo">
                                    <i class="fa fa-graduation-cap"></i>
                                    <span>LKVM Academy</span>
                                </div>

                                <!-- Course Title -->
                                <h3 class="coursera-course-title">
                                    <a href="details?slug=<?php echo urlencode($course['slug']); ?>" class="text-decoration-none" style="color: inherit;">
                                        <?php echo htmlspecialchars($course['title']); ?>
                                    </a>
                                </h3>

                                <!-- Description -->
                                <p class="coursera-course-skills">
                                    <?php echo htmlspecialchars($course['short_desc']); ?>
                                </p>

                                <!-- Rating & Level Row -->
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="coursera-rating-box">
                                        <i class="fa fa-star"></i> 4.8
                                    </span>
                                    <span class="course-level-pill">
                                        <?php echo htmlspecialchars($course['level'] ?: 'All Levels'); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card Footer: Price + CTA -->
                            <div class="coursera-card-footer">
                                <div>
                                    <?php if ($course['is_free']): ?>
                                        <span class="course-price-free">FREE</span>
                                    <?php else: ?>
                                        <span class="course-price-main">₹<?php echo number_format($course['price'], 0); ?></span>
                                        <?php if ($course['discount_price'] > 0): ?>
                                            <span class="course-price-old">₹<?php echo number_format($course['discount_price'], 0); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <a href="details?slug=<?php echo urlencode($course['slug']); ?>" class="btn-course-view">
                                    Enroll Now <i class="fa fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <div class="bg-white p-5 rounded-4 border shadow-sm mx-auto" style="max-width: 480px;">
                        <i class="fa fa-folder-open fs-1 text-muted mb-3 opacity-25"></i>
                        <h4 class="fw-bold">No Courses in this Category</h4>
                        <p class="text-muted small">Courses under this category are currently being updated. Check back soon or view all courses.</p>
                        <a href="categories" class="btn btn-primary rounded-pill px-4 fw-bold" style="background: #0056D2; border-color: #0056D2;">All Categories</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
