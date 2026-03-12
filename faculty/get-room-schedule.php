<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireLogin();

$room_name = $_GET['room'] ?? '';

if (empty($room_name)) {
    echo '<div class="alert alert-danger">Room name is required.</div>';
    exit;
}

// Get room details
$room_query = "SELECT * FROM rooms WHERE room_name = ?";
$stmt = $conn->prepare($room_query);
$stmt->bind_param("s", $room_name);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();

if (!$room) {
    echo '<div class="alert alert-danger">Room not found.</div>';
    exit;
}

// Get current semester schedule
$schedule_query = "
    SELECT 
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
    WHERE tl.room = ?
    AND w.semester = 'First Semester'
    AND w.school_year = 'AY 2025-2026'
    ORDER BY 
        CASE tl.day 
            WHEN 'M' THEN 1 
            WHEN 'T' THEN 2 
            WHEN 'W' THEN 3 
            WHEN 'TH' THEN 4 
            WHEN 'F' THEN 5 
            WHEN 'S' THEN 6 
            WHEN 'MWF' THEN 1
            WHEN 'TTH' THEN 2
            ELSE 7 
        END, 
        tl.time_start
";

$stmt = $conn->prepare($schedule_query);
$stmt->bind_param("s", $room_name);
$stmt->execute();
$schedules = $stmt->get_result();

?>

<div class="mb-3">
    <h5><i class="bi bi-door-open"></i> <?php echo htmlspecialchars($room_name); ?></h5>
    <p class="text-muted mb-1"><?php echo htmlspecialchars($room['building'] ?? 'N/A'); ?></p>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge <?php 
            echo $room['room_type'] === 'classroom' ? 'bg-primary' : 
                 ($room['room_type'] === 'laboratory' ? 'bg-success' : 
                 ($room['room_type'] === 'auditorium' ? 'bg-warning text-dark' : 'bg-secondary'));
        ?>">
            <?php echo ucfirst($room['room_type']); ?>
        </span>
        <?php if ($room['capacity'] > 0): ?>
            <span class="badge bg-info"><?php echo $room['capacity']; ?> seats</span>
        <?php endif; ?>
        <span class="badge bg-secondary"><?php echo ucfirst($room['status']); ?></span>
    </div>
    <?php if ($room['equipment']): ?>
        <p class="small text-muted mt-2"><strong>Equipment:</strong> <?php echo htmlspecialchars($room['equipment']); ?></p>
    <?php endif; ?>
</div>

<hr>

<h6><i class="bi bi-calendar-week"></i> Current Schedule (First Semester, AY 2025-2026)</h6>

<?php if ($schedules->num_rows > 0): ?>
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
                <?php while ($schedule = $schedules->fetch_assoc()): ?>
                    <tr>
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
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="text-center py-4">
        <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
        <p class="text-muted mt-2">No schedules found for this room.</p>
        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Room is currently available</span>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-1">Total Classes</h6>
                        <h4 class="mb-0"><?php echo $schedules->num_rows; ?></h4>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-book" style="font-size: 2rem; color: #666;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-1">Utilization</h6>
                        <?php
                        // Calculate basic utilization (assuming 8 hours per day, 6 days per week)
                        $total_possible_hours = 8 * 6; // 48 hours per week
                        $scheduled_hours = 0;
                        
                        $schedules->data_seek(0);
                        while ($schedule = $schedules->fetch_assoc()) {
                            $start = strtotime($schedule['time_start']);
                            $end = strtotime($schedule['time_end']);
                            $hours = ($end - $start) / 3600;
                            $scheduled_hours += $hours;
                        }
                        
                        $utilization = $total_possible_hours > 0 ? round(($scheduled_hours / $total_possible_hours) * 100) : 0;
                        ?>
                        <h4 class="mb-0"><?php echo $utilization; ?>%</h4>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-graph-up" style="font-size: 2rem; color: #666;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 