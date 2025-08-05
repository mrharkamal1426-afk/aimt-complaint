<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if the user is logged in and is a superadmin
if (!is_logged_in() || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$message = '';
$message_type = '';
$step = $_GET['step'] ?? '1';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation removed
    $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'generate_reset_code':
                handleGenerateResetCode($mysqli, $_POST);
                break;
            case 'verify_and_reset':
                handleVerifyAndReset($mysqli, $_POST);
                break;
        }
} 

function handleGenerateResetCode($mysqli, $data) {
    global $message, $message_type, $step;
    
    $target_admin_id = intval($data['target_admin_id'] ?? 0);
    $verification_password = $data['verification_password'] ?? '';
    
    // Validation
    if (!$target_admin_id || !$verification_password) {
        $message = 'All fields are required.';
        $message_type = 'error';
        return;
    }
    
    // Verify current superadmin's password
    $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Invalid admin account.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    
    $current_admin = $result->fetch_assoc();
    if (!password_verify($verification_password, $current_admin['password_hash'])) {
        $message = 'Verification password is incorrect.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Verify target admin exists
    $stmt = $mysqli->prepare("SELECT id, full_name, username FROM users WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('i', $target_admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $message = 'Target admin account not found.';
        $message_type = 'error';
        $stmt->close();
        return;
    }
    
    $target_admin = $result->fetch_assoc();
    $stmt->close();
    
    // Generate secure reset code (8 characters, alphanumeric)
    $reset_code = strtoupper(substr(md5(uniqid() . time()), 0, 8));
    $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store reset code in session for verification
    $_SESSION['admin_reset_data'] = [
        'target_admin_id' => $target_admin_id,
        'reset_code' => $reset_code,
        'expiry_time' => $expiry_time,
        'target_admin_name' => $target_admin['full_name'],
        'target_admin_username' => $target_admin['username']
    ];
    
    $message = "Reset code generated successfully for {$target_admin['full_name']} ({$target_admin['username']}).<br><br><strong>Reset Code: {$reset_code}</strong><br><br>This code will expire in 1 hour.";
    $message_type = 'success';
    $step = '2';
}

function handleVerifyAndReset($mysqli, $data) {
    global $message, $message_type, $step;
    
    $reset_code = trim($data['reset_code'] ?? '');
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    // Validation
    if (!$reset_code || !$new_password || !$confirm_password) {
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
    
    // Verify reset data exists
    if (!isset($_SESSION['admin_reset_data'])) {
        $message = 'No reset session found. Please generate a new reset code.';
        $message_type = 'error';
        $step = '1';
        return;
    }
    
    $reset_data = $_SESSION['admin_reset_data'];
    
    // Check if reset code has expired
    if (strtotime($reset_data['expiry_time']) < time()) {
        $message = 'Reset code has expired. Please generate a new one.';
        $message_type = 'error';
        unset($_SESSION['admin_reset_data']);
        $step = '1';
        return;
    }
    
    // Verify reset code
    if ($reset_code !== $reset_data['reset_code']) {
        $message = 'Invalid reset code.';
        $message_type = 'error';
        return;
    }
    
    // Update password
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'superadmin'");
    $stmt->bind_param('si', $new_password_hash, $reset_data['target_admin_id']);
    
    if ($stmt->execute()) {
        $message = "Password reset successfully for {$reset_data['target_admin_name']} ({$reset_data['target_admin_username']}).";
        $message_type = 'success';
        log_security_action('reset_password', $reset_data['target_admin_username'], "Password reset for admin: {$reset_data['target_admin_name']}");
        unset($_SESSION['admin_reset_data']);
        $step = '1';
    } else {
        $message = 'Failed to reset password.';
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
    <title>Reset Admin Password</title>
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
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col md:ml-64 w-full">
            <!-- Include the new header template -->
            <?php include '../templates/superadmin_header.php'; ?>
            
            <!-- Content Container with proper spacing -->
            <div class="px-4 sm:px-6 md:px-8 py-6"><!-- Message Display -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Security Notice -->
        <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
            <div class="flex items-start">
                <i class="lucide-alert-triangle text-amber-600 mt-1 mr-3"></i>
                <div>
                    <h3 class="font-semibold text-amber-800 mb-2">Security Notice</h3>
                    <p class="text-amber-700 text-sm">
                        This feature allows you to reset passwords for other superadmin accounts. 
                        This action requires your own password verification and generates a temporary reset code.
                        The reset code expires in 1 hour for security.
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 1: Generate Reset Code -->
        <?php if ($step === '1'): ?>
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 sm:p-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-6">Step 1: Generate Reset Code</h2>
                <form method="POST" class="space-y-6">
                    
                    <input type="hidden" name="action" value="generate_reset_code">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Select Admin Account</label>
                        <select name="target_admin_id" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Admin Account</option>
                            <?php foreach ($admins as $admin): ?>
                                <?php if ($admin['id'] != $_SESSION['user_id']): ?>
                                    <option value="<?= $admin['id'] ?>"><?= htmlspecialchars($admin['full_name']) ?> (<?= htmlspecialchars($admin['username']) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-slate-500 mt-1">You cannot reset your own password using this method.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Your Password (Verification)</label>
                        <input type="password" name="verification_password" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter your current password">
                        <p class="text-sm text-slate-500 mt-1">Enter your own password to verify you have permission to reset other admin passwords.</p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            Generate Reset Code
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Step 2: Enter Reset Code and New Password -->
        <?php if ($step === '2' && isset($_SESSION['admin_reset_data'])): ?>
            <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-4 sm:p-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-6">Step 2: Reset Password</h2>
                <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center">
                        <i class="lucide-info text-blue-600 mr-3"></i>
                        <div>
                            <p class="text-blue-800 font-medium">Reset Code Generated</p>
                            <p class="text-blue-700 text-sm">Please use the reset code above to complete the password reset for <strong><?= htmlspecialchars($_SESSION['admin_reset_data']['target_admin_name']) ?></strong>.</p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="space-y-6">
                    
                    <input type="hidden" name="action" value="verify_and_reset">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Reset Code</label>
                        <input type="text" name="reset_code" required class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter the 8-character reset code" maxlength="8" style="text-transform: uppercase;">
                        <p class="text-sm text-slate-500 mt-1">Enter the 8-character reset code shown above.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">New Password</label>
                        <input type="password" name="new_password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Enter new password">
                        <p class="text-sm text-slate-500 mt-1">Password must be at least 8 characters long.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" class="w-full border border-slate-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Confirm new password">
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-end gap-3">
                        <a href="reset_admin_password.php" class="px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200 transition-colors text-center">Cancel</a>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">Reset Password</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Back to Admin Management -->
        <div class="mt-8 text-center">
            <a href="admin_management.php" class="inline-flex items-center px-4 py-2 text-slate-600 bg-slate-100 rounded-md hover:bg-slate-200 transition-colors">
                <i class="lucide-arrow-left mr-2"></i>
                Back to Admin Management
            </a>
                </div> <!-- Close content container -->
            </div> <!-- Close main content -->

    <script>
        // Auto-uppercase reset code input
        document.addEventListener('DOMContentLoaded', function() {
            const resetCodeInput = document.querySelector('input[name="reset_code"]');
            if (resetCodeInput) {
                resetCodeInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
        });
    </script>
</body>
</html> 