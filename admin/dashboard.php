<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Admin Dashboard';

// Get statistics
$total_faculty_query = "SELECT COUNT(*) as count FROM users WHERE role = 'faculty'";
$total_faculty = $conn->query($total_faculty_query)->fetch_assoc()['count'];

$total_workloads_query = "SELECT COUNT(*) as count FROM workloads";
$total_workloads = $conn->query($total_workloads_query)->fetch_assoc()['count'];

$current_semester_query = "SELECT COUNT(*) as count FROM workloads WHERE semester = 'First Semester' AND school_year = 'AY 2025-2026'";
$current_semester_workloads = $conn->query($current_semester_query)->fetch_assoc()['count'];

// Get recent workloads
$recent_workloads_query = "
    SELECT w.*, u.name as faculty_name 
    FROM workloads w 
    JOIN users u ON w.faculty_id = u.id 
    ORDER BY w.created_at DESC 
    LIMIT 5
";
$recent_workloads = $conn->query($recent_workloads_query);

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-speedometer2"></i> Admin Dashboard</h2>
    <div class="text-muted">
        Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $total_faculty; ?></h4>
                        <p class="mb-0">Total Faculty</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-people" style="font-size: 2rem;"></i>
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
                        <h4><?php echo $total_workloads; ?></h4>
                        <p class="mb-0">Total Workloads</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text" style="font-size: 2rem;"></i>
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
                        <h4><?php echo $current_semester_workloads; ?></h4>
                        <p class="mb-0">Current Semester</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
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
                        <h4><?php echo date('Y'); ?></h4>
                        <p class="mb-0">Academic Year</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-mortarboard" style="font-size: 2rem;"></i>
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
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <a href="manage-faculty.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-people"></i>
                            <span class="d-block d-lg-inline">Manage Faculty</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <a href="encode-workload.php" class="btn btn-outline-success w-100">
                            <i class="bi bi-calendar-plus"></i>
                            <span class="d-block d-lg-inline">Encode Workload</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
                        <a href="view-workloads.php" class="btn btn-outline-info w-100">
                            <i class="bi bi-file-text"></i>
                            <span class="d-block d-lg-inline">View Workloads</span>
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-2">
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

<!-- Recent Workloads -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Workloads</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_workloads->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Faculty</th>
                                    <th>Semester</th>
                                    <th>School Year</th>
                                    <th>Program</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($workload = $recent_workloads->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($workload['faculty_name']); ?></td>
                                        <td><?php echo htmlspecialchars($workload['semester']); ?></td>
                                        <td><?php echo htmlspecialchars($workload['school_year']); ?></td>
                                        <td><?php echo htmlspecialchars($workload['program'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($workload['created_at'])); ?></td>
                                        <td>
                                            <a href="print-workload.php?id=<?php echo $workload['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <p class="text-muted mt-2">No workloads found. <a href="encode-workload.php">Create one now</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?> 