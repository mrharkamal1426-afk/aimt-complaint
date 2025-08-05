<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

$roles = ['student','faculty','nonteaching','technician','outsourced_vendor'];
$categories = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac'];
$vendor_types = ['mess','cafeteria','arboriculture','security','housekeeping'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? '';
        $special_code = trim($_POST['special_code'] ?? '');
        // If outsourced_vendor, get vendor type as specialization
        if ($role === 'outsourced_vendor') {
            $specialization = $_POST['vendor_type'] ?? '';
        } else if ($role === 'technician') {
            $specialization = $_POST['specialization'] ?? '';
        } else {
            $specialization = null;
        }
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $hostel_type = ($role === 'student') ? ($_POST['hostel_type'] ?? '') : null;

        if (!$full_name || !$phone || !$role || !$special_code || !$username || !$password || !$confirm_password) {
            $errors[] = 'All fields are required.';
        }
        if (!in_array($role, $roles)) {
            $errors[] = 'Invalid role.';
        }
        if ($role === 'technician' && !$specialization) {
            $errors[] = 'Specialization required for technician.';
        }
        if ($role === 'outsourced_vendor' && !$specialization) {
            $errors[] = 'Vendor type required for outsourced vendor.';
        }
        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }
        if ($role === 'student' && !$hostel_type) {
            $errors[] = 'Hostel type required for students.';
        }
        // Validate special code
        if ($role === 'technician') {
            $stmt = $mysqli->prepare("SELECT * FROM special_codes WHERE code = ? AND role = ? AND specialization IS NULL");
            $stmt->bind_param('ss', $special_code, $role);
        } else if ($role === 'outsourced_vendor') {
            $stmt = $mysqli->prepare("SELECT * FROM special_codes WHERE code = ? AND role = ?");
            $stmt->bind_param('ss', $special_code, $role);
        } else {
            $stmt = $mysqli->prepare("SELECT * FROM special_codes WHERE code = ? AND role = ?");
            $stmt->bind_param('ss', $special_code, $role);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $errors[] = 'Invalid code.';
        }
        $stmt->close();
        // Check username unique
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Username already exists.';
        }
        $stmt->close();
        // Check phone unique
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE phone = ?");
        $stmt->bind_param('s', $phone);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'Phone number already exists.';
        }
        $stmt->close();
        if (!$errors) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO users (full_name, phone, role, special_code, specialization, username, password_hash, hostel_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssss', $full_name, $phone, $role, $special_code, $specialization, $username, $hash, $hostel_type);
            if ($stmt->execute()) {
                redirect('login.php?registered=1');
            } else {
                $errors[] = 'Something went wrong. Please try again.';
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
    <title>Register - AIMT Complaint Portal</title>
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

        .register-container {
            width: 100%;
            max-width: 800px;
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

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header img {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .register-header h1 {
            color: var(--text);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .register-header p {
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

        /* Grid layout for form fields */
        form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        /* Make certain fields full width */
        .form-group:nth-last-child(3),
        .form-group:nth-last-child(2),
        .form-group:nth-last-child(1) {
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .register-container {
                padding: 1.5rem;
            }

            form {
                grid-template-columns: 1fr;
            }

            .register-header img {
                width: 60px;
                height: 60px;
            }

            .register-header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT Logo">
            <h1>Create Account</h1>
            <p>Join the AIMT Complaint Portal</p>
        </div>

        <?php if ($errors): ?>
            <?php foreach ($errors as $error): ?>
                <div class="error-message">
                    <span class="material-icons">error</span>
                    <?= $error ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required 
                    value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                    placeholder="Enter your full name">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" maxlength="10" name="phone" required 
                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                    placeholder="Enter your phone number">
                <span id="phone-status" style="font-size:0.85em;display:block;margin-top:0.25em;"></span>
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required onchange="showSpec()">
                    <option value="">Select your role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= $r ?>" <?= (($_POST['role'] ?? '')==$r)?'selected':'' ?>>
                            <?= $r === 'outsourced_vendor' ? 'Outsourced Vendor' : ucfirst($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="vendor-type-div" style="display:none;">
                <label for="vendor_type">Vendor Type</label>
                <select id="vendor_type" name="vendor_type">
                    <option value="">Select vendor type</option>
                    <?php foreach ($vendor_types as $vt): ?>
                        <option value="<?= $vt ?>" <?= (($_POST['vendor_type'] ?? '')==$vt)?'selected':'' ?>>
                            <?= ucfirst($vt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="spec-div" style="display:none;">
                <label for="specialization">Specialization</label>
                <select id="specialization" name="specialization">
                    <option value="">Select specialization</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= (($_POST['specialization'] ?? '')==$cat)?'selected':'' ?>>
                            <?= ucfirst($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="special_code">Special Code</label>
                <input type="text" id="special_code" name="special_code" required 
                    value="<?= htmlspecialchars($_POST['special_code'] ?? '') ?>"
                    placeholder="Enter your special code">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="Choose a username">
                <span id="username-status" style="font-size:0.85em;display:block;margin-top:0.25em;"></span>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required 
                        placeholder="Create a password">
                    <span class="password-toggle" onclick="togglePassword('password')">
                        <span class="material-icons">visibility</span>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                        placeholder="Confirm your password">
                    <span class="password-toggle" onclick="togglePassword('confirm_password')">
                        <span class="material-icons">visibility</span>
                    </span>
                </div>
            </div>

            <div class="form-group" id="hostel-div" style="display:none;">
                <label for="hostel_type">Hostel Type</label>
                <select id="hostel_type" name="hostel_type">
                    <option value="">Select hostel</option>
                    <option value="boys" <?= (($_POST['hostel_type'] ?? '')=='boys')?'selected':'' ?>>Boys</option>
                    <option value="girls" <?= (($_POST['hostel_type'] ?? '')=='girls')?'selected':'' ?>>Girls</option>
                </select>
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>

    <script>
        function showSpec() {
            var role = document.getElementById('role').value;
            document.getElementById('spec-div').style.display = (role === 'technician') ? 'block' : 'none';
            document.getElementById('hostel-div').style.display = (role === 'student') ? 'block' : 'none';
            document.getElementById('vendor-type-div').style.display = (role === 'outsourced_vendor') ? 'block' : 'none';
        }

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

        // Real-time username and phone uniqueness check
        function checkFieldUnique(field, value) {
            if (!value) {
                showFieldStatus(field, '');
                return;
            }
            fetch('/complaint_portal/auth/validate_unique.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: encodeURI('field=' + field + '&value=' + value)
            })
            .then(response => response.json())
            .then(data => {
                showFieldStatus(field, data.unique ? '' : (field === 'username' ? 'Username already exists.' : 'Phone number already exists.'));
            });
        }

        function showFieldStatus(field, message) {
            let el = document.getElementById(field + '-status');
            if (!el) return;
            el.textContent = message;
            el.style.color = message ? '#dc2626' : '#059669';
            updateSubmitState();
        }

        function updateSubmitState() {
            const usernameStatus = document.getElementById('username-status').textContent;
            const phoneStatus = document.getElementById('phone-status').textContent;
            const button = document.querySelector('button[type="submit"]');
            button.disabled = !!(usernameStatus || phoneStatus);
        }

        document.getElementById('username').addEventListener('input', function() {
            checkFieldUnique('username', this.value.trim());
        });
        document.getElementById('phone').addEventListener('input', function() {
            checkFieldUnique('phone', this.value.trim());
        });

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.innerHTML = '<span class="material-icons animate-spin">refresh</span> Creating account...';
        });

        // Initialize specialization visibility
        window.onload = function() {
            showSpec();
            // Initial check for prefilled values
            checkFieldUnique('username', document.getElementById('username').value.trim());
            checkFieldUnique('phone', document.getElementById('phone').value.trim());
        };
    </script>
</body>
</html> 