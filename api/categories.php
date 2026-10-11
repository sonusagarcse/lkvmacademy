<?php
require_once __DIR__ . '/config.php';

$sql = "SELECT cat.*, COUNT(c.id) as course_count 
        FROM lms_categories cat 
        LEFT JOIN lms_courses c ON c.category_id = cat.id AND c.status = 1
        WHERE cat.status = 1 
        GROUP BY cat.id 
        ORDER BY cat.name ASC";
$res = mysqli_query($con, $sql);

$categories = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $categories[] = [
            'id' => intval($row['id']),
            'name' => $row['name'],
            'slug' => $row['slug'],
            'icon' => $row['icon'] ?: 'fa-laptop-code',
            'description' => $row['description'] ?: '',
            'course_count' => intval($row['course_count'])
        ];
    }
}

json_res(true, 'Categories fetched successfully', $categories);
