<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

// Get enhanced assignment statistics with alerts
$enhanced_stats = get_enhanced_assignment_statistics();
$system_alerts = $enhanced_stats['enhanced']['system_health'] ?? [];

// Check for critical alerts that need immediate attention
$critical_alerts = array_filter($system_alerts, function($alert) {
    return $alert['severity'] === 'high';
});

$warning_alerts = array_filter($system_alerts, function($alert) {
    return $alert['severity'] === 'medium';
});

// Get recent security logs for system alerts
$stmt = $mysqli->prepare("
    SELECT * FROM security_logs 
    WHERE action IN ('system_alert', 'technician_deleted', 'auto_assignment_failed')
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute();
$result = $stmt->get_result();
$recent_alerts = [];
while ($row = $result->fetch_assoc()) {
    $recent_alerts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Alerts - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        @keyframes hamburger-pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.3); }
            70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        
        .animate-hamburger-pulse {
            animation: hamburger-pulse 2s infinite;
        }
        .alert-card {
            @apply bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-all duration-200;
        }
        .stat-card {
            @apply bg-gradient-to-br from-white to-slate-50 rounded-xl shadow-sm border border-slate-200;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-slate-900 to-slate-800 shadow-lg">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-8">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-red-500/20 rounded-xl border border-red-400/30">
                            <svg class="text-red-400 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white">System Alerts & Monitoring</h1>
                            <p class="text-red-200 text-sm mt-1 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Real-time system health monitoring and alert management
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <p class="text-slate-300 text-sm">Last Updated</p>
                            <p class="text-white font-medium"><?= date('M d, Y H:i') ?></p>
                        </div>
                        <div class="p-2 bg-white/10 rounded-lg border border-white/20">
                            <svg class="text-white w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <a href="dashboard.php" class="inline-flex items-center px-3 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-100 rounded-lg text-sm font-medium transition-all duration-300 backdrop-blur-sm border border-red-400/30 hover:border-red-300/50 hover:scale-105 hover:shadow-lg transform animate-hamburger-pulse">
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

        <?php
// Backup status flash message
if(isset($_GET['backup'])){
    $msg='';$color='';
    if($_GET['backup']=='success'){ $msg='Backup task triggered successfully.'; $color='green';}
    elseif($_GET['backup']=='error'){ $msg='Failed to trigger backup task.'; $color='red';}
    elseif($_GET['backup']=='missing'){ $msg='backup.bat not found.'; $color='red';}
    if($msg){
        echo "<div class='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6'><div class='bg-$color-100 border border-$color-300 text-$color-800 px-4 py-3 rounded relative' role='alert'>$msg</div></div>";
    }
}
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
<!-- Backup Buttons -->
<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-end gap-4">
    <a href="run_backup.php?return=system_alerts.php" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors">
        <i class="lucide-database mr-2"></i>
        Backup Now (Local)
    </a>
</div>
            <!-- Alert Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card p-6 border-2 border-red-200 bg-gradient-to-br from-red-50 to-red-100/50">
                    <div class="flex items-center">
                        <div class="p-4 bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg">
                            <svg class="text-white w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-slate-600 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                Critical Alerts
                            </p>
                            <p class="text-3xl font-bold text-red-700"><?= count($critical_alerts) ?></p>
                            <p class="text-xs text-red-600 mt-1">Requires immediate attention</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 border-yellow-200 bg-gradient-to-br from-yellow-50 to-yellow-100/50">
                    <div class="flex items-center">
                        <div class="p-4 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg">
                            <svg class="text-white w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM4.19 4.19C4.74 3.63 5.5 3.3 6.3 3.3c.8 0 1.56.33 2.11.89L12 9l3.59-4.81c.55-.56 1.31-.89 2.11-.89.8 0 1.56.33 2.11.89C20.37 4.74 20.7 5.5 20.7 6.3c0 .8-.33 1.56-.89 2.11L16 12l3.81 3.59c.56.55.89 1.31.89 2.11 0 .8-.33 1.56-.89 2.11C19.26 20.37 18.5 20.7 17.7 20.7c-.8 0-1.56-.33-2.11-.89L12 15l-3.59 4.81c-.55.56-1.31.89-2.11.89-.8 0-1.56-.33-2.11-.89C3.63 19.26 3.3 18.5 3.3 17.7c0-.8.33-1.56.89-2.11L8 12l-3.81-3.59C3.63 7.86 3.3 7.1 3.3 6.3c0-.8.33-1.56.89-2.11z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-slate-600 flex items-center">
                                <svg class="w-4 h-4 mr-1 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Warnings
                            </p>
                            <p class="text-3xl font-bold text-yellow-700"><?= count($warning_alerts) ?></p>
                            <p class="text-xs text-yellow-600 mt-1">Monitor closely</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 <?= empty($system_alerts) ? 'border-green-200 bg-gradient-to-br from-green-50 to-green-100/50' : (count($critical_alerts) > 0 ? 'border-red-200 bg-gradient-to-br from-red-50 to-red-100/50' : 'border-yellow-200 bg-gradient-to-br from-yellow-50 to-yellow-100/50') ?>">
                    <div class="flex items-center">
                        <div class="p-4 <?= empty($system_alerts) ? 'bg-gradient-to-br from-green-500 to-green-600' : (count($critical_alerts) > 0 ? 'bg-gradient-to-br from-red-500 to-red-600' : 'bg-gradient-to-br from-yellow-500 to-yellow-600') ?> rounded-xl shadow-lg">
                            <?php if (empty($system_alerts)): ?>
                            <svg class="text-white w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?php elseif (count($critical_alerts) > 0): ?>
                            <svg class="text-white w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <?php else: ?>
                            <svg class="text-white w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-slate-600 flex items-center">
                                <svg class="w-4 h-4 mr-1 <?= empty($system_alerts) ? 'text-green-500' : (count($critical_alerts) > 0 ? 'text-red-500' : 'text-yellow-500') ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                System Status
                            </p>
                            <p class="text-3xl font-bold <?= empty($system_alerts) ? 'text-green-700' : (count($critical_alerts) > 0 ? 'text-red-700' : 'text-yellow-700') ?>">
                                <?= empty($system_alerts) ? 'Healthy' : (count($critical_alerts) > 0 ? 'Critical' : 'Warning') ?>
                            </p>
                            <p class="text-xs <?= empty($system_alerts) ? 'text-green-600' : (count($critical_alerts) > 0 ? 'text-red-600' : 'text-yellow-600') ?> mt-1">
                                <?= empty($system_alerts) ? 'All systems operational' : (count($critical_alerts) > 0 ? 'Immediate action required' : 'Attention needed') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Alerts -->
            <?php if (!empty($critical_alerts) || !empty($warning_alerts)): ?>
            <div class="alert-card mb-8">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">Current System Alerts</h2>
                    <p class="text-sm text-slate-600">Active alerts that require attention</p>
                </div>
                
                <div class="p-6 space-y-4">
                    <?php if (!empty($critical_alerts)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <svg class="text-red-600 w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-red-800">Critical Alerts</h3>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($critical_alerts as $alert): ?>
                            <div class="bg-red-100 border border-red-300 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($alert['message']) ?></p>
                                        <?php if (isset($alert['details']) && !empty($alert['details'])): ?>
                                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($alert['details']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <a href="auto_assignment.php" class="inline-flex items-center px-3 py-1 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                            Manage
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($warning_alerts)): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <svg class="text-yellow-600 w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="text-lg font-semibold text-yellow-800">System Warnings</h3>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($warning_alerts as $alert): ?>
                            <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-yellow-800"><?= htmlspecialchars($alert['message']) ?></p>
                                        <?php if (isset($alert['details']) && !empty($alert['details'])): ?>
                                        <p class="text-xs text-yellow-600 mt-1"><?= htmlspecialchars($alert['details']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <a href="auto_assignment.php" class="inline-flex items-center px-3 py-1 border border-yellow-300 text-sm font-medium rounded-md text-yellow-700 bg-white hover:bg-yellow-50">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Alert History -->
            <div class="alert-card">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-lg font-semibold text-slate-900">Recent Alert History</h2>
                    <p class="text-sm text-slate-600">System events and alerts from the last 50 actions</p>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Target</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP Address</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php if (!empty($recent_alerts)): ?>
                                <?php foreach ($recent_alerts as $alert): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?= date('M d, Y H:i', strtotime($alert['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?= $alert['action'] === 'system_alert' ? 'bg-red-100 text-red-800' : 
                                               ($alert['action'] === 'technician_deleted' ? 'bg-orange-100 text-orange-800' : 'bg-blue-100 text-blue-800') ?>">
                                            <?= ucfirst(str_replace('_', ' ', $alert['action'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?= htmlspecialchars($alert['target']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <div class="max-w-xs truncate" title="<?= htmlspecialchars($alert['details']) ?>">
                                            <?= htmlspecialchars($alert['details']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                        <?= htmlspecialchars($alert['ip_address'] ?? 'N/A') ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                                                         <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                         <div class="flex flex-col items-center">
                                             <svg class="w-12 h-12 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                             </svg>
                                             <p class="text-sm">No recent alerts found</p>
                                             <p class="text-xs text-slate-400 mt-1">System activity will appear here</p>
                                         </div>
                                     </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh page every 5 minutes to check for new alerts
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 minutes
    </script>
</body>
</html> 