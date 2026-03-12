<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

header('Content-Type: application/json');

$response = [
    'courses' => [],
    'sections' => [],
    'course_titles' => []
];

// Get all active courses
$courses_query = "SELECT course_code, course_title, course_category, units FROM courses WHERE status = 'active' ORDER BY course_category, course_code";
$courses_result = $conn->query($courses_query);

if ($courses_result) {
    while ($course = $courses_result->fetch_assoc()) {
        $response['courses'][] = [
            'code' => $course['course_code'],
            'title' => $course['course_title'],
            'category' => $course['course_category'] ?? 'Other',
            'units' => $course['units']
        ];
        
        // Also build course titles mapping
        $response['course_titles'][$course['course_code']] = $course['course_title'];
    }
}

// Get all active sections
$sections_query = "SELECT section_name, year_level FROM sections WHERE status = 'active' ORDER BY year_level, section_name";
$sections_result = $conn->query($sections_query);

if ($sections_result) {
    while ($section = $sections_result->fetch_assoc()) {
        $response['sections'][] = [
            'name' => $section['section_name'],
            'year_level' => $section['year_level']
        ];
    }
}

echo json_encode($response);
?>

