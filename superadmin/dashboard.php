<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../auth/login.php?error=unauthorized');
}
$categories = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac'];
$roles = ['student','faculty','nonteaching','technician'];

// Get selected time period (default to monthly)
$time_period = $_GET['time_period'] ?? 'monthly';

// Define date range based on time period
$date_condition = '';
switch($time_period) {
    case 'daily':
        $date_condition = "DATE(c.created_at) = CURDATE()";
        break;
    case 'monthly':
        $date_condition = "c.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        break;
    case 'yearly':
        $date_condition = "c.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'overall':
        $date_condition = "1=1"; // No date filtering
        break;
}

// Fetch technicians for dropdown (add after $roles definition)
$technicians = [];
$tech_query = "SELECT id, full_name, specialization FROM users WHERE role = 'technician'";
$result = $mysqli->query($tech_query);
while($row = $result->fetch_assoc()){
    $technicians[] = $row;
}

// Define hostel and issue types for display
$hostel_types = [
    'boys_hostel' => 'Boys Hostel',
    'girls_hostel' => 'Girls Hostel',
    'faculty_hostel' => 'Faculty Hostel'
];

$issue_types = [
    'water_supply' => 'Water Supply',
    'electricity' => 'Electricity',
    'wifi' => 'WiFi',
    'cleaning' => 'Cleaning',
    'maintenance' => 'Maintenance',
    'security' => 'Security',
    'food' => 'Food',
    'other' => 'Other'
];

// Summary card queries with time period filter
$stmt = $mysqli->prepare("SELECT COUNT(*) FROM complaints c WHERE " . $date_condition);
$stmt->execute();
$stmt->bind_result($total_complaints);
$stmt->fetch();
$stmt->close();

$total_users = $mysqli->query("SELECT COUNT(*) FROM users WHERE role != 'superadmin'")->fetch_row()[0];

$stmt = $mysqli->prepare("SELECT COUNT(*) FROM complaints c WHERE status = 'resolved' AND " . $date_condition);
$stmt->execute();
$stmt->bind_result($resolved_complaints);
$stmt->fetch();
$stmt->close();

$completion_percentage = $total_complaints > 0 ? round(($resolved_complaints / $total_complaints) * 100) : 0;

// Get complaints by category with status counts
$category_stats = [];
$category_query = "SELECT 
    category,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM complaints c 
    WHERE " . $date_condition . "
    GROUP BY category
    ORDER BY total DESC";
$result = $mysqli->query($category_query);
while ($row = $result->fetch_assoc()) {
    $row['completion_rate'] = $row['total'] > 0 ? round(($row['resolved'] / $row['total']) * 100) : 0;
    $category_stats[$row['category']] = $row;
}

// Get recent complaints (recent 10)
$recent_complaints = [];
$recent_query = "SELECT c.token, c.category, c.status, c.created_at, u.full_name as user_name 
                 FROM complaints c 
                 JOIN users u ON u.id = c.user_id 
                 WHERE " . $date_condition . "
                 ORDER BY c.created_at DESC LIMIT 10";
$result = $mysqli->query($recent_query);
while ($row = $result->fetch_assoc()) {
    $recent_complaints[] = $row;
}

// Get assignment statistics
$stats = get_assignment_statistics();

// Get system alerts for sidebar notification only
$enhanced_stats = get_enhanced_assignment_statistics();
$system_alerts = $enhanced_stats['enhanced']['system_health'] ?? [];
$critical_alerts = array_filter($system_alerts, function($alert) {
    return $alert['severity'] === 'high';
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMT - Superadmin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .glassmorphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .loading.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-radius: 50%;
            border-top-color: #10b981;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Clean KPI card styles */
        .kpi-card {
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced KPI structure */
        .kpi-icon-container {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
            color: #1e293b;
        }

        .kpi-description {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        .kpi-action {
            border-top: 1px solid #f1f5f9;
            padding-top: 1rem;
            margin-top: auto;
        }

        .sidebar-link {
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .gradient-border {
            position: relative;
            border-radius: 16px;
            background: linear-gradient(to right, #0f172a, #1e293b);
        }

        .gradient-border::after {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(60deg, #10b981, #0ea5e9);
            border-radius: 17px;
            z-index: -1;
            opacity: 0.3;
        }

        .logo-glow {
            filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.3));
        }

        .institute-name {
            background: linear-gradient(to right, #1e40af, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        .institute-name::before {
            content: 'ARMY INSTITUTE OF MANAGEMENT & TECHNOLOGY';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(90deg, #1e40af 0%, #a00 40%, #ff3b3b 50%, #a00 60%, #1e40af 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: shimmer-red 2.5s linear infinite;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        @keyframes shimmer-red {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                position: fixed;
                z-index: 50;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0.75rem !important;
            }

            .institute-name {
                font-size: 1.1rem;
                line-height: 1.4;
            }

            /* Enhanced mobile table styles */
            .mobile-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            .mobile-table table {
                min-width: 600px;
            }

            .mobile-table th,
            .mobile-table td {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
            }

            /* Mobile card improvements */
            .stats-card {
                padding: 1.5rem !important;
                margin-bottom: 1rem;
            }

            .stats-card h3 {
                font-size: 1.25rem !important;
            }

            .stats-card .text-5xl {
                font-size: 2.5rem !important;
            }

            /* Mobile KPI structure improvements */
            .mobile-grid .flex.items-center {
                flex-direction: column;
                align-items: flex-start;
                text-align: center;
            }

            .mobile-grid .flex-shrink-0 {
                margin-left: 0 !important;
                margin-top: 1rem;
                align-self: center;
            }

            .mobile-grid .kpi-icon-container {
                width: 48px;
                height: 48px;
            }

            .mobile-grid .kpi-value {
                font-size: 2rem;
            }

            /* Mobile KPI structure improvements */
            .mobile-grid .flex.items-start {
                flex-direction: column;
                align-items: flex-start;
            }

            .mobile-grid .flex-shrink-0 {
                margin-left: 0 !important;
                margin-top: 1rem;
                align-self: flex-end;
            }

            .mobile-grid .kpi-icon-container {
                width: 40px;
                height: 40px;
            }

            .mobile-grid .kpi-value {
                font-size: 1.75rem;
            }

            /* Mobile grid improvements */
            .mobile-grid {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
            }

            /* Tablet grid improvements */
            @media (min-width: 640px) and (max-width: 1023px) {
                .mobile-grid {
                    grid-template-columns: repeat(2, 1fr) !important;
                    gap: 1rem !important;
                }
            }

            /* Mobile header improvements */
            .mobile-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding-top: 4rem !important; /* Add space for hamburger menu */
            }

            .mobile-header img {
                width: 3rem;
                height: 3rem;
            }

            /* Mobile time period selector */
            .mobile-time-selector {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .mobile-time-selector select {
                width: 100%;
                padding: 0.75rem;
            }

            /* Improved mobile menu button styles */
            #menu-toggle {
                position: fixed;
                top: 1rem;
                left: 1rem;
                z-index: 60;
                padding: 0.75rem;
                border-radius: 0.75rem;
                background: white;
                color: #374151;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                transition: all 0.3s ease;
                border: 1px solid #e5e7eb;
                cursor: pointer;
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            /* Hide hamburger menu when sidebar is active */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%);
                opacity: 0;
                pointer-events: none;
            }

            #menu-toggle:hover {
                background: #f9fafb;
                transform: translateY(-1px);
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            }

            #menu-toggle:active {
                transform: translateY(0);
            }

            #menu-toggle:focus {
                outline: none;
                ring: 2px;
                ring-color: #10b981;
                ring-offset: 2px;
            }

            /* Hamburger menu animation */
            #hamburger-line-1,
            #hamburger-line-2,
            #hamburger-line-3 {
                transition: all 0.3s ease;
            }

            #menu-toggle.active #hamburger-line-1 {
                transform: rotate(45deg) translate(5px, 5px);
            }

            #menu-toggle.active #hamburger-line-2 {
                opacity: 0;
            }

            #menu-toggle.active #hamburger-line-3 {
                transform: rotate(-45deg) translate(7px, -6px);
            }

            /* Mobile overlay */
            #mobile-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }

            #mobile-overlay.active {
                opacity: 1;
                visibility: visible;
            }

            /* Menu icon animation */
            #menu-icon {
                transition: transform 0.3s ease;
            }

            #menu-icon.active {
                transform: rotate(90deg);
            }

            /* Ensure content doesn't overlap with hamburger menu */
            .main-content {
                padding-top: 1rem !important;
            }

            /* Better spacing for mobile content */
            .mobile-content-spacing {
                margin-top: 1rem;
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .main-content {
                padding: 0.5rem !important;
                padding-top: 1rem !important;
            }

            .stats-card {
                padding: 0.75rem !important;
            }

            .stats-card h3 {
                font-size: 0.875rem !important;
            }

            .stats-card .text-3xl {
                font-size: 1.5rem !important;
            }

            .institute-name {
                font-size: 1rem;
            }

            /* Adjust hamburger menu for very small screens */
            #menu-toggle {
                top: 0.75rem;
                left: 0.75rem;
                width: 44px;
                height: 44px;
                padding: 0.5rem;
            }

            /* Hide hamburger menu when sidebar is active on small screens */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }

            .mobile-header {
                padding-top: 3.5rem !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50">
    <div id="loading" class="loading">
        <div class="spinner"></div>
    </div>

    <!-- Mobile Menu Button -->
    <button id="menu-toggle" class="lg:hidden fixed top-4 left-4 z-50 p-3 rounded-xl bg-white text-slate-700 shadow-lg hover:bg-slate-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 border border-slate-200" aria-label="Toggle navigation menu">
        <div class="flex flex-col space-y-1">
            <span id="hamburger-line-1" class="w-5 h-0.5 bg-slate-700 transition-all duration-200"></span>
            <span id="hamburger-line-2" class="w-5 h-0.5 bg-slate-700 transition-all duration-200"></span>
            <span id="hamburger-line-3" class="w-5 h-0.5 bg-slate-700 transition-all duration-200"></span>
        </div>
    </button>

    <!-- Mobile Overlay -->
    <div id="mobile-overlay" class="lg:hidden fixed inset-0 bg-black bg-opacity-50 z-40 hidden"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="sidebar w-64 bg-slate-900 text-white flex flex-col fixed h-full shadow-xl">
            <div class="px-6 py-4 gradient-border flex items-center gap-4">
                <div class="bg-white p-1.5 rounded-lg">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10 logo-glow">
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight">Complaint<span class="text-emerald-400">System</span></h1>
                    <div class="text-xs text-slate-400">AIMT Portal</div>
                </div>
            </div>
            <div class="px-6 py-4 border-b border-slate-700/50">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin') ?></div>
                <div class="text-emerald-400 text-sm">Superadmin</div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-3 overflow-y-auto">
                <!-- System Alerts (Top Priority) -->
                <a href="system_alerts.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl <?= !empty($critical_alerts) ? 'bg-red-900/50 border border-red-500' : '' ?>">
                    <i class="lucide-alert-triangle mr-3 <?= !empty($critical_alerts) ? 'text-red-400' : 'text-slate-400' ?>"></i>
                    <span>System Alerts</span>
                    <?php if (!empty($critical_alerts)): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-2 py-1 font-bold"><?= count($critical_alerts) ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Primary Navigation -->
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl bg-slate-800/50">
                    <i class="lucide-layout-dashboard mr-3 text-emerald-400"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Complaint Management (High Priority) -->
                <div class="px-2 py-1">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Complaint Management</div>
                </div>
                <a href="view_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-database mr-3 text-slate-400"></i>
                    <span>View All Complaints</span>
                </a>
                <a href="submit_complaint.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-plus-circle mr-3 text-slate-400"></i>
                    <span>Submit Complaint</span>
                </a>
                <a href="my_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-list-checks mr-3 text-slate-400"></i>
                    <span>My Complaints</span>
                </a>
                
                <!-- User & Admin Management (High Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">User Management</div>
                </div>
                <a href="user_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-users mr-3 text-slate-400"></i>
                    <span>User Management</span>
                </a>
                <a href="admin_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-shield mr-3 text-slate-400"></i>
                    <span>Admin Management</span>
                </a>
                
                <!-- System Operations (Medium Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">System Operations</div>
                </div>
                <a href="auto_assignment.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-zap mr-3 text-slate-400"></i>
                    <span>Auto Assignment</span>
                </a>
                <a href="system_monitoring.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-activity mr-3 text-slate-400"></i>
                    <span>System Monitoring</span>
                </a>
                <a href="technician_status.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-toggle-left mr-3 text-slate-400"></i>
                    <span>Technician Status</span>
                </a>
                <a href="register_codes.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-key mr-3 text-slate-400"></i>
                    <span>Generate Codes</span>
                </a>
                
                <!-- Reports & Analytics (Medium Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Reports & Analytics</div>
                </div>
                <a href="reports.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-bar-chart mr-3 text-slate-400"></i>
                    <span>Reports</span>
                </a>
                <a href="manage_suggestions.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-lightbulb mr-3 text-slate-400"></i>
                    <span>Manage Suggestions</span>
                </a>
                
                <!-- Help Guide Button -->
                <button id="help-button" type="button" class="sidebar-link flex items-center w-full px-4 py-3 rounded-xl hover:bg-slate-800/50 transition-colors group">
                    <i class="lucide-help-circle mr-3 text-emerald-400"></i>
                    <span>Help Guide</span>
                </button>

                <!-- Account (Low Priority) -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                        <i class="lucide-log-out mr-3 text-slate-400"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col px-4 sm:px-6 md:px-8 md:ml-64 max-w-7xl mx-auto w-full">
            <!-- Header -->
            <header class="py-6 flex flex-col sm:flex-row items-center justify-between gap-4 mobile-header mobile-content-spacing">
                <div class="flex items-center gap-4 sm:gap-6">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-12 h-12 sm:w-16 sm:h-16 logo-glow">
                    <div>
                        <h1 class="text-lg sm:text-2xl font-bold institute-name mb-1">ARMY INSTITUTE OF MANAGEMENT & TECHNOLOGY</h1>
                        <p class="text-slate-500 text-sm sm:text-base">Superadmin Dashboard</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="text-center sm:text-right">
                        <div class="text-sm text-slate-600">Welcome,</div>
                        <div class="font-medium text-slate-900"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin') ?></div>
                    </div>
                </div>
            </header>



            <!-- Time Period Selection -->
            <div class="glassmorphism rounded-2xl shadow-lg p-4 mb-6 sm:mb-8">
                <div class="flex items-center justify-between mobile-time-selector">
                    <div class="flex items-center gap-3">
                        <i class="lucide-calendar text-emerald-600"></i>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Time Period</h3>
                            <p class="text-sm text-slate-500">Select duration for statistics</p>
                        </div>
                    </div>
                    <select id="time-period" onchange="window.location.href='dashboard.php?time_period=' + this.value" 
                            class="border border-slate-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white">
                        <option value="daily" <?= $time_period === 'daily' ? 'selected' : '' ?>>Daily</option>
                        <option value="monthly" <?= $time_period === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                        <option value="yearly" <?= $time_period === 'yearly' ? 'selected' : '' ?>>Yearly</option>
                        <option value="overall" <?= $time_period === 'overall' ? 'selected' : '' ?>>Overall</option>
                    </select>
                </div>
            </div>

            <!-- Active Hostel-wide Issues Section -->
            <?php
            // Fetch active hostel-wide issues
            $active_issues = $mysqli->query("SELECT hi.*, 
                (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes
                FROM hostel_issues hi
                WHERE hi.status != 'resolved'
                ORDER BY hi.updated_at DESC");

            if ($active_issues->num_rows > 0):
            ?>
            <div class="glassmorphism rounded-2xl shadow-lg p-6 mb-6 sm:mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-slate-900">Active Hostel-Wide Issues</h2>
                    <div class="text-sm text-slate-500">
                        <i class="lucide-building mr-1"></i>
                        Urgent hostel-wide complaints that need attention
                    </div>
                </div>
                <div class="overflow-x-auto mobile-table">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Hostel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Issue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Votes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Last Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php while ($issue = $active_issues->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?= htmlspecialchars($hostel_types[$issue['hostel_type']] ?? $issue['hostel_type']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= htmlspecialchars($issue_types[$issue['issue_type']] ?? $issue['issue_type']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            <?= $issue['status'] === 'in_progress' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                            <?= ucfirst(str_replace('_',' ',$issue['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= $issue['votes'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= date('M j, Y H:i', strtotime($issue['updated_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <a href="hostel_issue_details.php?id=<?= $issue['id'] ?>" class="inline-flex items-center px-3 py-1 border border-slate-300 text-sm font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- KPI Dashboard Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Complaints KPI -->
                <div class="group relative bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                    <!-- Icon Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= ucfirst($time_period) ?></div>
                        </div>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="mb-6">
                        <h3 class="text-base font-semibold text-slate-900 mb-2">Total Complaints</h3>
                        <div class="text-3xl font-bold text-slate-900 mb-3"><?= number_format($total_complaints) ?></div>
                        <div class="flex items-center text-slate-600">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">Active complaints</span>
                        </div>
                    </div>
                    
                    <!-- Action -->
                    <div class="pt-4 border-t border-slate-100">
                        <a href="view_complaints.php" class="inline-flex items-center justify-between w-full text-emerald-600 hover:text-emerald-700 text-sm font-semibold group-hover:translate-x-1 transition-transform duration-200">
                            <span>View All Complaints</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Completion Rate KPI -->
                <div class="group relative bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                    <!-- Icon Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= ucfirst($time_period) ?></div>
                        </div>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="mb-6">
                        <h3 class="text-base font-semibold text-slate-900 mb-2">Completion Rate</h3>
                        <div class="text-3xl font-bold text-slate-900 mb-3"><?= $completion_percentage ?>%</div>
                        
                        <!-- Dynamic Progress Bar -->
                        <div class="space-y-2 mb-3">
                            <div class="bg-slate-200 rounded-full h-2 overflow-hidden">
                                <?php
                                // Determine progress bar color based on percentage
                                $progress_color = '';
                                $status_color = '';
                                $status_text = '';
                                
                                if ($completion_percentage >= 80) {
                                    $progress_color = 'from-emerald-500 to-emerald-600';
                                    $status_color = 'bg-emerald-500';
                                    $status_text = 'Excellent';
                                } elseif ($completion_percentage >= 60) {
                                    $progress_color = 'from-emerald-400 to-emerald-500';
                                    $status_color = 'bg-emerald-500';
                                    $status_text = 'Good';
                                } elseif ($completion_percentage >= 40) {
                                    $progress_color = 'from-yellow-500 to-yellow-600';
                                    $status_color = 'bg-yellow-500';
                                    $status_text = 'Average';
                                } elseif ($completion_percentage >= 20) {
                                    $progress_color = 'from-orange-500 to-orange-600';
                                    $status_color = 'bg-orange-500';
                                    $status_text = 'Below Average';
                                } else {
                                    $progress_color = 'from-red-500 to-red-600';
                                    $status_color = 'bg-red-500';
                                    $status_text = 'Poor';
                                }
                                ?>
                                <div class="bg-gradient-to-r <?= $progress_color ?> h-2 rounded-full transition-all duration-700 ease-out" style="width: <?= $completion_percentage ?>%"></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>0%</span>
                                <span>100%</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center text-slate-600">
                            <div class="w-2 h-2 <?= $status_color ?> rounded-full mr-2"></div>
                            <span class="text-sm font-medium"><?= $status_text ?> performance</span>
                        </div>
                    </div>
                    
                    <!-- Action -->
                    <div class="pt-4 border-t border-slate-100">
                        <a href="reports.php" class="inline-flex items-center justify-between w-full text-blue-600 hover:text-blue-700 text-sm font-semibold group-hover:translate-x-1 transition-transform duration-200">
                            <span>View Reports</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Resolved Complaints KPI -->
                <div class="group relative bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                    <!-- Icon Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide"><?= ucfirst($time_period) ?></div>
                        </div>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="mb-6">
                        <h3 class="text-base font-semibold text-slate-900 mb-2">Resolved Complaints</h3>
                        <div class="text-3xl font-bold text-slate-900 mb-3"><?= number_format($resolved_complaints) ?></div>
                        <div class="flex items-center text-slate-600">
                            <div class="w-2 h-2 bg-teal-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">Successfully resolved</span>
                        </div>
                    </div>
                    
                    <!-- Action -->
                    <div class="pt-4 border-t border-slate-100">
                        <a href="view_complaints.php?status=resolved" class="inline-flex items-center justify-between w-full text-teal-600 hover:text-teal-700 text-sm font-semibold group-hover:translate-x-1 transition-transform duration-200">
                            <span>View Resolved</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Total Users KPI -->
                <div class="group relative bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                    <!-- Icon Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">System</div>
                        </div>
                    </div>
                    
                    <!-- Main Content -->
                    <div class="mb-6">
                        <h3 class="text-base font-semibold text-slate-900 mb-2">Total Users</h3>
                        <div class="text-3xl font-bold text-slate-900 mb-3"><?= number_format($total_users) ?></div>
                        <div class="flex items-center text-slate-600">
                            <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                            <span class="text-sm font-medium">Registered users</span>
                        </div>
                    </div>
                    
                    <!-- Action -->
                    <div class="pt-4 border-t border-slate-100">
                        <a href="user_management.php" class="inline-flex items-center justify-between w-full text-purple-600 hover:text-purple-700 text-sm font-semibold group-hover:translate-x-1 transition-transform duration-200">
                            <span>Manage Users</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Category Stats and Recent Complaints -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Category Stats -->
                <div class="glassmorphism rounded-2xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-slate-900 mb-6">Complaints by Category</h2>
                    <div class="space-y-6">
                        <?php 
                        // Sort categories by total complaints
                        uasort($category_stats, function($a, $b) {
                            return $b['total'] - $a['total'];
                        });
                        
                        foreach ($category_stats as $category => $stats): 
                        ?>
                            <div class="border-b border-slate-200 pb-4 last:border-0 last:pb-0">
                                <div class="flex justify-between items-center mb-3">
                                    <h3 class="font-medium text-slate-900 capitalize"><?= $category ?></h3>
                                    <a href="view_complaints.php?category=<?= urlencode($category) ?>" 
                                       class="text-emerald-600 hover:text-emerald-700 text-sm font-medium inline-flex items-center">
                                        Details
                                        <i class="lucide-arrow-right ml-1 h-4 w-4"></i>
                                    </a>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="text-center p-2 rounded-lg bg-slate-50">
                                        <div class="font-semibold text-slate-900"><?= $stats['total'] ?></div>
                                        <div class="text-slate-600 text-xs">Total</div>
                                    </div>
                                    <div class="text-center p-2 rounded-lg bg-yellow-50">
                                        <div class="font-semibold text-yellow-700"><?= $stats['pending'] ?></div>
                                        <div class="text-yellow-600 text-xs">Pending</div>
                                    </div>
                                    <div class="text-center p-2 rounded-lg bg-emerald-50">
                                        <div class="font-semibold text-emerald-700"><?= $stats['resolved'] ?></div>
                                        <div class="text-emerald-600 text-xs">Resolved</div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-slate-600">Completion Rate</span>
                                        <span class="font-medium text-slate-900"><?= $stats['completion_rate'] ?>%</span>
                                    </div>
                                    <div class="bg-slate-200 rounded-full h-1.5">
                                        <div class="bg-emerald-500 h-1.5 rounded-full" 
                                             style="width: <?= $stats['completion_rate'] ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Complaints -->
                <div class="glassmorphism rounded-2xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-slate-900 mb-6">Recent Complaints</h2>
                    <div class="space-y-4">
                        <?php foreach ($recent_complaints as $complaint): ?>
                            <a href="complaint_details.php?token=<?= urlencode($complaint['token']) ?>" 
                               class="block flex items-start justify-between p-4 rounded-xl border border-slate-200 hover:border-emerald-200 hover:bg-emerald-50 transition-all duration-200 cursor-pointer">
                                <div>
                                    <div class="font-medium text-slate-900"><?= htmlspecialchars($complaint['user_name']) ?></div>
                                    <div class="text-slate-500 text-sm capitalize"><?= htmlspecialchars($complaint['category']) ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="inline-flex px-2 py-1 rounded-full text-xs font-medium 
                                        <?= getStatusBadgeColor($complaint['status']) ?>">
                                        <?= ucfirst(htmlspecialchars($complaint['status'])) ?>
                                    </div>
                                    <div class="text-slate-400 text-xs mt-1">
                                        <?= date('M d, Y', strtotime($complaint['created_at'])) ?>
                                    </div>
                                </div>
                            </a>
                    <?php endforeach; ?>
                        <div class="text-center pt-4">
                            <a href="view_complaints.php" 
                               class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors font-medium text-sm">
                                View All Complaints
                                <i class="lucide-arrow-right ml-2 h-4 w-4"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resolved Hostel-wide Issues -->
            <div class="glassmorphism rounded-2xl shadow-lg p-6 mb-6 sm:mb-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold text-slate-900">Resolved Hostel-Wide Issues</h2>
                    <div class="flex items-center gap-4">
                    <div class="text-sm text-slate-500">
                        <i class="lucide-building mr-1"></i>
                            <?php
                            try {
                                $resolved_count = $mysqli->query("SELECT COUNT(*) as count FROM hostel_issues WHERE status = 'resolved'")->fetch_assoc()['count'];
                                echo $resolved_count . " resolved issues";
                            } catch (Exception $e) {
                                echo "0 resolved issues";
                            }
                            ?>
                        </div>
                        <button onclick="toggleResolvedIssues()" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium transition-colors">
                            <span id="toggle-text">Show Details</span>
                            <i id="toggle-icon" class="lucide-chevron-down ml-1 h-4 w-4"></i>
                        </button>
                    </div>
                </div>
                <div id="resolved-issues" class="hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Hostel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Issue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Votes</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Resolved On</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php
                            try {
                                // Fetch resolved hostel-wide issues
                                $resolved_issues = $mysqli->query("SELECT hi.*, 
                                    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes
                                    FROM hostel_issues hi
                                    WHERE hi.status = 'resolved'
                                    ORDER BY hi.updated_at DESC");

                                if ($resolved_issues && $resolved_issues->num_rows > 0):
                                    while ($issue = $resolved_issues->fetch_assoc()): 
                            ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                        <?= htmlspecialchars($hostel_types[$issue['hostel_type']] ?? $issue['hostel_type']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= htmlspecialchars($issue_types[$issue['issue_type']] ?? $issue['issue_type']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= $issue['votes'] ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                        <?= date('M j, Y H:i', strtotime($issue['updated_at'])) ?>
                                    </td>
                                </tr>
                            <?php 
                                    endwhile;
                                else:
                            ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-slate-500">
                                        No resolved hostel-wide issues.
                                    </td>
                                </tr>
                            <?php 
                                endif;
                            } catch (Exception $e) {
                            ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-slate-500">
                                        Unable to load resolved issues.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Loading spinner
        window.addEventListener('load', () => {
            const loading = document.getElementById('loading');
            loading.classList.remove('active');
        });

        document.addEventListener('DOMContentLoaded', () => {
            const loading = document.getElementById('loading');
            loading.classList.add('active');

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const mobileOverlay = document.getElementById('mobile-overlay');
            
            function toggleMobileMenu() {
                const isActive = sidebar.classList.contains('active');
                
                if (isActive) {
                    // Close menu
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                    menuToggle.classList.remove('active');
                    menuToggle.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                } else {
                    // Open menu
                    sidebar.classList.add('active');
                    mobileOverlay.classList.add('active');
                    menuToggle.classList.add('active');
                    menuToggle.classList.add('sidebar-active');
                    document.body.style.overflow = 'hidden';
                }
            }
            
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMobileMenu();
            });

            // Close sidebar when clicking overlay
            mobileOverlay.addEventListener('click', () => {
                toggleMobileMenu();
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (event) => {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                    menuToggle.classList.remove('active');
                    menuToggle.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                }
            });

            // Close menu when pressing Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });
        });

        function toggleResolvedIssues() {
           
            
            const resolvedSection = document.getElementById('resolved-issues');
            const toggleText = document.getElementById('toggle-text');
            const toggleIcon = document.getElementById('toggle-icon');
            
            if (resolvedSection.classList.contains('hidden')) {
                resolvedSection.classList.remove('hidden');
                toggleText.textContent = 'Hide Details';
                toggleIcon.classList.remove('lucide-chevron-down');
                toggleIcon.classList.add('lucide-chevron-up');
            } else {
                resolvedSection.classList.add('hidden');
                toggleText.textContent = 'Show Details';
                toggleIcon.classList.remove('lucide-chevron-up');
                toggleIcon.classList.add('lucide-chevron-down');
            }
        }
    </script>
    <?php include 'help_guide.php'; ?>
</body>
</html> 
</html> 
</html> 
</html> 