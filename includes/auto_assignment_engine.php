<?php
/**
 * Smart Auto-Assignment Engine
 * Runs automatically every 5 minutes via JavaScript/AJAX
 * No cron jobs required - completely self-contained
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

class SmartAutoAssignmentEngine {
    private $mysqli;
    private $logs = [];
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    /**
     * Main auto-assignment validation and assignment engine
     */
    public function runSmartAutoAssignment() {
        $this->log("Starting Smart Auto-Assignment Engine");
        
        try {
            // Step 1: Validate existing assignments
            $validation_results = $this->validateExistingAssignments();
            
            // Step 2: Assign unassigned complaints
            $assignment_results = $this->assignUnassignedComplaints();
            
            // Step 3: Assign unassigned hostel issues
            $hostel_results = $this->assignUnassignedHostelIssues();
            
            // Step 4: Generate system health report
            $health_report = $this->generateHealthReport();
            
            $this->log("Smart Auto-Assignment completed successfully");
            
            return [
                'status' => 'success',
                'validation' => $validation_results,
                'assignments' => $assignment_results,
                'hostel_assignments' => $hostel_results,
                'health_report' => $health_report,
                'logs' => $this->logs
            ];
            
        } catch (Exception $e) {
            $this->log("Error in Smart Auto-Assignment: " . $e->getMessage(), 'ERROR');
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'logs' => $this->logs
            ];
        }
    }
    
    /**
     * Validate all existing assignments for integrity
     */
    private function validateExistingAssignments() {
        $this->log("Validating existing assignments...");
        
        $results = [
            'validated_complaints' => 0,
            'reassigned_complaints' => 0,
            'orphaned_complaints' => 0,
            'validated_hostel_issues' => 0,
            'reassigned_hostel_issues' => 0,
            'orphaned_hostel_issues' => 0
        ];
        
        // Validate complaint assignments
        $stmt = $this->mysqli->prepare("
            SELECT c.id, c.token, c.category, c.technician_id, c.status, c.created_at,
                   u.full_name as tech_name, u.specialization, u.is_online
            FROM complaints c
            LEFT JOIN users u ON c.technician_id = u.id
            WHERE c.technician_id IS NOT NULL 
            AND c.technician_id != 0
            AND c.status IN ('pending', 'in_progress')
        ");
        $stmt->execute();
        $complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($complaints as $complaint) {
            $results['validated_complaints']++;
            
            // Check if technician exists and is valid
            if (!$complaint['tech_name']) {
                // Technician was deleted - reassign
                $this->log("Technician deleted for complaint {$complaint['token']} - reassigning");
                $reassigned = $this->reassignComplaint($complaint['id'], $complaint['category']);
                if ($reassigned) {
                    $results['reassigned_complaints']++;
                } else {
                    $results['orphaned_complaints']++;
                }
            } elseif ($complaint['is_online'] == 0) {
                // Technician is offline - check if we can reassign
                $this->log("Technician {$complaint['tech_name']} is offline for complaint {$complaint['token']}");
                $reassigned = $this->reassignOfflineTechnicianComplaint($complaint['id'], $complaint['category']);
                if ($reassigned) {
                    $results['reassigned_complaints']++;
                }
            } elseif ($complaint['specialization'] !== $complaint['category']) {
                // Technician specialization doesn't match - reassign if possible
                $this->log("Specialization mismatch for complaint {$complaint['token']} - reassigning");
                $reassigned = $this->reassignComplaint($complaint['id'], $complaint['category']);
                if ($reassigned) {
                    $results['reassigned_complaints']++;
                }
            }
        }
        
        // Validate hostel issue assignments
        $stmt = $this->mysqli->prepare("
            SELECT hi.id, hi.issue_type, hi.technician_id, hi.status, hi.created_at,
                   u.full_name as tech_name, u.specialization, u.is_online
            FROM hostel_issues hi
            LEFT JOIN users u ON hi.technician_id = u.id
            WHERE hi.technician_id IS NOT NULL 
            AND hi.technician_id != 0
            AND hi.status IN ('not_assigned', 'in_progress')
        ");
        $stmt->execute();
        $hostel_issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($hostel_issues as $issue) {
            $results['validated_hostel_issues']++;
            
            // Check if technician exists and is valid
            if (!$issue['tech_name']) {
                // Technician was deleted - reassign
                $this->log("Technician deleted for hostel issue {$issue['id']} - reassigning");
                $reassigned = $this->reassignHostelIssue($issue['id'], $issue['issue_type']);
                if ($reassigned) {
                    $results['reassigned_hostel_issues']++;
                } else {
                    $results['orphaned_hostel_issues']++;
                }
            } elseif ($issue['is_online'] == 0) {
                // Technician is offline - check if we can reassign
                $this->log("Technician {$issue['tech_name']} is offline for hostel issue {$issue['id']}");
                $reassigned = $this->reassignOfflineTechnicianHostelIssue($issue['id'], $issue['issue_type']);
                if ($reassigned) {
                    $results['reassigned_hostel_issues']++;
                }
            } elseif ($issue['specialization'] !== $issue['issue_type']) {
                // Technician specialization doesn't match - reassign if possible
                $this->log("Specialization mismatch for hostel issue {$issue['id']} - reassigning");
                $reassigned = $this->reassignHostelIssue($issue['id'], $issue['issue_type']);
                if ($reassigned) {
                    $results['reassigned_hostel_issues']++;
                }
            }
        }
        
        $this->log("Validation completed: {$results['validated_complaints']} complaints, {$results['validated_hostel_issues']} hostel issues validated");
        
        return $results;
    }
    
    /**
     * Assign unassigned complaints to available technicians
     */
    private function assignUnassignedComplaints() {
        $this->log("Assigning unassigned complaints...");
        
        $results = [
            'total_unassigned' => 0,
            'assigned' => 0,
            'failed' => 0,
            'category_breakdown' => []
        ];
        
        // Get all unassigned complaints with priority
        $stmt = $this->mysqli->prepare("
            SELECT id, token, category, room_no, created_at, user_id
            FROM complaints 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('pending', 'in_progress')
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $unassigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $results['total_unassigned'] = count($unassigned);
        
        foreach ($unassigned as $complaint) {
            $priority = $this->calculateComplaintPriority($complaint['id']);
            $assigned = $this->assignComplaintToBestTechnician($complaint['id'], $complaint['category'], $priority);
            
            if ($assigned) {
                $results['assigned']++;
                if (!isset($results['category_breakdown'][$complaint['category']])) {
                    $results['category_breakdown'][$complaint['category']] = 0;
                }
                $results['category_breakdown'][$complaint['category']]++;
                $this->log("Auto-assigned complaint {$complaint['token']} to technician");
            } else {
                $results['failed']++;
                $this->log("Failed to assign complaint {$complaint['token']} - no suitable technician available");
            }
        }
        
        $this->log("Complaint assignment completed: {$results['assigned']} assigned, {$results['failed']} failed");
        
        return $results;
    }
    
    /**
     * Assign unassigned hostel issues
     */
    private function assignUnassignedHostelIssues() {
        $this->log("Assigning unassigned hostel issues...");
        
        $results = [
            'total_unassigned' => 0,
            'assigned' => 0,
            'failed' => 0
        ];
        
        // Get all unassigned hostel issues
        $stmt = $this->mysqli->prepare("
            SELECT id, issue_type, hostel_type, created_at
            FROM hostel_issues 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('not_assigned', 'in_progress')
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $unassigned = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $results['total_unassigned'] = count($unassigned);
        
        foreach ($unassigned as $issue) {
            $assigned = $this->assignHostelIssueToBestTechnician($issue['id'], $issue['issue_type']);
            
            if ($assigned) {
                $results['assigned']++;
                $this->log("Assigned hostel issue {$issue['id']} to technician");
            } else {
                $results['failed']++;
                $this->log("Failed to assign hostel issue {$issue['id']} - no suitable technician available");
            }
        }
        
        $this->log("Hostel issue assignment completed: {$results['assigned']} assigned, {$results['failed']} failed");
        
        return $results;
    }
    
    /**
     * Assign complaint to the best available technician
     */
    private function assignComplaintToBestTechnician($complaint_id, $category, $priority) {
        // Get available technicians for this category
        $stmt = $this->mysqli->prepare("
            SELECT u.id, u.full_name, u.specialization,
                   COUNT(c.id) as current_complaints,
                   COUNT(hi.id) as current_hostel_issues
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
            LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY (current_complaints + current_hostel_issues) ASC, u.id ASC
            LIMIT 1
        ");
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $technician = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Assign the complaint
            $stmt = $this->mysqli->prepare("
                UPDATE complaints 
                SET technician_id = ?, updated_at = NOW() 
                WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)
            ");
            $stmt->bind_param('ii', $technician['id'], $complaint_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                $this->logAutoAssignment($complaint_id, $technician['id'], $category, 'auto_assigned');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Assign hostel issue to the best available technician
     */
    private function assignHostelIssueToBestTechnician($issue_id, $issue_type) {
        // Get available technicians for this issue type
        $stmt = $this->mysqli->prepare("
            SELECT u.id, u.full_name, u.specialization,
                   COUNT(c.id) as current_complaints,
                   COUNT(hi.id) as current_hostel_issues
            FROM users u
            LEFT JOIN complaints c ON u.id = c.technician_id AND c.status IN ('pending', 'in_progress')
            LEFT JOIN hostel_issues hi ON u.id = hi.technician_id AND hi.status IN ('not_assigned', 'in_progress')
            WHERE u.role = 'technician' 
            AND u.specialization = ?
            AND u.is_online = 1
            GROUP BY u.id, u.full_name, u.specialization
            ORDER BY (current_complaints + current_hostel_issues) ASC, u.id ASC
            LIMIT 1
        ");
        $stmt->bind_param('s', $issue_type);
        $stmt->execute();
        $technician = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($technician) {
            // Assign the hostel issue
            $stmt = $this->mysqli->prepare("
                UPDATE hostel_issues 
                SET technician_id = ?, updated_at = NOW() 
                WHERE id = ? AND (technician_id IS NULL OR technician_id = 0)
            ");
            $stmt->bind_param('ii', $technician['id'], $issue_id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                $this->logAutoAssignment($issue_id, $technician['id'], $issue_type, 'auto_assigned');
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Reassign complaint when technician is invalid
     */
    private function reassignComplaint($complaint_id, $category) {
        return $this->assignComplaintToBestTechnician($complaint_id, $category, 0);
    }
    
    /**
     * Reassign hostel issue when technician is invalid
     */
    private function reassignHostelIssue($issue_id, $issue_type) {
        return $this->assignHostelIssueToBestTechnician($issue_id, $issue_type);
    }
    
    /**
     * Reassign complaint when technician goes offline
     */
    private function reassignOfflineTechnicianComplaint($complaint_id, $category) {
        // Only reassign if there are other online technicians available
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as available_count
            FROM users 
            WHERE role = 'technician' 
            AND specialization = ?
            AND is_online = 1
        ");
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['available_count'] > 0) {
            return $this->assignComplaintToBestTechnician($complaint_id, $category, 0);
        }
        
        return false; // Keep with offline technician if no alternatives
    }
    
    /**
     * Reassign hostel issue when technician goes offline
     */
    private function reassignOfflineTechnicianHostelIssue($issue_id, $issue_type) {
        // Only reassign if there are other online technicians available
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as available_count
            FROM users 
            WHERE role = 'technician' 
            AND specialization = ?
            AND is_online = 1
        ");
        $stmt->bind_param('s', $issue_type);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['available_count'] > 0) {
            return $this->assignHostelIssueToBestTechnician($issue_id, $issue_type);
        }
        
        return false; // Keep with offline technician if no alternatives
    }
    
    /**
     * Calculate complaint priority
     */
    private function calculateComplaintPriority($complaint_id) {
        $stmt = $this->mysqli->prepare("
            SELECT 
                c.category,
                c.created_at,
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
        
        if (!$complaint) return 0;
        
        $priority = 0;
        
        // Age-based priority
        $hours_old = $complaint['hours_old'];
        if ($hours_old > 72) $priority += 50;
        elseif ($hours_old > 48) $priority += 30;
        elseif ($hours_old > 24) $priority += 20;
        elseif ($hours_old > 12) $priority += 10;
        
        // Category priority
        $category_priorities = [
            'electrician' => 25, 'plumber' => 20, 'wifi' => 15,
            'ac' => 10, 'mess' => 8, 'housekeeping' => 5,
            'carpenter' => 5, 'laundry' => 3
        ];
        $priority += $category_priorities[$complaint['category']] ?? 5;
        
        // User role priority
        $role_priorities = [
            'faculty' => 15, 'nonteaching' => 10, 'student' => 5
        ];
        $priority += $role_priorities[$complaint['user_role']] ?? 5;
        
        // Emergency keywords
        $emergency_keywords = ['urgent', 'emergency', 'broken', 'not working', 'critical', 'immediate'];
        $description_lower = strtolower($complaint['description']);
        foreach ($emergency_keywords as $keyword) {
            if (strpos($description_lower, $keyword) !== false) {
                $priority += 8;
                break;
            }
        }
        
        return $priority;
    }
    
    /**
     * Log auto-assignment
     */
    private function logAutoAssignment($item_id, $technician_id, $category, $type) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO security_logs (admin_id, action, target, details, created_at) 
            VALUES (?, 'auto_assignment', ?, ?, NOW())
        ");
        
        $admin_id = $_SESSION['user_id'] ?? null;
        $target = $type === 'complaint' ? "complaint_$item_id" : "hostel_issue_$item_id";
        $details = json_encode([
            'type' => $type,
            'item_id' => $item_id,
            'technician_id' => $technician_id,
            'category' => $category,
            'assignment_method' => 'smart_auto_assignment'
        ]);
        
        $stmt->bind_param('iss', $admin_id, $target, $details);
        $stmt->execute();
        $stmt->close();
        
        // Mark as auto-assigned in the database
        if ($type === 'complaint') {
            $stmt = $this->mysqli->prepare("
                UPDATE complaints 
                SET assignment_type = 'auto_assigned', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $this->mysqli->prepare("
                UPDATE hostel_issues 
                SET assignment_type = 'auto_assigned', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param('i', $item_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    /**
     * Generate system health report
     */
    private function generateHealthReport() {
        // Get system statistics
        $stmt = $this->mysqli->prepare("
            SELECT 
                COUNT(*) as total_technicians,
                SUM(is_online) as online_technicians
            FROM users 
            WHERE role = 'technician'
        ");
        $stmt->execute();
        $tech_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as unassigned_complaints
            FROM complaints 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('pending', 'in_progress')
        ");
        $stmt->execute();
        $complaint_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) as unassigned_hostel_issues
            FROM hostel_issues 
            WHERE (technician_id IS NULL OR technician_id = 0)
            AND status IN ('not_assigned', 'in_progress')
        ");
        $stmt->execute();
        $hostel_stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Calculate health score
        $health_score = 100;
        $health_score -= ($complaint_stats['unassigned_complaints'] * 2);
        $health_score -= ($hostel_stats['unassigned_hostel_issues'] * 2);
        
        if ($tech_stats['total_technicians'] > 0) {
            $online_percentage = ($tech_stats['online_technicians'] / $tech_stats['total_technicians']) * 100;
            if ($online_percentage < 50) {
                $health_score -= (50 - $online_percentage);
            }
        }
        
        $health_score = max(0, $health_score);
        
        return [
            'system_health_score' => $health_score,
            'total_technicians' => $tech_stats['total_technicians'],
            'online_technicians' => $tech_stats['online_technicians'],
            'unassigned_complaints' => $complaint_stats['unassigned_complaints'],
            'unassigned_hostel_issues' => $hostel_stats['unassigned_hostel_issues'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Log messages (production mode - minimal logging)
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message";
        $this->logs[] = $log_entry;
        
        // Only log errors to file in production
        if ($level === 'ERROR') {
            $log_file = __DIR__ . '/../logs/smart_auto_assignment.log';
            if (!is_dir(dirname($log_file))) {
                mkdir(dirname($log_file), 0755, true);
            }
            file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}

// AJAX endpoint for auto-assignment
if (isset($_POST['action']) && $_POST['action'] === 'run_smart_auto_assignment') {
    header('Content-Type: application/json');
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $engine = new SmartAutoAssignmentEngine($mysqli);
    $result = $engine->runSmartAutoAssignment();
    
    echo json_encode($result);
    exit;
}

// Prevent direct access to this file
if (!defined('AJAX_REQUEST') && !isset($_POST['action'])) {
    http_response_code(403);
    die('Direct access not allowed');
}
?> 