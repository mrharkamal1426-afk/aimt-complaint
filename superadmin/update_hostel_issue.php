<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in as superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

// Check if request is POST and has required fields
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['hostel_issue_id'], $_POST['new_status'])) {
    redirect('dashboard.php?error=invalid_request');
}

$hostel_issue_id = intval($_POST['hostel_issue_id']);
$new_status = $_POST['new_status'];
$technician_id = isset($_POST['technician_id']) && $_POST['technician_id'] !== '' ? intval($_POST['technician_id']) : null;

// Validate status
$allowed_statuses = ['not_assigned', 'in_progress', 'resolved'];
if (!in_array($new_status, $allowed_statuses)) {
    redirect('dashboard.php?error=invalid_status');
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Update hostel issue status and technician
    $stmt = $mysqli->prepare("UPDATE hostel_issues SET status = ?, technician_id = ? WHERE id = ?");
    $stmt->bind_param('sii', $new_status, $technician_id, $hostel_issue_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update hostel issue status");
    }
    
    $stmt->close();

    // Commit transaction
    $mysqli->commit();

    // Redirect back with success message
    redirect('dashboard.php?success=status_updated');

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    // Redirect back with error message
    redirect('dashboard.php?error=' . urlencode($e->getMessage()));
}

// Close database connection
if (isset($mysqli)) {
    $mysqli->close();
}
?> 