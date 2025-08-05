<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student', 'faculty', 'nonteaching'])) {
    redirect('../login.php?error=unauthorized');
}

// Check if token is provided
if (!isset($_POST['token']) || empty($_POST['token'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Token is required'
    ]));
}

$token = trim($_POST['token']);
$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if complaint exists and belongs to the user
    $stmt = $mysqli->prepare("SELECT id, status FROM complaints WHERE token = ? AND user_id = ? FOR UPDATE");
    $stmt->bind_param('si', $token, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();

    if (!$complaint) {
        throw new Exception('Complaint not found or unauthorized');
    }

    // Only allow deletion of pending complaints
    if ($complaint['status'] !== 'pending') {
        throw new Exception('Only pending complaints can be deleted');
    }

    // Delete the complaint
    $stmt = $mysqli->prepare("DELETE FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint['id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete complaint');
    }
    $stmt->close();

    // Commit transaction
    $mysqli->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Complaint deleted successfully'
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($mysqli)) {
    $mysqli->close();
}
?> 