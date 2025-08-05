<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

$error = '';
$success = '';
$show_reset_form = false;
$username = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Step 1: If resetting password
        if (isset($_POST['new_password'], $_POST['confirm_password'], $_POST['username'], $_POST['phone'])) {
            $username = trim($_POST['username']);
            $phone = trim($_POST['phone']);
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            if (!$new_password || !$confirm_password) {
                $error = 'Please enter and confirm your new password.';
                $show_reset_form = true;
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
                $show_reset_form = true;
            } elseif (strlen($new_password) < 6) {
                $error = 'Password must be at least 6 characters long.';
                $show_reset_form = true;
            } else {
                // Verify user again for security
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND phone = ?");
                $stmt->bind_param('ss', $username, $phone);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id);
                    $stmt->fetch();
                    $stmt->close();
                    $hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $update->bind_param('si', $hash, $user_id);
                    if ($update->execute()) {
                        $success = 'Password reset successful. You can now login.';
                    } else {
                        $error = 'Failed to reset password. Please try again.';
                    }
                    $update->close();
                } else {
                    $error = 'Invalid username or mobile number.';
                }
            }
        }
        // Step 2: If verifying user for reset
        elseif (isset($_POST['username'], $_POST['phone'])) {
            $username = trim($_POST['username']);
            $phone = trim($_POST['phone']);
            if (!$username || !$phone) {
                $error = 'Please enter both username and registered mobile number.';
            } else {
                $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ? AND phone = ?");
                $stmt->bind_param('ss', $username, $phone);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $show_reset_form = true;
                } else {
                    $error = 'Invalid username or mobile number.';
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AIMT Complaint Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --primary: #1e3a8a;
            --primary-dark: #1e40af;
            --secondary: #166534;
            --accent: #f59e0b;
            --text: #1f2937;
            --text-light: #6b7280;
            --bg: #f8fafc;
            --bg-card: #ffffff;
            --error: #dc2626;
            --success: #059669;
            --border: #e5e7eb;
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            animation: backgroundShift 15s ease-in-out infinite alternate;
            z-index: 0;
        }
        @keyframes backgroundShift {
            0% { transform: scale(1); }
            100% { transform: scale(1.1); }
        }
        .forgot-container {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border-radius: 1rem;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            position: relative;
            z-index: 1;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .forgot-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .forgot-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        .forgot-header h1 {
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .forgot-header p {
            color: var(--text-light);
            font-size: 0.875rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        .form-group label {
            display: block;
            color: var(--text);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: var(--bg);
            color: var(--text);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }
        .error-message {
            background: #fee2e2;
            color: var(--error);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: shake 0.5s ease-in-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .success-message {
            background: #dcfce7;
            color: var(--success);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        button[type="submit"] {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        button[type="submit"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
        }
        button[type="submit"]:active {
            transform: translateY(0);
        }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-light);
        }
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .login-link a:hover {
            color: var(--primary-dark);
        }
        @media (max-width: 480px) {
            .forgot-container { padding: 1.5rem; }
            .forgot-header img { width: 60px; height: 60px; }
            .forgot-header h1 { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT Logo">
            <h1>Forgot Password</h1>
            <p>Enter your username and registered mobile number to reset your password.</p>
        </div>
        <?php if ($error): ?>
            <div class="error-message">
                <span class="material-icons">error</span>
                <?= $error ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-message">
                <span class="material-icons">check_circle</span>
                <?= $success ?>
            </div>
        <?php endif; ?>
        <?php if ($show_reset_form && !$success): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="username" value="<?= htmlspecialchars($username) ?>">
                <input type="hidden" name="phone" value="<?= htmlspecialchars($phone) ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password" style="padding-right:2.5rem;">
                        <span class="material-icons" id="toggleConfirmPassword" style="position:absolute;top:50%;right:0.75rem;transform:translateY(-50%);cursor:pointer;user-select:none;">visibility_off</span>
                    </div>
                </div>
                <script>
                const toggle = document.getElementById('toggleConfirmPassword');
                const confirmInput = document.getElementById('confirm_password');
                toggle.addEventListener('click', function() {
                    if (confirmInput.type === 'password') {
                        confirmInput.type = 'text';
                        toggle.textContent = 'visibility';
                    } else {
                        confirmInput.type = 'password';
                        toggle.textContent = 'visibility_off';
                    }
                });
                </script>
                <button type="submit">Reset Password</button>
            </form>
        <?php elseif (!$success): ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username" value="<?= htmlspecialchars($username) ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Registered Mobile Number</label>
                    <input type="tel" id="phone" name="phone" required placeholder="Enter your registered mobile number" value="<?= htmlspecialchars($phone) ?>" maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                </div>
                <button type="submit">Continue</button>
            </form>
        <?php endif; ?>
        <div class="login-link">
            Remembered your password? <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html> 