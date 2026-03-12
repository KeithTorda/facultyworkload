<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Manage Faculty';
$active_page = 'manage-faculty';

// Process form submission for adding/editing faculty
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    // Generate email automatically from name
    $email = strtolower(str_replace(' ', '.', $name)) . '@apayao.edu.ph';
    $faculty_rank = $_POST['faculty_rank'] ?? '';
    $eligibility = $_POST['eligibility'] ?? '';
    $bachelor_degree = $_POST['bachelor_degree'] ?? '';
    $master_degree = $_POST['master_degree'] ?? '';
    $doctorate_degree = $_POST['doctorate_degree'] ?? '';
    $scholarship = $_POST['scholarship'] ?? '';
    $length_of_service = $_POST['length_of_service'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Handle photo upload
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "../uploads/faculty/";
        $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
        $photo = uniqid() . '.' . $file_extension;
        $target_file = $target_dir . $photo;
        
        // Check if directory exists, if not create it
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        // Upload file
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            // File uploaded successfully
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    }
    
    if ($id > 0) {
        // Update existing faculty (email will update automatically based on name)
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, faculty_rank = ?, eligibility = ?, bachelor_degree = ?, master_degree = ?, doctorate_degree = ?, scholarship = ?, length_of_service = ?, status = ? WHERE id = ? AND role = 'faculty'");
        $stmt->bind_param("ssssssssssi", $name, $email, $faculty_rank, $eligibility, $bachelor_degree, $master_degree, $doctorate_degree, $scholarship, $length_of_service, $status, $id);
        
        // Update photo if a new one was uploaded
        if (!empty($photo)) {
            $photo_stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
            $photo_stmt->bind_param("si", $photo, $id);
            $photo_stmt->execute();
        }
    } else {
        // Add new faculty - generate a random password
        $random_password = bin2hex(random_bytes(8)); // Generate a 16-character random password
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, faculty_rank, eligibility, bachelor_degree, master_degree, doctorate_degree, scholarship, length_of_service, photo, status) VALUES (?, ?, ?, 'faculty', ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssss", $name, $email, $hashed_password, $faculty_rank, $eligibility, $bachelor_degree, $master_degree, $doctorate_degree, $scholarship, $length_of_service, $photo, $status);
    }
    
    if ($stmt->execute()) {
        $success = "Faculty information saved successfully! Email: " . $email;
        if ($id == 0) {
            $success .= " | Password: " . $random_password;
        }
    } else {
        $error = "Error: " . $stmt->error;
    }
}

// Delete faculty
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'faculty'");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $success = "Faculty deleted successfully!";
    } else {
        $error = "Error deleting faculty: " . $stmt->error;
    }
}

// Get faculty list
$faculty_query = "SELECT * FROM users WHERE role = 'faculty' ORDER BY name";
$faculty_result = $conn->query($faculty_query);

// Get faculty details for editing
$edit_id = $_GET['edit'] ?? 0;
$faculty_data = null;

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'faculty'");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $faculty_data = $stmt->get_result()->fetch_assoc();
}

include_once '../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4"><?php echo $page_title; ?></h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
        <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
    </ol>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-user-plus me-1"></i>
            <?php echo $edit_id > 0 ? 'Edit Faculty' : 'Add New Faculty'; ?>
        </div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label for="name">Name:</label>
                            <input type="text" class="form-control" id="name" name="name" required value="<?php echo $faculty_data['name'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="faculty_rank">Faculty Rank:</label>
                            <select class="form-control" id="faculty_rank" name="faculty_rank">
                                <option value="">Select Rank</option>
                                <option value="Instructor I" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Instructor I') ? 'selected' : ''; ?>>Instructor I</option>
                                <option value="Instructor II" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Instructor II') ? 'selected' : ''; ?>>Instructor II</option>
                                <option value="Instructor III" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Instructor III') ? 'selected' : ''; ?>>Instructor III</option>
                                <option value="Assistant Professor I" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Assistant Professor I') ? 'selected' : ''; ?>>Assistant Professor I</option>
                                <option value="Assistant Professor II" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Assistant Professor II') ? 'selected' : ''; ?>>Assistant Professor II</option>
                                <option value="Assistant Professor III" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Assistant Professor III') ? 'selected' : ''; ?>>Assistant Professor III</option>
                                <option value="Assistant Professor IV" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Assistant Professor IV') ? 'selected' : ''; ?>>Assistant Professor IV</option>
                                <option value="Associate Professor I" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Associate Professor I') ? 'selected' : ''; ?>>Associate Professor I</option>
                                <option value="Associate Professor II" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Associate Professor II') ? 'selected' : ''; ?>>Associate Professor II</option>
                                <option value="Associate Professor III" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Associate Professor III') ? 'selected' : ''; ?>>Associate Professor III</option>
                                <option value="Associate Professor IV" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Associate Professor IV') ? 'selected' : ''; ?>>Associate Professor IV</option>
                                <option value="Associate Professor V" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Associate Professor V') ? 'selected' : ''; ?>>Associate Professor V</option>
                                <option value="Professor I" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor I') ? 'selected' : ''; ?>>Professor I</option>
                                <option value="Professor II" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor II') ? 'selected' : ''; ?>>Professor II</option>
                                <option value="Professor III" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor III') ? 'selected' : ''; ?>>Professor III</option>
                                <option value="Professor IV" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor IV') ? 'selected' : ''; ?>>Professor IV</option>
                                <option value="Professor V" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor V') ? 'selected' : ''; ?>>Professor V</option>
                                <option value="Professor VI" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Professor VI') ? 'selected' : ''; ?>>Professor VI</option>
                                <option value="College/University Professor" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'College/University Professor') ? 'selected' : ''; ?>>College/University Professor</option>
                                <option value="Visiting Lecturer" <?php echo (isset($faculty_data['faculty_rank']) && $faculty_data['faculty_rank'] === 'Visiting Lecturer') ? 'selected' : ''; ?>>Visiting Lecturer</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="eligibility">Eligibility:</label>
                            <input type="text" class="form-control" id="eligibility" name="eligibility" value="<?php echo $faculty_data['eligibility'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="length_of_service">Length of Service:</label>
                            <input type="text" class="form-control" id="length_of_service" name="length_of_service" value="<?php echo $faculty_data['length_of_service'] ?? ''; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="bachelor_degree">Bachelor's Degree:</label>
                            <input type="text" class="form-control" id="bachelor_degree" name="bachelor_degree" value="<?php echo $faculty_data['bachelor_degree'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label for="master_degree">Master's Degree:</label>
                            <input type="text" class="form-control" id="master_degree" name="master_degree" value="<?php echo $faculty_data['master_degree'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label for="doctorate_degree">Doctorate Degree:</label>
                            <input type="text" class="form-control" id="doctorate_degree" name="doctorate_degree" value="<?php echo $faculty_data['doctorate_degree'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label for="scholarship">Scholarship:</label>
                            <input type="text" class="form-control" id="scholarship" name="scholarship" value="<?php echo $faculty_data['scholarship'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="photo">Faculty Photo:</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <?php if (!empty($faculty_data['photo'])): ?>
                                <div class="mt-2">
                                    <img src="../uploads/faculty/<?php echo $faculty_data['photo']; ?>" alt="Current Photo" style="max-width: 150px; max-height: 150px;">
                                    <p class="text-muted">Current photo</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="status">Status:</label>
                            <select class="form-control" id="status" name="status">
                                <option value="active" <?php echo (isset($faculty_data['status']) && $faculty_data['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($faculty_data['status']) && $faculty_data['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Faculty
                    </button>
                    <a href="manage-faculty.php" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-users me-1"></i>
            Faculty List
        </div>
        <div class="card-body">
            <table id="facultyTable" class="table table-striped table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Faculty Rank</th>
                        <th>Length of Service</th>
                        <th>Photo</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $faculty_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['faculty_rank']; ?></td>
                        <td><?php echo $row['length_of_service'] ?? 'N/A'; ?></td>
                        <td>
                            <?php if (!empty($row['photo'])): ?>
                                <img src="../uploads/faculty/<?php echo $row['photo']; ?>" alt="Faculty Photo" style="max-width: 50px; max-height: 50px;">
                            <?php else: ?>
                                <span class="text-muted">No photo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?php echo $row['status'] === 'active' ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="manage-faculty.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="manage-faculty.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this faculty?');">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#facultyTable').DataTable({
            responsive: true
        });
    });
</script>

<?php include_once '../includes/footer.php'; ?> 