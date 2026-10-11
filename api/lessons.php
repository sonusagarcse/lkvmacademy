<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$data = get_request_data();
$action = $data['action'] ?? ($_GET['action'] ?? '');

// 1. ADD LESSON
if ($action === 'create' || ($method === 'POST' && empty($_GET['id']) && empty($data['id']) && $action !== 'update' && $action !== 'delete')) {
    $courseId = intval($data['course_id'] ?? 0);
    $title = trim($data['title'] ?? '');
    
    if ($courseId <= 0 || empty($title)) {
        json_res(false, 'Course ID and lesson title are required.', null, 400);
    }

    $desc = trim($data['description'] ?? '');
    $type = trim($data['content_type'] ?? 'youtube');
    $url = trim($data['content_url'] ?? '');
    $duration = trim($data['duration'] ?? '10 mins');
    $isPreview = !empty($data['is_preview']) ? 1 : 0;
    
    // Sort order: get max + 1
    $soQ = mysqli_query($con, "SELECT MAX(sort_order) as m FROM lms_lessons WHERE course_id = $courseId");
    $maxSo = ($soQ && $r = mysqli_fetch_assoc($soQ)) ? intval($r['m']) : 0;
    $sortOrder = intval($data['sort_order'] ?? ($maxSo + 1));

    $stmt = $con->prepare("INSERT INTO lms_lessons (course_id, title, description, content_type, content_url, duration, is_preview, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssssii", $courseId, $title, $desc, $type, $url, $duration, $isPreview, $sortOrder);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            json_res(true, 'Lesson added successfully!', ['lesson_id' => $newId], 201);
        } else {
            json_res(false, 'Failed to insert lesson.', null, 500);
        }
    }
}

// 2. UPDATE LESSON
if ($action === 'update' || $method === 'PUT') {
    $lid = intval($data['lesson_id'] ?? ($data['id'] ?? 0));
    if ($lid <= 0) {
        json_res(false, 'Valid lesson ID is required.', null, 400);
    }

    $title = trim($data['title'] ?? '');
    $desc = trim($data['description'] ?? '');
    $type = trim($data['content_type'] ?? 'youtube');
    $url = trim($data['content_url'] ?? '');
    $duration = trim($data['duration'] ?? '');
    $isPreview = !empty($data['is_preview']) ? 1 : 0;
    $sortOrder = intval($data['sort_order'] ?? 0);

    $stmt = $con->prepare("UPDATE lms_lessons SET title = ?, description = ?, content_type = ?, content_url = ?, duration = ?, is_preview = ?, sort_order = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssssiii", $title, $desc, $type, $url, $duration, $isPreview, $sortOrder, $lid);
        $stmt->execute();
        $stmt->close();
        json_res(true, 'Lesson updated successfully!');
    } else {
        json_res(false, 'Failed to update lesson.', null, 500);
    }
}

// 3. DELETE LESSON
if ($action === 'delete' || $method === 'DELETE') {
    $lid = intval($data['lesson_id'] ?? ($data['id'] ?? ($_GET['id'] ?? 0)));
    if ($lid <= 0) {
        json_res(false, 'Valid lesson ID is required.', null, 400);
    }

    mysqli_query($con, "DELETE FROM lms_progress WHERE lesson_id = $lid");
    mysqli_query($con, "DELETE FROM lms_lessons WHERE id = $lid");
    json_res(true, 'Lesson deleted successfully.');
}

// 4. GET LESSONS FOR COURSE
$cid = intval($_GET['course_id'] ?? ($data['course_id'] ?? 0));
if ($cid > 0) {
    $user = get_auth_user($con);
    $currentUserId = ($user && isset($user['id'])) ? intval($user['id']) : intval($_GET['user_id'] ?? 0);
    $isAdmin = ($user && isset($user['user_type']) && $user['user_type'] === 'admin');

    $isEnrolled = false;
    if ($isAdmin) {
        $isEnrolled = true;
    } elseif ($currentUserId > 0) {
        $chk = mysqli_query($con, "SELECT id FROM lms_enrollments WHERE user_id = $currentUserId AND course_id = $cid AND status = 'completed' LIMIT 1");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $isEnrolled = true;
        }
    }

    $res = mysqli_query($con, "SELECT * FROM lms_lessons WHERE course_id = $cid ORDER BY sort_order ASC, id ASC");
    $lessons = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['id'] = intval($r['id']);
        $r['course_id'] = intval($r['course_id']);
        $isPreview = intval($r['is_preview']) == 1;
        $r['is_preview'] = $isPreview;
        $isAccessible = $isEnrolled || $isPreview;
        $r['is_locked'] = !$isAccessible;
        if (!$isAccessible) {
            $r['content_url'] = '';
        }
        $lessons[] = $r;
    }
    json_res(true, 'Lessons fetched successfully', $lessons);
}

json_res(false, 'Course ID is required.', null, 400);
