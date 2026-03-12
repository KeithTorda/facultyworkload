<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);

require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

// Helpers for schedule validation
if (!function_exists('fw_expand_day_list')) {
    function fw_expand_day_list(string $day_string): array
    {
        $day_string = trim($day_string);
        if ($day_string === '') {
            return [];
        }

        $map = [
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

        $upper = strtoupper(str_replace(' ', '', $day_string));
        if (isset($map[$upper])) {
            return $map[$upper];
        }

        if (str_contains($day_string, '-')) {
            return array_filter(array_map('trim', explode('-', $day_string)));
        }

        return [$day_string];
    }

    function fw_days_overlap(string $day_a, string $day_b): bool
    {
        $a = fw_expand_day_list($day_a);
        $b = fw_expand_day_list($day_b);
        return count(array_intersect($a, $b)) > 0;
    }

    function fw_times_overlap(string $start_a, string $end_a, string $start_b, string $end_b): bool
    {
        $a_start = strtotime($start_a);
        $a_end = strtotime($end_a);
        $b_start = strtotime($start_b);
        $b_end = strtotime($end_b);

        if ($a_start === false || $a_end === false || $b_start === false || $b_end === false) {
            return false;
        }

        return ($a_start < $b_end) && ($a_end > $b_start);
    }
}

// Check if we're in edit mode
$edit_mode = isset($_GET['edit']) && is_numeric($_GET['edit']);
$edit_workload_id = $edit_mode ? (int)$_GET['edit'] : 0;
$edit_data = null;
$edit_teaching_loads = [];
$edit_consultation_hours = [];
$edit_functions = [];

if ($edit_mode) {
    // Load existing workload data
    $workload_query = "SELECT * FROM workloads WHERE id = ?";
    $stmt = $conn->prepare($workload_query);
    $stmt->bind_param("i", $edit_workload_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
    
    if (!$edit_data) {
        header('Location: view-workloads.php?error=not_found');
        exit();
    }
    
    // Load teaching loads
    $teaching_query = "SELECT * FROM teaching_loads WHERE workload_id = ?";
    $stmt = $conn->prepare($teaching_query);
    $stmt->bind_param("i", $edit_workload_id);
    $stmt->execute();
    $teaching_result = $stmt->get_result();
    while ($row = $teaching_result->fetch_assoc()) {
        $edit_teaching_loads[] = $row;
    }
    
    // Load consultation hours
    $consultation_query = "SELECT * FROM consultation_hours WHERE workload_id = ?";
    $stmt = $conn->prepare($consultation_query);
    $stmt->bind_param("i", $edit_workload_id);
    $stmt->execute();
    $consultation_result = $stmt->get_result();
    while ($row = $consultation_result->fetch_assoc()) {
        $edit_consultation_hours[] = $row;
    }
    
    // Load functions
    $functions_query = "SELECT * FROM functions WHERE workload_id = ?";
    $stmt = $conn->prepare($functions_query);
    $stmt->bind_param("i", $edit_workload_id);
    $stmt->execute();
    $functions_result = $stmt->get_result();
    while ($row = $functions_result->fetch_assoc()) {
        $edit_functions[] = $row;
    }
}

$page_title = $edit_mode ? 'Edit Workload' : 'Encode Workload';
$success_message = '';
$error_message = '';

// Get all faculty for dropdown
$faculty_query = "SELECT id, name FROM users WHERE role = 'faculty' ORDER BY name";
$faculty_result = $conn->query($faculty_query);

// Handle form submission
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'save_workload') {
    $faculty_id = $_POST['faculty_id'];
    $semester = trim($_POST['semester']);
    $school_year = trim($_POST['school_year']);
    $program = trim($_POST['program']);
    $prepared_by = trim($_POST['prepared_by']);
    $prepared_by_title = trim($_POST['prepared_by_title']);
    $reviewed_by = trim($_POST['reviewed_by']);
    $reviewed_by_title = trim($_POST['reviewed_by_title']);
    $approved_by = trim($_POST['approved_by']);
    $approved_by_title = trim($_POST['approved_by_title']);
    
    if (empty($faculty_id) || empty($semester) || empty($school_year)) {
        $error_message = 'Faculty, semester, and school year are required.';
    } else {
        // Validate schedule conflicts before saving
        $teaching_inputs = isset($_POST['teaching_loads']) && is_array($_POST['teaching_loads']) ? $_POST['teaching_loads'] : [];
        $consultation_inputs = isset($_POST['consultation_hours']) && is_array($_POST['consultation_hours']) ? $_POST['consultation_hours'] : [];

        $teaching_entries = [];
        foreach ($teaching_inputs as $idx => $load) {
            $day = trim($load['day'] ?? '');
            $start = $load['time_start'] ?? '';
            $end = $load['time_end'] ?? '';
            if ($day === '' || $start === '' || $end === '') {
                continue;
            }
            $teaching_entries[] = [
                'index' => $idx,
                'course' => trim($load['course_code'] ?? 'Subject ' . ($idx + 1)),
                'room' => trim($load['room'] ?? ''),
                'day' => $day,
                'start' => $start,
                'end' => $end
            ];
        }

        $consultation_entries = [];
        foreach ($consultation_inputs as $idx => $consultation) {
            $day = trim($consultation['day'] ?? '');
            $start = $consultation['time_start'] ?? '';
            $end = $consultation['time_end'] ?? '';
            if ($day === '' || $start === '' || $end === '') {
                continue;
            }
            $consultation_entries[] = [
                'index' => $idx,
                'day' => $day,
                'start' => $start,
                'end' => $end,
                'room' => trim($consultation['room'] ?? '')
            ];
        }

        // Validate time ranges (7:00 AM - 7:00 PM)
        $time_range_errors = [];
        
        foreach ($teaching_entries as $idx => $load) {
            if (empty($load['start']) || empty($load['end'])) {
                continue;
            }
            
            $start_time = strtotime($load['start']);
            $end_time = strtotime($load['end']);
            $min_time = strtotime('07:00:00');
            $max_time = strtotime('19:00:00');
            
            if ($start_time === false || $end_time === false) {
                $time_range_errors[] = "Teaching load '{$load['course']}' has invalid time format.";
                continue;
            }
            
            if ($start_time < $min_time || $end_time > $max_time) {
                $time_range_errors[] = "Teaching load '{$load['course']}' has time outside allowed range (7:00 AM - 7:00 PM).";
            }
            if ($end_time <= $start_time) {
                $time_range_errors[] = "Teaching load '{$load['course']}' has invalid time range (end time must be after start time).";
            }
        }
        
        foreach ($consultation_entries as $idx => $consultation) {
            if (empty($consultation['start']) || empty($consultation['end'])) {
                continue;
            }
            
            $start_time = strtotime($consultation['start']);
            $end_time = strtotime($consultation['end']);
            $min_time = strtotime('07:00:00');
            $max_time = strtotime('19:00:00');
            
            if ($start_time === false || $end_time === false) {
                $time_range_errors[] = "Consultation hour on {$consultation['day']} has invalid time format.";
                continue;
            }
            
            if ($start_time < $min_time || $end_time > $max_time) {
                $time_range_errors[] = "Consultation hour on {$consultation['day']} has time outside allowed range (7:00 AM - 7:00 PM).";
            }
            if ($end_time <= $start_time) {
                $time_range_errors[] = "Consultation hour on {$consultation['day']} has invalid time range (end time must be after start time).";
            }
        }
        
        if (!empty($time_range_errors)) {
            $error_message = 'Cannot save workload. ' . implode(' ', $time_range_errors);
        } else {
            $schedule_conflicts = [];

            // Teaching vs teaching overlaps
            for ($i = 0; $i < count($teaching_entries); $i++) {
                for ($j = $i + 1; $j < count($teaching_entries); $j++) {
                    if (fw_days_overlap($teaching_entries[$i]['day'], $teaching_entries[$j]['day']) &&
                        fw_times_overlap($teaching_entries[$i]['start'], $teaching_entries[$i]['end'], $teaching_entries[$j]['start'], $teaching_entries[$j]['end'])) {
                        $room_note = '';
                        if ($teaching_entries[$i]['room'] !== '' && $teaching_entries[$i]['room'] === $teaching_entries[$j]['room']) {
                            $room_note = ' (room ' . $teaching_entries[$i]['room'] . ')';
                        }
                        $schedule_conflicts[] = "Teaching loads {$teaching_entries[$i]['course']} and {$teaching_entries[$j]['course']} overlap on {$teaching_entries[$i]['day']}{$room_note}.";
                    }
                }
            }

            // Consultation vs teaching overlaps
            foreach ($consultation_entries as $consultation) {
                foreach ($teaching_entries as $load) {
                    if (fw_days_overlap($consultation['day'], $load['day']) &&
                        fw_times_overlap($consultation['start'], $consultation['end'], $load['start'], $load['end'])) {
                        $schedule_conflicts[] = "Consultation on {$consultation['day']} (" . date('g:i A', strtotime($consultation['start'])) . " - " . date('g:i A', strtotime($consultation['end'])) . ") conflicts with class {$load['course']} (" . date('g:i A', strtotime($load['start'])) . " - " . date('g:i A', strtotime($load['end'])) . ").";
                    }
                }
            }

            // Consultation vs consultation overlaps
            for ($i = 0; $i < count($consultation_entries); $i++) {
                for ($j = $i + 1; $j < count($consultation_entries); $j++) {
                    if (fw_days_overlap($consultation_entries[$i]['day'], $consultation_entries[$j]['day']) &&
                        fw_times_overlap($consultation_entries[$i]['start'], $consultation_entries[$i]['end'], $consultation_entries[$j]['start'], $consultation_entries[$j]['end'])) {
                        $schedule_conflicts[] = "Consultation schedules on {$consultation_entries[$i]['day']} overlap (" . date('g:i A', strtotime($consultation_entries[$i]['start'])) . " - " . date('g:i A', strtotime($consultation_entries[$i]['end'])) . ").";
                    }
                }
            }

            if (!empty($schedule_conflicts)) {
                $error_message = 'Cannot save workload. Resolve these conflicts: ' . implode(' ', $schedule_conflicts);
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // If in edit mode, use the edit workload ID directly
                    if ($edit_mode && $edit_workload_id > 0) {
                        $workload_id = $edit_workload_id;
                        
                        // Update existing workload (including semester and school_year in case they were changed)
                        $update_workload = $conn->prepare("
                            UPDATE workloads SET semester = ?, school_year = ?, program = ?, prepared_by = ?, prepared_by_title = ?, 
                                   reviewed_by = ?, reviewed_by_title = ?, approved_by = ?, approved_by_title = ?
                            WHERE id = ?
                        ");
                        $update_workload->bind_param("sssssssssi", $semester, $school_year, $program, $prepared_by, $prepared_by_title, 
                                                   $reviewed_by, $reviewed_by_title, $approved_by, $approved_by_title, $workload_id);
                        $update_workload->execute();
                        
                        // Delete existing entries
                        $delete_teaching = $conn->prepare("DELETE FROM teaching_loads WHERE workload_id = ?");
                        $delete_teaching->bind_param("i", $workload_id);
                        $delete_teaching->execute();
                        
                        $delete_consultation = $conn->prepare("DELETE FROM consultation_hours WHERE workload_id = ?");
                        $delete_consultation->bind_param("i", $workload_id);
                        $delete_consultation->execute();
                        
                        $delete_functions = $conn->prepare("DELETE FROM functions WHERE workload_id = ?");
                        $delete_functions->bind_param("i", $workload_id);
                        $delete_functions->execute();
                    } else {
                        // Check if workload already exists (for new entries)
                        $check_workload = $conn->prepare("SELECT id FROM workloads WHERE faculty_id = ? AND semester = ? AND school_year = ?");
                        $check_workload->bind_param("iss", $faculty_id, $semester, $school_year);
                        $check_workload->execute();
                        $existing_workload = $check_workload->get_result()->fetch_assoc();
                        
                        if ($existing_workload) {
                            $workload_id = $existing_workload['id'];
                            
                            // Update existing workload
                            $update_workload = $conn->prepare("
                                UPDATE workloads SET program = ?, prepared_by = ?, prepared_by_title = ?, 
                                       reviewed_by = ?, reviewed_by_title = ?, approved_by = ?, approved_by_title = ?
                                WHERE id = ?
                            ");
                            $update_workload->bind_param("sssssssi", $program, $prepared_by, $prepared_by_title, 
                                                       $reviewed_by, $reviewed_by_title, $approved_by, $approved_by_title, $workload_id);
                            $update_workload->execute();
                            
                            // Delete existing entries
                            $delete_teaching = $conn->prepare("DELETE FROM teaching_loads WHERE workload_id = ?");
                            $delete_teaching->bind_param("i", $workload_id);
                            $delete_teaching->execute();
                            
                            $delete_consultation = $conn->prepare("DELETE FROM consultation_hours WHERE workload_id = ?");
                            $delete_consultation->bind_param("i", $workload_id);
                            $delete_consultation->execute();
                            
                            $delete_functions = $conn->prepare("DELETE FROM functions WHERE workload_id = ?");
                            $delete_functions->bind_param("i", $workload_id);
                            $delete_functions->execute();
                        } else {
                            // Insert new workload
                            $insert_workload = $conn->prepare("
                                INSERT INTO workloads (faculty_id, semester, school_year, program, prepared_by, 
                                                     prepared_by_title, reviewed_by, reviewed_by_title, approved_by, approved_by_title) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $insert_workload->bind_param("isssssssss", $faculty_id, $semester, $school_year, $program, 
                                                       $prepared_by, $prepared_by_title, $reviewed_by, $reviewed_by_title, 
                                                       $approved_by, $approved_by_title);
                            $insert_workload->execute();
                            $workload_id = $conn->insert_id;
                        }
                    }
                    
                    // Insert teaching loads
                    if (isset($_POST['teaching_loads']) && is_array($_POST['teaching_loads'])) {
                        // Check for duplicate time and room for same faculty
                        $duplicate_errors = [];
                        foreach ($_POST['teaching_loads'] as $idx => $load) {
                            if (empty($load['course_code']) || empty($load['course_title']) || 
                                empty($load['day']) || empty($load['time_start']) || empty($load['time_end']) || 
                                empty($load['room'])) {
                                continue;
                            }
                            
                            // Check for duplicates within the same submission
                            foreach ($_POST['teaching_loads'] as $idx2 => $load2) {
                                if ($idx >= $idx2) continue;
                                if (empty($load2['day']) || empty($load2['time_start']) || empty($load2['time_end']) || 
                                    empty($load2['room'])) {
                                    continue;
                                }
                                
                                // Check if same day, time, and room
                                if (fw_days_overlap($load['day'], $load2['day']) &&
                                    fw_times_overlap($load['time_start'], $load['time_end'], 
                                                   $load2['time_start'], $load2['time_end']) &&
                                    $load['room'] === $load2['room']) {
                                    $duplicate_errors[] = "Duplicate schedule: Same time ({$load['time_start']} - {$load['time_end']}) and room ({$load['room']}) on {$load['day']}.";
                                }
                            }
                        }
                        
                        if (!empty($duplicate_errors)) {
                            throw new Exception('Cannot save: ' . implode(' ', $duplicate_errors));
                        }
                        
                        // Check if lecture_units and lab_units columns exist, otherwise use units
                        $check_columns = $conn->query("SHOW COLUMNS FROM teaching_loads LIKE 'lecture_units'");
                        $has_separate_units = $check_columns->num_rows > 0;
                        
                        if ($has_separate_units) {
                            $teaching_stmt = $conn->prepare("
                                INSERT INTO teaching_loads (workload_id, course_code, course_title, section, room, 
                                                          day, time_start, time_end, lecture_units, lab_units, students, class_type) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            foreach ($_POST['teaching_loads'] as $load) {
                                if (!empty($load['course_code']) && !empty($load['course_title'])) {
                                    $lecture_units = isset($load['lecture_units']) ? (float)$load['lecture_units'] : 0;
                                    $lab_units = isset($load['lab_units']) ? (float)$load['lab_units'] : 0;
                                    
                                    // If both are 0, try to get from old 'units' field
                                    if ($lecture_units == 0 && $lab_units == 0 && isset($load['units'])) {
                                        if ($load['class_type'] === 'Lec') {
                                            $lecture_units = (float)$load['units'];
                                        } else {
                                            $lab_units = (float)$load['units'];
                                        }
                                    }
                                    
                                    $teaching_stmt->bind_param("isssssssddss", $workload_id, 
                                        $load['course_code'], $load['course_title'], $load['section'], $load['room'],
                                        $load['day'], $load['time_start'], $load['time_end'], 
                                        $lecture_units, $lab_units, $load['students'], $load['class_type']);
                                    $teaching_stmt->execute();
                                }
                            }
                        } else {
                            // Fallback to old units column
                            $teaching_stmt = $conn->prepare("
                                INSERT INTO teaching_loads (workload_id, course_code, course_title, section, room, 
                                                          day, time_start, time_end, units, students, class_type) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            
                            foreach ($_POST['teaching_loads'] as $load) {
                                if (!empty($load['course_code']) && !empty($load['course_title'])) {
                                    $units = 0;
                                    if (isset($load['lecture_units'])) {
                                        $units += (float)$load['lecture_units'];
                                    }
                                    if (isset($load['lab_units'])) {
                                        $units += (float)$load['lab_units'];
                                    }
                                    if ($units == 0 && isset($load['units'])) {
                                        $units = (float)$load['units'];
                                    }
                                    
                                    $teaching_stmt->bind_param("isssssssiss", $workload_id, 
                                        $load['course_code'], $load['course_title'], $load['section'], $load['room'],
                                        $load['day'], $load['time_start'], $load['time_end'], 
                                        $units, $load['students'], $load['class_type']);
                                    $teaching_stmt->execute();
                                }
                            }
                        }
                    }
                
                // Insert consultation hours
                if (isset($_POST['consultation_hours']) && is_array($_POST['consultation_hours'])) {
                    // Check for conflicts with teaching loads
                    $consultation_conflicts = [];
                    $teaching_inputs = isset($_POST['teaching_loads']) && is_array($_POST['teaching_loads']) ? $_POST['teaching_loads'] : [];
                    
                    foreach ($_POST['consultation_hours'] as $consultation) {
                        if (empty($consultation['day']) || empty($consultation['time_start']) || empty($consultation['time_end'])) {
                            continue;
                        }
                        
                        // Check against teaching loads
                        foreach ($teaching_inputs as $load) {
                            if (empty($load['day']) || empty($load['time_start']) || empty($load['time_end'])) {
                                continue;
                            }
                            
                            if (fw_days_overlap($consultation['day'], $load['day']) &&
                                fw_times_overlap($consultation['time_start'], $consultation['time_end'],
                                               $load['time_start'], $load['time_end'])) {
                                $consultation_conflicts[] = "Consultation on {$consultation['day']} ({$consultation['time_start']} - {$consultation['time_end']}) conflicts with teaching load on same day and time.";
                            }
                        }
                    }
                    
                    if (!empty($consultation_conflicts)) {
                        throw new Exception('Cannot save consultation: ' . implode(' ', $consultation_conflicts));
                    }
                    
                    $consultation_stmt = $conn->prepare("
                        INSERT INTO consultation_hours (workload_id, day, time_start, time_end, room) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    if (!$consultation_stmt) {
                        throw new Exception('Failed to prepare consultation hours statement: ' . $conn->error);
                    }
                    
                    foreach ($_POST['consultation_hours'] as $consultation) {
                        if (!empty($consultation['day']) && !empty($consultation['time_start']) && !empty($consultation['time_end'])) {
                            // Validate and format time values
                            $day = trim($consultation['day']);
                            $time_start = trim($consultation['time_start']);
                            $time_end = trim($consultation['time_end']);
                            $room = isset($consultation['room']) ? trim($consultation['room']) : '';
                            
                            // Validate time format (should be HH:MM or HH:MM:SS)
                            if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time_start) || 
                                !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time_end)) {
                                throw new Exception("Invalid time format for consultation hours. Day: {$day}, Start: {$time_start}, End: {$time_end}");
                            }
                            
                            $consultation_stmt->bind_param("issss", $workload_id, $day, $time_start, $time_end, $room);
                            
                            if (!$consultation_stmt->execute()) {
                                throw new Exception('Failed to insert consultation hour: ' . $consultation_stmt->error . ' - Day: ' . $day . ', Time: ' . $time_start . '-' . $time_end);
                            }
                        }
                    }
                }
                
                // Insert functions
                if (isset($_POST['functions']) && is_array($_POST['functions'])) {
                    $function_stmt = $conn->prepare("
                        INSERT INTO functions (workload_id, type, description, hours) 
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    foreach ($_POST['functions'] as $function) {
                        if (!empty($function['description']) && !empty($function['hours'])) {
                            $function_stmt->bind_param("issd", $workload_id, 
                                $function['type'], $function['description'], $function['hours']);
                            $function_stmt->execute();
                        }
                    }
                }
                
                $conn->commit();
                
                // Redirect with success message (only if no output has been sent)
                if ($edit_mode) {
                    // Make sure no output has been sent before redirect
                    if (!headers_sent()) {
                        header('Location: view-workloads.php?message=updated');
                        exit();
                    } else {
                        // If headers already sent, set success message instead
                        $success_message = 'Workload updated successfully!';
                    }
                } else {
                    $success_message = 'Workload saved successfully!';
                }
                
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = 'Error saving workload: ' . $e->getMessage();
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0">
        <i class="bi bi-<?php echo $edit_mode ? 'pencil-square' : 'calendar-plus'; ?>"></i> 
        <?php echo $edit_mode ? 'Edit Workload' : 'Encode Workload'; ?>
    </h2>
    <a href="view-workloads.php" class="btn btn-outline-primary">
        <i class="bi bi-<?php echo $edit_mode ? 'arrow-left' : 'file-text'; ?>"></i> 
        <span class="d-none d-sm-inline"><?php echo $edit_mode ? 'Back to List' : 'View Workloads'; ?></span>
    </a>
</div>

<?php if ($edit_mode): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Editing Mode:</strong> You are editing an existing workload. Make your changes and click "Save Workload" to update.
</div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($success_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="workloadForm">
    <input type="hidden" name="action" value="save_workload">
    
    <!-- Basic Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-info-circle"></i> Basic Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="faculty_id" class="form-label">Faculty Member *</label>
                    <select class="form-control" id="faculty_id" name="faculty_id" required <?php echo $edit_mode ? 'disabled' : ''; ?>>
                        <option value="">Select Faculty</option>
                        <?php while ($faculty = $faculty_result->fetch_assoc()): ?>
                            <option value="<?php echo $faculty['id']; ?>" <?php echo ($edit_mode && $edit_data['faculty_id'] == $faculty['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($faculty['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="faculty_id" value="<?php echo $edit_data['faculty_id']; ?>">
                        <small class="text-muted">Faculty cannot be changed when editing</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="semester" class="form-label">Semester *</label>
                    <select class="form-control" id="semester" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="First Semester" <?php echo ($edit_mode && $edit_data['semester'] == 'First Semester') ? 'selected' : ''; ?>>First Semester</option>
                        <option value="Second Semester" <?php echo ($edit_mode && $edit_data['semester'] == 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                        <option value="Summer" <?php echo ($edit_mode && $edit_data['semester'] == 'Summer') ? 'selected' : ''; ?>>Summer</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label for="school_year" class="form-label">School Year *</label>
                    <input type="text" class="form-control" id="school_year" name="school_year" 
                           placeholder="AY 2025-2026" value="<?php echo $edit_mode ? htmlspecialchars($edit_data['school_year']) : 'AY 2025-2026'; ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="program" class="form-label">Program</label>
                <input type="text" class="form-control" id="program" name="program" 
                       placeholder="Bachelor of Science in Information Technology"
                       value="<?php echo $edit_mode ? htmlspecialchars($edit_data['program']) : ''; ?>">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="prepared_by" class="form-label">Prepared By</label>
                    <input type="text" class="form-control" id="prepared_by" name="prepared_by"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['prepared_by']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="prepared_by_title" class="form-label">Position</label>
                    <input type="text" class="form-control" id="prepared_by_title" name="prepared_by_title"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['prepared_by_title']) : ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="reviewed_by" class="form-label">Reviewed By</label>
                    <input type="text" class="form-control" id="reviewed_by" name="reviewed_by"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['reviewed_by']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="reviewed_by_title" class="form-label">Position</label>
                    <input type="text" class="form-control" id="reviewed_by_title" name="reviewed_by_title"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['reviewed_by_title']) : ''; ?>">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="approved_by" class="form-label">Approved By</label>
                    <input type="text" class="form-control" id="approved_by" name="approved_by"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['approved_by']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="approved_by_title" class="form-label">Position</label>
                    <input type="text" class="form-control" id="approved_by_title" name="approved_by_title"
                           value="<?php echo $edit_mode ? htmlspecialchars($edit_data['approved_by_title']) : ''; ?>">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Teaching Loads -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-book"></i> Teaching Schedule</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addTeachingLoad()">
                <i class="bi bi-plus"></i> Add Subject
            </button>
        </div>
        <div class="card-body">
            <div id="teachingLoads">
                <!-- Teaching loads will be added here -->
            </div>
        </div>
    </div>
    
    <!-- Consultation Hours -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock"></i> Consultation Schedule</h5>
            <div>
                <button type="button" class="btn btn-sm btn-outline-success me-2" onclick="autoGenerateConsultationHours()">
                    <i class="bi bi-magic"></i> Auto-Generate Consultation Hours
                </button>
                <button type="button" class="btn btn-sm btn-outline-info me-2" onclick="showVacantTimes()">
                    <i class="bi bi-calendar-check"></i> Show Vacant Times
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addConsultationHour()">
                    <i class="bi bi-plus"></i> Add Schedule
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="consultationHours">
                <!-- Consultation hours will be added here -->
            </div>
        </div>
    </div>
    
    <!-- Functions -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-briefcase"></i> Functions (Research & Administrative)</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addFunction()">
                <i class="bi bi-plus"></i> Add Function
            </button>
        </div>
        <div class="card-body">
            <div id="functions">
                <!-- Functions will be added here -->
            </div>
        </div>
    </div>
    
    <!-- Conflict Warning Alert (Hidden by default) -->
    <div class="alert alert-danger d-none" id="conflictWarning" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">Room Schedule Conflicts Detected!</h5>
                <p class="mb-0">Please resolve all room conflicts before saving the workload. Check the red highlighted rooms above.</p>
            </div>
        </div>
    </div>
    
    <!-- Faculty schedule conflicts (teaching/consultation overlaps) -->
    <div class="alert alert-danger d-none" id="scheduleWarning" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-calendar-x-fill fs-3 me-3"></i>
            <div>
                <h5 class="alert-heading mb-1">Faculty schedule conflicts detected!</h5>
                <ul class="mb-0 ps-3" id="scheduleWarningList"></ul>
            </div>
        </div>
    </div>
    
    <!-- Submit Button -->
    <div class="text-center">
        <button type="submit" class="btn btn-success btn-lg" id="submitWorkloadBtn">
            <i class="bi bi-save"></i> <?php echo $edit_mode ? 'Update Workload' : 'Save Workload'; ?>
        </button>
        <?php if ($edit_mode): ?>
        <a href="view-workloads.php" class="btn btn-secondary btn-lg ms-2">
            <i class="bi bi-x-circle"></i> Cancel
        </a>
        <?php endif; ?>
    </div>
</form>

<!-- Room Conflict Warning Modal -->
<div class="modal fade" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="conflictModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Room Schedule Conflict Detected!
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-3">
                    <strong>Cannot save workload!</strong> There are room scheduling conflicts that must be resolved first.
                </div>
                
                <h6 class="fw-bold mb-3">Conflicts Found:</h6>
                <div id="conflictList" class="list-group">
                    <!-- Conflicts will be listed here -->
                </div>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <h6 class="fw-bold mb-2">How to resolve:</h6>
                    <ol class="mb-0 small">
                        <li>Change the room to an available one</li>
                        <li>Change the day or time schedule</li>
                        <li>Add a new room via the <a href="manage-rooms.php" target="_blank">Manage Rooms</a> page</li>
                    </ol>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close and Fix Conflicts
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Vacant Times Modal -->
<div class="modal fade" id="vacantTimesModal" tabindex="-1" aria-labelledby="vacantTimesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="vacantTimesModalLabel">
                    <i class="bi bi-calendar-check me-2"></i>Available Consultation Times
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Tip:</strong> Click on any time slot to automatically fill the consultation form.
                </div>
                <div id="vacantTimesContent">
                    <!-- Vacant times will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Room Schedule Viewer Modal -->
<div class="modal fade" id="roomScheduleModal" tabindex="-1" aria-labelledby="roomScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="roomScheduleModalLabel">
                    <i class="bi bi-calendar-week me-2"></i>Room Schedule Viewer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Select Room to View:</label>
                    <select class="form-control" id="viewRoomSelect" onchange="loadRoomScheduleDetails()">
                        <option value="">Choose a room...</option>
                        <?php 
                        $rooms_query2 = "SELECT room_name FROM rooms WHERE status = 'active' ORDER BY room_name";
                        $rooms_result2 = $conn->query($rooms_query2);
                        while ($room = $rooms_result2->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($room['room_name']); ?>">
                                <?php echo htmlspecialchars($room['room_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div id="roomScheduleContent">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-calendar3" style="font-size: 3rem;"></i>
                        <p class="mt-2">Select a room to view its schedule</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let teachingLoadCounter = 0;
let consultationHourCounter = 0;
let functionCounter = 0;

let coursesData = [];
let sectionsData = [];
let courseTitlesData = {};
let roomConflicts = {};

const dayCombinationMap = {
    'MWF': ['Monday', 'Wednesday', 'Friday'],
    'TTH': ['Tuesday', 'Thursday'],
    'MW': ['Monday', 'Wednesday'],
    'MTH': ['Monday', 'Thursday'],
    'MF': ['Monday', 'Friday'],
    'MS': ['Monday', 'Saturday'],
    'TF': ['Tuesday', 'Friday'],
    'TS': ['Tuesday', 'Saturday'],
    'WF': ['Wednesday', 'Friday'],
    'WS': ['Wednesday', 'Saturday'],
    'THS': ['Thursday', 'Saturday'],
};

// Convert 24-hour time to 12-hour format (HH:MM -> hh:mm AM/PM)
function formatTime12(value) {
    if (!value) return '';
    const parts = value.split(':');
    if (parts.length < 2) return value;
    const hours = parseInt(parts[0], 10);
    const minutes = parts[1] || '00';
    if (Number.isNaN(hours)) return value;
    const period = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours === 0 ? 12 : (hours > 12 ? hours - 12 : hours);
    return `${hour12}:${minutes} ${period}`;
}

// Convert 12-hour format to 24-hour format (hh:mm AM/PM -> HH:MM)
function parseTime12(value) {
    if (!value) return '';
    const match = value.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
    if (!match) return value; // Return as-is if not in expected format
    
    let hours = parseInt(match[1], 10);
    const minutes = match[2];
    const period = match[3].toUpperCase();
    
    if (period === 'PM' && hours !== 12) {
        hours += 12;
    } else if (period === 'AM' && hours === 12) {
        hours = 0;
    }
    
    return `${hours.toString().padStart(2, '0')}:${minutes}`;
}

// Create 12-hour time input HTML
function createTimeInput12(name, value = '') {
    const time24 = value || '';
    let hour12 = '', minute = '', period = 'AM';
    
    if (time24) {
        const parts = time24.split(':');
        const hours24 = parseInt(parts[0], 10);
        minute = parts[1] || '00';
        
        if (hours24 === 0) {
            hour12 = '12';
            period = 'AM';
        } else if (hours24 < 12) {
            hour12 = hours24.toString();
            period = 'AM';
        } else if (hours24 === 12) {
            hour12 = '12';
            period = 'PM';
        } else {
            hour12 = (hours24 - 12).toString();
            period = 'PM';
        }
    }
    
    return `
        <div class="input-group">
            <input type="number" class="form-control time-hour text-center" min="1" max="12" value="${hour12}" placeholder="12" style="min-width: 55px; font-weight: 500;">
            <span class="input-group-text bg-light fw-bold">:</span>
            <input type="number" class="form-control time-minute text-center" min="0" max="59" value="${minute}" placeholder="00" style="min-width: 55px; font-weight: 500;">
            <select class="form-select time-period" style="min-width: 75px; font-weight: 500;">
                <option value="AM" ${period === 'AM' ? 'selected' : ''}>AM</option>
                <option value="PM" ${period === 'PM' ? 'selected' : ''}>PM</option>
            </select>
            <input type="hidden" name="${name}" class="time-hidden" value="${time24}">
        </div>
    `;
}

// Validate if time is within allowed range (7:00 AM - 7:00 PM)
function isValidTimeRange(hours24, minutes) {
    const timeInMinutes = hours24 * 60 + minutes;
    const minTime = 7 * 60; // 7:00 AM = 420 minutes
    const maxTime = 19 * 60; // 7:00 PM = 1140 minutes
    return timeInMinutes >= minTime && timeInMinutes <= maxTime;
}

// Update hidden time field when 12-hour inputs change
function updateTimeHidden(inputGroup) {
    const hourInput = inputGroup.querySelector('.time-hour');
    const minuteInput = inputGroup.querySelector('.time-minute');
    const periodSelect = inputGroup.querySelector('.time-period');
    const hiddenInput = inputGroup.querySelector('.time-hidden');
    
    if (!hourInput || !minuteInput || !periodSelect || !hiddenInput) return;
    
    const hour = parseInt(hourInput.value, 10);
    let minute = String(minuteInput.value || '0').trim();
    if (minute === '' || isNaN(parseInt(minute, 10))) {
        minute = '00';
    } else {
        minute = String(parseInt(minute, 10)).padStart(2, '0');
        if (parseInt(minute, 10) > 59) minute = '59';
    }
    const period = periodSelect.value || 'AM';
    
    if (!hour || isNaN(hour) || hour < 1 || hour > 12) {
        hiddenInput.value = '';
        // Remove validation styling
        inputGroup.classList.remove('is-invalid');
        const errorMsg = inputGroup.parentElement.querySelector('.time-error-msg');
        if (errorMsg) errorMsg.remove();
        return;
    }
    
    let hours24 = hour;
    if (period === 'PM' && hour !== 12) {
        hours24 = hour + 12;
    } else if (period === 'AM' && hour === 12) {
        hours24 = 0;
    }
    
    // Validate time range (7:00 AM - 7:00 PM)
    const isValid = isValidTimeRange(hours24, parseInt(minute, 10));
    
    if (isValid) {
        hiddenInput.value = `${hours24.toString().padStart(2, '0')}:${minute}`;
        // Remove validation styling
        inputGroup.classList.remove('is-invalid');
        const errorMsg = inputGroup.parentElement.querySelector('.time-error-msg');
        if (errorMsg) errorMsg.remove();
    } else {
        hiddenInput.value = '';
        // Add validation styling
        inputGroup.classList.add('is-invalid');
        
        // Add error message if not exists
        let errorMsg = inputGroup.parentElement.querySelector('.time-error-msg');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'time-error-msg invalid-feedback d-block';
            errorMsg.style.fontSize = '0.875rem';
            inputGroup.parentElement.appendChild(errorMsg);
        }
        errorMsg.textContent = 'Time must be between 7:00 AM and 7:00 PM';
    }
}

// Update all time hidden fields before form submission
function updateAllTimeHiddenFields() {
    try {
        document.querySelectorAll('.time-input-12 .input-group').forEach(inputGroup => {
            if (inputGroup) {
                updateTimeHidden(inputGroup);
            }
        });
    } catch (error) {
        console.error('Error updating time hidden fields:', error);
    }
}

function expandDays(day) {
    if (!day) return [];
    const normalized = day.trim().toUpperCase().replace(/\s+/g, '');
    if (dayCombinationMap[normalized]) {
        return dayCombinationMap[normalized];
    }
    if (day.includes('-')) {
        return day.split('-').map(part => part.trim()).filter(Boolean);
    }
    return [day];
}

function daysOverlap(dayA, dayB) {
    const a = expandDays(dayA);
    const b = expandDays(dayB);
    return a.some(d => b.includes(d));
}

function toMinutes(time) {
    if (!time) return null;
    const [hours, minutes] = time.split(':').map(Number);
    if (Number.isNaN(hours) || Number.isNaN(minutes)) return null;
    return hours * 60 + minutes;
}

function timesOverlap(startA, endA, startB, endB) {
    const a1 = toMinutes(startA);
    const a2 = toMinutes(endA);
    const b1 = toMinutes(startB);
    const b2 = toMinutes(endB);
    if ([a1, a2, b1, b2].some(v => v === null)) return false;
    return a1 < b2 && a2 > b1;
}

function collectTeachingLoads() {
    const loads = [];
    document.querySelectorAll('#teachingLoads .border').forEach(row => {
        const idx = row.dataset.index;
        if (idx === undefined) return;
        const day = row.querySelector(`select[name="teaching_loads[${idx}][day]"]`)?.value || '';
        const startHidden = row.querySelector(`input[name="teaching_loads[${idx}][time_start]"]`);
        const endHidden = row.querySelector(`input[name="teaching_loads[${idx}][time_end]"]`);
        const start = startHidden?.value || '';
        const end = endHidden?.value || '';
        const course = row.querySelector(`select[name="teaching_loads[${idx}][course_code]"]`)?.value
            || row.querySelector(`input[name="teaching_loads[${idx}][course_title]"]`)?.value
            || `Subject ${Number(idx) + 1}`;
        const room = row.querySelector(`select[name="teaching_loads[${idx}][room]"]`)?.value || '';
        if (day && start && end) {
            loads.push({ index: idx, day, start, end, course, room });
        }
    });
    return loads;
}

function collectConsultations() {
    const consults = [];
    document.querySelectorAll('#consultationHours .border').forEach(row => {
        const idx = row.dataset.index;
        if (idx === undefined) return;
        const day = row.querySelector(`select[name="consultation_hours[${idx}][day]"]`)?.value || '';
        const startHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_start]"]`);
        const endHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_end]"]`);
        const start = startHidden?.value || '';
        const end = endHidden?.value || '';
        const room = row.querySelector(`input[name="consultation_hours[${idx}][room]"]`)?.value || '';
        if (day && start && end) {
            consults.push({ index: idx, day, start, end, room });
        }
    });
    return consults;
}

function renderScheduleWarning(conflicts) {
    const wrapper = document.getElementById('scheduleWarning');
    const list = document.getElementById('scheduleWarningList');
    if (!wrapper || !list) return;
    list.innerHTML = '';
    if (conflicts.length === 0) {
        wrapper.classList.add('d-none');
        return;
    }
    conflicts.forEach(msg => {
        const li = document.createElement('li');
        li.textContent = msg;
        list.appendChild(li);
    });
    wrapper.classList.remove('d-none');
}

function detectScheduleConflicts() {
    const loads = collectTeachingLoads();
    const consultations = collectConsultations();
    const conflicts = [];

    for (let i = 0; i < loads.length; i++) {
        for (let j = i + 1; j < loads.length; j++) {
            if (daysOverlap(loads[i].day, loads[j].day) &&
                timesOverlap(loads[i].start, loads[i].end, loads[j].start, loads[j].end)) {
                const roomNote = loads[i].room && loads[i].room === loads[j].room ? ` in room ${loads[i].room}` : '';
                conflicts.push(`Subjects ${loads[i].course} and ${loads[j].course} overlap on ${loads[i].day}${roomNote}.`);
            }
        }
    }

    consultations.forEach(consult => {
        loads.forEach(load => {
            if (daysOverlap(consult.day, load.day) &&
                timesOverlap(consult.start, consult.end, load.start, load.end)) {
                conflicts.push(`Consultation on ${consult.day} (${formatTime12(consult.start)} - ${formatTime12(consult.end)}) conflicts with ${load.course} (${formatTime12(load.start)} - ${formatTime12(load.end)}).`);
            }
        });
    });

    for (let i = 0; i < consultations.length; i++) {
        for (let j = i + 1; j < consultations.length; j++) {
            if (daysOverlap(consultations[i].day, consultations[j].day) &&
                timesOverlap(consultations[i].start, consultations[i].end, consultations[j].start, consultations[j].end)) {
                conflicts.push(`Consultation schedules overlap on ${consultations[i].day} (${formatTime12(consultations[i].start)} - ${formatTime12(consultations[i].end)}).`);
            }
        }
    }

    return conflicts;
}

function runScheduleValidation() {
    const conflicts = detectScheduleConflicts();
    renderScheduleWarning(conflicts);
    return conflicts;
}

function refreshConsultationStatuses() {
    document.querySelectorAll('#consultationHours .border').forEach(row => {
        const idx = row.dataset.index;
        if (idx !== undefined) {
            updateConsultationStatus(Number(idx));
        }
    });
}

function addTeachingLoad() {
    const container = document.getElementById('teachingLoads');
    const div = document.createElement('div');
    div.className = 'border p-3 mb-3 position-relative';
    div.dataset.index = teachingLoadCounter;
    
    let courseOptions = '<option value="">Select Course</option>';
    const coursesByCategory = {};
    
    coursesData.forEach(course => {
        if (!coursesByCategory[course.category]) {
            coursesByCategory[course.category] = [];
        }
        coursesByCategory[course.category].push(course);
    });
    
    for (const [category, courses] of Object.entries(coursesByCategory)) {
        courseOptions += `<optgroup label="${category} Courses">`;
        courses.forEach(course => {
            courseOptions += `<option value="${course.code}">${course.code}</option>`;
        });
        courseOptions += '</optgroup>';
    }
    courseOptions += '<option value="OTHER">Other (Type manually)</option>';
    
    let sectionOptions = '<option value="">Select</option>';
    sectionsData.forEach(section => {
        sectionOptions += `<option value="${section.name}">${section.name}</option>`;
    });
    
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2" 
                onclick="removeTeachingLoad(this)">
            <i class="bi bi-x"></i>
        </button>
        
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Course Code 
                    <a href="manage-courses.php" target="_blank" class="text-primary" title="Manage Courses">
                        <i class="bi bi-plus-circle-fill"></i>
                    </a>
                </label>
                <select class="form-control course-code-select" name="teaching_loads[${teachingLoadCounter}][course_code]" 
                        onchange="updateCourseTitle(this, ${teachingLoadCounter})">
                    ${courseOptions}
                </select>
                <input type="text" class="form-control mt-1 d-none course-code-manual" 
                       id="course_code_manual_${teachingLoadCounter}" placeholder="Enter course code">
            </div>
            <div class="col-md-5 mb-2">
                <label class="form-label">Course Title</label>
                <input type="text" class="form-control course-title-input" 
                       id="course_title_${teachingLoadCounter}"
                       name="teaching_loads[${teachingLoadCounter}][course_title]" 
                       placeholder="Course title will auto-fill">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Section 
                    <a href="manage-sections.php" target="_blank" class="text-primary" title="Manage Sections">
                        <i class="bi bi-plus-circle-fill"></i>
                    </a>
                </label>
                <select class="form-control" name="teaching_loads[${teachingLoadCounter}][section]">
                    ${sectionOptions}
                </select>
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Room 
                    <a href="manage-rooms.php" target="_blank" class="text-primary" title="Manage Rooms">
                        <i class="bi bi-plus-circle-fill"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1" 
                            onclick="viewRoomSchedule(${teachingLoadCounter})" 
                            title="View Room Schedule">
                        <i class="bi bi-calendar-event text-info"></i>
                    </button>
                </label>
                <select class="form-control room-select" name="teaching_loads[${teachingLoadCounter}][room]" 
                        id="room_select_${teachingLoadCounter}"
                        onchange="checkRoomAvailability(this, ${teachingLoadCounter})">
                    <option value="">Select Room</option>
                    <?php 
                    $rooms_query = "SELECT room_name FROM rooms WHERE status = 'active' ORDER BY room_name";
                    $rooms_for_js = $conn->query($rooms_query);
                    while ($room = $rooms_for_js->fetch_assoc()): 
                    ?>
                        <option value="<?php echo htmlspecialchars($room['room_name']); ?>">
                            <?php echo htmlspecialchars($room['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="invalid-feedback" id="room-conflict-${teachingLoadCounter}"></div>
                <small class="text-muted" id="room-schedule-hint-${teachingLoadCounter}"></small>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-2 mb-2">
                <label class="form-label">Day</label>
                <select class="form-control" name="teaching_loads[${teachingLoadCounter}][day]">
                    <option value="">Select Day</option>
                    <optgroup label="Single Days">
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </optgroup>
                    <optgroup label="Consecutive Days">
                        <option value="Monday-Tuesday">Monday-Tuesday</option>
                        <option value="Tuesday-Wednesday">Tuesday-Wednesday</option>
                        <option value="Wednesday-Thursday">Wednesday-Thursday</option>
                        <option value="Thursday-Friday">Thursday-Friday</option>
                        <option value="Friday-Saturday">Friday-Saturday</option>
                    </optgroup>
                    <optgroup label="Common Combinations">
                        <option value="MWF">MWF</option>
                        <option value="TTH">TTH</option>
                        <option value="MW">MW</option>
                        <option value="MTH">MTH</option>
                        <option value="MF">MF</option>
                        <option value="MS">MS</option>
                        <option value="TF">TF</option>
                        <option value="TS">TS</option>
                        <option value="WF">WF</option>
                        <option value="WS">WS</option>
                        <option value="THS">THS</option>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Start Time</label>
                <div class="time-input-12" data-name="teaching_loads[${teachingLoadCounter}][time_start]"></div>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">End Time</label>
                <div class="time-input-12" data-name="teaching_loads[${teachingLoadCounter}][time_end]"></div>
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Lec Units</label>
                <input type="number" class="form-control" name="teaching_loads[${teachingLoadCounter}][lecture_units]" 
                       min="0" max="6" value="0" step="0.5">
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Lab Units</label>
                <input type="number" class="form-control" name="teaching_loads[${teachingLoadCounter}][lab_units]" 
                       min="0" max="6" value="0" step="0.5">
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Students</label>
                <input type="number" class="form-control" name="teaching_loads[${teachingLoadCounter}][students]" 
                       min="1" placeholder="45">
            </div>
            <div class="col-md-1 mb-2">
                <label class="form-label">Type</label>
                <select class="form-control" name="teaching_loads[${teachingLoadCounter}][class_type]">
                    <option value="Lec">Lec</option>
                    <option value="Lab">Lab</option>
                </select>
            </div>
        </div>
    `;
    container.appendChild(div);
    
    // Initialize 12-hour time inputs
    const timeInputs = div.querySelectorAll('.time-input-12');
    timeInputs.forEach(timeDiv => {
        const name = timeDiv.getAttribute('data-name');
        timeDiv.innerHTML = createTimeInput12(name);
        
        // Add event listeners to update hidden field
        const inputGroup = timeDiv.querySelector('.input-group');
        if (inputGroup) {
            inputGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                input.addEventListener('change', () => {
                    updateTimeHidden(inputGroup);
                    const counter = teachingLoadCounter;
                    const roomSelect = div.querySelector(`select[name="teaching_loads[${counter}][room]"]`);
                    if (roomSelect) {
                        checkRoomAvailability(roomSelect, counter);
                    }
                    refreshConsultationStatuses();
                    runScheduleValidation();
                });
                input.addEventListener('input', () => {
                    updateTimeHidden(inputGroup);
                });
            });
        }
    });
    
    addConflictCheckListeners(teachingLoadCounter);
    teachingLoadCounter++;
    runScheduleValidation();
}

function removeTeachingLoad(button) {
    const row = button.closest('.border');
    if (row) {
        row.remove();
        updateConflictWarning();
        runScheduleValidation();
        refreshConsultationStatuses();
    }
}

function addConsultationHour() {
    const container = document.getElementById('consultationHours');
    const div = document.createElement('div');
    div.className = 'border p-3 mb-3 position-relative';
    div.dataset.index = consultationHourCounter;
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2" 
                onclick="removeConsultationHour(this)">
            <i class="bi bi-x"></i>
        </button>
        
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Day</label>
                <select class="form-control" name="consultation_hours[${consultationHourCounter}][day]">
                    <option value="">Select Day</option>
                    <optgroup label="Single Days">
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </optgroup>
                    <optgroup label="Consecutive Days">
                        <option value="Monday-Tuesday">Monday-Tuesday</option>
                        <option value="Tuesday-Wednesday">Tuesday-Wednesday</option>
                        <option value="Wednesday-Thursday">Wednesday-Thursday</option>
                        <option value="Thursday-Friday">Thursday-Friday</option>
                        <option value="Friday-Saturday">Friday-Saturday</option>
                    </optgroup>
                    <optgroup label="Common Combinations">
                        <option value="MWF">MWF</option>
                        <option value="TTH">TTH</option>
                        <option value="MW">MW</option>
                        <option value="MTH">MTH</option>
                        <option value="MF">MF</option>
                        <option value="MS">MS</option>
                        <option value="TF">TF</option>
                        <option value="TS">TS</option>
                        <option value="WF">WF</option>
                        <option value="WS">WS</option>
                        <option value="THS">THS</option>
                    </optgroup>
                </select>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Start Time</label>
                <div class="time-input-12" data-name="consultation_hours[${consultationHourCounter}][time_start]"></div>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">End Time</label>
                <div class="time-input-12" data-name="consultation_hours[${consultationHourCounter}][time_end]"></div>
            </div>
            <div class="col-md-3 mb-2">
                <label class="form-label">Room</label>
                <input type="text" class="form-control" name="consultation_hours[${consultationHourCounter}][room]" 
                       placeholder="Faculty Rm.">
            </div>
        </div>
        <div class="alert alert-info py-2 px-2 mt-2 d-none small" id="consultation-status-${consultationHourCounter}"></div>
    `;
    container.appendChild(div);

    // Initialize 12-hour time inputs
    const timeInputs = div.querySelectorAll('.time-input-12');
    timeInputs.forEach(timeDiv => {
        const name = timeDiv.getAttribute('data-name');
        timeDiv.innerHTML = createTimeInput12(name);
        
        // Add event listeners to update hidden field
        const inputGroup = timeDiv.querySelector('.input-group');
        if (inputGroup) {
            inputGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                input.addEventListener('change', () => {
                    updateTimeHidden(inputGroup);
                    updateConsultationStatus(consultationHourCounter);
                    runScheduleValidation();
                });
                input.addEventListener('input', () => {
                    updateTimeHidden(inputGroup);
                    updateConsultationStatus(consultationHourCounter);
                    runScheduleValidation();
                });
            });
        }
    });
    
    const daySelect = div.querySelector(`select[name="consultation_hours[${consultationHourCounter}][day]"]`);
    if (daySelect) {
        daySelect.addEventListener('change', () => {
            updateConsultationStatus(consultationHourCounter);
            runScheduleValidation();
        });
    }
    
    consultationHourCounter++;
}

function removeConsultationHour(button) {
    const row = button.closest('.border');
    if (row) {
        row.remove();
        runScheduleValidation();
    }
}

function addFunction() {
    const container = document.getElementById('functions');
    const div = document.createElement('div');
    div.className = 'border p-3 mb-3 position-relative';
    div.innerHTML = `
        <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2" 
                onclick="this.parentElement.remove()">
            <i class="bi bi-x"></i>
        </button>
        
        <div class="row">
            <div class="col-md-3 mb-2">
                <label class="form-label">Type</label>
                <select class="form-control" name="functions[${functionCounter}][type]">
                    <option value="admin">Administrative</option>
                    <option value="research">Research</option>
                </select>
            </div>
            <div class="col-md-7 mb-2">
                <label class="form-label">Description</label>
                <input type="text" class="form-control" name="functions[${functionCounter}][description]" 
                       placeholder="Infrastructure Development Officer">
            </div>
            <div class="col-md-2 mb-2">
                <label class="form-label">Hours</label>
                <input type="number" class="form-control" name="functions[${functionCounter}][hours]" 
                       min="0" step="0.5" placeholder="9.00">
            </div>
        </div>
    `;
    container.appendChild(div);
    functionCounter++;
}

function updateConsultationStatus(counter) {
    const statusEl = document.getElementById(`consultation-status-${counter}`);
    const daySelect = document.querySelector(`select[name="consultation_hours[${counter}][day]"]`);
    const startHidden = document.querySelector(`input[name="consultation_hours[${counter}][time_start]"]`);
    const endHidden = document.querySelector(`input[name="consultation_hours[${counter}][time_end]"]`);

    if (!statusEl || !daySelect || !startHidden || !endHidden) return;

    const day = daySelect.value;
    const start = startHidden.value;
    const end = endHidden.value;
    
    // Get the input groups for visual feedback
    const startGroup = startHidden.closest('.time-input-12')?.querySelector('.input-group');
    const endGroup = endHidden.closest('.time-input-12')?.querySelector('.input-group');

    statusEl.classList.add('d-none');
    statusEl.classList.remove('alert-danger', 'alert-success');
    [startGroup, endGroup].forEach(group => {
        if (group) {
            group.classList.remove('is-invalid', 'is-valid');
        }
    });

    if (!day || !start || !end) {
        return;
    }

    const conflicts = collectTeachingLoads().filter(load => 
        daysOverlap(day, load.day) && timesOverlap(start, end, load.start, load.end)
    );

    if (conflicts.length > 0) {
        statusEl.classList.remove('d-none');
        statusEl.classList.add('alert-danger');
        statusEl.textContent = `⚠️ Cannot save: Conflicts with ${conflicts[0].course} (${formatTime12(conflicts[0].start)} - ${formatTime12(conflicts[0].end)}) on same day and time.`;
        [startGroup, endGroup].forEach(group => {
            if (group) {
                group.classList.add('is-invalid');
            }
        });
    } else {
        statusEl.classList.remove('d-none');
        statusEl.classList.add('alert-success');
        statusEl.textContent = '✓ Vacant time: no teaching schedule overlaps.';
        [startGroup, endGroup].forEach(group => {
            if (group) {
                group.classList.add('is-valid');
            }
        });
    }
}

// Show vacant times modal
async function showVacantTimes() {
    const facultyId = document.getElementById('faculty_id').value;
    const semester = document.getElementById('semester').value;
    const schoolYear = document.getElementById('school_year').value;
    
    if (!facultyId || !semester || !schoolYear) {
        alert('Please select Faculty, Semester, and School Year first.');
        return;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('vacantTimesModal'));
    modal.show();
    
    const content = document.getElementById('vacantTimesContent');
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading vacant times...</p>
        </div>
    `;
    
    try {
        const response = await fetch('get-vacant-times.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                faculty_id: facultyId,
                semester: semester,
                school_year: schoolYear
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (Object.keys(data.vacant_times).length === 0) {
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i>
                        <strong>No vacant times found.</strong> The faculty has a full schedule or no teaching loads assigned yet.
                    </div>
                `;
            } else {
                let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Day</th><th>Available Time Slots</th></tr></thead><tbody>';
                
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                days.forEach(day => {
                    if (data.vacant_times[day] && data.vacant_times[day].length > 0) {
                        html += `<tr><td><strong>${day}</strong></td><td>`;
                        data.vacant_times[day].forEach(slot => {
                            html += `<span class="badge bg-success me-2 mb-2" style="cursor: pointer;" onclick="selectVacantTime('${day}', '${slot.start_24}', '${slot.end_24}')">
                                ${slot.start_12} - ${slot.end_12} (${slot.duration} hrs)
                            </span>`;
                        });
                        html += '</td></tr>';
                    }
                });
                
                html += '</tbody></table></div>';
                content.innerHTML = html;
            }
        } else {
            content.innerHTML = `<div class="alert alert-danger">Error: ${data.error || 'Failed to load vacant times'}</div>`;
        }
    } catch (error) {
        console.error('Error loading vacant times:', error);
        content.innerHTML = '<div class="alert alert-danger">Error loading vacant times. Please try again.</div>';
    }
}

// Auto-generate consultation hours for all vacant time slots
async function autoGenerateConsultationHours() {
    const facultyId = document.getElementById('faculty_id').value;
    const semester = document.getElementById('semester').value;
    const schoolYear = document.getElementById('school_year').value;
    
    if (!facultyId || !semester || !schoolYear) {
        alert('Please select Faculty, Semester, and School Year first.');
        return;
    }
    
    // Confirm with user
    if (!confirm('This will automatically generate consultation hours for all vacant time slots. Continue?')) {
        return;
    }
    
    try {
        const response = await fetch('get-vacant-times.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                faculty_id: facultyId,
                semester: semester,
                school_year: schoolYear
            })
        });
        
        const data = await response.json();
        
        if (data.success && Object.keys(data.vacant_times).length > 0) {
            let generatedCount = 0;
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            days.forEach(day => {
                if (data.vacant_times[day] && data.vacant_times[day].length > 0) {
                    data.vacant_times[day].forEach(slot => {
                        // Normalize time format
                        const normalizeTime = (time) => {
                            if (!time) return '';
                            return time.substring(0, 5); // Remove seconds if present
                        };
                        
                        const start24 = normalizeTime(slot.start_24);
                        const end24 = normalizeTime(slot.end_24);
                        
                        // Add consultation hour
                        addConsultationHour();
                        
                        // Wait a bit for DOM to update
                        setTimeout(() => {
                            const consultationRows = document.querySelectorAll('#consultationHours .border');
                            const lastRow = consultationRows[consultationRows.length - 1];
                            const idx = lastRow.dataset.index;
                            
                            if (lastRow && idx !== undefined) {
                                // Set day
                                const daySelect = lastRow.querySelector(`select[name="consultation_hours[${idx}][day]"]`);
                                if (daySelect) {
                                    daySelect.value = day;
                                }
                                
                                // Set times
                                const startDiv = lastRow.querySelector(`div[data-name="consultation_hours[${idx}][time_start]"]`);
                                const endDiv = lastRow.querySelector(`div[data-name="consultation_hours[${idx}][time_end]"]`);
                                
                                if (startDiv && endDiv) {
                                    startDiv.innerHTML = createTimeInput12(`consultation_hours[${idx}][time_start]`, start24);
                                    endDiv.innerHTML = createTimeInput12(`consultation_hours[${idx}][time_end]`, end24);
                                    
                                    // Re-attach event listeners
                                    [startDiv, endDiv].forEach(div => {
                                        const inputGroup = div.querySelector('.input-group');
                                        if (inputGroup) {
                                            const hiddenInput = inputGroup.querySelector('.time-hidden');
                                            if (hiddenInput) {
                                                if (div === startDiv) {
                                                    hiddenInput.value = start24;
                                                } else {
                                                    hiddenInput.value = end24;
                                                }
                                            }
                                            updateTimeHidden(inputGroup);
                                            
                                            inputGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                                                input.addEventListener('change', () => {
                                                    updateTimeHidden(inputGroup);
                                                    updateConsultationStatus(idx);
                                                    runScheduleValidation();
                                                });
                                                input.addEventListener('input', () => {
                                                    updateTimeHidden(inputGroup);
                                                });
                                            });
                                        }
                                    });
                                    
                                    updateConsultationStatus(idx);
                                }
                            }
                        }, 50 * generatedCount);
                        
                        generatedCount++;
                    });
                }
            });
            
            setTimeout(() => {
                runScheduleValidation();
                alert(`Successfully generated ${generatedCount} consultation hour(s) for vacant time slots.`);
            }, 100 * generatedCount + 500);
            
        } else {
            alert('No vacant time slots found. The faculty may have a full schedule or no teaching loads assigned yet.');
        }
    } catch (error) {
        console.error('Error auto-generating consultation hours:', error);
        alert('Error generating consultation hours. Please try again.');
    }
}

// Select vacant time and fill consultation form
function selectVacantTime(day, start24, end24) {
    // Normalize time format (remove seconds if present, ensure HH:MM format)
    const normalizeTime = (time) => {
        if (!time) return '';
        // Remove seconds if present (HH:MM:SS -> HH:MM)
        return time.substring(0, 5);
    };
    
    start24 = normalizeTime(start24);
    end24 = normalizeTime(end24);
    
    // Find the last consultation hour row or add a new one
    const consultationRows = document.querySelectorAll('#consultationHours .border');
    let targetRow = null;
    
    if (consultationRows.length === 0) {
        addConsultationHour();
        setTimeout(() => {
            selectVacantTime(day, start24, end24);
        }, 100);
        return;
    }
    
    // Use the last row
    targetRow = consultationRows[consultationRows.length - 1];
    const idx = targetRow.dataset.index;
    
    if (!idx && idx !== 0) {
        console.error('Invalid consultation row index');
        return;
    }
    
    // Set day
    const daySelect = targetRow.querySelector(`select[name="consultation_hours[${idx}][day]"]`);
    if (daySelect) {
        daySelect.value = day;
    }
    
    // Set times
    const startDiv = targetRow.querySelector(`div[data-name="consultation_hours[${idx}][time_start]"]`);
    const endDiv = targetRow.querySelector(`div[data-name="consultation_hours[${idx}][time_end]"]`);
    
    if (startDiv && endDiv) {
        startDiv.innerHTML = createTimeInput12(`consultation_hours[${idx}][time_start]`, start24);
        endDiv.innerHTML = createTimeInput12(`consultation_hours[${idx}][time_end]`, end24);
        
        // Re-attach event listeners and immediately update hidden fields
        [startDiv, endDiv].forEach(div => {
            const inputGroup = div.querySelector('.input-group');
            if (inputGroup) {
                // Initialize hidden field immediately with the correct value
                const hiddenInput = inputGroup.querySelector('.time-hidden');
                if (hiddenInput) {
                    // Set the hidden value directly first
                    if (div === startDiv) {
                        hiddenInput.value = start24;
                    } else {
                        hiddenInput.value = end24;
                    }
                }
                // Then update from the 12-hour inputs to ensure consistency
                updateTimeHidden(inputGroup);
                
                inputGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                    input.addEventListener('change', () => {
                        updateTimeHidden(inputGroup);
                        updateConsultationStatus(idx);
                        runScheduleValidation();
                    });
                    input.addEventListener('input', () => {
                        updateTimeHidden(inputGroup);
                    });
                });
            }
        });
        
        updateConsultationStatus(idx);
    }
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('vacantTimesModal'));
    if (modal) {
        modal.hide();
    }
}

function checkRoomAvailability(roomSelect, counter) {
    const room = roomSelect.value;
    const row = roomSelect.closest('.border');
    if (!row) return;
    
    const daySelect = row.querySelector(`select[name="teaching_loads[${counter}][day]"]`);
    const timeStartHidden = row.querySelector(`input[name="teaching_loads[${counter}][time_start]"]`);
    const timeEndHidden = row.querySelector(`input[name="teaching_loads[${counter}][time_end]"]`);
    const conflictDiv = document.getElementById(`room-conflict-${counter}`);
    
    if (!room || !daySelect || !timeStartHidden || !timeEndHidden) {
        return;
    }
    
    const day = daySelect.value;
    const timeStart = timeStartHidden.value;
    const timeEnd = timeEndHidden.value;
    
    if (!day || !timeStart || !timeEnd) {
        conflictDiv.style.display = 'none';
        roomSelect.classList.remove('is-invalid');
        delete roomConflicts[counter];
        return;
    }
    
    const semester = document.getElementById('semester').value;
    const schoolYear = document.getElementById('school_year').value;
    
    if (!semester || !schoolYear) {
        return;
    }
    
    fetch('check-room-conflict.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            room: room,
            day: day,
            time_start: timeStart,
            time_end: timeEnd,
            semester: semester,
            school_year: schoolYear
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.has_conflict) {
            roomSelect.classList.add('is-invalid');
            conflictDiv.style.display = 'block';
            conflictDiv.innerHTML = `⚠️ Time conflict with ${data.conflicting_course} (${data.conflicting_faculty})`;
            
            roomConflicts[counter] = {
                room: room,
                day: day,
                time: `${timeStart} - ${timeEnd}`,
                conflictingCourse: data.conflicting_course,
                conflictingFaculty: data.conflicting_faculty
            };
        } else {
            roomSelect.classList.remove('is-invalid');
            conflictDiv.style.display = 'none';
            delete roomConflicts[counter];
        }
        
        updateConflictWarning();
    })
    .catch(error => {
        console.error('Error checking room availability:', error);
    });
}

function updateConflictWarning() {
    const conflictWarning = document.getElementById('conflictWarning');
    const submitBtn = document.getElementById('submitWorkloadBtn');
    
    if (Object.keys(roomConflicts).length > 0) {
        conflictWarning.classList.remove('d-none');
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-danger');
        submitBtn.innerHTML = '<i class="bi bi-x-circle"></i> Cannot Save - Conflicts Exist';
    } else {
        conflictWarning.classList.add('d-none');
        submitBtn.classList.remove('btn-danger');
        submitBtn.classList.add('btn-success');
        submitBtn.innerHTML = '<i class="bi bi-save"></i> Save Workload';
    }
}

async function loadCoursesAndSections() {
    try {
        const response = await fetch('get-courses-sections.php');
        const data = await response.json();
        
        coursesData = data.courses || [];
        sectionsData = data.sections || [];
        courseTitlesData = data.course_titles || {};
    } catch (error) {
        console.error('Error loading courses and sections:', error);
        coursesData = [];
        sectionsData = [];
        courseTitlesData = {};
    }
}

function updateCourseTitle(selectElement, counter) {
    const courseCode = selectElement.value;
    const courseTitleInput = document.getElementById(`course_title_${counter}`);
    const courseCodeManual = document.getElementById(`course_code_manual_${counter}`);
    
    if (courseCode === 'OTHER') {
        courseCodeManual.classList.remove('d-none');
        courseTitleInput.value = '';
        courseTitleInput.readOnly = false;
        courseTitleInput.placeholder = 'Enter course title';
    } else if (courseCode && courseTitlesData[courseCode]) {
        courseCodeManual.classList.add('d-none');
        courseTitleInput.value = courseTitlesData[courseCode];
        courseTitleInput.readOnly = false;
    } else {
        courseCodeManual.classList.add('d-none');
        courseTitleInput.value = '';
        courseTitleInput.readOnly = false;
    }
}

function viewRoomSchedule(counter) {
    const roomSelect = document.getElementById(`room_select_${counter}`);
    const room = roomSelect ? roomSelect.value : '';
    
    const modal = new bootstrap.Modal(document.getElementById('roomScheduleModal'));
    modal.show();
    
    if (room) {
        document.getElementById('viewRoomSelect').value = room;
        loadRoomScheduleDetails();
    }
}

async function loadRoomScheduleDetails() {
    const room = document.getElementById('viewRoomSelect').value;
    const content = document.getElementById('roomScheduleContent');
    
    if (!room) {
        content.innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar3" style="font-size: 3rem;"></i>
                <p class="mt-2">Select a room to view its schedule</p>
            </div>
        `;
        return;
    }
    
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading schedule for ${room}...</p>
        </div>
    `;
    
    const semester = document.getElementById('semester').value || 'First Semester';
    const schoolYear = document.getElementById('school_year').value || 'AY 2025-2026';
    
    try {
        const response = await fetch('get-room-schedule.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                room: room,
                semester: semester,
                school_year: schoolYear
            })
        });
        
        const data = await response.json();
        
        if (data.schedules && data.schedules.length > 0) {
            let scheduleHTML = `
                <div class="alert alert-info">
                    <strong>${room}</strong> - ${semester}, ${schoolYear}
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Faculty</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            data.schedules.forEach(schedule => {
                scheduleHTML += `
                    <tr>
                        <td><span class="badge bg-primary">${schedule.day}</span></td>
                        <td><strong>${schedule.time_display}</strong></td>
                        <td>
                            <strong>${schedule.course_code}</strong><br>
                            <small class="text-muted">${schedule.course_title}</small>
                        </td>
                        <td>${schedule.section}</td>
                        <td>${schedule.faculty_name}</td>
                    </tr>
                `;
            });
            
            scheduleHTML += `
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-success mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Total:</strong> ${data.schedules.length} schedule(s) found
                </div>
            `;
            
            content.innerHTML = scheduleHTML;
        } else {
            content.innerHTML = `
                <div class="text-center py-5">
                    <i class="bi bi-calendar-check text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Room is Available!</h5>
                    <p class="text-muted">No schedules found for <strong>${room}</strong></p>
                    <p class="text-muted">${semester}, ${schoolYear}</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading room schedule:', error);
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                Error loading schedule. Please try again.
            </div>
        `;
    }
}

function addConflictCheckListeners(counter) {
    const row = document.querySelector(`#teachingLoads .border[data-index="${counter}"]`);
    if (!row) return;
    
    const daySelect = row.querySelector(`select[name="teaching_loads[${counter}][day]"]`);
    const timeStartHidden = row.querySelector(`input[name="teaching_loads[${counter}][time_start]"]`);
    const timeEndHidden = row.querySelector(`input[name="teaching_loads[${counter}][time_end]"]`);
    const roomSelect = row.querySelector(`select[name="teaching_loads[${counter}][room]"]`);
    
    if (daySelect && roomSelect) {
        daySelect.addEventListener('change', () => {
            if (timeStartHidden && timeStartHidden.value && timeEndHidden && timeEndHidden.value) {
                checkRoomAvailability(roomSelect, counter);
            }
            refreshConsultationStatuses();
            runScheduleValidation();
        });
        
        if (roomSelect) {
            roomSelect.addEventListener('change', () => {
                if (timeStartHidden && timeStartHidden.value && timeEndHidden && timeEndHidden.value) {
                    checkRoomAvailability(roomSelect, counter);
                }
            });
        }
    }
}

const EDIT_MODE = <?php echo $edit_mode ? 'true' : 'false'; ?>;
const EDIT_DATA = {
    teaching_loads: <?php echo json_encode($edit_teaching_loads); ?>,
    consultation_hours: <?php echo json_encode($edit_consultation_hours); ?>,
    functions: <?php echo json_encode($edit_functions); ?>
};

document.addEventListener('DOMContentLoaded', async function() {
    await loadCoursesAndSections();
    
    if (EDIT_MODE && EDIT_DATA.teaching_loads.length > 0) {
        EDIT_DATA.teaching_loads.forEach((load, index) => {
            addTeachingLoad();
            setTimeout(() => {
                const form = document.querySelector(`select[name="teaching_loads[${index}][day]"]`)?.closest('.border');
                if (form) {
                    form.querySelector(`select[name="teaching_loads[${index}][course_code]"]`).value = load.course_code;
                    form.querySelector(`input[name="teaching_loads[${index}][course_title]"]`).value = load.course_title;
                    form.querySelector(`select[name="teaching_loads[${index}][section]"]`).value = load.section;
                    form.querySelector(`select[name="teaching_loads[${index}][room]"]`).value = load.room;
                    form.querySelector(`select[name="teaching_loads[${index}][day]"]`).value = load.day;
                    
                    // Set 12-hour time inputs
                    const startTimeDiv = form.querySelector(`div[data-name="teaching_loads[${index}][time_start]"]`);
                    const endTimeDiv = form.querySelector(`div[data-name="teaching_loads[${index}][time_end]"]`);
                    if (startTimeDiv) {
                        startTimeDiv.innerHTML = createTimeInput12(`teaching_loads[${index}][time_start]`, load.time_start);
                        const startGroup = startTimeDiv.querySelector('.input-group');
                        if (startGroup) {
                            // Initialize hidden field immediately
                            updateTimeHidden(startGroup);
                            startGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                                input.addEventListener('change', () => {
                                    updateTimeHidden(startGroup);
                                    const roomSelect = form.querySelector(`select[name="teaching_loads[${index}][room]"]`);
                                    if (roomSelect) checkRoomAvailability(roomSelect, index);
                                    refreshConsultationStatuses();
                                    runScheduleValidation();
                                });
                                input.addEventListener('input', () => {
                                    updateTimeHidden(startGroup);
                                });
                            });
                        }
                    }
                    if (endTimeDiv) {
                        endTimeDiv.innerHTML = createTimeInput12(`teaching_loads[${index}][time_end]`, load.time_end);
                        const endGroup = endTimeDiv.querySelector('.input-group');
                        if (endGroup) {
                            // Initialize hidden field immediately
                            updateTimeHidden(endGroup);
                            endGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                                input.addEventListener('change', () => {
                                    updateTimeHidden(endGroup);
                                    const roomSelect = form.querySelector(`select[name="teaching_loads[${index}][room]"]`);
                                    if (roomSelect) checkRoomAvailability(roomSelect, index);
                                    refreshConsultationStatuses();
                                    runScheduleValidation();
                                });
                                input.addEventListener('input', () => {
                                    updateTimeHidden(endGroup);
                                });
                            });
                        }
                    }
                    
                    // Set units (check for separate lecture/lab units)
                    if (load.lecture_units !== undefined) {
                        const lecInput = form.querySelector(`input[name="teaching_loads[${index}][lecture_units]"]`);
                        if (lecInput) lecInput.value = load.lecture_units || 0;
                    }
                    if (load.lab_units !== undefined) {
                        const labInput = form.querySelector(`input[name="teaching_loads[${index}][lab_units]"]`);
                        if (labInput) labInput.value = load.lab_units || 0;
                    } else if (load.units !== undefined) {
                        // Fallback: split units based on class_type
                        if (load.class_type === 'Lec') {
                            const lecInput = form.querySelector(`input[name="teaching_loads[${index}][lecture_units]"]`);
                            if (lecInput) lecInput.value = load.units || 0;
                        } else {
                            const labInput = form.querySelector(`input[name="teaching_loads[${index}][lab_units]"]`);
                            if (labInput) labInput.value = load.units || 0;
                        }
                    }
                    
                    form.querySelector(`input[name="teaching_loads[${index}][students]"]`).value = load.students;
                    form.querySelector(`select[name="teaching_loads[${index}][class_type]"]`).value = load.class_type;
                }
            }, 100);
        });
    } else {
        addTeachingLoad();
    }
    
    if (EDIT_MODE && EDIT_DATA.consultation_hours.length > 0) {
        EDIT_DATA.consultation_hours.forEach((consultation, index) => {
            addConsultationHour();
            setTimeout(() => {
                const form = document.querySelector(`select[name="consultation_hours[${index}][day]"]`)?.closest('.border');
                if (form) {
                    form.querySelector(`select[name="consultation_hours[${index}][day]"]`).value = consultation.day;
                    
                    // Set 12-hour time inputs
                    const startTimeDiv = form.querySelector(`div[data-name="consultation_hours[${index}][time_start]"]`);
                    const endTimeDiv = form.querySelector(`div[data-name="consultation_hours[${index}][time_end]"]`);
                    if (startTimeDiv) {
                        startTimeDiv.innerHTML = createTimeInput12(`consultation_hours[${index}][time_start]`, consultation.time_start);
                        const startGroup = startTimeDiv.querySelector('.input-group');
                        if (startGroup) {
                            // Initialize hidden field immediately
                            updateTimeHidden(startGroup);
                            startGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                                input.addEventListener('change', () => {
                                    updateTimeHidden(startGroup);
                                    updateConsultationStatus(index);
                                    runScheduleValidation();
                                });
                                input.addEventListener('input', () => {
                                    updateTimeHidden(startGroup);
                                });
                            });
                        }
                    }
                    if (endTimeDiv) {
                        endTimeDiv.innerHTML = createTimeInput12(`consultation_hours[${index}][time_end]`, consultation.time_end);
                        const endGroup = endTimeDiv.querySelector('.input-group');
                        if (endGroup) {
                            // Initialize hidden field immediately
                            updateTimeHidden(endGroup);
                            endGroup.querySelectorAll('.time-hour, .time-minute, .time-period').forEach(input => {
                                input.addEventListener('change', () => {
                                    updateTimeHidden(endGroup);
                                    updateConsultationStatus(index);
                                    runScheduleValidation();
                                });
                                input.addEventListener('input', () => {
                                    updateTimeHidden(endGroup);
                                });
                            });
                        }
                    }
                    
                    form.querySelector(`input[name="consultation_hours[${index}][room]"]`).value = consultation.room || '';
                    updateConsultationStatus(index);
                }
            }, 100);
        });
    } else {
        addConsultationHour();
    }
    
    if (EDIT_MODE && EDIT_DATA.functions.length > 0) {
        EDIT_DATA.functions.forEach((func, index) => {
            addFunction();
            setTimeout(() => {
                const form = document.querySelector(`select[name="functions[${index}][type]"]`)?.closest('.border');
                if (form) {
                    form.querySelector(`select[name="functions[${index}][type]"]`).value = func.type;
                    form.querySelector(`input[name="functions[${index}][description]"]`).value = func.description;
                    form.querySelector(`input[name="functions[${index}][hours]"]`).value = func.hours;
                }
            }, 100);
        });
    } else {
        addFunction();
    }
    
    refreshConsultationStatuses();
    runScheduleValidation();
    
    const workloadForm = document.getElementById('workloadForm');
    if (workloadForm) {
        workloadForm.addEventListener('submit', function(e) {
            // Update all time hidden fields before validation and submission
            // This ensures all time values are synced from 12-hour inputs to hidden 24-hour fields
            updateAllTimeHiddenFields();
            
            // Double-check: ensure all consultation hours have valid time values
            document.querySelectorAll('#consultationHours .border').forEach(row => {
                const idx = row.dataset.index;
                if (idx !== undefined) {
                    const startHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_start]"]`);
                    const endHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_end]"]`);
                    const startGroup = startHidden?.closest('.time-input-12')?.querySelector('.input-group');
                    const endGroup = endHidden?.closest('.time-input-12')?.querySelector('.input-group');
                    
                    if (startGroup && startHidden && !startHidden.value) {
                        updateTimeHidden(startGroup);
                    }
                    if (endGroup && endHidden && !endHidden.value) {
                        updateTimeHidden(endGroup);
                    }
                }
            });
            
            // Validate all times are within 7:00 AM - 7:00 PM range
            let timeValidationErrors = [];
            
            // Helper function to convert time string (HH:MM) to minutes for comparison
            function timeToMinutes(timeStr) {
                if (!timeStr) return 0;
                const parts = timeStr.split(':');
                if (parts.length < 2) return 0;
                return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
            }
            
            // Check teaching loads
            document.querySelectorAll('#teachingLoads .border').forEach(row => {
                const idx = row.dataset.index;
                if (idx !== undefined) {
                    const startHidden = row.querySelector(`input[name="teaching_loads[${idx}][time_start]"]`);
                    const endHidden = row.querySelector(`input[name="teaching_loads[${idx}][time_end]"]`);
                    const courseCode = row.querySelector(`select[name="teaching_loads[${idx}][course_code]"]`)?.value || 
                                       row.querySelector(`input[name="teaching_loads[${idx}][course_title]"]`)?.value || 
                                       `Subject ${parseInt(idx) + 1}`;
                    
                    if (startHidden && endHidden && startHidden.value && endHidden.value) {
                        const startTime = timeToMinutes(startHidden.value);
                        const endTime = timeToMinutes(endHidden.value);
                        const minTime = timeToMinutes('07:00');
                        const maxTime = timeToMinutes('19:00');
                        
                        if (startTime < minTime || endTime > maxTime) {
                            timeValidationErrors.push(`Teaching load "${courseCode}" has time outside allowed range (7:00 AM - 7:00 PM)`);
                        }
                    }
                }
            });
            
            // Check consultation hours
            document.querySelectorAll('#consultationHours .border').forEach(row => {
                const idx = row.dataset.index;
                if (idx !== undefined) {
                    const startHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_start]"]`);
                    const endHidden = row.querySelector(`input[name="consultation_hours[${idx}][time_end]"]`);
                    const day = row.querySelector(`select[name="consultation_hours[${idx}][day]"]`)?.value || 'Unknown';
                    
                    if (startHidden && endHidden && startHidden.value && endHidden.value) {
                        const startTime = timeToMinutes(startHidden.value);
                        const endTime = timeToMinutes(endHidden.value);
                        const minTime = timeToMinutes('07:00');
                        const maxTime = timeToMinutes('19:00');
                        
                        if (startTime < minTime || endTime > maxTime) {
                            timeValidationErrors.push(`Consultation hour on ${day} has time outside allowed range (7:00 AM - 7:00 PM)`);
                        }
                    }
                }
            });
            
            if (timeValidationErrors.length > 0) {
                e.preventDefault();
                alert('Cannot save workload. Please fix the following time validation errors:\n\n' + timeValidationErrors.join('\n'));
                
                // Scroll to first invalid time input
                const firstInvalidTime = document.querySelector('.time-input-12 .input-group.is-invalid');
                if (firstInvalidTime) {
                    firstInvalidTime.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            const scheduleConflicts = runScheduleValidation();
            const hasRoomConflicts = Object.keys(roomConflicts).length > 0;
            
            if (hasRoomConflicts) {
                e.preventDefault();
                
                const conflictList = document.getElementById('conflictList');
                conflictList.innerHTML = '';
                
                Object.keys(roomConflicts).forEach(key => {
                    const conflict = roomConflicts[key];
                    const listItem = document.createElement('div');
                    listItem.className = 'list-group-item list-group-item-danger';
                    listItem.innerHTML = `
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">
                                <i class="bi bi-door-closed-fill text-danger"></i> 
                                Room: <strong>${conflict.room}</strong>
                            </h6>
                            <small>${conflict.day} ${conflict.time}</small>
                        </div>
                        <p class="mb-1 small">
                            <strong>Conflicts with:</strong> ${conflict.conflictingCourse}
                        </p>
                        <small class="text-muted">Faculty: ${conflict.conflictingFaculty}</small>
                    `;
                    conflictList.appendChild(listItem);
                });
                
                const conflictModal = new bootstrap.Modal(document.getElementById('conflictModal'));
                conflictModal.show();
                
                const firstInvalidRoom = document.querySelector('.room-select.is-invalid');
                if (firstInvalidRoom) {
                    firstInvalidRoom.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidRoom.focus();
                }
            }
            
            if (scheduleConflicts.length > 0 || hasRoomConflicts) {
                e.preventDefault();
                const scheduleWarning = document.getElementById('scheduleWarning');
                if (scheduleWarning) {
                    scheduleWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            return true;
        });
    }
});
</script>

<style>
/* Improve time input visibility and alignment */
.time-input-12 .input-group {
    display: flex;
    align-items: stretch;
}

.time-input-12 .form-control.time-hour,
.time-input-12 .form-control.time-minute {
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    padding: 0.375rem 0.5rem;
}

.time-input-12 .form-select.time-period {
    font-size: 14px;
    font-weight: 500;
    padding: 0.375rem 0.5rem;
}

.time-input-12 .input-group-text {
    font-weight: 600;
    padding: 0.375rem 0.5rem;
    border-left: 0;
    border-right: 0;
}

.time-input-12 .input-group .form-control:first-child {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.time-input-12 .input-group .form-control:not(:first-child):not(:last-child) {
    border-radius: 0;
}

.time-input-12 .input-group .form-select:last-child {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

/* Ensure time inputs are visible on smaller screens */
@media (max-width: 768px) {
    .time-input-12 .input-group {
        flex-wrap: nowrap;
    }
    
    .time-input-12 .form-control.time-hour,
    .time-input-12 .form-control.time-minute {
        min-width: 60px;
    }
    
    .time-input-12 .form-select.time-period {
        min-width: 80px;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 