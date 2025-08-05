<?php
require_once __DIR__.'/../includes/config.php';

header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'role' => null]);
    exit;
}

$stmt = $mysqli->prepare("SELECT role FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->bind_result($role);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'role' => $role]);
} else {
    echo json_encode(['success' => false, 'role' => null]);
}
$stmt->close(); 