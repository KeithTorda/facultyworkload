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

$faculty_id = $input['faculty_id'] ?? 0;
$semester = $input['semester'] ?? '';
$school_year = $input['school_year'] ?? '';

if (empty($faculty_id) || empty($semester) || empty($school_year)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Get all teaching loads for this faculty in the semester
$teaching_query = "
    SELECT tl.day, tl.time_start, tl.time_end
    FROM teaching_loads tl
    JOIN workloads w ON tl.workload_id = w.id
    WHERE w.faculty_id = ? AND w.semester = ? AND w.school_year = ?
    ORDER BY tl.day, tl.time_start
";
$stmt = $conn->prepare($teaching_query);
$stmt->bind_param("iss", $faculty_id, $semester, $school_year);
$stmt->execute();
$teaching_result = $stmt->get_result();

// Group schedules by day
$schedules_by_day = [];
while ($row = $teaching_result->fetch_assoc()) {
    $day = $row['day'];
    if (!isset($schedules_by_day[$day])) {
        $schedules_by_day[$day] = [];
    }
    $schedules_by_day[$day][] = [
        'start' => $row['time_start'],
        'end' => $row['time_end']
    ];
}

// Helper function to expand day combinations
function expandDays($day) {
    $dayMap = [
        'MWF' => ['Monday', 'Wednesday', 'Friday'],
        'TTH' => ['Tuesday', 'Thursday'],
        'MW' => ['Monday', 'Wednesday'],
        'MTH' => ['Monday', 'Thursday'],
        'MF' => ['Monday', 'Friday'],
        'MS' => ['Monday', 'Saturday'],
        'TF' => ['Tuesday', 'Friday'],
        'TS' => ['Tuesday', 'Saturday'],
        'WF' => ['Wednesday', 'Friday'],
        'WS' => ['Wednesday', 'Saturday'],
        'THS' => ['Thursday', 'Saturday'],
    ];
    
    $normalized = strtoupper(str_replace(' ', '', $day));
    if (isset($dayMap[$normalized])) {
        return $dayMap[$normalized];
    }
    if (strpos($day, '-') !== false) {
        return array_map('trim', explode('-', $day));
    }
    return [$day];
}

// Calculate vacant times for each day
$vacant_times = [];
$all_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

foreach ($all_days as $day) {
    $day_schedules = [];
    
    // Collect all schedules for this day (including from day combinations)
    foreach ($schedules_by_day as $schedule_day => $schedules) {
        $expanded = expandDays($schedule_day);
        if (in_array($day, $expanded)) {
            foreach ($schedules as $schedule) {
                $day_schedules[] = $schedule;
            }
        }
    }
    
    // Sort by start time
    usort($day_schedules, function($a, $b) {
        return strcmp($a['start'], $b['start']);
    });
    
    // Calculate vacant slots
    $vacant_slots = [];
    $start_time = strtotime('07:00:00'); // Start of day (7:00 AM)
    $end_time = strtotime('19:00:00');   // End of day (7:00 PM)
    
    foreach ($day_schedules as $schedule) {
        $schedule_start = strtotime($schedule['start']);
        $schedule_end = strtotime($schedule['end']);
        
        // If there's a gap before this schedule
        if ($start_time < $schedule_start) {
            $vacant_slots[] = [
                'start' => date('H:i:s', $start_time),
                'end' => date('H:i:s', $schedule_start)
            ];
        }
        
        $start_time = max($start_time, $schedule_end);
    }
    
    // If there's time after the last schedule
    if ($start_time < $end_time) {
        $vacant_slots[] = [
            'start' => date('H:i:s', $start_time),
            'end' => date('H:i:s', $end_time)
        ];
    }
    
    if (!empty($vacant_slots)) {
        $vacant_times[$day] = $vacant_slots;
    }
}

// Format for display
$formatted_vacant = [];
foreach ($vacant_times as $day => $slots) {
    $formatted_vacant[$day] = [];
    foreach ($slots as $slot) {
        $formatted_vacant[$day][] = [
            'start_24' => $slot['start'],
            'end_24' => $slot['end'],
            'start_12' => date('g:i A', strtotime($slot['start'])),
            'end_12' => date('g:i A', strtotime($slot['end'])),
            'duration' => round((strtotime($slot['end']) - strtotime($slot['start'])) / 3600, 1)
        ];
    }
}

echo json_encode([
    'success' => true,
    'vacant_times' => $formatted_vacant,
    'has_schedule' => !empty($schedules_by_day)
]);

