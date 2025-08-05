<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../login.php?error=unauthorized');
}

$tech_id = $_SESSION['user_id'];

// Get technician's specialization
$stmt = $mysqli->prepare("SELECT specialization FROM users WHERE id = ?");
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$stmt->bind_result($specialization);
$stmt->fetch();
$stmt->close();

if (!$specialization) {
    redirect('../login.php?error=no_specialization');
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the SQL query with filters
$where_conditions = ["(c.category = ? OR c.technician_id = ?)"];
$params = [$specialization, $tech_id];
$param_types = 'si';

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
    if ($status_filter === 'resolved') {
        $where_conditions[] = "c.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    }
}

if (!empty($search_query)) {
    $where_conditions[] = "(u.full_name LIKE ? OR c.room_no LIKE ? OR c.token LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$sql = "SELECT c.*, u.full_name, u.phone, u.hostel_type
        FROM complaints c 
        JOIN users u ON u.id = c.user_id 
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY c.created_at ASC";

$stmt = $mysqli->prepare($sql);
// Bind parameters for the dynamic filters (category, technician, status, search etc.)
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Calculate statistics
$total_complaints = count($complaints);
$pending_complaints = count(array_filter($complaints, fn($c) => $c['status'] === 'pending'));
$resolved_complaints = count(array_filter($complaints, fn($c) => $c['status'] === 'resolved'));
$assigned_complaints = count(array_filter($complaints, fn($c) => $c['assignment_type'] === 'admin_assigned'));
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>All Complaints | Technician Dashboard</title>
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

        @media (max-width: 640px) {
            .complaint-card-mobile {
                flex-direction: column !important;
                align-items: flex-start !important;
                padding: 1rem !important;
                gap: 0.75rem !important;
            }
            .complaint-card-mobile .flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }
            .complaint-card-mobile .w-12, .complaint-card-mobile .h-12 {
                width: 2.5rem !important;
                height: 2.5rem !important;
            }
            .complaint-card-mobile .text-lg {
                font-size: 1.1rem !important;
            }
            .complaint-card-mobile .text-sm {
                font-size: 0.97rem !important;
            }
            .complaint-card-mobile .text-xs {
                font-size: 0.85rem !important;
            }
            .complaint-card-mobile .w-full {
                width: 100% !important;
            }
            .complaint-card-mobile .mobile-btn {
                width: 100% !important;
                display: block !important;
                margin-top: 0.5rem !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-dark-bg-primary min-h-screen font-['Inter'] transition-colors duration-200">
    <!-- Sidebar Backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black bg-opacity-30 z-50 hidden md:hidden"></div>
    
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed md:static inset-y-0 left-0 transform -translate-x-full md:translate-x-0 z-[55] w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white flex flex-col transition-transform duration-200 ease-in-out shadow-xl">
            <div class="px-6 py-6 bg-gradient-to-r from-blue-700 to-blue-800 dark:from-blue-800 dark:to-blue-900 mt-16 md:mt-0">
                <div class="text-2xl font-bold">Complaint<span class="text-cyan-300">System</span></div>
            </div>
            <div class="px-6 py-4 border-b border-blue-700/50 dark:border-blue-800/50 bg-blue-800/30 dark:bg-blue-900/30">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Technician') ?></div>
                <div class="text-cyan-300 text-sm"><?= ucfirst(htmlspecialchars($specialization)) ?> Technician</div>
            </div>
            <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="scanner.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">qr_code_scanner</span>
                    <span>QR Scanner</span>
                </a>
                <a href="complaints.php" class="flex items-center px-4 py-3 rounded-lg bg-blue-700/50 dark:bg-blue-800/50 hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">assignment</span>
                    <span>All Complaints</span>
                </a>
                <a href="hostel_issues.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">campaign</span>
                    <span>Hostel Issues</span>
                </a>
                <!-- Help Guide Button -->
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
        <main id="main-content" class="flex-1 flex flex-col w-0 md:w-auto md:ml-0 transition-all duration-200">
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
                            <h1 class="text-base sm:text-lg md:text-xl font-bold truncate">All Complaints</h1>
                            <p class="hidden md:block text-sm text-blue-100"><?= ucfirst(htmlspecialchars($specialization)) ?> Specialization</p>
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

            <!-- Content Container -->
            <div class="flex-1 p-4 md:p-6 space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Total</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-dark-text-primary"><?= $total_complaints ?></p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <span class="material-icons text-blue-600 dark:text-blue-400">assignment</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Pending</p>
                                <p class="text-2xl font-bold text-orange-600"><?= $pending_complaints ?></p>
                            </div>
                            <div class="p-3 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                                <span class="material-icons text-orange-600 dark:text-orange-400">schedule</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Admin Assigned</p>
                                <p class="text-2xl font-bold text-purple-600"><?= $assigned_complaints ?></p>
                            </div>
                            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                                <span class="material-icons text-purple-600 dark:text-purple-400">admin_panel_settings</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Resolved</p>
                                <p class="text-2xl font-bold text-green-600"><?= $resolved_complaints ?></p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6">
                    <form method="GET" class="flex flex-col md:flex-row gap-4">
                        <div class="flex-1">
                            <label for="search" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Search</label>
                            <input type="text" id="search" name="search" value="<?= htmlspecialchars($search_query) ?>" 
                                   placeholder="Search by name or room number..." 
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                        </div>
                        <div class="md:w-48">
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Status</label>
                            <select id="status" name="status" 
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Complaints List -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border">
                    <div class="p-6 border-b border-gray-200 dark:border-dark-border">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">
                                Complaints (<?= $total_complaints ?> found)
                            </h2>
                            <?php if ($assigned_complaints > 0): ?>
                                <span class="text-sm text-purple-600 dark:text-purple-400 font-medium">
                                    <?= $assigned_complaints ?> admin assigned
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($complaints)): ?>
                            <div class="text-center py-12">
                                <span class="material-icons text-gray-400 text-6xl mb-4">search_off</span>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-dark-text-primary mb-2">No complaints found</h3>
                                <p class="text-gray-500 dark:text-dark-text-secondary">
                                    <?php if (!empty($search_query) || $status_filter !== 'all'): ?>
                                        Try adjusting your search criteria or filters.
                                    <?php else: ?>
                                        No complaints have been assigned to your specialization yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($complaints as $complaint): ?>
                                    <div class="border border-gray-200 dark:border-dark-border rounded-lg p-6 hover:bg-gray-50 dark:hover:bg-dark-bg-primary transition-colors
                                        <?= $complaint['assignment_type'] === 'admin_assigned' ? 'bg-purple-50 dark:bg-purple-900/10 border-l-4 border-purple-500' : '' ?> complaint-card-mobile">
                                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                            <div class="flex-1 w-full">
                                                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 mb-3 w-full">
                                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-full flex items-center justify-center">
                                                        <span class="material-icons text-blue-600 dark:text-blue-400">person</span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary truncate">
                                                            <?= htmlspecialchars($complaint['full_name']) ?>
                                                        </h3>
                                                        <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 text-sm text-gray-600 dark:text-dark-text-secondary w-full">
                                                            <span class="flex items-center">
                                                                <span class="material-icons text-xs mr-1">door_front</span>
                                                                Room <?= htmlspecialchars($complaint['room_no']) ?>
                                                            </span>
                                                            <a href="tel:<?= htmlspecialchars($complaint['phone']) ?>" 
                                                               class="flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">
                                                                <span class="material-icons text-xs mr-1">phone</span>
                                                                <?= htmlspecialchars($complaint['phone']) ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 w-full">
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900 dark:text-dark-text-primary capitalize">
                                                            <?= htmlspecialchars($complaint['category']) ?>
                                                        </p>
                                                        <?php if ($complaint['hostel_type']): ?>
                                                            <p class="text-xs text-gray-500 dark:text-dark-text-secondary capitalize">
                                                                <?= htmlspecialchars(str_replace('_', ' ', $complaint['hostel_type'])) ?> Hostel
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-xs text-gray-500 dark:text-dark-text-secondary">
                                                            Submitted: <?= date('M j, g:i A', strtotime($complaint['created_at'])) ?>
                                                        </p>
                                                        <?php if ($complaint['updated_at'] !== $complaint['created_at']): ?>
                                                            <p class="text-xs text-gray-500 dark:text-dark-text-secondary">
                                                                Updated: <?= date('M j, g:i A', strtotime($complaint['updated_at'])) ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                                            <?php
                                                            switch($complaint['status']) {
                                                                case 'pending': echo 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400'; break;
                                                                case 'in_progress': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                                                case 'resolved': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                                                case 'rejected': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                                                                default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                                                            }
                                                            ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $complaint['status'])) ?>
                                                        </span>
                                                        <?php if ($complaint['assignment_type'] === 'admin_assigned'): ?>
                                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                                                <span class="material-icons text-xs mr-1">admin_panel_settings</span>
                                                                Admin Assigned
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <?php if (!empty($complaint['description'])): ?>
                                                    <div class="bg-gray-50 dark:bg-dark-bg-primary rounded-lg p-3 mb-4">
                                                        <p class="text-sm text-gray-700 dark:text-dark-text-secondary">
                                                            <?= nl2br(htmlspecialchars($complaint['description'])) ?>
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex flex-col space-y-2 w-full md:w-auto mt-2 md:mt-0">
                                                <button onclick="viewComplaintDetails('<?= $complaint['token'] ?>')" 
                                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 rounded-lg transition-colors mobile-btn w-full">
                                                    <span class="material-icons text-sm mr-1">visibility</span>
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Complaint Details Modal -->
    <div id="complaint-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 dark:border-dark-border">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">Complaint Details</h3>
                        <button onclick="closeComplaintModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                </div>
                <div id="complaint-modal-content" class="p-6">
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
            if (backdrop.classList.contains('hidden')) {
                document.body.style.overflow = '';
            } else {
                document.body.style.overflow = 'hidden';
            }
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
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Status</p>
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full
                                            ${getStatusBadgeClass(data.complaint.status)}">
                                            ${escapeHtml(data.complaint.status.replace('_', ' '))}
                                        </span>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Name</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${escapeHtml(data.complaint.full_name)}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Room</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${escapeHtml(data.complaint.room_no)}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-2 text-sm">
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Category</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${escapeHtml(data.complaint.category)}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Phone</p>
                                        <a href="tel:${escapeHtml(data.complaint.phone)}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 transition-colors">${escapeHtml(data.complaint.phone)}</a>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Hostel</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${escapeHtml(data.complaint.hostel_type || '-')}
                                    </div>
                                </div>
                                ${data.complaint.description ? `
                                <div class="pt-4 border-t border-gray-200 dark:border-dark-border">
                                    <p class="font-medium text-gray-600 dark:text-dark-text-secondary mb-2">Description</p>
                                    <div class="bg-gray-50 dark:bg-dark-bg-primary rounded-lg p-3 text-gray-700 dark:text-dark-text-primary text-sm">
                                        ${escapeHtml(data.complaint.description)}
                                    </div>
                                </div>
                                ` : ''}
                                ${data.complaint.tech_note ? `
                                <div class="pt-4 border-t border-gray-200 dark:border-dark-border">
                                    <p class="font-medium text-gray-600 dark:text-dark-text-secondary mb-2">Technician Notes</p>
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 text-gray-700 dark:text-dark-text-primary text-sm">
                                        ${escapeHtml(data.complaint.tech_note)}
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        `;
                        
                        modal.classList.remove('hidden');
                    } else {
                        alert('Failed to load complaint details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load complaint details');
                });
        }

        // Update complaint status
        function updateComplaintStatus(token, status) {
            if (!confirm(`Are you sure you want to mark this complaint as "${status.replace('_', ' ')}"?`)) {
                return;
            }
            
            // Show loading state
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Updating...';
            button.disabled = true;
            
            // Prepare form data
            const formData = new FormData();
            formData.append('token', token);
            formData.append('status', status);
            formData.append('tech_remark', ''); // Empty remarks for quick status updates
            
            fetch('update_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Status updated successfully!');
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
                button.textContent = originalText;
                button.disabled = false;
            });
        }

        // Get status badge class
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'pending': return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
                case 'in_progress': return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
                case 'resolved': return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
                case 'rejected': return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
                default: return 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
            }
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

        function closeComplaintModal() {
            document.getElementById('complaint-modal').classList.add('hidden');
        }

        // Close modal on backdrop click
        document.getElementById('complaint-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComplaintModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeComplaintModal();
            }
        });
    </script>
    <?php include 'help_guide.php'; ?>
</body>
</html> 