<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tech_id = $_SESSION['user_id'];
// Accept token from POST or GET
$token = isset($_POST['token']) ? trim($_POST['token']) : (isset($_GET['token']) ? trim($_GET['token']) : '');

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}

// Get technician's specialization
$stmt = $mysqli->prepare("SELECT specialization FROM users WHERE id = ?");
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$stmt->bind_result($specialization);
$stmt->fetch();
$stmt->close();

if (!$specialization) {
    echo json_encode(['success' => false, 'message' => 'No specialization found']);
    exit;
}

// Fetch complaint details - now includes both category match and assigned complaints
$stmt = $mysqli->prepare("
    SELECT c.*, u.full_name, u.phone, u.hostel_type
    FROM complaints c
    JOIN users u ON u.id = c.user_id
    WHERE c.token = ? AND (c.category = ? OR c.technician_id = ?)
");

$stmt->bind_param('ssi', $token, $specialization, $tech_id);
$stmt->execute();
$result = $stmt->get_result();
$complaint = $result->fetch_assoc();
$stmt->close();

if (!$complaint) {
    echo json_encode(['success' => false, 'message' => 'Complaint not found or not authorized']);
    exit;
}


// Return complaint details
echo json_encode([
    'success' => true,
    'complaint' => $complaint
]);
?> 