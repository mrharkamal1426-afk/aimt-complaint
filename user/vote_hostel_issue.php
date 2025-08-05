<?php
require_once __DIR__.'/../includes/config.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Only students can vote on hostel issues
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$issue_id = $_POST['issue_id'] ?? null;
$hostel_type = $_POST['hostel_type'] ?? null;

if (!$issue_id || !$hostel_type) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Prevent double voting
$stmt = $mysqli->prepare("SELECT 1 FROM hostel_issue_votes WHERE user_id = ? AND issue_id = ?");
$stmt->bind_param('ii', $user_id, $issue_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already voted']);
    exit;
}
$stmt->close();

// Insert vote
$stmt = $mysqli->prepare("INSERT INTO hostel_issue_votes (user_id, issue_id) VALUES (?, ?)");
$stmt->bind_param('ii', $user_id, $issue_id);
if (!$stmt->execute()) {
    $errorMsg = $mysqli->error ?: 'Failed to record vote';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode(['success' => true]); 