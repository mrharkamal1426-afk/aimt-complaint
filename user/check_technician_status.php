<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['student', 'faculty', 'nonteaching', 'outsourced_vendor'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_status':
            // Check technician status for a specific complaint
            $complaint_id = intval($_POST['complaint_id'] ?? 0);
            
            if (!$complaint_id) {
                echo json_encode(['success' => false, 'message' => 'Complaint ID required']);
                exit;
            }
            
            // Verify the complaint belongs to this user
            $stmt = $mysqli->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $complaint_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Complaint not found']);
                exit;
            }
            $stmt->close();
            
            // Get technician status
            $tech_status = get_technician_status_for_complaint($complaint_id);
            
            if ($tech_status) {
                echo json_encode([
                    'success' => true,
                    'status' => $tech_status
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Unable to get technician status']);
            }
            break;
            
        case 'reassign_offline':
            // Trigger auto-reassignment for offline technicians
            $result = auto_reassign_offline_technician_complaints();
            
            echo json_encode([
                'success' => true,
                'result' => $result,
                'message' => "Reassigned {$result['reassigned']} complaints from offline technicians"
            ]);
            break;
            
        case 'get_all_status':
            // Get status for all user's complaints
            $stmt = $mysqli->prepare("
                SELECT c.id, c.token, c.category, c.status, c.technician_id,
                       t.full_name as tech_name, t.phone as tech_phone, t.is_online as tech_online
                FROM complaints c
                LEFT JOIN users t ON t.id = c.technician_id
                WHERE c.user_id = ? AND c.status IN ('pending', 'in_progress')
                ORDER BY c.created_at DESC
            ");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $complaints = [];
            while ($row = $result->fetch_assoc()) {
                $tech_status = get_technician_status_for_complaint($row['id']);
                $complaints[] = [
                    'id' => $row['id'],
                    'token' => $row['token'],
                    'category' => $row['category'],
                    'status' => $row['status'],
                    'technician_status' => $tech_status
                ];
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'complaints' => $complaints
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    // GET request - return current status
    $complaint_id = intval($_GET['complaint_id'] ?? 0);
    
    if ($complaint_id) {
        // Verify the complaint belongs to this user
        $stmt = $mysqli->prepare("SELECT id FROM complaints WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $complaint_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Complaint not found']);
            exit;
        }
        $stmt->close();
        
        // Get technician status
        $tech_status = get_technician_status_for_complaint($complaint_id);
        
        if ($tech_status) {
            echo json_encode([
                'success' => true,
                'status' => $tech_status
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unable to get technician status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Complaint ID required']);
    }
}
?> 