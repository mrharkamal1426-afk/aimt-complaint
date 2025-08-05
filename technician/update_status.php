<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Log the request for debugging (but don't display errors)
error_log("update_status.php called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    die(json_encode([
        'success' => false, 
        'message' => 'Invalid request method. Only POST requests are allowed.'
    ]));
}

// Validate user authentication and role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    error_log("Unauthorized access attempt - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['role'] ?? 'not set'));
    die(json_encode([
        'success' => false, 
        'message' => 'Unauthorized access. Please log in as a technician.'
    ]));
}

// Validate required parameters
if (!isset($_POST['token']) || empty(trim($_POST['token']))) {
    error_log("Missing or empty token parameter");
    die(json_encode([
        'success' => false, 
        'message' => 'Complaint token is required.'
    ]));
}

if (!isset($_POST['status']) || empty(trim($_POST['status']))) {
    error_log("Missing or empty status parameter");
    die(json_encode([
        'success' => false, 
        'message' => 'Status is required.'
    ]));
}

// Validate status value - allow resolved only for QR scanning
$valid_statuses = ['pending', 'in_progress', 'rejected'];
$new_status = trim($_POST['status']);

// Special case: allow resolved status for QR scanning (when status is explicitly 'resolved')
if ($new_status === 'resolved') {
    // This is allowed for QR scanning resolution
} elseif (!in_array($new_status, $valid_statuses)) {
    die(json_encode([
        'success' => false, 
        'message' => 'Invalid status value. Resolved status can only be set through QR scanning.'
    ]));
}

// Sanitize input parameters
$token = trim($_POST['token']);
$tech_remark = isset($_POST['tech_remark']) ? trim($_POST['tech_remark']) : '';
$tech_id = (int)$_SESSION['user_id'];

error_log("Processing request - Token: $token, Tech ID: $tech_id, New Status: $new_status, Remark: $tech_remark");

try {
    // Start database transaction
    $mysqli->begin_transaction();

    // Step 1: Get technician's specialization
    $tech_stmt = $mysqli->prepare("SELECT specialization FROM users WHERE id = ? AND role = 'technician'");
    if (!$tech_stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }
    
    $tech_stmt->bind_param('i', $tech_id);
    $tech_stmt->execute();
    $tech_result = $tech_stmt->get_result();
    $tech_data = $tech_result->fetch_assoc();
    $tech_stmt->close();

    if (!$tech_data || !$tech_data['specialization']) {
        throw new Exception("Technician profile not found or specialization not set.");
    }

    $specialization = $tech_data['specialization'];
    error_log("Technician specialization: $specialization");

    // Step 2: Get complaint details with all necessary information
    $complaint_stmt = $mysqli->prepare("
        SELECT 
            c.id,
            c.token,
            c.status,
            c.room_no,
            c.category,
            c.technician_id,
            c.created_at,
            c.updated_at,
            u.full_name as user_name,
            u.phone as user_phone
        FROM complaints c 
        JOIN users u ON u.id = c.user_id 
        WHERE c.token = ? 
        FOR UPDATE
    ");
    
    if (!$complaint_stmt) {
        throw new Exception("Database error: " . $mysqli->error);
    }

    $complaint_stmt->bind_param('s', $token);
    $complaint_stmt->execute();
    $complaint_result = $complaint_stmt->get_result();
    
    if ($complaint_result->num_rows === 0) {
        throw new Exception("Complaint not found with the provided token.");
    }
    
    $complaint = $complaint_result->fetch_assoc();
    $complaint_stmt->close();

    error_log("Found complaint - ID: {$complaint['id']}, Current Status: {$complaint['status']}, Category: {$complaint['category']}");

    // Step 3: Validate complaint access - either category match OR admin assigned
    $is_category_match = ($complaint['category'] === $specialization);
    $is_admin_assigned = ($complaint['technician_id'] == $tech_id);
    
    if (!$is_category_match && !$is_admin_assigned) {
        throw new Exception("This complaint is for '{$complaint['category']}' category. You are specialized in '$specialization'. Please contact the relevant technician.");
    }

    // Step 4: Check current complaint status and validate status transition
    $current_status = $complaint['status'];
    
    // Define valid status transitions
    $valid_transitions = [
        'pending' => ['in_progress', 'resolved', 'rejected'],
        'in_progress' => ['resolved', 'rejected'],
        'resolved' => [], // Cannot change from resolved
        'rejected' => []  // Cannot change from rejected
    ];
    
    if (!in_array($new_status, $valid_transitions[$current_status])) {
        throw new Exception("Cannot change status from '$current_status' to '$new_status'.");
    }

    // Step 5: Update complaint status
    if (!empty($complaint['technician_id']) && $complaint['technician_id'] != 0) {
        // Admin assigned: update technician_id
        $update_stmt = $mysqli->prepare("
            UPDATE complaints 
            SET 
                status = ?,
                technician_id = ?,
                tech_note = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND token = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        $update_stmt->bind_param('sisis', $new_status, $tech_id, $tech_remark, $complaint['id'], $token);
    } else {
        // Auto assigned: do not update technician_id
        $update_stmt = $mysqli->prepare("
            UPDATE complaints 
            SET 
                status = ?,
                tech_note = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND token = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Database error: " . $mysqli->error);
        }
        $update_stmt->bind_param('ssis', $new_status, $tech_remark, $complaint['id'], $token);
    }
    
    if (!$update_stmt->execute()) {
        throw new Exception("Failed to update complaint status: " . $update_stmt->error);
    }
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception("No rows were updated. The complaint may have been modified by another user.");
    }
    
    $update_stmt->close();

    // Step 6: Commit transaction
    $mysqli->commit();

    error_log("Successfully updated complaint ID: {$complaint['id']} to status: $new_status");

    // Step 7: Return success response with complaint details
    echo json_encode([
        'success' => true,
        'message' => 'Complaint status updated successfully!',
        'data' => [
            'complaint_id' => $complaint['id'],
            'token' => $complaint['token'],
            'user_name' => $complaint['user_name'],
            'user_phone' => $complaint['user_phone'],
            'room_no' => $complaint['room_no'],
            'category' => $complaint['category'],
            'old_status' => $current_status,
            'new_status' => $new_status,
            'tech_remark' => $tech_remark,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $tech_id
        ]
    ]);

} catch (Exception $e) {
    error_log("Error in update_status.php: " . $e->getMessage());
    
    // Rollback transaction on error
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'validation_error'
    ]);
    
} catch (Error $e) {
    error_log("Fatal error in update_status.php: " . $e->getMessage());
    
    // Rollback transaction on fatal error
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'error_type' => 'system_error'
    ]);
}

// Close database connection
if (isset($mysqli) && $mysqli->ping()) {
    $mysqli->close();
}
?> 