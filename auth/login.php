<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

$roles = ['student','faculty','nonteaching','technician','superadmin','outsourced_vendor'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        if (!$username || !$password || !$role) {
            $error = 'All fields are required.';
        } elseif (!in_array($role, $roles)) {
            $error = 'Invalid role.';
        } else {
            // Normal login flow
            $stmt = $mysqli->prepare("SELECT id, full_name, phone, password_hash, hostel_type, specialization FROM users WHERE username = ? AND role = ?");
            $stmt->bind_param('ss', $username, $role);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $full_name, $phone, $hash, $hostel_type, $specialization);
                $stmt->fetch();
                if (password_verify($password, $hash)) {
                    $_SESSION['user_id'] = $id;
                    $_SESSION['role'] = $role;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['phone'] = $phone;
                    if ($role === 'student') {
                        $_SESSION['hostel_type'] = $hostel_type;
                    }
                    if ($role === 'outsourced_vendor') {
                        $_SESSION['specialization'] = $specialization;
                    }
                    if ($role === 'superadmin') redirect('../superadmin/dashboard.php');
                    elseif ($role === 'technician') redirect('../technician/dashboard.php');
                    else redirect('../user/dashboard.php');
                } else {
                    $error = 'Invalid credentials.';
                }
            } else {
                $error = 'Invalid credentials.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AIMT Complaint Portal</title>
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

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

        /* Animated background */
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

        .login-container {
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

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .login-header h1 {
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-header p {
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: var(--bg);
            color: var(--text);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-light);
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--text);
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

        button[type="submit"]::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button[type="submit"]:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: var(--text-light);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .register-link a:hover {
            color: var(--primary-dark);
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
            }

            .login-header img {
                width: 60px;
                height: 60px;
            }

            .login-header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT Logo">
            <h1>Welcome Back</h1>
            <p>Sign in to access the Complaint Portal</p>
        </div>

        <?php if (isset($_GET['registered'])): ?>
            <div class="success-message">
                <span class="material-icons">check_circle</span>
                Registration successful. Please log in.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logout_message'])): ?>
            <div class="success-message">
                <span class="material-icons">check_circle</span>
                <?= htmlspecialchars($_GET['logout_message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <span class="material-icons">error</span>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                    placeholder="Enter your username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required 
                        placeholder="Enter your password">
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <span class="material-icons">visibility</span>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select your role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>"><?= $r === 'outsourced_vendor' ? 'Outsourced Vendor' : ucfirst($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit">Sign In</button>
        </form>

        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
        <div class="register-link" style="margin-top:0.5rem;">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('.material-icons');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="material-icons animate-spin">refresh</span> Signing in...';
        });

        // Auto-fetch role based on username
        document.getElementById('username').addEventListener('blur', function() {
            const username = this.value.trim();
            if (!username) return;

            fetch('get_role.php?username=' + encodeURIComponent(username))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.role) {
                        const roleSelect = document.getElementById('role');
                        for (let i = 0; i < roleSelect.options.length; i++) {
                            if (roleSelect.options[i].value === data.role) {
                                roleSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                });
        });
    </script>
</body>
</html> 