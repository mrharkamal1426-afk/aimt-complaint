<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student','faculty','nonteaching','outsourced_vendor','technician'])) {
    header('Location: ../auth/login.php?error=unauthorized');
    exit();
}
$categories = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac','other'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$phone = $_SESSION['phone'];
$specialization = $_SESSION['specialization'] ?? null;

$errors = [];
$success = false;
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $room_no = trim($_POST['room_no'] ?? '');
        $hostel_type = ($role === 'student') ? ($_POST['hostel_type'] ?? null) : null;
        $category = $_POST['category'] ?? '';
        $description = trim($_POST['description'] ?? '');
        if (!$room_no || ($role==='student' && !$hostel_type) || !$category || !$description) {
            $errors[] = 'All fields are required.';
        }
        if (!in_array($category, $categories)) {
            $errors[] = 'Invalid category.';
        }
        if (!$errors) {
            $bytes = random_bytes(16);
            $token = bin2hex($bytes);

            // Find any technician with matching specialization to show contact info
            $tech_stmt = $mysqli->prepare("
                SELECT id, full_name, phone 
                FROM users 
                WHERE role = 'technician' 
                AND specialization = ? 
                LIMIT 1
            ");
            $tech_stmt->bind_param('s', $category);
            $tech_stmt->execute();
            $tech_result = $tech_stmt->get_result();
            $technician = $tech_result->fetch_assoc();
            $tech_stmt->close();

            // Insert complaint without technician assignment
            $stmt = $mysqli->prepare("
                INSERT INTO complaints (
                    user_id, token, room_no, hostel_type, category, description, 
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->bind_param('isssss', $user_id, $token, $room_no, $hostel_type, $category, $description);
            
            if ($stmt->execute()) {
                $complaint_id = $stmt->insert_id;
                $stmt->close();
                
                // Auto-assign the complaint to a technician based on workload
                $assigned_technician = auto_assign_complaint($complaint_id);
                
                // Store token in session and redirect to avoid resubmission
                $_SESSION['complaint_token'] = $token;
                $_SESSION['assigned_technician'] = $assigned_technician;
                if ($role === 'technician') {
                    header('Location: dashboard.php?success=1');
                } else {
                    header('Location: submit_complaint.php?success=1');
                }
                exit;
            } else {
                $errors[] = 'Something went wrong. Please try again.';
            }
            $stmt->close();
        }
    }
}
// Show success page if redirected
if (isset($_GET['success']) && isset($_SESSION['complaint_token'])) {
    $token = $_SESSION['complaint_token'];
    unset($_SESSION['complaint_token']);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Success • Complaint Portal</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-dark: #0a0a0a;
                --bg-card: #111111;
                --accent: #10b981;
                --accent-dark: #059669;
                --accent-glow: rgba(16, 185, 129, 0.15);
                --text-primary: #f8fafc;
                --text-secondary: #94a3b8;
            }
            html { height: 100%; background: var(--bg-dark); }
            body {
                min-height: 100vh;
                margin: 0;
                background: 
                    radial-gradient(circle at 0% 0%, var(--accent-glow) 0%, transparent 50%),
                    radial-gradient(circle at 100% 100%, var(--accent-glow) 0%, transparent 50%),
                    var(--bg-dark);
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Outfit', sans-serif;
                color: var(--text-primary);
                -webkit-font-smoothing: antialiased;
                overflow: hidden;
            }
            /* Floating particles */
            .particles {
                position: fixed;
                inset: 0;
                pointer-events: none;
                z-index: 0;
            }
            .particle {
                position: absolute;
                border-radius: 50%;
                background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
                opacity: 0.18;
                animation: floatParticle 12s linear infinite;
            }
            .particle.p1 { width: 60px; height: 60px; left: 10vw; top: 20vh; animation-delay: 0s; }
            .particle.p2 { width: 40px; height: 40px; left: 70vw; top: 60vh; animation-delay: 3s; }
            .particle.p3 { width: 30px; height: 30px; left: 50vw; top: 80vh; animation-delay: 6s; }
            .particle.p4 { width: 50px; height: 50px; left: 80vw; top: 10vh; animation-delay: 1.5s; }
            @keyframes floatParticle {
                0% { transform: translateY(0) scale(1); opacity: 0.18; }
                50% { transform: translateY(-30px) scale(1.1); opacity: 0.28; }
                100% { transform: translateY(0) scale(1); opacity: 0.18; }
            }
            .success-card {
                position: relative;
                width: 90vw;
                max-width: 480px;
                max-height: 100vh;
                background: linear-gradient(145deg, rgba(17, 17, 17, 0.9), rgba(17, 17, 17, 0.4));
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                border-radius: 28px;
                border: 1px solid rgba(16, 185, 129, 0.1);
                box-shadow: 
                    0 8px 32px rgba(0, 0, 0, 0.3),
                    0 1px 1px rgba(16, 185, 129, 0.1),
                    0 0 0 1px rgba(255, 255, 255, 0.05) inset;
                padding: 2rem;
                overflow: hidden;
                animation: cardFloat 1s cubic-bezier(0.22, 1, 0.36, 1);
                z-index: 1;
            }
            /* Animated gradient shine */
            .shine {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                pointer-events: none;
                border-radius: 28px;
                overflow: hidden;
            }
            .shine::before {
                content: '';
                position: absolute;
                top: -60%; left: -60%;
                width: 220%; height: 220%;
                background: linear-gradient(120deg, transparent 60%, rgba(16,185,129,0.12) 80%, transparent 100%);
                transform: rotate(8deg);
                animation: shineMove 2.2s cubic-bezier(0.22, 1, 0.36, 1) 0.5s 1;
            }
            @keyframes shineMove {
                0% { transform: translateX(-80%) rotate(8deg); opacity: 0; }
                20% { opacity: 1; }
                80% { opacity: 1; }
                100% { transform: translateX(80%) rotate(8deg); opacity: 0; }
            }
            .status-line {
                position: absolute;
                top: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 80%;
                height: 4px;
                background: linear-gradient(90deg, 
                    var(--accent) 0%,
                    var(--accent-dark) 100%
                );
                border-radius: 0 0 4px 4px;
                box-shadow: 0 0 20px var(--accent-glow);
            }
            .success-icon {
                position: relative;
                width: 80px;
                height: 80px;
                margin: 0 auto 1.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2;
            }
            .tick-ripple {
                position: absolute;
                left: 50%;
                top: 50%;
                width: 80px;
                height: 80px;
                background: radial-gradient(circle, var(--accent) 0%, transparent 70%);
                border-radius: 50%;
                transform: translate(-50%, -50%) scale(0.7);
                opacity: 0.5;
                pointer-events: none;
                animation: tickRipple 1.1s 0.5s cubic-bezier(0.22, 1, 0.36, 1) 1;
            }
            @keyframes tickRipple {
                0% { transform: translate(-50%, -50%) scale(0.7); opacity: 0.5; }
                60% { opacity: 0.25; }
                100% { transform: translate(-50%, -50%) scale(1.7); opacity: 0; }
            }
            .success-icon::before {
                content: '';
                position: absolute;
                inset: 0;
                border-radius: 50%;
                padding: 3px;
                background: linear-gradient(135deg, var(--accent), var(--accent-dark));
                -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
                mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
                -webkit-mask-composite: xor;
                mask-composite: exclude;
                animation: rotateGradient 4s linear infinite;
            }
            .success-icon::after {
                content: '';
                position: absolute;
                inset: 8px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--accent), var(--accent-dark));
                box-shadow: 
                    0 0 32px var(--accent-glow),
                    0 0 0 1px rgba(16, 185, 129, 0.2) inset;
            }
            .success-icon svg {
                width: 32px;
                height: 32px;
                position: relative;
                z-index: 1;
                stroke: var(--accent);
                stroke-width: 3.5;
                stroke-linecap: round;
                stroke-linejoin: round;
                animation: checkmarkDraw 1s 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards, iconPulse 0.7s 1.5s cubic-bezier(0.22, 1, 0.36, 1) 1;
            }
            .success-icon .checkmark {
                stroke: #fff;
                stroke-width: 3.5;
                stroke-linecap: round;
                stroke-linejoin: round;
                fill: none;
                stroke-dasharray: 32;
                stroke-dashoffset: 32;
                animation: checkmarkDraw 1s 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            }
            @keyframes iconPulse {
                0% { filter: drop-shadow(0 0 0 var(--accent)); transform: scale(1); }
                40% { filter: drop-shadow(0 0 12px var(--accent)); transform: scale(1.13); }
                60% { filter: drop-shadow(0 0 18px var(--accent)); transform: scale(1.08); }
                100% { filter: drop-shadow(0 0 0 var(--accent)); transform: scale(1); }
            }
            .info-box {
                background: rgba(16, 185, 129, 0.05);
                border: 1px solid rgba(16, 185, 129, 0.1);
                border-radius: 16px;
                padding: 1.25rem;
                margin: 1.5rem 0;
                animation: infoSlideIn 0.7s 1.1s cubic-bezier(0.22, 1, 0.36, 1) backwards;
                position: relative;
                overflow: hidden;
            }
            @keyframes infoSlideIn {
                0% { opacity: 0; transform: translateY(40px) scale(0.98); }
                100% { opacity: 1; transform: translateY(0) scale(1); }
            }
            .info-box::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 4px;
                height: 100%;
                background: linear-gradient(to bottom, var(--accent), transparent);
                border-radius: 4px 0 0 4px;
            }
            .info-box p {
                color: var(--text-secondary);
                font-size: 0.95rem;
                line-height: 1.5;
                margin: 0;
                padding-left: 1rem;
            }
            .info-box strong {
                color: var(--accent);
                font-weight: 500;
            }
            .title {
                font-size: 1.75rem;
                font-weight: 800;
                background: linear-gradient(to right, var(--accent), #fff);
                -webkit-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                text-align: center;
                margin-bottom: 0.5rem;
                letter-spacing: -0.02em;
                animation: fadeUp 0.8s cubic-bezier(0.22, 1, 0.36, 1);
            }
            .subtitle {
                font-size: 1.125rem;
                font-weight: 500;
                color: var(--accent);
                text-align: center;
                margin-bottom: 0.5rem;
                animation: fadeUp 0.8s 0.1s cubic-bezier(0.22, 1, 0.36, 1) backwards;
            }
            .message {
                font-size: 0.95rem;
                color: var(--text-secondary);
                text-align: center;
                line-height: 1.5;
                margin-bottom: 0.75rem;
                animation: fadeUp 0.8s 0.2s cubic-bezier(0.22, 1, 0.36, 1) backwards;
            }
            .premium-button {
                width: 100%;
                background: linear-gradient(135deg, var(--accent), var(--accent-dark));
                color: #fff;
                font-size: 1.125rem;
                font-weight: 600;
                padding: 1rem;
                border: none;
                border-radius: 14px;
                cursor: pointer;
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
                box-shadow: 
                    0 8px 24px var(--accent-glow),
                    0 0 0 1px rgba(255, 255, 255, 0.05) inset;
                animation: fadeUp 0.8s 0.3s cubic-bezier(0.22, 1, 0.36, 1) backwards;
            }
            .premium-button::before {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(90deg, 
                    transparent, 
                    rgba(255, 255, 255, 0.1), 
                    transparent
                );
                transform: translateX(-100%);
                animation: shimmer 3s infinite;
            }
            .premium-button:active {
                transform: translateY(0);
            }
            /* Button ripple effect */
            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                background: rgba(16,185,129,0.25);
                pointer-events: none;
                z-index: 2;
            }
            @keyframes ripple {
                to {
                    transform: scale(2.5);
                    opacity: 0;
                }
            }
            @keyframes cardFloat {
                0% {
                    opacity: 0;
                    transform: translateY(20px) scale(0.95);
                }
                100% {
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
            @keyframes fadeUp {
                0% {
                    opacity: 0;
                    transform: translateY(10px);
                }
                100% {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes fadeScale {
                0% {
                    opacity: 0;
                    transform: scale(0.95);
                }
                100% {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            @keyframes rotateGradient {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes checkmarkDraw {
                from { stroke-dashoffset: 32; }
                to { stroke-dashoffset: 0; }
            }
            @keyframes shimmer {
                0% { transform: translateX(-100%); }
                50% { transform: translateX(100%); }
                100% { transform: translateX(100%); }
            }
            @media (max-width: 480px) {
                .success-card {
                    padding: 1.5rem;
                    margin: 1rem;
                    max-height: calc(100vh - 2rem);
                }
                .success-icon {
                    width: 64px;
                    height: 64px;
                    margin-bottom: 1rem;
                }
                .title {
                    font-size: 1.5rem;
                }
                .subtitle {
                    font-size: 1rem;
                }
                .info-box {
                    padding: 1rem;
                    margin: 1rem 0;
                }
                .premium-button {
                    padding: 0.875rem;
                    font-size: 1rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="particles">
            <div class="particle p1"></div>
            <div class="particle p2"></div>
            <div class="particle p3"></div>
            <div class="particle p4"></div>
        </div>
        <div class="success-card">
            <div class="shine"></div>
            <div class="status-line"></div>
            <div class="success-icon">
                <span class="tick-ripple"></span>
                <svg viewBox="0 0 24 24" fill="none">
                    <path class="checkmark" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h1 class="title">Success</h1>
            <p class="subtitle">Complaint Registered</p>
            <p class="message">Your request has been successfully submitted to our system.</p>

            <div class="info-box">
                <p>
                    <strong>Status Update:</strong> Your complaint has been registered and assigned to our technical team. A qualified technician will reach out to you shortly.
                </p>
                
                <?php 
                $assigned_technician = $_SESSION['assigned_technician'] ?? null;
                if ($assigned_technician): 
                ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(16, 185, 129, 0.2);">
                    <p style="margin-bottom: 0.5rem;">
                        <strong>Assigned Technician:</strong>
                    </p>
                    <div style="background: rgba(16, 185, 129, 0.1); padding: 0.75rem; border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);">
                        <p style="margin: 0; color: var(--accent); font-weight: 500;">
                            <?= htmlspecialchars($assigned_technician['full_name']) ?>
                        </p>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; opacity: 0.8;">
                            Specialization: <?= htmlspecialchars(ucfirst($assigned_technician['specialization'])) ?>
                        </p>
                        <?php if (!empty($assigned_technician['phone'])): ?>
                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; opacity: 0.8;">
                            Contact: <a href="tel:<?= htmlspecialchars($assigned_technician['phone']) ?>" style="color: var(--accent); text-decoration: none;"><?= htmlspecialchars($assigned_technician['phone']) ?></a>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                unset($_SESSION['assigned_technician']);
                endif; 
                ?>
            </div>

            <p class="message" style="font-size: 0.9rem; opacity: 0.8;">
                Thank you for helping us maintain smooth operations.
            </p>

            <button class="premium-button" id="dashboardBtn" onclick="window.location.href='dashboard.php'">
                Return to Dashboard
            </button>
        </div>
        <script>
        // Button ripple effect
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('dashboardBtn');
            btn.addEventListener('click', function(e) {
                var ripple = document.createElement('span');
                ripple.className = 'ripple';
                var rect = btn.getBoundingClientRect();
                ripple.style.left = (e.clientX - rect.left) + 'px';
                ripple.style.top = (e.clientY - rect.top) + 'px';
                ripple.style.width = ripple.style.height = Math.max(rect.width, rect.height) + 'px';
                btn.appendChild(ripple);
                setTimeout(function() { ripple.remove(); }, 600);
            });
        });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Submit Complaint</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gradient-to-b from-gray-50 to-gray-100 min-h-screen font-['Inter']">
    <div class="container mx-auto px-4 py-6 max-w-lg sm:px-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <button type="button" class="text-blue-600 hover:text-blue-800 focus:outline-none p-2" onclick="window.history.back()">
                <span class="material-icons">arrow_back</span>
            </button>
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Submit Complaint</h1>
            <div class="w-8"></div>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-5 py-4">
                <div class="text-white text-lg sm:text-xl font-medium">Complaint Details</div>
                <div class="text-blue-100 text-sm">Please fill in all required fields</div>
            </div>

            <?php if ($errors): ?>
                <div class="px-5 py-3 bg-red-50 border-b border-red-100">
                    <?php foreach ($errors as $e): ?>
                        <div class="text-red-600 text-sm flex items-center">
                            <span class="material-icons text-sm mr-2">error</span>
                            <?= $e ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="p-4 sm:p-6 space-y-6">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <!-- User Info Section -->
                <div class="bg-gray-50 p-4 rounded-lg space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                        <input type="text" name="name" required 
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base" 
                            value="<?= htmlspecialchars($full_name) ?>" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone No.</label>
                        <input type="text" name="phone" required 
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base" 
                            value="<?= htmlspecialchars($phone) ?>" readonly>
                    </div>
                </div>

                <!-- Complaint Details Section -->
                <div class="space-y-4">
                    <?php if ($role === 'outsourced_vendor' && $specialization): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded mb-2 text-blue-800 text-sm">
                            You are submitting as: <strong><?= ucfirst($specialization) ?> Vendor</strong>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Room No.</label>
                        <input type="text" name="room_no" required 
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base" 
                            value="<?= htmlspecialchars($_POST['room_no'] ?? '') ?>" 
                            placeholder="Enter your room number">
                    </div>

                    <?php if ($role === 'student'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Hostel Type</label>
                        <select name="hostel_type" required 
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base appearance-none bg-no-repeat"
                            style="background-image: url('data:image/svg+xml;charset=US-ASCII,<svg width=\"20\" height=\"20\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 7l3-3 3 3m0 6l-3 3-3-3\" fill=\"none\" stroke=\"%23666\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>');
                            background-position: right 1rem center;
                            background-size: 1.5em;">
                            <option value="">Select Hostel Type</option>
                            <option value="boys" <?= (($_POST['hostel_type'] ?? '') == 'boys')?'selected':'' ?>>Boys Hostel</option>
                            <option value="girls" <?= (($_POST['hostel_type'] ?? '') == 'girls')?'selected':'' ?>>Girls Hostel</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" required 
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base appearance-none bg-no-repeat"
                            style="background-image: url('data:image/svg+xml;charset=US-ASCII,<svg width=\"20\" height=\"20\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M7 7l3-3 3 3m0 6l-3 3-3-3\" fill=\"none\" stroke=\"%23666\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>');
                            background-position: right 1rem center;
                            background-size: 1.5em;">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '')==$cat)?'selected':'' ?>>
                                    <?= ucfirst($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" required rows="4"
                            class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-white text-gray-700 text-base resize-none"
                            style="min-height: 120px;"
                            placeholder="Describe your complaint in detail"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" 
                        class="w-full bg-blue-600 text-white font-medium px-4 py-3 rounded-lg text-base
                            transition duration-150 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500
                            active:transform active:scale-[0.98] shadow-md">
                        Submit Complaint
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Improved mobile form styles */
        @media (max-width: 640px) {
            input, select, textarea {
                font-size: 16px !important;
                line-height: 1.5 !important;
                padding: 0.75rem !important;
            }
            
            .container {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            form {
                padding: 1rem !important;
            }
        }

        /* Minimal custom scrollbar */
        textarea::-webkit-scrollbar {
            width: 6px;
        }
        textarea::-webkit-scrollbar-track {
            background: transparent;
        }
        textarea::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 3px;
        }
        textarea::-webkit-scrollbar-thumb:hover {
            background: #ccc;
        }
        
        /* Remove number input spinners */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"] {
            -moz-appearance: textfield;
        }

        /* Touch-friendly tap targets */
        button, 
        input[type="submit"],
        select {
            min-height: 48px;
        }

        /* Prevent double-tap zoom */
        * {
            touch-action: manipulation;
        }
    </style>
</body>
</html> 