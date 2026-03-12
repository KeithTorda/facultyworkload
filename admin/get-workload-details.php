<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$workload_id = $_GET['id'] ?? 0;

// Get workload with faculty information
$workload_query = "
    SELECT w.*, u.name as faculty_name, u.faculty_rank, u.eligibility, 
           u.bachelor_degree, u.master_degree, u.doctorate_degree, 
           u.scholarship, u.length_of_service
    FROM workloads w 
    JOIN users u ON w.faculty_id = u.id 
    WHERE w.id = ?
";
$stmt = $conn->prepare($workload_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$workload = $stmt->get_result()->fetch_assoc();

if (!$workload) {
    echo '<div class="alert alert-danger">Workload not found</div>';
    exit;
}

// Get teaching loads
$teaching_loads_query = "SELECT * FROM teaching_loads WHERE workload_id = ? ORDER BY day, time_start";
$stmt = $conn->prepare($teaching_loads_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$teaching_loads = $stmt->get_result();

// Get consultation hours
$consultation_hours_query = "SELECT * FROM consultation_hours WHERE workload_id = ? ORDER BY day, time_start";
$stmt = $conn->prepare($consultation_hours_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$consultation_hours = $stmt->get_result();

// Get functions
$functions_query = "SELECT * FROM functions WHERE workload_id = ? ORDER BY type, description";
$stmt = $conn->prepare($functions_query);
$stmt->bind_param("i", $workload_id);
$stmt->execute();
$functions = $stmt->get_result();

// Calculate totals
$total_teaching_hours = 0;
$total_students = 0;
$consultation_total_hours = 0;
$admin_hours = 0;
$research_hours = 0;

$teaching_loads->data_seek(0);
while ($load = $teaching_loads->fetch_assoc()) {
    $total_teaching_hours += $load['units'];
    $total_students += $load['students'];
}

$consultation_hours->data_seek(0);
while ($consultation = $consultation_hours->fetch_assoc()) {
    $start = strtotime($consultation['time_start']);
    $end = strtotime($consultation['time_end']);
    $consultation_total_hours += ($end - $start) / 3600;
}

$functions->data_seek(0);
while ($function = $functions->fetch_assoc()) {
    if ($function['type'] === 'admin') {
        $admin_hours += $function['hours'];
    } else {
        $research_hours += $function['hours'];
    }
}

$total_contact_hours = $total_teaching_hours + $consultation_total_hours + $research_hours + $admin_hours;
?>

<div class="workload-details">
    <!-- Faculty Information -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">Faculty Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($workload['faculty_name']); ?></p>
                    <p><strong>Rank:</strong> <?php echo htmlspecialchars($workload['faculty_rank'] ?? 'N/A'); ?></p>
                    <p><strong>Eligibility:</strong> <?php echo htmlspecialchars($workload['eligibility'] ?? 'N/A'); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Semester:</strong> <?php echo htmlspecialchars($workload['semester']); ?></p>
                    <p><strong>School Year:</strong> <?php echo htmlspecialchars($workload['school_year']); ?></p>
                    <p><strong>Program:</strong> <?php echo htmlspecialchars($workload['program'] ?? 'N/A'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Teaching Loads -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">Teaching Schedule (<?php echo $teaching_loads->num_rows; ?> subjects)</h6>
        </div>
        <div class="card-body">
            <?php if ($teaching_loads->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Title</th>
                                <th>Section</th>
                                <th>Schedule</th>
                                <th>Room</th>
                                <th>Units</th>
                                <th>Students</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $teaching_loads->data_seek(0);
                            while ($load = $teaching_loads->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($load['course_code']); ?></td>
                                    <td><?php echo htmlspecialchars($load['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars($load['section']); ?></td>
                                    <td><?php echo $load['day'] . ' ' . date('H:i', strtotime($load['time_start'])) . '-' . date('H:i', strtotime($load['time_end'])); ?></td>
                                    <td><?php echo htmlspecialchars($load['room']); ?></td>
                                    <td><?php echo $load['units']; ?></td>
                                    <td><?php echo $load['students']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No teaching loads assigned</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Consultation Hours -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">Consultation Schedule</h6>
        </div>
        <div class="card-body">
            <?php if ($consultation_hours->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $consultation_hours->data_seek(0);
                            while ($consultation = $consultation_hours->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($consultation['day']); ?></td>
                                    <td><?php echo date('H:i', strtotime($consultation['time_start'])) . ' - ' . date('H:i', strtotime($consultation['time_end'])); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['room'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No consultation hours assigned</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Functions -->
    <div class="card mb-3">
        <div class="card-header">
            <h6 class="mb-0">Functions (Research & Administrative)</h6>
        </div>
        <div class="card-body">
            <?php if ($functions->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $functions->data_seek(0);
                            while ($function = $functions->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $function['type'] === 'admin' ? 'bg-primary' : 'bg-success'; ?>">
                                            <?php echo ucfirst($function['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($function['description']); ?></td>
                                    <td><?php echo number_format($function['hours'], 2); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No functions assigned</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Contact Hours Summary</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ul class="list-unstyled">
                        <li><strong>Teaching:</strong> <?php echo number_format($total_teaching_hours, 2); ?> hours</li>
                        <li><strong>Consultation:</strong> <?php echo number_format($consultation_total_hours, 2); ?> hours</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="list-unstyled">
                        <li><strong>Research:</strong> <?php echo number_format($research_hours, 2); ?> hours</li>
                        <li><strong>Administrative:</strong> <?php echo number_format($admin_hours, 2); ?> hours</li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h6><strong>Total Contact Hours:</strong> <?php echo number_format($total_contact_hours, 2); ?></h6>
                </div>
                <div class="col-md-6">
                    <h6><strong>Total Students:</strong> <?php echo $total_students; ?></h6>
                </div>
            </div>
        </div>
    </div>
</div> 