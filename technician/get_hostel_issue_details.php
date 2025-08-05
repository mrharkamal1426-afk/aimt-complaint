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

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get issue ID
$issue_id = $_GET['issue_id'] ?? null;

if (!$issue_id) {
    echo json_encode(['success' => false, 'message' => 'Issue ID is required']);
    exit;
}

try {
    $issue = getHostelIssueDetails($mysqli, $issue_id);
    
    if (!$issue) {
        echo json_encode(['success' => false, 'message' => 'Issue not found']);
        exit;
    }
    
    // Get issue type labels
    $issue_types = [
        'wifi' => 'Wi-Fi Issues',
        'water' => 'Water Issues', 
        'mess' => 'Mess Issues',
        'electricity' => 'Electricity Issues',
        'cleanliness' => 'Cleanliness Issues',
        'other' => 'Other Issues'
    ];
    
    // Format the response
    $response = [
        'success' => true,
        'issue' => [
            'id' => $issue['id'],
            'hostel_type' => ucfirst($issue['hostel_type']) . ' Hostel',
            'issue_type' => $issue_types[$issue['issue_type']] ?? ucfirst($issue['issue_type']),
            'status' => ucfirst(str_replace('_', ' ', $issue['status'])),
            'votes' => $issue['votes'],
            'technician_name' => $issue['technician_name'] ?? 'Not Assigned',
            'technician_phone' => $issue['technician_phone'] ?? '',
            'tech_remarks' => $issue['tech_remarks'] ?? '',
            'created_at' => date('M j, Y g:i A', strtotime($issue['created_at'])),
            'updated_at' => $issue['updated_at'] ? date('M j, Y g:i A', strtotime($issue['updated_at'])) : ''
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 