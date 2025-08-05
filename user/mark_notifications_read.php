<?php
require_once __DIR__.'/../includes/config.php';
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}
$user_id = $_SESSION['user_id'];

// Mark resolved complaints as read
$sql = "INSERT IGNORE INTO user_notifications (user_id, complaint_token, type) 
        SELECT id_user, token, 'resolved' FROM (
            SELECT ? as id_user, token FROM complaints 
            WHERE user_id = ? AND status = 'resolved' 
        ) as subq";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$stmt->close();

// Mark resolved hostel-wide issues as read (only for students)
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    $sql = "INSERT IGNORE INTO user_notifications (user_id, hostel_issue_id, type) 
            SELECT ?, hi.id, 'hostel_resolved' FROM hostel_issues hi 
            WHERE hi.status = 'resolved' AND hi.hostel_type = (
                SELECT hostel_type FROM users WHERE id = ?
            )";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('ii', $user_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]); 