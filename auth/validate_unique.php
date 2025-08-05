<?php
require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json');

$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$allowed = ['username', 'phone'];

if (!in_array($field, $allowed) || !$value) {
    echo json_encode(['unique' => false]);
    exit;
}

$stmt = $mysqli->prepare("SELECT id FROM users WHERE $field = ?");
$stmt->bind_param('s', $value);
$stmt->execute();
$stmt->store_result();
$is_unique = $stmt->num_rows === 0;
$stmt->close();

echo json_encode(['unique' => $is_unique]); 