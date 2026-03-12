<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$room = $input['room'] ?? '';
$day = $input['day'] ?? '';
$time_start = $input['time_start'] ?? '';
$time_end = $input['time_end'] ?? '';
$semester = $input['semester'] ?? '';
$school_year = $input['school_year'] ?? '';

if (empty($room) || empty($day) || empty($time_start) || empty($time_end) || empty($semester) || empty($school_year)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Check for existing schedules in the same room, day, semester, and school year
$conflict_query = "
    SELECT 
        tl.course_code,
        tl.course_title,
        tl.time_start,
        tl.time_end,
        u.name as faculty_name
    FROM teaching_loads tl
    JOIN workloads w ON tl.workload_id = w.id
    JOIN users u ON w.faculty_id = u.id
    WHERE tl.room = ? 
    AND tl.day = ? 
    AND w.semester = ? 
    AND w.school_year = ?
";

$stmt = $conn->prepare($conflict_query);
$stmt->bind_param("ssss", $room, $day, $semester, $school_year);
$stmt->execute();
$existing_schedules = $stmt->get_result();

$has_conflict = false;
$conflicting_course = '';
$conflicting_faculty = '';

while ($schedule = $existing_schedules->fetch_assoc()) {
    // Check if times overlap
    $existing_start = strtotime($schedule['time_start']);
    $existing_end = strtotime($schedule['time_end']);
    $new_start = strtotime($time_start);
    $new_end = strtotime($time_end);
    
    // Times conflict if: (new_start < existing_end) AND (new_end > existing_start)
    if (($new_start < $existing_end) && ($new_end > $existing_start)) {
        $has_conflict = true;
        $conflicting_course = $schedule['course_code'] . ' - ' . $schedule['course_title'];
        $conflicting_faculty = $schedule['faculty_name'];
        break;
    }
}

$response = [
    'has_conflict' => $has_conflict,
    'conflicting_course' => $conflicting_course,
    'conflicting_faculty' => $conflicting_faculty,
    'room' => $room,
    'day' => $day,
    'time_slot' => date('g:i A', strtotime($time_start)) . ' - ' . date('g:i A', strtotime($time_end))
];

echo json_encode($response);
?> 