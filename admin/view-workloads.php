<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'View Workloads';

// Get all workloads with faculty information
$workloads_query = "
    SELECT w.*, u.name as faculty_name, u.email as faculty_email 
    FROM workloads w 
    JOIN users u ON w.faculty_id = u.id 
    ORDER BY w.created_at DESC
";
$workloads_result = $conn->query($workloads_query);

include '../includes/header.php';

// Handle success/error messages
$message = '';
$error = '';
if (isset($_GET['message'])) {
    if ($_GET['message'] === 'deleted') {
        $message = 'Workload deleted successfully.';
    } elseif ($_GET['message'] === 'updated') {
        $message = 'Workload updated successfully!';
    }
}
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'delete_failed') {
        $error = 'Error deleting workload. Please try again.';
    } elseif ($_GET['error'] === 'not_found') {
        $error = 'Workload not found.';
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-file-text"></i> View Workloads</h2>
    <a href="encode-workload.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add Workload</span>
    </a>
</div>

<!-- Workloads List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> Faculty Workloads</h5>
    </div>
    <div class="card-body">
        <?php if ($workloads_result->num_rows > 0): ?>
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
                        <?php while ($workload = $workloads_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($workload['faculty_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($workload['faculty_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($workload['semester']); ?></td>
                                <td><?php echo htmlspecialchars($workload['school_year']); ?></td>
                                <td><?php echo htmlspecialchars($workload['program'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y', strtotime($workload['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                        <a href="encode-workload.php?edit=<?php echo $workload['id']; ?>" 
                                           class="btn btn-outline-success" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="print-workload.php?id=<?php echo $workload['id']; ?>" 
                                           class="btn btn-outline-primary" target="_blank" title="Print">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-info" 
                                                onclick="viewWorkloadDetails(<?php echo $workload['id']; ?>)" title="View">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteWorkload(<?php echo $workload['id']; ?>, '<?php echo htmlspecialchars($workload['faculty_name']); ?>')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <a href="encode-workload.php?edit=<?php echo $workload['id']; ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="print-workload.php?id=<?php echo $workload['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="bi bi-printer"></i> Print
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                onclick="viewWorkloadDetails(<?php echo $workload['id']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteWorkload(<?php echo $workload['id']; ?>, '<?php echo htmlspecialchars($workload['faculty_name']); ?>')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-file-text" style="font-size: 4rem; color: #ccc;"></i>
                <h4 class="text-muted mt-3">No Workloads Found</h4>
                <p class="text-muted">Start by <a href="encode-workload.php">creating a workload</a> for faculty members.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Workload Details Modal -->
<div class="modal fade" id="workloadDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Workload Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="workloadDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printModalContent()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteWorkloadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="delete-workload.php">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="workload_id" id="delete_workload_id">
                    <p>Are you sure you want to delete the workload for <strong id="delete_faculty_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewWorkloadDetails(workloadId) {
    if (!workloadId) {
        alert('Invalid workload ID');
        return;
    }
    
    // Show loading state
    const modalContent = document.getElementById('workloadDetailsContent');
    modalContent.innerHTML = '<div class="text-center p-4"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    new bootstrap.Modal(document.getElementById('workloadDetailsModal')).show();
    
    fetch(`get-workload-details.php?id=${workloadId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            modalContent.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div class="alert alert-danger">Error loading workload details. Please try again.</div>';
        });
}

function deleteWorkload(id, facultyName) {
    if (!id || !facultyName) {
        alert('Invalid workload data');
        return;
    }
    
    document.getElementById('delete_workload_id').value = id;
    document.getElementById('delete_faculty_name').textContent = facultyName;
    new bootstrap.Modal(document.getElementById('deleteWorkloadModal')).show();
}

function printModalContent() {
    const content = document.getElementById('workloadDetailsContent').innerHTML;
    if (!content || content.includes('Loading') || content.includes('Error')) {
        alert('Please wait for the content to load before printing');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Please allow popups to print the workload');
        return;
    }
    
    printWindow.document.open();
    printWindow.document.write('<html><head><title>Faculty Workload</title>');
    printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
    printWindow.document.write('<style>body { font-size: 12px; } .table th, .table td { padding: 0.25rem; border: 1px solid #000 !important; } @media print { .no-print { display: none; } }</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('<script>window.onload = function() { setTimeout(function() { window.print(); window.close(); }, 500); }<\/script>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
}

// Add event listeners for better UX
document.addEventListener('DOMContentLoaded', function() {
    // Add loading states to action buttons
    document.querySelectorAll('a[href*="print-workload.php"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Opening...';
            setTimeout(() => {
                this.innerHTML = originalText;
            }, 2000);
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?> 