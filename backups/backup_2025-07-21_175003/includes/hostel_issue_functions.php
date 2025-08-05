<?php
require_once __DIR__.'/config.php';

/**
 * Auto-assign hostel issues based on technician specialization
 * If no technician with matching specialization exists, assign to all technicians
 */
function autoAssignHostelIssue($mysqli, $issue_id) {
    // Get the issue details
    $stmt = $mysqli->prepare("SELECT hostel_type, issue_type FROM hostel_issues WHERE id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();
    $stmt->close();
    
    if (!$issue) {
        return false;
    }
    
    // Define specialization mapping for hostel issues
    $specialization_mapping = [
        'wifi' => 'wifi',
        'water' => 'plumber',
        'mess' => 'mess',
        'electricity' => 'electrician',
        'cleanliness' => 'housekeeping',
        'other' => null // Will be assigned to all technicians
    ];
    
    $target_specialization = $specialization_mapping[$issue['issue_type']] ?? null;
    
    if ($target_specialization) {
        // Try to find a technician with matching specialization
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE role = 'technician' AND specialization = ? LIMIT 1");
        $stmt->bind_param('s', $target_specialization);
        $stmt->execute();
        $result = $stmt->get_result();
        $technician = $result->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Assign to the first available technician with matching specialization
            $stmt = $mysqli->prepare("UPDATE hostel_issues SET technician_id = ?, status = 'in_progress' WHERE id = ?");
            $stmt->bind_param('ii', $technician['id'], $issue_id);
            $success = $stmt->execute();
            $stmt->close();
            return $success;
        }
    }
    
    // If no matching specialization or no technician found, leave as not_assigned
    // (will be visible to all technicians)
    return true;
}

/**
 * Get hostel issues for a technician based on their specialization
 */
function getHostelIssuesForTechnician($mysqli, $tech_id, $filters = []) {
    // Get technician's specialization
    $stmt = $mysqli->prepare("SELECT specialization FROM users WHERE id = ?");
    $stmt->bind_param('i', $tech_id);
    $stmt->execute();
    $stmt->bind_result($specialization);
    $stmt->fetch();
    $stmt->close();
    
    if (!$specialization) {
        return [];
    }
    
    // Define specialization mapping for hostel issues
    $specialization_mapping = [
        'wifi' => 'wifi',
        'water' => 'plumber',
        'mess' => 'mess',
        'electricity' => 'electrician',
        'cleanliness' => 'housekeeping',
        'other' => null // Will be assigned to all technicians
    ];
    
    // Build the SQL query with filters
    $where_conditions = [];
    $params = [];
    $param_types = '';
    
    // Add specialization filter
    $matching_issue_types = [];
    foreach ($specialization_mapping as $issue_type => $spec) {
        if ($spec === $specialization || $spec === null) {
            $matching_issue_types[] = $issue_type;
        }
    }
    
    if (!empty($matching_issue_types)) {
        $placeholders = str_repeat('?,', count($matching_issue_types) - 1) . '?';
        $where_conditions[] = "hi.issue_type IN ($placeholders)";
        $params = array_merge($params, $matching_issue_types);
        $param_types .= str_repeat('s', count($matching_issue_types));
    }
    
    // Add other filters
    if (isset($filters['hostel']) && $filters['hostel'] !== 'all') {
        $where_conditions[] = "hi.hostel_type = ?";
        $params[] = $filters['hostel'];
        $param_types .= 's';
    }
    
    if (isset($filters['status']) && $filters['status'] !== 'all') {
        $where_conditions[] = "hi.status = ?";
        $params[] = $filters['status'];
        $param_types .= 's';
    }
    
    if (isset($filters['issue']) && $filters['issue'] !== 'all') {
        $where_conditions[] = "hi.issue_type = ?";
        $params[] = $filters['issue'];
        $param_types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $sql = "SELECT hi.*, 
        (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes,
        t.full_name as technician_name
        FROM hostel_issues hi
        LEFT JOIN users t ON t.id = hi.technician_id
        $where_clause
        ORDER BY hi.created_at DESC";
    
    $stmt = $mysqli->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hostel_issues = [];
    while ($row = $result->fetch_assoc()) {
        $hostel_issues[] = $row;
    }
    $stmt->close();
    
    return $hostel_issues;
}

/**
 * Update hostel issue status and add remarks
 */
function updateHostelIssueStatus($mysqli, $issue_id, $tech_id, $status, $remarks = null) {
    // Verify the technician can work on this issue
    $stmt = $mysqli->prepare("SELECT hi.*, u.specialization 
                             FROM hostel_issues hi 
                             LEFT JOIN users u ON u.id = hi.technician_id 
                             WHERE hi.id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();
    $stmt->close();
    
    if (!$issue) {
        return ['success' => false, 'message' => 'Issue not found'];
    }
    
    // Check if technician is assigned or if issue is unassigned (available to all)
    if ($issue['technician_id'] && $issue['technician_id'] != $tech_id) {
        return ['success' => false, 'message' => 'This issue is assigned to another technician'];
    }
    
    // Update the issue
    $stmt = $mysqli->prepare("UPDATE hostel_issues SET status = ?, technician_id = ?, tech_remarks = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('sisi', $status, $tech_id, $remarks, $issue_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        return ['success' => true, 'message' => 'Issue updated successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to update issue'];
    }
}

/**
 * Get hostel issue details
 */
function getHostelIssueDetails($mysqli, $issue_id) {
    $stmt = $mysqli->prepare("SELECT hi.*, 
        (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes,
        t.full_name as technician_name,
        t.phone as technician_phone
        FROM hostel_issues hi
        LEFT JOIN users t ON t.id = hi.technician_id
        WHERE hi.id = ?");
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $issue = $result->fetch_assoc();
    $stmt->close();
    
    return $issue;
}
?> 