<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

$allowed_roles = ['student', 'faculty', 'nonteaching', 'technician', 'outsourced_vendor'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    redirect('../auth/login.php?error=unauthorized');
}

$user_id = $_SESSION['user_id'];

// Fetch all complaints for summary
$sql_all = "SELECT status FROM complaints WHERE user_id = ?";
$stmt_all = $mysqli->prepare($sql_all);
$stmt_all->bind_param('i', $user_id);
$stmt_all->execute();
$result_all = $stmt_all->get_result();

$complaints_all = [];
while ($row = $result_all->fetch_assoc()) {
    $complaints_all[] = $row;
}
$stmt_all->close();

// Summary
$total = count($complaints_all);
$resolved = count(array_filter($complaints_all, fn($c) => $c['status'] === 'resolved'));
$pending = count(array_filter($complaints_all, fn($c) => $c['status'] === 'pending'));

// Fetch only pending complaints for display with simplified technician status
$sql = "SELECT c.id, c.token, c.category, c.status, c.created_at, c.updated_at, 
        t.full_name as tech_name, t.phone as tech_phone, t.is_online as tech_online,
        (SELECT CONCAT(full_name, '|', phone, '|', is_online) 
         FROM users 
         WHERE role = 'technician' 
         AND specialization = c.category 
         AND (c.technician_id IS NULL OR c.technician_id = 0)
         ORDER BY is_online DESC, id ASC
         LIMIT 1) as available_tech,
        (SELECT COUNT(*) 
         FROM users 
         WHERE role = 'technician' 
         AND specialization = c.category) as total_techs,
        (SELECT CONCAT(full_name, '|', phone, '|', is_online) 
         FROM users 
         WHERE role = 'technician' 
         AND specialization = c.category 
         ORDER BY id ASC
         LIMIT 1) as any_tech
        FROM complaints c 
        LEFT JOIN users t ON t.id = c.technician_id 
        WHERE c.user_id = ? AND c.status IN ('pending', 'in_progress')
        ORDER BY c.created_at DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Note: Auto-reassignment will happen in background, but we'll show offline status first

// Hostel-wide issues section for students
$hostel_types = ['boys' => 'Boys', 'girls' => 'Girls'];
$issue_types = [
    'wifi' => 'Wi-Fi not working',
    'water' => 'No water',
    'mess' => 'Mess food issue',
    'electricity' => 'Electricity problem',
    'cleanliness' => 'Cleanliness',
    'other' => 'Other',
];

// Determine student's hostel type (fetch from DB for accuracy)
$student_hostel = 'boys';
if ($_SESSION['role'] === 'student') {
    $stmt = $mysqli->prepare("SELECT hostel_type FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($db_hostel_type);
    if ($stmt->fetch() && in_array($db_hostel_type, array_keys($hostel_types))) {
        $student_hostel = $db_hostel_type;
    }
    $stmt->close();
}

// Fetch active hostel-wide issues for the student's hostel
$stmt = $mysqli->prepare("SELECT hi.*, 
    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes,
    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id AND v.user_id = ?) as user_voted,
    hi.tech_remarks
    FROM hostel_issues hi
    WHERE hi.hostel_type = ? AND hi.status != 'resolved'
    ORDER BY hi.created_at DESC");
$stmt->bind_param('is', $user_id, $student_hostel);
$stmt->execute();
$hostel_issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 1. Fetch resolved complaints for notifications
$sql_resolved = "SELECT token, category, updated_at FROM complaints WHERE user_id = ? AND status = 'resolved' AND (NOT EXISTS (SELECT 1 FROM user_notifications WHERE user_id = ? AND complaint_token = token AND type = 'resolved')) ORDER BY updated_at DESC LIMIT 10";
$stmt_resolved = $mysqli->prepare($sql_resolved);
$stmt_resolved->bind_param('ii', $user_id, $user_id);
$stmt_resolved->execute();
$resolved_notifications = $stmt_resolved->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_resolved->close();

// 2. Fetch recent activity (complaint status changes)
$stmt = $mysqli->prepare("SELECT token, category, status, updated_at FROM complaints WHERE user_id = ? ORDER BY updated_at DESC LIMIT 5");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AIMT - User Dashboard</title>
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
        /* Custom scrollbar for better UX */
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
    </style>
</head>
<body class="bg-gray-50 dark:bg-dark-bg-primary min-h-screen font-['Inter'] transition-colors duration-200">
    <div class="flex min-h-screen">
        <!-- Sidebar Backdrop -->
        <div id="sidebar-backdrop" class="fixed inset-0 bg-black bg-opacity-30 z-[54] hidden md:hidden transition-opacity"></div>
        
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed md:static inset-y-0 left-0 transform -translate-x-full md:translate-x-0 z-[55] w-64 bg-gradient-to-b from-blue-800/95 to-blue-900/95 dark:from-blue-900/95 dark:to-blue-950/95 backdrop-blur-md border-r border-white/10 text-white flex flex-col transition-transform duration-200 ease-in-out shadow-2xl md:shadow-xl">
            <div class="px-6 py-6 bg-gradient-to-r from-blue-700 to-blue-800 dark:from-blue-800 dark:to-blue-900 mt-4 md:mt-0">
                <div class="flex items-center space-x-3">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10 bg-white p-1 rounded-lg shadow-md">
                    <div class="leading-tight">
                        <span class="block text-base font-semibold">AIMT</span>
                        <span class="block text-xs text-cyan-300 tracking-wide">Complaint&nbsp;Portal</span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-b border-blue-700/50 dark:border-blue-800/50 bg-blue-800/30 dark:bg-blue-900/30">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></div>
                <div class="text-cyan-300 text-sm">User</div>
            </div>
            <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg bg-blue-700/50 dark:bg-blue-800/50 hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="submit_complaint.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">add_circle</span>
                    <span>Submit Complaint</span>
                </a>
                <a href="track_complaint.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">search</span>
                    <span>Track Complaint</span>
                </a>
                <a href="suggestions.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">lightbulb</span>
                    <span>Suggestions</span>
                </a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="hostel_issues.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group">
                    <span class="material-icons mr-3 text-blue-200">campaign</span>
                    <span>Hostel-Wide Complaints</span>
                </a>
                <?php endif; ?>
                <!-- Remove dark mode toggle and add help button -->
                <button id="help-button" class="flex items-center w-full px-4 py-3 rounded-lg hover:bg-blue-700/50 dark:hover:bg-blue-800/50 transition-colors group mt-2">
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
            <header class="sticky top-0 z-50 backdrop-blur-md bg-gradient-to-r from-blue-600/90 to-indigo-700/90 dark:from-blue-800/90 dark:to-indigo-900/90 text-white shadow-xl">
                <div class="flex items-center justify-between px-4 py-4 md:px-6 md:py-6">
                    <div class="flex items-center space-x-4">
                        <!-- Mobile Menu Button -->
                        <button id="mobile-menu-button" class="md:hidden p-2 -ml-2 rounded-lg hover:bg-blue-700/50 transition-colors">
                            <span class="material-icons">menu</span>
                        </button>
                        <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10 md:w-12 md:h-12 bg-white p-1 rounded-lg shadow-lg">
                        <div class="flex flex-col leading-tight">
                            <h1 class="text-xl md:text-2xl font-bold">Dashboard</h1>
                            <span class="text-xs md:text-sm text-blue-200">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></span>
                        </div>
                    </div>
                    <!-- Notification Bell -->
                    <button id="notification-bell" class="relative bg-gray-200 dark:bg-dark-bg-secondary text-gray-800 dark:text-dark-text-primary p-2 rounded-lg shadow-lg hover:bg-gray-300 dark:hover:bg-dark-border transition-colors">
                        <span class="material-icons">notifications</span>
                        <span id="notification-badge" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full px-1 hidden"></span>
                    </button>
                    <!-- Notification Dropdown -->
                    <div id="notification-dropdown" class="hidden absolute top-16 right-4 bg-white dark:bg-dark-bg-secondary rounded-xl shadow-lg w-80 max-w-xs z-[101] border border-gray-200 dark:border-dark-border">
                        <div class="p-4 border-b border-gray-100 dark:border-dark-border font-semibold flex justify-between items-center">
                            <span>Notifications</span>
                            <button id="delete-all-notifications" class="text-red-500 hover:text-red-700 text-sm flex items-center" title="Delete All Notifications" onclick="deleteAllNotifications()">
                                <span class="material-icons text-base mr-1">delete</span>Delete All
                            </button>
                        </div>
                        <div id="notification-list" class="max-h-64 overflow-y-auto"></div>
                    </div>
                </div>
            </header>

            <!-- Content Container -->
            <div class="flex-1 p-4 md:p-6 space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Total Complaints Card -->
                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm hover:shadow-md transition-shadow p-4">
                        <div class="flex items-center justify-between">
                            <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                                <span class="material-icons text-xl text-blue-600 dark:text-blue-400">description</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-800 dark:text-dark-text-primary"><?= $total ?></span>
                        </div>
                        <h3 class="mt-2 text-sm text-gray-600 dark:text-dark-text-secondary font-medium">Total Complaints</h3>
                    </div>

                    <!-- Resolved Card -->
                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm hover:shadow-md transition-shadow p-4">
                        <div class="flex items-center justify-between">
                            <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                                <span class="material-icons text-xl text-green-600 dark:text-green-400">check_circle</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-800 dark:text-dark-text-primary"><?= $resolved ?></span>
                        </div>
                        <h3 class="mt-2 text-sm text-gray-600 dark:text-dark-text-secondary font-medium">Resolved</h3>
                    </div>

                    <!-- Pending Card -->
                    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm hover:shadow-md transition-shadow p-4">
                        <div class="flex items-center justify-between">
                            <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                                <span class="material-icons text-xl text-yellow-600 dark:text-yellow-400">pending</span>
                            </div>
                            <span class="text-2xl font-bold text-gray-800 dark:text-dark-text-primary"><?= $pending ?></span>
                        </div>
                        <h3 class="mt-2 text-sm text-gray-600 dark:text-dark-text-secondary font-medium">Pending</h3>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div>
                    <a href="submit_complaint.php" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 dark:bg-blue-700 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-800 transition-colors shadow-sm">
                        <span class="material-icons mr-2">add_circle</span>
                        New Complaint
                    </a>
                </div>

                <!-- Hostel Issues Section -->
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student' && !empty($hostel_issues)): ?>
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-dark-border">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-dark-text-primary">Hostel-Wide Complaints</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-dark-text-secondary">
                            Active issues for <?= htmlspecialchars($hostel_types[$student_hostel] ?? $student_hostel) ?> Hostel
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($hostel_issues as $issue): ?>
                            <div class="relative bg-white dark:bg-dark-bg-secondary border border-gray-100 dark:border-dark-border rounded-2xl shadow-lg hover:shadow-2xl hover:-translate-y-1 transition p-5 flex flex-col space-y-3">
                                <span class="absolute top-4 right-4 px-2 py-0.5 rounded-full text-xs font-bold
                                    <?= $issue['status'] === 'resolved' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800' : 
                                       ($issue['status'] === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-800' : 
                                       'bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-800') ?>">
                                    <?= ucfirst(str_replace('_',' ',$issue['status'])) ?>
                                </span>
                                <div class="flex items-center space-x-3">
                                    <div class="bg-red-50 dark:bg-red-900/30 rounded-full p-2">
                                        <span class="material-icons text-red-500 dark:text-red-300 text-2xl">campaign</span>
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary"><?= htmlspecialchars($issue_types[$issue['issue_type']] ?? $issue['issue_type']) ?></div>
                                        <div class="text-xs text-gray-400 dark:text-dark-text-secondary">Votes: <span class="font-bold"><?= $issue['votes'] ?></span></div>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-dark-text-secondary">
                                    <?= !empty($issue['tech_remarks']) ? nl2br(htmlspecialchars($issue['tech_remarks'])) : '<span class="italic text-gray-400">No remarks</span>' ?>
                                </div>
                                <div class="mt-2">
                                    <?php if ($issue['user_voted'] == 0): ?>
                                        <button type="button"
                                            onclick="voteHostelIssue('<?= $issue['id'] ?>', '<?= htmlspecialchars($student_hostel) ?>')"
                                            class="flex items-center px-3 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded-lg hover:bg-green-200 dark:hover:bg-green-800 transition">
                                            <span class="material-icons text-base mr-1">thumb_up</span>Yes, I have this issue too
                                        </button>
                                    <?php else: ?>
                                        <span class="flex items-center text-green-700 dark:text-green-400 text-sm font-medium">
                                            <span class="material-icons text-base mr-1">check_circle</span>Voted
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Complaints -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-gray-100 dark:border-dark-border">
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-dark-text-primary">Recent Complaints</h2>
                    </div>
                    <?php if ($complaints): ?>
                    <div class="overflow-x-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($complaints as $c): ?>
                            <div class="relative bg-white dark:bg-dark-bg-secondary border border-gray-100 dark:border-dark-border rounded-2xl shadow-lg hover:shadow-2xl hover:-translate-y-1 transition p-5 flex flex-col space-y-3" data-complaint-id="<?= $c['id'] ?>">
                                <!-- Status badge -->
                                <span class="absolute top-4 right-4 px-2 py-0.5 rounded-full text-xs font-bold
                                    <?= $c['status'] === 'resolved' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800' : 
                                       ($c['status'] === 'pending' ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-800' : 
                                       'bg-gray-100 dark:bg-gray-900/30 text-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-800') ?>">
                                    <?= htmlspecialchars(ucfirst($c['status'])) ?>
                                </span>
                                <div class="flex items-center space-x-3">
                                    <div class="bg-blue-50 dark:bg-blue-900/30 rounded-full p-2">
                                        <span class="material-icons text-blue-600 dark:text-blue-400 text-2xl">
                                            <?php
                                                $icons = [
                                                    'mess' => 'restaurant',
                                                    'carpenter' => 'handyman',
                                                    'wifi' => 'wifi',
                                                    'housekeeping' => 'cleaning_services',
                                                    'plumber' => 'plumbing',
                                                    'electrician' => 'electrical_services',
                                                    'laundry' => 'local_laundry_service',
                                                    'infrastructure' => 'apartment',
                                                    'other' => 'miscellaneous_services'
                                                ];
                                                echo $icons[$c['category']] ?? 'help';
                                            ?>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary"><?= htmlspecialchars(ucfirst($c['category'])) ?></div>
                                        <div class="text-xs text-gray-400 dark:text-dark-text-secondary">Token: <?= htmlspecialchars($c['token']) ?></div>
                                    </div>
                                </div>
                                <div class="flex flex-col text-sm text-gray-500 dark:text-dark-text-secondary space-y-1">
                                    <span>Created: <?= htmlspecialchars(date('M j, Y', strtotime($c['created_at']))) ?></span>
                                    <span>Updated: <?= htmlspecialchars(date('M j, Y', strtotime($c['updated_at']))) ?></span>
                                </div>
                                <div class="flex items-center text-sm technician-status">
                                                                        <?php if ($c['tech_name']): ?>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center">
                                                <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Assigned Technician:</span>
                                                <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name"><?= htmlspecialchars($c['tech_name']) ?></span>
                                            </div>
                                            
                                            <!-- Show status -->
                                            <?php if ($c['tech_online'] == 1): ?>
                                                <div class="flex items-center">
                                                    <span class="text-sm text-green-600 dark:text-green-400">
                                                        <span class="material-icons text-sm mr-1">check_circle</span>
                                                        Ready to help
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center">
                                                    <span class="text-sm text-orange-600 dark:text-orange-400">
                                                        <span class="material-icons text-sm mr-1">schedule</span>
                                                        Currently unavailable - will be reassigned to another technician
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($c['tech_phone']): ?>
                                                <div class="flex items-center">
                                                    <a href="tel:<?= htmlspecialchars($c['tech_phone']) ?>" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                                        <span class="material-icons text-base mr-1">phone</span>Call
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($c['available_tech']): ?>
                                        <?php list($tech_name, $tech_phone, $tech_online) = explode('|', $c['available_tech']); ?>
                                        <div class="flex flex-col space-y-1">
                                            <div class="flex items-center">
                                                <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Available Technician:</span>
                                                <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name"><?= htmlspecialchars($tech_name) ?></span>
                                            </div>
                                            
                                            <?php if ($tech_online == 1): ?>
                                                <div class="flex items-center">
                                                    <span class="text-sm text-green-600 dark:text-green-400">
                                                        <span class="material-icons text-sm mr-1">check_circle</span>
                                                        Ready to help
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center">
                                                    <span class="text-sm text-orange-600 dark:text-orange-400">
                                                        <span class="material-icons text-sm mr-1">schedule</span>
                                                        Currently unavailable - will be assigned when online
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center">
                                                <a href="tel:<?= htmlspecialchars($tech_phone) ?>" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                                    <span class="material-icons text-base mr-1">phone</span>Call
                                                </a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Check if there are any technicians for this category -->
                                                                                   <?php if ($c['total_techs'] > 0): ?>
                                               <?php list($offline_tech_name, $offline_tech_phone, $offline_tech_online) = explode('|', $c['any_tech']); ?>
                                               <!-- Show the offline technician's name -->
                                               <div class="flex flex-col space-y-1">
                                                   <div class="flex items-center">
                                                       <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Technician:</span>
                                                       <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name"><?= htmlspecialchars($offline_tech_name) ?></span>
                                                   </div>
                                                   
                                                   <?php if ($offline_tech_online == 0): ?>
                                                       <div class="flex items-center">
                                                           <span class="text-sm text-orange-600 dark:text-orange-400">
                                                               <span class="material-icons text-sm mr-1">schedule</span>
                                                               Currently unavailable - will be assigned when online
                                                           </span>
                                                       </div>
                                                   <?php endif; ?>
                                                   
                                                   <?php if ($offline_tech_phone): ?>
                                                       <div class="flex items-center">
                                                           <a href="tel:<?= htmlspecialchars($offline_tech_phone) ?>" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                                               <span class="material-icons text-base mr-1">phone</span>Call
                                                           </a>
                                                       </div>
                                                   <?php endif; ?>
                                               </div>
                                                                                   <?php else: ?>
                                               <!-- No technicians exist for this category -->
                                               <div class="flex flex-col space-y-1">
                                                   <div class="flex items-center">
                                                       <span class="text-gray-400">No technician available</span>
                                                   </div>
                                                   <div class="flex items-center">
                                                       <span class="text-xs text-orange-600 dark:text-orange-400">(Will be assigned when available)</span>
                                                   </div>
                                               </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="flex space-x-3 mt-2">
                                    <button onclick="showQRModal('<?= htmlspecialchars($c['token']) ?>')" class="flex items-center px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-800 transition">
                                        <span class="material-icons text-base mr-1">qr_code_2</span>QR
                                    </button>
                                    <?php if ($c['status'] === 'pending'): ?>
                                    <button onclick="deleteComplaint('<?= htmlspecialchars($c['token']) ?>')" class="flex items-center px-3 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg hover:bg-red-200 dark:hover:bg-red-800 transition">
                                        <span class="material-icons text-base mr-1">delete</span>Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-icons text-gray-400 dark:text-dark-text-secondary text-4xl mb-3">inbox</span>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-dark-text-primary mb-2">No complaints yet</h3>
                            <p class="text-sm text-gray-500 dark:text-dark-text-secondary">Submit your first complaint to get started</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity Timeline -->
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm p-4 mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-dark-text-primary mb-2">Recent Activity</h3>
                    <ul class="space-y-2">
                        <?php foreach ($recent_activity as $act): ?>
                        <li class="flex items-center space-x-3">
                            <span class="material-icons text-base <?php
                                if ($act['status'] === 'resolved') echo 'text-green-500';
                                elseif ($act['status'] === 'pending') echo 'text-yellow-500 animate-pulse';
                                else echo 'text-blue-500';
                            ?>">fiber_manual_record</span>
                            <span class="text-sm text-gray-700 dark:text-dark-text-secondary">
                                <?= htmlspecialchars(ucfirst($act['category'])) ?> complaint <b><?= htmlspecialchars($act['status']) ?></b> on <?= date('M j, g:i A', strtotime($act['updated_at'])) ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>

    <!-- QR Modal -->
    <div id="qr-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl max-w-sm w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">Scan to Track</h3>
                <button onclick="closeQRModal()" class="text-gray-400 dark:text-dark-text-secondary hover:text-gray-600 dark:hover:text-dark-text-primary p-1 rounded-full hover:bg-gray-100 dark:hover:bg-dark-bg-primary">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div id="qr-container" class="flex justify-center p-4 bg-gray-50 dark:bg-dark-bg-primary rounded-lg"></div>
            <p class="text-sm text-gray-500 dark:text-dark-text-secondary text-center mt-4">Show this QR code to the technician to get your complaint resolved</p>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-dark-bg-secondary rounded-xl max-w-sm w-full p-6 shadow-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">Delete Complaint</h3>
                <button onclick="closeDeleteModal()" class="text-gray-400 dark:text-dark-text-secondary hover:text-gray-600 dark:hover:text-dark-text-primary p-1 rounded-full hover:bg-gray-100 dark:hover:bg-dark-bg-primary">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <p class="text-gray-600 dark:text-dark-text-secondary mb-6">Are you sure you want to delete this complaint? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button onclick="closeDeleteModal()" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-dark-text-secondary hover:bg-gray-100 dark:hover:bg-dark-bg-primary rounded-lg transition-colors">
                    Cancel
                </button>
                <button onclick="confirmDelete()" class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/qr-gen/qrcode.min.js"></script>
    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const sidebar = document.getElementById('sidebar');
        const sidebarBackdrop = document.getElementById('sidebar-backdrop');
        const mainContent = document.getElementById('main-content');

        function toggleSidebar() {
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            
            if (isOpen) {
                // Close sidebar
                sidebar.classList.add('-translate-x-full');
                sidebarBackdrop.classList.add('hidden');
                mainContent.classList.remove('md:ml-0');
            } else {
                // Open sidebar
                sidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.remove('hidden');
                mainContent.classList.add('md:ml-0');
            }
        }

        mobileMenuButton.addEventListener('click', toggleSidebar);
        sidebarBackdrop.addEventListener('click', toggleSidebar);

        // Close sidebar when screen size changes to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) { // md breakpoint
                sidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.add('hidden');
            }
        });

        // QR Code Modal
        function showQRModal(token) {
            const container = document.getElementById('qr-container');
            container.innerHTML = '';
            new QRCode(container, {
                text: token,
                width: 200,
                height: 200,
                colorDark: document.documentElement.classList.contains('dark') ? "#60a5fa" : "#1e40af",
                colorLight: document.documentElement.classList.contains('dark') ? "#1a1b1e" : "#ffffff",
                correctLevel: QRCode.CorrectLevel.H
            });
            document.getElementById('qr-modal').classList.remove('hidden');
        }

        function closeQRModal() {
            document.getElementById('qr-modal').classList.add('hidden');
        }

        // Close modal on outside click
        document.getElementById('qr-modal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('qr-modal')) {
                closeQRModal();
            }
        });

        // Delete Complaint functionality
        let complaintToDelete = null;

        function deleteComplaint(token) {
            complaintToDelete = token;
            document.getElementById('delete-modal').classList.remove('hidden');
        }

        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.add('hidden');
            complaintToDelete = null;
        }

        function confirmDelete() {
            if (!complaintToDelete) return;

            fetch('delete_complaint.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'token=' + encodeURIComponent(complaintToDelete)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Complaint deleted successfully');
                    // Reload the page to update the list
                    window.location.reload();
                } else {
                    // Show error message
                    alert(data.message || 'Failed to delete complaint');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the complaint');
            })
            .finally(() => {
                closeDeleteModal();
            });
        }

        // Close delete modal on outside click
        document.getElementById('delete-modal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('delete-modal')) {
                closeDeleteModal();
            }
        });

        // Notification logic
        const resolvedNotifications = <?= json_encode($resolved_notifications) ?>;
        let notificationCount = resolvedNotifications.length;

        // Show notification badge if there are notifications
        if (notificationCount > 0) {
            const badge = document.getElementById('notification-badge');
            badge.classList.remove('hidden');
            badge.textContent = notificationCount;
        }

        // Real-time technician status checking
        let statusCheckInterval = null;
        let lastStatusCheck = 0;
        const STATUS_CHECK_INTERVAL = 30000; // Check every 30 seconds

        function startStatusChecking() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
            
            statusCheckInterval = setInterval(checkTechnicianStatus, STATUS_CHECK_INTERVAL);
            
            // Also check immediately on page load
            setTimeout(checkTechnicianStatus, 2000);
        }

        function checkTechnicianStatus() {
            const now = Date.now();
            if (now - lastStatusCheck < STATUS_CHECK_INTERVAL) {
                return; // Don't check too frequently
            }
            lastStatusCheck = now;

            fetch('check_technician_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_all_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateTechnicianStatusDisplay(data.complaints);
                }
            })
            .catch(error => {
                console.error('Error checking technician status:', error);
            });
        }

        function updateTechnicianStatusDisplay(complaints) {
            complaints.forEach(complaint => {
                const complaintElement = document.querySelector(`[data-complaint-id="${complaint.id}"]`);
                if (complaintElement) {
                    const statusElement = complaintElement.querySelector('.technician-status');
                    if (statusElement && complaint.technician_status) {
                        updateStatusBadge(statusElement, complaint.technician_status);
                    }
                }
            });
        }

        // Track current status to detect changes
        const currentStatus = new Map();
        
        function updateStatusBadge(element, techStatus) {
            const complaintId = element.closest('[data-complaint-id]')?.getAttribute('data-complaint-id');
            const previousStatus = currentStatus.get(complaintId);
            
            // Get the technician name from the current element or use the one from techStatus
            const techNameElement = element.querySelector('.tech-name');
            const techName = techNameElement ? techNameElement.textContent.trim() : techStatus.tech_name || 'Technician';
            
            if (techStatus.status === 'offline') {
                element.innerHTML = `
                    <div class="flex flex-col space-y-1">
                        <div class="flex items-center">
                            <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Assigned Technician:</span>
                            <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name">${techName}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-orange-600 dark:text-orange-400">
                                <span class="material-icons text-sm mr-1">schedule</span>
                                Currently unavailable - will be reassigned to another technician
                            </span>
                        </div>
                        ${techStatus.tech_phone ? `
                        <div class="flex items-center">
                            <a href="tel:${techStatus.tech_phone}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                <span class="material-icons text-base mr-1">phone</span>Call
                            </a>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                // Only show notification if status changed from online to offline
                if (previousStatus && previousStatus !== 'offline') {
                    showOfflineNotification(techName);
                }
                
                currentStatus.set(complaintId, 'offline');
            } else if (techStatus.status === 'online') {
                element.innerHTML = `
                    <div class="flex flex-col space-y-1">
                        <div class="flex items-center">
                            <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Assigned Technician:</span>
                            <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name">${techName}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-green-600 dark:text-green-400">
                                <span class="material-icons text-sm mr-1">check_circle</span>
                                Ready to help
                            </span>
                        </div>
                        ${techStatus.tech_phone ? `
                        <div class="flex items-center">
                            <a href="tel:${techStatus.tech_phone}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                <span class="material-icons text-base mr-1">phone</span>Call
                            </a>
                        </div>
                        ` : ''}
                    </div>
                `;
                
                // Show notification if status changed from offline to online
                if (previousStatus && previousStatus === 'offline') {
                    showOnlineNotification(techName);
                }
                
                currentStatus.set(complaintId, 'online');
            } else if (techStatus.status === 'available') {
                element.innerHTML = `
                    <div class="flex flex-col space-y-1">
                        <div class="flex items-center">
                            <span class="text-gray-500 dark:text-dark-text-secondary mr-2">Available Technician:</span>
                            <span class="font-medium text-gray-800 dark:text-dark-text-primary tech-name">${techStatus.tech_name}</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-sm text-green-600 dark:text-green-400">
                                <span class="material-icons text-sm mr-1">check_circle</span>
                                Ready to help
                            </span>
                        </div>
                        ${techStatus.tech_phone ? `
                        <div class="flex items-center">
                            <a href="tel:${techStatus.tech_phone}" class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
                                <span class="material-icons text-base mr-1">phone</span>Call
                            </a>
                        </div>
                        ` : ''}
                    </div>
                `;
                currentStatus.set(complaintId, 'available');
            } else if (techStatus.status === 'no_technician') {
                element.innerHTML = `
                    <div class="flex flex-col space-y-1">
                        <div class="flex items-center">
                            <span class="text-gray-400">No technician available</span>
                        </div>
                        <div class="flex items-center">
                            <span class="text-xs text-orange-600 dark:text-orange-400">(Will be assigned when available)</span>
                        </div>
                    </div>
                `;
                currentStatus.set(complaintId, 'no_technician');
            } else {
                // Other status - preserve existing content
                currentStatus.set(complaintId, techStatus.status);
            }
        }

        // Track shown notifications to avoid duplicates
        const shownNotifications = new Set();
        
        function showOfflineNotification(techName) {
            // Create unique key for this notification
            const notificationKey = `offline_${techName}_${Date.now()}`;
            
            // Check if we've already shown this notification recently
            if (shownNotifications.has(techName)) {
                return; // Don't show duplicate notifications
            }
            
            // Add to shown notifications
            shownNotifications.add(techName);
            
            // Create a temporary notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-orange-50 border border-orange-200 text-orange-700 px-4 py-3 rounded z-50 max-w-sm';
            notification.innerHTML = `
                <div class="flex items-center">
                    <span class="material-icons mr-2 text-orange-500">schedule</span>
                    <div>
                        <p class="font-medium">Technician Unavailable</p>
                        <p class="text-sm">${techName} is currently unavailable. Your complaint will be reassigned to another technician.</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-orange-500 hover:text-orange-700">
                        <span class="material-icons text-sm">close</span>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
                // Remove from shown notifications after some time
                setTimeout(() => {
                    shownNotifications.delete(techName);
                }, 10000); // Allow showing again after 10 seconds
            }, 5000);
        }
        
        function showOnlineNotification(techName) {
            // Check if we've already shown this notification recently
            if (shownNotifications.has(`online_${techName}`)) {
                return; // Don't show duplicate notifications
            }
            
            // Add to shown notifications
            shownNotifications.add(`online_${techName}`);
            
            // Create a temporary notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded z-50 max-w-sm';
            notification.innerHTML = `
                <div class="flex items-center">
                    <span class="material-icons mr-2 text-green-500">check_circle</span>
                    <div>
                        <p class="font-medium">Technician Available</p>
                        <p class="text-sm">${techName} is now available and ready to help with your complaint.</p>
                    </div>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-green-500 hover:text-green-700">
                        <span class="material-icons text-sm">close</span>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
                // Remove from shown notifications after some time
                setTimeout(() => {
                    shownNotifications.delete(`online_${techName}`);
                }, 10000); // Allow showing again after 10 seconds
            }, 5000);
        }

        // Start status checking when page loads
        startStatusChecking();
        
        // Run auto-reassignment in background after page loads
        setTimeout(() => {
            fetch('check_technician_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=reassign_offline'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.result.reassigned > 0) {
                    // Auto-reassignment completed
                }
            })
            .catch(error => {
                console.error('Error in auto-reassignment:', error);
            });
        }, 3000); // Run after 3 seconds

        function showNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            const list = document.getElementById('notification-list');
            list.innerHTML = '';
            resolvedNotifications.forEach(n => {
                const item = document.createElement('div');
                item.className = 'p-3 border-b border-gray-100 dark:border-dark-border text-sm flex justify-between items-center';
                item.innerHTML = `<div><span class='block text-gray-900 dark:text-dark-text-primary font-medium'>Your <b>${n.category}</b> complaint was resolved.</span><span class='block text-xs text-gray-400 mt-1'>${new Date(n.updated_at).toLocaleString()}</span></div>` +
                    `<button class='ml-2 text-red-500 hover:text-red-700' title='Delete Notification' onclick=\"deleteNotification('complaint','${n.token}', this)\"><span class='material-icons text-base'>delete</span></button>`;
                list.appendChild(item);
            });
            dropdown.classList.toggle('hidden');
            // Mark notifications as read when dropdown is opened
            if (!dropdown.classList.contains('hidden') && notificationCount > 0) {
                fetch('mark_notifications_read.php', { method: 'POST' })
                    .then(() => {
                        notificationCount = 0;
                        document.getElementById('notification-badge').classList.add('hidden');
                    });
            }
        }

        document.getElementById('notification-bell').addEventListener('click', showNotifications);

        function voteHostelIssue(issueId, hostelType) {
            fetch('vote_hostel_issue.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'issue_id=' + encodeURIComponent(issueId) + '&hostel_type=' + encodeURIComponent(hostelType)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to vote');
                }
            })
            .catch(() => alert('An error occurred while voting'));
        }

        // Add deleteNotification function
        function deleteNotification(type, id, btn) {
            btn.disabled = true;
            fetch('delete_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the notification from the DOM
                    btn.closest('div.p-3').remove();
                    // Decrement notification count and hide badge if zero
                    notificationCount--;
                    if (notificationCount <= 0) {
                        document.getElementById('notification-badge').classList.add('hidden');
                    } else {
                        document.getElementById('notification-badge').textContent = notificationCount;
                    }
                } else {
                    alert(data.message || 'Failed to delete notification');
                    btn.disabled = false;
                }
            })
            .catch(() => {
                alert('An error occurred while deleting the notification');
                btn.disabled = false;
            });
        }

        // Add deleteAllNotifications function
        function deleteAllNotifications() {
            if (!confirm('Are you sure you want to delete all notifications?')) return;
            fetch('delete_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'all=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('notification-list').innerHTML = '';
                    document.getElementById('notification-badge').classList.add('hidden');
                } else {
                    alert(data.message || 'Failed to delete notifications');
                }
            })
            .catch(() => {
                alert('All complaints are deleted succesfully.');
            });
        }
    </script>
<!-- Help Modal -->
<div id="help-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl max-w-2xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-dark-text-primary">Hey there! Need a hand?</h3>
            <button onclick="closeHelpModal()" class="text-gray-400 dark:text-dark-text-secondary hover:text-gray-600 dark:hover:text-dark-text-primary p-1 rounded-full hover:bg-gray-100 dark:hover:bg-dark-bg-primary">
                <span class="material-icons">close</span>
            </button>
        </div>

        <div class="space-y-6">

            <!-- Quick Start Guide -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                <h4 class="flex items-center text-lg font-medium text-blue-900 dark:text-blue-100 mb-2">
                    <span class="material-icons mr-2">tips_and_updates</span>
                    Quick Start Guide
                </h4>
                <ul class="text-blue-800 dark:text-blue-200 text-sm space-y-2">
                    <li class="flex items-center">
                        <span class="material-icons text-blue-500 mr-2 text-sm">add_circle</span>
                        Submit new complaint via "New Complaint" button
                    </li>
                    <li class="flex items-center">
                        <span class="material-icons text-blue-500 mr-2 text-sm">qr_code_scanner</span>
                        Show QR code to technician when they arrive
                    </li>
                    <li class="flex items-center">
                        <span class="material-icons text-blue-500 mr-2 text-sm">notifications</span>
                        Check notifications for updates (top-right bell icon)
                    </li>
                </ul>
            </div>

            <!-- Key Features -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                    <h4 class="flex items-center font-medium text-green-900 dark:text-green-100 mb-2">
                        <span class="material-icons mr-2">track_changes</span>
                        Track Your Complaints
                    </h4>
                    <p class="text-green-800 dark:text-green-200 text-sm">
                        View status, assigned technician, and get real-time updates on your complaints.
                    </p>
                </div>

                <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-100 dark:border-purple-800">
                    <h4 class="flex items-center font-medium text-purple-900 dark:text-purple-100 mb-2">
                        <span class="material-icons mr-2">contact_phone</span>
                        Direct Contact
                    </h4>
                    <p class="text-purple-800 dark:text-purple-200 text-sm">
                        Call assigned technician directly from the dashboard when needed.
                    </p>
                </div>

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-100 dark:border-orange-800">
                    <h4 class="flex items-center font-medium text-orange-900 dark:text-orange-100 mb-2">
                        <span class="material-icons mr-2">campaign</span>
                        Hostel Issues
                    </h4>
                    <p class="text-orange-800 dark:text-orange-200 text-sm">
                        Vote on hostel-wide problems to help prioritize fixes.
                    </p>
                </div>
                <?php endif; ?>

                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800">
                    <h4 class="flex items-center font-medium text-yellow-900 dark:text-yellow-100 mb-2">
                        <span class="material-icons mr-2">lightbulb</span>
                        Suggestions & Feedback
                    </h4>
                    <p class="text-yellow-800 dark:text-yellow-200 text-sm">
                        Share your ideas or feedback to help improve campus life! Visit the Suggestions section to submit new suggestions or vote on others' ideas.
                    </p>
                </div>

                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg border border-red-100 dark:border-red-800">
                    <h4 class="flex items-center font-medium text-red-900 dark:text-red-100 mb-2">
                        <span class="material-icons mr-2">delete</span>
                        Manage Complaints
                    </h4>
                    <p class="text-red-800 dark:text-red-200 text-sm">
                        Delete pending complaints or clear notifications as needed.
                    </p>
                </div>
            </div>

            <!-- Need Help? -->
            <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
                <p class="text-gray-600 dark:text-gray-300 text-sm">
                    Still need help? Contact the admin or give feedback after your issue is resolved.
                </p>
            </div>

            <!-- Developer Signature -->
            <div class="text-center opacity-60 hover:opacity-100 transition-opacity duration-300 cursor-default select-none">
                <div class="font-mono text-[11px] tracking-[0.3em] text-gray-400 dark:text-gray-500">
                    DEVELOPED BY
                </div>
                <div class="font-mono text-[10px] tracking-[0.4em] text-transparent bg-clip-text bg-gradient-to-r from-gray-600 to-gray-400 dark:from-gray-400 dark:to-gray-600">
                    MR.HARKAMAL
                </div>
            </div>

        </div>
    </div>
</div>
<script>
    function openHelpModal() {
        const modal = document.getElementById('help-modal');
        modal.classList.remove('hidden');

        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');

        if (sidebar?.classList.contains('active')) {
            sidebar.classList.remove('active');
            backdrop?.classList.add('hidden');
        }
    }

    function closeHelpModal() {
        const modal = document.getElementById('help-modal');
        modal.classList.add('hidden');
    }

    // Open modal on Help button click
    document.getElementById('help-button')?.addEventListener('click', openHelpModal);

    // Close modal on outside click
    document.getElementById('help-modal')?.addEventListener('click', (e) => {
        if (e.target === document.getElementById('help-modal')) {
            closeHelpModal();

            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');

            sidebar?.classList.remove('active');
            backdrop?.classList.add('hidden');
        }
    });
</script>

</body>
</html> 