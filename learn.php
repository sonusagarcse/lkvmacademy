<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/certificate_helper.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = trim($_GET['slug'] ?? '');
$lesson_id = intval($_GET['lesson'] ?? 0);
$user_id = $_SESSION['student_id'] ?? null;
$student_name = $_SESSION['student_name'] ?? 'Learner';

// Fetch course
$stmt = $con->prepare("SELECT * FROM lms_courses WHERE slug = ? LIMIT 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>Course Not Found</h2><p><a href='index'>Return to Courses Catalog</a></p></div>");
}

// Fetch all lessons for this course
$lessons_q = $con->prepare("SELECT * FROM lms_lessons WHERE course_id = ? ORDER BY sort_order ASC, id ASC");
$lessons_q->bind_param("i", $course['id']);
$lessons_q->execute();
$lessons_res = $lessons_q->get_result();

$lessons_array = [];
while ($row = $lessons_res->fetch_assoc()) {
    $lessons_array[] = $row;
}
$lessons_q->close();

if (empty($lessons_array)) {
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'><h2>No lectures added yet</h2><p>Course content is being prepared by the instructor.</p><a href='details?slug=" . urlencode($course['slug']) . "'>Back to Course Details</a></div>");
}

// Active lesson identification
$active_lesson = null;
if ($lesson_id > 0) {
    foreach ($lessons_array as $l) {
        if ($l['id'] == $lesson_id) {
            $active_lesson = $l;
            break;
        }
    }
}
if (!$active_lesson) {
    $active_lesson = $lessons_array[0];
}

// Verify student enrollment strictly
$is_enrolled = false;
if ($user_id) {
    $check_enrolled = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $user_id AND course_id = {$course['id']} AND status = 'completed'");
    $is_enrolled = (mysqli_num_rows($check_enrolled) > 0);
}

// STRICT ACCESS ENFORCEMENT:
// If the student is NOT enrolled and the requested lesson is NOT a free trial preview:
if (!$is_enrolled && ($active_lesson['is_preview'] != 1)) {
    if (!$user_id) {
        // Not logged in -> redirect to login
        lms_redirect("login?redirect=" . urlencode("learn?slug=" . urlencode($course['slug']) . "&lesson=" . $active_lesson['id']));
    } else {
        // Logged in but not purchased/enrolled -> redirect to checkout/details
        lms_redirect("checkout?id=" . $course['id'] . "&notice=enroll_required");
    }
    exit();
}

// Handle AJAX Auto Mark Completed
if (isset($_POST['ajax_complete'])) {
    header('Content-Type: application/json');
    $target_lid = intval($_POST['lesson_id'] ?? 0);
    if ($user_id && $target_lid > 0) {
        mysqli_query($con, "INSERT IGNORE INTO lms_progress (user_id, lesson_id, course_id) VALUES ($user_id, $target_lid, {$course['id']})");
        // Try auto-issue certificate
        $cert_result = lms_try_auto_issue_certificate($con, $user_id, $course['id']);
        $resp = ['success' => true, 'lesson_id' => $target_lid, 'logged_in' => true];
        if ($cert_result) {
            $resp['certificate_issued'] = true;
            $resp['certificate_uid'] = $cert_result['certificate_uid'];

            // Dispatch graduation email
            $st_email = $_SESSION['student_email'] ?? '';
            if (!empty($st_email)) {
                lms_send_course_completed_email($con, $st_email, $student_name, $course['title'], $cert_result['certificate_uid']);
            }
        }
        echo json_encode($resp);
    } else {
        echo json_encode(['success' => true, 'lesson_id' => $target_lid, 'logged_in' => false]);
    }
    exit;
}

// Handle Mark Completed / Toggle
if (isset($_POST['toggle_complete']) && $user_id) {
    $target_lid = intval($_POST['lesson_id'] ?? 0);
    $chk_p = mysqli_query($con, "SELECT id FROM lms_progress WHERE user_id = $user_id AND lesson_id = $target_lid");
    if (mysqli_num_rows($chk_p) > 0) {
        mysqli_query($con, "DELETE FROM lms_progress WHERE user_id = $user_id AND lesson_id = $target_lid");
    } else {
        mysqli_query($con, "INSERT IGNORE INTO lms_progress (user_id, lesson_id, course_id) VALUES ($user_id, $target_lid, {$course['id']})");
        // Try auto-issue certificate on manual toggle
        $new_cert = lms_try_auto_issue_certificate($con, $user_id, $course['id']);
        if ($new_cert) {
            $st_email = $_SESSION['student_email'] ?? '';
            if (!empty($st_email)) {
                lms_send_course_completed_email($con, $st_email, $student_name, $course['title'], $new_cert['certificate_uid']);
            }
        }
    }
    // Check if certificate was just issued - redirect with celebration flag
    $just_issued_cert = lms_get_certificate($con, $user_id, $course['id']);
    $cert_param = ($just_issued_cert && $prog_percent_after = lms_check_course_completion($con, $user_id, $course['id']) && $prog_percent_after['completed']) ? '&cert_earned=1' : '';
    lms_redirect("learn?slug=" . urlencode($course['slug']) . "&lesson=" . $target_lid . $cert_param);
}

// Fetch Completed lessons for this user
$completed_lessons = [];
if ($user_id) {
    $prog_res = mysqli_query($con, "SELECT lesson_id FROM lms_progress WHERE user_id = $user_id");
    while ($p = mysqli_fetch_assoc($prog_res)) {
        $completed_lessons[] = $p['lesson_id'];
    }
}

// Identify Next and Previous Lessons
$next_lesson = null;
$prev_lesson = null;
foreach ($lessons_array as $idx => $l) {
    if ($l['id'] == $active_lesson['id']) {
        $prev_lesson = $lessons_array[$idx - 1] ?? null;
        $next_lesson = $lessons_array[$idx + 1] ?? null;
        break;
    }
}

$can_access_next = $next_lesson && ($is_enrolled || !empty($next_lesson['is_preview']));
$next_lesson_url = $can_access_next ? ("learn?slug=" . urlencode($course['slug']) . "&lesson=" . $next_lesson['id'] . "&autoplay=1") : null;
$next_lesson_title = $next_lesson ? $next_lesson['title'] : '';

// Calculate progress percentage
$total_lessons_count = count($lessons_array);
$completed_count = 0;
foreach ($lessons_array as $l) {
    if (in_array($l['id'], $completed_lessons)) {
        $completed_count++;
    }
}
$prog_percent = ($total_lessons_count > 0) ? round(($completed_count / $total_lessons_count) * 100) : 0;

// Helper to extract YouTube video ID
function get_youtube_video_id($url) {
    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
        return $match[1];
    }
    return '';
}

$yt_video_id = get_youtube_video_id($active_lesson['content_url'] ?? '');
$is_youtube = (!empty($yt_video_id) || $active_lesson['content_type'] == 'youtube');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($active_lesson['title']); ?> - <?php echo htmlspecialchars($course['title']); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="shortcut icon" href="favicon.ico">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Plyr Video Player CSS -->
    <link rel="stylesheet" href="https://cdn.plyr.io/3.7.8/plyr.css" />

    <style>
        :root {
            --primary: #6366f1;
            --dark: #0f172a;
            --bg: #f8fafc;
            --plyr-color-main: #6366f1;
            --plyr-video-control-color: #ffffff;
            --plyr-video-control-background-hover: #6366f1;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #ffffff;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .learn-topbar {
            height: 68px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1030;
        }
        .learn-body {
            flex: 1;
            display: flex;
            overflow: hidden;
        }
        .curriculum-sidebar {
            width: 360px;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: all 0.3s ease;
        }
        .lecture-content-area {
            flex: 1;
            overflow-y: auto;
            padding: 30px 40px;
            background: #ffffff;
        }
        .lesson-nav-item {
            padding: 14px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: #334155;
            transition: all 0.2s ease;
            font-size: 0.92rem;
        }
        .lesson-nav-item:hover {
            background: #f1f5f9;
            color: var(--primary);
        }
        .lesson-nav-item.active {
            background: #eef2ff;
            border-left: 4px solid var(--primary);
            color: var(--primary);
            font-weight: 700;
        }

        /* Clean Custom Video Player Box */
        .player-wrapper {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: #000000;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.18);
            margin-bottom: 12px;
        }

        /* Plyr customizations - Completely block clicks to YouTube iframe */
        .plyr--video {
            border-radius: 16px;
            overflow: hidden;
        }
        .plyr__video-embed iframe {
            top: 0 !important;
            height: 100% !important;
            pointer-events: none !important; /* CRITICAL: Blocks any clicks to YouTube's "More videos", logos, channel links */
        }
        .plyr__controls {
            padding: 12px 18px !important;
            background: linear-gradient(rgba(0,0,0,0), rgba(0,0,0,0.85)) !important;
            z-index: 10 !important;
            pointer-events: auto !important;
        }





        @media (max-width: 992px) {
            body {
                overflow-y: auto;
                height: auto;
            }
            .learn-body {
                flex-direction: column;
            }
            .curriculum-sidebar {
                width: 100%;
                order: 2;
                max-height: 400px;
            }
            .lecture-content-area {
                padding: 20px;
                order: 1;
            }
        }
    </style>
</head>
<body>

<!-- Topbar -->
<header class="learn-topbar">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold">
            <i class="fa fa-arrow-left me-1"></i> Dashboard
        </a>
        <div class="d-none d-md-block">
            <h6 class="fw-800 mb-0 text-dark"><?php echo htmlspecialchars($course['title']); ?></h6>
            <small class="text-muted"><i class="fa fa-user-tie me-1"></i> <?php echo htmlspecialchars($course['instructor']); ?></small>
        </div>
    </div>

    <!-- Progress indicator -->
    <div class="d-flex align-items-center gap-4">
        <?php if ($is_enrolled): ?>
            <div class="d-none d-sm-block text-end" style="min-width: 180px;">
                <div class="d-flex justify-content-between small fw-bold mb-1">
                    <span class="text-muted">Course Progress</span>
                    <span class="text-primary"><?php echo $prog_percent; ?>%</span>
                </div>
                <div class="progress" style="height: 6px; border-radius: 10px;">
                    <div class="progress-bar bg-success" style="width: <?php echo $prog_percent; ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
                <i class="fa fa-eye me-1"></i> FREE TRIAL PREVIEW
            </span>
            <a href="checkout?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                Unlock All Lectures
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="learn-body">
    <!-- Curriculum Sidebar -->
    <aside class="curriculum-sidebar">
        <div class="p-3 border-bottom bg-white">
            <h6 class="fw-800 mb-1 text-dark">Course Curriculum</h6>
            <small class="text-muted"><span id="lms-completed-count"><?php echo $completed_count; ?></span> of <?php echo $total_lessons_count; ?> completed</small>
        </div>

        <div class="flex-grow-1 overflow-y-auto">
            <?php foreach ($lessons_array as $index => $item): 
                $is_active = ($item['id'] == $active_lesson['id']);
                $is_done = in_array($item['id'], $completed_lessons);
                $is_locked = (!$is_enrolled && !$item['is_preview']);
            ?>
                <?php if ($is_locked): ?>
                    <a href="checkout?id=<?php echo $course['id']; ?>" class="lesson-nav-item opacity-75 text-muted" data-lesson-id="<?php echo $item['id']; ?>" title="Enroll to unlock this lecture">
                        <i class="fa fa-lock text-danger"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate"><?php echo htmlspecialchars($item['title']); ?></div>
                            <small class="text-danger" style="font-size:0.75rem;"><i class="fa fa-shopping-cart me-1"></i>Locked • Enroll to Unlock</small>
                        </div>
                    </a>
                <?php else: ?>
                    <a href="learn?slug=<?php echo urlencode($course['slug']); ?>&lesson=<?php echo $item['id']; ?>" 
                       class="lesson-nav-item <?php echo $is_active ? 'active' : ''; ?>"
                       data-lesson-id="<?php echo $item['id']; ?>">
                        <?php if ($is_done): ?>
                            <i class="fa fa-circle-check text-success fs-5 lesson-status-icon"></i>
                        <?php else: ?>
                            <?php
                            $c_type = $item['content_type'];
                            $icon_c = 'fa-play-circle text-muted';
                            if ($c_type == 'pdf' || preg_match('/\.pdf(\?.*)?$/i', $item['content_url'] ?? '')) {
                                $icon_c = 'fa-file-pdf text-danger';
                            } elseif ($c_type == 'image' || preg_match('/\.(png|jpe?g|webp|gif)(\?.*)?$/i', $item['content_url'] ?? '')) {
                                $icon_c = 'fa-image text-success';
                            } elseif ($c_type == 'file') {
                                $icon_c = 'fa-paperclip text-info';
                            } elseif ($c_type == 'text') {
                                $icon_c = 'fa-file-lines text-warning';
                            }
                            ?>
                            <i class="fa <?php echo $icon_c; ?> fs-5 lesson-status-icon"></i>
                        <?php endif; ?>

                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate fw-semibold"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="text-muted d-flex gap-2" style="font-size:0.75rem;">
                                <?php if (!empty($item['duration'])): ?>
                                    <span><i class="fa fa-clock me-1"></i><?php echo htmlspecialchars($item['duration']); ?></span>
                                <?php endif; ?>
                                <?php if ($item['is_preview'] && !$is_enrolled): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success">Free Demo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- Main Lecture Content Area -->
    <main class="lecture-content-area">
        <!-- Media Viewer with Custom LMS Video Player -->
        <div class="player-wrapper position-relative" id="lms-player-container" oncontextmenu="return false;">
            <?php if ($is_youtube && !empty($yt_video_id)): 
                $autoplay_param = (!empty($_GET['autoplay'])) ? '&autoplay=1' : '';
            ?>
                <!-- Plyr Embedded YouTube with custom LMS controls -->
                <div class="plyr__video-embed" id="lms-custom-player">
                    <iframe
                        id="yt-embed-iframe"
                        src="https://www.youtube.com/embed/<?php echo htmlspecialchars($yt_video_id); ?>?origin=<?php echo urlencode($coursesBaseUrl); ?>&iv_load_policy=3&modestbranding=1&playsinline=1&showinfo=0&rel=0&enablejsapi=1<?php echo $autoplay_param; ?>"
                        allowfullscreen
                        allowtransparency
                        allow="autoplay; encrypted-media; picture-in-picture"
                    ></iframe>
                </div>
            <?php elseif ($active_lesson['content_type'] == 'video' && !empty($active_lesson['content_url'])): ?>
                <!-- Plyr HTML5 Native Video Player -->
                <video id="lms-custom-player" playsinline controls class="w-100 h-100" <?php echo !empty($_GET['autoplay']) ? 'autoplay' : ''; ?>>
                    <source src="<?php echo htmlspecialchars($active_lesson['content_url']); ?>" type="video/mp4">
                    Your browser does not support HTML5 video.
                </video>
            <?php elseif (($active_lesson['content_type'] == 'pdf' || preg_match('/\.pdf(\?.*)?$/i', $active_lesson['content_url'] ?? '')) && !empty($active_lesson['content_url'])): 
                $pdf_file_url = $active_lesson['content_url'];
                if (!preg_match('/^https?:\/\//i', $pdf_file_url)) {
                    $pdf_file_url = $coursesBaseUrl . '/' . ltrim($pdf_file_url, '/');
                }
            ?>
                <!-- Interactive Classroom PDF Viewer -->
                <div class="card border-0 shadow rounded-4 overflow-hidden bg-white mb-2">
                    <div class="p-3 bg-dark text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger rounded-pill px-3 py-1 fw-bold">
                                <i class="fa fa-file-pdf me-1"></i> PDF DOCUMENT
                            </span>
                            <span class="fw-bold text-truncate text-white" style="max-width: 380px;">
                                <?php echo htmlspecialchars($active_lesson['title']); ?>
                            </span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <a href="<?php echo htmlspecialchars($pdf_file_url); ?>" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-semibold">
                                <i class="fa fa-expand me-1"></i> Fullscreen View
                            </a>
                            <a href="<?php echo htmlspecialchars($pdf_file_url); ?>" download class="btn btn-sm btn-primary rounded-pill px-3 fw-bold">
                                <i class="fa fa-download me-1"></i> Download PDF
                            </a>
                        </div>
                    </div>
                    <div class="pdf-viewer-box position-relative w-100" style="min-height: 700px; height: 78vh; background: #525659;">
                        <iframe 
                            src="<?php echo htmlspecialchars($pdf_file_url); ?>#toolbar=1&navpanes=1" 
                            class="w-100 h-100 border-0" 
                            title="<?php echo htmlspecialchars($active_lesson['title']); ?>">
                            <div class="p-4 text-center text-white">
                                Your browser cannot display embedded PDFs directly. 
                                <a href="<?php echo htmlspecialchars($pdf_file_url); ?>" target="_blank" class="btn btn-primary btn-sm ms-2">Download PDF</a>
                            </div>
                        </iframe>
                    </div>
                </div>
            <?php elseif (($active_lesson['content_type'] == 'image' || preg_match('/\.(png|jpe?g|webp|gif)(\?.*)?$/i', $active_lesson['content_url'] ?? '')) && !empty($active_lesson['content_url'])): 
                $img_file_url = $active_lesson['content_url'];
                if (!preg_match('/^https?:\/\//i', $img_file_url)) {
                    $img_file_url = $coursesBaseUrl . '/' . ltrim($img_file_url, '/');
                }
            ?>
                <!-- Interactive High-Res Image / Diagram Viewer -->
                <div class="card border-0 shadow rounded-4 overflow-hidden bg-dark text-center p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-3 px-2 text-white">
                        <span class="badge bg-success rounded-pill px-3 py-1 fw-bold">
                            <i class="fa fa-image me-1"></i> DIAGRAM / GRAPHIC
                        </span>
                        <div class="d-flex gap-2">
                            <a href="<?php echo htmlspecialchars($img_file_url); ?>" target="_blank" class="btn btn-sm btn-outline-light rounded-pill px-3">
                                <i class="fa fa-expand me-1"></i> Full Size
                            </a>
                            <a href="<?php echo htmlspecialchars($img_file_url); ?>" download class="btn btn-sm btn-light rounded-pill px-3 fw-bold">
                                <i class="fa fa-download me-1"></i> Save Image
                            </a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-center p-2 rounded-3 bg-black" style="min-height: 480px; max-height: 80vh; overflow: auto;">
                        <img src="<?php echo htmlspecialchars($img_file_url); ?>" alt="<?php echo htmlspecialchars($active_lesson['title']); ?>" class="img-fluid rounded-3 shadow" style="max-height: 75vh; object-fit: contain;">
                    </div>
                </div>
            <?php elseif ($active_lesson['content_type'] == 'file' && !empty($active_lesson['content_url'])): 
                $file_url = $active_lesson['content_url'];
                if (!preg_match('/^https?:\/\//i', $file_url)) {
                    $file_url = $coursesBaseUrl . '/' . ltrim($file_url, '/');
                }
            ?>
                <!-- Downloadable File Resource -->
                <div class="p-5 text-center bg-white border shadow-sm rounded-4 mb-2">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-4 mb-3">
                        <i class="fa fa-folder-open fs-1"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($active_lesson['title']); ?></h4>
                    <p class="text-muted mx-auto mb-4" style="max-width: 500px;">Download and review this complementary course resource.</p>
                    <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank" download class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">
                        <i class="fa fa-download me-2"></i> Download Resource File
                    </a>
                </div>
            <?php else: ?>
                <!-- Text / Notes Fallback -->
                <div class="p-5 bg-white border rounded-4 text-center mb-2">
                    <i class="fa fa-book-open fs-1 text-primary mb-3"></i>
                    <h4 class="fw-bold text-dark"><?php echo htmlspecialchars($active_lesson['title']); ?></h4>
                    <p class="text-muted">Reading lecture / Practical assignment</p>
                </div>
            <?php endif; ?>
        </div>



        <!-- Lecture Meta & Action Buttons -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 pb-4 border-bottom">
            <div>
                <h3 class="fw-800 text-dark mb-1"><?php echo htmlspecialchars($active_lesson['title']); ?></h3>
                <div class="text-muted small">
                    <span><i class="fa fa-graduation-cap me-1 text-primary"></i> <?php echo htmlspecialchars($course['title']); ?></span>
                    <?php if (!empty($active_lesson['duration'])): ?>
                        <span class="mx-2">•</span>
                        <span><i class="fa fa-clock me-1"></i> <?php echo htmlspecialchars($active_lesson['duration']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complete Toggle & Next Buttons -->
            <div class="d-flex align-items-center gap-2">
                <?php if ($user_id && $is_enrolled): 
                    $is_current_done = in_array($active_lesson['id'], $completed_lessons);
                ?>
                    <form method="POST" class="d-inline" id="form-toggle-complete">
                        <input type="hidden" name="lesson_id" value="<?php echo $active_lesson['id']; ?>">
                        <button type="submit" name="toggle_complete" id="btn-toggle-complete" class="btn rounded-pill px-4 fw-bold <?php echo $is_current_done ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="fa fa-check-circle me-1"></i> <span id="toggle-complete-label"><?php echo $is_current_done ? 'Completed' : 'Mark Complete'; ?></span>
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($prev_lesson && ($is_enrolled || $prev_lesson['is_preview'])): ?>
                    <a href="learn?slug=<?php echo urlencode($course['slug']); ?>&lesson=<?php echo $prev_lesson['id']; ?>" class="btn btn-light rounded-pill px-3 fw-semibold border">
                        <i class="fa fa-chevron-left me-1"></i> Previous
                    </a>
                <?php endif; ?>

                <?php if ($can_access_next): ?>
                    <a href="<?php echo htmlspecialchars($next_lesson_url); ?>" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        Next Lecture <i class="fa fa-chevron-right ms-1"></i>
                    </a>
                <?php elseif (!$is_enrolled): ?>
                    <a href="checkout?id=<?php echo $course['id']; ?>" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                        <i class="fa fa-lock me-1"></i> Unlock Remaining (Enroll Now)
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lecture Description / Notes -->
        <?php if (!empty($active_lesson['description'])): ?>
            <div class="card border-0 bg-light p-4 rounded-4 mb-4">
                <h5 class="fw-bold mb-2 text-dark"><i class="fa fa-sticky-note text-warning me-2"></i>Lesson Notes & Highlights</h5>
                <div class="text-muted" style="line-height: 1.7;">
                    <?php echo nl2br(htmlspecialchars($active_lesson['description'])); ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Plyr Player JS -->
<script src="https://cdn.plyr.io/3.7.8/plyr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Global Player Instance
let lmsPlyrInstance = null;

// Send command directly to YouTube IFrame via PostMessage
function sendYTCommand(func, args = []) {
    const iframe = document.getElementById('yt-embed-iframe') || 
                   document.querySelector('#lms-custom-player iframe') || 
                   document.querySelector('.plyr__video-embed iframe');
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage(JSON.stringify({
            event: 'command',
            func: func,
            args: args
        }), '*');
    }
}

// Toggle YouTube Closed Captions (Subtitles) ON or OFF
function toggleYTCaptions(enable) {
    if (enable) {
        sendYTCommand('loadModule', ['captions']);
        sendYTCommand('setOption', ['captions', 'track', { languageCode: 'en' }]);
        sendYTCommand('setOption', ['captions', 'reload', true]);
        if (lmsPlyrInstance && lmsPlyrInstance.embed && typeof lmsPlyrInstance.embed.loadModule === 'function') {
            try {
                lmsPlyrInstance.embed.loadModule('captions');
                lmsPlyrInstance.embed.setOption('captions', 'track', { languageCode: 'en' });
            } catch(e) {}
        }
        showPlayerToast('Subtitles (CC): ON');
    } else {
        sendYTCommand('unloadModule', ['captions']);
        sendYTCommand('setOption', ['captions', 'track', {}]);
        if (lmsPlyrInstance && lmsPlyrInstance.embed && typeof lmsPlyrInstance.embed.unloadModule === 'function') {
            try {
                lmsPlyrInstance.embed.unloadModule('captions');
                lmsPlyrInstance.embed.setOption('captions', 'track', {});
            } catch(e) {}
        }
        showPlayerToast('Subtitles (CC): OFF');
    }
}

// Select YouTube Playback Quality
function setYTQuality(qCode, label) {
    const qualityMap = {
        'auto': 'default',
        '1080': 'hd1080',
        '720': 'hd720',
        '480': 'large',
        '360': 'medium',
        '240': 'small'
    };
    const ytQuality = qualityMap[qCode] || 'default';

    // Direct PostMessage to YouTube Player
    sendYTCommand('setPlaybackQuality', [ytQuality]);
    sendYTCommand('setPlaybackQualityRange', [ytQuality, ytQuality]);

    // YT Player API via Plyr embed
    if (lmsPlyrInstance && lmsPlyrInstance.embed) {
        try {
            if (typeof lmsPlyrInstance.embed.setPlaybackQuality === 'function') {
                lmsPlyrInstance.embed.setPlaybackQuality(ytQuality);
            }
            if (typeof lmsPlyrInstance.embed.setPlaybackQualityRange === 'function') {
                lmsPlyrInstance.embed.setPlaybackQualityRange(ytQuality, ytQuality);
            }
        } catch(e) {}
    }

    showPlayerToast('Video Quality: ' + (qCode === 'auto' ? 'Auto' : qCode + 'p'));
}

// Inject Quality Option Directly into Plyr's Settings Menu (Gear Icon)
function setupPlyrQualityMenu(player) {
    if (!player || !player.elements || !player.elements.container) return;

    function injectMenu() {
        const container = player.elements.container;
        const menuContainer = container.querySelector('.plyr__menu__container');
        if (!menuContainer) {
            setTimeout(injectMenu, 150);
            return;
        }

        const homePane = menuContainer.querySelector('div[role="menu"]') || menuContainer.firstElementChild;
        if (!homePane || homePane.querySelector('#lms-quality-trigger')) return;

        // 1. Add Quality trigger row in main settings menu
        const qualityTrigger = document.createElement('button');
        qualityTrigger.id = 'lms-quality-trigger';
        qualityTrigger.type = 'button';
        qualityTrigger.role = 'menuitem';
        qualityTrigger.className = 'plyr__control plyr__control--forward';
        qualityTrigger.setAttribute('aria-haspopup', 'true');
        qualityTrigger.innerHTML = '<span>Quality<span class="plyr__menu__value" id="lms-quality-badge">Auto</span></span>';

        const speedBtn = homePane.querySelector('button[data-plyr="speed"]');
        if (speedBtn) {
            homePane.insertBefore(qualityTrigger, speedBtn);
        } else {
            homePane.appendChild(qualityTrigger);
        }

        // 2. Add Quality sub-menu pane
        const qualityPane = document.createElement('div');
        qualityPane.id = 'plyr-settings-quality-sub';
        qualityPane.role = 'menu';
        qualityPane.hidden = true;

        const backBtn = document.createElement('button');
        backBtn.type = 'button';
        backBtn.className = 'plyr__control plyr__control--back';
        backBtn.innerHTML = '<span>Quality</span>';
        qualityPane.appendChild(backBtn);

        const group = document.createElement('div');
        group.role = 'group';

        const resolutions = [
            { code: 'auto', label: 'Auto (Recommended)', short: 'Auto' },
            { code: '1080', label: '1080p Full HD', short: '1080p' },
            { code: '720', label: '720p HD', short: '720p' },
            { code: '480', label: '480p SD', short: '480p' },
            { code: '360', label: '360p', short: '360p' },
            { code: '240', label: '240p Data Saver', short: '240p' }
        ];

        resolutions.forEach((res, i) => {
            const optBtn = document.createElement('button');
            optBtn.type = 'button';
            optBtn.role = 'menuitemradio';
            optBtn.className = 'plyr__control';
            optBtn.setAttribute('aria-checked', i === 0 ? 'true' : 'false');
            optBtn.innerHTML = '<span>' + res.label + '</span>';

            optBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                group.querySelectorAll('button').forEach(b => b.setAttribute('aria-checked', 'false'));
                optBtn.setAttribute('aria-checked', 'true');

                const badge = document.getElementById('lms-quality-badge');
                if (badge) badge.textContent = res.short;

                setYTQuality(res.code, res.label);

                // Return to main settings pane
                qualityPane.hidden = true;
                homePane.hidden = false;
            });

            group.appendChild(optBtn);
        });

        qualityPane.appendChild(group);
        menuContainer.appendChild(qualityPane);

        // Sub-menu navigation
        qualityTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            homePane.hidden = true;
            qualityPane.hidden = false;
        });

        backBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            qualityPane.hidden = true;
            homePane.hidden = false;
        });
    }

    injectMenu();
    const settingsBtn = player.elements.container.querySelector('button[data-plyr="settings"]');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', () => {
            setTimeout(injectMenu, 50);
        });
    }
}

// Feedback Toast Notification
function showPlayerToast(msg) {
    let toast = document.getElementById('lms-player-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'lms-player-toast';
        toast.style.cssText = 'position:fixed; bottom:25px; right:25px; background:rgba(15,23,42,0.92); color:#fff; padding:10px 18px; border-radius:30px; font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.25); z-index:9999; transition:opacity 0.3s; opacity:0; pointer-events:none; border:1px solid rgba(255,255,255,0.15); display:flex; align-items:center; gap:8px;';
        document.body.appendChild(toast);
    }
    toast.innerHTML = '<i class="fa fa-info-circle text-primary"></i> ' + msg;
    toast.style.opacity = '1';
    clearTimeout(window.lmsToastTimer);
    window.lmsToastTimer = setTimeout(() => {
        toast.style.opacity = '0';
    }, 2200);
}

// Toggle play or pause smoothly
function toggleVideoPlayback() {
    if (!lmsPlyrInstance) return;
    if (lmsPlyrInstance.playing) {
        lmsPlyrInstance.pause();
    } else {
        lmsPlyrInstance.play();
    }
}

// Initialize Plyr with native controls and clean click-to-play
document.addEventListener('DOMContentLoaded', () => {
    const playerEl = document.getElementById('lms-custom-player');
    if (playerEl) {
        lmsPlyrInstance = new Plyr('#lms-custom-player', {
            controls: [
                'play-large',
                'restart',
                'rewind',
                'play',
                'fast-forward',
                'progress',
                'current-time',
                'duration',
                'mute',
                'volume',
                'captions',
                'settings',
                'pip',
                'fullscreen'
            ],
            settings: ['captions', 'speed'],
            captions: { active: false, language: 'auto', update: true },
            speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
            seekTime: 10,
            clickToPlay: false,
            keyboard: { focused: true, global: true },
            tooltips: { controls: true, seek: true },
            youtube: {
                noCookie: true,
                rel: 0,
                showinfo: 0,
                iv_load_policy: 3,
                modestbranding: 1,
                playsinline: 1,
                enablejsapi: 1
            }
        });

        // Setup Quality menu inside settings gear
        setupPlyrQualityMenu(lmsPlyrInstance);

        // Click anywhere on player container to toggle Play / Pause
        const playerContainer = document.getElementById('lms-player-container');
        if (playerContainer) {
            playerContainer.addEventListener('click', (e) => {
                // If clicked on controls bar, slider, or popup menu, let them handle it
                if (e.target.closest('.plyr__controls') || e.target.closest('.plyr__menu')) {
                    return;
                }
                toggleVideoPlayback();
            });

            playerContainer.addEventListener('dblclick', (e) => {
                if (e.target.closest('.plyr__controls') || e.target.closest('.plyr__menu')) {
                    return;
                }
                if (lmsPlyrInstance && lmsPlyrInstance.fullscreen) {
                    lmsPlyrInstance.fullscreen.toggle();
                }
            });

            // Prevent right-click context menu on the video player
            playerContainer.addEventListener('contextmenu', (e) => e.preventDefault());
        }

        // Sync with Plyr's native caption button events
        lmsPlyrInstance.on('captionsenabled', () => {
            toggleYTCaptions(true);
        });
        lmsPlyrInstance.on('captionsdisabled', () => {
            toggleYTCaptions(false);
        });

        // Auto-complete & Auto-advance on video end
        lmsPlyrInstance.on('ended', () => {
            handleVideoCompletion();
        });

        // Safety fallback: if video is within 0.5s of the end
        lmsPlyrInstance.on('timeupdate', () => {
            if (lmsPlyrInstance.duration > 5 && (lmsPlyrInstance.duration - lmsPlyrInstance.currentTime) < 0.4) {
                handleVideoCompletion();
            }
        });

        // Autoplay if instructed from previous lesson transition
        if (isAutoplayRequested) {
            lmsPlyrInstance.on('ready', () => {
                setTimeout(() => {
                    lmsPlyrInstance.play().catch(() => {});
                }, 350);
            });
        }
    }
});

// Lesson Auto-completion & Auto-advance System
const activeLessonId = <?php echo intval($active_lesson['id']); ?>;
const nextLessonUrl = <?php echo json_encode($next_lesson_url); ?>;
const hasNextLesson = <?php echo $can_access_next ? 'true' : 'false'; ?>;
const isAutoplayRequested = <?php echo !empty($_GET['autoplay']) ? 'true' : 'false'; ?>;

let completionProcessed = false;
let autoAdvanceTimer = null;

function handleVideoCompletion() {
    if (completionProcessed) return;
    completionProcessed = true;

    // 1. Post AJAX request to automatically mark lesson as completed
    const payload = new URLSearchParams();
    payload.append('ajax_complete', '1');
    payload.append('lesson_id', activeLessonId);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString()
    }).then(res => res.json()).then(data => {
        // Update Mark Complete button if present
        const btnToggle = document.getElementById('btn-toggle-complete');
        if (btnToggle) {
            btnToggle.classList.remove('btn-outline-success');
            btnToggle.classList.add('btn-success');
            const lbl = document.getElementById('toggle-complete-label');
            if (lbl) lbl.textContent = 'Completed';
        }

        // Update sidebar lesson checkmark
        const sidebarIcon = document.querySelector(`.lesson-nav-item[data-lesson-id="${activeLessonId}"] .lesson-status-icon`);
        if (sidebarIcon) {
            sidebarIcon.className = 'fa fa-circle-check text-success fs-5 lesson-status-icon';
        }

        // Increment sidebar completed count
        const countEl = document.getElementById('lms-completed-count');
        if (countEl) {
            const currentNum = parseInt(countEl.textContent || '0', 10);
            countEl.textContent = currentNum + 1;
        }
    }).catch(e => {
        console.log('Progress auto-sync:', e);
    });

    // 2. Show sleek auto-advance pill
    const advanceCard = document.getElementById('lms-auto-advance-card');
    if (advanceCard) {
        advanceCard.style.display = 'block';
        advanceCard.style.opacity = '0';
        advanceCard.style.transform = 'translateY(15px)';
        advanceCard.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        setTimeout(() => {
            advanceCard.style.opacity = '1';
            advanceCard.style.transform = 'translateY(0)';
        }, 30);

        if (hasNextLesson && nextLessonUrl) {
            let secondsLeft = 3;
            const secEl = document.getElementById('lms-countdown-sec');
            const barEl = document.getElementById('lms-countdown-bar');
            if (barEl) {
                setTimeout(() => {
                    barEl.style.width = '0%';
                }, 50);
            }

            autoAdvanceTimer = setInterval(() => {
                secondsLeft--;
                if (secEl) secEl.textContent = secondsLeft + 's';
                if (secondsLeft <= 0) {
                    clearInterval(autoAdvanceTimer);
                    window.location.href = nextLessonUrl;
                }
            }, 1000);

            // Allow student to cancel auto-advance if they wish to remain
            const cancelBtn = document.getElementById('lms-cancel-next-btn');
            if (cancelBtn) {
                cancelBtn.onclick = () => {
                    clearInterval(autoAdvanceTimer);
                    advanceCard.style.opacity = '0';
                    setTimeout(() => { advanceCard.style.display = 'none'; }, 300);
                    showLmsToast('Auto-advance paused. Take your time to review.');
                };
            }
        } else {
            // Course finished / No next lecture
            showLmsToast('🎉 Congratulations! You have completed all lessons in this course!');
            setTimeout(() => {
                advanceCard.style.opacity = '0';
                setTimeout(() => { advanceCard.style.display = 'none'; }, 350);
            }, 4500);
        }
    }
}

// Window message listener for YouTube API state change 0 (ENDED)
window.addEventListener('message', (e) => {
    try {
        let msg = typeof e.data === 'string' ? JSON.parse(e.data) : e.data;
        if (msg) {
            if (msg.event === 'onStateChange' && msg.info === 0) {
                handleVideoCompletion();
            } else if (msg.info && msg.info.playerState === 0) {
                handleVideoCompletion();
            }
        }
    } catch(err) {}
});
</script>

<!-- Sleek Floating Auto-Advance Pill -->
<div id="lms-auto-advance-card" class="position-fixed bottom-0 end-0 m-3 m-md-4 p-3 rounded-4 shadow-lg text-white" style="display:none; z-index:99999; width: min(390px, calc(100vw - 32px)); background: rgba(15, 23, 42, 0.96); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 20px 45px rgba(0,0,0,0.6);">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.25rem;">
            <i class="fa fa-circle-check"></i>
        </div>
        <div class="flex-grow-1 overflow-hidden">
            <div class="d-flex align-items-center justify-content-between mb-1">
                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.5px;">LESSON COMPLETED</span>
                <span class="text-white-50 small fw-bold" id="lms-countdown-sec">3s</span>
            </div>
            <div class="text-white text-truncate fw-semibold" style="font-size: 0.9rem;" id="lms-next-title">
                <?php echo htmlspecialchars($next_lesson_title); ?>
            </div>
        </div>
    </div>
    
    <?php if ($can_access_next): ?>
        <div class="progress mt-3 mb-2" style="height: 4px; background: rgba(255,255,255,0.12); border-radius: 99px; overflow: hidden;">
            <div id="lms-countdown-bar" class="progress-bar bg-primary" style="width: 100%; transition: width 3s linear;"></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2 pt-1">
            <button type="button" class="btn btn-sm btn-link text-white-50 text-decoration-none p-0" id="lms-cancel-next-btn">
                <i class="fa fa-times me-1"></i> Cancel
            </button>
            <a href="<?php echo htmlspecialchars($next_lesson_url); ?>" class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold" id="lms-play-next-now-btn">
                Play Next <i class="fa fa-forward ms-1"></i>
            </a>
        </div>
    <?php else: ?>
        <?php if (!$is_enrolled): ?>
            <div class="p-2 mt-3 bg-white bg-opacity-10 rounded-3 text-center">
                <small class="d-block mb-2 text-white-50">Trial finished! Enroll to unlock remaining lectures & notes.</small>
                <a href="checkout?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-warning w-100 rounded-pill fw-bold">
                    <i class="fa fa-lock-open me-1"></i> Unlock Full Course
                </a>
            </div>
        <?php else: ?>
            <?php 
                // Check for certificate
                lms_ensure_certificates_table($con);
                $earned_cert = lms_get_certificate($con, $user_id, $course['id']);
                if ($earned_cert): 
            ?>
            <div class="mt-2 pt-1">
                <a href="certificate-view?uid=<?php echo urlencode($earned_cert['certificate_uid']); ?>" class="btn btn-sm btn-warning w-100 rounded-pill fw-bold">
                    <i class="fa fa-award me-1"></i> View Your Certificate
                </a>
            </div>
            <?php else: ?>
            <div class="text-white-50 small mt-2">
                🎉 All lessons completed in this course!
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Certificate Celebration Modal -->
<?php 
    $show_celebration = isset($_GET['cert_earned']) || (isset($earned_cert) && $earned_cert && $prog_percent >= 100);
    $celebration_cert = $earned_cert ?? lms_get_certificate($con, $user_id ?? 0, $course['id']);
    // Only show once per session
    $celebration_key = 'cert_celebrated_' . $course['id'];
    if ($show_celebration && $celebration_cert && empty($_SESSION[$celebration_key])):
        $_SESSION[$celebration_key] = true;
?>
<div class="modal fade" id="certCelebrationModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="text-center" style="background: linear-gradient(135deg, #fefce8, #fffbeb); padding: 40px 30px 20px;">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 8px 25px rgba(245,158,11,0.4); margin-bottom: 16px;">
                    <i class="fa fa-award text-white" style="font-size: 2rem;"></i>
                </div>
                <h3 class="fw-800" style="color: #1e293b;">🎉 Congratulations!</h3>
                <p class="text-muted mb-0">You've earned a certificate!</p>
            </div>
            <div class="p-4 text-center">
                <div class="mb-3">
                    <div class="small text-muted fw-semibold mb-1">CERTIFICATE OF COMPLETION</div>
                    <h5 class="fw-800" style="color: #1e293b;"><?php echo htmlspecialchars($course['title']); ?></h5>
                </div>
                <div class="d-inline-block bg-light border rounded-3 px-3 py-2 mb-4" style="font-family: 'Courier New', monospace; font-weight: 700; letter-spacing: 1px; font-size: 0.9rem; color: #475569;">
                    <?php echo htmlspecialchars($celebration_cert['certificate_uid']); ?>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-light rounded-pill flex-fill fw-bold" data-bs-dismiss="modal">
                        Continue
                    </button>
                    <a href="certificate-view?uid=<?php echo urlencode($celebration_cert['certificate_uid']); ?>" class="btn btn-primary rounded-pill flex-fill fw-bold shadow-sm">
                        <i class="fa fa-eye me-1"></i> View Certificate
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('certCelebrationModal'));
    setTimeout(function() { modal.show(); }, 800);
});
</script>
<?php endif; ?>

</body>
</html>
