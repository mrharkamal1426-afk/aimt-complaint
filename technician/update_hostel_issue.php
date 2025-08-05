<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/hostel_issue_functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$tech_id = $_SESSION['user_id'];

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$issue_id = $_POST['issue_id'] ?? null;
$action = $_POST['action'] ?? '';
$status = $_POST['status'] ?? '';
$remarks = $_POST['remarks'] ?? '';

if (!$issue_id) {
    echo json_encode(['success' => false, 'message' => 'Issue ID is required']);
    exit;
}

try {
    switch ($action) {
        case 'assign':
            // Assign issue to technician
            $result = updateHostelIssueStatus($mysqli, $issue_id, $tech_id, 'in_progress', $remarks);
            break;
            
        case 'update_status':
            // Update issue status
            if (!in_array($status, ['not_assigned', 'in_progress', 'resolved'])) {
                throw new Exception('Invalid status');
            }
            $result = updateHostelIssueStatus($mysqli, $issue_id, $tech_id, $status, $remarks);
            break;
            
        case 'add_remarks':
            // Just add remarks without changing status
            $stmt = $mysqli->prepare("SELECT status FROM hostel_issues WHERE id = ?");
            $stmt->bind_param('i', $issue_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $issue = $result->fetch_assoc();
            $stmt->close();
            
            if (!$issue) {
                throw new Exception('Issue not found');
            }
            
            $result = updateHostelIssueStatus($mysqli, $issue_id, $tech_id, $issue['status'], $remarks);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 