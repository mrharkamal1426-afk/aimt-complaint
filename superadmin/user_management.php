<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in and is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../login.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    // Prevent deleting superadmin or self
    if ($user_id != $_SESSION['user_id']) {
        
        // Check if the user is a technician
        $stmt = $mysqli->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && $user['role'] === 'technician') {
            // Handle technician deletion with complaint and hostel issue reassignment
            $reassignment_result = handle_technician_deletion($user_id);
            
            if ($reassignment_result['success']) {
                $message_parts = [];
                $message_parts[] = "Technician '{$reassignment_result['technician_name']}' ({$reassignment_result['specialization']}) deleted successfully.";
                
                if ($reassignment_result['total_complaints'] > 0) {
                    $message_parts[] = "Complaints: {$reassignment_result['reassigned_complaints']} reassigned, {$reassignment_result['failed_complaints']} failed.";
                }
                
                if ($reassignment_result['total_hostel_issues'] > 0) {
                    $message_parts[] = "Hostel issues: {$reassignment_result['reassigned_hostel_issues']} reassigned, {$reassignment_result['failed_hostel_issues']} failed.";
                }
                
                $message = implode(' ', $message_parts);
                $message_type = ($reassignment_result['failed_complaints'] > 0 || $reassignment_result['failed_hostel_issues'] > 0) ? 'warning' : 'success';
            } else {
                $message = "Error deleting technician: " . $reassignment_result['error'];
                $message_type = 'error';
            }
        } else {
            // Regular user deletion
            $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role != 'superadmin'");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $message = "User deleted successfully.";
            $message_type = 'success';
        }
        
        // Store message in session for display
        $_SESSION['user_management_message'] = $message;
        $_SESSION['user_management_message_type'] = $message_type;
        
        header('Location: user_management.php');
        exit;
    }
}

// Handle search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build query with filters
$where_conditions = ["role != 'superadmin'"];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR username LIKE ? OR phone LIKE ? OR specialization LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

$where_clause = implode(' AND ', $where_conditions);
$sql = "SELECT id, full_name, username, phone, role, specialization, created_at FROM users WHERE $where_clause ORDER BY created_at DESC";

$stmt = $mysqli->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get unique roles for filter dropdown
$roles_result = $mysqli->query("SELECT DISTINCT role FROM users WHERE role != 'superadmin' ORDER BY role");
$roles = [];
while ($role_row = $roles_result->fetch_assoc()) {
    $roles[] = $role_row['role'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Complaint Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Sidebar styles */
        .sidebar {
            transition: transform 0.3s ease-in-out;
        }
        
        .sidebar-link {
            transition: all 0.2s ease;
        }
        
        .sidebar-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }
        
        .gradient-border {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo-glow {
            filter: drop-shadow(0 0 8px rgba(255, 255, 255, 0.3));
        }
        
        /* Mobile menu button styles */
        #menu-toggle {
            transition: all 0.2s ease;
        }
        
        #menu-toggle:hover {
            transform: scale(1.05);
        }
        
        #menu-toggle:active {
            transform: scale(0.95);
        }
        
        /* Hamburger animation */
        .animate-hamburger-pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(5, 150, 105, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(5, 150, 105, 0);
            }
        }
        
        /* Responsive sidebar */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar:not(.hidden) {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0.75rem !important;
                padding-top: 5rem !important; /* Add space for hamburger menu */
            }

            /* Improved hamburger menu positioning */
            #menu-toggle {
                top: 1rem !important;
                left: 1rem !important;
                width: 48px !important;
                height: 48px !important;
                z-index: 60 !important;
                transition: all 0.3s ease !important;
            }

            /* Hide hamburger menu when sidebar is active */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }

            /* Mobile header improvements */
            .mobile-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .mobile-header h1 {
                font-size: 2rem !important;
            }

            /* Mobile table improvements */
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

            /* Mobile form improvements */
            .mobile-form {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }

            .mobile-form input,
            .mobile-form select {
                width: 100%;
                padding: 0.75rem;
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .main-content {
                padding: 0.5rem !important;
                padding-top: 4.5rem !important;
            }

            .mobile-header h1 {
                font-size: 1.5rem !important;
            }

            /* Adjust hamburger menu for very small screens */
            #menu-toggle {
                top: 0.75rem !important;
                left: 0.75rem !important;
                width: 44px !important;
                height: 44px !important;
            }

            /* Hide hamburger menu when sidebar is active on small screens */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Menu Button (always visible, improved style) -->
    <button id="menu-toggle"
        class="fixed top-4 left-4 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-white/90 shadow-2xl border border-emerald-300 hover:bg-emerald-100 focus:bg-emerald-200 transition-all duration-200 outline-none ring-emerald-400 ring-offset-2 ring-offset-white focus:ring-4 animate-hamburger-pulse"
        aria-label="Toggle navigation menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="block">
            <line x1="4" y1="6" x2="20" y2="6" />
            <line x1="4" y1="12" x2="20" y2="12" />
            <line x1="4" y1="18" x2="20" y2="18" />
        </svg>
    </button>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden"></div>
    <div class="flex min-h-screen">
        <!-- Sidebar (hidden by default, overlays content) -->
        <aside class="sidebar w-64 bg-slate-900 text-white flex flex-col fixed h-full shadow-xl z-50 hidden" tabindex="-1">
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
                <!-- Primary Navigation -->
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-layout-dashboard mr-3 text-slate-400"></i>
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
                <a href="user_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl bg-slate-800/50">
                    <i class="lucide-users mr-3 text-emerald-400"></i>
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
                
                <!-- Account (Low Priority) -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                        <i class="lucide-log-out mr-3 text-slate-400"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 main-content">
        <!-- Breadcrumb Navigation -->
        <nav class="mb-6" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-sm text-gray-500">
                <li>
                    <a href="dashboard.php" class="hover:text-emerald-600 transition-colors">
                        <i class="fas fa-home mr-1"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </li>
                <li class="text-emerald-600 font-medium">
                    <i class="fas fa-users-cog mr-1"></i>
                    User Management
                </li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="mb-8">
    <div class="flex flex-wrap justify-between items-start gap-4 md:items-center md:flex-nowrap">
        <!-- Title and Description -->
        <div class="flex-1 min-w-[250px]">
            <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center flex-wrap">
                <i class="fas fa-users-cog text-emerald-600 mr-3"></i>
                User Management
            </h1>
            <p class="text-gray-600 text-base sm:text-lg leading-snug">
                Manage all users, technicians, and administrators in the complaint portal system.
            </p>
        </div>

        <!-- Stats Badges -->
        <div class="flex flex-wrap md:flex-nowrap items-stretch gap-4">
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl px-5 py-3 shadow-sm min-w-[140px]">
                <div class="text-sm text-emerald-600 font-semibold tracking-wide">
                    Total Users
                </div>
                <div class="text-2xl font-extrabold text-emerald-700 mt-1">
                    <?php 
                        $total_users = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role != 'superadmin'")->fetch_assoc()['count'];
                        echo $total_users;
                    ?>
                </div>
            </div>
            <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3 shadow-sm min-w-[140px]">
                <div class="text-sm text-blue-600 font-semibold tracking-wide">
                    Created Today
                </div>
                <div class="text-2xl font-extrabold text-blue-700 mt-1">
                    <?php 
                        $created_today = $mysqli->query("SELECT COUNT(*) as count FROM users WHERE role != 'superadmin' AND DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
                        echo $created_today;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Message Display -->
        <?php if (isset($_SESSION['user_management_message'])): ?>
            <div class="mb-6 p-4 rounded-lg <?= $_SESSION['user_management_message_type'] === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : ($_SESSION['user_management_message_type'] === 'warning' ? 'bg-yellow-50 border border-yellow-200 text-yellow-800' : 'bg-red-50 border border-red-200 text-red-800') ?>">
                <?= htmlspecialchars($_SESSION['user_management_message']) ?>
            </div>
            <?php 
            unset($_SESSION['user_management_message']);
            unset($_SESSION['user_management_message_type']);
            ?>
        <?php endif; ?>

        <!-- Search and Filter Section -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 mobile-form">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Users</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search by name, username, phone..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
                
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Filter by Role</label>
                    <select id="role" 
                            name="role" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role) ?>" <?= $role_filter === $role ? 'selected' : '' ?>>
                                <?= ucfirst(htmlspecialchars($role)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" 
                            class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors">
                        Search
                    </button>
                </div>
            </form>
            
            <?php if (!empty($search) || !empty($role_filter)): ?>
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-600">
                        Filtered results
                        <?php if (!empty($search)): ?>
                            for "<?= htmlspecialchars($search) ?>"
                        <?php endif; ?>
                        <?php if (!empty($role_filter)): ?>
                            in role "<?= ucfirst(htmlspecialchars($role_filter)) ?>"
                        <?php endif; ?>
                    </div>
                    <a href="user_management.php" class="text-blue-600 hover:text-blue-800 text-sm">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Specialization</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                                <span class="text-blue-600 font-medium text-sm">
                                                    <?= strtoupper(substr($row['full_name'], 0, 2)) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['full_name']) ?></div>
                                                <div class="text-sm text-gray-500">@<?= htmlspecialchars($row['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($row['phone']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                            <?php
                                            switch($row['role']) {
                                                case 'admin': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'technician': echo 'bg-green-100 text-green-800'; break;
                                                case 'user': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst(htmlspecialchars($row['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= htmlspecialchars($row['specialization'] ?: 'N/A') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($row['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-3">
                                            <a href="edit_user.php?id=<?= $row['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900">
                                                Edit
                                            </a>
                                            <a href="user_management.php?delete=<?= $row['id'] ?>" 
                                               class="text-red-600 hover:text-red-900"
                                               onclick="return confirm('Are you sure you want to delete this user?');">
                                                Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-gray-500">
                                        <i class="fas fa-users text-3xl mb-3 opacity-50"></i>
                                        <p class="text-lg font-medium">No users found</p>
                                        <p class="text-sm">Try adjusting your search criteria</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Back Button -->
        <div class="text-center mt-8">
            <a href="dashboard.php" 
               class="inline-flex items-center px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change
        document.getElementById('role').addEventListener('change', function() {
            this.form.submit();
        });

        // Mobile menu toggle functionality
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            
            function toggleMobileMenu() {
                const isActive = sidebar.classList.contains('hidden');
                
                if (isActive) {
                    // Open menu
                    sidebar.classList.remove('hidden');
                    sidebarOverlay.classList.remove('hidden');
                    menuToggle.classList.add('sidebar-active');
                    document.body.style.overflow = 'hidden';
                } else {
                    // Close menu
                    sidebar.classList.add('hidden');
                    sidebarOverlay.classList.add('hidden');
                    menuToggle.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                }
            }
            
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMobileMenu();
            });

            // Close sidebar when clicking overlay
            sidebarOverlay.addEventListener('click', () => {
                toggleMobileMenu();
            });

            // Close sidebar when clicking outside
            document.addEventListener('click', (event) => {
                if (window.innerWidth <= 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    !sidebar.classList.contains('hidden')) {
                    toggleMobileMenu();
                }
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.add('hidden');
                    sidebarOverlay.classList.add('hidden');
                    menuToggle.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                }
            });

            // Close menu when pressing Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !sidebar.classList.contains('hidden')) {
                    toggleMobileMenu();
                }
            });
        });
    </script>
</body>
</html> 