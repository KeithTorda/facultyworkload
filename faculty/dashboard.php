<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireFaculty();

$page_title = 'Faculty Dashboard';
$faculty_id = $_SESSION['user_id'];

// Get faculty workloads
$workloads_query = "SELECT * FROM workloads WHERE faculty_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($workloads_query);
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$workloads = $stmt->get_result();

// Get current semester workload (most recent)
$current_workload = $workloads->fetch_assoc();
$workloads->data_seek(0);

// Get statistics for current workload
$total_subjects = 0;
$total_students = 0;
$total_teaching_hours = 0;
$consultation_hours_count = 0;
$functions_count = 0;

if ($current_workload) {
    // Teaching loads
    $teaching_query = "SELECT COUNT(*) as count, SUM(units) as total_units, SUM(students) as total_students FROM teaching_loads WHERE workload_id = ?";
    $stmt = $conn->prepare($teaching_query);
    $stmt->bind_param("i", $current_workload['id']);
    $stmt->execute();
    $teaching_stats = $stmt->get_result()->fetch_assoc();
    
    $total_subjects = $teaching_stats['count'] ?? 0;
    $total_students = $teaching_stats['total_students'] ?? 0;
    $total_teaching_hours = $teaching_stats['total_units'] ?? 0;
    
    // Consultation hours
    $consultation_query = "SELECT COUNT(*) as count FROM consultation_hours WHERE workload_id = ?";
    $stmt = $conn->prepare($consultation_query);
    $stmt->bind_param("i", $current_workload['id']);
    $stmt->execute();
    $consultation_hours_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
    
    // Functions
    $functions_query = "SELECT COUNT(*) as count FROM functions WHERE workload_id = ?";
    $stmt = $conn->prepare($functions_query);
    $stmt->bind_param("i", $current_workload['id']);
    $stmt->execute();
    $functions_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
}

include '../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-speedometer2"></i> Faculty Dashboard</h2>
    <div class="text-muted">
        Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
    </div>
</div>

<?php if ($current_workload): ?>
    <!-- Current Workload Summary -->
    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle"></i> Current Workload</h5>
        <p class="mb-0">
            <strong><?php echo $current_workload['semester']; ?>, <?php echo $current_workload['school_year']; ?></strong>
            <?php if ($current_workload['program']): ?>
                - <?php echo $current_workload['program']; ?>
            <?php endif; ?>
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $total_subjects; ?></h4>
                            <p class="mb-0">Teaching Subjects</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-book" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $total_students; ?></h4>
                            <p class="mb-0">Total Students</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $total_teaching_hours; ?></h4>
                            <p class="mb-0">Teaching Hours</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo $functions_count; ?></h4>
                            <p class="mb-0">Functions</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-briefcase" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <a href="my-workload.php" class="btn btn-outline-primary w-100">
                                <i class="bi bi-calendar-check"></i>
                                <span class="d-block d-lg-inline">View My Workload</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="print-workload.php?id=<?php echo $current_workload['id']; ?>" 
                               class="btn btn-outline-success w-100" target="_blank">
                                <i class="bi bi-printer"></i>
                                <span class="d-block d-lg-inline">Print Workload</span>
                            </a>
                        </div>
                        <div class="col-md-4 mb-2">
                            <a href="../logout.php" class="btn btn-outline-danger w-100">
                                <i class="bi bi-box-arrow-right"></i>
                                <span class="d-block d-lg-inline">Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- No Workload Message -->
    <div class="text-center py-5">
        <i class="bi bi-calendar-x" style="font-size: 4rem; color: #ccc;"></i>
        <h4 class="text-muted mt-3">No Workload Assigned</h4>
        <p class="text-muted">Your workload has not been assigned yet. Please contact the administrator.</p>
        
        <div class="mt-4">
            <a href="../logout.php" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- All Workloads History -->
<?php if ($workloads->num_rows > 0): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Workload History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Semester</th>
                                    <th>School Year</th>
                                    <th>Program</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $workloads->data_seek(0);
                                while ($workload = $workloads->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($workload['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($workload['school_year']); ?></td>
                                        <td><?php echo htmlspecialchars($workload['program'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($workload['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                                <a href="my-workload.php?id=<?php echo $workload['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="print-workload.php?id=<?php echo $workload['id']; ?>" 
                                                   class="btn btn-outline-success" target="_blank" title="Print">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </div>
                                            <div class="btn-group d-none d-md-flex" role="group">
                                                <a href="my-workload.php?id=<?php echo $workload['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="print-workload.php?id=<?php echo $workload['id']; ?>" 
                                                   class="btn btn-sm btn-outline-success" target="_blank">
                                                    <i class="bi bi-printer"></i> Print
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?> 