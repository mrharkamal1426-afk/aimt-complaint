<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/hostel_issue_functions.php';

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

// Handle filters
$hostel_filter = isset($_GET['hostel']) ? $_GET['hostel'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$issue_filter = isset($_GET['issue']) ? $_GET['issue'] : 'all';

$filters = [
    'hostel' => $hostel_filter,
    'status' => $status_filter,
    'issue' => $issue_filter
];

// Get hostel issues for this technician
$hostel_issues = getHostelIssuesForTechnician($mysqli, $tech_id, $filters);

// Calculate statistics
$total_issues = count($hostel_issues);
$not_assigned = count(array_filter($hostel_issues, fn($i) => $i['status'] === 'not_assigned'));
$in_progress = count(array_filter($hostel_issues, fn($i) => $i['status'] === 'in_progress'));
$resolved = count(array_filter($hostel_issues, fn($i) => $i['status'] === 'resolved'));

// Get issue type counts for filter options
$issue_types = [
    'wifi' => 'Wi-Fi Issues',
    'water' => 'Water Issues', 
    'mess' => 'Mess Issues',
    'electricity' => 'Electricity Issues',
    'cleanliness' => 'Cleanliness Issues',
    'other' => 'Other Issues'
];
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hostel Issues | Technician Dashboard</title>
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
                <a href="complaints.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">assignment</span>
                    <span>All Complaints</span>
                </a>
                <a href="hostel_issues.php" class="flex items-center px-4 py-3 rounded-lg bg-blue-700/50 dark:bg-blue-800/50 hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">campaign</span>
                    <span>Hostel Issues</span>
                </a>
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
                            <h1 class="text-base sm:text-lg md:text-xl font-bold truncate">Hostel Issues</h1>
                            <p class="hidden md:block text-sm text-blue-100">Manage hostel-wide complaints and issues</p>
                        </div>
                    </div>

                    <!-- Spacer for desktop -->
                    <div class="hidden md:block flex-1"></div>

                    <!-- No QR scanner for hostel issues -->
                    <div class="flex items-center">
                        <div class="text-blue-100 text-sm">Hostel-wide issues</div>
                    </div>
                </div>
            </header>

            <!-- Content Container -->
            <div class="flex-1 p-4 md:p-6 space-y-6">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Total Issues</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-dark-text-primary"><?= $total_issues ?></p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <span class="material-icons text-blue-600 dark:text-blue-400">campaign</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Not Assigned</p>
                                <p class="text-2xl font-bold text-red-600"><?= $not_assigned ?></p>
                            </div>
                            <div class="p-3 bg-red-100 dark:bg-red-900/30 rounded-lg">
                                <span class="material-icons text-red-600 dark:text-red-400">assignment_late</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">In Progress</p>
                                <p class="text-2xl font-bold text-blue-600"><?= $in_progress ?></p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <span class="material-icons text-blue-600 dark:text-blue-400">engineering</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6 card-hover">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-dark-text-secondary">Resolved</p>
                                <p class="text-2xl font-bold text-green-600"><?= $resolved ?></p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <span class="material-icons text-green-600 dark:text-green-400">check_circle</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border p-6">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="hostel" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Hostel Type</label>
                            <select id="hostel" name="hostel" 
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                                <option value="all" <?= $hostel_filter === 'all' ? 'selected' : '' ?>>All Hostels</option>
                                <option value="boys" <?= $hostel_filter === 'boys' ? 'selected' : '' ?>>Boys Hostel</option>
                                <option value="girls" <?= $hostel_filter === 'girls' ? 'selected' : '' ?>>Girls Hostel</option>
                            </select>
                        </div>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Status</label>
                            <select id="status" name="status" 
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                                <option value="not_assigned" <?= $status_filter === 'not_assigned' ? 'selected' : '' ?>>Not Assigned</option>
                                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            </select>
                        </div>
                        <div>
                            <label for="issue" class="block text-sm font-medium text-gray-700 dark:text-dark-text-secondary mb-2">Issue Type</label>
                            <select id="issue" name="issue" 
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-dark-border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-dark-bg-primary dark:text-dark-text-primary">
                                <option value="all" <?= $issue_filter === 'all' ? 'selected' : '' ?>>All Issues</option>
                                <?php foreach ($issue_types as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $issue_filter === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-3 flex justify-end">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Hostel Issues List -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border">
                    <div class="p-6 border-b border-gray-200 dark:border-dark-border">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">
                            Hostel Issues (<?= $total_issues ?> found)
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($hostel_issues)): ?>
                            <div class="text-center py-12">
                                <span class="material-icons text-gray-400 text-6xl mb-4">campaign</span>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-dark-text-primary mb-2">No hostel issues found</h3>
                                <p class="text-gray-500 dark:text-dark-text-secondary">
                                    <?php if ($hostel_filter !== 'all' || $status_filter !== 'all' || $issue_filter !== 'all'): ?>
                                        Try adjusting your filters to see more results.
                                    <?php else: ?>
                                        No hostel-wide issues have been reported yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($hostel_issues as $issue): ?>
                                    <div class="border border-gray-200 dark:border-dark-border rounded-lg p-6 hover:bg-gray-50 dark:hover:bg-dark-bg-primary transition-colors">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-3">
                                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">
                                                        <?= $issue_types[$issue['issue_type']] ?? ucfirst($issue['issue_type']) ?>
                                                    </h3>
                                                    <span class="px-3 py-1 text-sm font-medium rounded-full status-badge
                                                        <?php
                                                        switch($issue['status']) {
                                                            case 'not_assigned': echo 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'; break;
                                                            case 'in_progress': echo 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'; break;
                                                            case 'resolved': echo 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'; break;
                                                            default: echo 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
                                                        }
                                                        ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $issue['status'])) ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mb-4">
                                                    <div>
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Hostel</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary"><?= ucfirst(htmlspecialchars($issue['hostel_type'])) ?> Hostel</p>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Votes</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary"><?= $issue['votes'] ?> students</p>
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Reported</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary"><?= date('M j, Y g:i A', strtotime($issue['created_at'])) ?></p>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($issue['technician_name']): ?>
                                                    <div class="mb-4">
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Assigned To</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary"><?= htmlspecialchars($issue['technician_name']) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($issue['tech_remarks']): ?>
                                                    <div class="mb-4">
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Remarks</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary text-sm bg-gray-50 dark:bg-dark-bg-primary p-3 rounded-lg"><?= nl2br(htmlspecialchars($issue['tech_remarks'])) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($issue['status'] === 'resolved'): ?>
                                                    <div class="mt-4">
                                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary mb-2">Resolved On</p>
                                                        <p class="text-gray-900 dark:text-dark-text-primary"><?= date('M j, Y g:i A', strtotime($issue['updated_at'])) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="flex flex-col space-y-2 ml-6">
                                                <?php if ($issue['status'] === 'not_assigned'): ?>
                                                    <button onclick="assignIssue(<?= $issue['id'] ?>)" 
                                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                                        Assign to Me
                                                    </button>
                                                <?php elseif ($issue['status'] === 'in_progress'): ?>
                                                    <button onclick="updateIssueStatus(<?= $issue['id'] ?>, 'resolved')" 
                                                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                                        Mark Resolved
                                                    </button>
                                                    <button onclick="updateIssueStatus(<?= $issue['id'] ?>, 'in_progress')" 
                                                            class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                                        Update Progress
                                                    </button>
                                                <?php elseif ($issue['status'] === 'resolved'): ?>
                                                    <button onclick="updateIssueStatus(<?= $issue['id'] ?>, 'resolved')" 
                                                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                                        Update Remarks
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button onclick="viewIssueDetails(<?= $issue['id'] ?>)" 
                                                        class="bg-gray-100 dark:bg-dark-border hover:bg-gray-200 dark:hover:bg-dark-bg-primary text-gray-700 dark:text-dark-text-primary px-4 py-2 rounded-lg text-sm font-medium transition-colors">
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

    <!-- Issue Details Modal -->
    <div id="issue-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-gray-200 dark:border-dark-border">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">Issue Details</h3>
                        <button onclick="closeIssueModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <span class="material-icons">close</span>
                        </button>
                    </div>
                </div>
                <div id="issue-modal-content" class="p-6">
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

        // Assign issue to technician
        function assignIssue(issueId) {
            if (confirm('Are you sure you want to assign this issue to yourself?')) {
                const formData = new FormData();
                formData.append('issue_id', issueId);
                formData.append('action', 'assign');
                formData.append('remarks', '');
                
                fetch('update_hostel_issue.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Issue assigned successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while assigning the issue.');
                });
            }
        }

        // Update issue status
        function updateIssueStatus(issueId, newStatus) {
            let action = 'update_status';
            let title = 'Update Issue Status';
            let message = 'Are you sure you want to update this issue status?';
            
            if (newStatus === 'resolved') {
                title = 'Mark as Resolved';
                message = 'Are you sure you want to mark this issue as resolved?';
            } else if (newStatus === 'in_progress') {
                title = 'Update Progress';
                message = 'Update progress and add remarks:';
            }
            
            let remarks = '';
            if (newStatus === 'in_progress' || newStatus === 'resolved') {
                remarks = prompt('Add remarks (optional):');
                if (remarks === null) return; // User cancelled
            }
            
            if (confirm(message)) {
                const formData = new FormData();
                formData.append('issue_id', issueId);
                formData.append('action', action);
                formData.append('status', newStatus);
                formData.append('remarks', remarks || '');
                
                fetch('update_hostel_issue.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Issue updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating the issue.');
                });
            }
        }

        // View issue details
        function viewIssueDetails(issueId) {
            fetch(`get_hostel_issue_details.php?issue_id=${issueId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const issue = data.issue;
                        const modal = document.getElementById('issue-modal');
                        const content = document.getElementById('issue-modal-content');
                        
                        content.innerHTML = `
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Issue Type</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.issue_type}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Hostel</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.hostel_type}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Status</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.status}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Votes</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.votes} students</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Assigned To</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.technician_name}</p>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Reported On</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.created_at}</p>
                                    </div>
                                </div>
                                
                                ${issue.tech_remarks ? `
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary mb-2">Remarks</p>
                                        <div class="bg-gray-50 dark:bg-dark-bg-primary p-3 rounded-lg">
                                            <p class="text-gray-900 dark:text-dark-text-primary text-sm">${issue.tech_remarks.replace(/\n/g, '<br>')}</p>
                                        </div>
                                    </div>
                                ` : ''}
                                
                                ${issue.updated_at && issue.updated_at !== issue.created_at ? `
                                    <div>
                                        <p class="font-medium text-gray-600 dark:text-dark-text-secondary">Last Updated</p>
                                        <p class="text-gray-900 dark:text-dark-text-primary">${issue.updated_at}</p>
                                    </div>
                                ` : ''}
                                
                                <div class="pt-4 border-t border-gray-200 dark:border-dark-border">
                                    <button onclick="addRemarks(${issueId})" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                        Add/Update Remarks
                                    </button>
                                </div>
                            </div>
                        `;
                        
                        modal.classList.remove('hidden');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching issue details.');
                });
        }

        // Add remarks to issue
        function addRemarks(issueId) {
            const remarks = prompt('Enter remarks:');
            if (remarks === null) return; // User cancelled
            
            const formData = new FormData();
            formData.append('issue_id', issueId);
            formData.append('action', 'add_remarks');
            formData.append('remarks', remarks);
            
            fetch('update_hostel_issue.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Remarks added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding remarks.');
            });
        }

        function closeIssueModal() {
            document.getElementById('issue-modal').classList.add('hidden');
        }

        // Close modal on backdrop click
        document.getElementById('issue-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeIssueModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeIssueModal();
            }
        });
    </script>
</body>
</html>
