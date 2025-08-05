<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}
$filter = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? '',
    'role' => $_GET['role'] ?? '',
    'technician' => $_GET['technician'] ?? ''
];
$query = "SELECT c.token, u.full_name AS user_name, u.role AS user_role, c.category, c.status, t.full_name AS tech_name, c.created_at, c.updated_at FROM complaints c JOIN users u ON u.id = c.user_id LEFT JOIN users t ON t.id = c.technician_id WHERE 1=1";
$params = [];
$types = '';
if ($filter['date_from']) {
    $query .= " AND c.created_at >= ?";
    $params[] = $filter['date_from'];
    $types .= 's';
}
if ($filter['date_to']) {
    $query .= " AND c.created_at <= ?";
    $params[] = $filter['date_to'];
    $types .= 's';
}
if ($filter['category']) {
    $query .= " AND c.category = ?";
    $params[] = $filter['category'];
    $types .= 's';
}
if ($filter['status']) {
    $query .= " AND c.status = ?";
    $params[] = $filter['status'];
    $types .= 's';
}
if ($filter['role']) {
    $query .= " AND u.role = ?";
    $params[] = $filter['role'];
    $types .= 's';
}
if ($filter['technician']) {
    $query .= " AND t.id = ?";
    $params[] = $filter['technician'];
    $types .= 'i';
}
$query .= " ORDER BY c.created_at DESC";
$stmt = $mysqli->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename=complaints_report_'.date('Ymd_His').'.csv');
$output = fopen('php://output', 'w');
fputcsv($output, ['Token', 'User Name', 'User Role', 'Category', 'Status', 'Technician', 'Created At', 'Updated At']);
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['token'],
        $row['user_name'],
        $row['user_role'],
        $row['category'],
        $row['status'],
        $row['tech_name'] ?? 'Not assigned',
        $row['created_at'],
        $row['updated_at']
    ]);
}
fclose($output);
$stmt->close();
exit; 