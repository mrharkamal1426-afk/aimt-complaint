<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../auth/login.php?error=unauthorized');
}

$tech_id = $_SESSION['user_id'];

// Get technician's specialization and online status
$stmt = $mysqli->prepare("SELECT specialization, is_online FROM users WHERE id = ?");
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$stmt->bind_result($specialization, $is_online);
$stmt->fetch();
$stmt->close();

if (!$specialization) {
    redirect('../auth/login.php?error=no_specialization');
}

// If technician is offline, redirect to offline dashboard
if ($is_online == 0) {
    redirect('offline_dashboard.php');
}

// Fetch complaints for technician's specialization AND complaints specifically assigned to this technician
// Only show pending and in_progress complaints on dashboard
$sql = "SELECT c.*, u.full_name, u.phone, u.hostel_type, c.assignment_type
        FROM complaints c 
        JOIN users u ON u.id = c.user_id 
        WHERE (c.category = ? OR c.technician_id = ?)
        AND c.status IN ('pending', 'in_progress')
        ORDER BY c.created_at ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('si', $specialization, $tech_id);
$stmt->execute();
$result = $stmt->get_result();

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Calculate statistics for active complaints only
$total_complaints = count($complaints);
$pending_complaints = count(array_filter($complaints, fn($c) => $c['status'] === 'pending'));
$in_progress_complaints = count(array_filter($complaints, fn($c) => $c['status'] === 'in_progress'));

// Count assigned vs category complaints
$admin_assigned_complaints = count(array_filter($complaints, fn($c) => $c['assignment_type'] === 'admin_assigned'));
$category_complaints = count(array_filter($complaints, fn($c) => $c['assignment_type'] === 'category'));

// Get total resolved complaints for completion rate calculation
$total_resolved_sql = "SELECT COUNT(*) as total_resolved FROM complaints c 
                      WHERE (c.category = ? OR c.technician_id = ?) AND c.status = 'resolved'";
$stmt = $mysqli->prepare($total_resolved_sql);
$stmt->bind_param('si', $specialization, $tech_id);
$stmt->execute();
$stmt->bind_result($total_resolved);
$stmt->fetch();
$stmt->close();

$total_all_complaints = $total_complaints + $total_resolved;
$completion_rate = $total_all_complaints > 0 ? round(($total_resolved / $total_all_complaints) * 100) : 0;

// Fetch hostel-wide issues
$hostel_issues_sql = "SELECT hi.*, 
    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes
    FROM hostel_issues hi
    WHERE hi.status != 'resolved'
    ORDER BY hi.created_at DESC LIMIT 10";

$hostel_issues_result = $mysqli->query($hostel_issues_sql);
$hostel_issues = [];
while ($row = $hostel_issues_result->fetch_assoc()) {
    $hostel_issues[] = $row;
}

// Get recent activity (last 5 resolved complaints by this technician)
$recent_activity_sql = "SELECT c.*, u.full_name
                        FROM complaints c 
                        JOIN users u ON u.id = c.user_id 
                        WHERE c.technician_id = ? AND c.status = 'resolved' AND c.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        ORDER BY c.updated_at DESC LIMIT 5";

$stmt = $mysqli->prepare($recent_activity_sql);
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Technician Dashboard | AIMT Complaint Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            'bg-primary': '#1a1b1e',
                            'bg-secondary': '#2c2e33',
                            'text-primary': '#e4e6eb',
                            'text-secondary': '#b0b3b8',
                            'border': '#3e4044'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
        
        /* Smooth transitions */
        .transition-all {
            transition-property: all;
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
            transition-duration: 300ms;
        }
        
        /* Status badge animations */
        .status-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        /* Card hover effects */
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        /* QR Scanner button glow */
        .qr-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
        }
        
        .qr-glow:hover {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .mobile-card {
                padding: 1rem !important;
                margin-bottom: 0.75rem;
            }
            
            .mobile-text {
                font-size: 0.875rem !important;
                line-height: 1.4;
            }
            
            .mobile-button {
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
                min-height: 44px; /* Touch-friendly */
            }
            
            .mobile-header {
                padding: 0.75rem 1rem !important;
            }
            
            .mobile-sidebar {
                width: 100vw !important;
                max-width: 320px;
            }
            
            .mobile-content {
                padding: 0.75rem !important;
                overflow-y: auto;
                height: calc(100vh - 60px); /* Account for header */
            }
            
            .mobile-grid {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
            }
            
            .mobile-stats {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
            
            .mobile-complaint-card {
                padding: 1rem !important;
                margin-bottom: 0.75rem;
                border-radius: 12px !important;
            }
            
            .mobile-complaint-info {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            .mobile-actions {
                flex-direction: column !important;
                gap: 0.5rem !important;
                margin-top: 1rem !important;
            }
            
            .mobile-modal {
                padding: 0.5rem !important;
            }
            
            .mobile-modal-content {
                max-width: 95vw !important;
                max-height: 85vh !important;
            }
            
            /* Improved scrolling for mobile */
            .mobile-scrollable {
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            
            /* Better touch targets */
            .mobile-touch-target {
                min-height: 48px;
                min-width: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Compact complaint cards for mobile */
            .mobile-complaint-compact {
                padding: 0.75rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            .mobile-complaint-compact .grid {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* Better spacing for mobile */
            .mobile-spacing-y > * + * {
                margin-top: 0.75rem !important;
            }
            
            /* Improved button sizing for mobile */
            .mobile-btn {
                padding: 0.75rem 1rem !important;
                font-size: 0.875rem !important;
                border-radius: 8px !important;
            }
        }

        /* Touch-friendly improvements */
        .touch-target {
            min-height: 44px;
            min-width: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .touch-button {
            padding: 12px 16px;
            font-size: 16px; /* Prevents zoom on iOS */
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .touch-button:active {
            transform: scale(0.98);
        }
        
        /* Better readability */
        .readable-text {
            line-height: 1.6;
            letter-spacing: 0.01em;
        }
        
        .readable-heading {
            line-height: 1.3;
            font-weight: 600;
        }
        
        /* Improved spacing for mobile */
        .mobile-spacing {
            margin-bottom: 1rem;
        }
        
        .mobile-spacing:last-child {
            margin-bottom: 0;
        }

        /* Complaint Modal Styles */
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: #f8fafc;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .info-icon {
            font-size: 1rem;
            color: #64748b;
            margin-top: 4px;
            width: 16px;
            text-align: center;
        }

        .info-label {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 500;
        }
        
        .info-value.link {
            color: #2563eb;
            text-decoration: none;
            transition: color 0.2s;
        }
        .info-value.link:hover {
            color: #1d4ed8;
        }

        .info-group {
            margin-top: 24px;
        }

        .info-group-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .description-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #334155;
            white-space: pre-wrap;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.status-in-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.status-resolved {
            background: #dcfce7;
            color: #166534;
        }

        .capitalize {
            text-transform: capitalize;
        }

        /* Grid utilities for modal */
        .grid { display: grid; }
        .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
        .sm\\:grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .gap-4 { gap: 1rem; }
        .col-span-full { grid-column: 1 / -1; }
        .space-y-6 > :not([hidden]) ~ :not([hidden]) {
            --tw-space-y-reverse: 0;
            margin-top: calc(1.5rem * calc(1 - var(--tw-space-y-reverse)));
            margin-bottom: calc(1.5rem * var(--tw-space-y-reverse));
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-dark-bg-primary min-h-screen font-['Inter'] transition-colors duration-200">
    <!-- Sidebar Backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black bg-opacity-30 z-50 hidden md:hidden"></div>

    <!-- Main container -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed md:static inset-y-0 left-0 transform -translate-x-full md:translate-x-0 z-[55] w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white flex flex-col transition-transform duration-200 ease-in-out shadow-xl">
            <div class="px-6 py-6 bg-gradient-to-r from-blue-700 to-blue-800 dark:from-blue-800 dark:to-blue-900">
                <div class="text-2xl font-bold">Complaint<span class="text-cyan-300">System</span></div>
            </div>
            <div class="px-6 py-4 border-b border-blue-700/50 dark:border-blue-800/50 bg-blue-800/30 dark:bg-blue-900/30">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Technician') ?></div>
                <div class="text-cyan-300 text-sm"><?= ucfirst(htmlspecialchars($specialization)) ?> Technician</div>
            </div>
            <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg bg-blue-700/50 dark:bg-blue-800/50 hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="scanner.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">qr_code_scanner</span>
                    <span>QR Scanner</span>
                </a>
                <a href="complaints.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">assignment</span>
                    <span>All Complaints</span>
                </a>
                <a href="hostel_issues.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">campaign</span>
                    <span>Hostel Issues</span>
                </a>
                <a href="submit_complaint.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-green-700/50 dark:hover:bg-green-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-green-200">add_circle</span>
                    <span>Submit Complaint</span>
                </a>
                <a href="my_complaints.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">assignment</span>
                    <span>My Complaints</span>
                </a>   
                <button id="help-button" type="button" class="flex items-center w-full px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">help</span>
                    <span>Help Guide</span>
                </button> 
                <a href="../auth/logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-red-500/10 text-red-100 transition-colors group mt-auto">
                    <span class="material-icons mr-3">logout</span>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 flex flex-col w-0 md:w-auto md:ml-0 transition-all duration-200 overflow-hidden">
            <!-- Header -->
            <header class="sticky top-0 z-40 bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg">
                <div class="flex items-center justify-between px-4 py-3">
                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-button" class="md:hidden p-2 rounded-lg text-white hover:bg-white/20 touch-target">
                        <span class="material-icons">menu</span>
                    </button>
                    
                    <!-- Logo and Title -->
                    <div class="flex items-center space-x-3 flex-1 md:flex-none mx-2">
                        <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-8 h-8 md:w-10 md:h-10 bg-white p-1 rounded-lg shadow-lg">
                        <div class="min-w-0">
                            <h1 class="text-base sm:text-lg md:text-xl font-bold truncate">Technician Dashboard</h1>
                            <p class="hidden md:block text-sm text-blue-100">Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Technician') ?></p>
                        </div>
                    </div>

                    <!-- Spacer for desktop -->
                    <div class="hidden md:block flex-1"></div>

                    <!-- Scan QR Button -->
                    <div class="flex items-center">
                        <a href="scanner.php" class="touch-target bg-white/20 hover:bg-white/30 text-white px-3 py-2 rounded-lg transition-colors flex items-center space-x-2 qr-glow">
                            <span class="material-icons">qr_code_scanner</span>
                            <span class="hidden sm:inline">Scan</span>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Scrollable Content Area -->
            <div class="flex-1 overflow-y-auto mobile-scrollable">
                <div class="p-4 md:p-6 space-y-6 mobile-spacing-y">
                    
                    <!-- Success Message -->
                    <?php if (isset($_SESSION['status_message'])): ?>
                    <div class="bg-green-500 text-white p-4 rounded-xl text-center shadow-lg">
                        <div class="flex items-center justify-center">
                            <span class="material-icons mr-2">check_circle</span>
                            <span><?= htmlspecialchars($_SESSION['status_message']) ?></span>
                        </div>
                    </div>
                    <?php unset($_SESSION['status_message']); endif; ?>
                    <!-- Summary Cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        <!-- Total Complaints -->
                        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-4 md:p-6 card-hover">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Total Complaints</p>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-dark-text-primary"><?= $total_complaints ?></p>
                                </div>
                                <div class="p-2 md:p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                    <span class="material-icons text-blue-600 dark:text-blue-400">assignment</span>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Complaints -->
                        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-4 md:p-6 card-hover">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Pending</p>
                                    <p class="text-2xl font-bold text-orange-600"><?= $pending_complaints ?></p>
                                </div>
                                <div class="p-2 md:p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                    <span class="material-icons text-orange-600 dark:text-orange-400">schedule</span>
                                </div>
                            </div>
                        </div>

                        <!-- Admin Assigned -->
                        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-4 md:p-6 card-hover">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Admin Assigned</p>
                                    <p class="text-2xl font-bold text-purple-600"><?= $admin_assigned_complaints ?></p>
                                </div>
                                <div class="p-2 md:p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                    <span class="material-icons text-purple-600 dark:text-purple-400">admin_panel_settings</span>
                                </div>
                            </div>
                        </div>

                        <!-- Completion Rate -->
                        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-4 md:p-6 card-hover">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Completion Rate</p>
                                    <p class="text-2xl font-bold text-green-600"><?= $completion_rate ?>%</p>
                                </div>
                                <div class="p-2 md:p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                    <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Active Complaints -->
                        <div class="lg:col-span-2 bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border">
                            <div class="p-4 md:p-6 border-b border-gray-200 dark:border-dark-border">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">
                                        Active Complaints (<?= ucfirst($specialization) ?>)
                                    </h2>
                                    <div class="flex items-center space-x-4">
                                        <span class="text-sm text-gray-500 dark:text-dark-text-secondary">
                                            <?= $pending_complaints ?> pending
                                        </span>
                                        <?php if ($admin_assigned_complaints > 0): ?>
                                            <span class="text-sm text-purple-600 dark:text-purple-400 font-medium">
                                                <?= $admin_assigned_complaints ?> admin assigned
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-2 md:p-6">
                                <?php if (!empty($complaints)): ?>
                                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border overflow-hidden">
                                        <!-- Mobile-optimized complaint list -->
                                        <div class="divide-y divide-gray-200 dark:divide-dark-border max-h-96 overflow-y-auto mobile-scrollable">
                                            <?php 
                                            $active_complaints = array_filter($complaints, fn($c) => in_array($c['status'], ['pending', 'in_progress']));
                                            foreach ($active_complaints as $complaint): 
                                            ?>
                                                <div class="p-4 md:p-6 hover:bg-gray-50 dark:hover:bg-dark-bg-primary transition-colors
                                                    <?= $complaint['assignment_type'] === 'admin_assigned' ? 'bg-purple-50 dark:bg-purple-900/10 border-l-4 border-purple-500' : '' ?>
                                                    mobile-complaint-compact">
                                                    
                                                    <!-- Mobile-friendly grid layout -->
                                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 md:gap-4 mobile-complaint-info">
                                                        <!-- User Column -->
                                                        <div class="md:col-span-1">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center flex-shrink-0">
                                                                    <span class="material-icons text-blue-600 dark:text-blue-400 text-lg">person</span>
                                                                </div>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="text-sm font-medium text-gray-900 dark:text-dark-text-primary truncate">
                                                                        <?= htmlspecialchars($complaint['full_name']) ?>
                                                                    </p>
                                                                    <div class="flex flex-wrap items-center gap-1 mt-1">
                                                                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200" style="letter-spacing:0.2px;">
                                                                            Room <?= htmlspecialchars($complaint['room_no']) ?>
                                                                        </span>
                                                                        <?php if (!empty($complaint['hostel_type'])): ?>
                                                                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700 dark:bg-gray-800/50 dark:text-gray-200 capitalize" style="letter-spacing:0.2px;">
                                                                                <?= htmlspecialchars(str_replace('_', ' ', $complaint['hostel_type'])) ?> Hostel
                                                                            </span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Contact Info Column -->
                                                        <div class="md:col-span-1">
                                                            <div class="space-y-1">
                                                                <a href="tel:<?= htmlspecialchars($complaint['phone']) ?>" 
                                                                   class="flex items-center text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                                                    <span class="material-icons text-xs mr-1">phone</span>
                                                                    <?= htmlspecialchars($complaint['phone']) ?>
                                                                </a>
                                                                <p class="text-xs text-gray-500 dark:text-dark-text-secondary">
                                                                    <span class="font-semibold">Submitted on:</span> <?= date('M j, g:i A', strtotime($complaint['created_at'])) ?>
                                                                </p>
                                                            </div>
                                                        </div>

                                                        <!-- Category Column -->
                                                        <div class="md:col-span-1">
                                                            <p class="text-sm text-gray-900 dark:text-dark-text-primary font-medium capitalize">
                                                                <?= htmlspecialchars($complaint['category']) ?>
                                                            </p>
                                                            <?php if ($complaint['hostel_type']): ?>
                                                                <p class="text-xs text-gray-500 dark:text-dark-text-secondary capitalize">
                                                                    <?= htmlspecialchars(str_replace('_', ' ', $complaint['hostel_type'])) ?>
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Assignment Type Column -->
                                                        <div class="md:col-span-1">
                                                            <?php if ($complaint['assignment_type'] === 'admin_assigned'): ?>
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 animate-pulse">
                                                                    <span class="material-icons text-xs mr-1">warning</span>
                                                                    Very Urgent: Admin Assigned
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                                                    <span class="material-icons text-xs mr-1">category</span>
                                                                    Auto Assigned
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <!-- Status & Actions Column -->
                                                        <div class="md:col-span-1">
                                                            <div class="space-y-2">
                                                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                                                    <?php
                                                                    switch($complaint['status']) {
                                                                        case 'pending': echo 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'; break;
                                                                        case 'in_progress': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                                                        default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                                                                    }
                                                                    ?>">
                                                                    <?= ucfirst(str_replace('_', ' ', $complaint['status'])) ?>
                                                                </span>
                                                                <button onclick="viewComplaintDetails('<?= $complaint['token'] ?>')" 
                                                                        class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded-lg transition-colors mobile-btn mobile-touch-target">
                                                                    <span class="material-icons text-sm mr-1">visibility</span>
                                                                    View Details
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Sidebar Content -->
                        <div class="space-y-6">
                            <!-- Quick Actions -->
                            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary mb-4">Quick Actions</h3>
                                <div class="space-y-3">
                                    <a href="scanner.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2 qr-glow">
                                        <span class="material-icons">qr_code_scanner</span>
                                        <span>Scan QR Code</span>
                                    </a>
                                    <a href="complaints.php" class="w-full bg-gray-100 dark:bg-dark-border hover:bg-gray-200 dark:hover:bg-dark-bg-primary text-gray-700 dark:text-dark-text-primary px-4 py-3 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                                        <span class="material-icons">assignment</span>
                                        <span>View All Complaints</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Recent Activity -->
                            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary mb-4">Recent Activity</h3>
                                <?php if (empty($recent_activity)): ?>
                                    <p class="text-gray-500 dark:text-dark-text-secondary text-sm">No recent activity</p>
                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php foreach ($recent_activity as $activity): ?>
                                            <div class="flex items-start space-x-3">
                                                <div class="w-2 h-2 bg-green-500 rounded-full mt-2 flex-shrink-0"></div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 dark:text-dark-text-primary">
                                                        Resolved complaint for <?= htmlspecialchars($activity['full_name']) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-dark-text-secondary">
                                                        Room <?= htmlspecialchars($activity['room_no']) ?> • 
                                                        <?= date('M j, g:i A', strtotime($activity['updated_at'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Hostel Issues -->
                            <?php 
                            $urgent_issue = null;
                            foreach ($hostel_issues as $issue) {
                                if ($issue['votes'] >= 15) {
                                    $urgent_issue = $issue;
                                    break;
                                }
                            }
                            ?>
                            <?php if ($urgent_issue): ?>
                            <div class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 w-full max-w-lg">
                                <marquee behavior="scroll" direction="left" scrollamount="8" class="bg-red-600 text-white font-bold py-2 px-4 rounded-xl shadow-lg border-2 border-red-800 animate-pulse">
                                    🚨 URGENT: Hostel-wide issue (<?= htmlspecialchars(ucfirst($urgent_issue['issue_type'])) ?>, <?= htmlspecialchars(ucfirst($urgent_issue['hostel_type'])) ?> Hostel) has reached 15+ votes! Immediate attention required!
                                </marquee>
                            </div>
                            <?php endif; ?>
                            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary mb-4">Hostel Issues</h3>
                                <?php if (empty($hostel_issues)): ?>
                                    <p class="text-gray-500 dark:text-dark-text-secondary text-sm">No active hostel issues</p>
                                <?php else: ?>
                                    <div class="space-y-3">
                                        <?php foreach (array_slice($hostel_issues, 0, 5) as $issue): ?>
                                            <div class="border border-gray-200 dark:border-dark-border rounded-lg p-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-sm font-medium text-gray-900 dark:text-dark-text-primary">
                                                        <?= ucfirst(htmlspecialchars($issue['issue_type'])) ?>
                                                    </span>
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                                        <?php
                                                        switch($issue['status']) {
                                                            case 'not_assigned': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                                                            case 'in_progress': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                                            default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                                                        }
                                                        ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $issue['status'])) ?>
                                                    </span>
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-dark-text-secondary">
                                                    <p><?= ucfirst(htmlspecialchars($issue['hostel_type'])) ?> Hostel • <?= $issue['votes'] ?> votes</p>
                                                    <p><?= date('M j, g:i A', strtotime($issue['created_at'])) ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($hostel_issues) > 5): ?>
                                        <a href="hostel_issues.php" class="text-blue-600 dark:text-blue-400 text-sm font-medium hover:underline mt-3 inline-block">
                                            View all hostel issues →
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Complaint Details Modal -->
    <div id="complaint-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-2 md:p-4">
            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-xl w-full max-w-md max-h-[95vh] md:max-h-[90vh] overflow-y-auto">
                <div class="p-4 md:p-6 border-b border-gray-200 dark:border-dark-border">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">Complaint Details</h3>
                        <button onclick="closeComplaintModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                </div>
                <div id="complaint-modal-content" class="p-4 md:p-6">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const menuButton = document.getElementById('mobile-menu-button');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        if (menuButton) {
            menuButton.addEventListener('click', toggleSidebar);
        }
        if (backdrop) {
            backdrop.addEventListener('click', toggleSidebar);
        }

        // View complaint details
        function viewComplaintDetails(token) {
            fetch('get_complaint_details.php?token=' + encodeURIComponent(token))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('complaint-modal');
                        const content = document.getElementById('complaint-modal-content');
                        
                        content.innerHTML = `
                            <div class="space-y-4 md:space-y-6">
                                <!-- User & Location Info -->
                                <div class="grid grid-cols-1 gap-3 md:gap-4">
                                    <div class="info-item">
                                        <i class="material-icons info-icon">person</i>
                                        <div>
                                            <p class="info-label">Name</p>
                                            <p class="info-value">${escapeHtml(data.complaint.full_name)}</p>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="material-icons info-icon">door_front</i>
                                        <div>
                                            <p class="info-label">Room No.</p>
                                            <p class="info-value">${escapeHtml(data.complaint.room_no)}</p>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <i class="material-icons info-icon">phone</i>
                                        <div>
                                            <p class="info-label">Phone</p>
                                            <a href="tel:${escapeHtml(data.complaint.phone)}" class="info-value link">${escapeHtml(data.complaint.phone)}</a>
                                        </div>
                                    </div>
                                    ${data.complaint.hostel_type ? `
                                    <div class="info-item">
                                        <i class="material-icons info-icon">home</i>
                                        <div>
                                            <p class="info-label">Hostel Type</p>
                                            <p class="info-value capitalize">${escapeHtml(data.complaint.hostel_type.replace('_', ' '))}</p>
                                        </div>
                                    </div>
                                    ` : ''}
                                </div>

                                <!-- Complaint Details -->
                                <div class="info-group">
                                    <h3 class="info-group-title">Complaint Details</h3>
                                    <div class="grid grid-cols-1 gap-3 md:gap-4">
                                        <div class="info-item">
                                            <i class="material-icons info-icon">category</i>
                                            <div>
                                                <p class="info-label">Category</p>
                                                <p class="info-value capitalize">${escapeHtml(data.complaint.category)}</p>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class="material-icons info-icon">flag</i>
                                            <div>
                                                <p class="info-label">Current Status</p>
                                                <span class="status-badge status-${data.complaint.status.replace('_', '-')}">
                                                    ${escapeHtml(data.complaint.status.replace('_', ' '))}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <i class="material-icons info-icon">schedule</i>
                                            <div>
                                                <p class="info-label">Submitted On</p>
                                                <p class="info-value">${new Date(data.complaint.created_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description -->
                                ${data.complaint.description ? `
                                <div class="info-group">
                                    <h3 class="info-group-title">Description</h3>
                                    <div class="description-box">
                                        <p>${escapeHtml(data.complaint.description)}</p>
                                    </div>
                                </div>
                                ` : ''}

                                <!-- Status Update Section -->
                                <div class="info-group">
                                    <h3 class="info-group-title">Update Status</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label for="status-select" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">New Status</label>
                                            <select id="status-select" class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                                                <option value="in_progress">In Progress</option>
                                                <option value="rejected">Rejected</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label for="tech-remarks" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Remarks (Optional)</label>
                                            <textarea id="tech-remarks" rows="3" placeholder="Add any notes about the work performed or reason for status change..." class="w-full px-3 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="pt-4 space-y-3">
                                    <button onclick="updateComplaintStatus('${escapeHtml(data.complaint.token)}')" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition-colors">
                                        Update Status
                                    </button>
                                    <button onclick="closeComplaintModal()" class="w-full bg-gray-100 dark:bg-dark-border hover:bg-gray-200 dark:hover:bg-dark-bg-primary text-gray-700 dark:text-dark-text-primary px-4 py-3 rounded-lg font-medium transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        modal.classList.remove('hidden');
                    } else {
                        alert('Failed to load complaint details: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load complaint details. Please try again.');
                });
        }

        // Update complaint status
        function updateComplaintStatus(token) {
            const statusSelect = document.getElementById('status-select');
            const remarksTextarea = document.getElementById('tech-remarks');
            
            const newStatus = statusSelect.value;
            const remarks = remarksTextarea.value.trim();
            
            if (!newStatus) {
                alert('Please select a status');
                return;
            }
            
            // Show loading state
            const updateBtn = event.target;
            const originalText = updateBtn.textContent;
            updateBtn.textContent = 'Updating...';
            updateBtn.disabled = true;
            
            // Prepare form data
            const formData = new FormData();
            formData.append('token', token);
            formData.append('status', newStatus);
            formData.append('tech_remark', remarks);
            
            fetch('update_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated successfully!');
                    closeComplaintModal();
                    // Reload the page to show updated data
                    location.reload();
                } else {
                    alert('Failed to update status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update status. Please try again.');
            })
            .finally(() => {
                // Reset button state
                updateBtn.textContent = originalText;
                updateBtn.disabled = false;
            });
        }

        // Close complaint modal
        function closeComplaintModal() {
            const modal = document.getElementById('complaint-modal');
            modal.classList.add('hidden');
        }

        // Escape HTML function
        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Check for new assignments periodically
        function checkNewAssignments() {
            fetch('check_new_assignments.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_new_assignments) {
                        // Show notification
                        showAssignmentNotification(data);
                    }
                })
                .catch(error => {
                    console.error('Error checking for new assignments:', error);
                });
        }

        // Show assignment notification
        function showAssignmentNotification(data) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50 max-w-sm';
            notification.innerHTML = `
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="lucide-bell w-5 h-5"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium">${data.message}</p>
                        <p class="text-xs opacity-90 mt-1">
                            ${data.new_complaints > 0 ? `${data.new_complaints} new complaint(s)` : ''}
                            ${data.new_hostel_issues > 0 ? `${data.new_hostel_issues} new hostel issue(s)` : ''}
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <button onclick="this.parentElement.parentElement.remove()" class="text-white hover:text-gray-200">
                            <i class="lucide-x w-4 h-4"></i>
                        </button>
                    </div>
                </div>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto-remove after 10 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 10000);
        }

        // Start checking for new assignments every 30 seconds
        setInterval(checkNewAssignments, 30000);
        
        // Check once when page loads
        setTimeout(checkNewAssignments, 5000);
    </script>
    <?php include 'help_guide.php'; ?>
</body>
</html> 