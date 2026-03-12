<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

$page_title = 'Manage Rooms';
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'];
    
    if ($action === 'add') {
        $room_name = trim($_POST['room_name']);
        $building = trim($_POST['building']);
        $capacity = (int)$_POST['capacity'];
        $room_type = $_POST['room_type'];
        $equipment = trim($_POST['equipment']);
        $status = $_POST['status'];
        
        if (empty($room_name)) {
            $error_message = 'Room name is required.';
        } else {
            // Check if room already exists
            $check_room = $conn->prepare("SELECT id FROM rooms WHERE room_name = ?");
            $check_room->bind_param("s", $room_name);
            $check_room->execute();
            
            if ($check_room->get_result()->num_rows > 0) {
                $error_message = 'Room already exists.';
            } else {
                // Insert new room
                $stmt = $conn->prepare("INSERT INTO rooms (room_name, building, capacity, room_type, equipment, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisss", $room_name, $building, $capacity, $room_type, $equipment, $status);
                
                if ($stmt->execute()) {
                    $success_message = 'Room added successfully.';
                } else {
                    $error_message = 'Error adding room.';
                }
            }
        }
    }
    
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        $room_name = trim($_POST['room_name']);
        $building = trim($_POST['building']);
        $capacity = (int)$_POST['capacity'];
        $room_type = $_POST['room_type'];
        $equipment = trim($_POST['equipment']);
        $status = $_POST['status'];
        
        if (empty($room_name)) {
            $error_message = 'Room name is required.';
        } else {
            // Update room
            $stmt = $conn->prepare("UPDATE rooms SET room_name = ?, building = ?, capacity = ?, room_type = ?, equipment = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssisssi", $room_name, $building, $capacity, $room_type, $equipment, $status, $id);
            
            if ($stmt->execute()) {
                $success_message = 'Room updated successfully.';
            } else {
                $error_message = 'Error updating room.';
            }
        }
    }
    
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        // Check if room is being used in any teaching loads
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM teaching_loads WHERE room = (SELECT room_name FROM rooms WHERE id = ?)");
        $check_usage->bind_param("i", $id);
        $check_usage->execute();
        $usage_count = $check_usage->get_result()->fetch_assoc()['count'];
        
        if ($usage_count > 0) {
            $error_message = 'Cannot delete room. It is currently being used in ' . $usage_count . ' schedule(s).';
        } else {
            // Delete room
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success_message = 'Room deleted successfully.';
            } else {
                $error_message = 'Error deleting room.';
            }
        }
    }
}

// Get all rooms
$rooms_query = "SELECT * FROM rooms ORDER BY building, room_name";
$rooms_result = $conn->query($rooms_query);

include '../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
    <h2 class="mb-2 mb-md-0"><i class="bi bi-door-open"></i> Manage Rooms</h2>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
        <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Add Room</span>
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

<!-- Room Statistics -->
<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4><?php echo $rooms_result->num_rows; ?></h4>
                        <p class="mb-0">Total Rooms</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-door-open" style="font-size: 2rem;"></i>
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
                        $active_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE status = 'active'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $active_rooms; ?></h4>
                        <p class="mb-0">Active Rooms</p>
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
                        $classrooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE room_type = 'classroom'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $classrooms; ?></h4>
                        <p class="mb-0">Classrooms</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-house" style="font-size: 2rem;"></i>
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
                        $labs = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE room_type = 'laboratory'")->fetch_assoc()['count'];
                        ?>
                        <h4><?php echo $labs; ?></h4>
                        <p class="mb-0">Laboratories</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-cpu" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Rooms List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list"></i> Room List</h5>
    </div>
    <div class="card-body">
        <?php if ($rooms_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Room Name</th>
                            <th>Building</th>
                            <th>Capacity</th>
                            <th>Type</th>
                            <th>Equipment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rooms_result->data_seek(0);
                        while ($room = $rooms_result->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($room['room_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($room['building'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($room['capacity'] > 0): ?>
                                        <span class="badge bg-info"><?php echo $room['capacity']; ?> seats</span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        echo $room['room_type'] === 'classroom' ? 'bg-primary' : 
                                             ($room['room_type'] === 'laboratory' ? 'bg-success' : 
                                             ($room['room_type'] === 'auditorium' ? 'bg-warning' : 'bg-secondary'));
                                    ?>">
                                        <?php echo ucfirst($room['room_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($room['equipment'] ?? 'None'); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php 
                                        echo $room['status'] === 'active' ? 'bg-success' : 
                                             ($room['status'] === 'maintenance' ? 'bg-warning text-dark' : 'bg-danger');
                                    ?>">
                                        <?php echo ucfirst($room['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group-vertical btn-group-sm d-md-none" role="group">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)" 
                                                title="Edit Room">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>')" 
                                                title="Delete Room">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                    <div class="btn-group d-none d-md-flex" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editRoom(<?php echo htmlspecialchars(json_encode($room)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>')">
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
                <i class="bi bi-door-open" style="font-size: 3rem; color: #ccc;"></i>
                <p class="text-muted mt-2">No rooms found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_room_name" class="form-label">Room Name *</label>
                            <input type="text" class="form-control" id="add_room_name" name="room_name" required 
                                   placeholder="e.g., Room 101, Computer Lab 1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_building" class="form-label">Building</label>
                            <input type="text" class="form-control" id="add_building" name="building" 
                                   placeholder="e.g., Main Building, IT Building">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="add_capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="add_capacity" name="capacity" min="0" max="500" 
                                   placeholder="Number of seats">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="add_room_type" class="form-label">Room Type</label>
                            <select class="form-control" id="add_room_type" name="room_type">
                                <option value="classroom">Classroom</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="auditorium">Auditorium</option>
                                <option value="conference">Conference Room</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="add_status" class="form-label">Status</label>
                            <select class="form-control" id="add_status" name="status">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="add_equipment" class="form-label">Equipment & Facilities</label>
                        <textarea class="form-control" id="add_equipment" name="equipment" rows="3" 
                                  placeholder="e.g., Whiteboard, Projector, Air Conditioning, Computers"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_room_name" class="form-label">Room Name *</label>
                            <input type="text" class="form-control" id="edit_room_name" name="room_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_building" class="form-label">Building</label>
                            <input type="text" class="form-control" id="edit_building" name="building">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit_capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="edit_capacity" name="capacity" min="0" max="500">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_room_type" class="form-label">Room Type</label>
                            <select class="form-control" id="edit_room_type" name="room_type">
                                <option value="classroom">Classroom</option>
                                <option value="laboratory">Laboratory</option>
                                <option value="auditorium">Auditorium</option>
                                <option value="conference">Conference Room</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-control" id="edit_status" name="status">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_equipment" class="form-label">Equipment & Facilities</label>
                        <textarea class="form-control" id="edit_equipment" name="equipment" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteRoomModal" tabindex="-1">
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
                    <p class="text-danger"><small>This action cannot be undone. Make sure no schedules are using this room.</small></p>
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
function editRoom(room) {
    if (!room || !room.id) {
        alert('Invalid room data');
        return;
    }
    
    try {
        document.getElementById('edit_id').value = room.id;
        document.getElementById('edit_room_name').value = room.room_name || '';
        document.getElementById('edit_building').value = room.building || '';
        document.getElementById('edit_capacity').value = room.capacity || 0;
        document.getElementById('edit_room_type').value = room.room_type || 'classroom';
        document.getElementById('edit_equipment').value = room.equipment || '';
        document.getElementById('edit_status').value = room.status || 'active';
        
        new bootstrap.Modal(document.getElementById('editRoomModal')).show();
    } catch (error) {
        console.error('Error editing room:', error);
        alert('Error opening edit form. Please try again.');
    }
}

function deleteRoom(id, name) {
    if (!id || !name) {
        alert('Invalid room data');
        return;
    }
    
    try {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = name;
        
        new bootstrap.Modal(document.getElementById('deleteRoomModal')).show();
    } catch (error) {
        console.error('Error deleting room:', error);
        alert('Error opening delete confirmation. Please try again.');
    }
}
</script>

<?php include '../includes/footer.php'; ?> 