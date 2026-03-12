<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireFaculty();

$page_title = 'Room Availability';

// Get filter parameters
$selected_day = $_GET['day'] ?? '';
$selected_time = $_GET['time'] ?? '';
$selected_type = $_GET['type'] ?? '';

// Get all rooms
$rooms_query = "SELECT * FROM rooms WHERE status = 'active' ORDER BY building, room_name";
$rooms_result = $conn->query($rooms_query);

// Time slots for checking availability
$time_slots = [
    '07:00' => '7:00 AM',
    '08:00' => '8:00 AM',
    '09:00' => '9:00 AM',
    '10:00' => '10:00 AM',
    '11:00' => '11:00 AM',
    '12:00' => '12:00 PM',
    '13:00' => '1:00 PM',
    '14:00' => '2:00 PM',
    '15:00' => '3:00 PM',
    '16:00' => '4:00 PM',
    '17:00' => '5:00 PM',
    '18:00' => '6:00 PM'
];

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-door-open"></i> Room Availability</h2>
    <button class="btn btn-outline-primary" onclick="location.reload()">
        <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">Refresh</span>
    </button>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Rooms</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row">
            <div class="col-md-3 mb-3">
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
            
            <div class="col-md-3 mb-3">
                <label for="time" class="form-label">Time</label>
                <select class="form-control" name="time" id="time">
                    <option value="">All Times</option>
                    <?php foreach ($time_slots as $time_value => $time_label): ?>
                        <option value="<?php echo $time_value; ?>" <?php echo $selected_time === $time_value ? 'selected' : ''; ?>>
                            <?php echo $time_label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3 mb-3">
                <label for="type" class="form-label">Room Type</label>
                <select class="form-control" name="type" id="type">
                    <option value="">All Types</option>
                    <option value="classroom" <?php echo $selected_type === 'classroom' ? 'selected' : ''; ?>>Classroom</option>
                    <option value="laboratory" <?php echo $selected_type === 'laboratory' ? 'selected' : ''; ?>>Laboratory</option>
                    <option value="auditorium" <?php echo $selected_type === 'auditorium' ? 'selected' : ''; ?>>Auditorium</option>
                    <option value="conference" <?php echo $selected_type === 'conference' ? 'selected' : ''; ?>>Conference Room</option>
                </select>
            </div>
            
            <div class="col-md-3 mb-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Room Availability Grid -->
<div class="row">
    <?php
    $room_filters = [];
    if ($selected_type) {
        $room_filters[] = "room_type = '$selected_type'";
    }
    
    $room_where = $room_filters ? ' AND ' . implode(' AND ', $room_filters) : '';
    $filtered_rooms_query = "SELECT * FROM rooms WHERE status = 'active' $room_where ORDER BY building, room_name";
    $filtered_rooms = $conn->query($filtered_rooms_query);
    
    while ($room = $filtered_rooms->fetch_assoc()):
        // Check if room is available for the selected day and time
        $is_available = true;
        $current_schedule = '';
        
        if ($selected_day && $selected_time) {
            $end_time = date('H:i', strtotime($selected_time) + 3600); // Add 1 hour
            
            $conflict_query = "
                SELECT tl.course_code, tl.time_start, tl.time_end, u.name as faculty_name
                FROM teaching_loads tl
                JOIN workloads w ON tl.workload_id = w.id
                JOIN users u ON w.faculty_id = u.id
                WHERE tl.room = ? 
                AND tl.day = ?
                AND w.semester = 'First Semester'
                AND w.school_year = 'AY 2025-2026'
                AND ((? < tl.time_end) AND (? > tl.time_start))
            ";
            
            $stmt = $conn->prepare($conflict_query);
            $stmt->bind_param("ssss", $room['room_name'], $selected_day, $selected_time, $end_time);
            $stmt->execute();
            $conflict_result = $stmt->get_result();
            
            if ($conflict_result->num_rows > 0) {
                $is_available = false;
                $conflict = $conflict_result->fetch_assoc();
                $current_schedule = $conflict['course_code'] . ' (' . $conflict['faculty_name'] . ')';
            }
        }
    ?>
        <div class="col-sm-6 col-lg-4 col-xl-3 mb-3">
            <div class="card h-100 <?php echo !$is_available ? 'border-danger' : 'border-success'; ?>">
                <div class="card-header <?php echo !$is_available ? 'bg-danger text-white' : 'bg-success text-white'; ?>">
                    <h6 class="mb-0">
                        <i class="bi bi-door-open"></i> <?php echo htmlspecialchars($room['room_name']); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">Building:</small><br>
                        <strong><?php echo htmlspecialchars($room['building'] ?? 'N/A'); ?></strong>
                    </div>
                    
                    <div class="mb-2">
                        <small class="text-muted">Type:</small><br>
                        <span class="badge <?php 
                            echo $room['room_type'] === 'classroom' ? 'bg-primary' : 
                                 ($room['room_type'] === 'laboratory' ? 'bg-success' : 
                                 ($room['room_type'] === 'auditorium' ? 'bg-warning text-dark' : 'bg-secondary'));
                        ?>">
                            <?php echo ucfirst($room['room_type']); ?>
                        </span>
                    </div>
                    
                    <?php if ($room['capacity'] > 0): ?>
                        <div class="mb-2">
                            <small class="text-muted">Capacity:</small><br>
                            <strong><?php echo $room['capacity']; ?> seats</strong>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($room['equipment']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Equipment:</small><br>
                            <small><?php echo htmlspecialchars($room['equipment']); ?></small>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($selected_day && $selected_time): ?>
                        <hr>
                        <div class="text-center">
                            <?php if ($is_available): ?>
                                <span class="badge bg-success fs-6">
                                    <i class="bi bi-check-circle"></i> Available
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger fs-6">
                                    <i class="bi bi-x-circle"></i> Occupied
                                </span>
                                <br><small class="text-muted mt-1"><?php echo htmlspecialchars($current_schedule); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <button class="btn btn-sm btn-outline-info w-100" 
                            onclick="viewRoomSchedule('<?php echo htmlspecialchars($room['room_name']); ?>')">
                        <i class="bi bi-calendar-week"></i> View Schedule
                    </button>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<?php if ($filtered_rooms->num_rows === 0): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-door-closed" style="font-size: 4rem; color: #ccc;"></i>
            <h4 class="text-muted mt-3">No Rooms Found</h4>
            <p class="text-muted">No rooms match the selected criteria.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Room Schedule Modal -->
<div class="modal fade" id="roomScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Room Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="roomScheduleContent">
                <!-- Schedule content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Legend -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Legend</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><span class="badge bg-success me-2"><i class="bi bi-check-circle"></i> Available</span> Room is free for the selected time</p>
                <p><span class="badge bg-danger me-2"><i class="bi bi-x-circle"></i> Occupied</span> Room is currently scheduled</p>
            </div>
            <div class="col-md-6">
                <p><span class="badge bg-primary me-2">Classroom</span> Regular classroom</p>
                <p><span class="badge bg-success me-2">Laboratory</span> Computer/Science lab</p>
            </div>
        </div>
    </div>
</div>

<script>
function viewRoomSchedule(roomName) {
    const modalContent = document.getElementById('roomScheduleContent');
    modalContent.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    new bootstrap.Modal(document.getElementById('roomScheduleModal')).show();
    
    fetch(`get-room-schedule.php?room=${encodeURIComponent(roomName)}`)
        .then(response => response.text())
        .then(data => {
            modalContent.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div class="alert alert-danger">Error loading room schedule. Please try again.</div>';
        });
}

// Auto-refresh every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000); // 5 minutes
</script>

<?php include '../includes/footer.php'; ?> 