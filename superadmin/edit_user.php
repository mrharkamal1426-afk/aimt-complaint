<?php
require_once __DIR__.'/../includes/config.php';

// Only superadmin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../login.php?error=unauthorized');
    exit;
}

// Get user ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: user_management.php?error=invalid_user');
    exit;
}
$user_id = intval($_GET['id']);

// Fetch user details
$stmt = $mysqli->prepare("SELECT id, full_name, username, phone, role, specialization FROM users WHERE id = ? AND role != 'superadmin'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: user_management.php?error=user_not_found');
    exit;
}

$roles = ['student','faculty','nonteaching','technician'];
$specializations = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac'];
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role = $_POST['role'] ?? '';
    $specialization = ($role === 'technician') ? trim($_POST['specialization'] ?? '') : null;

    // Validation
    if (!$full_name || !$username || !$phone || !$role) {
        $error = 'All fields except specialization are required.';
    } elseif (!in_array($role, $roles)) {
        $error = 'Invalid role.';
    } elseif ($role === 'technician' && !$specialization) {
        $error = 'Specialization is required for technicians.';
    } else {
        // Update user
        $stmt = $mysqli->prepare("UPDATE users SET full_name=?, username=?, phone=?, role=?, specialization=? WHERE id=? AND role != 'superadmin'");
        $stmt->bind_param('sssssi', $full_name, $username, $phone, $role, $specialization, $user_id);
        if ($stmt->execute()) {
            $success = true;
            header('Location: user_management.php?success=updated');
            exit;
        } else {
            $error = 'Failed to update user.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-xl mx-auto py-10">
        <h1 class="text-3xl font-bold mb-8 text-center">Edit User</h1>
        <div class="bg-white p-6 rounded shadow">
            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block font-medium mb-1">Full Name</label>
                    <input type="text" name="full_name" class="w-full border rounded px-3 py-2" value="<?= htmlspecialchars($_POST['full_name'] ?? $user['full_name']) ?>" required>
                </div>
                <div>
                    <label class="block font-medium mb-1">Username</label>
                    <input type="text" name="username" class="w-full border rounded px-3 py-2" value="<?= htmlspecialchars($_POST['username'] ?? $user['username']) ?>" required>
                </div>
                <div>
                    <label class="block font-medium mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full border rounded px-3 py-2" value="<?= htmlspecialchars($_POST['phone'] ?? $user['phone']) ?>" required>
                </div>
                <div>
                    <label class="block font-medium mb-1">Role</label>
                    <select name="role" id="role" class="w-full border rounded px-3 py-2" required onchange="showSpec()">
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>" <?= (($user['role']==$r) || (isset($_POST['role']) && $_POST['role']==$r)) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="spec-div" style="display: none;">
                    <label class="block font-medium mb-1">Specialization</label>
                    <select name="specialization" class="w-full border rounded px-3 py-2">
                        <option value="">Select Specialization</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?= $spec ?>" <?= ((($user['specialization'] ?? '')==$spec) || (isset($_POST['specialization']) && $_POST['specialization']==$spec)) ? 'selected' : '' ?>><?= ucfirst($spec) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-between items-center pt-4">
                    <a href="user_management.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300">Cancel</a>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">Update User</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showSpec() {
            var role = document.getElementById('role').value;
            document.getElementById('spec-div').style.display = (role === 'technician') ? 'block' : 'none';
        }
        window.onload = showSpec;
    </script>
</body>
</html> 