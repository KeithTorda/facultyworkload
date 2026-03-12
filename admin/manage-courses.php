<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Manage Courses';
$success_message = '';
$error_message = '';

// Create courses table if not exists
$create_table = "
CREATE TABLE IF NOT EXISTS courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_title VARCHAR(200) NOT NULL,
    course_category VARCHAR(50) NULL,
    units INT DEFAULT 3,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table);

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $course_code = trim($_POST['course_code']);
        $course_title = trim($_POST['course_title']);
        $course_category = trim($_POST['course_category']);
        $units = (int)$_POST['units'];
        $status = $_POST['status'];
        
        if (empty($course_code) || empty($course_title)) {
            $error_message = 'Course code and title are required.';
        } else {
            // Check if course already exists
            $check_course = $conn->prepare("SELECT id FROM courses WHERE course_code = ?");
            $check_course->bind_param("s", $course_code);
            $check_course->execute();
            
            if ($check_course->get_result()->num_rows > 0) {
                $error_message = 'Course code already exists.';
            } else {
                // Insert new course
                $stmt = $conn->prepare("INSERT INTO courses (course_code, course_title, course_category, units, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssis", $course_code, $course_title, $course_category, $units, $status);
                
                if ($stmt->execute()) {
                    $success_message = 'Course added successfully.';
                } else {
                    $error_message = 'Error adding course.';
                }
            }
        }
    }
    
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $course_code = trim($_POST['course_code']);
        $course_title = trim($_POST['course_title']);
        $course_category = trim($_POST['course_category']);
        $units = (int)$_POST['units'];
        $status = $_POST['status'];
        
        if (empty($course_code) || empty($course_title)) {
            $error_message = 'Course code and title are required.';
        } else {
            // Update course
            $stmt = $conn->prepare("UPDATE courses SET course_code = ?, course_title = ?, course_category = ?, units = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssisi", $course_code, $course_title, $course_category, $units, $status, $id);
            
            if ($stmt->execute()) {
                $success_message = 'Course updated successfully.';
            } else {
                $error_message = 'Error updating course.';
            }
        }
    }
    
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        // Check if course is being used in teaching loads
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM teaching_loads WHERE course_code = (SELECT course_code FROM courses WHERE id = ?)");
        $check_usage->bind_param("i", $id);
        $check_usage->execute();
        $usage_count = $check_usage->get_result()->fetch_assoc()['count'];
        
        if ($usage_count > 0) {
            $error_message = 'Cannot delete course. It is currently being used in ' . $usage_count . ' teaching load(s).';
        } else {
            // Delete course
            $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = 'Course deleted successfully.';
            } else {
                $error_message = 'Error deleting course.';
            }
        }
    }
}

// Get all courses
$courses_query = "SELECT * FROM courses ORDER BY course_category, course_code";
$courses_result = $conn->query($courses_query);

// Get course categories for filter
$categories_query = "SELECT DISTINCT course_category FROM courses WHERE course_category IS NOT NULL AND course_category != '' ORDER BY course_category";
$categories_result = $conn->query($categories_query);

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-book"></i> Manage Courses</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add Course</span>
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

<!-- Course Statistics -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $courses_result->num_rows; ?></h4>
                        <p class="mb-0">Total Courses</p>
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
                        <?php 
                        $active_courses = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $active_courses; ?></h4>
                        <p class="mb-0">Active Courses</p>
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
                        $categories_count = $conn->query("SELECT COUNT(DISTINCT course_category) as count FROM courses WHERE course_category IS NOT NULL AND course_category != ''")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $categories_count; ?></h4>
                        <p class="mb-0">Categories</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-folder" style="font-size: 2rem;"></i>
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

<!-- Courses List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> Course List</h5>
    </div>
    <div class="card-body">
        <?php if ($courses_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Title</th>
                            <th>Category</th>
                            <th>Units</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $courses_result->data_seek(0);
                        while ($course = $courses_result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['course_title']); ?></td>
                                <td>
                                    <?php if ($course['course_category']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($course['course_category']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $course['units']; ?></td>
                                <td>
                                    <span class="badge <?php echo $course['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($course['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editCourse(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteCourse(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>')">
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
                <i class="bi bi-book" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No courses found. <a href="#" data-bs-toggle="modal" data-bs-target="#addCourseModal">Add your first course</a>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Course Modal -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="add_course_code" class="form-label">Course Code *</label>
                        <input type="text" class="form-control" id="add_course_code" name="course_code" required 
                               placeholder="e.g., IT 111, NSTP 11">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_course_title" class="form-label">Course Title *</label>
                        <input type="text" class="form-control" id="add_course_title" name="course_title" required 
                               placeholder="e.g., Introduction to Computing">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_course_category" class="form-label">Category</label>
                        <select class="form-control" id="add_course_category" name="course_category">
                            <option value="">Select Category</option>
                            <option value="NSTP">NSTP Courses</option>
                            <option value="IT">IT Courses</option>
                            <option value="GE">General Education</option>
                            <option value="PROF">Professional Courses</option>
                            <option value="ELEC">Elective Courses</option>
                            <option value="OTHER">Other</option>
                        </select>
                        <small class="text-muted">Or type custom category</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_units" class="form-label">Units</label>
                            <input type="number" class="form-control" id="add_units" name="units" min="1" max="6" value="3">
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
                    <button type="submit" class="btn btn-primary">Add Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Course Modal -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_course_code" class="form-label">Course Code *</label>
                        <input type="text" class="form-control" id="edit_course_code" name="course_code" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_title" class="form-label">Course Title *</label>
                        <input type="text" class="form-control" id="edit_course_title" name="course_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_course_category" class="form-label">Category</label>
                        <select class="form-control" id="edit_course_category" name="course_category">
                            <option value="">Select Category</option>
                            <option value="NSTP">NSTP Courses</option>
                            <option value="IT">IT Courses</option>
                            <option value="GE">General Education</option>
                            <option value="PROF">Professional Courses</option>
                            <option value="ELEC">Elective Courses</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_units" class="form-label">Units</label>
                            <input type="number" class="form-control" id="edit_units" name="units" min="1" max="6">
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
                    <button type="submit" class="btn btn-primary">Update Course</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCourseModal" tabindex="-1">
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
                    <p>Are you sure you want to delete <strong id="delete_code"></strong>?</p>
                    <p class="text-danger"><small>This action cannot be undone if the course is not being used.</small></p>
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
function editCourse(course) {
    document.getElementById('edit_id').value = course.id;
    document.getElementById('edit_course_code').value = course.course_code || '';
    document.getElementById('edit_course_title').value = course.course_title || '';
    document.getElementById('edit_course_category').value = course.course_category || '';
    document.getElementById('edit_units').value = course.units || 3;
    document.getElementById('edit_status').value = course.status || 'active';
    
    new bootstrap.Modal(document.getElementById('editCourseModal')).show();
}

function deleteCourse(id, code) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_code').textContent = code;
    
    new bootstrap.Modal(document.getElementById('deleteCourseModal')).show();
}

// Allow custom category input
document.getElementById('add_course_category').addEventListener('change', function() {
    if (this.value === 'OTHER') {
        this.outerHTML = '<input type="text" class="form-control" name="course_category" placeholder="Enter custom category">';
    }
});

document.getElementById('edit_course_category').addEventListener('change', function() {
    if (this.value === 'OTHER') {
        this.outerHTML = '<input type="text" class="form-control" name="course_category" placeholder="Enter custom category">';
    }
});
</script>

<?php include '../includes/footer.php'; ?>

