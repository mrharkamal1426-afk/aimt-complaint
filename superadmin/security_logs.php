<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if the user is logged in and is a superadmin
if (!is_logged_in() || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$message = '';
$message_type = '';

// Handle backup status messages
if (isset($_GET['backup'])) {
    if ($_GET['backup'] === 'success') {
        $message = 'Backup task triggered successfully.';
        $message_type = 'success';
    } elseif ($_GET['backup'] === 'error') {
        $message = 'Failed to trigger backup task.';
        $message_type = 'error';
    } elseif ($_GET['backup'] === 'missing') {
        $message = 'backup.bat not found.';
        $message_type = 'error';
    }
}

// Handle log export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filter_date_from = $_GET['date_from'] ?? '';
    $filter_date_to = $_GET['date_to'] ?? '';
    $filter_action = $_GET['action'] ?? '';
    $filter_admin = $_GET['admin'] ?? '';
    
    // Build query
    $query = "SELECT sl.*, u.full_name as admin_name, u.username as admin_username 
              FROM security_logs sl 
              LEFT JOIN users u ON sl.admin_id = u.id 
              WHERE 1=1";
    $params = [];
    $types = '';
    
    if ($filter_date_from) {
        $query .= " AND sl.created_at >= ?";
        $params[] = $filter_date_from . ' 00:00:00';
        $types .= 's';
    }
    
    if ($filter_date_to) {
        $query .= " AND sl.created_at <= ?";
        $params[] = $filter_date_to . ' 23:59:59';
        $types .= 's';
    }
    
    if ($filter_action) {
        $query .= " AND sl.action = ?";
        $params[] = $filter_action;
        $types .= 's';
    }
    
    if ($filter_admin) {
        $query .= " AND sl.admin_id = ?";
        $params[] = $filter_admin;
        $types .= 'i';
    }
    
    $query .= " ORDER BY sl.created_at DESC";
    
    $stmt = $mysqli->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="security_logs_' . date('Ymd_His') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date/Time', 'Admin', 'Username', 'Action', 'Target', 'IP Address', 'User Agent', 'Details']);
    
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['created_at'],
            $row['admin_name'],
            $row['admin_username'],
            $row['action'],
            $row['target'],
            $row['ip_address'],
            $row['user_agent'],
            $row['details']
        ]);
    }
    
    fclose($output);
    $stmt->close();
    exit();
}

// Get filter parameters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_admin = $_GET['admin'] ?? '';

// Build query for display
$query = "SELECT sl.*, u.full_name as admin_name, u.username as admin_username 
          FROM security_logs sl 
          LEFT JOIN users u ON sl.admin_id = u.id 
          WHERE 1=1";
$params = [];
$types = '';

if ($filter_date_from) {
    $query .= " AND sl.created_at >= ?";
    $params[] = $filter_date_from . ' 00:00:00';
    $types .= 's';
}

if ($filter_date_to) {
    $query .= " AND sl.created_at <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
    $types .= 's';
}

if ($filter_action) {
    $query .= " AND sl.action = ?";
    $params[] = $filter_action;
    $types .= 's';
}

if ($filter_admin) {
    $query .= " AND sl.admin_id = ?";
    $params[] = $filter_admin;
    $types .= 'i';
}

$limitRows = 100;
$viewAll = isset($_GET['view']) && $_GET['view']==='all';
if(!$viewAll){
    $query .= " ORDER BY sl.created_at DESC LIMIT $limitRows";
}else{
    $query .= " ORDER BY sl.created_at DESC";
}

$stmt = $mysqli->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get available actions for filter
$actions = [];
$action_query = "SELECT DISTINCT action FROM security_logs ORDER BY action";
$action_result = $mysqli->query($action_query);
while ($row = $action_result->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Get available admins for filter
$admins = [];
$admin_query = "SELECT DISTINCT sl.admin_id, u.full_name, u.username 
                FROM security_logs sl 
                LEFT JOIN users u ON sl.admin_id = u.id 
                WHERE sl.admin_id IS NOT NULL 
                ORDER BY u.full_name";
$admin_result = $mysqli->query($admin_query);
while ($row = $admin_result->fetch_assoc()) {
    $admins[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/lucide.min.css" rel="stylesheet">
        <!-- DataTables CSS -->
        <link rel="stylesheet" href="https://cdn.datatables.net/v/bs5/dt-1.13.6/datatables.min.css"/>
    <style>
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link:hover {
            background-color: #f1f5f9;
            transform: translateX(4px);
        }
        .logo-glow {
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }
        .institute-name {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col w-full">
            <!-- Include the new header template -->
            <?php include '../templates/superadmin_header.php'; ?>
            
            <!-- Content Container with proper spacing -->
            <div class="px-4 sm:px-6 md:px-8 py-6"><!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 sm:p-6 mb-6">
            <h2 class="text-lg font-semibold text-slate-800 mb-4">Filter Logs</h2>
            <form method="GET" class="space-y-4 sm:space-y-0 sm:grid sm:grid-cols-2 lg:grid-cols-5 sm:gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Date To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Action</label>
                    <select name="action" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= htmlspecialchars($action) ?>" <?= $filter_action === $action ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $action))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Admin</label>
                    <select name="admin" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">All Admins</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['admin_id'] ?>" <?= $filter_admin == $admin['admin_id'] ? 'selected' : '' ?>><?= htmlspecialchars($admin['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end gap-2 sm:flex-col lg:flex-row">
                    <button type="submit" class="flex-1 sm:flex-none px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">Filter</button>
                    <a href="security_logs.php" class="flex-1 sm:flex-none px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200 transition-colors text-center">Clear</a>
                </div>
            </form>
        </div>

        <!-- Quick Filters & Export -->
        <div class="mb-6 flex flex-col lg:flex-row justify-between gap-4 items-start lg:items-center">
            <!-- Quick Filters -->
            <div class="flex flex-wrap gap-2">
                <?php
                $today = date('Y-m-d');
                $last7 = date('Y-m-d', strtotime('-6 days'));
                ?>
                <a href="security_logs.php?date_from=<?= $today ?>&date_to=<?= $today ?>" class="px-3 py-1 bg-slate-200 rounded-md text-sm <?= ($filter_date_from==$today && $filter_date_to==$today)?'bg-blue-600 text-white':'' ?>">Today</a>
                <a href="security_logs.php?date_from=<?= $last7 ?>&date_to=<?= $today ?>" class="px-3 py-1 bg-slate-200 rounded-md text-sm <?= ($filter_date_from==$last7 && $filter_date_to==$today)?'bg-blue-600 text-white':'' ?>">Last 7 Days</a>
                <a href="security_logs.php?action=critical" class="px-3 py-1 bg-slate-200 rounded-md text-sm <?= ($filter_action=='critical')?'bg-blue-600 text-white':'' ?>">Critical Actions</a>
            </div>
            <h2 class="text-xl font-semibold text-slate-800">Security Audit Logs</h2>
            <a href="security_logs.php?export=csv&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>&action=<?= urlencode($filter_action) ?>&admin=<?= urlencode($filter_admin) ?>" 
               class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors flex items-center w-full sm:w-auto justify-center">
                <i class="lucide-download mr-2"></i>
                Export CSV
                </a>
                <!-- Auto-Refresh Toggle -->
                <label class="flex items-center gap-2 text-sm ml-0 lg:ml-4">
                    <input type="checkbox" id="autoRefreshToggle" class="h-4 w-4 text-blue-600 border-gray-300 rounded">
                    Auto-refresh 30s
                </label>
            </a>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table id="logsTable" class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date/Time</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Admin</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Target</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Details</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php $detailJson = json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
                                <tr class="hover:bg-slate-50" data-details='<?= htmlspecialchars($detailJson, ENT_QUOTES, "UTF-8") ?>'>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                        <div class="hidden sm:block"><?= date('M j, Y H:i:s', strtotime($row['created_at'])) ?></div>
                                        <div class="sm:hidden"><?= date('M j, H:i', strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($row['admin_name'] ?? 'System') ?></div>
                                        <div class="text-sm text-slate-500 hidden sm:block"><?= htmlspecialchars($row['admin_username'] ?? 'N/A') ?></div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?= in_array($row['action'], ['login', 'logout']) ? 'bg-blue-100 text-blue-800' : 
                                               (in_array($row['action'], ['add_admin', 'update_profile']) ? 'bg-green-100 text-green-800' : 
                                               (in_array($row['action'], ['delete_admin', 'reset_password']) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) ?>">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $row['action']))) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                        <div class="max-w-20 sm:max-w-none truncate" title="<?= htmlspecialchars($row['target'] ?? 'N/A') ?>">
                                            <?= htmlspecialchars($row['target'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-slate-500 hidden sm:table-cell">
                                        <?= htmlspecialchars($row['ip_address'] ?? 'N/A') ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-4 text-sm text-slate-900">
                                        <div class="max-w-32 sm:max-w-xs truncate" title="<?= htmlspecialchars($row['details'] ?? '') ?>">
                                            <?= htmlspecialchars($row['details'] ?? 'N/A') ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-3 sm:px-6 py-4 text-center text-sm text-slate-500">
                                    No security logs found for the selected criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if(!$viewAll && $result->num_rows==$limitRows): ?>
        <div class="mt-4 text-center">
            <a href="<?= strtok($_SERVER['REQUEST_URI'],'?') . '?' . http_build_query(array_merge($_GET,['view'=>'all'])) ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition-colors">View More</a>
        </div>
        <?php endif; ?>

        <!-- Back to Admin Management -->
        <!-- Backup Buttons -->
<div class="mt-8 flex flex-col sm:flex-row sm:items-center sm:justify-center gap-4">
    <a href="run_backup.php" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors">
        <i class="lucide-database mr-2"></i>
        Backup Now (Local)
    </a>
    
        
</div>

<div class="mt-8 text-center">
            <a href="admin_management.php" class="inline-flex items-center px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200 transition-colors w-full sm:w-auto justify-center">
                <i class="lucide-arrow-left mr-2"></i>
                Back to Admin Management
            </a>
        </div>
    </div>
<!-- Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white max-w-lg w-full rounded-lg shadow-lg p-6 relative">
        <button id="closeModal" class="absolute top-2 right-2 text-slate-500 hover:text-slate-700">&times;</button>
        <h3 class="text-lg font-semibold mb-4">Log Details</h3>
        <pre id="modalContent" class="text-xs whitespace-pre-wrap bg-slate-100 p-3 rounded-md max-h-96 overflow-y-auto"></pre>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/v/bs5/dt-1.13.6/datatables.min.js"></script>
<script>
$(document).ready(function(){
    // Initialise DataTables
    const table = $('#logsTable').DataTable({
        pageLength: 25,
        order: [[0,'desc']],
        language: { search: "Search:" }
    });

    // Row click to open modal
    $('#logsTable tbody').on('click','tr',function(){
        const details = $(this).data('details');
        if(details){
            $('#modalContent').text(details);
            $('#detailsModal').removeClass('hidden flex').addClass('flex');
        }
    });
    $('#closeModal, #detailsModal').on('click', function(e){
        if(e.target.id==='detailsModal' || e.target.id==='closeModal'){
            $('#detailsModal').addClass('hidden').removeClass('flex');
        }
    });

    // Auto-refresh toggle with localStorage
    const toggle = $('#autoRefreshToggle');
    toggle.prop('checked', localStorage.getItem('autoRefresh')==='1');
    let interval;
    function startRefresh(){ interval = setInterval(()=>location.reload(),30000); }
    function stopRefresh(){ clearInterval(interval); }
    if(toggle.prop('checked')) startRefresh();
    toggle.on('change',function(){
        if(this.checked){
            localStorage.setItem('autoRefresh','1');
            startRefresh();
        }else{
            localStorage.removeItem('autoRefresh');
            stopRefresh();
        }
    });
});
</script>
</body>
</html> 