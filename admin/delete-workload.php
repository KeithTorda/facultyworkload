<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

if ($_POST && isset($_POST['workload_id'])) {
    $workload_id = $_POST['workload_id'];
    
    // Delete workload (cascade will delete related records)
    $stmt = $conn->prepare("DELETE FROM workloads WHERE id = ?");
    $stmt->bind_param("i", $workload_id);
    
    if ($stmt->execute()) {
        header('Location: view-workloads.php?message=deleted');
    } else {
        header('Location: view-workloads.php?error=delete_failed');
    }
} else {
    header('Location: view-workloads.php');
}
exit();
?> 