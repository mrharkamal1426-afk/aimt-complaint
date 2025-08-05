<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Production error handling
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../auth/login.php?error=unauthorized');
}

$tech_id = $_SESSION['user_id'];

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        try {
            // Get current status
            $stmt = $mysqli->prepare("SELECT is_online, full_name FROM users WHERE id = ? AND role = 'technician'");
            if (!$stmt) {
                throw new Exception("Database error: " . $mysqli->error);
            }
            $stmt->bind_param('i', $tech_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $technician = $result->fetch_assoc();
            $stmt->close();
            
            if ($technician) {
                $new_status = $technician['is_online'] == 1 ? 0 : 1;
                $status_text = $new_status == 1 ? 'online' : 'offline';
                
                // Update status in database
                $stmt = $mysqli->prepare("UPDATE users SET is_online = ? WHERE id = ? AND role = 'technician'");
                if (!$stmt) {
                    throw new Exception("Database error: " . $mysqli->error);
                }
                $stmt->bind_param('ii', $new_status, $tech_id);
                $success = $stmt->execute();
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($success && $affected_rows > 0) {
                    // Log the status change
                    error_log("Technician ID: $tech_id, Name: {$technician['full_name']} changed status to: $status_text");
                    
                    if ($new_status == 1) {
                        // If going online, redirect to regular dashboard with success message
                        $_SESSION['status_message'] = "Successfully went online!";
                        
                        // Try to redirect, if it fails, show a simple message
                        try {
                            redirect('dashboard.php');
                        } catch (Exception $e) {
                            // Fallback: show success message and manual redirect
                            echo "<div style='text-align: center; padding: 50px; font-family: Arial, sans-serif;'>";
                            echo "<h2 style='color: green;'>Successfully went online!</h2>";
                            echo "<p>Status updated in database. You can now access your dashboard.</p>";
                            echo "<a href='dashboard.php' style='background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;'>Go to Dashboard</a>";
                            echo "</div>";
                            exit;
                        }
                    } else {
                        // If going offline, show success message and logout
                        $_SESSION['logout_message'] = "Successfully went offline. You have been logged out.";
                        redirect('../auth/logout.php');
                    }
                } else {
                    throw new Exception("Failed to update status in database");
                }
            } else {
                throw new Exception("Technician not found");
            }
        } catch (Exception $e) {
            error_log("Error updating technician status: " . $e->getMessage());
            $_SESSION['error_message'] = "Failed to update status: " . $e->getMessage();
            redirect('offline_dashboard.php');
        }
    }
}

// Get technician's information with error handling
try {
    $stmt = $mysqli->prepare("SELECT full_name, specialization, is_online, last_login FROM users WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }
    $stmt->bind_param('i', $tech_id);
    $stmt->execute();
    $stmt->bind_result($full_name, $specialization, $is_online, $last_login);
    $stmt->fetch();
    $stmt->close();

    // If technician is online, redirect to regular dashboard
    if ($is_online == 1) {
        redirect('dashboard.php');
    }
} catch (Exception $e) {
    // If there's a database error, set default values
    $full_name = $_SESSION['full_name'] ?? 'Technician';
    $specialization = 'Unknown';
    $is_online = 0;
    $last_login = null;
}

// Get current assignments with error handling
$current_complaints = [];
$current_hostel_issues = [];
$total_current_work = 0;

try {
    // Get current complaints
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as count FROM complaints 
        WHERE technician_id = ? AND status IN ('pending', 'in_progress')
    ");
    if ($stmt) {
        $stmt->bind_param('i', $tech_id);
        $stmt->execute();
        $stmt->bind_result($complaint_count);
        $stmt->fetch();
        $stmt->close();
    } else {
        $complaint_count = 0;
    }

    // Get hostel issues count
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as count FROM hostel_issues 
        WHERE technician_id = ? AND status IN ('in_progress')
    ");
    if ($stmt) {
        $stmt->bind_param('i', $tech_id);
        $stmt->execute();
        $stmt->bind_result($hostel_count);
        $stmt->fetch();
        $stmt->close();
    } else {
        $hostel_count = 0;
    }

    $total_current_work = $complaint_count + $hostel_count;
} catch (Exception $e) {
    $complaint_count = 0;
    $hostel_count = 0;
    $total_current_work = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - <?= htmlspecialchars($full_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f1f5f9;
            min-height: 100vh;
        }
        .status-card {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 20px 25px -5px rgba(220, 38, 38, 0.3);
        }
        .work-card {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(71, 85, 105, 0.3);
        }
        .btn-primary {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 10px 15px -3px rgba(5, 150, 105, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: rgba(71, 85, 105, 0.8);
            backdrop-filter: blur(10px);
        }
        .btn-secondary:hover {
            background: rgba(100, 116, 139, 0.8);
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Header -->
    <div class="status-card p-6 rounded-b-3xl">
        <div class="max-w-md mx-auto text-center">
            <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4 p-2">
                <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">You're Offline</h1>
            <p class="text-red-100 text-sm">No new assignments will be received</p>
        </div>
    </div>

    <div class="max-w-md mx-auto px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['status_message'])): ?>
        <div class="bg-green-500 text-white p-4 rounded-xl mb-6 text-center">
            <div class="flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span><?= htmlspecialchars($_SESSION['status_message']) ?></span>
            </div>
        </div>
        <?php unset($_SESSION['status_message']); endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-500 text-white p-4 rounded-xl mb-6 text-center">
            <div class="flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <!-- Technician Info -->
        <div class="work-card p-6 rounded-2xl mb-6">
            <div class="text-center">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center mx-auto mb-3 text-white font-semibold text-lg">
                    <?= strtoupper(substr($full_name, 0, 1)) ?>
                </div>
                <h2 class="text-lg font-semibold text-white mb-1"><?= htmlspecialchars($full_name) ?></h2>
                <p class="text-slate-400 text-sm"><?= htmlspecialchars(ucfirst($specialization)) ?></p>
            </div>
        </div>

        <!-- Work Summary -->
        <div class="work-card p-6 rounded-2xl mb-6">
            <h3 class="text-white font-semibold mb-4 text-center">Current Work</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold text-white"><?= $complaint_count ?></div>
                    <div class="text-slate-400 text-xs">Complaints</div>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                    </div>
                    <div class="text-2xl font-bold text-white"><?= $hostel_count ?></div>
                    <div class="text-slate-400 text-xs">Hostel Issues</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-3">
            <form method="POST" class="w-full">
                <input type="hidden" name="action" value="toggle_status">
                <button type="submit" class="btn-primary w-full py-4 rounded-xl font-semibold text-white transition-all duration-300">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0" />
                    </svg>
                    Go Online
                </button>
            </form>
            
            <?php if ($total_current_work > 0): ?>
            <a href="complaints.php" class="btn-secondary w-full py-4 rounded-xl font-semibold text-white transition-all duration-300 block text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                View Work (<?= $total_current_work ?>)
            </a>
            <?php endif; ?>
        </div>

        <!-- Info Card -->
        <div class="work-card p-6 rounded-2xl mt-6">
            <div class="flex items-start space-x-3">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="text-sm text-slate-300">
                    <p class="font-medium text-white mb-2">Offline Mode</p>
                    <p>You can still work on existing assignments. Contact your supervisor to go online and receive new work.</p>
                </div>
            </div>
        </div>

        <!-- Logout -->
        <div class="text-center mt-8">
            <a href="../logout.php" class="text-slate-400 hover:text-white text-sm transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Logout
            </a>
        </div>
    </div>
</body>
</html> 