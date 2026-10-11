<?php
$page_title = 'LKVM Academy - Master Digital & Computer Skills Online';
include 'includes/header.php';

$search_term = trim($_GET['s'] ?? '');
$cat_filter = intval($_GET['cat_id'] ?? 0);
$filter_type = trim($_GET['filter'] ?? 'all');

$where = ["c.status = 1"];
if (!empty($search_term)) {
    $st_esc = mysqli_real_escape_string($con, $search_term);
    $where[] = "(c.title LIKE '%$st_esc%' OR c.short_desc LIKE '%$st_esc%' OR c.instructor LIKE '%$st_esc%')";
}
if ($cat_filter > 0) {
    $where[] = "c.category_id = $cat_filter";
}
if ($filter_type === 'free') {
    $where[] = "c.is_free = 1";
} elseif ($filter_type === 'featured') {
    $where[] = "c.is_featured = 1";
}

$where_sql = implode(" AND ", $where);
$is_filtered_or_all = isset($_GET['show_all']) || !empty($search_term) || $cat_filter > 0 || $filter_type !== 'all';
$limit_sql = $is_filtered_or_all ? "" : "LIMIT 3";

$courses_query = "
    SELECT c.*, cat.name as category_name,
           (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as total_lessons
    FROM lms_courses c 
    LEFT JOIN lms_categories cat ON c.category_id = cat.id 
    WHERE $where_sql 
    ORDER BY c.is_featured DESC, c.id DESC
    $limit_sql
";
$result = mysqli_query($con, $courses_query);
?>

<!-- =========================================================
     ANIMATED HERO SECTION (LearNix Inspired with Hero Student)
     ========================================================= -->
<section class="hero-animated-section">
    <!-- Playful Floating Vector Doodles -->
    <!-- Doodle 1: Hand-Drawn White Curves (Top Left) -->
    <svg class="hero-doodle doodle-curve" viewBox="0 0 100 68" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 55C18 30 38 10 70 8M32 60C45 42 62 28 88 22M5 40C12 22 26 12 45 10" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <!-- Doodle 2: 12-Ray Yellow Sunburst (Middle Left) -->
    <svg class="hero-doodle doodle-sun" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="30" cy="30" r="9" fill="#facc15"/>
        <line x1="30" y1="5" x2="30" y2="14" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="30" y1="46" x2="30" y2="55" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="5" y1="30" x2="14" y2="30" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="46" y1="30" x2="55" y2="30" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="12" y1="12" x2="19" y2="19" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="41" y1="41" x2="48" y2="48" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="12" y1="48" x2="19" y2="41" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
        <line x1="41" y1="19" x2="48" y2="12" stroke="#facc15" stroke-width="3" stroke-linecap="round"/>
    </svg>

    <!-- Doodle 3: 4-Point Yellow Sparkle Star (Top Right) -->
    <svg class="hero-doodle doodle-star" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M30 0C30 16.5685 43.4315 30 60 30C43.4315 30 30 43.4315 30 60C30 43.4315 16.5685 30 0 30C16.5685 30 30 16.5685 30 0Z" fill="#facc15"/>
    </svg>

    <div class="container text-center position-relative" style="z-index: 5;">
        <!-- Top Pill Badge -->
        <div class="hero-badge-pill">
            <span class="badge-icon">🎓</span>
            <span>Learn From the Top Experts</span>
        </div>

        <!-- Main Headline -->
        <h1 class="hero-main-title">
            <span class="d-inline-block">Learn Anywhere,</span> <span class="d-inline-block">Anytime</span><br>
            <span class="gradient-text-hero">Empower Your Future</span>
        </h1>

        <!-- Subtitle -->
        <p class="hero-subtitle">
            Join thousands of learners gaining new skills, advancing careers and shaping a better tomorrow—one lesson at a time.
        </p>

        <!-- Search Bar -->
        <div class="hero-search-wrapper">
            <form action="./#catalog" method="GET" class="hero-search-form">
                <i class="fa fa-search search-icon"></i>
                <input type="text" name="s" placeholder="Search courses (e.g. DCA, Tally, Web)..." value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off">
                <button type="submit">
                    Search <i class="fa fa-arrow-right ms-1 d-none d-sm-inline"></i>
                </button>
            </form>
        </div>

        <!-- Hero Stage (Student Cutout + Concentric Rings + Floating Cards) -->
        <div class="hero-stage">
            <!-- Concentric Glowing Ripple Rings -->
            <div class="hero-ripples">
                <div class="hero-ripple-ring ring-1"></div>
                <div class="hero-ripple-ring ring-2"></div>
                <div class="hero-ripple-ring ring-3"></div>
            </div>

            <!-- Left Floating Card (4.8 Rating) - Visible on Desktop -->
            <div class="hero-floating-card card-left d-none d-lg-block">
                <div class="rating-number">4.8</div>
                <div class="rating-stars">
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star"></i>
                    <i class="fa fa-star-half-stroke"></i>
                </div>
                <p class="card-caption">By students worldwide for quality learning and support.</p>
            </div>

            <!-- Centered Instructor Photo Provided By User -->
            <img src="img/hero-student.png" alt="Instructor Sonu Sagar - LKVM Academy" class="hero-student-img">

            <!-- Instructor Callout with Hand-Drawn Brush Arrow pointing to Sonu Sagar -->
            <div class="instructor-pointer-wrap">
                <div class="instructor-note-bubble">
                    <span class="instructor-badge-role"><i class="fa fa-chalkboard-user"></i> Your Instructor</span>
                    <span class="instructor-handwritten-text">Sonu Sagar <span class="wave-emoji">👋</span></span>
                </div>
                <!-- Hand-Drawn Curved Brush Arrow pointing directly to Sonu Sagar -->
                <svg class="instructor-brush-arrow" viewBox="0 0 120 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <!-- Smooth natural brush stroke curving down-left toward Sonu Sagar -->
                    <path d="M 100 10 C 75 8, 38 22, 16 52" stroke="#facc15" stroke-width="4.5" stroke-linecap="round"/>
                    <path d="M 96 12 C 72 10, 39 24, 20 50" stroke="#fef08a" stroke-width="2" stroke-linecap="round" opacity="0.65"/>
                    <!-- Natural hand-drawn arrowhead barbs meeting seamlessly at tip (16, 52) -->
                    <path d="M 32 38 L 16 52 L 28 66" stroke="#facc15" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M 30 40 L 17 52 L 26 64" stroke="#fef08a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity="0.65"/>
                </svg>
            </div>

            <!-- Right Floating Card (60k+ Learners) - Visible on Desktop -->
            <div class="hero-floating-card card-right d-none d-lg-block">
                <div class="avatar-stack-hero">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=120" alt="Learner">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=120" alt="Learner">
                    <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=120" alt="Learner">
                    <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&q=80&w=120" alt="Learner">
                </div>
                <div class="stat-number">60k+</div>
                <p class="card-caption">Learners growing with expert guidance from trusted mentors.</p>
            </div>
        </div>

        <!-- Mobile & Tablet Responsive Stats Row -->
        <div class="d-flex d-lg-none justify-content-center gap-3 hero-mobile-stats-row">
            <div class="hero-mobile-stat-pill">
                <div class="stat-main">4.8 <span class="text-warning">★★★★★</span></div>
                <span class="stat-sub">Top Rated Courses</span>
            </div>
            <div class="hero-mobile-stat-pill">
                <div class="stat-main">60k+ <span class="text-primary fs-6">👥</span></div>
                <span class="stat-sub">Active Learners</span>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     SECTION 2: FADED DESIGN CREDENTIALS & TRUST CARDS
     ========================================================= -->
<section class="section-faded-trust">
    <div class="container position-relative" style="z-index: 2;">
        <div class="row g-3 g-lg-4">
            <!-- Faded Card 1: Govt. Recognized -->
            <div class="col-lg-3 col-6">
                <div class="faded-trust-card">
                    <div class="faded-watermark-icon">
                        <i class="fa fa-award"></i>
                    </div>
                    <div class="trust-icon-box box-blue">
                        <i class="fa fa-award"></i>
                    </div>
                    <div class="trust-content">
                        <span class="trust-badge-pill pill-blue">Certified</span>
                        <h4 class="trust-title">Govt. Recognized</h4>
                        <p class="trust-desc">Certified Curriculum & Online QR Verification</p>
                    </div>
                </div>
            </div>

            <!-- Faded Card 2: 100% Practical Labs -->
            <div class="col-lg-3 col-6">
                <div class="faded-trust-card">
                    <div class="faded-watermark-icon">
                        <i class="fa fa-laptop-code"></i>
                    </div>
                    <div class="trust-icon-box box-emerald">
                        <i class="fa fa-laptop-code"></i>
                    </div>
                    <div class="trust-content">
                        <span class="trust-badge-pill pill-emerald">Hands-on</span>
                        <h4 class="trust-title">100% Practical Labs</h4>
                        <p class="trust-desc">Real-World Projects & Exercise Practice Files</p>
                    </div>
                </div>
            </div>

            <!-- Faded Card 3: PDF Reader & Notes -->
            <div class="col-lg-3 col-6">
                <div class="faded-trust-card">
                    <div class="faded-watermark-icon">
                        <i class="fa fa-file-pdf"></i>
                    </div>
                    <div class="trust-icon-box box-sky">
                        <i class="fa fa-file-pdf"></i>
                    </div>
                    <div class="trust-content">
                        <span class="trust-badge-pill pill-sky">Built-in</span>
                        <h4 class="trust-title">PDF Reader & Notes</h4>
                        <p class="trust-desc">Study Cheatsheets & Books Inside Dashboard</p>
                    </div>
                </div>
            </div>

            <!-- Faded Card 4: Lifetime Access -->
            <div class="col-lg-3 col-6">
                <div class="faded-trust-card">
                    <div class="faded-watermark-icon">
                        <i class="fa fa-infinity"></i>
                    </div>
                    <div class="trust-icon-box box-amber">
                        <i class="fa fa-infinity"></i>
                    </div>
                    <div class="trust-content">
                        <span class="trust-badge-pill pill-amber">Unlimited</span>
                        <h4 class="trust-title">Lifetime Access</h4>
                        <p class="trust-desc">Self-Paced Learning on Mobile, Tablet & PC</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     COURSE CATALOG & EXPLORER
     ========================================================= -->
<section class="py-5 bg-light" id="catalog">
    <div class="container py-2">
        <!-- Section Header -->
        <!-- Section Header - Centered -->
        <div class="text-center mb-5">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold small text-uppercase" style="letter-spacing: 0.5px;">
                <?php echo $is_filtered_or_all ? 'Course Catalog' : 'Featured Programs'; ?>
            </span>
            <h2 class="catalog-section-title mt-3 mb-2">
                <?php if ($is_filtered_or_all): ?>
                    All Available Courses
                <?php else: ?>
                    Most <span class="brush-underline-wrap">Popular
                        <svg class="brush-underline-svg" viewBox="0 0 200 12" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                            <path d="M2,9 C40,3 80,11 120,6 C155,2 180,10 198,7" stroke="#0056D2" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span> Courses
                <?php endif; ?>
            </h2>
            <p class="text-muted mb-0" style="font-size: 0.95rem; max-width: 500px; margin: 0 auto;">
                <?php echo $is_filtered_or_all ? 'Explore all training modules and professional certifications' : 'Hand-picked career programs taught step-by-step with practical assignments'; ?>
            </p>
        </div>



        <?php if (!empty($search_term)): ?>
            <div class="mb-4 d-flex align-items-center gap-2 p-3 bg-white rounded-3 border">
                <span class="text-muted small">Showing results for <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong></span>
                <a href="./#catalog" class="badge bg-danger bg-opacity-10 text-danger text-decoration-none px-2 py-1">
                    Clear filter &times;
                </a>
            </div>
        <?php endif; ?>

        <!-- Course Cards Grid -->
        <div class="row g-4">
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
                                <?php if ($course['is_featured']): ?>
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
                    <div class="p-5 border rounded-4 bg-white mx-auto shadow-sm" style="max-width: 480px;">
                        <i class="fa fa-search fs-1 text-muted mb-3 opacity-25"></i>
                        <h5 class="fw-800 text-dark mb-1">No courses match your query</h5>
                        <p class="text-muted small mb-4">Try using different keywords or clear selected category filters.</p>
                        <a href="./#catalog" class="btn btn-primary rounded-pill px-4 fw-bold">Reset Filters</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Custom Centered "More Courses" Action Block -->
        <div class="text-center mt-5 pt-2">
            <a href="categories" class="btn-more-courses-custom">
                <span class="btn-icon-wrapper">
                    <i class="fa fa-layer-group"></i>
                </span>
                <span class="btn-text-content">
                    <span class="btn-main-label">Explore More Courses</span>
                    <span class="btn-sub-label">Browse all streams & certifications</span>
                </span>
                <span class="btn-arrow-wrapper">
                    <i class="fa fa-arrow-right"></i>
                </span>
            </a>
        </div>
    </div>
</section>

<!-- =========================================================
     WHY CHOOSE LKVM ACADEMY (INTERACTIVE BENEFIT CARDS)
     ========================================================= -->
<section class="py-5 bg-white border-top">
    <div class="container py-2">
        <div class="text-center max-w-700 mx-auto mb-5" style="max-width: 650px;">
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-1 rounded-pill fw-bold small text-uppercase">
                Learning Experience
            </span>
            <h2 class="fw-800 text-dark mt-2 mb-2">Designed For Student Success</h2>
            <p class="text-muted small">Everything you need to master modern computer literacy and land high-paying office or tech jobs.</p>
        </div>

        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="p-4 rounded-4 border bg-light h-100 transition-hover">
                    <div class="bg-primary text-white rounded-3 p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                        <i class="fa fa-tv fs-4"></i>
                    </div>
                    <h5 class="fw-800 text-dark mb-2">Smart Video Classroom</h5>
                    <p class="text-muted small mb-0">High-definition lessons with auto-advance, speed control, captions, and responsive layout.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="p-4 rounded-4 border bg-light h-100 transition-hover">
                    <div class="bg-info text-white rounded-3 p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                        <i class="fa fa-file-pdf fs-4"></i>
                    </div>
                    <h5 class="fw-800 text-dark mb-2">In-Browser PDF Viewer</h5>
                    <p class="text-muted small mb-0">Read lecture notes, cheatsheets, and practical books directly inside your dashboard without downloading.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="p-4 rounded-4 border bg-light h-100 transition-hover">
                    <div class="bg-success text-white rounded-3 p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                        <i class="fa fa-certificate fs-4"></i>
                    </div>
                    <h5 class="fw-800 text-dark mb-2">Verified Certificates</h5>
                    <p class="text-muted small mb-0">Receive accredited certificates with QR codes for instant online verification by employers.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="p-4 rounded-4 border bg-light h-100 transition-hover">
                    <div class="bg-warning text-white rounded-3 p-3 d-inline-flex align-items-center justify-content-center mb-3" style="width: 52px; height: 52px;">
                        <i class="fa fa-user-graduate fs-4"></i>
                    </div>
                    <h5 class="fw-800 text-dark mb-2">Expert Mentorship</h5>
                    <p class="text-muted small mb-0">Clear your doubts with experienced faculty specialized in DCA, Tally Prime, Excel, and Web Design.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     COMPACT HIGH-CONVERTING CTA BANNER
     ========================================================= -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="p-5 rounded-4 text-white text-center position-relative shadow-lg" 
             style="background: radial-gradient(circle at 50% 30%, #2575fc 0%, #0d4fd3 60%, #0a3bb3 100%); overflow: hidden; contain: paint;">
            <!-- Decorative circle -->
            <div class="position-absolute rounded-circle" style="width: 250px; height: 250px; background: rgba(255,255,255,0.06); top: -80px; right: 0; pointer-events: none;"></div>
            
            <div class="position-relative" style="z-index: 2; max-width: 650px; margin: 0 auto;">
                <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold small text-uppercase mb-3">
                    Start Today
                </span>
                <h2 class="display-6 fw-800 mb-3">Ready to Accelerate Your Career?</h2>
                <p class="text-white-50 mb-4" style="font-size: 1.05rem;">
                    Join thousands of students learning in-demand computer applications and digital accounting skills today.
                </p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="signup" class="btn btn-light rounded-pill px-5 py-3 fw-bold text-primary shadow">
                        <i class="fa fa-user-plus me-2"></i> Join Academy Free
                    </a>
                    <a href="categories" class="btn btn-outline-light rounded-pill px-4 py-3 fw-semibold">
                        Browse All Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>