<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/hostel_issue_functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

if (!isset($_GET['id'])) {
    redirect('dashboard.php?error=noissueid');
}

$issue_id = intval($_GET['id']);
$issue = getHostelIssueDetails($mysqli, $issue_id);
if (!$issue) {
    redirect('dashboard.php?error=invalidissueid');
}

// Fetch technicians for dropdown
$technicians = [];
$tech_query = "SELECT id, full_name, specialization FROM users WHERE role = 'technician'";
$result = $mysqli->query($tech_query);
while($row = $result->fetch_assoc()){
    $technicians[] = $row;
}

// Handle form submission for updating issue
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status = $_POST['status'] ?? $issue['status'];
    $technician_id = $_POST['technician_id'] ?? $issue['technician_id'];
    $remarks = $_POST['remarks'] ?? '';

    // If technician_id is empty, set to NULL
    if ($technician_id === '' || $technician_id === null) {
        $stmt = $mysqli->prepare("UPDATE hostel_issues SET status = ?, technician_id = NULL, tech_remarks = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssi', $new_status, $remarks, $issue_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE hostel_issues SET status = ?, technician_id = ?, tech_remarks = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('sisi', $new_status, $technician_id, $remarks, $issue_id);
    }
    $stmt->execute();
    $stmt->close();

    // Refresh issue data after update
    $issue = getHostelIssueDetails($mysqli, $issue_id);
    $_SESSION['success_message'] = "Hostel issue updated successfully!";
    redirect("hostel_issue_details.php?id=$issue_id");
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Issue Details - <?= htmlspecialchars($issue_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .glassmorphism { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.18); }
        @keyframes hamburger-pulse {
            0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
            70% { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        .animate-hamburger-pulse {
            animation: hamburger-pulse 2s infinite;
        }
        .header-offset { margin-left: 0; }
        @media (max-width: 600px) {
            .header-offset { margin-left: 56px; }
            .main-content {
                padding-top: 4.5rem !important;
            }
            #menu-toggle {
                width: 44px;
                height: 44px;
                top: 0.75rem;
                left: 0.75rem;
                padding: 0;
                z-index: 60 !important;
                transition: all 0.3s ease !important;
            }

            /* Hide hamburger menu when sidebar is active on small screens */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
            #menu-toggle svg {
                width: 20px;
                height: 20px;
            }
            .grid-cols-1, .md\:grid-cols-2, .lg\:col-span-2 {
                grid-template-columns: 1fr !important;
            }
            .glassmorphism {
                padding: 1rem !important;
            }
            .text-xl, .text-lg, .text-base {
                font-size: 1.1rem !important;
            }
            .text-sm {
                font-size: 1rem !important;
            }
            .p-3, .p-6 {
                padding: 0.75rem !important;
            }
        }
        @media (min-width: 601px) and (max-width: 900px) { .header-offset { margin-left: 72px; } }
        @media (min-width: 901px) { .header-offset { margin-left: 60px; } }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s cubic-bezier(.4,0,.2,1); position: fixed; z-index: 50; left: 0; top: 0; width: 80vw; max-width: 320px; height: 100vh; box-shadow: 2px 0 16px rgba(0,0,0,0.12); background: #0f172a; }
        .sidebar.active { transform: translateX(0); display: flex !important; }
        #sidebar-overlay.active { display: block; }
        .sidebar.hidden { display: none !important; }
        .main-content { margin-left: 0 !important; padding: 1rem !important; padding-top: 5rem !important; width: 100vw !important; max-width: 100vw !important; }
        @media (min-width: 769px) { .sidebar { width: 320px; } }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarClose = document.getElementById('sidebar-close');
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                sidebar.classList.remove('hidden');
                sidebarOverlay.classList.add('active');
                sidebarOverlay.classList.remove('hidden');
                menuToggle.classList.add('sidebar-active');
                sidebar.focus();
            });
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebar.classList.add('hidden');
                sidebarOverlay.classList.remove('active');
                menuToggle.classList.remove('sidebar-active');
                setTimeout(()=>sidebarOverlay.classList.add('hidden'), 300);
            }
            sidebarOverlay.addEventListener('click', closeSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
            window.addEventListener('resize', () => { closeSidebar(); });

            // Close sidebar when pressing Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });
    </script>
</head>
<body class="bg-slate-50">
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
        <!-- Main Content (always full width) -->
        <div class="main-content flex-1 flex flex-col px-4 sm:px-6 md:px-8 max-w-7xl mx-auto w-full">
            <!-- Header -->
            <header class="py-6 header-offset">
                <h1 class="text-3xl font-bold text-slate-900">Hostel Issue Details</h1>
            </header>
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-4" role="alert">
                    <p><?= $_SESSION['success_message'] ?></p>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="glassmorphism rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900 capitalize">
                                    <?= htmlspecialchars($hostel_types[$issue['hostel_type']] ?? $issue['hostel_type']) ?> - <?= htmlspecialchars($issue_types[$issue['issue_type']] ?? $issue['issue_type']) ?>
                                </h2>
                                <p class="text-sm text-slate-500">ID: <?= htmlspecialchars($issue['id']) ?></p>
                            </div>
                            <span class="px-3 py-1 text-sm font-medium rounded-full <?= getStatusBadgeColor($issue['status']) ?>">
                                <?= ucfirst(str_replace('_',' ',$issue['status'])) ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                            <div>
                                <p class="text-slate-500">Assigned Technician</p>
                                <p class="font-medium text-slate-800">
                                    <?= htmlspecialchars($issue['technician_name'] ?? 'Not Assigned') ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500">Votes</p>
                                <p class="font-medium text-slate-800">
                                    <?= $issue['votes'] ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500">Created On</p>
                                <p class="font-medium text-slate-800">
                                    <?= date('M d, Y - h:i A', strtotime($issue['created_at'])) ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-slate-500">Last Updated</p>
                                <p class="font-medium text-slate-800">
                                    <?= date('M d, Y - h:i A', strtotime($issue['updated_at'])) ?>
                                </p>
                            </div>
                        </div>
                        <div class="mt-6">
                            <p class="text-slate-500">Technician Remarks</p>
                            <p class="font-medium text-slate-800 bg-slate-100 p-3 rounded-lg">
                                <?= !empty($issue['tech_remarks']) ? nl2br(htmlspecialchars($issue['tech_remarks'])) : 'No remarks yet.' ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="glassmorphism rounded-2xl shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-slate-900 mb-4">Update Hostel Issue</h2>
                        <form method="POST">
                            <div class="mb-4">
                                <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                                <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm rounded-md">
                                    <option value="not_assigned" <?= $issue['status'] === 'not_assigned' ? 'selected' : '' ?>>Not Assigned</option>
                                    <option value="in_progress" <?= $issue['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $issue['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="technician_id" class="block text-sm font-medium text-slate-700">Assign Technician</label>
                                <select id="technician_id" name="technician_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-slate-300 focus:outline-none focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm rounded-md">
                                    <option value="">-- Select Technician --</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>" <?= $issue['technician_id'] == $tech['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tech['full_name']) ?> (<?= htmlspecialchars($tech['specialization']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label for="remarks" class="block text-sm font-medium text-slate-700">Remarks (Optional)</label>
                                <textarea id="remarks" name="remarks" rows="3" class="mt-1 block w-full shadow-sm sm:text-sm border-slate-300 rounded-md"><?= htmlspecialchars($issue['tech_remarks'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                Update Hostel Issue
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="mt-6">
                <a href="dashboard.php" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium">
                    &larr; Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html> 