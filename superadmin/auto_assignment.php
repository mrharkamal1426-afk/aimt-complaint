<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$message = '';
$message_type = '';

// Handle auto-assignment action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'auto_assign_all') {
        // Use smart auto-assignment engine with admin_assigned type
        require_once __DIR__ . '/../includes/auto_assignment_engine.php';
        $engine = new SmartAutoAssignmentEngine($mysqli);
        $result = $engine->runSmartAutoAssignment();
        
        // Mark all assignments as admin_assigned since admin triggered it
        if ($result['status'] === 'success' && isset($result['assignments']['assigned']) && $result['assignments']['assigned'] > 0) {
            $stmt = $mysqli->prepare("
                UPDATE complaints 
                SET assignment_type = 'admin_assigned', updated_at = NOW() 
                WHERE technician_id IS NOT NULL 
                AND technician_id != 0 
                AND assignment_type = 'auto_assigned'
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $stmt->execute();
            $stmt->close();
        }
        
        $complaint_result = $result['assignments'] ?? ['assigned_count' => 0, 'failed_count' => 0];
        $hostel_result = $result['hostel_assignments'] ?? ['assigned_count' => 0, 'failed_count' => 0];
        
        $message_parts = [];
        $message_parts[] = "Auto-assignment completed!";
        
        if ($complaint_result['total_unassigned'] > 0) {
            $message_parts[] = "Complaints: {$complaint_result['assigned_count']} assigned, {$complaint_result['failed_count']} failed.";
        }
        
        if ($hostel_result['total_unassigned'] > 0) {
            $message_parts[] = "Hostel issues: {$hostel_result['assigned_count']} assigned, {$hostel_result['failed_count']} failed.";
        }
        
        $message = implode(' ', $message_parts);
        $message_type = ($complaint_result['failed_count'] > 0 || $hostel_result['failed_count'] > 0) ? 'warning' : 'success';
        
        // Store detailed results for display
        $_SESSION['auto_assignment_results'] = [
            'complaints' => $complaint_result,
            'hostel_issues' => $hostel_result,
            'failed_complaints' => $complaint_result['failed_complaints'] ?? [],
            'failed_hostel_issues' => $hostel_result['failed_hostel_issues'] ?? []
        ];
        
    } elseif ($_POST['action'] === 'validate_assignments') {
        $complaint_result = validate_technician_assignments();
        $hostel_result = validate_hostel_issue_assignments();
        
        $message_parts = [];
        $message_parts[] = "Assignment validation completed!";
        
        if ($complaint_result['orphaned_complaints'] > 0) {
            $message_parts[] = "Complaints: {$complaint_result['fixed_count']} fixed out of {$complaint_result['orphaned_complaints']} orphaned.";
        }
        
        if ($hostel_result['orphaned_hostel_issues'] > 0) {
            $message_parts[] = "Hostel issues: {$hostel_result['fixed_count']} fixed out of {$hostel_result['orphaned_hostel_issues']} orphaned.";
        }
        
        if (empty($message_parts)) {
            $message_parts[] = "No orphaned assignments found.";
        }
        
        $message = implode(' ', $message_parts);
        $message_type = ($complaint_result['orphaned_complaints'] > 0 || $hostel_result['orphaned_hostel_issues'] > 0) ? 'warning' : 'success';
    }
}

// Get enhanced assignment statistics with alerts
$enhanced_stats = get_enhanced_assignment_statistics();
$stats = $enhanced_stats;

// Get enhanced system health monitoring
$enhanced_health = enhanced_monitor_assignment_system_health();
$system_alerts = $enhanced_health['alerts'] ?? [];
$performance_metrics = $enhanced_health['performance_metrics'] ?? [];



$system_alerts = $enhanced_stats['enhanced']['system_health'] ?? [];

// Check if there are any online technicians available
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as online_technicians
    FROM users 
    WHERE role = 'technician' 
    AND is_online = 1
");
$stmt->execute();
$result = $stmt->get_result();
$online_technicians = $result->fetch_assoc()['online_technicians'];
$stmt->close();

// Always show truly unassigned complaints, regardless of technician online status
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as truly_unassigned 
    FROM complaints 
    WHERE (technician_id IS NULL OR technician_id = 0) 
    AND status IN ('pending', 'in_progress')
");
$stmt->execute();
$result = $stmt->get_result();
$truly_unassigned = $result->fetch_assoc()['truly_unassigned'];
$stmt->close();

// Also check for complaints that might have been auto-assigned but still show as unassigned
$stmt = $mysqli->prepare("
    SELECT COUNT(*) as auto_assigned_but_showing_unassigned
    FROM complaints c
    LEFT JOIN users u ON c.technician_id = u.id
    WHERE c.technician_id IS NOT NULL 
    AND c.technician_id != 0
    AND c.status IN ('pending', 'in_progress')
    AND u.id IS NULL
");
$stmt->execute();
$result = $stmt->get_result();
$auto_assigned_but_showing_unassigned = $result->fetch_assoc()['auto_assigned_but_showing_unassigned'];
$stmt->close();

// Total truly unassigned = unassigned + auto-assigned but technician deleted
$truly_unassigned = $truly_unassigned + $auto_assigned_but_showing_unassigned;

// Update the unassigned count to show only truly unassigned complaints
if (isset($stats['unassigned_count'])) {
    $stats['unassigned_count'] = $truly_unassigned;
}

// Get orphaned complaints
$orphaned_complaints = get_orphaned_complaints();

// Check for critical alerts that need immediate attention
$critical_alerts = array_filter($system_alerts, function($alert) {
    return $alert['severity'] === 'high';
});

$warning_alerts = array_filter($system_alerts, function($alert) {
    return $alert['severity'] === 'medium';
});

// Check if there are failed assignments
$has_failed = isset($_SESSION['auto_assignment_results']) && 
    ((isset($_SESSION['auto_assignment_results']['failed_complaints']) && count($_SESSION['auto_assignment_results']['failed_complaints']) > 0) || 
     (isset($_SESSION['auto_assignment_results']['failed_hostel_issues']) && count($_SESSION['auto_assignment_results']['failed_hostel_issues']) > 0));

// Smart auto-assignment trigger: If technicians are available, suggest auto-assignment
$should_suggest_auto_assignment = ($online_technicians > 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Assignment Management - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .action-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .action-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .stat-card {
            background: linear-gradient(135deg, white 0%, #f8fafc 100%);
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: #eff6ff;
            color: #1e40af;
            border: 4px solid #2563eb;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #dbeafe;
            border-color: #1d4ed8;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: #fff7ed;
            color: #c2410c;
            border: 4px solid #f97316;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background-color: #fed7aa;
            border-color: #ea580c;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .btn-danger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: #fef2f2;
            color: #dc2626;
            border: 2px solid #dc2626;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-danger:hover {
            background-color: #fee2e2;
            border-color: #b91c1c;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .btn-info {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: #faf5ff;
            color: #7c3aed;
            border: 2px solid #7c3aed;
            padding: 0.75rem 1.75rem;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-info:hover {
            background-color: #f3e8ff;
            border-color: #6d28d9;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-online {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-offline {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .failed-details {
            display: none;
        }
        .failed-details.show {
            display: block;
        }
        .lucide {
            width: 1.25rem;
            height: 1.25rem;
        }
        .lucide-w-5 {
            width: 1.25rem;
        }
        .lucide-h-5 {
            height: 1.25rem;
        }
        .lucide-w-6 {
            width: 1.5rem;
        }
        .lucide-h-6 {
            height: 1.5rem;
        }
        .lucide-w-8 {
            width: 2rem;
        }
        .lucide-h-8 {
            height: 2rem;
        }
        

        
        /* Hover effects */
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Gradient backgrounds */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        /* Professional shadows */
        .shadow-professional {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .shadow-professional-hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
        <!-- Header -->
        <header class="bg-gradient-to-r from-blue-900 to-blue-800 shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 bg-black opacity-10"></div>
            <div class="relative z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-8">
                    <div class="flex items-center space-x-4">
                        <div class="p-3 bg-blue-500/20 rounded-xl border border-blue-400/30">
                            <svg class="text-blue-400 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-white">Auto Assignment Management</h1>
                            <p class="text-blue-200 text-sm mt-1 flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Intelligent complaint assignment and technician workload management
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-right">
                            <p class="text-blue-200 text-sm">Last Updated</p>
                            <p class="text-white font-medium" id="lastUpdated"><?= date('M d, Y H:i') ?></p>
                            <p class="text-blue-300 text-xs">Auto-refresh every 30s</p>
                        </div>
                        <div class="p-2 bg-white/10 rounded-lg border border-white/20">
                            <svg class="text-white w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <button onclick="location.reload()" class="inline-flex items-center px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-all duration-200 shadow-professional hover:shadow-professional-hover border border-blue-500 hover:border-blue-600 mr-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                        <a href="../superadmin/dashboard.php" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-all duration-200 shadow-professional hover:shadow-professional-hover border border-red-500 hover:border-red-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h4a2 2 0 012 2v2H8V5z"></path>
                            </svg>
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>
            </div>
        </header>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Navigation Breadcrumb -->
            <div class="mb-6">
                <nav class="flex items-center space-x-2 text-sm text-slate-600">
                    <a href="../superadmin/dashboard.php" class="hover:text-blue-600 transition-colors">Dashboard</a>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="text-slate-900 font-medium">Auto Assignment Management</span>
                </nav>
            </div>

            <!-- System Health Alerts -->
            <?php if (!empty($critical_alerts) || !empty($warning_alerts)): ?>
            <div class="mb-6">
                <?php if (!empty($critical_alerts)): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <svg class="text-red-600 w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-red-800">Critical System Issues</h3>
                    </div>
                    <div class="mt-2 space-y-2">
                        <?php foreach ($critical_alerts as $alert): ?>
                        <div class="bg-red-100 border border-red-300 rounded-lg p-3">
                            <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($alert['message']) ?></p>
                            <?php if (isset($alert['categories'])): ?>
                            <p class="text-xs text-red-600 mt-1">Categories affected: <?= htmlspecialchars($alert['categories']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($warning_alerts)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <svg class="text-yellow-600 w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-yellow-800">System Warnings</h3>
                    </div>
                    <div class="mt-2 space-y-2">
                        <?php foreach ($warning_alerts as $alert): ?>
                        <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-3">
                            <p class="text-sm font-medium text-yellow-800"><?= htmlspecialchars($alert['message']) ?></p>
                            <?php if (isset($alert['technicians'])): ?>
                            <div class="mt-2">
                                <p class="text-xs text-yellow-600 font-medium">Overloaded Technicians:</p>
                                <div class="flex flex-wrap gap-2 mt-1">
                                    <?php foreach ($alert['technicians'] as $tech): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-200 text-yellow-800">
                                        <?= htmlspecialchars($tech['full_name']) ?> (<?= $tech['complaint_count'] + $tech['hostel_issue_count'] ?>)
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div id="notification" class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-yellow-50 border border-yellow-200 text-yellow-800' ?> transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $message_type === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z' ?>"></path>
                            </svg>
                            <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                        </div>
                        <button onclick="hideNotification()" class="text-slate-400 hover:text-slate-600 ml-4">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Smart Auto-Assignment Suggestion -->
            <?php if ($online_technicians > 0 && !isset($_SESSION['auto_assignment_results'])): ?>
            <div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <span class="font-medium">Smart Suggestion: <?= $online_technicians ?> technician(s) available. Run auto-assignment to assign any pending complaints.</span>
                    </div>
                    <form method="POST" class="ml-4">
                        <input type="hidden" name="action" value="auto_assign_all">
                        <button type="submit" class="btn-primary text-sm px-4 py-2">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Auto-Assign Now
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- No Technicians Available Message -->
            <?php if ($online_technicians == 0): ?>
            <div class="mb-6 p-6 rounded-lg bg-slate-50 border border-slate-200 text-slate-700">
                <div class="flex items-center justify-center">
                    <div class="p-3 bg-slate-100 rounded-full mr-4">
                        <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="text-center">
                        <h3 class="text-lg font-semibold text-slate-800">No Technicians Available</h3>
                        <p class="text-slate-600 mt-1">Complaints are waiting for technicians to come online. Once technicians are available, complaints will be automatically assigned.</p>
                        <?php if ($truly_unassigned > 0): ?>
                        <p class="text-sm text-slate-500 mt-2"><?= $truly_unassigned ?> complaint(s) currently waiting for assignment.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Enhanced Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card p-6 border-2 border-blue-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Total Complaints</p>
                                <p class="text-2xl font-bold text-slate-900"><?= $stats['total_complaints'] ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 border-green-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Assigned</p>
                                <p class="text-2xl font-bold text-slate-900"><?= $stats['assigned_count'] ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 border-yellow-200 cursor-pointer hover:shadow-lg transition-all duration-300" id="unassignedKpiBox">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Unassigned</p>
                                <p class="text-2xl font-bold text-slate-900"><?= $truly_unassigned ?></p>
                                <?php if ($online_technicians > 0): ?>
                                    <p class="text-xs text-green-600 mt-1 font-medium">✓ <?= $online_technicians ?> technician(s) available - Auto-assignment active</p>
                                <?php else: ?>
                                    <p class="text-xs text-red-600 mt-1 font-medium">⚠ No technicians online - Complaints waiting</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 <?= !empty($orphaned_complaints) ? 'border-red-200' : 'border-purple-200' ?> hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br <?= !empty($orphaned_complaints) ? 'from-red-500 to-red-600' : 'from-purple-500 to-purple-600' ?> rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Active Technicians</p>
                                <p class="text-2xl font-bold text-slate-900"><?= $stats['total_technicians'] ?></p>
                                <?php if (!empty($orphaned_complaints)): ?>
                                <p class="text-xs text-red-600 mt-1 font-medium">⚠️ <?= count($orphaned_complaints) ?> orphaned</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 <?= !empty($orphaned_complaints) ? 'bg-red-500 animate-pulse' : 'bg-purple-500' ?> rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced System Metrics -->
            <?php if (isset($enhanced_stats['enhanced'])): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="stat-card p-6 border-2 border-orange-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Average Wait Time</p>
                                <p class="text-2xl font-bold text-slate-900">
                                    <?= isset($enhanced_stats['enhanced']['wait_times']['avg_wait_time']) ? round($enhanced_stats['enhanced']['wait_times']['avg_wait_time']) . 'h' : 'N/A' ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 border-red-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">Max Wait Time</p>
                                <p class="text-2xl font-bold text-slate-900">
                                    <?= isset($enhanced_stats['enhanced']['wait_times']['max_wait_time']) ? round($enhanced_stats['enhanced']['wait_times']['max_wait_time']) . 'h' : 'N/A' ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card p-6 border-2 border-indigo-200 hover:shadow-lg transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="p-3 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg">
                                <svg class="text-white w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-slate-600">System Health</p>
                                <p class="text-2xl font-bold text-slate-900">
                                    <?= empty($system_alerts) ? 'Good' : (count($critical_alerts) > 0 ? 'Critical' : 'Warning') ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="w-3 h-3 <?= empty($system_alerts) ? 'bg-green-500' : (count($critical_alerts) > 0 ? 'bg-red-500 animate-pulse' : 'bg-yellow-500') ?> rounded-full"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Unassigned Complaints Section (Hidden by default) -->
            <?php if ($truly_unassigned > 0): ?>
            <div id="unassignedTable" class="action-card mb-8" style="display: none;">
                <div class="px-6 py-4 border-b border-slate-200">
                        <div class="flex items-center justify-between">
                            <div>
                            <h3 class="text-lg font-semibold text-slate-900">Unassigned Complaints</h3>
                            <p class="text-sm text-slate-600"><?= $truly_unassigned ?> complaints waiting to be assigned to technicians</p>
                        </div>
                        <div class="flex items-center">
                            <div class="p-2 bg-yellow-100 rounded-lg mr-3">
                                <i class="lucide-clock text-yellow-600 w-5 h-5"></i>
                            </div>
                            <button onclick="toggleUnassignedTable()" class="text-slate-400 hover:text-slate-600">
                                <i class="lucide-x w-5 h-5"></i>
                                </button>
                    </div>
                </div>
            </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Token</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Room</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php 
                            // Get all unassigned complaints
                            $unassigned_complaints = [];
                            if ($truly_unassigned > 0) {
                                // If no technicians are online, show unassigned complaints
                                $stmt = $mysqli->prepare("
                                    SELECT token, category, room_no, description, status, created_at 
                                    FROM complaints 
                                    WHERE (technician_id IS NULL OR technician_id = 0) 
                                    AND status IN ('pending', 'in_progress')
                                    ORDER BY created_at DESC
                                ");
                                $stmt->execute();
                                $unassigned_complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            }
                            
                            // Also get complaints that were auto-assigned but technician was deleted
                            $stmt2 = $mysqli->prepare("
                                SELECT c.token, c.category, c.room_no, c.description, c.status, c.created_at 
                                FROM complaints c
                                LEFT JOIN users u ON c.technician_id = u.id
                                WHERE c.technician_id IS NOT NULL 
                                AND c.technician_id != 0
                                AND c.status IN ('pending', 'in_progress')
                                AND u.id IS NULL
                                ORDER BY c.created_at DESC
                            ");
                            $stmt2->execute();
                            $orphaned_auto_assigned = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt2->close();
                            
                            // Combine both arrays
                            $unassigned_complaints = array_merge($unassigned_complaints, $orphaned_auto_assigned);
                            ?>
                            <?php foreach ($unassigned_complaints as $complaint): ?>
                            <tr class="hover:bg-yellow-50/30">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($complaint['token']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars(ucfirst($complaint['category'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= htmlspecialchars($complaint['room_no']) ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-slate-600 max-w-xs truncate" title="<?= htmlspecialchars($complaint['description']) ?>">
                                        <?= htmlspecialchars($complaint['description']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <?= ucfirst(str_replace('_', ' ', $complaint['status'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= date('M d, Y', strtotime($complaint['created_at'])) ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Main Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <!-- Auto Assignment Card -->
                <div class="bg-white rounded-xl shadow-lg p-8 flex flex-col items-center hover:shadow-xl transition-all duration-300 border border-slate-100">
                    <div class="p-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full shadow-lg mb-4">
                        <svg class="text-white w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold mb-3 text-blue-700 text-center">Auto Assignment</h2>
                    <p class="text-slate-600 mb-6 text-center leading-relaxed">
                        Automatically assign unassigned complaints to available technicians based on specialization and workload optimization.
                    </p>
                    <div class="text-xs text-slate-500 mb-6 text-center bg-slate-50 p-3 rounded-lg">
                        <strong>Smart Features:</strong> Online technician filtering, specialization matching, workload balancing, and hostel issue handling.
                    </div>
                    <form method="POST" class="w-full flex justify-center">
                        <input type="hidden" name="action" value="auto_assign_all">
                        <button type="submit" 
                            class="btn-primary w-full md:w-auto">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>Run Auto Assignment</span>
                        </button>
                    </form>
                </div>

                <!-- Validate Assignments Card -->
                <div class="bg-white rounded-xl shadow-lg p-8 flex flex-col items-center hover:shadow-xl transition-all duration-300 border border-slate-100">
                    <div class="p-4 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full shadow-lg mb-4">
                        <svg class="text-white w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold mb-3 text-orange-700 text-center">Validate Assignments</h2>
                    <p class="text-slate-600 mb-6 text-center leading-relaxed">
                        Check and fix complaints assigned to deleted technicians. Ensures system integrity and proper complaint routing.
                    </p>
                    <div class="text-xs text-slate-500 mb-6 text-center bg-slate-50 p-3 rounded-lg">
                        <strong>Safety Features:</strong> Orphaned complaint detection, automatic reassignment, and system health monitoring.
                    </div>
                    <form method="POST" class="w-full flex justify-center">
                        <input type="hidden" name="action" value="validate_assignments">
                        <button type="submit" 
                            class="btn-secondary w-full md:w-auto">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 1 0-14 0 7 7 0 0 0 14 0z" />
                            </svg>
                            <span>Validate Assignments</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Failed Assignments Button (only show if there are failed assignments) -->
            <?php if ($has_failed): ?>
            <div class="flex justify-center mb-6">
                <button type="button" onclick="toggleFailedDetails()" 
                    class="inline-flex items-center justify-center bg-purple-50 hover:bg-purple-100 text-purple-800 border-2 border-purple-600 hover:border-purple-700 px-7 py-3 rounded-full font-semibold text-base shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span id="failedButtonText">Show Failed Assignments</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Failed Assignments Details (hidden by default) -->
            <?php if ($has_failed): ?>
            <div id="failedDetails" class="failed-details bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
                <div class="flex items-center mb-4">
                    <div class="p-2 bg-red-100 rounded-lg mr-3">
                        <i class="lucide-alert-triangle text-red-600 w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-red-800">Failed Assignments Details</h3>
                </div>
                
                <?php if (!empty($_SESSION['auto_assignment_results']['failed_complaints'])): ?>
                <div class="mb-4">
                    <h4 class="font-medium text-red-700 mb-2">Failed Complaint Assignments:</h4>
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <div class="space-y-2">
                            <?php foreach ($_SESSION['auto_assignment_results']['failed_complaints'] as $failed): ?>
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                                    <div>
                                        <div class="font-medium text-red-800">Token: <?= htmlspecialchars($failed['token']) ?></div>
                                        <div class="text-sm text-red-600">Category: <?= htmlspecialchars(ucfirst($failed['category'])) ?> | Room: <?= htmlspecialchars($failed['room_no']) ?></div>
                                        <div class="text-xs text-red-500">Reason: <?= htmlspecialchars($failed['reason'] ?? 'No online technicians available for this category') ?></div>
                                    </div>
                                </div>
                                <div class="text-xs text-red-500">
                                    <?= date('M d, Y', strtotime($failed['created_at'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['auto_assignment_results']['failed_hostel_issues'])): ?>
                <div>
                    <h4 class="font-medium text-red-700 mb-2">Failed Hostel Issue Assignments:</h4>
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <div class="space-y-2">
                            <?php foreach ($_SESSION['auto_assignment_results']['failed_hostel_issues'] as $failed): ?>
                            <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg border border-red-200">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-red-500 rounded-full mr-3"></div>
                                    <div>
                                        <div class="font-medium text-red-800">ID: <?= $failed['id'] ?></div>
                                        <div class="text-sm text-red-600">Type: <?= htmlspecialchars(ucfirst($failed['issue_type'])) ?> | Hostel: <?= htmlspecialchars(ucfirst($failed['hostel_type'])) ?></div>
                                        <div class="text-xs text-red-500">Reason: <?= htmlspecialchars($failed['reason'] ?? 'No online technicians available for this issue type') ?></div>
                                    </div>
                                </div>
                                <div class="text-xs text-red-500">
                                    <?= date('M d, Y', strtotime($failed['created_at'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center text-blue-700">
                        <i class="lucide-info w-4 h-4 mr-2"></i>
                        <span class="text-sm">These items could not be assigned because no online technicians are available for their respective categories. Consider setting technicians online or adding new technicians.</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Orphaned Complaints Alert -->
            <?php if (!empty($orphaned_complaints)): ?>
            <div class="action-card p-6 mb-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="p-2 bg-red-100 rounded-lg mr-4">
                            <i class="lucide-alert-triangle text-red-600 w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Orphaned Complaints</h3>
                            <p class="text-sm text-slate-600"><?= count($orphaned_complaints) ?> complaints assigned to deleted technicians</p>
                        </div>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="validate_assignments">
                        <button type="submit" class="btn-danger">
                            <i class="lucide-wrench w-5 h-5"></i>
                            <span>Fix Now</span>
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>





            <!-- Technician Workload Table -->
            <div class="action-card">
                <div class="px-6 py-4 border-b border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Technician Workload</h3>
                            <p class="text-sm text-slate-600">Current workload based on average of <?= $stats['average_workload'] ?> complaints per technician</p>
                        </div>
                        <div class="flex items-center space-x-4 text-sm">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-slate-600">Low</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                                <span class="text-slate-600">Medium</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                <span class="text-slate-600">High</span>
                            </div>
                            <div class="border-l border-slate-300 pl-4">
                                <div class="flex items-center space-x-2 text-xs">
                                    <span class="text-blue-600 font-medium">Complaints</span>
                                    <span class="text-green-600 font-medium">Hostel Issues</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Technician</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Specialization</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Total Workload</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Pending</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Workload Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php if (empty($stats['technician_distribution'])): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="p-3 bg-slate-100 rounded-full mb-3">
                                            <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-slate-800 mb-2">No Technicians Found</h3>
                                        <p class="text-slate-600 mb-4">There are no technicians registered in the system.</p>
                                        <a href="../superadmin/user_management.php" class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                            Add Technicians
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($stats['technician_distribution'] as $tech): ?>
                            <?php 
                            $total_workload = $tech['assigned_complaints'] + $tech['assigned_hostel_issues'];
                            $workload_status = getWorkloadStatus($total_workload, $stats['average_workload']);
                            ?>
                            <tr class="hover:bg-slate-50 <?= $tech['is_online'] == 0 ? 'bg-red-50/50' : '' ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                    <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($tech['full_name']) ?></div>
                                        <?php if ($tech['is_online'] == 0): ?>
                                        <span class="ml-2 text-xs text-red-600 font-medium">(Offline)</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="status-badge <?= $tech['is_online'] == 1 ? 'status-online' : 'status-offline' ?>">
                                        <i class="lucide-<?= $tech['is_online'] == 1 ? 'wifi' : 'wifi-off' ?> w-3 h-3 mr-1"></i>
                                        <?= $tech['is_online'] == 1 ? 'Online' : 'Offline' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= htmlspecialchars(ucfirst($tech['specialization'])) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-slate-900"><?= $total_workload ?></div>
                                    <?php if ($tech['assigned_complaints'] > 0): ?>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <span class="text-blue-600"><?= $tech['assigned_complaints'] ?> complaints</span>
                                        <?php if ($tech['assigned_hostel_issues'] > 0): ?>
                                        <span class="text-green-600"><?= $tech['assigned_hostel_issues'] ?> hostel issues</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= $tech['pending_count'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $workload_status['class'] ?>">
                                        <?= $workload_status['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <script>
        // Toggle unassigned complaints table
        function toggleUnassignedTable() {
            const table = document.getElementById('unassignedTable');
            if (table) {
                table.style.display = table.style.display === 'none' ? 'block' : 'none';
                // Scroll to the table if showing
                if (table.style.display === 'block') {
                    table.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }

        // Add click handler to the KPI box
        document.addEventListener('DOMContentLoaded', function() {
            const kpiBox = document.getElementById('unassignedKpiBox');
            if (kpiBox) {
                kpiBox.addEventListener('click', toggleUnassignedTable);
            }
        });

        // Define hideNotification function first
        function hideNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 300);
            }
        }

        // Auto-hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.getElementById('notification');
            if (notification) {
                setTimeout(function() {
                    hideNotification();
                }, 5000);
            }
        });

        function toggleUnassignedTable() {
            const unassignedTable = document.getElementById('unassignedTable');
            if (unassignedTable.style.display === 'none') {
                unassignedTable.style.display = 'block';
            } else {
                unassignedTable.style.display = 'none';
            }
        }

        function toggleFailedDetails() {
            const details = document.getElementById('failedDetails');
            const buttonText = document.getElementById('failedButtonText');
            
            if (details.classList.contains('show')) {
                details.classList.remove('show');
                buttonText.textContent = 'Show Failed Assignments';
            } else {
                details.classList.add('show');
                buttonText.textContent = 'Hide Failed Assignments';
            }
        }

        // Auto-refresh the page every 30 seconds to get updated statistics
        setInterval(function() {
            // Only refresh if no forms are being submitted and no modals are open
            if (!document.querySelector('form:focus') && !document.querySelector('.modal')) {
                location.reload();
            }
        }, 30000);

        // Smart auto-assignment trigger: Check if technicians became available
        let lastTechnicianCount = <?= $online_technicians ?>;
        setInterval(function() {
            // This will be updated when the page refreshes
            const currentTechnicianCount = <?= $online_technicians ?>;
            const unassignedCount = <?= $truly_unassigned ?>;
            
            // If technicians became available (went from 0 to >0), show notification
            if (lastTechnicianCount == 0 && currentTechnicianCount > 0) {
                showTechnicianAvailableNotification(currentTechnicianCount);
            }
            lastTechnicianCount = currentTechnicianCount;
        }, 10000); // Check every 10 seconds

        function showTechnicianAvailableNotification(techCount) {
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-50 border border-green-200 text-green-800 p-4 rounded-lg shadow-lg z-50 transition-all duration-300';
            notification.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium">${techCount} technician(s) now available! Complaints can now be auto-assigned.</span>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-600 hover:text-green-800 ml-4">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 10000);
        }

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
                hour12: false 
            });
            document.getElementById('lastUpdated').textContent = timeString;
        }, 1000);
    </script>
    
    <!-- Smart Auto-Assignment Script -->
    <script src="../assets/js/smart-auto-assignment.js"></script>

</body>
</html> 