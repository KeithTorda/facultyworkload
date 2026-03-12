<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid input', 'schedules' => []]);
    exit;
}

$room = $input['room'] ?? '';
$semester = $input['semester'] ?? '';
$school_year = $input['school_year'] ?? '';

if (empty($room)) {
    echo json_encode(['error' => 'Room is required', 'schedules' => []]);
    exit;
}

// Get all schedules for this room in the selected semester/year
$schedule_query = "
    SELECT 
        tl.day,
        tl.time_start,
        tl.time_end,
        tl.course_code,
        tl.course_title,
        tl.section,
        tl.room,
        u.name as faculty_name,
        w.semester,
        w.school_year
    FROM teaching_loads tl
    JOIN workloads w ON tl.workload_id = w.id
    JOIN users u ON w.faculty_id = u.id
    WHERE tl.room = ?
";

$params = [$room];
$param_types = "s";

if (!empty($semester)) {
    $schedule_query .= " AND w.semester = ?";
    $params[] = $semester;
    $param_types .= "s";
}

if (!empty($school_year)) {
    $schedule_query .= " AND w.school_year = ?";
    $params[] = $school_year;
    $param_types .= "s";
}

$schedule_query .= " ORDER BY 
    CASE tl.day
        WHEN 'Monday' THEN 1
        WHEN 'Tuesday' THEN 2
        WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4
        WHEN 'Friday' THEN 5
        WHEN 'Saturday' THEN 6
        WHEN 'MWF' THEN 7
        WHEN 'TTH' THEN 8
        ELSE 9
    END,
    tl.time_start
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$schedules = [];
while ($row = $result->fetch_assoc()) {
    $schedules[] = [
        'day' => $row['day'],
        'time_start' => $row['time_start'],
        'time_end' => $row['time_end'],
        'time_display' => date('g:i A', strtotime($row['time_start'])) . ' - ' . date('g:i A', strtotime($row['time_end'])),
        'course_code' => $row['course_code'],
        'course_title' => $row['course_title'],
        'section' => $row['section'],
        'room' => $row['room'],
        'faculty_name' => $row['faculty_name'],
        'semester' => $row['semester'],
        'school_year' => $row['school_year']
    ];
}

echo json_encode([
    'success' => true,
    'room' => $room,
    'semester' => $semester,
    'school_year' => $school_year,
    'schedules' => $schedules,
    'count' => count($schedules)
]);
?>

