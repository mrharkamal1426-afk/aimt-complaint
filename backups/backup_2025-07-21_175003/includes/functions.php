<?php
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function show_error($msg = 'Something went wrong. Please try again.') {
    echo '<div class="error">' . htmlspecialchars($msg) . '</div>';
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input helper
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate random token
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// Check user role
function has_role($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

// Format date
function format_date($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function getStatusBadgeColor($status) {
    switch ($status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'in_progress':
            return 'bg-blue-100 text-blue-800';
        case 'resolved':
            return 'bg-emerald-100 text-emerald-800';
        case 'rejected':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-slate-100 text-slate-800';
    }
}

function get_complaint_by_token($token) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT c.*, u.full_name as user_name, u.role as user_role, t.full_name as tech_name 
                              FROM complaints c 
                              JOIN users u ON u.id = c.user_id 
                              LEFT JOIN users t ON t.id = c.technician_id 
                              WHERE c.token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    return $complaint;
}

function update_complaint_status($token, $status, $technician_id, $remarks = '') {
    global $mysqli;

    if (empty($technician_id)) {
        $technician_id = NULL;
    }
    
    $stmt = $mysqli->prepare("UPDATE complaints SET status = ?, technician_id = ?, tech_note = ?, updated_at = NOW() WHERE token = ?");
    $stmt->bind_param('siss', $status, $technician_id, $remarks, $token);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Security logging function
function log_security_action($action, $target = null, $details = null) {
    global $mysqli;
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $mysqli->prepare("INSERT INTO security_logs (admin_id, action, target, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $admin_id, $action, $target, $details, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}
?> 