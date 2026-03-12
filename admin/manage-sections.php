<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Manage Sections';
$success_message = '';
$error_message = '';

// Create sections table if not exists
$create_table = "
CREATE TABLE IF NOT EXISTS sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(20) NOT NULL UNIQUE,
    year_level INT NOT NULL,
    program VARCHAR(100) NULL,
    max_students INT DEFAULT 40,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $section_name = trim($_POST['section_name']);
        $year_level = (int)$_POST['year_level'];
        $program = trim($_POST['program']);
        $max_students = (int)$_POST['max_students'];
        $status = $_POST['status'];
        
        if (empty($section_name)) {
            $error_message = 'Section name is required.';
        } else {
            // Check if section already exists
            $check_section = $conn->prepare("SELECT id FROM sections WHERE section_name = ?");
            $check_section->bind_param("s", $section_name);
            $check_section->execute();
            
            if ($check_section->get_result()->num_rows > 0) {
                $error_message = 'Section already exists.';
            } else {
                // Insert new section
                $stmt = $conn->prepare("INSERT INTO sections (section_name, year_level, program, max_students, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sisis", $section_name, $year_level, $program, $max_students, $status);
                
                if ($stmt->execute()) {
                    $success_message = 'Section added successfully.';
                } else {
                    $error_message = 'Error adding section.';
                }
            }
        }
    }
    
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $section_name = trim($_POST['section_name']);
        $year_level = (int)$_POST['year_level'];
        $program = trim($_POST['program']);
        $max_students = (int)$_POST['max_students'];
        $status = $_POST['status'];
        
        if (empty($section_name)) {
            $error_message = 'Section name is required.';
        } else {
            // Update section
            $stmt = $conn->prepare("UPDATE sections SET section_name = ?, year_level = ?, program = ?, max_students = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sisisi", $section_name, $year_level, $program, $max_students, $status, $id);
            
            if ($stmt->execute()) {
                $success_message = 'Section updated successfully.';
            } else {
                $error_message = 'Error updating section.';
            }
        }
    }
    
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        // Check if section is being used in teaching loads
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM teaching_loads WHERE section = (SELECT section_name FROM sections WHERE id = ?)");
        $check_usage->bind_param("i", $id);
        $check_usage->execute();
        $usage_count = $check_usage->get_result()->fetch_assoc()['count'];
        
        if ($usage_count > 0) {
            $error_message = 'Cannot delete section. It is currently being used in ' . $usage_count . ' teaching load(s).';
        } else {
            // Delete section
            $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = 'Section deleted successfully.';
            } else {
                $error_message = 'Error deleting section.';
            }
        }
    }
}

// Get all sections
$sections_query = "SELECT * FROM sections ORDER BY year_level, section_name";
$sections_result = $conn->query($sections_query);

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-collection"></i> Manage Sections</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add Section</span>
    </button>
</div>

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

<!-- Section Statistics -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $sections_result->num_rows; ?></h4>
                        <p class="mb-0">Total Sections</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-collection" style="font-size: 2rem;"></i>
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
                        <?php 
                        $active_sections = $conn->query("SELECT COUNT(*) as count FROM sections WHERE status = 'active'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $active_sections; ?></h4>
                        <p class="mb-0">Active Sections</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
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
                        <?php 
                        $year_levels = $conn->query("SELECT COUNT(DISTINCT year_level) as count FROM sections")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $year_levels; ?></h4>
                        <p class="mb-0">Year Levels</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-layers" style="font-size: 2rem;"></i>
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
                        <a href="encode-workload.php" class="text-white text-decoration-none">
                            <h5><i class="bi bi-arrow-right-circle"></i></h5>
                            <p class="mb-0">Go to Encode Workload</p>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Sections List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> Section List</h5>
    </div>
    <div class="card-body">
        <?php if ($sections_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Section Name</th>
                            <th>Year Level</th>
                            <th>Program</th>
                            <th>Max Students</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sections_result->data_seek(0);
                        while ($section = $sections_result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($section['section_name']); ?></strong></td>
                                <td>
                                    <span class="badge bg-primary">Year <?php echo $section['year_level']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($section['program'] ?? 'N/A'); ?></td>
                                <td><?php echo $section['max_students']; ?> students</td>
                                <td>
                                    <span class="badge <?php echo $section['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($section['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars($section['section_name']); ?>')">
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
            <div class="text-center py-4">
                <i class="bi bi-collection" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No sections found. <a href="#" data-bs-toggle="modal" data-bs-target="#addSectionModal">Add your first section</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="add_section_name" class="form-label">Section Name *</label>
                        <input type="text" class="form-control" id="add_section_name" name="section_name" required 
                               placeholder="e.g., 1A, 2B, 3C">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_year_level" class="form-label">Year Level *</label>
                        <select class="form-control" id="add_year_level" name="year_level" required>
                            <option value="">Select Year</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="add_program" name="program" 
                               placeholder="e.g., Bachelor of Science in Information Technology">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_max_students" class="form-label">Max Students</label>
                            <input type="number" class="form-control" id="add_max_students" name="max_students" 
                                   min="1" max="100" value="40">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-control" id="add_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Section</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_section_name" class="form-label">Section Name *</label>
                        <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_year_level" class="form-label">Year Level *</label>
                        <select class="form-control" id="edit_year_level" name="year_level" required>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_program" class="form-label">Program</label>
                        <input type="text" class="form-control" id="edit_program" name="program">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_max_students" class="form-label">Max Students</label>
                            <input type="number" class="form-control" id="edit_max_students" name="max_students" min="1" max="100">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">
                    <p>Are you sure you want to delete <strong id="delete_name"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone if the section is not being used.</small></p>
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
function editSection(section) {
    document.getElementById('edit_id').value = section.id;
    document.getElementById('edit_section_name').value = section.section_name || '';
    document.getElementById('edit_year_level').value = section.year_level || 1;
    document.getElementById('edit_program').value = section.program || '';
    document.getElementById('edit_max_students').value = section.max_students || 40;
    document.getElementById('edit_status').value = section.status || 'active';
    
    new bootstrap.Modal(document.getElementById('editSectionModal')).show();
}

function deleteSection(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    
    new bootstrap.Modal(document.getElementById('deleteSectionModal')).show();
}
</script>

<?php include '../includes/footer.php'; ?>

