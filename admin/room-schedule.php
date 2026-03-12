<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Room Schedule';

// Get filter parameters
$selected_room = $_GET['room'] ?? '';
$selected_day = $_GET['day'] ?? '';
$selected_semester = $_GET['semester'] ?? 'First Semester';
$selected_year = $_GET['year'] ?? 'AY 2025-2026';

// Get all rooms for filter
$rooms_query = "SELECT * FROM rooms WHERE status = 'active' ORDER BY building, room_name";
$rooms_result = $conn->query($rooms_query);

// Build schedule query with filters
$where_conditions = ["w.semester = ?", "w.school_year = ?"];
$params = [$selected_semester, $selected_year];
$param_types = "ss";

if ($selected_room) {
    $where_conditions[] = "tl.room = ?";
    $params[] = $selected_room;
    $param_types .= "s";
}

if ($selected_day) {
    $where_conditions[] = "tl.day = ?";
    $params[] = $selected_day;
    $param_types .= "s";
}

$schedule_query = "
    SELECT 
        tl.room,
        tl.day,
        tl.time_start,
        tl.time_end,
        tl.course_code,
        tl.course_title,
        tl.section,
        u.name as faculty_name,
        w.semester,
        w.school_year
    FROM teaching_loads tl
    JOIN workloads w ON tl.workload_id = w.id
    JOIN users u ON w.faculty_id = u.id
    WHERE " . implode(" AND ", $where_conditions) . "
    ORDER BY tl.room, tl.day, tl.time_start
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$schedule_result = $stmt->get_result();

// Function to check for time conflicts
function checkTimeConflict($start1, $end1, $start2, $end2) {
    return (strtotime($start1) < strtotime($end2)) && (strtotime($end1) > strtotime($start2));
}

// Group schedules by room and day
$room_schedules = [];
while ($schedule = $schedule_result->fetch_assoc()) {
    $room_schedules[$schedule['room']][$schedule['day']][] = $schedule;
}

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-calendar-week"></i> Room Schedule</h2>
    <div class="d-flex gap-2">
        <a href="manage-rooms.php" class="btn btn-outline-primary">
            <i class="bi bi-door-open"></i> <span class="d-none d-sm-inline">Manage Rooms</span>
        </a>
        <button class="btn btn-success" onclick="window.print()">
            <i class="bi bi-printer"></i> <span class="d-none d-sm-inline">Print</span>
        </button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4 no-print">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Schedule</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-3 mb-3">
                <label for="room" class="form-label">Room</label>
                <select class="form-control" name="room" id="room">
                    <option value="">All Rooms</option>
                    <?php 
                    $rooms_result->data_seek(0);
                    while ($room = $rooms_result->fetch_assoc()): 
                    ?>
                        <option value="<?php echo htmlspecialchars($room['room_name']); ?>" 
                                <?php echo $selected_room === $room['room_name'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($room['room_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="col-md-2 mb-3">
                <label for="day" class="form-label">Day</label>
                <select class="form-control" name="day" id="day">
                    <option value="">All Days</option>
                    <option value="M" <?php echo $selected_day === 'M' ? 'selected' : ''; ?>>Monday</option>
                    <option value="T" <?php echo $selected_day === 'T' ? 'selected' : ''; ?>>Tuesday</option>
                    <option value="W" <?php echo $selected_day === 'W' ? 'selected' : ''; ?>>Wednesday</option>
                    <option value="TH" <?php echo $selected_day === 'TH' ? 'selected' : ''; ?>>Thursday</option>
                    <option value="F" <?php echo $selected_day === 'F' ? 'selected' : ''; ?>>Friday</option>
                    <option value="S" <?php echo $selected_day === 'S' ? 'selected' : ''; ?>>Saturday</option>
                    <option value="MWF" <?php echo $selected_day === 'MWF' ? 'selected' : ''; ?>>MWF</option>
                    <option value="TTH" <?php echo $selected_day === 'TTH' ? 'selected' : ''; ?>>TTH</option>
                </select>
            </div>
            
            <div class="col-md-2 mb-3">
                <label for="semester" class="form-label">Semester</label>
                <select class="form-control" name="semester" id="semester">
                    <option value="First Semester" <?php echo $selected_semester === 'First Semester' ? 'selected' : ''; ?>>First Semester</option>
                    <option value="Second Semester" <?php echo $selected_semester === 'Second Semester' ? 'selected' : ''; ?>>Second Semester</option>
                    <option value="Summer" <?php echo $selected_semester === 'Summer' ? 'selected' : ''; ?>>Summer</option>
                </select>
            </div>
            
            <div class="col-md-3 mb-3">
                <label for="year" class="form-label">School Year</label>
                <input type="text" class="form-control" name="year" id="year" 
                       value="<?php echo htmlspecialchars($selected_year); ?>" placeholder="AY 2025-2026">
            </div>
            
            <div class="col-md-2 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Schedule Display -->
<?php if (empty($room_schedules)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-calendar-x" style="font-size: 4rem; color: #ccc;"></i>
            <h4 class="text-muted mt-3">No Schedules Found</h4>
            <p class="text-muted">No classes scheduled for the selected criteria.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($room_schedules as $room_name => $days): ?>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($room_name); ?>
                    <?php
                    // Get room details
                    $room_details_query = "SELECT * FROM rooms WHERE room_name = ?";
                    $stmt = $conn->prepare($room_details_query);
                    $stmt->bind_param("s", $room_name);
                    $stmt->execute();
                    $room_details = $stmt->get_result()->fetch_assoc();
                    
                    if ($room_details) {
                        echo " - " . htmlspecialchars($room_details['building'] ?? '');
                        if ($room_details['capacity'] > 0) {
                            echo " (Capacity: " . $room_details['capacity'] . ")";
                        }
                    }
                    ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Course</th>
                                <th>Section</th>
                                <th>Faculty</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($days as $day => $schedules):
                                // Check for conflicts within the same day
                                $conflicts = [];
                                for ($i = 0; $i < count($schedules); $i++) {
                                    for ($j = $i + 1; $j < count($schedules); $j++) {
                                        if (checkTimeConflict(
                                            $schedules[$i]['time_start'], 
                                            $schedules[$i]['time_end'],
                                            $schedules[$j]['time_start'], 
                                            $schedules[$j]['time_end']
                                        )) {
                                            $conflicts[] = $i;
                                            $conflicts[] = $j;
                                        }
                                    }
                                }
                                
                                foreach ($schedules as $index => $schedule):
                                    $has_conflict = in_array($index, $conflicts);
                            ?>
                                <tr class="<?php echo $has_conflict ? 'table-danger' : ''; ?>">
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($schedule['day']); ?></span>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo date('g:i A', strtotime($schedule['time_start'])); ?> - 
                                            <?php echo date('g:i A', strtotime($schedule['time_end'])); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($schedule['course_code']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($schedule['course_title']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['section']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['faculty_name']); ?></td>
                                    <td>
                                        <?php if ($has_conflict): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-triangle"></i> Conflict
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle"></i> OK
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Conflict Summary -->
<?php
// Count total conflicts
$total_conflicts = 0;
foreach ($room_schedules as $room_name => $days) {
    foreach ($days as $day => $schedules) {
        for ($i = 0; $i < count($schedules); $i++) {
            for ($j = $i + 1; $j < count($schedules); $j++) {
                if (checkTimeConflict(
                    $schedules[$i]['time_start'], 
                    $schedules[$i]['time_end'],
                    $schedules[$j]['time_start'], 
                    $schedules[$j]['time_end']
                )) {
                    $total_conflicts++;
                }
            }
        }
    }
}
?>

<?php if ($total_conflicts > 0): ?>
    <div class="alert alert-danger no-print" role="alert">
        <h5 class="alert-heading">
            <i class="bi bi-exclamation-triangle-fill"></i> Schedule Conflicts Detected!
        </h5>
        <p class="mb-0">
            Found <strong><?php echo $total_conflicts; ?></strong> time conflict(s) in the current schedule. 
            Please review and resolve these conflicts to avoid classroom scheduling issues.
        </p>
    </div>
<?php else: ?>
    <div class="alert alert-success no-print" role="alert">
        <h5 class="alert-heading">
            <i class="bi bi-check-circle-fill"></i> No Conflicts Found
        </h5>
        <p class="mb-0">All room schedules are properly arranged without time conflicts.</p>
    </div>
<?php endif; ?>

<!-- Legend -->
<div class="card no-print">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Legend</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> OK</span> No time conflicts</p>
                <p><span class="badge bg-danger me-2"><i class="bi bi-exclamation-triangle"></i> Conflict</span> Overlapping schedules</p>
            </div>
            <div class="col-md-6">
                <p><span class="badge bg-secondary me-2">Day</span> Class days (M/T/W/TH/F/S/MWF/TTH)</p>
                <p><span class="table-danger px-2">Row highlighting</span> Indicates conflicting schedules</p>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    .table {
        font-size: 11px;
    }
    
    .badge {
        border: 1px solid #000;
    }
}
</style>

<?php include '../includes/footer.php'; ?> 