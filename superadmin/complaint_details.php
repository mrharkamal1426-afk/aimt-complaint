<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

if (!isset($_GET['token'])) {
    redirect('view_complaints.php?error=notoken');
}

$token = $_GET['token'];
$complaint = get_complaint_by_token($token);

if (!$complaint) {
    redirect('view_complaints.php?error=invalidtoken');
}

// Fetch technicians for dropdown
$technicians = [];
$tech_query = "SELECT id, full_name, specialization FROM users WHERE role = 'technician'";
$result = $mysqli->query($tech_query);
while($row = $result->fetch_assoc()){
    $technicians[] = $row;
}

// Handle form submission for updating complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_status    = $_POST['status'] ?? $complaint['status'];
    $technician_id = $_POST['technician_id'] ?? $complaint['technician_id'];
    $remarks       = $_POST['remarks'] ?? '';

    // If admin clicked the urgent button, force admin assignment
    if (isset($_POST['make_admin']) && $_POST['make_admin'] == '1') {
        if (empty($technician_id)) {
            // Require technician selection for urgent admin assign
            $_SESSION['error_message'] = 'Select a technician to mark this complaint urgent.';
        } else {
            update_complaint_status($token, $new_status, $technician_id, $remarks);
            $_SESSION['success_message'] = 'Complaint marked urgent and assigned.';
        }
    } else {
        // Normal update path (auto vs admin logic handled in function)
        update_complaint_status($token, $new_status, $technician_id, $remarks);
        $_SESSION['success_message'] = 'Complaint updated successfully!';
    }

    // Refresh data and redirect to avoid resubmission
    redirect("complaint_details.php?token=$token");
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details - <?= htmlspecialchars($token) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        .glassmorphism {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .status-badge {
            background: linear-gradient(135deg, var(--badge-bg) 0%, var(--badge-bg-dark) 100%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .update-button {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        
        .update-button:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
        }
        
        @media (max-width: 600px) {
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

        @keyframes statusPulse {
          0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
          70% { box-shadow: 0 0 0 6px rgba(16,185,129,0); }
          100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        .status-badge-emerald {
          border: 2px solid #10b981;
          animation: statusPulse 1.5s infinite;
          box-shadow: 0 0 0 0 rgba(16,185,129,0.3);
        }
        .status-badge-blue {
          border: 2px solid #2563eb;
          animation: statusPulseBlue 1.5s infinite;
          box-shadow: 0 0 0 0 rgba(37,99,235,0.3);
        }
        @keyframes statusPulseBlue {
          0% { box-shadow: 0 0 0 0 rgba(37,99,235,0.3); }
          70% { box-shadow: 0 0 0 6px rgba(37,99,235,0); }
          100% { box-shadow: 0 0 0 0 rgba(37,99,235,0); }
        }
        .status-badge-orange {
          border: 2px solid #f59e0b;
          animation: statusPulseOrange 1.5s infinite;
          box-shadow: 0 0 0 0 rgba(245,158,11,0.3);
        }
        @keyframes statusPulseOrange {
          0% { box-shadow: 0 0 0 0 rgba(245,158,11,0.3); }
          70% { box-shadow: 0 0 0 6px rgba(245,158,11,0); }
          100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        }
        .status-badge-red {
          border: 2px solid #ef4444;
          animation: statusPulseRed 1.5s infinite;
          box-shadow: 0 0 0 0 rgba(239,68,68,0.3);
        }
        @keyframes statusPulseRed {
          0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.3); }
          70% { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
          100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }


    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
    <div class="min-h-screen">
        <!-- Main Content (always full width) -->
        <div class="main-content flex-1 flex flex-col px-4 sm:px-6 md:px-8 max-w-7xl mx-auto w-full">
            <!-- Navigation Bar -->
            <nav class="bg-white shadow-md border-b border-slate-200">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex flex-col sm:flex-row justify-between items-center h-auto py-4 sm:h-20 space-y-4 sm:space-y-0">
                        <!-- Left Section -->
                        <div class="flex items-center space-x-4">
                            <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10">
                            <div>
                                <h1 class="text-xl font-bold text-slate-900">Complaint Details</h1>
                                <p class="text-sm text-slate-500">Token: <?= htmlspecialchars($complaint['token']) ?></p>
                            </div>
                        </div>

                        <!-- Right Section -->
                        <div class="flex items-center space-x-3">
                            <a href="dashboard.php"
                                class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm px-4 py-2 rounded-lg font-medium shadow transition">
                                Dashboard
                            </a>
                            <a href="../auth/logout.php"
                                class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-lg font-medium shadow transition">
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Add spacing after navigation -->
            <div class="h-6"></div>

            <!-- Back to All Complaints Button -->
            <div class="mb-6">
                <a href="view_complaints.php" class="inline-flex items-center text-slate-600 hover:text-slate-800 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span class="text-sm font-medium">Back to All Complaints</span>
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="bg-emerald-100 border-l-4 border-emerald-500 text-emerald-700 p-4 mb-6 rounded-lg shadow-sm" role="alert">
                    <div class="flex items-center">
                        <i class="lucide-check-circle w-5 h-5 mr-2"></i>
                        <p class="font-medium"><?= $_SESSION['success_message'] ?></p>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>



            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Complaint Information -->
                <div class="lg:col-span-2">
                    <div class="glassmorphism rounded-2xl shadow-xl p-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h2 class="text-2xl font-bold text-slate-900 capitalize mb-2">
                                    <?= htmlspecialchars($complaint['category']) ?> Complaint
                                </h2>
                                <p class="text-sm text-slate-500">Token: <?= htmlspecialchars($complaint['token']) ?></p>
                            </div>
                            <?php
                            $status_colors = [
                                'pending' => 'bg-gradient-to-r from-yellow-400 to-orange-500 text-white shadow-lg',
                                'in_progress' => 'bg-gradient-to-r from-blue-500 to-indigo-600 text-white shadow-lg',
                                'resolved' => 'bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg',
                                'rejected' => 'bg-gradient-to-r from-red-500 to-pink-600 text-white shadow-lg'
                            ];
                            $status_icon = [
                                'pending' => 'clock',
                                'in_progress' => 'loader-2',
                                'resolved' => 'check-circle',
                                'rejected' => 'x-circle'
                            ];
                            $status_text_colors = [
                                'pending' => 'text-orange-500 status-badge-orange',
                                'in_progress' => 'text-blue-600 status-badge-blue',
                                'resolved' => 'text-emerald-600 status-badge-emerald',
                                'rejected' => 'text-red-600 status-badge-red'
                            ];
                            $current_status = $complaint['status'];
                            $badge_class = $status_colors[$current_status] ?? 'bg-gradient-to-r from-gray-500 to-gray-600 text-white shadow-lg';
                            $icon = $status_icon[$current_status] ?? 'help-circle';
                            $text_class = $status_text_colors[$current_status] ?? 'text-gray-600';
                            ?>
                            <span class="font-bold text-lg rounded-full px-4 py-1 <?= $text_class ?>">
                                <?= ucfirst(str_replace('_', ' ', htmlspecialchars($complaint['status']))) ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div class="bg-slate-50 p-4 rounded-lg">
                                <p class="text-slate-500 text-sm font-medium mb-1">Complainant</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($complaint['user_name']) ?></p>
                                <p class="text-xs text-slate-500 capitalize"><?= htmlspecialchars($complaint['user_role']) ?></p>
                            </div>
                            <div class="bg-slate-50 p-4 rounded-lg">
                                <p class="text-slate-500 text-sm font-medium mb-1">Assignment Type</p>
                                <?php
                                $assignment_type_colors = [
                                    'auto_assigned' => 'text-blue-600',
                                    'admin_assigned' => 'text-purple-600',
                                    'reassigned'    => 'text-orange-600',
                                    'unassigned'    => 'text-gray-600'
                                ];
                                $assignment_type = $complaint['assignment_type'] ?? 'auto_assigned';
                                $assignment_text_class = $assignment_type_colors[$assignment_type] ?? 'text-gray-600';
                                switch ($assignment_type) {
    case 'admin_assigned':
        $assignment_label = 'Admin Assigned';
        break;
    case 'reassigned':
        $assignment_label = 'Reassigned';
        break;
    case 'auto_assigned':
        $assignment_label = 'Auto Assigned';
        break;
    default:
        $assignment_label = 'Unassigned';
}
                                ?>
                                <div class="mb-1">
                                    <span class="text-slate-500 font-medium">Assignment:</span>
                                    <span class="font-bold text-base <?= $assignment_text_class ?>">
                                        <?= $assignment_label ?>
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500"><?= $complaint['assignment_details']['message'] ?></p>
                            </div>
                             <div class="bg-slate-50 p-4 rounded-lg">
                                <p class="text-slate-500 text-sm font-medium mb-1">Assigned Technician</p>
                                <?php if (!empty($complaint['technician_id'])): ?>
                                    <div class="flex items-center gap-2">
                                        <p class="font-semibold text-slate-800"><?= htmlspecialchars($complaint['tech_name']) ?></p>
                                        <?php if ($complaint['tech_online'] == 0): ?>
                                            <span class="px-2 py-0.5 text-xs bg-red-100 text-red-800 rounded-full">Offline</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500">Specialization: <?= htmlspecialchars($complaint['tech_specialization'] ?? 'N/A') ?></p>
                                <?php elseif ($complaint['assignment_type'] === 'auto_assigned'): ?>
                                    <p class="font-semibold text-blue-600">Available to Category Technicians</p>
                                    <p class="text-xs text-slate-500">Category: <?= htmlspecialchars(ucfirst($complaint['category'])) ?></p>
                                <?php else: ?>
                                    <p class="font-semibold text-gray-500">Not Assigned</p>
                                <?php endif; ?>
                            </div>
                             <div class="bg-slate-50 p-4 rounded-lg">
                                <p class="text-slate-500 text-sm font-medium mb-1">Created On</p>
                                <p class="font-semibold text-slate-800"><?= date('M d, Y - h:i A', strtotime($complaint['created_at'])) ?></p>
                            </div>
                             <div class="bg-slate-50 p-4 rounded-lg">
                                <p class="text-slate-500 text-sm font-medium mb-1">Last Updated</p>
                                <p class="font-semibold text-slate-800"><?= date('M d, Y - h:i A', strtotime($complaint['updated_at'])) ?></p>
                            </div>
                        </div>

                        <?php if ($complaint['assignment_type'] === 'auto_assigned' && !empty($complaint['assignment_details']['available_technicians'])): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-slate-900 mb-4 flex items-center">
                                <i class="lucide-users w-5 h-5 mr-2"></i>
                                Available Technicians for <?= htmlspecialchars(ucfirst($complaint['category'])) ?> Category
                            </h3>
                            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($complaint['assignment_details']['available_technicians'] as $tech): ?>
                                    <div class="flex items-center justify-between bg-white rounded-xl p-4 border border-blue-100 shadow-sm hover:shadow-md transition-shadow">
                                        <div class="flex-1">
                                            <p class="font-semibold text-slate-800"><?= htmlspecialchars($tech['full_name']) ?></p>
                                            <p class="text-sm text-slate-500"><?= htmlspecialchars(ucfirst($tech['specialization'])) ?></p>
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="text-xs text-slate-500">Workload:</span>
                                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $tech['current_workload'] <= 2 ? 'bg-green-100 text-green-800' : ($tech['current_workload'] <= 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                                    <?= $tech['current_workload'] ?> active
                                                </span>
                                                <?php if ($tech['pending_count'] > 0): ?>
                                                <span class="text-xs text-orange-600 font-medium">(<?= $tech['pending_count'] ?> pending)</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($tech['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($tech['phone']) ?>" class="text-blue-600 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-colors">
                                            <i class="lucide-phone w-5 h-5"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3 flex items-center">
                                <i class="lucide-file-text w-5 h-5 mr-2"></i>
                                Description
                            </h3>
                            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <p class="text-slate-800 leading-relaxed">
                                    <?= nl2br(htmlspecialchars($complaint['description'])) ?>
                                </p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3 flex items-center">
                                <i class="lucide-message-square w-5 h-5 mr-2"></i>
                                Technician Remarks
                            </h3>
                            <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                                <p class="text-slate-800 leading-relaxed">
                                    <?= !empty($complaint['tech_note']) ? nl2br(htmlspecialchars($complaint['tech_note'])) : '<span class="text-slate-500 italic">No remarks yet.</span>' ?>
                                </p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Update Form -->
                <div>
                    <div class="glassmorphism rounded-2xl shadow-xl p-8">
                        <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center">
                            <i class="lucide-edit-3 w-5 h-5 mr-2"></i>
                            Update Complaint
                        </h2>
                        
                        <!-- Current Assignment Status -->
                        <div class="mb-6 p-4 bg-gradient-to-r from-slate-50 to-gray-50 rounded-xl border border-slate-200">
                            <h3 class="text-sm font-semibold text-slate-700 mb-3 flex items-center">
                                <i class="lucide-info w-4 h-4 mr-2"></i>
                                Current Assignment Status
                            </h3>
                            <?php
                            $assignment_type_colors = [
                                'auto_assigned' => 'text-blue-600',
                                'admin_assigned' => 'text-purple-600',
                                'reassigned'    => 'text-orange-600',
                                'unassigned'    => 'text-gray-600'
                            ];
                            $assignment_type = $complaint['assignment_type'] ?? 'auto_assigned';
                            $assignment_text_class = $assignment_type_colors[$assignment_type] ?? 'text-gray-600';
                            switch ($assignment_type) {
    case 'admin_assigned':
        $assignment_label = 'Admin Assigned';
        break;
    case 'reassigned':
        $assignment_label = 'Reassigned';
        break;
    case 'auto_assigned':
        $assignment_label = 'Auto Assigned';
        break;
    default:
        $assignment_label = 'Unassigned';
}
                            ?>
                            <div class="mb-2">
                                <span class="text-slate-500 font-medium">Assignment:</span>
                                <span class="font-bold text-base <?= $assignment_text_class ?>">
                                    <?= $assignment_label ?>
                                </span>
                            </div>
                            <?php if ($complaint['assignment_type'] === 'admin_assigned'): ?>
                                <p class="text-sm text-slate-600">
                                    Currently assigned to: <strong><?= htmlspecialchars($complaint['tech_name'] ?? 'Unknown') ?></strong>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-slate-600">
                                    Available to all technicians with <strong><?= htmlspecialchars(ucfirst($complaint['category'])) ?></strong> specialization
                                </p>
                                <?php if (!empty($complaint['assignment_details']['available_technicians'])): ?>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?= count($complaint['assignment_details']['available_technicians']) ?> technician(s) available
                                </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" class="space-y-6">
                            <div>
                                <label for="status" class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
                                <select id="status" name="status" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                                    <option value="pending" <?= $complaint['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $complaint['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $complaint['status'] === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="rejected" <?= $complaint['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>

                            <div>
                                <label for="technician_id" class="block text-sm font-semibold text-slate-700 mb-2">Assign Technician</label>
                                <select id="technician_id" name="technician_id" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                                    <option value="">Auto Assigned by System</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?= $tech['id'] ?>" <?= $complaint['technician_id'] == $tech['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tech['full_name']) ?> (<?= htmlspecialchars($tech['specialization']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-slate-500 mt-2">
                                    Leave empty to keep auto-assignment, or select a specific technician for admin assignment.
                                </p>
                            </div>

                            <div>
                                 <label for="remarks" class="block text-sm font-semibold text-slate-700 mb-2">Remarks (Optional)</label>
                                 <textarea id="remarks" name="remarks" rows="4" class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors resize-none"><?= htmlspecialchars($complaint['tech_note'] ?? '') ?></textarea>
                            </div>

                            <div class="flex flex-col gap-3">
                                <button type="submit" class="update-button w-full py-3 px-6 rounded-lg text-white font-semibold text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                                    <i class="lucide-save w-4 h-4 inline-block mr-2"></i>
                                    Update Complaint
                                </button>
                                <button type="submit" name="make_admin" value="1" class="bg-purple-600 hover:bg-purple-700 w-full py-3 px-6 rounded-lg text-white font-semibold text-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition">
                                    <i class="lucide-alert-triangle w-4 h-4 inline-block mr-2"></i>
                                    Make Urgent (Admin Assign)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>


        </div>
    </div>

</body>
</html> 