<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireFaculty();

$page_title = 'My Workload';
$faculty_id = $_SESSION['user_id'];
$workload_id = $_GET['id'] ?? null;

// Get workload (specific or most recent)
if ($workload_id) {
    $workload_query = "
        SELECT w.*, u.name as faculty_name, u.faculty_rank, u.eligibility, 
               u.bachelor_degree, u.master_degree, u.doctorate_degree, 
               u.scholarship, u.length_of_service
        FROM workloads w 
        JOIN users u ON w.faculty_id = u.id 
        WHERE w.id = ? AND w.faculty_id = ?
    ";
    $stmt = $conn->prepare($workload_query);
    $stmt->bind_param("ii", $workload_id, $faculty_id);
} else {
    $workload_query = "
        SELECT w.*, u.name as faculty_name, u.faculty_rank, u.eligibility, 
               u.bachelor_degree, u.master_degree, u.doctorate_degree, 
               u.scholarship, u.length_of_service
        FROM workloads w 
        JOIN users u ON w.faculty_id = u.id 
        WHERE w.faculty_id = ? 
        ORDER BY w.created_at DESC 
        LIMIT 1
    ";
    $stmt = $conn->prepare($workload_query);
    $stmt->bind_param("i", $faculty_id);
}

$stmt->execute();
$workload = $stmt->get_result()->fetch_assoc();

if (!$workload) {
    header('Location: dashboard.php');
    exit();
}

$workload_id = $workload['id'];

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
$total_units = 0;
$total_students = 0;
$teaching_hours = 0;
$consultation_total_hours = 0;
$admin_hours = 0;
$research_hours = 0;

$teaching_loads->data_seek(0);
while ($load = $teaching_loads->fetch_assoc()) {
    $total_units += $load['units'];
    $total_students += $load['students'];
    $teaching_hours += $load['units'];
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

$total_contact_hours = $teaching_hours + $consultation_total_hours + $research_hours + $admin_hours;

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-calendar-check"></i> My Workload</h2>
    <div class="d-flex flex-column flex-sm-row gap-2">
        <a href="print-workload.php?id=<?php echo $workload_id; ?>" 
           class="btn btn-success" target="_blank">
            <i class="bi bi-printer"></i> <span class="d-none d-sm-inline">Print Workload</span>
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Back</span>
        </a>
    </div>
</div>

<!-- Workload Header -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="bi bi-person-badge"></i> 
            <?php echo htmlspecialchars($workload['faculty_name']); ?> - 
            <?php echo $workload['semester']; ?>, <?php echo $workload['school_year']; ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Faculty Rank:</strong> <?php echo htmlspecialchars($workload['faculty_rank'] ?? 'N/A'); ?></p>
                <p><strong>Eligibility:</strong> <?php echo htmlspecialchars($workload['eligibility'] ?? 'N/A'); ?></p>
                <p><strong>Length of Service:</strong> <?php echo htmlspecialchars($workload['length_of_service'] ?? 'N/A'); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Bachelor's Degree:</strong> <?php echo htmlspecialchars($workload['bachelor_degree'] ?? 'N/A'); ?></p>
                <p><strong>Master's Degree:</strong> <?php echo htmlspecialchars($workload['master_degree'] ?? 'N/A'); ?></p>
                <p><strong>Program:</strong> <?php echo htmlspecialchars($workload['program'] ?? 'N/A'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-book text-primary" style="font-size: 2rem;"></i>
                <h4 class="mt-2"><?php echo $teaching_loads->num_rows; ?></h4>
                <p class="text-muted">Teaching Subjects</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-people text-success" style="font-size: 2rem;"></i>
                <h4 class="mt-2"><?php echo $total_students; ?></h4>
                <p class="text-muted">Total Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                <h4 class="mt-2"><?php echo number_format($teaching_hours, 1); ?></h4>
                <p class="text-muted">Teaching Hours</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-briefcase text-warning" style="font-size: 2rem;"></i>
                <h4 class="mt-2"><?php echo $functions->num_rows; ?></h4>
                <p class="text-muted">Functions</p>
            </div>
        </div>
    </div>
</div>

<!-- Teaching Schedule -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Teaching Schedule</h5>
    </div>
    <div class="card-body">
        <?php if ($teaching_loads->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Section</th>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Units</th>
                            <th>Students</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $teaching_loads->data_seek(0);
                        while ($load = $teaching_loads->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($load['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($load['course_title']); ?></td>
                                <td><?php echo htmlspecialchars($load['section']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($load['day']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('g:i A', strtotime($load['time_start'])); ?> - 
                                    <?php echo date('g:i A', strtotime($load['time_end'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($load['room']); ?></td>
                                <td><?php echo $load['units']; ?></td>
                                <td><?php echo $load['students']; ?></td>
                                <td>
                                    <span class="badge <?php echo $load['class_type'] === 'Lec' ? 'bg-info' : 'bg-success'; ?>">
                                        <?php echo $load['class_type']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No teaching loads assigned</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Consultation Hours -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-clock"></i> Consultation Hours</h5>
    </div>
    <div class="card-body">
        <?php if ($consultation_hours->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                            <th>Room</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $consultation_hours->data_seek(0);
                        while ($consultation = $consultation_hours->fetch_assoc()): 
                            $duration = (strtotime($consultation['time_end']) - strtotime($consultation['time_start'])) / 3600;
                        ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($consultation['day']); ?></span>
                                </td>
                                <td>
                                    <?php echo date('g:i A', strtotime($consultation['time_start'])); ?> - 
                                    <?php echo date('g:i A', strtotime($consultation['time_end'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($consultation['room'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($duration, 1); ?> hour<?php echo $duration != 1 ? 's' : ''; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-clock-history" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No consultation hours assigned</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Functions -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-briefcase"></i> Functions (Research & Administrative)</h5>
    </div>
    <div class="card-body">
        <?php if ($functions->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
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
                                <td><?php echo number_format($function['hours'], 2); ?> hours</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-briefcase" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No functions assigned</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contact Hours Summary -->
<div class="card">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Contact Hours Summary</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <td><strong>Teaching Hours:</strong></td>
                        <td class="text-end"><?php echo number_format($teaching_hours, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Consultation Hours:</strong></td>
                        <td class="text-end"><?php echo number_format($consultation_total_hours, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Research Hours:</strong></td>
                        <td class="text-end"><?php echo number_format($research_hours, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Administrative Hours:</strong></td>
                        <td class="text-end"><?php echo number_format($admin_hours, 2); ?></td>
                    </tr>
                    <tr class="table-primary">
                        <td><strong>Total Contact Hours:</strong></td>
                        <td class="text-end"><strong><?php echo number_format($total_contact_hours, 2); ?></strong></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="text-center">
                    <h3 class="text-primary"><?php echo number_format($total_contact_hours, 1); ?></h3>
                    <p class="text-muted">Total Weekly Contact Hours</p>
                    
                    <?php 
                    $total_etl = $teaching_hours + $research_hours + $admin_hours;
                    $overload = max(0, $total_etl - 21);
                    ?>
                    
                    <div class="mt-3">
                        <div class="badge bg-success fs-6 p-2">
                            Total ETL: <?php echo number_format($total_etl, 2); ?>
                        </div>
                        <?php if ($overload > 0): ?>
                            <div class="badge bg-warning fs-6 p-2 ms-2">
                                Overload: <?php echo number_format($overload, 2); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 