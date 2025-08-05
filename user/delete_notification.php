<?php
session_start();
require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}
$user_id = $_SESSION['user_id'];

if (isset($_POST['all']) && $_POST['all'] == '1') {
    // Mark all resolved complaints as read
    $sql1 = "INSERT IGNORE INTO user_notifications (user_id, complaint_token, type)
             SELECT ?, token, 'resolved' FROM complaints WHERE user_id = ? AND status = 'resolved'";
    $stmt1 = $mysqli->prepare($sql1);
    $stmt1->bind_param('ii', $user_id, $user_id);
    $ok1 = $stmt1->execute();
    $stmt1->close();

    // Mark all resolved hostel-wide issues as read (only for students)
    $ok2 = true;
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
        $sql2 = "INSERT IGNORE INTO user_notifications (user_id, hostel_issue_id, type)
                 SELECT ?, hi.id, 'hostel_resolved' FROM hostel_issues hi
                 WHERE hi.status = 'resolved' AND hi.hostel_type = (SELECT hostel_type FROM users WHERE id = ?)";
        $stmt2 = $mysqli->prepare($sql2);
        $stmt2->bind_param('ii', $user_id, $user_id);
        $ok2 = $stmt2->execute();
        $stmt2->close();
    }

    if ($ok1 && $ok2) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Hide individual hostel-wide notification (only for students)
if (isset($_POST['type'], $_POST['id']) && $_POST['type'] === 'hostel') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    $hostel_issue_id = intval($_POST['id']);
    $stmt = $mysqli->prepare("INSERT IGNORE INTO user_notifications (user_id, hostel_issue_id, type) VALUES (?, ?, 'hostel_resolved')");
    $stmt->bind_param('ii', $user_id, $hostel_issue_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
    exit;
}

// Hide individual complaint notification
if (isset($_POST['type'], $_POST['id']) && $_POST['type'] === 'complaint') {
    $token = $_POST['id'];
    $stmt = $mysqli->prepare("INSERT IGNORE INTO user_notifications (user_id, complaint_token, type) VALUES (?, ?, 'resolved')");
    $stmt->bind_param('is', $user_id, $token);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']); 