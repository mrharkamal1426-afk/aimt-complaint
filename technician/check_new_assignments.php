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

// Get technician's last login time
$stmt = $mysqli->prepare("SELECT last_login, is_online FROM users WHERE id = ?");
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$stmt->bind_result($last_login, $is_online);
$stmt->fetch();
$stmt->close();

if (!$last_login) {
    $last_login = date('Y-m-d H:i:s', strtotime('-1 day'));
}

// Check for new assignments since last login
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as new_complaints
    FROM complaints 
    WHERE technician_id = ? 
    AND updated_at >= ?
    AND status IN ('pending', 'in_progress')
");
$stmt->bind_param('is', $tech_id, $last_login);
$stmt->execute();
$stmt->bind_result($new_complaints);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT COUNT(*) as new_hostel_issues
    FROM hostel_issues 
    WHERE technician_id = ? 
    AND updated_at >= ?
    AND status IN ('in_progress')
");
$stmt->bind_param('is', $tech_id, $last_login);
$stmt->execute();
$stmt->bind_result($new_hostel_issues);
$stmt->fetch();
$stmt->close();

$stmt = $mysqli->prepare("
    SELECT COUNT(*) as unread_notifications
    FROM notifications 
    WHERE user_id = ? 
    AND is_read = 0
    AND created_at >= ?
");
$stmt->bind_param('is', $tech_id, $last_login);
$stmt->execute();
$stmt->bind_result($unread_notifications);
$stmt->fetch();
$stmt->close();

$has_new_assignments = ($new_complaints > 0 || $new_hostel_issues > 0 || $unread_notifications > 0);

$message = $has_new_assignments ? 'You have new assignments!' : 'No new assignments';

// If technician is offline, add a flag
$is_offline = ($is_online == 0);

echo json_encode([
    'success' => true,
    'has_new_assignments' => $has_new_assignments,
    'new_complaints' => $new_complaints,
    'new_hostel_issues' => $new_hostel_issues,
    'unread_notifications' => $unread_notifications,
    'message' => $message,
    'is_offline' => $is_offline
]);
?> 