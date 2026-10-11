<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = get_request_data();
$action = $data['action'] ?? ($_GET['action'] ?? '');

$currentUser = get_auth_user($con);
$currentUserId = ($currentUser && $currentUser['user_type'] === 'student') ? $currentUser['id'] : 0;

// ==========================================
// 1. CREATE COURSE (Instructor / Admin)
// ==========================================
if ($action === 'create' || ($method === 'POST' && empty($_GET['id']) && empty($data['id']) && $action !== 'update' && $action !== 'delete')) {
    $title = trim($data['title'] ?? '');
    if (empty($title)) {
        json_res(false, 'Course title is required.', null, 400);
    }

    $slug = trim($data['slug'] ?? '');
    if (empty($slug)) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }
    
    // Check uniqueness
    $chk = mysqli_query($con, "SELECT id FROM lms_courses WHERE slug = '" . mysqli_real_escape_string($con, $slug) . "'");
    if (mysqli_num_rows($chk) > 0) {
        $slug .= '-' . time();
    }

    $category_id = intval($data['category_id'] ?? 1);
    $instructor = trim($data['instructor'] ?? ($currentUser['name'] ?? 'Senior Faculty'));
    $level = trim($data['level'] ?? 'All Levels');
    $duration = trim($data['duration'] ?? '10 Hours');
    $is_free = !empty($data['is_free']) ? 1 : 0;
    $price = $is_free ? 0.00 : floatval($data['price'] ?? 0);
    $discount_price = !empty($data['discount_price']) ? floatval($data['discount_price']) : null;
    $short_desc = trim($data['short_desc'] ?? '');
    $long_desc = trim($data['long_desc'] ?? '');
    $thumbnail = trim($data['thumbnail'] ?? 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=800');
    $preview_video = trim($data['preview_video'] ?? '');
    $status = isset($data['status']) ? intval($data['status']) : 1;
    $is_featured = !empty($data['is_featured']) ? 1 : 0;
    $has_certificate = !empty($data['has_certificate']) ? 1 : 1;

    $stmt = $con->prepare("INSERT INTO lms_courses (
        title, slug, category_id, instructor, level, duration, is_free, price, discount_price, 
        thumbnail, preview_video, short_desc, long_desc, status, is_featured, has_certificate
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssisssiddssssiii", 
            $title, $slug, $category_id, $instructor, $level, $duration, 
            $is_free, $price, $discount_price, $thumbnail, $preview_video, 
            $short_desc, $long_desc, $status, $is_featured, $has_certificate
        );
        if ($stmt->execute()) {
            $newCourseId = $stmt->insert_id;
            $stmt->close();
            
            // Add a welcome lesson if provided or sample lesson
            if (!empty($data['initial_lesson_title'])) {
                $lTitle = trim($data['initial_lesson_title']);
                $lUrl = trim($data['initial_lesson_url'] ?? '');
                $lType = trim($data['initial_lesson_type'] ?? 'youtube');
                $lStmt = $con->prepare("INSERT INTO lms_lessons (course_id, title, content_type, content_url, duration, is_preview, sort_order) VALUES (?, ?, ?, ?, '15 mins', 1, 1)");
                $lStmt->bind_param("isss", $newCourseId, $lTitle, $lType, $lUrl);
                $lStmt->execute();
                $lStmt->close();
            }

            json_res(true, 'Course created successfully!', [
                'course_id' => $newCourseId,
                'slug' => $slug,
                'title' => $title
            ], 201);
        } else {
            json_res(false, 'Database error while saving course.', null, 500);
        }
    } else {
        json_res(false, 'Database query preparation failed.', null, 500);
    }
}

// ==========================================
// 2. UPDATE COURSE
// ==========================================
if ($action === 'update' || $method === 'PUT') {
    $cid = intval($data['course_id'] ?? ($data['id'] ?? 0));
    if ($cid <= 0) {
        json_res(false, 'Valid Course ID is required.', null, 400);
    }

    $title = trim($data['title'] ?? '');
    $category_id = intval($data['category_id'] ?? 1);
    $instructor = trim($data['instructor'] ?? 'Senior Faculty');
    $level = trim($data['level'] ?? 'All Levels');
    $duration = trim($data['duration'] ?? '');
    $is_free = !empty($data['is_free']) ? 1 : 0;
    $price = $is_free ? 0.00 : floatval($data['price'] ?? 0);
    $discount_price = !empty($data['discount_price']) ? floatval($data['discount_price']) : null;
    $short_desc = trim($data['short_desc'] ?? '');
    $long_desc = trim($data['long_desc'] ?? '');
    $thumbnail = trim($data['thumbnail'] ?? '');
    $preview_video = trim($data['preview_video'] ?? '');
    $status = isset($data['status']) ? intval($data['status']) : 1;
    $is_featured = !empty($data['is_featured']) ? 1 : 0;

    $stmt = $con->prepare("UPDATE lms_courses SET 
        title = ?, category_id = ?, instructor = ?, level = ?, duration = ?, 
        is_free = ?, price = ?, discount_price = ?, short_desc = ?, long_desc = ?, 
        thumbnail = IF(? != '', ?, thumbnail), preview_video = ?, status = ?, is_featured = ?
        WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("sisssiddssssssiii", 
            $title, $category_id, $instructor, $level, $duration, 
            $is_free, $price, $discount_price, $short_desc, $long_desc, 
            $thumbnail, $thumbnail, $preview_video, $status, $is_featured, $cid
        );
        $stmt->execute();
        $stmt->close();
        json_res(true, 'Course updated successfully!', ['course_id' => $cid]);
    } else {
        json_res(false, 'Failed to update course.', null, 500);
    }
}

// ==========================================
// 3. DELETE COURSE
// ==========================================
if ($action === 'delete' || $method === 'DELETE') {
    $cid = intval($data['course_id'] ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
    if ($cid <= 0) {
        json_res(false, 'Valid Course ID is required.', null, 400);
    }

    mysqli_query($con, "DELETE FROM lms_lessons WHERE course_id = $cid");
    mysqli_query($con, "DELETE FROM lms_courses WHERE id = $cid");
    json_res(true, 'Course and associated curriculum removed successfully.');
}

// ==========================================
// 4. GET SINGLE COURSE DETAILS
// ==========================================
$courseId = intval($_GET['id'] ?? ($data['id'] ?? 0));
$slug = trim($_GET['slug'] ?? ($data['slug'] ?? ''));

if ($courseId > 0 || !empty($slug)) {
    if ($courseId > 0) {
        $stmt = $con->prepare("SELECT c.*, cat.name as category_name 
                               FROM lms_courses c 
                               LEFT JOIN lms_categories cat ON c.category_id = cat.id 
                               WHERE c.id = ? LIMIT 1");
        $stmt->bind_param("i", $courseId);
    } else {
        $stmt = $con->prepare("SELECT c.*, cat.name as category_name 
                               FROM lms_courses c 
                               LEFT JOIN lms_categories cat ON c.category_id = cat.id 
                               WHERE c.slug = ? LIMIT 1");
        $stmt->bind_param("s", $slug);
    }
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$course) {
        json_res(false, 'Course not found.', null, 404);
    }

    $cId = intval($course['id']);

    // Check enrollment status
    $isEnrolled = false;
    if ($currentUserId > 0) {
        $chkEnrolled = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $currentUserId AND course_id = $cId AND status = 'completed' LIMIT 1");
        if ($chkEnrolled && mysqli_num_rows($chkEnrolled) > 0) {
            $isEnrolled = true;
        }
    }

    // Fetch Lessons
    $lessonsQ = mysqli_query($con, "SELECT * FROM lms_lessons WHERE course_id = $cId ORDER BY sort_order ASC, id ASC");
    $lessons = [];
    $completedCount = 0;

    // Check user progress
    $completedLessonIds = [];
    if ($currentUserId > 0) {
        $pQ = mysqli_query($con, "SELECT lesson_id FROM lms_progress WHERE user_id = $currentUserId AND course_id = $cId");
        if ($pQ) {
            while ($pRow = mysqli_fetch_assoc($pQ)) {
                $completedLessonIds[] = intval($pRow['lesson_id']);
            }
        }
    }

    if ($lessonsQ) {
        while ($lRow = mysqli_fetch_assoc($lessonsQ)) {
            $lid = intval($lRow['id']);
            $isDone = in_array($lid, $completedLessonIds);
            if ($isDone) $completedCount++;

            $isAccessible = $isEnrolled || (intval($lRow['is_preview']) == 1);
            $lessons[] = [
                'id' => $lid,
                'course_id' => intval($lRow['course_id']),
                'title' => $lRow['title'],
                'description' => $lRow['description'] ?: '',
                'content_type' => $lRow['content_type'] ?: 'youtube',
                'content_url' => $isAccessible ? ($lRow['content_url'] ?: '') : '',
                'duration' => $lRow['duration'] ?: '10 mins',
                'is_preview' => intval($lRow['is_preview']) == 1,
                'sort_order' => intval($lRow['sort_order']),
                'is_completed' => $isDone,
                'is_locked' => !$isAccessible
            ];
        }
    }

    $totalLessons = count($lessons);
    $progressPercent = $totalLessons > 0 ? round(($completedCount / $totalLessons) * 100) : 0;

    $course['lessons_count'] = $totalLessons;
    $course['is_enrolled'] = $isEnrolled;
    $course['progress_percent'] = $progressPercent;
    $course['completed_lessons_count'] = $completedCount;
    $course['lessons'] = $lessons;

    json_res(true, 'Course details fetched.', $course);
}

// ==========================================
// 5. GET ALL COURSES (List, Filter & Search)
// ==========================================
$where = ["1=1"];

// Status check (students see only status=1, admin can see all if specified)
if (empty($_GET['all_status'])) {
    $where[] = "c.status = 1";
}

$catId = intval($_GET['category_id'] ?? 0);
if ($catId > 0) {
    $where[] = "c.category_id = $catId";
}

$search = trim($_GET['search'] ?? '');
if (!empty($search)) {
    $escaped = mysqli_real_escape_string($con, $search);
    $where[] = "(c.title LIKE '%$escaped%' OR c.short_desc LIKE '%$escaped%' OR c.instructor LIKE '%$escaped%')";
}

if (!empty($_GET['featured'])) {
    $where[] = "c.is_featured = 1";
}

if (isset($_GET['is_free'])) {
    $isFreeVal = intval($_GET['is_free']);
    $where[] = "c.is_free = $isFreeVal";
}

$whereClause = implode(" AND ", $where);
$limit = intval($_GET['limit'] ?? 50);

$query = "SELECT c.*, cat.name as category_name, 
          (SELECT COUNT(*) FROM lms_lessons WHERE course_id = c.id) as lessons_count,
          (SELECT COUNT(*) FROM lms_enrollments WHERE course_id = c.id AND status = 'completed') as students_count
          FROM lms_courses c 
          LEFT JOIN lms_categories cat ON c.category_id = cat.id 
          WHERE $whereClause 
          ORDER BY c.is_featured DESC, c.id DESC 
          LIMIT $limit";

$res = mysqli_query($con, $query);
$courses = [];

if ($res) {
    // If student logged in, get their enrolled course IDs in batch
    $userEnrolledCourseIds = [];
    if ($currentUserId > 0) {
        $enrQ = mysqli_query($con, "SELECT course_id FROM lms_enrollments WHERE user_id = $currentUserId AND status = 'completed'");
        if ($enrQ) {
            while ($erow = mysqli_fetch_assoc($enrQ)) {
                $userEnrolledCourseIds[] = intval($erow['course_id']);
            }
        }
    }

    while ($row = mysqli_fetch_assoc($res)) {
        $cid = intval($row['id']);
        $row['id'] = $cid;
        $row['price'] = floatval($row['price']);
        $row['discount_price'] = $row['discount_price'] ? floatval($row['discount_price']) : null;
        $row['is_free'] = intval($row['is_free']) == 1;
        $row['is_featured'] = intval($row['is_featured']) == 1;
        $row['is_enrolled'] = in_array($cid, $userEnrolledCourseIds);
        $row['lessons_count'] = intval($row['lessons_count']);
        $row['students_count'] = intval($row['students_count']);
        $courses[] = $row;
    }
}

json_res(true, 'Courses retrieved successfully', [
    'count' => count($courses),
    'courses' => $courses
]);
