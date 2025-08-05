<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Only technicians allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../auth/login.php?error=unauthorized');
}

if (!isset($_POST['token']) || empty($_POST['token'])) {
    die(json_encode(['success'=>false,'message'=>'Token is required']));
}

$token = trim($_POST['token']);
$user_id = $_SESSION['user_id'];

try {
    $mysqli->begin_transaction();

    // Ensure complaint belongs to this technician (as user) and is pending
    $stmt = $mysqli->prepare("SELECT id, status FROM complaints WHERE token = ? AND user_id = ? FOR UPDATE");
    $stmt->bind_param('si', $token, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();

    if (!$complaint) {
        throw new Exception('Complaint not found or unauthorized');
    }

    if ($complaint['status'] !== 'pending') {
        throw new Exception('Only pending complaints can be deleted');
    }

    $stmt = $mysqli->prepare("DELETE FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint['id']);

    if (!$stmt->execute()) {
        throw new Exception('Failed to delete complaint');
    }
    $stmt->close();

    $mysqli->commit();

    // Set flash message and redirect back to My Complaints page
    $_SESSION['status_message'] = 'Complaint deleted successfully.';
    header('Location: my_complaints.php');
    exit;

} catch (Exception $e) {
    if (isset($mysqli)) { $mysqli->rollback(); }
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}

if (isset($mysqli)) { $mysqli->close(); }
?>
