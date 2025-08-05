<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$message = '';
$message_type = '';

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['tech']) && isset($_GET['status'])) {
    $tech_name = htmlspecialchars($_GET['tech']);
    $status = htmlspecialchars($_GET['status']);
    $message = "Technician {$tech_name} is now {$status}.";
    $message_type = 'success';
}

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['technician_id'])) {
    $technician_id = (int)$_POST['technician_id'];
    $action = $_POST['action'];
    
    if ($action === 'toggle_status') {
        global $mysqli;
        
        // Get current status
        $stmt = $mysqli->prepare("SELECT is_online, full_name FROM users WHERE id = ? AND role = 'technician'");
        $stmt->bind_param('i', $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            $new_status = $technician['is_online'] == 1 ? 0 : 1;
            $status_text = $new_status == 1 ? 'online' : 'offline';
            
            // Update status
            $stmt = $mysqli->prepare("UPDATE users SET is_online = ? WHERE id = ?");
            $stmt->bind_param('ii', $new_status, $technician_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                $message = "Technician {$technician['full_name']} is now {$status_text}.";
                $message_type = 'success';
                
                // If technician is coming online, automatically assign unassigned complaints
                if ($new_status == 1) {
                    $auto_assignment_result = auto_assign_complaints_for_technician($technician_id, $technician['specialization']);
                    $hostel_assignment_result = auto_assign_hostel_issues_for_technician($technician_id, $technician['specialization']);
                    
                    if ($auto_assignment_result['assigned_count'] > 0 || $hostel_assignment_result['assigned_count'] > 0) {
                        $assignment_message = "";
                        if ($auto_assignment_result['assigned_count'] > 0) {
                            $assignment_message .= "{$auto_assignment_result['assigned_count']} complaints";
                        }
                        if ($hostel_assignment_result['assigned_count'] > 0) {
                            if (!empty($assignment_message)) $assignment_message .= " and ";
                            $assignment_message .= "{$hostel_assignment_result['assigned_count']} hostel issues";
                        }
                        $assignment_message .= " automatically assigned.";
                        $message .= " " . $assignment_message;
                    }
                }
                
                // Log the action
                log_security_action('technician_status_toggle', $technician['full_name'], "Set to {$status_text}");
                
                // Redirect to prevent form resubmission
                header("Location: technician_status.php?success=1&tech=" . urlencode($technician['full_name']) . "&status=" . $status_text);
                exit();
            } else {
                $message = "Failed to update technician status.";
                $message_type = 'error';
            }
        } else {
            $message = "Technician not found.";
            $message_type = 'error';
        }
    }
}

// Get all technicians with their status and workload
global $mysqli;
$stmt = $mysqli->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.specialization,
        u.is_online,
        u.phone,
        COUNT(c.id) as assigned_complaints,
        COUNT(hi.id) as assigned_hostel_issues
    FROM users u
    LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
    LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
    WHERE u.role = 'technician'
    GROUP BY u.id, u.full_name, u.specialization, u.is_online, u.phone
    ORDER BY u.full_name
");
$stmt->execute();
$result = $stmt->get_result();
$technicians = [];
while ($row = $result->fetch_assoc()) {
    $technicians[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician Status Management - Superadmin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .status-toggle {
            position: relative;
            display: inline-flex;
            height: 1.5rem;
            width: 2.75rem;
            align-items: center;
            border-radius: 9999px;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            border: none;
            outline: none;
        }
        .status-toggle:focus {
            outline: none;
            box-shadow: 0 0 0 2px #3b82f6, 0 0 0 4px white;
        }
        .status-toggle.online {
            background-color: #16a34a;
        }
        .status-toggle.offline {
            background-color: #e5e7eb;
        }
        .status-toggle-thumb {
            display: inline-block;
            height: 1rem;
            width: 1rem;
            transform: translateX(0.25rem);
            border-radius: 9999px;
            background-color: white;
            transition: transform 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .status-toggle.online .status-toggle-thumb {
            transform: translateX(1.5rem);
        }
        .status-toggle.offline .status-toggle-thumb {
            transform: translateX(0.25rem);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen">
    <div class="min-h-screen">
        <!-- Header -->
     <header class="bg-gradient-to-r from-purple-900 to-purple-800 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-8">
            <!-- Left: Title and Subtitle -->
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-purple-500/20 rounded-xl border border-purple-400/30">
                    <svg class="text-purple-400 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white">Technician Status Management</h1>
                    <p class="text-purple-200 text-sm mt-1 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Real-time technician availability and workload monitoring
                    </p>
                </div>
            </div>

            <!-- Right: Last updated + Dashboard Button -->
            <div class="flex items-center space-x-4">
                <!-- Last Updated -->
                <div class="text-right">
                    <p class="text-purple-200 text-sm">Last Updated</p>
                    <p class="text-white font-medium"><?= date('M d, Y H:i') ?></p>
                </div>

                <!-- Icon Box -->
                <div class="p-2 bg-white/10 rounded-lg border border-white/20">
                    <svg class="text-white w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <!-- Dashboard Button -->
                <a href="dashboard.php"
                    class="inline-flex items-center px-4 py-2 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg shadow-sm border border-white/30 transition duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7m-9 2v6m4-6v6m4 0H5" />
                    </svg>
                    Dashboard
                </a>
            </div>
        </div>
    </div>
</header>



        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php if ($message): ?>
                <div class="mb-8 p-6 rounded-2xl <?= $message_type === 'success' ? 'bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 text-green-800' : 'bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 text-red-800' ?> shadow-lg">
                    <div class="flex items-center">
                        <?php if ($message_type === 'success'): ?>
                        <svg class="w-6 h-6 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php else: ?>
                        <svg class="w-6 h-6 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <?php endif; ?>
                        <span class="font-medium"><?= htmlspecialchars($message) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Technician Status Table -->
            <div class="bg-white rounded-2xl shadow-lg border border-slate-100">
                <div class="px-8 py-6 border-b border-slate-200">
                    <h2 class="text-2xl font-bold text-slate-900 flex items-center">
                        <svg class="w-6 h-6 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        Technician Status
                    </h2>
                    <p class="text-slate-600 mt-2">Toggle online/offline status for technicians. Offline technicians won't receive new assignments.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Technician</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Specialization</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Phone</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Current Workload</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($technicians as $tech): ?>
                            <tr class="hover:bg-slate-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($tech['full_name']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= htmlspecialchars(ucfirst($tech['specialization'])) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-600"><?= htmlspecialchars($tech['phone']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-slate-900">
                                        <?= $tech['assigned_complaints'] + $tech['assigned_hostel_issues'] ?> total
                                        <span class="text-slate-500 text-xs">
                                            (<?= $tech['assigned_complaints'] ?> complaints, <?= $tech['assigned_hostel_issues'] ?> hostel issues)
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="status-toggle <?= $tech['is_online'] == 1 ? 'online' : 'offline' ?>">
                                            <span class="status-toggle-thumb"></span>
                                        </div>
                                        <span class="ml-3 text-sm font-medium <?= $tech['is_online'] == 1 ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $tech['is_online'] == 1 ? 'Online' : 'Offline' ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="technician_id" value="<?= $tech['id'] ?>">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200">
                                            <?= $tech['is_online'] == 1 ? 'Set Offline' : 'Set Online' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Information Panel -->
            <div class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 border border-blue-200">
                <h3 class="text-lg font-semibold text-slate-900 mb-3 flex items-center">
                    <i class="lucide-info w-5 h-5 mr-2 text-blue-600"></i>
                    How Online/Offline Status Works
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-slate-700">
                    <div>
                        <h4 class="font-semibold text-green-700 mb-2">Online Technicians</h4>
                        <ul class="space-y-1">
                            <li>• Can receive new complaint assignments</li>
                            <li>• Included in auto-assignment process</li>
                            <li>• Can update complaint status</li>
                            <li>• Appear in technician selection lists</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-red-700 mb-2">Offline Technicians</h4>
                        <ul class="space-y-1">
                            <li>• Cannot receive new assignments</li>
                            <li>• Excluded from auto-assignment</li>
                            <li>• Can still update existing complaints</li>
                            <li>• Current assignments remain active</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>


</body>
</html> 