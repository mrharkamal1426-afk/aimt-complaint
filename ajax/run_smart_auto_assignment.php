<?php
// Define AJAX request constant
define('AJAX_REQUEST', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auto_assignment_engine.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in and is superadmin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Initialize the smart auto-assignment engine
    $engine = new SmartAutoAssignmentEngine($mysqli);
    
    // Run the smart auto-assignment
    $result = $engine->runSmartAutoAssignment();
    
    // Return the result
    echo json_encode($result);
    
} catch (Exception $e) {
    // Log the error
    error_log("Smart Auto-Assignment Error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Auto-assignment failed: ' . $e->getMessage()
    ]);
}
?> 