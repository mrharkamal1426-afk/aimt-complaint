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
    // CSRF protection disabled as per updated requirements.
    // Return empty string so existing form inputs remain valid but harmless.
    return '';
}

function validate_csrf_token($token) {
    // CSRF validation disabled; always return true to bypass checks.
    return true;
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

function getWorkloadStatus($workload, $average_workload) {
    if ($average_workload <= 0) {
        return ['status' => 'Low', 'class' => 'bg-green-100 text-green-800'];
    }
    
    $deviation = $workload - $average_workload;
    
    if ($deviation < -0.5) {
        return ['status' => 'Low', 'class' => 'bg-green-100 text-green-800'];
    } elseif ($deviation <= 1) {
        return ['status' => 'Medium', 'class' => 'bg-yellow-100 text-yellow-800'];
    } else {
        return ['status' => 'High', 'class' => 'bg-red-100 text-red-800'];
    }
}

function get_complaint_by_token($token) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT c.*, u.full_name as user_name, u.role as user_role, t.full_name as tech_name, t.specialization as tech_specialization, t.is_online as tech_online
                              FROM complaints c 
                              JOIN users u ON u.id = c.user_id 
                              LEFT JOIN users t ON t.id = c.technician_id 
                              WHERE c.token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    if ($complaint) {
        // Determine assignment type
        $complaint['assignment_type'] = get_complaint_assignment_type($complaint);
        $complaint['assignment_details'] = get_complaint_assignment_details($complaint);
    }
    
    return $complaint;
}

function is_complaint_reassigned($complaint_id) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT id FROM complaint_history WHERE complaint_id = ? AND note LIKE 'Reassigned%' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? true : false;
}

function get_complaint_assignment_type($complaint) {
    // If no technician assigned, mark unassigned
    if (!$complaint['technician_id']) {
        return 'unassigned';
    }
    
    // Determine reassigned
    if (($complaint['assignment_type'] ?? '') === 'auto_assigned' && is_complaint_reassigned($complaint['id'])) {
        return 'reassigned';
    }
    // Return the assignment type from the database
    return $complaint['assignment_type'] ?? 'manual';
}

function get_complaint_assignment_details($complaint) {
    $details = [];
    $assignment_type = $complaint['assignment_type'] ?? 'manual';
    
    switch ($assignment_type) {
        case 'auto_assigned':
            $details['message'] = 'This complaint was automatically assigned by the smart auto-assignment system.';
            $details['badge_class'] = 'bg-green-100 text-green-800';
            $details['badge_text'] = 'Auto Assigned';
            $details['icon'] = 'zap';
            break;
            
        case 'admin_assigned':
            $details['message'] = 'This complaint was specifically assigned by an administrator.';
            $details['badge_class'] = 'bg-purple-100 text-purple-800';
            $details['badge_text'] = 'Admin Assigned';
            $details['icon'] = 'shield';
            break;
        case 'reassigned':
            $details['message'] = 'This complaint was automatically reassigned by the system due to technician unavailability.';
            $details['badge_class'] = 'bg-orange-100 text-orange-800';
            $details['badge_text'] = 'Reassigned';
            $details['icon'] = 'repeat';
            break;
        case 'unassigned':
            $details['message'] = 'No technician is currently assigned to this complaint.';
            $details['badge_class'] = 'bg-slate-100 text-slate-800';
            $details['badge_text'] = 'Unassigned';
            $details['icon'] = 'user-x';
            break;
        case 'manual':
            $details['message'] = 'This complaint was specifically assigned by an administrator.';
            $details['badge_class'] = 'bg-purple-100 text-purple-800';
            $details['badge_text'] = 'Admin Assigned';
            $details['icon'] = 'shield';
            break;
        case 'reassigned':
            $details['message'] = 'This complaint was automatically reassigned by the system because the previous technician became unavailable.';
            $details['badge_class'] = 'bg-orange-100 text-orange-800';
            $details['badge_text'] = 'Reassigned';
            $details['icon'] = 'repeat';
            break;
            $details['message'] = 'This complaint was specifically assigned by an administrator.';
            $details['badge_class'] = 'bg-purple-100 text-purple-800';
            $details['badge_text'] = 'Admin Assigned';
            $details['icon'] = 'shield';
            break;
            
        case 'manual':
        default:
            $details['message'] = 'This complaint was manually assigned.';
            $details['badge_class'] = 'bg-blue-100 text-blue-800';
            $details['badge_text'] = 'Manual Assigned';
            $details['icon'] = 'user';
            break;
    }
    
    return $details;
}

function update_complaint_status($token, $status, $technician_id, $remarks = '') {
    global $mysqli;

    // Determine assignment_type changes
    $assignment_type_sql = '';
    if ($technician_id === '' || $technician_id === null) {
        // admin chose to keep auto assignment, revert to auto_assigned
        $technician_id = NULL;
        $assignment_type_sql = ", assignment_type = 'auto_assigned'";
    } else {
        // specific technician selected -> admin assignment
        $assignment_type_sql = ", assignment_type = 'admin_assigned'";
    }
    
    $stmt = $mysqli->prepare("UPDATE complaints SET status = ?, technician_id = ?, tech_note = ?, updated_at = NOW()$assignment_type_sql WHERE token = ?");
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

function get_available_technicians_for_category($category) {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT id, full_name, specialization, phone FROM users WHERE role = 'technician' AND specialization = ? AND is_online = 1 ORDER BY full_name");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    $stmt->close();
    return $technicians;
}

function auto_assign_complaint($complaint_id) {
    global $mysqli;
    
    // Get complaint details
    $stmt = $mysqli->prepare("SELECT category, user_id FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return false;
    }
    
    $category = $complaint['category'];
    $submitter_id = $complaint['user_id'];
    
    // Get all online technicians with matching specialization, EXCLUDING the submitter if they are a technician
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as current_workload
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        AND u.is_online = 1
        AND u.id != ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_workload ASC
    ");
    $stmt->bind_param('si', $category, $submitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    $stmt->close();
    
    if (empty($technicians)) {
        // No technicians available for this category
        return false;
    }
    
    // If only one technician, assign to them
    if (count($technicians) === 1) {
        $selected_technician = $technicians[0];
    } else {
        // Multiple technicians - assign to the one with least workload
        $selected_technician = $technicians[0]; // Already sorted by workload ASC
    }
    
    // Double-check that the selected technician still exists before assignment
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND role = 'technician' AND specialization = ?");
    $stmt->bind_param('is', $selected_technician['id'], $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician_exists = $result->fetch_assoc();
    $stmt->close();
    
    if (!$technician_exists) {
        // Technician was deleted between selection and assignment
        // Try to find another technician
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('si', $category, $selected_technician['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $alternative_technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($alternative_technician) {
            $selected_technician = $alternative_technician;
        } else {
            // No other technicians available, leave unassigned
            return false;
        }
    }
    
    // Assign the complaint to the selected technician
    $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $selected_technician['id'], $complaint_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Log the auto-assignment
        log_auto_assignment($complaint_id, $selected_technician['id'], $category, 'auto_assigned');
        return $selected_technician;
    }
    
    return false;
}

function log_auto_assignment($complaint_id, $technician_id, $category, $assignment_type) {
    global $mysqli;

    // Fetch current assignment_type
    $stmt = $mysqli->prepare("SELECT assignment_type FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    // Only update if new type is different
    if ($current && $current['assignment_type'] !== $assignment_type) {
        $stmt = $mysqli->prepare("
            UPDATE complaints 
            SET assignment_type = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param('si', $assignment_type, $complaint_id);
        $stmt->execute();
        $stmt->close();
    }

    // Log to complaint_history as before
    $stmt = $mysqli->prepare("
        INSERT INTO complaint_history (complaint_id, status, note, created_at) 
        VALUES (?, 'pending', ?, NOW())
    ");
    $note = ucfirst(str_replace('_', ' ', $assignment_type)) . " to technician ID $technician_id for $category category";
    $stmt->bind_param('is', $complaint_id, $note);
    $stmt->execute();
    $stmt->close();
}

function get_technician_workload($technician_id) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_complaints,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_complaints
        FROM complaints 
        WHERE technician_id = ? 
        AND status IN ('pending', 'in_progress')
    ");
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $workload = $result->fetch_assoc();
    $stmt->close();
    
    return $workload;
}

function get_technicians_by_specialization_with_workload($specialization) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            u.phone,
            COUNT(c.id) as current_workload,
            SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        GROUP BY u.id, u.full_name, u.specialization, u.phone
        ORDER BY current_workload ASC, u.full_name ASC
    ");
    $stmt->bind_param('s', $specialization);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    $stmt->close();
    
    return $technicians;
}

/**
 * Enhanced auto-assignment for all unassigned complaints with priority and fallback
 */
function auto_assign_all_unassigned_complaints_enhanced() {
    global $mysqli;
    
    // Get all unassigned complaints with priority calculation
    $stmt = $mysqli->prepare("
        SELECT id, token, category, room_no, created_at
        FROM complaints 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('pending', 'in_progress')
        ORDER BY created_at ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $unassigned_complaints = [];
    while ($row = $result->fetch_assoc()) {
        $row['priority'] = calculate_complaint_priority($row['id']);
        $unassigned_complaints[] = $row;
    }
    $stmt->close();
    
    // Sort by priority (highest first)
    usort($unassigned_complaints, function($a, $b) {
        return $b['priority'] <=> $a['priority'];
    });
    
    $assigned_count = 0;
    $failed_count = 0;
    $failed_complaints = [];
    $category_stats = [];
    
    foreach ($unassigned_complaints as $complaint) {
        // Try priority-based assignment first
        $result = auto_assign_complaint_with_priority($complaint['id']);
        
        if (!$result) {
            // Try fallback assignment
            $result = auto_assign_complaint_with_fallback($complaint['id']);
        }
        
        if ($result) {
            $assigned_count++;
            $category = $complaint['category'];
            if (!isset($category_stats[$category])) {
                $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
            }
            $category_stats[$category]['assigned']++;
        } else {
            $failed_count++;
            $category = $complaint['category'];
            if (!isset($category_stats[$category])) {
                $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
            }
            $category_stats[$category]['failed']++;
            
            // Add failed complaint details
            $failed_complaints[] = [
                'id' => $complaint['id'],
                'token' => $complaint['token'],
                'category' => $complaint['category'],
                'room_no' => $complaint['room_no'],
                'created_at' => $complaint['created_at'],
                'priority' => $complaint['priority'],
                'reason' => 'No technicians available (tried primary and fallback assignments)'
            ];
        }
    }
    
    // Send alerts for critical failures
    if ($failed_count > 0) {
        $alert = [
            'type' => 'warning',
            'message' => "Auto-assignment completed with $failed_count failures out of " . count($unassigned_complaints) . " total",
            'failed_count' => $failed_count,
            'total_count' => count($unassigned_complaints),
            'severity' => 'medium'
        ];
        send_system_alert($alert);
    }
    
    return [
        'total_unassigned' => count($unassigned_complaints),
        'assigned_count' => $assigned_count,
        'failed_count' => $failed_count,
        'failed_complaints' => $failed_complaints,
        'category_stats' => $category_stats
    ];
}

function auto_assign_all_unassigned_hostel_issues() {
    global $mysqli;
    
    // Get all unassigned hostel issues with more details
    $stmt = $mysqli->prepare("
        SELECT id, issue_type, hostel_type, created_at
        FROM hostel_issues 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('not_assigned', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $unassigned_issues = [];
    while ($row = $result->fetch_assoc()) {
        $unassigned_issues[] = $row;
    }
    $stmt->close();
    
    $assigned_count = 0;
    $failed_count = 0;
    $failed_hostel_issues = [];
    $issue_type_stats = [];
    
    foreach ($unassigned_issues as $issue) {
        $result = auto_assign_hostel_issue($issue['id']);
        if ($result) {
            $assigned_count++;
            $issue_type = $issue['issue_type'];
            if (!isset($issue_type_stats[$issue_type])) {
                $issue_type_stats[$issue_type] = ['assigned' => 0, 'failed' => 0];
            }
            $issue_type_stats[$issue_type]['assigned']++;
        } else {
            $failed_count++;
            $issue_type = $issue['issue_type'];
            if (!isset($issue_type_stats[$issue_type])) {
                $issue_type_stats[$issue_type] = ['assigned' => 0, 'failed' => 0];
            }
            $issue_type_stats[$issue_type]['failed']++;
            
            // Add failed hostel issue details
            $failed_hostel_issues[] = [
                'id' => $issue['id'],
                'issue_type' => $issue['issue_type'],
                'hostel_type' => $issue['hostel_type'],
                'created_at' => $issue['created_at'],
                'reason' => 'No online technicians available for ' . ucfirst($issue['issue_type']) . ' issue type'
            ];
        }
    }
    
    return [
        'total_unassigned' => count($unassigned_issues),
        'assigned_count' => $assigned_count,
        'failed_count' => $failed_count,
        'failed_hostel_issues' => $failed_hostel_issues,
        'issue_type_stats' => $issue_type_stats
    ];
}

function auto_assign_hostel_issue($issue_id) {
    global $mysqli;
    
    // Get hostel issue details
    $stmt = $mysqli->prepare("SELECT issue_type FROM hostel_issues WHERE id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();
    $stmt->close();
    
    if (!$issue) {
        return false;
    }
    
    $issue_type = $issue['issue_type'];
    
    // Get all online technicians with matching specialization
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(hi.id) as current_hostel_workload
        FROM users u
        LEFT JOIN hostel_issues hi ON u.id = hi.technician_id 
            AND hi.status IN ('not_assigned', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        AND u.is_online = 1
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_hostel_workload ASC
    ");
    $stmt->bind_param('s', $issue_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
    $stmt->close();
    
    if (empty($technicians)) {
        // No technicians available for this issue type
        return false;
    }
    
    // If only one technician, assign to them
    if (count($technicians) === 1) {
        $selected_technician = $technicians[0];
    } else {
        // Multiple technicians - assign to the one with least workload
        $selected_technician = $technicians[0]; // Already sorted by workload ASC
    }
    
    // Double-check that the selected technician still exists before assignment
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND role = 'technician' AND specialization = ?");
    $stmt->bind_param('is', $selected_technician['id'], $issue_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician_exists = $result->fetch_assoc();
    $stmt->close();
    
    if (!$technician_exists) {
        // Technician was deleted between selection and assignment
        // Try to find another online technician
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(hi.id) as current_hostel_workload
            FROM users u
            LEFT JOIN hostel_issues hi ON u.id = hi.technician_id 
                AND hi.status IN ('not_assigned', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.id != ?
            AND u.is_online = 1
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_hostel_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('si', $issue_type, $selected_technician['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $alternative_technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($alternative_technician) {
            $selected_technician = $alternative_technician;
        } else {
            // No other technicians available, leave unassigned
            return false;
        }
    }
    
    // Assign the hostel issue to the selected technician
    $stmt = $mysqli->prepare("UPDATE hostel_issues SET technician_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $selected_technician['id'], $issue_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Log the auto-assignment
        log_hostel_issue_auto_assignment($issue_id, $selected_technician['id'], $issue_type);
        return $selected_technician;
    }
    
    return false;
}

function log_hostel_issue_auto_assignment($issue_id, $technician_id, $issue_type) {
    global $mysqli;
    
    // Log to security_logs instead since hostel_issue_history table doesn't exist
    $stmt = $mysqli->prepare("
        INSERT INTO security_logs (admin_id, action, target, details, created_at) 
        VALUES (?, 'hostel_issue_auto_assigned', ?, ?, NOW())
    ");
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $target = "issue_id:$issue_id";
    $note = "Auto-assigned to technician ID $technician_id for $issue_type issue";
    
    $stmt->bind_param('iss', $admin_id, $target, $note);
    $stmt->execute();
    $stmt->close();
}

function get_assignment_statistics() {
    global $mysqli;
    
    // Get complaint statistics
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN technician_id IS NULL OR technician_id = 0 THEN 1 ELSE 0 END) as unassigned_count,
            SUM(CASE WHEN technician_id IS NOT NULL AND technician_id != 0 THEN 1 ELSE 0 END) as assigned_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM complaints
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint_stats = $result->fetch_assoc();
    $stmt->close();
    
    // Get hostel issue statistics
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total_hostel_issues,
            SUM(CASE WHEN technician_id IS NULL OR technician_id = 0 THEN 1 ELSE 0 END) as unassigned_hostel_count,
            SUM(CASE WHEN technician_id IS NOT NULL AND technician_id != 0 THEN 1 ELSE 0 END) as assigned_hostel_count,
            SUM(CASE WHEN status = 'not_assigned' THEN 1 ELSE 0 END) as not_assigned_count,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as hostel_in_progress_count,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as hostel_resolved_count
        FROM hostel_issues
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $hostel_stats = $result->fetch_assoc();
    $stmt->close();
    
    // Get technician workload distribution for complaints with online status
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            u.is_online,
            COUNT(c.id) as assigned_complaints,
            SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN c.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
            COUNT(hi.id) as assigned_hostel_issues,
            SUM(CASE WHEN hi.status = 'not_assigned' THEN 1 ELSE 0 END) as hostel_not_assigned_count,
            SUM(CASE WHEN hi.status = 'in_progress' THEN 1 ELSE 0 END) as hostel_in_progress_count
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
        LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
        WHERE u.role = 'technician'
        GROUP BY u.id, u.full_name, u.specialization, u.is_online
        ORDER BY (assigned_complaints + assigned_hostel_issues) DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $technician_stats = [];
    $total_workload = 0;
    $technician_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $total_workload += $row['assigned_complaints'] + $row['assigned_hostel_issues'];
        $technician_count++;
        $technician_stats[] = $row;
    }
    $stmt->close();
    
    // Calculate average workload
    $average_workload = $technician_count > 0 ? $total_workload / $technician_count : 0;
    
    // Combine statistics
    $stats = array_merge($complaint_stats, $hostel_stats);
    $stats['technician_distribution'] = $technician_stats;
    $stats['average_workload'] = round($average_workload, 1);
    $stats['total_technicians'] = $technician_count;
    
    return $stats;
}

function handle_technician_deletion($technician_id) {
    global $mysqli;
    
    // Validate input
    if (!is_numeric($technician_id) || $technician_id <= 0) {
        return [
            'success' => false,
            'error' => 'Invalid technician ID provided'
        ];
    }
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Step 1: Get technician details before deletion
        $stmt = $mysqli->prepare("SELECT full_name, specialization FROM users WHERE id = ? AND role = 'technician'");
        if (!$stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        
        $stmt->bind_param('i', $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if (!$technician) {
            throw new Exception("Technician not found or is not a valid technician");
        }
        
        $specialization = $technician['specialization'];
        $technician_name = $technician['full_name'];
        
        // Step 2: Get all complaints assigned to this technician
        $stmt = $mysqli->prepare("
            SELECT id, category, status, token 
            FROM complaints 
            WHERE technician_id = ? 
            AND status IN ('pending', 'in_progress')
        ");
        if (!$stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        
        $stmt->bind_param('i', $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $assigned_complaints = [];
        while ($row = $result->fetch_assoc()) {
            $assigned_complaints[] = $row;
        }
        $stmt->close();
        
        // Step 3: Get all hostel issues assigned to this technician
        $stmt = $mysqli->prepare("
            SELECT id, issue_type, status, hostel_type 
            FROM hostel_issues 
            WHERE technician_id = ? 
            AND status IN ('not_assigned', 'in_progress')
        ");
        if (!$stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        
        $stmt->bind_param('i', $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $assigned_hostel_issues = [];
        while ($row = $result->fetch_assoc()) {
            $assigned_hostel_issues[] = $row;
        }
        $stmt->close();
        
        $reassigned_complaints = 0;
        $failed_complaints = 0;
        $reassigned_hostel_issues = 0;
        $failed_hostel_issues = 0;
        
        // Step 4: Reassign complaints
        foreach ($assigned_complaints as $complaint) {
            $reassignment_result = reassign_complaint_after_technician_deletion($complaint['id'], $complaint['category']);
            if ($reassignment_result) {
                $reassigned_complaints++;
            } else {
                $failed_complaints++;
            }
        }
        
        // Step 5: Reassign hostel issues
        foreach ($assigned_hostel_issues as $issue) {
            $reassignment_result = reassign_hostel_issue_after_technician_deletion($issue['id'], $issue['issue_type']);
            if ($reassignment_result) {
                $reassigned_hostel_issues++;
            } else {
                $failed_hostel_issues++;
            }
        }
        
        // Step 6: Delete the technician user
        $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ? AND role = 'technician'");
        if (!$stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        
        $stmt->bind_param('i', $technician_id);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();
        
        if (!$deleted) {
            throw new Exception("Failed to delete technician user");
        }
        
        // Step 7: Log the technician deletion and reassignment
        log_technician_deletion($technician_id, $technician_name, $specialization, $reassigned_complaints, $failed_complaints, $reassigned_hostel_issues, $failed_hostel_issues);
        
        // Step 8: Commit transaction
        $mysqli->commit();
        
        return [
            'success' => true,
            'technician_name' => $technician_name,
            'specialization' => $specialization,
            'reassigned_complaints' => $reassigned_complaints,
            'failed_complaints' => $failed_complaints,
            'reassigned_hostel_issues' => $reassigned_hostel_issues,
            'failed_hostel_issues' => $failed_hostel_issues,
            'total_complaints' => count($assigned_complaints),
            'total_hostel_issues' => count($assigned_hostel_issues)
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $mysqli->rollback();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function reassign_complaint_after_technician_deletion($complaint_id, $category) {
    global $mysqli;
    
    // Find another technician with the same specialization
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as current_workload
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_workload ASC
        LIMIT 1
    ");
    $stmt->bind_param('s', $category);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_technician = $result->fetch_assoc();
    $stmt->close();
    
    if (!$new_technician) {
        // No other technician available for this category
        // Set technician_id to NULL to make it available for auto-assignment
        $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = NULL WHERE id = ?");
        $stmt->bind_param('i', $complaint_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_complaint_reassignment($complaint_id, null, $category, 'no_technician_available');
        }
        
        return $success;
    }
    
    // Assign to the new technician
    $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $new_technician['id'], $complaint_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        log_complaint_reassignment($complaint_id, $new_technician['id'], $category, 'technician_reassigned');
    }
    
    return $success;
}

function reassign_hostel_issue_after_technician_deletion($issue_id, $issue_type) {
    global $mysqli;
    
    // Find another technician with the same specialization (issue_type maps to specialization)
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(hi.id) as current_hostel_workload
        FROM users u
        LEFT JOIN hostel_issues hi ON u.id = hi.technician_id 
            AND hi.status IN ('not_assigned', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_hostel_workload ASC
        LIMIT 1
    ");
    $stmt->bind_param('s', $issue_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_technician = $result->fetch_assoc();
    $stmt->close();
    
    if (!$new_technician) {
        // No other technician available for this issue type
        // Set technician_id to NULL to make it available for auto-assignment
        $stmt = $mysqli->prepare("UPDATE hostel_issues SET technician_id = NULL WHERE id = ?");
        $stmt->bind_param('i', $issue_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if ($success) {
            log_hostel_issue_reassignment($issue_id, null, $issue_type, 'no_technician_available');
        }
        
        return $success;
    }
    
    // Assign to the new technician
    $stmt = $mysqli->prepare("UPDATE hostel_issues SET technician_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $new_technician['id'], $issue_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        log_hostel_issue_reassignment($issue_id, $new_technician['id'], $issue_type, 'technician_reassigned');
    }
    
    return $success;
}

function log_technician_deletion($technician_id, $technician_name, $specialization, $reassigned_complaints, $failed_complaints, $reassigned_hostel_issues, $failed_hostel_issues) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        INSERT INTO security_logs (admin_id, action, target, details, ip_address, user_agent) 
        VALUES (?, 'technician_deleted', ?, ?, ?, ?)
    ");
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $target = "technician_id:$technician_id";
    $details = "Name: $technician_name, Specialization: $specialization, Reassigned Complaints: $reassigned_complaints, Failed Complaints: $failed_complaints, Reassigned Hostel Issues: $reassigned_hostel_issues, Failed Hostel Issues: $failed_hostel_issues";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt->bind_param('issss', $admin_id, $target, $details, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

function log_complaint_reassignment($complaint_id, $new_technician_id, $category, $reason) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        INSERT INTO complaint_history (complaint_id, status, note, created_at) 
        VALUES (?, 'pending', ?, NOW())
    ");
    
    $note = $new_technician_id 
        ? "Reassigned to technician ID $new_technician_id for $category category (reason: $reason)"
        : "Made available for auto-assignment (reason: $reason)";
    
    $stmt->bind_param('is', $complaint_id, $note);
    $stmt->execute();
    $stmt->close();
}

function log_hostel_issue_reassignment($issue_id, $new_technician_id, $issue_type, $reason) {
    global $mysqli;
    
    // Log to security_logs instead since hostel_issue_history table doesn't exist
    $stmt = $mysqli->prepare("
        INSERT INTO security_logs (admin_id, action, target, details, created_at) 
        VALUES (?, 'hostel_issue_reassigned', ?, ?, NOW())
    ");
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $target = "issue_id:$issue_id";
    $note = $new_technician_id 
        ? "Reassigned to technician ID $new_technician_id for $issue_type issue (reason: $reason)"
        : "Made available for auto-assignment (reason: $reason)";
    
    $stmt->bind_param('iss', $admin_id, $target, $note);
    $stmt->execute();
    $stmt->close();
}

function validate_technician_assignments() {
    global $mysqli;
    
    // Find complaints assigned to non-existent technicians
    $stmt = $mysqli->prepare("
        SELECT c.id, c.category, c.technician_id
        FROM complaints c
        LEFT JOIN users u ON c.technician_id = u.id
        WHERE c.technician_id IS NOT NULL 
        AND c.technician_id != 0
        AND u.id IS NULL
        AND c.status IN ('pending', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $orphaned_complaints = [];
    while ($row = $result->fetch_assoc()) {
        $orphaned_complaints[] = $row;
    }
    $stmt->close();
    
    $fixed_count = 0;
    
    foreach ($orphaned_complaints as $complaint) {
        $result = reassign_complaint_after_technician_deletion($complaint['id'], $complaint['category']);
        if ($result) {
            $fixed_count++;
        }
    }
    
    return [
        'orphaned_complaints' => count($orphaned_complaints),
        'fixed_count' => $fixed_count
    ];
}

function validate_hostel_issue_assignments() {
    global $mysqli;
    
    // Find hostel issues assigned to non-existent technicians
    $stmt = $mysqli->prepare("
        SELECT hi.id, hi.issue_type, hi.technician_id
        FROM hostel_issues hi
        LEFT JOIN users u ON hi.technician_id = u.id
        WHERE hi.technician_id IS NOT NULL 
        AND hi.technician_id != 0
        AND u.id IS NULL
        AND hi.status IN ('not_assigned', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $orphaned_hostel_issues = [];
    while ($row = $result->fetch_assoc()) {
        $orphaned_hostel_issues[] = $row;
    }
    $stmt->close();
    
    $fixed_count = 0;
    
    foreach ($orphaned_hostel_issues as $issue) {
        $result = reassign_hostel_issue_after_technician_deletion($issue['id'], $issue['issue_type']);
        if ($result) {
            $fixed_count++;
        }
    }
    
    return [
        'orphaned_hostel_issues' => count($orphaned_hostel_issues),
        'fixed_count' => $fixed_count
    ];
}

function get_orphaned_complaints() {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT c.id, c.token, c.category, c.status, c.created_at, c.technician_id
        FROM complaints c
        LEFT JOIN users u ON c.technician_id = u.id
        WHERE c.technician_id IS NOT NULL 
        AND c.technician_id != 0
        AND u.id IS NULL
        AND c.status IN ('pending', 'in_progress')
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $orphaned_complaints = [];
    while ($row = $result->fetch_assoc()) {
        $orphaned_complaints[] = $row;
    }
    $stmt->close();
    
    return $orphaned_complaints;
}

/**
 * Create a notification for a technician about new assignments
 */
function create_technician_notification($technician_id, $message, $type = 'assignment') {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        INSERT INTO notifications (user_id, message, type, created_at) 
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->bind_param('iss', $technician_id, $message, $type);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

/**
 * Auto-assign unassigned complaints to a specific technician when they come online
 */
function auto_assign_complaints_for_technician($technician_id, $specialization) {
    global $mysqli;
    
    $assigned_count = 0;
    $failed_count = 0;
    
    // Get unassigned complaints that match the technician's specialization
    $stmt = $mysqli->prepare("
        SELECT id, token, category, room_no, created_at
        FROM complaints
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND category = ?
        AND status IN ('pending', 'in_progress')
        ORDER BY created_at ASC
    ");
    
    $stmt->bind_param('s', $specialization);
    $stmt->execute();
    $result = $stmt->get_result();
    $unassigned_complaints = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($unassigned_complaints as $complaint) {
        // Assign the complaint to this technician
        $assign_stmt = $mysqli->prepare("
            UPDATE complaints 
            SET technician_id = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $assign_stmt->bind_param('ii', $technician_id, $complaint['id']);
        
        if ($assign_stmt->execute()) {
            $assigned_count++;
        } else {
            $failed_count++;
        }
        $assign_stmt->close();
    }
    
    // Create notification if assignments were made
    if ($assigned_count > 0) {
        $notification_message = "You have been automatically assigned {$assigned_count} new complaint(s) in your specialization ({$specialization}).";
        create_technician_notification($technician_id, $notification_message, 'auto_assignment');
    }
    
    return [
        'assigned_count' => $assigned_count,
        'failed_count' => $failed_count,
        'total_unassigned' => count($unassigned_complaints)
    ];
}

/**
 * Auto-assign unassigned hostel issues to a specific technician when they come online
 */
function auto_assign_hostel_issues_for_technician($technician_id, $specialization) {
    global $mysqli;
    
    $assigned_count = 0;
    $failed_count = 0;
    
    // Define specialization mapping for hostel issues
    $specialization_mapping = [
        'wifi' => 'wifi',
        'water' => 'plumber',
        'mess' => 'mess',
        'electricity' => 'electrician',
        'cleanliness' => 'housekeeping'
    ];
    
    // Find hostel issue types that match this technician's specialization
    $matching_issue_types = [];
    foreach ($specialization_mapping as $issue_type => $tech_specialization) {
        if ($tech_specialization === $specialization) {
            $matching_issue_types[] = $issue_type;
        }
    }
    
    if (empty($matching_issue_types)) {
        return [
            'assigned_count' => 0,
            'failed_count' => 0,
            'total_unassigned' => 0
        ];
    }
    
    // Create placeholders for the IN clause
    $placeholders = str_repeat('?,', count($matching_issue_types) - 1) . '?';
    
    // Get unassigned hostel issues that match the technician's specialization
    $stmt = $mysqli->prepare("
        SELECT id, issue_type, hostel_type, created_at
        FROM hostel_issues
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND issue_type IN ($placeholders)
        AND status IN ('not_assigned', 'in_progress')
        ORDER BY created_at ASC
    ");
    
    $stmt->bind_param(str_repeat('s', count($matching_issue_types)), ...$matching_issue_types);
    $stmt->execute();
    $result = $stmt->get_result();
    $unassigned_issues = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($unassigned_issues as $issue) {
        // Assign the hostel issue to this technician
        $assign_stmt = $mysqli->prepare("
            UPDATE hostel_issues 
            SET technician_id = ?, status = 'in_progress', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $assign_stmt->bind_param('ii', $technician_id, $issue['id']);
        
        if ($assign_stmt->execute()) {
            $assigned_count++;
        } else {
            $failed_count++;
        }
        $assign_stmt->close();
    }
    
    // Create notification if assignments were made
    if ($assigned_count > 0) {
        $notification_message = "You have been automatically assigned {$assigned_count} new hostel issue(s) in your specialization ({$specialization}).";
        create_technician_notification($technician_id, $notification_message, 'auto_assignment');
    }
    
    return [
        'assigned_count' => $assigned_count,
        'failed_count' => $failed_count,
        'total_unassigned' => count($unassigned_issues)
    ];
}

function check_technician_offline_and_reassign($technician_id) {
    global $mysqli;
    
    // Check if technician is offline
    $stmt = $mysqli->prepare("SELECT is_online, specialization FROM users WHERE id = ? AND role = 'technician'");
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician = $result->fetch_assoc();
    $stmt->close();
    
    if (!$technician || $technician['is_online'] == 1) {
        return false; // Technician is online or doesn't exist
    }
    
    // Get all pending and in_progress complaints assigned to this offline technician
    $stmt = $mysqli->prepare("
        SELECT id, category, token 
        FROM complaints 
        WHERE technician_id = ? AND status IN ('pending', 'in_progress')
    ");
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaints = [];
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
    $stmt->close();
    
    $reassigned_count = 0;
    $failed_count = 0;
    
    foreach ($complaints as $complaint) {
        // Check if there are other technicians for this category
        $stmt = $mysqli->prepare("
            SELECT COUNT(*) as total_techs
            FROM users 
            WHERE role = 'technician' 
            AND specialization = ?
        ");
        $stmt->bind_param('s', $complaint['category']);
        $stmt->execute();
        $result = $stmt->get_result();
        $tech_count = $result->fetch_assoc();
        $stmt->close();
        
        // If there's only one technician total, don't reassign
        if ($tech_count['total_techs'] <= 1) {
            continue; // Keep the assignment with the offline technician
        }
        
        // Try to find another online technician with same specialization
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('si', $complaint['category'], $technician_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $new_technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($new_technician) {
            // Reassign to new technician
            $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('ii', $new_technician['id'], $complaint['id']);
            if ($stmt->execute()) {
                $reassigned_count++;
                // Log the reassignment
                log_complaint_reassignment($complaint['id'], $new_technician['id'], $complaint['category'], 'Technician went offline');
            }
            $stmt->close();
        } else {
            // No other online technician available, but keep assignment if it's the only technician
            if ($tech_count['total_techs'] > 1) {
                $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = NULL, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $complaint['id']);
                if ($stmt->execute()) {
                    $reassigned_count++;
                    // Log the unassignment
                    log_complaint_reassignment($complaint['id'], null, $complaint['category'], 'No online technician available');
                }
                $stmt->close();
            }
        }
    }
    
    return [
        'reassigned' => $reassigned_count,
        'failed' => $failed_count,
        'total' => count($complaints)
    ];
}

function get_technician_status_for_complaint($complaint_id) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT 
            c.technician_id,
            c.category,
            t.full_name as tech_name,
            t.phone as tech_phone,
            t.is_online,
            t.specialization
        FROM complaints c
        LEFT JOIN users t ON t.id = c.technician_id
        WHERE c.id = ?
    ");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    
    if (!$data) {
        return null;
    }
    
    // If no technician assigned, find available online technician
    if (!$data['technician_id']) {
        $stmt = $mysqli->prepare("
            SELECT 
                id,
                full_name,
                phone,
                is_online,
                specialization
            FROM users 
            WHERE role = 'technician' 
            AND specialization = ? 
            AND is_online = 1
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->bind_param('s', $data['category']);
        $stmt->execute();
        $result = $stmt->get_result();
        $available_tech = $result->fetch_assoc();
        $stmt->close();
        
        if ($available_tech) {
            return [
                'assigned' => false,
                'available' => true,
                'tech_name' => $available_tech['full_name'],
                'tech_phone' => $available_tech['phone'],
                'is_online' => $available_tech['is_online'],
                'specialization' => $available_tech['specialization'],
                'status' => 'available'
            ];
        } else {
            return [
                'assigned' => false,
                'available' => false,
                'tech_name' => null,
                'tech_phone' => null,
                'is_online' => 0,
                'specialization' => $data['category'],
                'status' => 'no_technician'
            ];
        }
    }
    
    // Technician is assigned
    return [
        'assigned' => true,
        'available' => $data['is_online'] == 1,
        'tech_name' => $data['tech_name'],
        'tech_phone' => $data['tech_phone'],
        'is_online' => $data['is_online'],
        'specialization' => $data['specialization'],
        'status' => $data['is_online'] == 1 ? 'online' : 'offline'
    ];
}

function auto_reassign_offline_technician_complaints() {
    global $mysqli;
    
    // Get all offline technicians with active complaints
    $stmt = $mysqli->prepare("
        SELECT DISTINCT u.id, u.full_name, u.specialization
        FROM users u
        JOIN complaints c ON u.id = c.technician_id
        WHERE u.role = 'technician' 
        AND u.is_online = 0
        AND c.status IN ('pending', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $offline_technicians = [];
    while ($row = $result->fetch_assoc()) {
        $offline_technicians[] = $row;
    }
    $stmt->close();
    
    $total_reassigned = 0;
    $total_failed = 0;
    
    foreach ($offline_technicians as $technician) {
        $result = check_technician_offline_and_reassign($technician['id']);
        $total_reassigned += $result['reassigned'];
        $total_failed += $result['failed'];
    }
    
    return [
        'reassigned' => $total_reassigned,
        'failed' => $total_failed,
        'technicians_checked' => count($offline_technicians)
    ];
}

/**
 * Calculate priority score for a complaint based on multiple factors
 */
function calculate_complaint_priority($complaint_id) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT 
            c.category,
            c.created_at,
            c.status,
            u.role as user_role,
            TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return 0;
    }
    
    $priority_score = 0;
    
    // Age-based priority (older = higher priority)
    $hours_old = $complaint['hours_old'];
    if ($hours_old > 72) { // 3+ days old
        $priority_score += 50;
    } elseif ($hours_old > 48) { // 2+ days old
        $priority_score += 30;
    } elseif ($hours_old > 24) { // 1+ day old
        $priority_score += 20;
    } elseif ($hours_old > 12) { // 12+ hours old
        $priority_score += 10;
    }
    
    // Category-based priority
    $category_priorities = [
        'electrician' => 25,  // Critical infrastructure
        'plumber' => 20,      // Water issues are urgent
        'wifi' => 15,         // Modern necessity
        'ac' => 10,           // Comfort but not critical
        'mess' => 8,          // Food service
        'housekeeping' => 5,  // Maintenance
        'carpenter' => 5,     // Maintenance
        'laundry' => 3        // Non-critical
    ];
    
    $priority_score += $category_priorities[$complaint['category']] ?? 5;
    
    // User role priority (faculty/staff get higher priority)
    $role_priorities = [
        'faculty' => 15,
        'nonteaching' => 10,
        'student' => 5,
        'technician' => 0,
        'outsourced_vendor' => 0
    ];
    
    $priority_score += $role_priorities[$complaint['user_role']] ?? 5;
    
    return $priority_score;
}

/**
 * Enhanced auto-assignment with priority consideration
 */
function auto_assign_complaint_with_priority($complaint_id) {
    global $mysqli;
    
    // Get complaint details
    $stmt = $mysqli->prepare("SELECT category, user_id FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return false;
    }
    
    $category = $complaint['category'];
    $submitter_id = $complaint['user_id'];
    $priority_score = calculate_complaint_priority($complaint_id);
    
    // Get all online technicians with matching specialization and comprehensive workload, EXCLUDING the submitter if they are a technician
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as current_workload
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        AND u.is_online = 1
        AND u.id != ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_workload ASC
    ");
    $stmt->bind_param('si', $category, $submitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $technicians = [];
    while ($row = $result->fetch_assoc()) {
        $workload = get_comprehensive_workload($row['id']);
        $row['workload'] = $workload;
        $technicians[] = $row;
    }
    $stmt->close();
    
    if (empty($technicians)) {
        return false;
    }
    
    // Sort technicians based on priority and workload
    usort($technicians, function($a, $b) use ($priority_score) {
        // High priority complaints (>30) go to least busy technicians
        if ($priority_score > 30) {
            return $a['workload']['total_workload'] <=> $b['workload']['total_workload'];
        }
        // Medium priority complaints (10-30) consider both workload and fairness
        elseif ($priority_score > 10) {
            $a_score = $a['workload']['total_workload'] * 0.7 + $a['workload']['pending_complaints'] * 0.3;
            $b_score = $b['workload']['total_workload'] * 0.7 + $b['workload']['pending_complaints'] * 0.3;
            return $a_score <=> $b_score;
        }
        // Low priority complaints (<10) use round-robin for fairness
        else {
            return $a['id'] <=> $b['id'];
        }
    });
    
    $selected_technician = $technicians[0];
    
    // Assign the complaint to the selected technician
    $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $selected_technician['id'], $complaint_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        log_auto_assignment($complaint_id, $selected_technician['id'], $category, 'auto_assigned');
        return $selected_technician;
    }
    
    return false;
}

/**
 * Calculate comprehensive workload for a technician including both complaints and hostel issues
 * with appropriate weighting and complexity factors
 */
function get_comprehensive_workload($technician_id) {
    global $mysqli;
    
    // Get complaint workload (weighted by status)
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(CASE WHEN c.status = 'pending' THEN 1 END) as pending_complaints,
            COUNT(CASE WHEN c.status = 'in_progress' THEN 1 END) as in_progress_complaints,
            COUNT(*) as total_complaints
        FROM complaints c 
        WHERE c.technician_id = ? 
        AND c.status IN ('pending', 'in_progress')
    ");
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $complaint_workload = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get hostel issue workload (weighted by status)
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(CASE WHEN hi.status = 'not_assigned' THEN 1 END) as not_assigned_issues,
            COUNT(CASE WHEN hi.status = 'in_progress' THEN 1 END) as in_progress_issues,
            COUNT(*) as total_issues
        FROM hostel_issues hi 
        WHERE hi.technician_id = ? 
        AND hi.status IN ('not_assigned', 'in_progress')
    ");
    $stmt->bind_param('i', $technician_id);
    $stmt->execute();
    $hostel_workload = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Calculate weighted workload
    // Pending items get higher weight (more urgent)
    // In-progress items get medium weight (already being handled)
    $weighted_complaints = ($complaint_workload['pending_complaints'] * 2) + ($complaint_workload['in_progress_complaints'] * 1);
    $weighted_hostel_issues = ($hostel_workload['not_assigned_issues'] * 1.5) + ($hostel_workload['in_progress_issues'] * 1);
    
    // Total comprehensive workload
    $total_workload = $weighted_complaints + $weighted_hostel_issues;
    
    return [
        'total_workload' => $total_workload,
        'complaint_workload' => $weighted_complaints,
        'hostel_workload' => $weighted_hostel_issues,
        'pending_complaints' => $complaint_workload['pending_complaints'],
        'in_progress_complaints' => $complaint_workload['in_progress_complaints'],
        'not_assigned_issues' => $hostel_workload['not_assigned_issues'],
        'in_progress_issues' => $hostel_workload['in_progress_issues']
    ];
}

/**
 * Get related specializations for cross-specialization assignment
 * Only includes technicians who can actually handle the work
 */
function get_related_specializations($category) {
    // Define logical cross-specialization mappings
    // Only include technicians who can actually perform the work
    $specialization_groups = [
        // Electrical issues - only electricians and AC techs can handle
        'electrician' => ['electrician', 'ac'],
        'ac' => ['ac', 'electrician'],
        
        // Network issues - only WiFi technicians can handle
        'wifi' => ['wifi'],
        
        // Plumbing issues - only plumbers can handle
        'plumber' => ['plumber'],
        
        // Carpentry/structural issues - only carpenters can handle
        'carpenter' => ['carpenter'],
        
        // Cleaning/housekeeping - only housekeeping can handle
        'housekeeping' => ['housekeeping'],
        
        // Food service - only mess technicians can handle
        'mess' => ['mess'],
        
        // Laundry - only laundry technicians can handle
        'laundry' => ['laundry']
    ];
    
    return $specialization_groups[$category] ?? [$category];
}

/**
 * Auto-assign with cross-specialization fallback
 */
function auto_assign_complaint_with_fallback($complaint_id) {
    global $mysqli;
    
    // Get complaint details
    $stmt = $mysqli->prepare("SELECT category, user_id FROM complaints WHERE id = ?");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $complaint = $result->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return false;
    }
    
    $category = $complaint['category'];
    $submitter_id = $complaint['user_id'];
    $related_specializations = get_related_specializations($category);
    
    // Try primary specialization first
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as current_workload
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization = ?
        AND u.is_online = 1
        AND u.id != ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_workload ASC
        LIMIT 1
    ");
    $stmt->bind_param('si', $category, $submitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician = $result->fetch_assoc();
    $stmt->close();
    
    if ($technician) {
        // Primary specialization found - assign normally
        $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
        $stmt->bind_param('ii', $technician['id'], $complaint_id);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($success && $affected_rows > 0) {
            log_auto_assignment($complaint_id, $technician['id'], $category, 'auto_assigned');
            return $technician;
        }
    }
    
    // Try related specializations
    foreach ($related_specializations as $related_spec) {
        if ($related_spec === $category) continue; // Skip primary specialization
        
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('si', $related_spec, $submitter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Related specialization found - assign with note
            $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, tech_note = CONCAT('Cross-assigned to ', ?), updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
            $note = "Cross-assigned to {$technician['specialization']} technician";
            $stmt->bind_param('isi', $technician['id'], $note, $complaint_id);
            $success = $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($success && $affected_rows > 0) {
                log_auto_assignment($complaint_id, $technician['id'], $category, 'admin_assigned');
                return $technician;
            }
        }
    }
    
    // Last resort: Only assign to technicians who can handle general maintenance
    $general_maintenance_specializations = ['housekeeping', 'carpenter'];
    
    if (in_array($category, ['electrician', 'ac', 'wifi', 'plumber', 'mess', 'laundry'])) {
        // For specialized categories, don't assign to general maintenance
        return false;
    }
    
    // Only for general categories, try to assign to maintenance technicians
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as current_workload
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id 
            AND c.status IN ('pending', 'in_progress')
        WHERE u.role = 'technician' 
        AND u.specialization IN ('housekeeping', 'carpenter')
        AND u.is_online = 1
        AND u.id != ?
        GROUP BY u.id, u.full_name, u.specialization
        ORDER BY current_workload ASC
        LIMIT 1
    ");
    $stmt->bind_param('i', $submitter_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician = $result->fetch_assoc();
    $stmt->close();
    
    if ($technician) {
        // General maintenance technician found - assign with note
        $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, tech_note = CONCAT('General maintenance assignment to ', ?), updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
        $note = "General maintenance assignment to {$technician['specialization']} technician";
        $stmt->bind_param('isi', $technician['id'], $note, $complaint_id);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($success && $affected_rows > 0) {
            log_auto_assignment($complaint_id, $technician['id'], $category, 'manual');
            return $technician;
        }
    }
    
    return false;
}

/**
 * Transaction-safe auto-assignment with proper locking
 */
function safe_auto_assign_complaint($complaint_id) {
    global $mysqli;
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Lock the complaint row to prevent race conditions
        $stmt = $mysqli->prepare("SELECT category, technician_id FROM complaints WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $complaint = $result->fetch_assoc();
        $stmt->close();
        
        if (!$complaint) {
            throw new Exception("Complaint not found");
        }
        
        // Check if already assigned
        if ($complaint['technician_id'] && $complaint['technician_id'] != 0) {
            throw new Exception("Complaint already assigned");
        }
        
        $category = $complaint['category'];
        
        // Lock technicians table to prevent concurrent assignments
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if (!$technician) {
            throw new Exception("No online technicians available for this category");
        }
        
        // Double-check technician still exists and is online
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND role = 'technician' AND specialization = ? AND is_online = 1 FOR UPDATE");
        $stmt->bind_param('is', $technician['id'], $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician_exists = $result->fetch_assoc();
        $stmt->close();
        
        if (!$technician_exists) {
            throw new Exception("Selected technician is no longer available");
        }
        
        // Assign the complaint
        $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ii', $technician['id'], $complaint_id);
        $success = $stmt->execute();
        $stmt->close();
        
        if (!$success) {
            throw new Exception("Failed to assign complaint");
        }
        
        // Log the assignment
        log_auto_assignment($complaint_id, $technician['id'], $category, 'auto_assigned');
        
        // Commit transaction
        $mysqli->commit();
        
        return $technician;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $mysqli->rollback();
        error_log("Auto-assignment failed for complaint $complaint_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Transaction-safe bulk assignment with retry mechanism
 */
function safe_bulk_auto_assign($complaint_ids) {
    global $mysqli;
    
    $results = [
        'success' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    foreach ($complaint_ids as $complaint_id) {
        $max_retries = 3;
        $retry_count = 0;
        
        while ($retry_count < $max_retries) {
            $result = safe_auto_assign_complaint($complaint_id);
            
            if ($result) {
                $results['success']++;
                break;
            } else {
                $retry_count++;
                if ($retry_count < $max_retries) {
                    // Wait before retry (exponential backoff)
                    usleep(100000 * $retry_count); // 100ms, 200ms, 300ms
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to assign complaint $complaint_id after $max_retries retries";
                }
            }
        }
    }
    
    return $results;
}

/**
 * Monitor assignment system health and generate alerts
 */
function monitor_assignment_system_health() {
    global $mysqli;
    
    $alerts = [];
    
    // Check for unassigned complaints older than 24 hours
    $stmt = $mysqli->prepare("
        SELECT COUNT(*) as count, 
               GROUP_CONCAT(category) as categories
        FROM complaints 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('pending', 'in_progress')
        AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $old_unassigned = $result->fetch_assoc();
    $stmt->close();
    
    if ($old_unassigned['count'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "{$old_unassigned['count']} complaints unassigned for over 24 hours",
            'details' => "Categories: " . $old_unassigned['categories'],
            'severity' => 'medium'
        ];
    }
    
    // Check for categories with no online technicians
    $stmt = $mysqli->prepare("
        SELECT DISTINCT c.category, COUNT(*) as complaint_count
        FROM complaints c
        LEFT JOIN users u ON u.specialization = c.category AND u.role = 'technician' AND u.is_online = 1
        WHERE (c.technician_id IS NULL OR c.technician_id = 0)
        AND c.status IN ('pending', 'in_progress')
        AND u.id IS NULL
        GROUP BY c.category
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $no_technicians = [];
    $category_details = [];
    while ($row = $result->fetch_assoc()) {
        $no_technicians[] = $row['category'];
        $category_details[] = $row['category'] . ' (' . $row['complaint_count'] . ' complaints)';
    }
    $stmt->close();
    
    if (!empty($no_technicians)) {
        $alerts[] = [
            'type' => 'critical',
            'message' => "No online technicians available for categories: " . implode(', ', $no_technicians),
            'details' => "Total affected complaints: " . array_sum(array_map(function($detail) {
                preg_match('/\((\d+) complaints\)/', $detail, $matches);
                return isset($matches[1]) ? (int)$matches[1] : 0;
            }, $category_details)) . " | Categories: " . implode(', ', $no_technicians),
            'severity' => 'high'
        ];
    }
    
    // Check for overloaded technicians
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as complaint_count,
            COUNT(hi.id) as hostel_issue_count
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
        LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
        WHERE u.role = 'technician' AND u.is_online = 1
        GROUP BY u.id, u.full_name, u.specialization
        HAVING (complaint_count + hostel_issue_count) > 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $overloaded = [];
    while ($row = $result->fetch_assoc()) {
        $overloaded[] = $row;
    }
    $stmt->close();
    
    if (!empty($overloaded)) {
        $technician_names = array_map(function($tech) {
            return $tech['full_name'] . ' (' . $tech['specialization'] . ') - ' . ($tech['complaint_count'] + $tech['hostel_issue_count']) . ' tasks';
        }, $overloaded);
        
        $alerts[] = [
            'type' => 'warning',
            'message' => count($overloaded) . " technicians are overloaded (>10 total assignments)",
            'details' => "Technicians: " . implode(', ', $technician_names),
            'severity' => 'medium'
        ];
    }
    
    return $alerts;
}

/**
 * Send alert to superadmin about system issues
 */
function send_system_alert($alert) {
    global $mysqli;
    
    // Log to security_logs
    $stmt = $mysqli->prepare("
        INSERT INTO security_logs (admin_id, action, target, details, created_at) 
        VALUES (?, 'system_alert', ?, ?, NOW())
    ");
    
    $admin_id = $_SESSION['user_id'] ?? null;
    $target = "assignment_system";
    $details = json_encode($alert);
    
    $stmt->bind_param('iss', $admin_id, $target, $details);
    $stmt->execute();
    $stmt->close();
    
    // Could also send email/SMS notifications here
    error_log("SYSTEM ALERT: " . $alert['message']);
}

/**
 * Get assignment statistics with enhanced metrics
 */
function get_enhanced_assignment_statistics() {
    global $mysqli;
    
    $stats = get_assignment_statistics(); // Get existing stats
    
    // Add enhanced metrics
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as total_unassigned,
            AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())) as avg_wait_time,
            MAX(TIMESTAMPDIFF(HOUR, created_at, NOW())) as max_wait_time
        FROM complaints 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('pending', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $wait_times = $result->fetch_assoc();
    $stmt->close();
    
    // Get category distribution
    $stmt = $mysqli->prepare("
        SELECT 
            category,
            COUNT(*) as count
        FROM complaints 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('pending', 'in_progress')
        GROUP BY category
        ORDER BY count DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $category_distribution = [];
    while ($row = $result->fetch_assoc()) {
        $category_distribution[] = $row;
    }
    $stmt->close();
    
    // Get technician availability
    $stmt = $mysqli->prepare("
        SELECT 
            specialization,
            COUNT(*) as total_technicians,
            SUM(is_online) as online_technicians
        FROM users 
        WHERE role = 'technician'
        GROUP BY specialization
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $technician_availability = [];
    while ($row = $result->fetch_assoc()) {
        $technician_availability[] = $row;
    }
    $stmt->close();
    
    $stats['enhanced'] = [
        'wait_times' => $wait_times,
        'category_distribution' => $category_distribution,
        'technician_availability' => $technician_availability,
        'system_health' => monitor_assignment_system_health()
    ];
    
    return $stats;
}

/**
 * Original auto-assignment function (maintained for backward compatibility)
 */
function auto_assign_all_unassigned_complaints() {
    global $mysqli;
    
    // Get all unassigned complaints with more details
    $stmt = $mysqli->prepare("
        SELECT id, token, category, room_no, created_at
        FROM complaints 
        WHERE (technician_id IS NULL OR technician_id = 0)
        AND status IN ('pending', 'in_progress')
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $unassigned_complaints = [];
    while ($row = $result->fetch_assoc()) {
        $unassigned_complaints[] = $row;
    }
    $stmt->close();
    
    $assigned_count = 0;
    $failed_count = 0;
    $failed_complaints = [];
    $category_stats = [];
    
    foreach ($unassigned_complaints as $complaint) {
        $result = auto_assign_complaint($complaint['id']);
        if ($result) {
            $assigned_count++;
            $category = $complaint['category'];
            if (!isset($category_stats[$category])) {
                $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
            }
            $category_stats[$category]['assigned']++;
        } else {
            $failed_count++;
            $category = $complaint['category'];
            if (!isset($category_stats[$category])) {
                $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
            }
            $category_stats[$category]['failed']++;
            
            // Add failed complaint details
            $failed_complaints[] = [
                'id' => $complaint['id'],
                'token' => $complaint['token'],
                'category' => $complaint['category'],
                'room_no' => $complaint['room_no'],
                'created_at' => $complaint['created_at'],
                'reason' => 'No online technicians available for ' . ucfirst($complaint['category']) . ' category'
            ];
        }
    }
    
    return [
        'total_unassigned' => count($unassigned_complaints),
        'assigned_count' => $assigned_count,
        'failed_count' => $failed_count,
        'failed_complaints' => $failed_complaints,
        'category_stats' => $category_stats
    ];
}

/**
 * DEPRECATED: This function has been replaced by the Smart Auto-Assignment Engine
 * The new system runs automatically via JavaScript every 5 minutes
 * See: includes/auto_assignment_engine.php
 */
function real_time_auto_assignment() {
    global $mysqli;
    
    // Use advisory lock to prevent multiple instances running simultaneously
    $lock_name = 'auto_assignment_lock';
    $lock_result = $mysqli->query("SELECT GET_LOCK('$lock_name', 10) as lock_acquired");
    $lock_acquired = $lock_result->fetch_assoc()['lock_acquired'];
    
    if (!$lock_acquired) {
        error_log("Auto-assignment lock could not be acquired - another process may be running");
        return ['status' => 'locked', 'message' => 'Another auto-assignment process is running'];
    }
    
    try {
        $mysqli->begin_transaction();
        
        // Get unassigned complaints with proper locking
        $stmt = $mysqli->prepare("
            SELECT id, token, category, room_no, created_at, user_id
            FROM complaints 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('pending', 'in_progress')
            ORDER BY created_at ASC
            FOR UPDATE
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $unassigned_complaints = [];
        while ($row = $result->fetch_assoc()) {
            $row['priority'] = calculate_complaint_priority($row['id']);
            $unassigned_complaints[] = $row;
        }
        $stmt->close();
        
        // Sort by priority (highest first)
        usort($unassigned_complaints, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        $assigned_count = 0;
        $failed_count = 0;
        $failed_complaints = [];
        $category_stats = [];
        
        foreach ($unassigned_complaints as $complaint) {
            // Try priority-based assignment first
            $result = safe_auto_assign_complaint_with_priority($complaint['id']);
            
            if (!$result) {
                // Try fallback assignment
                $result = safe_auto_assign_complaint_with_fallback($complaint['id']);
            }
            
            if ($result) {
                $assigned_count++;
                $category = $complaint['category'];
                if (!isset($category_stats[$category])) {
                    $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
                }
                $category_stats[$category]['assigned']++;
                
                // Log the assignment with admin_assigned type
                log_auto_assignment($complaint['id'], $result['technician_id'], $category, 'admin_assigned');
            } else {
                $failed_count++;
                $category = $complaint['category'];
                if (!isset($category_stats[$category])) {
                    $category_stats[$category] = ['assigned' => 0, 'failed' => 0];
                }
                $category_stats[$category]['failed']++;
                
                // Add failed complaint details
                $failed_complaints[] = [
                    'id' => $complaint['id'],
                    'token' => $complaint['token'],
                    'category' => $complaint['category'],
                    'room_no' => $complaint['room_no'],
                    'created_at' => $complaint['created_at'],
                    'priority' => $complaint['priority'],
                    'reason' => 'No technicians available (tried primary and fallback assignments)'
                ];
            }
        }
        
        // Handle hostel issues with same locking mechanism
        $stmt = $mysqli->prepare("
            SELECT id, issue_type, hostel_type, created_at
            FROM hostel_issues 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('not_assigned', 'in_progress')
            FOR UPDATE
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $unassigned_issues = [];
        while ($row = $result->fetch_assoc()) {
            $unassigned_issues[] = $row;
        }
        $stmt->close();
        
        $hostel_assigned_count = 0;
        $hostel_failed_count = 0;
        $failed_hostel_issues = [];
        
        foreach ($unassigned_issues as $issue) {
            $result = safe_auto_assign_hostel_issue($issue['id']);
            if ($result) {
                $hostel_assigned_count++;
            } else {
                $hostel_failed_count++;
                $failed_hostel_issues[] = [
                    'id' => $issue['id'],
                    'issue_type' => $issue['issue_type'],
                    'hostel_type' => $issue['hostel_type'],
                    'created_at' => $issue['created_at'],
                    'reason' => 'No online technicians available for ' . ucfirst($issue['issue_type']) . ' issue type'
                ];
            }
        }
        
        // Send alerts for critical failures
        if ($failed_count > 0 || $hostel_failed_count > 0) {
            $alert = [
                'type' => 'warning',
                'message' => "Real-time auto-assignment completed with $failed_count complaint failures and $hostel_failed_count hostel issue failures",
                'failed_count' => $failed_count + $hostel_failed_count,
                'total_count' => count($unassigned_complaints) + count($unassigned_issues),
                'severity' => 'medium'
            ];
            send_system_alert($alert);
        }
        
        $mysqli->commit();
        
        // Release the lock
        $mysqli->query("SELECT RELEASE_LOCK('$lock_name')");
        
        return [
            'status' => 'success',
            'complaints' => [
                'total_unassigned' => count($unassigned_complaints),
                'assigned_count' => $assigned_count,
                'failed_count' => $failed_count,
                'failed_complaints' => $failed_complaints,
                'category_stats' => $category_stats
            ],
            'hostel_issues' => [
                'total_unassigned' => count($unassigned_issues),
                'assigned_count' => $hostel_assigned_count,
                'failed_count' => $hostel_failed_count,
                'failed_hostel_issues' => $failed_hostel_issues
            ]
        ];
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $mysqli->query("SELECT RELEASE_LOCK('$lock_name')");
        error_log("Real-time auto-assignment failed: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Auto-assignment failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Safe auto-assignment with priority consideration and proper error handling
 */
function safe_auto_assign_complaint_with_priority($complaint_id) {
    global $mysqli;
    
    try {
        // Get complaint details with proper locking
        $stmt = $mysqli->prepare("SELECT category, user_id FROM complaints WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $complaint = $result->fetch_assoc();
        $stmt->close();
        
        if (!$complaint) {
            return false;
        }
        
        $category = $complaint['category'];
        $submitter_id = $complaint['user_id'];
        $priority_score = calculate_complaint_priority($complaint_id);
        
        // Get all online technicians with matching specialization and comprehensive workload, EXCLUDING the submitter if they are a technician
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
        ");
        $stmt->bind_param('si', $category, $submitter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technicians = [];
        while ($row = $result->fetch_assoc()) {
            $workload = get_comprehensive_workload($row['id']);
            $row['workload'] = $workload;
            $technicians[] = $row;
        }
        $stmt->close();
        
        if (empty($technicians)) {
            return false;
        }
        
        // Sort technicians based on priority and workload
        usort($technicians, function($a, $b) use ($priority_score) {
            // High priority complaints (>30) go to least busy technicians
            if ($priority_score > 30) {
                return $a['workload']['total_workload'] <=> $b['workload']['total_workload'];
            }
            // Medium priority complaints (10-30) consider both workload and fairness
            elseif ($priority_score > 10) {
                $a_score = $a['workload']['total_workload'] * 0.7 + $a['workload']['pending_complaints'] * 0.3;
                $b_score = $b['workload']['total_workload'] * 0.7 + $b['workload']['pending_complaints'] * 0.3;
                return $a_score <=> $b_score;
            }
            // Low priority complaints (<10) use round-robin for fairness
            else {
                return $a['id'] <=> $b['id'];
            }
        });
        
        $selected_technician = $technicians[0];
        
        // Assign the complaint to the selected technician with proper error handling
        $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
        $stmt->bind_param('ii', $selected_technician['id'], $complaint_id);
        $success = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($success && $affected_rows > 0) {
            log_auto_assignment($complaint_id, $selected_technician['id'], $category, 'auto_assigned');
            return $selected_technician;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Safe auto-assignment with priority failed for complaint $complaint_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe fallback assignment with proper error handling
 */
function safe_auto_assign_complaint_with_fallback($complaint_id) {
    global $mysqli;
    
    try {
        // Get complaint details with proper locking
        $stmt = $mysqli->prepare("SELECT category, user_id FROM complaints WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $complaint_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $complaint = $result->fetch_assoc();
        $stmt->close();
        
        if (!$complaint) {
            return false;
        }
        
        $category = $complaint['category'];
        $submitter_id = $complaint['user_id'];
        $related_specializations = get_related_specializations($category);
        
        // Try primary specialization first
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('si', $category, $submitter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Primary specialization found - assign normally
            $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
            $stmt->bind_param('ii', $technician['id'], $complaint_id);
            $success = $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($success && $affected_rows > 0) {
                log_auto_assignment($complaint_id, $technician['id'], $category, 'auto_assigned');
                return $technician;
            }
        }
        
        // Try related specializations
        foreach ($related_specializations as $related_spec) {
            if ($related_spec === $category) continue; // Skip primary specialization
            
            $stmt = $mysqli->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.specialization,
                    COUNT(c.id) as current_workload
                FROM users u
                LEFT JOIN complaints c ON u.id = c.technician_id 
                    AND c.status IN ('pending', 'in_progress')
                WHERE u.role = 'technician' 
                AND u.specialization = ?
                AND u.is_online = 1
                AND u.id != ?
                GROUP BY u.id, u.full_name, u.specialization
                ORDER BY current_workload ASC
                LIMIT 1
            ");
            $stmt->bind_param('si', $related_spec, $submitter_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $technician = $result->fetch_assoc();
            $stmt->close();
            
            if ($technician) {
                // Related specialization found - assign with note
                $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, tech_note = CONCAT('Cross-assigned to ', ?), updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
                $note = "Cross-assigned to {$technician['specialization']} technician";
                $stmt->bind_param('isi', $technician['id'], $note, $complaint_id);
                $success = $stmt->execute();
                $affected_rows = $stmt->affected_rows;
                $stmt->close();
                
                if ($success && $affected_rows > 0) {
                    log_auto_assignment($complaint_id, $technician['id'], $category, 'admin_assigned');
                    return $technician;
                }
            }
        }
        
        // Last resort: Only assign to technicians who can handle general maintenance
        $general_maintenance_specializations = ['housekeeping', 'carpenter'];
        
        if (in_array($category, ['electrician', 'ac', 'wifi', 'plumber', 'mess', 'laundry'])) {
            // For specialized categories, don't assign to general maintenance
            return false;
        }
        
        // Only for general categories, try to assign to maintenance technicians
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(c.id) as current_workload
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id 
                AND c.status IN ('pending', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization IN ('housekeeping', 'carpenter')
            AND u.is_online = 1
            AND u.id != ?
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('i', $submitter_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // General maintenance technician found - assign with note
            $stmt = $mysqli->prepare("UPDATE complaints SET technician_id = ?, tech_note = CONCAT('General maintenance assignment to ', ?), updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
            $note = "General maintenance assignment to {$technician['specialization']} technician";
            $stmt->bind_param('isi', $technician['id'], $note, $complaint_id);
            $success = $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($success && $affected_rows > 0) {
                log_auto_assignment($complaint_id, $technician['id'], $category, 'manual');
                return $technician;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Safe fallback assignment failed for complaint $complaint_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe hostel issue assignment with proper error handling
 */
function safe_auto_assign_hostel_issue($issue_id) {
    global $mysqli;
    
    try {
        // Get hostel issue details with proper locking
        $stmt = $mysqli->prepare("SELECT issue_type, hostel_type FROM hostel_issues WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $issue = $result->fetch_assoc();
        $stmt->close();
        
        if (!$issue) {
            return false;
        }
        
        $issue_type = $issue['issue_type'];
        
        // Get available technicians for this issue type
        $stmt = $mysqli->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.specialization,
                COUNT(hi.id) as current_hostel_workload
            FROM users u
            LEFT JOIN hostel_issues hi ON u.id = hi.technician_id 
                AND hi.status IN ('not_assigned', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY current_hostel_workload ASC
            LIMIT 1
        ");
        $stmt->bind_param('s', $issue_type);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Assign the hostel issue to the selected technician
            $stmt = $mysqli->prepare("UPDATE hostel_issues SET technician_id = ?, updated_at = NOW() WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)");
            $stmt->bind_param('ii', $technician['id'], $issue_id);
            $success = $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($success && $affected_rows > 0) {
                log_hostel_issue_auto_assignment($issue_id, $technician['id'], $issue_type);
                return $technician;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Safe hostel issue assignment failed for issue $issue_id: " . $e->getMessage());
        return false;
    }
}

/**
 * Enhanced system health monitoring with caching and performance metrics
 */
function enhanced_monitor_assignment_system_health() {
    global $mysqli;
    
    $alerts = [];
    $performance_metrics = [];
    
    // Cache technician availability for 30 seconds
    $cache_key = 'technician_availability';
    $cached_data = get_cache($cache_key);
    
    if ($cached_data === false) {
        // Get technician availability data
        $stmt = $mysqli->prepare("
            SELECT 
                specialization,
                COUNT(*) as total_technicians,
                SUM(is_online) as online_technicians,
                AVG(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) as online_percentage
            FROM users 
            WHERE role = 'technician'
            GROUP BY specialization
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $technician_data = [];
        while ($row = $result->fetch_assoc()) {
            $technician_data[$row['specialization']] = $row;
        }
        $stmt->close();
        
        // Cache for 30 seconds
        set_cache($cache_key, $technician_data, 30);
    } else {
        $technician_data = $cached_data;
    }
    
    // Check for categories with no online technicians
    $stmt = $mysqli->prepare("
        SELECT DISTINCT c.category, COUNT(*) as complaint_count
        FROM complaints c
        LEFT JOIN users u ON u.specialization = c.category AND u.role = 'technician' AND u.is_online = 1
        WHERE (c.technician_id IS NULL OR c.technician_id = 0)
        AND c.status IN ('pending', 'in_progress')
        AND u.id IS NULL
        GROUP BY c.category
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $no_technicians = [];
    $category_details = [];
    while ($row = $result->fetch_assoc()) {
        $no_technicians[] = $row['category'];
        $category_details[] = $row['category'] . ' (' . $row['complaint_count'] . ' complaints)';
    }
    $stmt->close();
    
    if (!empty($no_technicians)) {
        $alerts[] = [
            'type' => 'critical',
            'message' => "No online technicians available for categories: " . implode(', ', $no_technicians),
            'details' => "Total affected complaints: " . array_sum(array_map(function($detail) {
                preg_match('/\((\d+) complaints\)/', $detail, $matches);
                return isset($matches[1]) ? (int)$matches[1] : 0;
            }, $category_details)) . " | Categories: " . implode(', ', $no_technicians),
            'severity' => 'high',
            'technicians' => array_map(function($cat) use ($technician_data) {
                return [
                    'category' => $cat,
                    'total' => $technician_data[$cat]['total_technicians'] ?? 0,
                    'online' => $technician_data[$cat]['online_technicians'] ?? 0
                ];
            }, $no_technicians)
        ];
    }
    
    // Check for overloaded technicians with enhanced metrics
    $stmt = $mysqli->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.specialization,
            COUNT(c.id) as complaint_count,
            COUNT(hi.id) as hostel_issue_count,
            AVG(TIMESTAMPDIFF(HOUR, c.created_at, NOW())) as avg_complaint_age,
            MAX(TIMESTAMPDIFF(HOUR, c.created_at, NOW())) as max_complaint_age
        FROM users u
        LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
        LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
        WHERE u.role = 'technician' AND u.is_online = 1
        GROUP BY u.id, u.full_name, u.specialization
        HAVING (complaint_count + hostel_issue_count) > 8
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $overloaded = [];
    while ($row = $result->fetch_assoc()) {
        $overloaded[] = $row;
    }
    $stmt->close();
    
    if (!empty($overloaded)) {
        $technician_names = array_map(function($tech) {
            return $tech['full_name'] . ' (' . $tech['specialization'] . ') - ' . ($tech['complaint_count'] + $tech['hostel_issue_count']) . ' tasks';
        }, $overloaded);
        
        $alerts[] = [
            'type' => 'warning',
            'message' => count($overloaded) . " technicians are overloaded (>8 total assignments)",
            'details' => "Technicians: " . implode(', ', $technician_names),
            'severity' => 'medium',
            'technicians' => $overloaded
        ];
    }
    
    // Check for stuck assignments (complaints older than 48 hours)
    $stmt = $mysqli->prepare("
        SELECT 
            COUNT(*) as stuck_count,
            GROUP_CONCAT(DISTINCT category) as categories,
            AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())) as avg_age
        FROM complaints 
        WHERE technician_id IS NOT NULL 
        AND status IN ('pending', 'in_progress')
        AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $stuck_assignments = $result->fetch_assoc();
    $stmt->close();
    
    if ($stuck_assignments['stuck_count'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "{$stuck_assignments['stuck_count']} complaints stuck for over 48 hours",
            'details' => "Average age: " . round($stuck_assignments['avg_age']) . " hours | Categories: " . $stuck_assignments['categories'],
            'severity' => 'medium'
        ];
    }
    
    // Performance metrics
    $performance_metrics = [
        'total_technicians' => array_sum(array_column($technician_data, 'total_technicians')),
        'online_technicians' => array_sum(array_column($technician_data, 'online_technicians')),
        'overloaded_technicians' => count($overloaded),
        'categories_without_technicians' => count($no_technicians),
        'stuck_assignments' => $stuck_assignments['stuck_count'],
        'system_health_score' => calculate_system_health_score($technician_data, $overloaded, $no_technicians, $stuck_assignments)
    ];
    
    return [
        'alerts' => $alerts,
        'performance_metrics' => $performance_metrics,
        'technician_data' => $technician_data
    ];
}

/**
 * Calculate system health score (0-100, higher is better)
 */
function calculate_system_health_score($technician_data, $overloaded, $no_technicians, $stuck_assignments) {
    $score = 100;
    
    // Deduct points for overloaded technicians
    $score -= (count($overloaded) * 5);
    
    // Deduct points for categories without technicians
    $score -= (count($no_technicians) * 15);
    
    // Deduct points for stuck assignments
    $score -= min(($stuck_assignments['stuck_count'] * 2), 20);
    
    // Deduct points for low online percentage
    $total_technicians = array_sum(array_column($technician_data, 'total_technicians'));
    $online_technicians = array_sum(array_column($technician_data, 'online_technicians'));
    
    if ($total_technicians > 0) {
        $online_percentage = ($online_technicians / $total_technicians) * 100;
        if ($online_percentage < 50) {
            $score -= (50 - $online_percentage);
        }
    }
    
    return max(0, $score);
}

/**
 * Simple caching system for performance optimization
 */
function get_cache($key) {
    $cache_file = __DIR__ . '/../logs/cache_' . md5($key) . '.json';
    
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && isset($data['expires']) && $data['expires'] > time()) {
            return $data['value'];
        }
        // Remove expired cache
        unlink($cache_file);
    }
    
    return false;
}

function set_cache($key, $value, $ttl_seconds = 300) {
    $cache_file = __DIR__ . '/../logs/cache_' . md5($key) . '.json';
    
    // Create logs directory if it doesn't exist
    if (!is_dir(dirname($cache_file))) {
        mkdir(dirname($cache_file), 0755, true);
    }
    
    $data = [
        'value' => $value,
        'expires' => time() + $ttl_seconds,
        'created' => time()
    ];
    
    file_put_contents($cache_file, json_encode($data), LOCK_EX);
}

function clear_cache($pattern = null) {
    $cache_dir = __DIR__ . '/../logs/';
    $files = glob($cache_dir . 'cache_*.json');
    
    foreach ($files as $file) {
        if ($pattern === null || strpos(basename($file), $pattern) !== false) {
            unlink($file);
        }
    }
}

/**
 * DEPRECATED: This function has been replaced by the Smart Auto-Assignment Engine
 * The new system runs automatically via JavaScript every 5 minutes
 * See: includes/auto_assignment_engine.php
 */
function enhanced_auto_assign_all_unassigned_complaints() {
    $start_time = microtime(true);
    
    // Run the enhanced auto-assignment
    $result = auto_assign_all_unassigned_complaints_enhanced();
    
    // Calculate performance metrics
    $execution_time = microtime(true) - $start_time;
    $performance_metrics = [
        'execution_time' => round($execution_time, 3),
        'complaints_processed' => $result['total_unassigned'],
        'success_rate' => $result['total_unassigned'] > 0 ? 
            round(($result['assigned_count'] / $result['total_unassigned']) * 100, 2) : 100
    ];
    
    // Log performance metrics
    error_log("Auto-assignment performance: " . json_encode($performance_metrics));
    
    // Clear relevant caches
    clear_cache('technician_availability');
    clear_cache('workload');
    
    return array_merge($result, ['performance_metrics' => $performance_metrics]);
}

/**
 * Enhanced workload calculation with caching
 */
function get_cached_comprehensive_workload($technician_id) {
    $cache_key = "workload_technician_$technician_id";
    $cached_workload = get_cache($cache_key);
    
    if ($cached_workload === false) {
        $workload = get_comprehensive_workload($technician_id);
        // Cache for 60 seconds
        set_cache($cache_key, $workload, 60);
        return $workload;
    }
    
    return $cached_workload;
}

/**
 * Enhanced priority calculation with complexity factors
 */
function calculate_enhanced_complaint_priority($complaint_id) {
    global $mysqli;
    
    $stmt = $mysqli->prepare("
        SELECT 
            c.category,
            c.created_at,
            c.status,
            c.description,
            u.role as user_role,
            TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as hours_old,
            LENGTH(c.description) as description_length
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param('i', $complaint_id);
    $stmt->execute();
    $complaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$complaint) {
        return 0;
    }
    
    $priority_score = 0;
    
    // Age-based priority (older = higher priority)
    $hours_old = $complaint['hours_old'];
    if ($hours_old > 72) { // 3+ days old
        $priority_score += 50;
    } elseif ($hours_old > 48) { // 2+ days old
        $priority_score += 30;
    } elseif ($hours_old > 24) { // 1+ day old
        $priority_score += 20;
    } elseif ($hours_old > 12) { // 12+ hours old
        $priority_score += 10;
    }
    
    // Category-based priority with complexity factors
    $category_priorities = [
        'electrician' => 25,  // Critical infrastructure
        'plumber' => 20,      // Water issues are urgent
        'wifi' => 15,         // Modern necessity
        'ac' => 10,           // Comfort but not critical
        'mess' => 8,          // Food service
        'housekeeping' => 5,  // Maintenance
        'carpenter' => 5,     // Maintenance
        'laundry' => 3        // Non-critical
    ];
    
    $priority_score += $category_priorities[$complaint['category']] ?? 5;
    
    // User role priority (faculty/staff get higher priority)
    $role_priorities = [
        'faculty' => 15,
        'nonteaching' => 10,
        'student' => 5,
        'technician' => 0,
        'outsourced_vendor' => 0
    ];
    
    $priority_score += $role_priorities[$complaint['user_role']] ?? 5;
    
    // Description complexity factor (longer descriptions might indicate more complex issues)
    $description_length = $complaint['description_length'];
    if ($description_length > 200) {
        $priority_score += 5; // Complex issue
    } elseif ($description_length > 100) {
        $priority_score += 3; // Moderate complexity
    }
    
    // Emergency keywords detection
    $emergency_keywords = ['urgent', 'emergency', 'broken', 'not working', 'critical', 'immediate'];
    $description_lower = strtolower($complaint['description']);
    foreach ($emergency_keywords as $keyword) {
        if (strpos($description_lower, $keyword) !== false) {
            $priority_score += 8;
            break; // Only add once per complaint
        }
    }
    
    return $priority_score;
}
?> 