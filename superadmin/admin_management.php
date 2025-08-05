<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if the user is logged in and is a superadmin
if (!is_logged_in() || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$message = '';
$message_type = '';
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation removed
    $action = $_POST['action'] ?? 'list';
        
        switch ($action) {
            case 'add_admin':
                handleAddAdmin($mysqli, $_POST);
                break;
            case 'change_password':
                handleChangePassword($mysqli, $_POST);
                break;
            case 'update_profile':
                handleUpdateProfile($mysqli, $_POST);
                break;
            case 'delete_admin':
                handleDeleteAdmin($mysqli, $_POST);
                break;
        }
}

function handleAddAdmin($mysqli, $data) {
    global $message, $message_type;
    
    $full_name = trim($data['full_name'] ?? '');
    $username = trim($data['username'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $password = $data['password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    // Validation
    if (!$full_name || !$username || !$phone || !$password) {
        $message = 'All fields are required.';
        $message_type = 'error';
        return;
    }
    
    if ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
        return;
    }
    
    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $message_type = 'error';
        return;
    }
    
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = 'Phone number must be exactly 10 digits.';
        $message_type = 'error';
        return;
    }
    
    // Check if username already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = 'Username already exists.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Check if phone already exists
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param('s', $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = 'Phone number already registered.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Create new superadmin
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $special_code = 'ADMIN' . strtoupper(substr(md5(uniqid()), 0, 6));
    
    $stmt = $mysqli->prepare("INSERT INTO users (full_name, phone, role, special_code, username, password_hash) VALUES (?, ?, 'superadmin', ?, ?, ?)");
    $stmt->bind_param('sssss', $full_name, $phone, $special_code, $username, $password_hash);
    
    if ($stmt->execute()) {
        $message = 'Superadmin account created successfully.';
        $message_type = 'success';
        log_security_action('add_admin', $username, "Created superadmin account: {$full_name} ({$username})");
    } else {
        $message = 'Failed to create superadmin account.';
        $message_type = 'error';
    }
    $stmt->close();
}

function handleChangePassword($mysqli, $data) {
    global $message, $message_type;
    
    $admin_id = intval($data['admin_id'] ?? 0);
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    // Validation
    if (!$admin_id || !$current_password || !$new_password) {
        $message = 'All fields are required.';
        $message_type = 'error';
        return;
    }
    
    if ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $message_type = 'error';
        return;
    }
    
    if (strlen($new_password) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $message_type = 'error';
        return;
    }
    
    // Verify current password
    $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Invalid admin account.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    
    $user = $result->fetch_assoc();
    if (!password_verify($current_password, $user['password_hash'])) {
        $message = 'Current password is incorrect.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Update password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('si', $new_password_hash, $admin_id);
    
    if ($stmt->execute()) {
        $message = 'Password changed successfully.';
        $message_type = 'success';
        log_security_action('change_password', $reset_data['target_admin_username'], "Password changed for admin: {$reset_data['target_admin_name']}");
    } else {
        $message = 'Failed to change password.';
        $message_type = 'error';
    }
    $stmt->close();
}

function handleUpdateProfile($mysqli, $data) {
    global $message, $message_type;
    
    $admin_id = intval($data['admin_id'] ?? 0);
    $full_name = trim($data['full_name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    
    // Validation
    if (!$admin_id || !$full_name || !$phone) {
        $message = 'All fields are required.';
        $message_type = 'error';
        return;
    }
    
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $message = 'Phone number must be exactly 10 digits.';
        $message_type = 'error';
        return;
    }
    
    // Check if phone already exists for another user
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
    $stmt->bind_param('si', $phone, $admin_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $message = 'Phone number already registered by another user.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Update profile
    $stmt = $mysqli->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('ssi', $full_name, $phone, $admin_id);
    
    if ($stmt->execute()) {
        $message = 'Profile updated successfully.';
        $message_type = 'success';
        log_security_action('update_profile', $reset_data['target_admin_username'], "Profile updated for admin: {$reset_data['target_admin_name']}");
    } else {
        $message = 'Failed to update profile.';
        $message_type = 'error';
    }
    $stmt->close();
}

function handleDeleteAdmin($mysqli, $data) {
    global $message, $message_type;
    
    $admin_id = intval($data['admin_id'] ?? 0);
    $current_user_id = $_SESSION['user_id'];
    
    // Prevent self-deletion
    if ($admin_id === $current_user_id) {
        $message = 'You cannot delete your own account.';
        $message_type = 'error';
        return;
    }
    
    // Check if this is the last superadmin
    $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    if ($count <= 1) {
        $message = 'Cannot delete the last superadmin account.';
        $message_type = 'error';
        return;
    }
    
    // Delete admin
    $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('i', $admin_id);
    
    if ($stmt->execute()) {
        $message = 'Superadmin account deleted successfully.';
        $message_type = 'success';
        log_security_action('delete_admin', $target_admin_id, "Deleted superadmin account ID: {$target_admin_id}");
    } else {
        $message = 'Failed to delete superadmin account.';
        $message_type = 'error';
    }
    $stmt->close();
}

// Fetch all superadmin accounts
$admins = [];
$stmt = $mysqli->prepare("SELECT id, full_name, username, phone, created_at FROM users WHERE role = 'superadmin' ORDER BY created_at ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/lucide.min.css" rel="stylesheet">
    <style>
        .sidebar-link {
            transition: all 0.3s ease;
        }
        .sidebar-link:hover {
            background-color: #f1f5f9;
            transform: translateX(4px);
        }
        .logo-glow {
            filter: drop-shadow(0 0 8px rgba(59, 130, 246, 0.5));
        }
        .institute-name {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Mobile responsive styles */
        @media (max-width: 768px) {
            /* Force mobile layout */
            body {
                overflow-x: hidden;
            }
            
            .mobile-nav {
                flex-direction: column !important;
                height: auto !important;
                padding: 1rem 0 !important;
                gap: 1rem !important;
                position: relative !important;
                z-index: 10 !important;
            }
            
            .mobile-nav .flex {
                flex-direction: column !important;
                gap: 1rem !important;
                align-items: center !important;
                width: 100% !important;
            }
            
            .mobile-nav .space-x-4 {
                flex-direction: column !important;
                gap: 0.75rem !important;
                width: 100% !important;
                align-items: center !important;
                display: flex !important;
            }
            
            .mobile-nav .space-x-4 > * {
                width: 100% !important;
                text-align: center !important;
                display: block !important;
            }

            .mobile-nav .space-x-4 a,
            .mobile-nav .space-x-4 span {
                display: block !important;
                width: 100% !important;
                padding: 0.5rem !important;
                margin: 0 !important;
            }

            .mobile-nav-buttons {
                flex-direction: column !important;
                gap: 0.75rem !important;
                width: 100% !important;
                align-items: center !important;
                display: flex !important;
            }

            .mobile-welcome {
                display: block !important;
                text-align: center !important;
                margin-bottom: 0 !important;
                padding: 0.5rem !important;
                background-color: #f8fafc !important;
                border-radius: 0.5rem !important;
                width: 100% !important;
            }

            .mobile-btn {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                padding: 0.75rem !important;
                margin: 0 !important;
                border-radius: 0.5rem !important;
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .mobile-nav {
                padding: 0.75rem 0;
                gap: 0.75rem;
            }
            
            .mobile-nav h1 {
                font-size: 1.25rem;
            }
            
            .mobile-nav p {
                font-size: 0.875rem;
            }
            
            .mobile-btn {
                padding: 0.5rem;
                font-size: 0.875rem;
            }
            
            .mobile-welcome {
                font-size: 0.875rem;
                padding: 0.5rem;
            }

            .main-content {
                padding-top: 1rem !important;
            }
        }

        /* Ensure proper spacing on all mobile devices */
        @media (max-width: 768px) {
            /* Force main content layout */
            .main-content {
                margin-top: 2rem !important;
                padding-top: 1rem !important;
                position: relative !important;
                z-index: 1 !important;
                clear: both !important;
            }
            
            /* Prevent any overlapping */
            nav {
                position: relative !important;
                z-index: 10 !important;
                clear: both !important;
            }
            
            /* Fix main content button grid on mobile */
            .mobile-button-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
                margin-top: 2rem !important;
                clear: both !important;
            }

            /* Ensure buttons don't overlap */
            .mobile-button-grid button,
            .mobile-button-grid a {
                width: 100% !important;
                padding: 0.5rem !important;
                margin: 0.5rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 0.5rem !important;
            }

            /* Add more spacing between sections */
            .mb-6 {
                margin-bottom: 2rem !important;
            }

            /* Force all elements to be block */
            .mobile-button-grid > * {
                display: block !important;
                width: 100% !important;
            }

            /* Prevent any floating elements */
            * {
                float: none !important;
            }

            /* Force proper stacking */
            .mobile-nav,
            .main-content,
            .mobile-button-grid {
                display: block !important;
                width: 100% !important;
                max-width:100% !important;
            }

            /* Navigation container */
            .mobile-nav-container {
                position: relative !important;
                z-index: 10 !important;
                clear: both !important;
                display: block !important;
                width: 100% !important;
            }

            /* Mobile separator */
            .mobile-separator {
                height: 0.5rem !important;
                clear: both !important;
                display: block !important;
                width: 80% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm border-b border-slate-200 mobile-nav-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 mobile-nav">
                <div class="flex items-center">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-8 h-8 mr-3">
                    <div>
                        <h1 class="text-lg font-bold text-slate-900">Admin Management</h1>
                        <p class="text-sm text-slate-500">Superadmin Portal</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4 mobile-nav-buttons">
                    <span class="text-sm text-slate-600 mobile-welcome">Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin') ?></span>
                    <a href="dashboard.php" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition-colors text-sm mobile-btn">
                        Dashboard
                    </a>
                    <a href="../auth/logout.php" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm mobile-btn">
                        Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Clear separation -->
    <div class="mobile-separator"></div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 main-content">


            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-2 sm:gap-3 lg:gap-4 mobile-button-grid">
                <button onclick="showAddAdmin()" class="bg-blue-600 text-white px-2 sm:px-4 lg:px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center justify-center text-sm sm:text-base">
                    <i class="lucide-plus mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Add New Admin</span>
                    <span class="sm:hidden">Add Admin</span>
                </button>
                <button onclick="showChangePassword()" class="bg-green-600 text-white px-2 sm:px-4 lg:px-6 py-2 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center text-sm sm:text-base">
                    <i class="lucide-key mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Change Password</span>
                    <span class="sm:hidden">Password</span>
                </button>
                <button onclick="showUpdateProfile()" class="bg-purple-600 text-white px-2 sm:px-4 lg:px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors flex items-center justify-center text-sm sm:text-base">
                    <i class="lucide-user-edit mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Update Profile</span>
                    <span class="sm:hidden">Profile</span>
                </button>
                <a href="reset_admin_password.php" class="bg-orange-600 text-white px-2 sm:px-4 lg:px-6 py-2 rounded-lg hover:bg-orange-700 transition-colors flex items-center justify-center text-sm sm:text-base">
                    <i class="lucide-shield mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Reset Admin Password</span>
                    <span class="sm:hidden">Reset</span>
                </a>
                <a href="security_logs.php" class="bg-red-600 text-white px-2 sm:px-4 lg:px-6 py-2 rounded-lg hover:bg-red-700 transition-colors flex items-center justify-center text-sm sm:text-base">
                    <i class="lucide-file-text mr-1 sm:mr-2"></i>
                    <span class="hidden sm:inline">Security Logs</span>
                    <span class="sm:hidden">Logs</span>
                </a>
            </div>

            <!-- Admin List -->
            <div class="bg-white rounded-lg shadow-sm border border-slate-200">
                <div class="p-3 sm:p-4 lg:p-6 border-b border-slate-200">
                    <h2 class="text-lg sm:text-xl font-semibold text-slate-800">Superadmin Accounts</h2>
                    <p class="text-slate-600 mt-1 text-sm">Manage all superadmin accounts in the system</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                                <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden sm:table-cell">Username</th>
                                <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden md:table-cell">Phone</th>
                                <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider hidden lg:table-cell">Created</th>
                                <th class="px-2 sm:px-3 lg:px-6 py-2 sm:py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <?php foreach ($admins as $admin): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-2 sm:px-3 lg:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-900"><?= htmlspecialchars($admin['full_name']) ?></div>
                                        <?php if ($admin['id'] == $_SESSION['user_id']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Current User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-3 lg:px-6 py-3 sm:py-4 whitespace-nowrap text-sm text-slate-900 hidden sm:table-cell"><?= htmlspecialchars($admin['username']) ?></td>
                                    <td class="px-2 sm:px-3 lg:px-6 py-3 sm:py-4 whitespace-nowrap text-sm text-slate-900 hidden md:table-cell"><?= htmlspecialchars($admin['phone']) ?></td>
                                    <td class="px-2 sm:px-3 lg:px-6 py-3 sm:py-4 whitespace-nowrap text-sm text-slate-500 hidden lg:table-cell"><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                                    <td class="px-2 sm:px-3 lg:px-6 py-3 sm:py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                            <button onclick="deleteAdmin(<?= $admin['id'] ?>)" class="text-red-600 hover:text-red-900 text-xs sm:text-sm">Delete</button>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs sm:text-sm">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Admin Modal -->
            <div id="addAdminModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">Add New Superadmin</h3>
                    <form method="POST" class="space-y-4">
                        
                        <input type="hidden" name="action" value="add_admin">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                            <input type="text" name="full_name" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                            <input type="text" name="username" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" pattern="[0-9]{10}" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="10 digits">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                            <input type="password" name="password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Confirm Password</label>
                            <input type="password" name="confirm_password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" onclick="hideAddAdmin()" class="px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Add Admin</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Modal -->
            <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">Change Password</h3>
                    <form method="POST" class="space-y-4">
                        
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select Admin</label>
                            <select name="admin_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['full_name']) ?> (<?= htmlspecialchars($admin['username']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Current Password</label>
                            <input type="password" name="current_password" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                            <input type="password" name="new_password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                            <input type="password" name="confirm_password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" onclick="hideChangePassword()" class="px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Update Profile Modal -->
            <div id="updateProfileModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">Update Profile</h3>
                    <form method="POST" class="space-y-4">
                        
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select Admin</label>
                            <select name="admin_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Admin</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['full_name']) ?> (<?= htmlspecialchars($admin['username']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                            <input type="text" name="full_name" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                            <input type="tel" name="phone" pattern="[0-9]{10}" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="10 digits">
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" onclick="hideUpdateProfile()" class="px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4 text-red-600">Delete Superadmin</h3>
                    <p class="text-slate-600 mb-6">Are you sure you want to delete this superadmin account? This action cannot be undone.</p>
                    <form method="POST" class="space-y-4">
                        
                        <input type="hidden" name="action" value="delete_admin">
                        <input type="hidden" name="admin_id" id="deleteAdminId">
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="hideDeleteModal()" class="px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showAddAdmin() {
            document.getElementById('addAdminModal').classList.remove('hidden');
        }
        
        function hideAddAdmin() {
            document.getElementById('addAdminModal').classList.add('hidden');
        }
        
        function showChangePassword() {
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }
        
        function hideChangePassword() {
            document.getElementById('changePasswordModal').classList.add('hidden');
        }
        
        function showUpdateProfile() {
            document.getElementById('updateProfileModal').classList.remove('hidden');
        }
        
        function hideUpdateProfile() {
            document.getElementById('updateProfileModal').classList.add('hidden');
        }
        
        function deleteAdmin(adminId) {
            document.getElementById('deleteAdminId').value = adminId;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('fixed')) {
                event.target.classList.add('hidden');
            }
        });
    </script>
</body>
</html> 