<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Manage Users';
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        if (empty($name) || empty($email)) {
            $error_message = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email format.';
        } else {
            // Check if email already exists
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->bind_param("s", $email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $error_message = 'Email already exists.';
            } else {
                // Generate a random password and hash it
                $random_password = bin2hex(random_bytes(8)); // Generate a 16-character random password
                $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $success_message = 'User added successfully. A temporary password has been generated for the account.';
                } else {
                    $error_message = 'Error adding user.';
                }
            }
        }
    }
    
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        
        if (empty($name) || empty($email)) {
            $error_message = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email format.';
        } else {
            // Check if email exists for other users
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email->bind_param("si", $email, $id);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $error_message = 'Email already exists for another user.';
            } else {
                // Update user without password
                $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $role, $id);
                
                if (!$error_message && $stmt->execute()) {
                    $success_message = 'User updated successfully.';
                } elseif (!$error_message) {
                    $error_message = 'Error updating user.';
                }
            }
        }
    }
    
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        $current_user = getCurrentUser();
        
        if ($id == $current_user['id']) {
            $error_message = 'You cannot delete your own account.';
        } else {
            // Check if user has workloads
            $check_workloads = $conn->prepare("SELECT COUNT(*) as count FROM workloads WHERE faculty_id = ?");
            $check_workloads->bind_param("i", $id);
            $check_workloads->execute();
            $workload_count = $check_workloads->get_result()->fetch_assoc()['count'];
            
            if ($workload_count > 0) {
                $error_message = 'Cannot delete user. They have ' . $workload_count . ' workload(s) assigned.';
            } else {
                // Delete user
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $success_message = 'User deleted successfully.';
                } else {
                    $error_message = 'Error deleting user.';
                }
            }
        }
    }
    
    elseif ($action === 'toggle_status') {
        $id = $_POST['id'];
        $current_user = getCurrentUser();
        
        if ($id == $current_user['id']) {
            $error_message = 'You cannot change your own status.';
        } else {
            // Get current status
            $user_query = $conn->prepare("SELECT status FROM users WHERE id = ?");
            $user_query->bind_param("i", $id);
            $user_query->execute();
            $user_data = $user_query->get_result()->fetch_assoc();
            
            if ($user_data) {
                $new_status = ($user_data['status'] === 'active') ? 'inactive' : 'active';
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $id);
                
                if ($stmt->execute()) {
                    $success_message = 'User status updated successfully.';
                } else {
                    $error_message = 'Error updating user status.';
                }
            }
        }
    }
}

// Get all users with statistics
$users_query = "SELECT u.*, 
                (SELECT COUNT(*) FROM workloads w WHERE w.faculty_id = u.id) as workload_count,
                u.created_at
                FROM users u 
                ORDER BY u.role DESC, u.name ASC";
$users_result = $conn->query($users_query);

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-person-gear"></i> Manage Users</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus"></i> <span class="d-none d-sm-inline">Add User</span>
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

<!-- User Statistics -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $users_result->num_rows; ?></h4>
                        <p class="mb-0">Total Users</p>
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
                        <?php 
                        $admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $admin_count; ?></h4>
                        <p class="mb-0">Administrators</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-shield-check" style="font-size: 2rem;"></i>
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
                        $faculty_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'faculty'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $faculty_count; ?></h4>
                        <p class="mb-0">Faculty Members</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-mortarboard" style="font-size: 2rem;"></i>
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
                        <?php 
                        $active_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $active_count; ?></h4>
                        <p class="mb-0">Active Users</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-person-check" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> User List</h5>
    </div>
    <div class="card-body">
        <?php if ($users_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Workloads</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_user = getCurrentUser();
                        $users_result->data_seek(0);
                        while ($user = $users_result->fetch_assoc()): 
                        ?>
                            <tr <?php echo $user['id'] == $current_user['id'] ? 'class="table-warning"' : ''; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                    <?php if ($user['id'] == $current_user['id']): ?>
                                        <span class="badge bg-warning text-dark">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                        <i class="bi bi-<?php echo $user['role'] === 'admin' ? 'shield-check' : 'mortarboard'; ?>"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo ucfirst($user['status'] ?? 'active'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'faculty'): ?>
                                        <span class="badge bg-info"><?php echo $user['workload_count']; ?> workload(s)</span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($user['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" 
                                                title="Edit User">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <?php if ($user['id'] != $current_user['id']): ?>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['status'] ?? 'active'; ?>')" 
                                                    title="Toggle Status">
                                                <i class="bi bi-toggle-<?php echo ($user['status'] ?? 'active') === 'active' ? 'off' : 'on'; ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', <?php echo $user['workload_count']; ?>)" 
                                                    title="Delete User">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <?php if ($user['id'] != $current_user['id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', '<?php echo $user['status'] ?? 'active'; ?>')">
                                                <i class="bi bi-toggle-<?php echo ($user['status'] ?? 'active') === 'active' ? 'off' : 'on'; ?>"></i>
                                                <?php echo ($user['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>', <?php echo $user['workload_count']; ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-people" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No users found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="add_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="add_name" name="name" required 
                               placeholder="Enter full name">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="add_email" name="email" required 
                               placeholder="Enter email address">
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_role" class="form-label">Role *</label>
                        <select class="form-control" id="add_role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="faculty">Faculty</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> A temporary password will be automatically generated.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-control" id="edit_role" name="role" required>
                            <option value="admin">Administrator</option>
                            <option value="faculty">Faculty</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Toggle User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" id="toggle_id">
                    <p>Are you sure you want to <span id="toggle_action"></span> <strong id="toggle_name"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="toggle_btn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
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
                    <div id="delete_warning" class="alert alert-warning" style="display: none;">
                        <i class="bi bi-exclamation-triangle"></i> This user has workloads assigned and cannot be deleted.
                    </div>
                    <p class="text-danger"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_btn">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(user) {
    if (!user || !user.id) {
        alert('Invalid user data');
        return;
    }
    
    try {
        document.getElementById('edit_id').value = user.id;
        document.getElementById('edit_name').value = user.name || '';
        document.getElementById('edit_email').value = user.email || '';
        document.getElementById('edit_role').value = user.role || 'faculty';
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    } catch (error) {
        console.error('Error editing user:', error);
        alert('Error opening edit form. Please try again.');
    }
}

function toggleUserStatus(id, name, currentStatus) {
    if (!id || !name) {
        alert('Invalid user data');
        return;
    }
    
    try {
        document.getElementById('toggle_id').value = id;
        document.getElementById('toggle_name').textContent = name;
        
        const action = currentStatus === 'active' ? 'deactivate' : 'activate';
        document.getElementById('toggle_action').textContent = action;
        document.getElementById('toggle_btn').textContent = action === 'activate' ? 'Activate' : 'Deactivate';
        document.getElementById('toggle_btn').className = action === 'activate' ? 'btn btn-success' : 'btn btn-warning';
        
        new bootstrap.Modal(document.getElementById('toggleStatusModal')).show();
    } catch (error) {
        console.error('Error toggling user status:', error);
        alert('Error opening status toggle. Please try again.');
    }
}

function deleteUser(id, name, workloadCount) {
    if (!id || !name) {
        alert('Invalid user data');
        return;
    }
    
    try {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = name;
        
        const warningDiv = document.getElementById('delete_warning');
        const deleteBtn = document.getElementById('delete_btn');
        
        if (workloadCount > 0) {
            warningDiv.style.display = 'block';
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Cannot Delete';
        } else {
            warningDiv.style.display = 'none';
            deleteBtn.disabled = false;
            deleteBtn.textContent = 'Delete';
        }
        
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('Error opening delete confirmation. Please try again.');
    }
}
</script>

<?php include '../includes/footer.php'; ?> 