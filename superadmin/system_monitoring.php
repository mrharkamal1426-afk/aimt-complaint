<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

// Get basic system statistics
$stats = get_assignment_statistics();

// Get technician data
$stmt = $mysqli->prepare("
    SELECT 
        specialization,
        COUNT(*) as total_technicians,
        SUM(is_online) as online_technicians
    FROM users 
    WHERE role = 'technician'
    GROUP BY specialization
");
$stmt->execute();
$result = $stmt->get_result();
$technician_data = [];
while ($row = $result->fetch_assoc()) {
    $technician_data[$row['specialization']] = $row;
}
$stmt->close();

// Get system alerts
$alerts = [];

// Check for categories with no online technicians
$stmt = $mysqli->prepare("
    SELECT DISTINCT c.category, COUNT(*) as complaint_count
    FROM complaints c
    LEFT JOIN users u ON u.specialization = c.category AND u.role = 'technician' AND u.is_online = 1
    WHERE (c.technician_id IS NULL OR c.technician_id = 0)
    AND c.status IN ('pending', 'in_progress')
    AND u.id IS NULL
    GROUP BY c.category
");
$stmt->execute();
$result = $stmt->get_result();
$no_technicians = [];
while ($row = $result->fetch_assoc()) {
    $no_technicians[] = $row['category'];
}
$stmt->close();

if (!empty($no_technicians)) {
    $alerts[] = [
        'type' => 'critical',
        'message' => "No online technicians available for categories: " . implode(', ', $no_technicians),
        'severity' => 'high'
    ];
}

// Check for overloaded technicians
$stmt = $mysqli->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.specialization,
        COUNT(c.id) as complaint_count,
        COUNT(hi.id) as hostel_issue_count
    FROM users u
    LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
    LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
    WHERE u.role = 'technician' AND u.is_online = 1
    GROUP BY u.id, u.full_name, u.specialization
    HAVING (complaint_count + hostel_issue_count) > 8
");
$stmt->execute();
$result = $stmt->get_result();
$overloaded = [];
while ($row = $result->fetch_assoc()) {
    $overloaded[] = $row;
}
$stmt->close();

// Build list of overloaded technician names with specialization for UI tooltips
$overloaded_names = array_map(function($t){
    return $t['full_name'] . ' (' . $t['specialization'] . ')';
}, $overloaded);

if (!empty($overloaded)) {
    $alerts[] = [
        'type' => 'warning',
        'message' => count($overloaded) . " technicians are overloaded (>8 total assignments)",
        'severity' => 'medium',
        'technicians' => $overloaded
    ];
}

// Calculate system health score
$total_technicians = array_sum(array_column($technician_data, 'total_technicians'));
$online_technicians = array_sum(array_column($technician_data, 'online_technicians'));
$system_health_score = 100;

// Deduct points for issues
$system_health_score -= (count($no_technicians) * 15);
$system_health_score -= (count($overloaded) * 5);

// Deduct points for low online percentage
if ($total_technicians > 0) {
    $online_percentage = ($online_technicians / $total_technicians) * 100;
    if ($online_percentage < 50) {
        $system_health_score -= (50 - $online_percentage);
    }
}

$system_health_score = max(0, $system_health_score);

// Get recent auto-assignment logs
$stmt = $mysqli->prepare("
    SELECT 
        action,
        details,
        created_at
    FROM security_logs 
    WHERE action IN ('auto_assignment', 'system_alert')
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
$recent_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// Get assignment type statistics
$stmt = $mysqli->prepare("
    SELECT 
        assignment_type,
        COUNT(*) as count
    FROM complaints 
    WHERE technician_id IS NOT NULL AND technician_id != 0
    AND status IN ('pending', 'in_progress')
    GROUP BY assignment_type
");
$stmt->execute();
$assignment_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$assignment_breakdown = [];
foreach ($assignment_stats as $stat) {
    $assignment_breakdown[$stat['assignment_type']] = $stat['count'];
}

// Fetch unassigned complaints count
$stmt = $mysqli->prepare("SELECT COUNT(*) AS cnt FROM complaints WHERE (technician_id IS NULL OR technician_id = 0) AND status IN ('pending','in_progress')");
$stmt->execute();
$unassigned_complaints = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// Performance metrics
$performance_metrics = [
    'total_technicians' => $total_technicians,
    'online_technicians' => $online_technicians,
    'overloaded_technicians' => count($overloaded),
    'unassigned_complaints' => $unassigned_complaints,
    'categories_without_technicians' => count($no_technicians),
    'system_health_score' => $system_health_score,
    'assignment_breakdown' => $assignment_breakdown
];
?>
<?php
// Show backup status message if present
$backupStatus = $_GET['backup'] ?? '';
$backupMessage = '';
if ($backupStatus === 'success') {
    $backupMessage = 'Backup completed successfully.';
} elseif ($backupStatus === 'error') {
    $backupMessage = 'Backup failed. Please check server logs.';
} elseif ($backupStatus === 'missing') {
    $backupMessage = 'Backup script not found.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .status-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .status-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .health-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .health-excellent { background-color: #10b981; }
        .health-good { background-color: #3b82f6; }
        .health-warning { background-color: #f59e0b; }
        .health-critical { background-color: #ef4444; }
        .health-unknown { background-color: #6b7280; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-900 to-blue-800 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-8">
                <div class="flex items-center space-x-4">
                    <div class="p-3 bg-blue-500/20 rounded-xl border border-blue-400/30">
                        <svg class="text-blue-400 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white">System Monitoring</h1>
                        <p class="text-blue-200 text-sm mt-1">Real-time auto-assignment system health and performance</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-blue-200 text-sm">Last Updated</p>
                        <p class="text-white font-medium" id="lastUpdated"><?= date('M d, Y H:i:s') ?></p>
                        <p class="text-blue-300 text-xs">Auto-refresh every 30s</p>
                    </div>
                    <button onclick="location.reload()" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </button>
                    <!-- Backup Now Button -->
                    <button onclick="if(confirm('Run full backup now?')){window.location='run_backup.php';}" class="inline-flex items-center px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        Backup Now
                    </button>
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                        </svg>
                        Dashboard
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Navigation Breadcrumb -->
        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-slate-600">
                <a href="dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <span class="text-slate-900 font-medium">System Monitoring</span>
            </nav>
        </div>

                    <!-- System Health Overview -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
            <div class="status-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">System Health Score</p>
                        <p class="text-2xl font-bold text-slate-900">
                            <?= $performance_metrics['system_health_score'] ?>/100
                        </p>
                    </div>
                    <div class="health-indicator <?= get_health_class($performance_metrics['system_health_score']) ?>"></div>
                </div>
            </div>

            <div class="status-card p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">Online Technicians</p>
                        <p class="text-2xl font-bold text-slate-900">
                            <?= $performance_metrics['online_technicians'] ?>/<?= $performance_metrics['total_technicians'] ?>
                        </p>
                    </div>
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <svg class="text-blue-600 w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="status-card p-6" title="<?= htmlspecialchars(implode(', ', $overloaded_names)) ?>">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">Overloaded Technicians</p>
                        <p class="text-2xl font-bold text-slate-900">
                            <?= $performance_metrics['overloaded_technicians'] ?>
                        </p>
                    </div>
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <svg class="text-yellow-600 w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

                            <div class="status-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600">Unassigned Complaints</p>
                            <p class="text-2xl font-bold text-slate-900">
                                <?= $performance_metrics['unassigned_complaints'] ?>
                            </p>
                        </div>
                        <div class="p-2 bg-red-100 rounded-lg">
                            <svg class="text-red-600 w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="status-card p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600">Assignment Types</p>
                            <div class="text-xs text-slate-500 mt-1 space-y-1">
                                <div class="flex justify-between">
                                    <span>Auto:</span>
                                    <span class="font-medium text-green-600"><?= $performance_metrics['assignment_breakdown']['auto_assigned'] ?? 0 ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Admin:</span>
                                    <span class="font-medium text-purple-600"><?= $performance_metrics['assignment_breakdown']['admin_assigned'] ?? 0 ?></span>
                                </div>
                           
                            </div>
                        </div>
                        <div class="p-2 bg-indigo-100 rounded-lg">
                            <svg class="text-indigo-600 w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                    </div>
                </div>
        </div>

        <!-- System Alerts -->
        <?php if (!empty($alerts)): ?>
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-slate-900 mb-4">System Alerts</h2>
            <div class="space-y-4">
                <?php foreach ($alerts as $alert): ?>
                <div class="status-card p-4 border-l-4 <?= $alert['severity'] === 'high' ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50' ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-3 <?= $alert['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <div>
                                <h3 class="font-medium <?= $alert['severity'] === 'high' ? 'text-red-800' : 'text-yellow-800' ?>">
                                    <?= htmlspecialchars($alert['message']) ?>
                                </h3>
                                <?php if (isset($alert['technicians'])): ?>
                                <ul class="mt-2 list-disc list-inside text-xs text-slate-700">
                                    <?php foreach ($alert['technicians'] as $tech): ?>
                                    <li><?= htmlspecialchars($tech['full_name']) ?> (<?= htmlspecialchars($tech['specialization']) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="text-xs font-medium px-2 py-1 rounded-full <?= $alert['severity'] === 'high' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800' ?>">
                            <?= ucfirst($alert['severity']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Technician Status by Category -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="status-card">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Technician Status by Category</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($technician_data)): ?>
                    <p class="text-slate-500 text-center py-4">No technician data available</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($technician_data as $category => $data): ?>
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                            <div>
                                <h4 class="font-medium text-slate-900"><?= ucfirst($category) ?></h4>
                                <p class="text-sm text-slate-600">
                                    <?= $data['online_technicians'] ?> online / <?= $data['total_technicians'] ?> total
                                </p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-slate-900">
                                    <?= round(($data['online_technicians'] / max($data['total_technicians'], 1)) * 100) ?>%
                                </div>
                                <div class="w-16 h-2 bg-slate-200 rounded-full mt-1">
                                    <div class="h-2 bg-blue-500 rounded-full" style="width: <?= ($data['online_technicians'] / max($data['total_technicians'], 1)) * 100 ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent System Logs -->
            <div class="status-card">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Recent System Logs</h3>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_logs)): ?>
                    <p class="text-slate-500 text-center py-4">No recent logs available</p>
                    <?php else: ?>
                    <?php
// Helper to summarize log details
function summarize_log_details($action, $details)
{
    $data = json_decode($details, true);
    if (is_array($data)) {
        if ($action === 'auto_assignment') {
            $parts = [];
            if (isset($data['token'])) $parts[] = 'token ' . $data['token'];
            if (isset($data['technician_id'])) $parts[] = 'tech ' . $data['technician_id'];
            if (isset($data['category'])) $parts[] = $data['category'];
            if (isset($data['assignment_method'])) $parts[] = $data['assignment_method'];
            return 'Auto-assigned ' . implode(', ', $parts);
        }
        if (isset($data['message'])) {
            return $data['message'];
        }
    }
    return mb_strimwidth($details, 0, 80, '…');
}
?>
<div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach ($recent_logs as $log): ?>
                        <div class="flex items-start space-x-3 p-3 bg-slate-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-900">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))) ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                                </p>
                                <?php if ($log['details']): ?>
                                <?php $summary = summarize_log_details($log['action'], $log['details']); ?>
                                <?php if ($summary): ?>
                                <p class="text-xs text-slate-600 mt-1">
                                    <?= htmlspecialchars($summary) ?>
                                </p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>


    </div>

    <!-- Smart Auto-Assignment Status -->
    <div class="status-card mb-8">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">Smart Auto-Assignment System</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Status</h4>
                    <div class="flex items-center">
                        <div class="w-3 h-3 rounded-full bg-green-400 mr-2" id="smart-status-indicator"></div>
                        <span class="text-sm font-medium" id="smart-status-text">Active</span>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Last Run</h4>
                    <p class="text-sm text-slate-600" id="smart-last-run">Checking...</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Schedule</h4>
                    <p class="text-sm text-slate-600">Every 5 minutes (Automatic)</p>
                </div>
            </div>
            
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h4 class="font-medium text-green-900 mb-2">✅ Smart Auto-Assignment Active</h4>
                <div class="text-sm text-green-800 space-y-2">
                    <p><strong>Fully Automated:</strong> The system automatically validates and assigns complaints every 5 minutes.</p>
                    <p><strong>Smart Validation:</strong> Checks technician availability, specialization, and online status.</p>
                    <p><strong>Automatic Reassignment:</strong> Reassigns complaints when technicians go offline or are deleted.</p>
                    <p><strong>Real-time Monitoring:</strong> Provides live status updates and health metrics.</p>
                </div>
            </div>
            
            <div class="mt-6">
                <button onclick="smartAutoRunner.runNow()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Run Now
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/smart-auto-assignment.js"></script>
    <script>
        // Update timestamp every second
        setInterval(function() {
            const now = new Date();
            const timeString = now.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) + ' ' + now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit',
                hour12: false 
            });
            document.getElementById('lastUpdated').textContent = timeString;
        }, 1000);
        
        // Update smart auto-assignment status
        setInterval(function() {
            if (window.smartAutoRunner) {
                const status = smartAutoRunner.getStatus();
                const statusText = document.getElementById('smart-status-text');
                const lastRun = document.getElementById('smart-last-run');
                const indicator = document.getElementById('smart-status-indicator');
                
                if (statusText && lastRun && indicator) {
                    statusText.textContent = status.isRunning ? 'Running...' : 'Active';
                    lastRun.textContent = status.lastRun ? status.lastRun.toLocaleString() : 'Never';
                    
                    if (status.isRunning) {
                        indicator.className = 'w-3 h-3 rounded-full bg-yellow-400 animate-pulse mr-2';
                    } else {
                        indicator.className = 'w-3 h-3 rounded-full bg-green-400 mr-2';
                    }
                }
            }
        }, 1000);
    </script>
</body>
</html>

<?php
function get_health_class($score) {
    if ($score >= 80) return 'health-excellent';
    if ($score >= 60) return 'health-good';
    if ($score >= 40) return 'health-warning';
    if ($score >= 0) return 'health-critical';
    return 'health-unknown';
}


?> 