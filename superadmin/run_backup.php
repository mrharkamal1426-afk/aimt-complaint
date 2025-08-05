<?php
// run_backup.php - triggers Windows batch backup script and redirects back with status
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Authorize superadmin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
    exit;
}

// Determine return page (default system_monitoring)
$returnPage = basename($_GET['return'] ?? 'system_monitoring.php');
$allowedReturns = ['system_monitoring.php','system_alerts.php','security_logs.php'];
if(!in_array($returnPage,$allowedReturns)) $returnPage='system_monitoring.php';

$batPath = realpath(__DIR__ . '/../backup.bat');

if (!$batPath || !file_exists($batPath)) {
    header('Location: system_monitoring.php?backup=missing');
    exit;
}

// Escape path and execute asynchronously
$escaped = escapeshellarg($batPath);
// Using cmd /c start ensures non-blocking execution
$command = "cmd /c start \"Backup\" $escaped";
exec($command, $output, $exitCode);

// Log the action (optional)
$mysqli->query("INSERT INTO security_logs(action, details) VALUES('manual_backup', 'Triggered by superadmin ID " . (int)$_SESSION['id'] . "')");

$param = $exitCode === 0 ? 'success' : 'error';
header('Location: ' . $returnPage . '?backup=' . $param);
exit;
?>
