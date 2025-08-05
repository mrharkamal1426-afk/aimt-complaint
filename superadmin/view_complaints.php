<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

// Handle cleanup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_action'])) {
    $cleanup_type = $_POST['cleanup_type'];
    $cleanup_status = $_POST['cleanup_status'] ?? '';
    $cleanup_category = $_POST['cleanup_category'] ?? '';
    $cleanup_date_from = $_POST['cleanup_date_from'] ?? '';
    $cleanup_date_to = $_POST['cleanup_date_to'] ?? '';
    $cleanup_older_than = $_POST['cleanup_older_than'] ?? '';
    
    $deleted_count = 0;
    $error_message = '';
    
    try {
        // Build cleanup query based on type
        switch ($cleanup_type) {
            case 'by_status':
                if (empty($cleanup_status)) {
                    throw new Exception('Please select a status to clean up.');
                }
                $query = "DELETE FROM complaints WHERE status = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('s', $cleanup_status);
                break;
                
            case 'by_category':
                if (empty($cleanup_category)) {
                    throw new Exception('Please select a category to clean up.');
                }
                $query = "DELETE FROM complaints WHERE category = ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('s', $cleanup_category);
                break;
                
            case 'by_date_range':
                if (empty($cleanup_date_from) || empty($cleanup_date_to)) {
                    throw new Exception('Please select both start and end dates.');
                }
                $query = "DELETE FROM complaints WHERE created_at BETWEEN ? AND ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('ss', $cleanup_date_from, $cleanup_date_to);
                break;
                
            case 'older_than':
                if (empty($cleanup_older_than)) {
                    throw new Exception('Please select how old the complaints should be.');
                }
                $date_limit = date('Y-m-d', strtotime("-{$cleanup_older_than} days"));
                $query = "DELETE FROM complaints WHERE created_at < ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('s', $date_limit);
                break;
                
            case 'resolved_old':
                $date_limit = date('Y-m-d', strtotime('-90 days'));
                $query = "DELETE FROM complaints WHERE status = 'resolved' AND created_at < ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('s', $date_limit);
                break;
                
            case 'all_old':
                $date_limit = date('Y-m-d', strtotime('-365 days'));
                $query = "DELETE FROM complaints WHERE created_at < ?";
                $stmt = $mysqli->prepare($query);
                $stmt->bind_param('s', $date_limit);
                break;
                
            default:
                throw new Exception('Invalid cleanup type.');
        }
        
        $stmt->execute();
        $deleted_count = $stmt->affected_rows;
        $stmt->close();
        
        $_SESSION['success_message'] = "Successfully deleted {$deleted_count} complaints from the database.";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header('Location: view_complaints.php?' . http_build_query($_GET));
    exit();
}

$categories = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','infrastructure','other'];
$roles = ['student','faculty','nonteaching','technician'];

$complaints = [];
$filter = [
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'category' => $_GET['category'] ?? '',
    'status' => $_GET['status'] ?? 'pending',
    'role' => $_GET['role'] ?? '',
    'sort' => $_GET['sort'] ?? 'newest'
];

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50; // Show 50 complaints per page
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM complaints c 
                JOIN users u ON u.id = c.user_id 
                LEFT JOIN users t ON t.id = c.technician_id 
                WHERE 1=1";
$count_params = [];
$count_types = '';

if ($filter['date_from']) {
    $count_query .= " AND c.created_at >= ?";
    $count_params[] = $filter['date_from'];
    $count_types .= 's';
}
if ($filter['date_to']) {
    $count_query .= " AND c.created_at <= ?";
    $count_params[] = $filter['date_to'];
    $count_types .= 's';
}
if ($filter['category']) {
    $count_query .= " AND c.category = ?";
    $count_params[] = $filter['category'];
    $count_types .= 's';
}
if ($filter['status']) {
    $count_query .= " AND c.status = ?";
    $count_params[] = $filter['status'];
    $count_types .= 's';
}
if ($filter['role']) {
    $count_query .= " AND u.role = ?";
    $count_params[] = $filter['role'];
    $count_types .= 's';
}

$count_stmt = $mysqli->prepare($count_query);
if ($count_params) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_complaints = $total_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_complaints / $per_page);

// Handle AJAX requests for infinite scroll
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => true,
        'complaints' => $complaints,
        'hasMore' => $page < $total_pages,
        'totalLoaded' => $offset + count($complaints),
        'totalComplaints' => $total_complaints
    ];
    
    echo json_encode($response);
    exit;
}

// Main query with pagination
$query = "SELECT c.token, u.full_name AS user_name, u.role AS user_role, c.category, c.status, t.full_name AS tech_name, c.created_at, c.updated_at 
          FROM complaints c 
          JOIN users u ON u.id = c.user_id 
          LEFT JOIN users t ON t.id = c.technician_id 
          WHERE 1=1";
$params = [];
$types = '';

if ($filter['date_from']) {
    $query .= " AND c.created_at >= ?";
    $params[] = $filter['date_from'];
    $types .= 's';
}
if ($filter['date_to']) {
    $query .= " AND c.created_at <= ?";
    $params[] = $filter['date_to'];
    $types .= 's';
}
if ($filter['category']) {
    $query .= " AND c.category = ?";
    $params[] = $filter['category'];
    $types .= 's';
}
if ($filter['status']) {
    $query .= " AND c.status = ?";
    $params[] = $filter['status'];
    $types .= 's';
}
if ($filter['role']) {
    $query .= " AND u.role = ?";
    $params[] = $filter['role'];
    $types .= 's';
}

$query .= " ORDER BY c.created_at " . ($filter['sort'] === 'oldest' ? 'ASC' : 'DESC');
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();

// Get statistics for cleanup options
$stats_query = "SELECT 
    COUNT(*) as total_complaints,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count,
    SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 ELSE 0 END) as older_than_90_days,
    SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 180 DAY) THEN 1 ELSE 0 END) as older_than_180_days,
    SUM(CASE WHEN created_at < DATE_SUB(NOW(), INTERVAL 365 DAY) THEN 1 ELSE 0 END) as older_than_365_days
FROM complaints";
$stats_result = $mysqli->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMT - View Complaints</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .glassmorphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
        }

        .loading.active {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e5e7eb;
            border-radius: 50%;
            border-top-color: #10b981;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .sidebar-link {
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .gradient-border {
            position: relative;
            border-radius: 16px;
            background: linear-gradient(to right, #0f172a, #1e293b);
        }

        .gradient-border::after {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(60deg, #10b981, #0ea5e9);
            border-radius: 17px;
            z-index: -1;
            opacity: 0.3;
        }

        .form-input {
            @apply w-full border border-slate-200 rounded-xl px-4 py-2.5 focus:outline-none focus:border-emerald-500 transition-colors;
        }

        .form-label {
            @apply block font-medium text-slate-700 mb-1.5;
        }

        /* Enhanced header styles */
        .header-gradient {
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 50%, #ecfdf5 100%);
        }

        .title-gradient {
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .icon-container {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.1), 0 10px 10px -5px rgba(16, 185, 129, 0.04);
        }

        .stats-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        /* Clean table styling */
        .complaints-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }

        .complaints-table th {
            background: #f8fafc;
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .complaints-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .complaints-table tbody tr {
            transition: all 0.2s ease;
        }

        .complaints-table tbody tr:hover {
            background: #f8fafc;
        }

        .complaints-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-in-progress {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-resolved {
            background: #d1fae5;
            color: #059669;
        }

        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Long pending styling (14+ days) */
        .long-pending {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
        }

        .long-pending:hover {
            background: #fecaca;
        }

        .long-pending .status-badge {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Category badges */
        .category-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            background: #f1f5f9;
            color: #475569;
        }

        /* Age indicator */
        .age-indicator {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .age-normal {
            background: #f1f5f9;
            color: #64748b;
        }

        .age-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .age-critical {
            background: #fee2e2;
            color: #dc2626;
        }

        /* User info */
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
        }

        .user-role {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: capitalize;
        }

        /* Technician info */
        .technician-info {
            color: #475569;
            font-size: 0.875rem;
        }

        .technician-unassigned {
            color: #94a3b8;
            font-style: italic;
        }

        /* Date formatting */
        .date-info {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Action button */
        .action-link {
            color: #059669;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .action-link:hover {
            color: #047857;
        }

        /* Table container with expand functionality */
        .table-container {
            position: relative;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .table-container.expanded {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 9999 !important;
            border-radius: 0 !important;
            background: #000000 !important;
            width: 100vw !important;
            height: 100vh !important;
            margin: 0 !important;
            padding: 20px !important;
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8) !important;
        }

        .table-container.expanded .table-wrapper {
            max-height: calc(100vh - 140px) !important;
            height: calc(100vh - 140px) !important;
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
            border: 3px solid #059669 !important;
        }

        .table-container.expanded .table-header {
            background: linear-gradient(135deg, #059669, #047857) !important;
            color: white !important;
        }

        .table-container.expanded .table-header h2 {
            color: white !important;
        }

        .table-container.expanded .table-header p {
            color: #e0f2fe !important;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .table-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
        }

        .table-header p {
            color: #64748b;
            margin: 4px 0 0 0;
            font-size: 0.875rem;
        }

        .table-controls {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .expand-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #059669;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .expand-btn:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .expand-btn.expanded {
            background: #dc2626;
        }

        .expand-btn.expanded:hover {
            background: #b91c1c;
        }

        .pagination-info {
            
            font-size: 0.875rem;
        }

        /* Infinite scroll loading */
        .loading-more {
            display: none;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: #f8fafc;
        }

        .loading-more.active {
            display: flex;
        }

        .loading-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid #e2e8f0;
            border-radius: 50%;
            border-top-color: #059669;
            animation: spin 1s linear infinite;
        }



        /* Scroll to top button */
        .scroll-top-btn {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #059669, #047857);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(5, 150, 105, 0.3);
            transition: all 0.3s ease;
            z-index: 1001;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .scroll-top-btn:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 35px rgba(5, 150, 105, 0.4);
        }

        .scroll-top-btn.visible {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .scroll-top-btn img {
            width: 24px;
            height: 24px;
            filter: brightness(0) invert(1);
        }

        /* Table wrapper for infinite scroll */
        .table-wrapper {
            max-height: 600px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
            border-radius: 0 0 12px 12px;
        }

        .table-wrapper::-webkit-scrollbar {
            width: 8px;
        }

        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .table-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Enhanced table scrolling */
        .complaints-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
        }

        /* Sticky header for better UX */
        .complaints-table thead th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            z-index: 10;
        }

        /* Expanded table height */
        .table-container.expanded .table-wrapper {
            max-height: calc(100vh - 120px);
        }

        /* Premium Shimmer Effect for Title - Inside Text Fill */
        .shimmer-text {
            position: relative;
            background: linear-gradient(90deg, #ffffff, #10b981, #34d399, #6ee7b7, #a7f3d0, #ffffff);
            background-size: 300% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer-fill 4s ease-in-out infinite;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .shimmer-text::before {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #ffffff, #10b981, #34d399, #6ee7b7, #a7f3d0, #ffffff);
            background-size: 300% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer-fill 4s ease-in-out infinite;
            filter: blur(0.5px);
        }

        .shimmer-text::after {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
            background-size: 200% 100%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer-highlight 3s ease-in-out infinite;
        }

        @keyframes shimmer-fill {
            0%, 100% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes shimmer-highlight {
            0% {
                background-position: -200% 0;
            }
            100% {
                background-position: 200% 0;
            }
        }

        .shimmer-text:hover {
            animation: shimmer-fill 2s ease-in-out infinite;
        }

        .shimmer-text:hover::before {
            animation: shimmer-fill 2s ease-in-out infinite;
        }

        .shimmer-text:hover::after {
            animation: shimmer-highlight 1.5s ease-in-out infinite;
        }

        /* Professional text sweep animation */
        .sweep-animation {
            position: relative;
            background: linear-gradient(135deg, #1e293b 0%, #475569 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: inline-block;
        }

        .sweep-animation::before {
            content: attr(data-text);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, #ef4444, transparent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textSweep 4s ease-in-out infinite;
            clip-path: polygon(0 0, 0 100%, 0 100%, 0 0);
        }

        @keyframes textSweep {
            0% {
                clip-path: polygon(0 0, 0 100%, 0 100%, 0 0);
            }
            25% {
                clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
            }
            75% {
                clip-path: polygon(100% 0, 100% 100%, 100% 100%, 100% 0);
            }
            100% {
                clip-path: polygon(100% 0, 100% 100%, 100% 100%, 100% 0);
            }
        }

        /* Subtle underline effect */
        .sweep-animation::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ef4444, transparent);
            animation: underlineSweep 4s ease-in-out infinite;
        }

        @keyframes underlineSweep {
            0%, 100% {
                transform: scaleX(0);
                opacity: 0;
            }
            25%, 75% {
                transform: scaleX(1);
                opacity: 1;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(.4,0,.2,1);
                position: fixed;
                z-index: 50;
                left: 0;
                top: 0;
                width: 80vw;
                max-width: 320px;
                height: 100vh;
                box-shadow: 2px 0 16px rgba(0,0,0,0.12);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            #sidebar-overlay.active {
                display: block;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0.75rem !important;
                padding-top: 5rem !important; /* Add space for hamburger menu */
                width: 100vw !important;
                max-width: 100vw !important;
            }

            /* Improved hamburger menu positioning */
            #menu-toggle {
                top: 1rem !important;
                left: 1rem !important;
                width: 48px !important;
                height: 48px !important;
                z-index: 60 !important;
                transition: all 0.3s ease !important;
            }

            /* Hide hamburger menu when sidebar is active */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }



            /* Mobile filter improvements */
            .filter-form {
                padding: 1rem !important;
            }

            .filter-grid {
                gap: 1rem !important;
                flex-direction: column;
            }

            .filter-grid > div {
                min-width: auto;
                width: 100%;
            }

            .filter-grid .form-input,
            .filter-grid select {
                width: 100%;
                padding: 0.75rem;
            }

            /* Mobile header improvements */
            .mobile-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .mobile-header h1 {
                font-size: 2rem !important;
            }

            .mobile-header .flex.gap-6 {
                flex-direction: column;
                gap: 1rem;
            }

            .mobile-header .flex.gap-6 > div {
                width: 100%;
            }

            /* Mobile stats cards */
            .stats-card {
                padding: 1rem !important;
                margin-bottom: 0.75rem;
            }

            .stats-card .text-2xl {
                font-size: 1.5rem !important;
            }

            /* Mobile action buttons */
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }

            .action-buttons button {
                width: 100%;
            }

            /* Mobile stats cards */
            .stats-card {
                padding: 1rem !important;
                margin-bottom: 0.75rem;
            }

            .stats-card .text-2xl {
                font-size: 1.5rem !important;
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .main-content {
                padding: 0.5rem !important;
                padding-top: 4.5rem !important;
            }

            .mobile-header h1 {
                font-size: 1.5rem !important;
            }

            .filter-form {
                padding: 0.75rem !important;
            }

            .stats-card {
                padding: 0.75rem !important;
            }

            .stats-card .text-2xl {
                font-size: 1.25rem !important;
            }

            /* Adjust hamburger menu for very small screens */
            #menu-toggle {
                top: 0.75rem !important;
                left: 0.75rem !important;
                width: 44px !important;
                height: 44px !important;
            }

            /* Hide hamburger menu when sidebar is active on small screens */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
        }

        /* Mobile responsive table */
        @media (max-width: 768px) {
        .complaints-table {
            display: block;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .complaints-table th,
        .complaints-table td {
                padding: 12px 16px;
                font-size: 0.875rem;
                white-space: nowrap;
            }

            .user-info {
                min-width: 120px;
            }

            .category-badge,
            .status-badge,
            .age-indicator {
                font-size: 0.7rem;
                padding: 4px 8px;
            }
        }

        @media (max-width: 640px) {
            .complaints-table th,
            .complaints-table td {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .user-name {
                font-size: 0.875rem;
            }

            .user-role {
                font-size: 0.7rem;
            }

            .table-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .table-controls {
                width: 100%;
                justify-content: space-between;
            }

            .expand-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }

            .expand-btn span {
                display: none;
            }
        }
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 80vw;
            max-width: 320px;
            height: 100vh;
            box-shadow: 2px 0 16px rgba(0,0,0,0.12);
            background: #0f172a;
        }
        .sidebar.active {
            transform: translateX(0);
            display: flex !important;
        }
        #sidebar-overlay.active {
            display: block;
        }
        .sidebar.hidden {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
            padding: 1rem !important;
            width: 100vw !important;
            max-width: 100vw !important;
        }
        @media (min-width: 769px) {
            .sidebar {
                width: 320px;
            }
        }

        /* Responsive header margin to avoid overlap with hamburger */
        .header-offset {
            margin-left: 0;
        }
        
        /* Ensure header content doesn't overlap with hamburger menu */
        .main-content header {
            position: relative;
            z-index: 10;
        }
        
        /* Hamburger menu positioning improvements */
        #menu-toggle {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 60;
            transition: all 0.3s ease;
        }
        
        /* Sidebar positioning improvements */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 50;
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        /* Ensure main content doesn't shift when sidebar is open */
        .main-content {
            margin-left: 0;
            width: 100%;
            transition: margin-left 0.3s ease;
        }
        
        /* Desktop sidebar behavior */
        @media (min-width: 769px) {
            .sidebar {
                width: 320px;
            }
            
            /* When sidebar is active on desktop, adjust main content */
            .sidebar.active + .main-content {
                margin-left: 320px;
            }
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 80vw;
                max-width: 320px;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100vw !important;
            }
            
            /* Ensure hamburger menu is always visible on mobile */
            #menu-toggle {
                top: 1rem !important;
                left: 1rem !important;
                width: 48px !important;
                height: 48px !important;
            }
            
            /* Mobile header adjustments */
            .main-content header .flex.justify-between {
                flex-direction: column;
                gap: 1.5rem;
                align-items: flex-start;
            }
            
            .main-content header .flex.justify-between > div:last-child {
                width: 100%;
                justify-content: space-between;
            }
            
            /* Mobile KPI cards */
            .main-content header .flex.justify-between > div:last-child .flex {
                gap: 0.75rem;
            }
            
            .main-content header .flex.justify-between > div:last-child .flex > div {
                flex: 1;
                min-width: 0;
            }
            
            .main-content header .flex.justify-between > div:last-child .flex > div .text-2xl {
                font-size: 1.25rem;
            }
            
            .main-content header .flex.justify-between > div:last-child .flex > div .text-sm {
                font-size: 0.75rem;
            }
        }
        
        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .main-content header .flex.justify-between > div:last-child .flex {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .main-content header .flex.justify-between > div:last-child .flex > div {
                width: 100%;
            }
            
            /* Mobile shimmer text adjustments */
            .shimmer-text {
                font-size: 1.75rem;
                animation: shimmer-fill 4s ease-in-out infinite;
            }
            
            .shimmer-text::before {
                animation: shimmer-fill 4s ease-in-out infinite;
            }
            
            .shimmer-text::after {
                animation: shimmer-highlight 3s ease-in-out infinite;
            }
            
            .shimmer-text:hover {
                animation: shimmer-fill 3s ease-in-out infinite;
            }
        }
        
        /* Tablet adjustments for shimmer */
        @media (max-width: 768px) {
            .shimmer-text {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="bg-slate-50">
    <div id="loading" class="loading">
        <div class="spinner"></div>
    </div>


    <!-- Sidebar Overlay (always available) -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden"></div>
    <div class="flex min-h-screen">
        <!-- Sidebar (hidden by default, overlays content) -->
        <aside class="sidebar w-64 bg-slate-900 text-white flex flex-col fixed h-full shadow-xl z-50 hidden" tabindex="-1">
            <div class="px-6 py-4 gradient-border flex items-center gap-4">
                <div class="bg-white p-1.5 rounded-lg">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10 logo-glow">
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight">Complaint<span class="text-emerald-400">System</span></h1>
                    <div class="text-xs text-slate-400">AIMT Portal</div>
                </div>
            </div>
            <div class="px-6 py-4 border-b border-slate-700/50">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin') ?></div>
                <div class="text-emerald-400 text-sm">Superadmin</div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-3 overflow-y-auto">
                <!-- Primary Navigation -->
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-layout-dashboard mr-3 text-slate-400"></i>
                    <span>Dashboard</span>
                </a>
                
                <!-- Complaint Management (High Priority) -->
                <div class="px-2 py-1">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Complaint Management</div>
                </div>
                <a href="view_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl bg-slate-800/50">
                    <i class="lucide-database mr-3 text-emerald-400"></i>
                    <span>View All Complaints</span>
                </a>
                <a href="submit_complaint.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-plus-circle mr-3 text-slate-400"></i>
                    <span>Submit Complaint</span>
                </a>
                <a href="my_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-list-checks mr-3 text-slate-400"></i>
                    <span>My Complaints</span>
                </a>
                
                <!-- User & Admin Management (High Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">User Management</div>
                </div>
                <a href="user_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-users mr-3 text-slate-400"></i>
                    <span>User Management</span>
                </a>
                <a href="admin_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-shield mr-3 text-slate-400"></i>
                    <span>Admin Management</span>
                </a>
                
                <!-- System Operations (Medium Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">System Operations</div>
                </div>
                <a href="auto_assignment.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-zap mr-3 text-slate-400"></i>
                    <span>Auto Assignment</span>
                </a>
                <a href="technician_status.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-toggle-left mr-3 text-slate-400"></i>
                    <span>Technician Status</span>
                </a>
                <a href="register_codes.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-key mr-3 text-slate-400"></i>
                    <span>Generate Codes</span>
                </a>
                
                <!-- Reports & Analytics (Medium Priority) -->
                <div class="px-2 py-1 mt-4">
                    <div class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Reports & Analytics</div>
                </div>
                <a href="reports.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-bar-chart mr-3 text-slate-400"></i>
                    <span>Reports</span>
                </a>
                <a href="manage_suggestions.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-lightbulb mr-3 text-slate-400"></i>
                    <span>Manage Suggestions</span>
                </a>
                
                <!-- Account (Low Priority) -->
                <div class="mt-auto pt-4">
                    <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                        <i class="lucide-log-out mr-3 text-slate-400"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        <!-- Main Content (always full width) -->
        <div class="main-content flex-1 flex flex-col w-full">
            <!-- Header -->
            <header class="bg-gradient-to-r from-emerald-900 to-emerald-800 shadow-lg relative">
                <!-- Menu Button (positioned in header) -->
                <button id="menu-toggle"
                    class="absolute top-6 left-6 z-50 flex items-center justify-center w-12 h-12 rounded-xl bg-white/95 shadow-lg border border-white/30 hover:bg-white hover:shadow-xl focus:bg-white transition-all duration-200 outline-none ring-2 ring-emerald-400/50 ring-offset-2 ring-offset-emerald-900 focus:ring-4 focus:ring-emerald-400/70 backdrop-blur-sm"
                    aria-label="Toggle navigation menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="block">
                        <line x1="4" y1="6" x2="20" y2="6" />
                        <line x1="4" y1="12" x2="20" y2="12" />
                        <line x1="4" y1="18" x2="20" y2="18" />
                    </svg>
                </button>
                
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center py-8 pl-20">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 bg-emerald-500/20 rounded-xl border border-emerald-400/30 backdrop-blur-sm">
                                <svg class="text-emerald-400 w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold shimmer-text" data-text="View All Complaints">View All Complaints</h1>
                                <p class="text-emerald-200 text-sm mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Comprehensive complaint management and filtering system
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <!-- Enhanced Statistics Cards -->
                            <div class="bg-gradient-to-br from-white/15 to-white/5 backdrop-blur-md border border-white/25 rounded-2xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white mb-1"><?= number_format($stats['total_complaints'] ?? 0) ?></div>
                                    <div class="text-emerald-200 text-sm font-medium">Total Complaints</div>
                                    <div class="w-8 h-1 bg-emerald-400 rounded-full mx-auto mt-2"></div>
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-white/15 to-white/5 backdrop-blur-md border border-white/25 rounded-2xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white mb-1"><?= number_format($stats['pending_count'] ?? 0) ?></div>
                                    <div class="text-emerald-200 text-sm font-medium">Pending</div>
                                    <div class="w-8 h-1 bg-yellow-400 rounded-full mx-auto mt-2"></div>
                                </div>
                            </div>
                            <div class="bg-gradient-to-br from-white/15 to-white/5 backdrop-blur-md border border-white/25 rounded-2xl px-6 py-4 shadow-lg hover:shadow-xl transition-all duration-300 hover:scale-105">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-white mb-1"><?= number_format($stats['resolved_count'] ?? 0) ?></div>
                                    <div class="text-emerald-200 text-sm font-medium">Resolved</div>
                                    <div class="w-8 h-1 bg-green-400 rounded-full mx-auto mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Last Updated Section - Positioned below KPI boxes -->
                    <div class="flex justify-end pb-6">
                        <div class="flex items-center space-x-3 bg-gradient-to-r from-white/15 to-white/5 backdrop-blur-md border border-white/25 rounded-xl px-5 py-3 shadow-lg">
                            <div class="text-right">
                                <p class="text-emerald-200 text-xs font-medium uppercase tracking-wider">Last Updated</p>
                                <p class="text-white text-sm font-semibold"><?= date('M d, Y H:i') ?></p>
                            </div>
                            <div class="p-2 bg-white/10 rounded-lg border border-white/20">
                                <svg class="text-white w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Search and Filter Section -->
            <div class="mb-8 px-4 sm:px-6 md:px-8">
                <form method="get" autocomplete="off">
                    <div class="bg-white rounded-lg shadow-sm border border-slate-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 filter-grid">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($filter['date_from']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Date To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($filter['date_to']) ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                            <select name="category" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= ($filter['category']==$cat)?'selected':'' ?>><?= ucfirst($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Status</label>
                            <select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">All Status</option>
                                <option value="pending" <?= ($filter['status']=='pending')?'selected':'' ?>>Pending</option>
                                <option value="in_progress" <?= ($filter['status']=='in_progress')?'selected':'' ?>>In Progress</option>
                                <option value="resolved" <?= ($filter['status']=='resolved')?'selected':'' ?>>Resolved</option>
                                <option value="rejected" <?= ($filter['status']=='rejected')?'selected':'' ?>>Rejected</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">User Role</label>
                            <select name="role" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r ?>" <?= ($filter['role']==$r)?'selected':'' ?>><?= ucfirst($r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Sort By</label>
                            <select name="sort" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="newest" <?= ($filter['sort']=='newest')?'selected':'' ?>>Newest First</option>
                                <option value="oldest" <?= ($filter['sort']=='oldest')?'selected':'' ?>>Oldest First</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <a href="view_complaints.php" class="px-6 py-2 rounded-lg bg-slate-200 text-slate-700 font-semibold hover:bg-slate-300 transition-colors">Reset</a>
                        <button type="submit" class="px-6 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors">Search</button>
                    </div>
                </div>
                </form>
            </div>

            <!-- Success/Error Messages -->
            <div class="px-4 sm:px-6 md:px-8">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg shadow">
                    <div class="flex items-center gap-2">
                        <i class="lucide-check-circle h-5 w-5"></i>
                        <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
                            <?php endif; ?>
            </div>

            <!-- Complaints Table -->
            <div class="px-4 sm:px-6 md:px-8">
                <?php if ($complaints): ?>
                <div class="table-container" id="tableContainer">
                    <div class="table-header">
                        <div>
                            <h2>Complaints List</h2>
                            <p>Showing <?= number_format($offset + 1) ?> - <?= number_format(min($offset + $per_page, $total_complaints)) ?> of <?= number_format($total_complaints) ?> complaints</p>
                    </div>
                        <div class="table-controls">
                            <div class="pagination-info" ">
                                Showing <?= number_format($total_complaints) ?> complaints • Scroll to load more
                            </div>
                            <!-- Expand/Collapse Button -->
<button 
    id="expandBtn"
    title="Expand table for better viewing"
    class="flex items-center justify-center gap-2 px-5 py-2 w-48 bg-green-50 border border-green-300 text-green-700 font-medium rounded-lg shadow-sm hover:bg-green-100 transition-all duration-300 text-center"
    onclick="toggleExpand(this)"
>
    <i id="expandIcon" class="lucide-maximize-2 w-5 h-5"></i>
    <span id="expandText" class="whitespace-nowrap">Expand Table</span>
</button>

<!-- Script for toggle behavior -->
<script>
    function toggleExpand(button) {
        const icon = document.getElementById('expandIcon');
        const text = document.getElementById('expandText');

        const isExpanded = button.classList.contains('bg-red-50');

        if (!isExpanded) {
            // Collapse mode (Red)
            button.classList.remove('bg-green-50', 'border-green-300', 'text-green-700', 'hover:bg-green-100');
            button.classList.add('bg-red-50', 'border-red-300', 'text-red-700', 'hover:bg-red-100');
            icon.classList.replace('lucide-maximize-2', 'lucide-minimize-2');
            text.textContent = "Collapse Table";
        } else {
            // Expand mode (Green)
            button.classList.remove('bg-red-50', 'border-red-300', 'text-red-700', 'hover:bg-red-100');
            button.classList.add('bg-green-50', 'border-green-300', 'text-green-700', 'hover:bg-green-100');
            icon.classList.replace('lucide-minimize-2', 'lucide-maximize-2');
            text.textContent = "Expand Table";
        }
    }
</script>

                        </div>
                    </div>
                    <div class="table-wrapper" id="tableWrapper">
                        <table class="w-full complaints-table" id="complaintsTable">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Technician</th>
                                    <th>Created</th>
                                    <th>Age</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complaints as $c): ?>
                                    <?php
                                        $created_time = strtotime($c['created_at']);
                                        $days_old = floor((time() - $created_time) / 86400);
                                        $is_long_pending = in_array($c['status'], ['pending', 'in_progress']) && ($days_old >= 14);
                                        
                                        // Determine age indicator class
                                        $age_class = 'age-normal';
                                        if ($is_long_pending) {
                                            $age_class = 'age-critical';
                                        } elseif ($days_old >= 7) {
                                            $age_class = 'age-warning';
                                        }
                                    ?>
                                    <tr class="<?= $is_long_pending ? 'long-pending' : '' ?>">
                                        <td>
                                            <div class="user-info">
                                                <div class="user-name"><?= htmlspecialchars($c['user_name']) ?></div>
                                                <div class="user-role"><?= htmlspecialchars($c['user_role']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="category-badge"><?= htmlspecialchars($c['category']) ?></span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= str_replace('_', '-', $c['status']) ?>">
                                                    <?= ucfirst(htmlspecialchars($c['status'])) ?>
                                                </span>
                                        </td>
                                        <td>
                                            <div class="technician-info <?= empty($c['tech_name']) ? 'technician-unassigned' : '' ?>">
                                            <?= htmlspecialchars($c['tech_name'] ?? 'Not assigned') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="date-info"><?= date('M j, Y', strtotime($c['created_at'])) ?></div>
                                        </td>
                                        <td>
                                            <span class="age-indicator <?= $age_class ?>">
                                                <?= $days_old ?> days
                                            </span>
                                        </td>
                                        <td>
                                            <a href="complaint_details.php?token=<?= urlencode($c['token']) ?>" class="action-link">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Loading indicator for infinite scroll -->
                    <div class="loading-more" id="loadingMore">
                        <div class="loading-spinner"></div>
                        <span class="ml-3 text-slate-600">Loading more complaints...</span>
                    </div>
                </div>
                
                            <!-- Scroll to Top Button -->
            <button class="scroll-top-btn" id="scrollTopBtn" title="Scroll to top">
                <img src="../assets/images/aimt-logo.png" alt="Scroll to top" class="scroll-top-icon">
            </button>
                
                <!-- Action Buttons Section -->
                <div class="mt-8 flex flex-col sm:flex-row gap-4 justify-between items-start sm:items-center">
                    <!-- Download Excel Report Button -->
                    <a href="generate_report.php?<?= http_build_query($filter) ?>" 
                       class="inline-flex items-center px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white font-medium hover:from-emerald-700 hover:to-teal-700 transition-all duration-200 shadow-lg shadow-emerald-200">
                        <i class="lucide-download mr-2 h-5 w-5"></i>
                        Download Excel Report
                    </a>
                    
                    <!-- Database Cleanup Toggle Button -->
                    <button id="cleanupToggle" class="inline-flex items-center px-6 py-3 rounded-xl bg-gradient-to-r from-red-600 to-pink-600 text-white font-medium hover:from-red-700 hover:to-pink-700 transition-all duration-200 shadow-lg shadow-red-200">
                        <i class="lucide-database mr-2 h-5 w-5"></i>
                        Database Cleaner
                        <i class="lucide-chevron-down ml-2 transition-transform duration-200" id="cleanupArrow"></i>
                    </button>
                </div>

                <!-- Database Cleanup Section -->
                <div id="cleanupSection" class="mt-8 hidden">
                    <div class="glassmorphism rounded-2xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-xl font-semibold text-slate-900">Database Cleanup</h2>
                                <p class="text-slate-500 text-sm">Clean up old or unwanted complaints from the database</p>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-slate-900"><?= number_format($stats['total_complaints']) ?></div>
                                <div class="text-sm text-slate-500">Total Complaints</div>
                            </div>
                        </div>

                        <!-- Cleanup Statistics -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="text-blue-600 font-semibold"><?= number_format($stats['resolved_count']) ?></div>
                                <div class="text-blue-500 text-sm">Resolved</div>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <div class="text-red-600 font-semibold"><?= number_format($stats['rejected_count']) ?></div>
                                <div class="text-red-500 text-sm">Rejected</div>
                            </div>
                            <div class="bg-yellow-50 p-4 rounded-lg">
                                <div class="text-yellow-600 font-semibold"><?= number_format($stats['older_than_90_days']) ?></div>
                                <div class="text-yellow-500 text-sm">Older than 90 days</div>
                            </div>
                            <div class="bg-orange-50 p-4 rounded-lg">
                                <div class="text-orange-600 font-semibold"><?= number_format($stats['older_than_365_days']) ?></div>
                                <div class="text-orange-500 text-sm">Older than 1 year</div>
                            </div>
                        </div>

                        <!-- Cleanup Options -->
                        <form method="POST" id="cleanupForm" class="space-y-6">
                            <input type="hidden" name="cleanup_action" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Cleanup by Status -->
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h3 class="font-medium text-slate-900 mb-3">Clean by Status</h3>
                                    <div class="space-y-3">
                                        <select name="cleanup_status" class="form-input w-full">
                                            <option value="">Select Status</option>
                                            <option value="resolved">Resolved (<?= number_format($stats['resolved_count']) ?>)</option>
                                            <option value="rejected">Rejected (<?= number_format($stats['rejected_count']) ?>)</option>
                                            <option value="pending">Pending (<?= number_format($stats['pending_count']) ?>)</option>
                                            <option value="in_progress">In Progress (<?= number_format($stats['in_progress_count']) ?>)</option>
                                        </select>
                                        <button type="submit" name="cleanup_type" value="by_status" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                                                onclick="return confirm('Are you sure you want to delete all complaints with this status? This action cannot be undone.')">
                                            <i class="lucide-trash-2 mr-2 h-4 w-4"></i>
                                            Clean by Status
                                        </button>
                                    </div>
                                </div>

                                <!-- Cleanup by Category -->
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h3 class="font-medium text-slate-900 mb-3">Clean by Category</h3>
                                    <div class="space-y-3">
                                        <select name="cleanup_category" class="form-input w-full">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat ?>"><?= ucfirst($cat) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="cleanup_type" value="by_category" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                                                onclick="return confirm('Are you sure you want to delete all complaints in this category? This action cannot be undone.')">
                                            <i class="lucide-trash-2 mr-2 h-4 w-4"></i>
                                            Clean by Category
                                        </button>
                                    </div>
                                </div>

                                <!-- Cleanup by Date Range -->
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h3 class="font-medium text-slate-900 mb-3">Clean by Date Range</h3>
                                    <div class="space-y-3">
                                        <input type="date" name="cleanup_date_from" class="form-input w-full" placeholder="From Date">
                                        <input type="date" name="cleanup_date_to" class="form-input w-full" placeholder="To Date">
                                        <button type="submit" name="cleanup_type" value="by_date_range" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                                                onclick="return confirm('Are you sure you want to delete all complaints in this date range? This action cannot be undone.')">
                                            <i class="lucide-trash-2 mr-2 h-4 w-4"></i>
                                            Clean by Date Range
                                        </button>
                                    </div>
                                </div>

                                <!-- Cleanup by Age -->
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h3 class="font-medium text-slate-900 mb-3">Clean by Age</h3>
                                    <div class="space-y-3">
                                        <select name="cleanup_older_than" class="form-input w-full">
                                            <option value="">Select Age</option>
                                            <option value="90">Older than 90 days (<?= number_format($stats['older_than_90_days']) ?>)</option>
                                            <option value="180">Older than 180 days (<?= number_format($stats['older_than_180_days']) ?>)</option>
                                            <option value="365">Older than 1 year (<?= number_format($stats['older_than_365_days']) ?>)</option>
                                        </select>
                                        <button type="submit" name="cleanup_type" value="older_than" 
                                                class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium"
                                                onclick="return confirm('Are you sure you want to delete all complaints older than the selected age? This action cannot be undone.')">
                                            <i class="lucide-trash-2 mr-2 h-4 w-4"></i>
                                            Clean by Age
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Cleanup Options -->
                            <div class="border-t border-slate-200 pt-6">
                                <h3 class="font-medium text-slate-900 mb-4">Quick Cleanup Options</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <button type="submit" name="cleanup_type" value="resolved_old" 
                                            class="flex items-center justify-center px-6 py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-medium"
                                            onclick="return confirm('This will delete all resolved complaints older than 90 days. Are you sure?')">
                                        <i class="lucide-clock mr-2 h-4 w-4"></i>
                                        Clean Old Resolved (90+ days)
                                    </button>
                                    <button type="submit" name="cleanup_type" value="all_old" 
                                            class="flex items-center justify-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium"
                                            onclick="return confirm('This will delete ALL complaints older than 1 year. This is a destructive action. Are you absolutely sure?')">
                                        <i class="lucide-alert-triangle mr-2 h-4 w-4"></i>
                                        Clean All Old (1+ year)
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="glassmorphism rounded-2xl shadow-lg p-12 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-400 mb-4">
                        <i class="lucide-search-x h-8 w-8"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No complaints found</h3>
                    <p class="text-slate-500 mb-6">Try adjusting your filters to see more results</p>
                    <a href="dashboard.php" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors text-sm font-medium">
                        <i class="lucide-arrow-left mr-2 h-4 w-4"></i>
                        Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Loading spinner
        window.addEventListener('load', () => {
            const loading = document.getElementById('loading');
            loading.classList.remove('active');
        });

        document.addEventListener('DOMContentLoaded', () => {
            const loading = document.getElementById('loading');
            loading.classList.add('active');

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const sidebarClose = document.getElementById('sidebar-close');
            
            // Open sidebar
            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                sidebar.classList.remove('hidden');
                sidebarOverlay.classList.add('active');
                sidebarOverlay.classList.remove('hidden');
                menuToggle.classList.add('sidebar-active');
                sidebar.focus();
            });
            
            // Close sidebar
            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebar.classList.add('hidden');
                sidebarOverlay.classList.remove('active');
                menuToggle.classList.remove('sidebar-active');
                setTimeout(()=>sidebarOverlay.classList.add('hidden'), 300);
            }
            sidebarOverlay.addEventListener('click', closeSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);
            
            // Always hide sidebar on resize
            window.addEventListener('resize', () => {
                closeSidebar();
            });

            // Close sidebar when pressing Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });

            // Table Expand Functionality
            const expandBtn = document.getElementById('expandBtn');
            const tableContainer = document.getElementById('tableContainer');
            const complaintsTable = document.getElementById('complaintsTable');
            const scrollTopBtn = document.getElementById('scrollTopBtn');
            

            if (expandBtn && tableContainer) {
                expandBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (tableContainer.classList.contains('expanded')) {
                        // Collapse
                        tableContainer.classList.remove('expanded');
                        expandBtn.classList.remove('expanded');
                        expandBtn.innerHTML = '<i class="lucide-maximize-2 h-4 w-4"></i><span>Expand</span>';
                        document.body.style.overflow = '';
                        
                        // Hide scroll-to-top button when collapsed
                        if (scrollTopBtn) {
                            scrollTopBtn.classList.remove('visible');
                        }
                    } else {
                        // Expand
                        tableContainer.classList.add('expanded');
                        expandBtn.classList.add('expanded');
                        expandBtn.innerHTML = '<i class="lucide-minimize-2 h-4 w-4"></i><span>Collapse</span>';
                        document.body.style.overflow = 'hidden';
                        
                        // Force a repaint to ensure the expanded state is applied
                        tableContainer.offsetHeight;
                    }
                });
                
                // Close expanded view on escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && tableContainer.classList.contains('expanded')) {
                        expandBtn.click();
                    }
                });
            }

            // Scroll to Top Button (only visible when expanded)
            const tableWrapper = document.getElementById('tableWrapper');
            const tableScrollContainer = tableWrapper;
            
            if (scrollTopBtn && tableScrollContainer) {
                function toggleScrollTopButton() {
                    const isExpanded = tableContainer && tableContainer.classList.contains('expanded');
                    const scrollTop = tableScrollContainer.scrollTop;
                    
                    if (isExpanded && scrollTop > 300) {
                        scrollTopBtn.classList.add('visible');
                    } else {
                        scrollTopBtn.classList.remove('visible');
                    }
                }
                
                tableScrollContainer.addEventListener('scroll', toggleScrollTopButton);
                
                scrollTopBtn.addEventListener('click', () => {
                    tableScrollContainer.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                });
            }

            // Infinite Scroll Implementation
            let isLoading = false;
            let currentPage = <?= $page ?>;
            let loadedComplaints = <?= count($complaints) ?>;
            const totalComplaints = <?= $total_complaints ?>;
            const perPage = <?= $per_page ?>;
            
            if (tableWrapper && totalComplaints > loadedComplaints) {
                tableWrapper.addEventListener('scroll', () => {
                    const { scrollTop, scrollHeight, clientHeight } = tableWrapper;
                    
                    // Load more when user scrolls to bottom (with 50px threshold)
                    if (scrollTop + clientHeight >= scrollHeight - 50 && !isLoading && loadedComplaints < totalComplaints) {
                        loadMoreComplaints();
                    }
                });
            }
            
            function loadMoreComplaints() {
                if (isLoading) return;
                
                isLoading = true;
                const loadingMore = document.getElementById('loadingMore');
                if (loadingMore) loadingMore.classList.add('active');
                
                currentPage++;
                const url = new URL(window.location);
                url.searchParams.set('page', currentPage);
                url.searchParams.set('ajax', '1'); // Add AJAX parameter
                
                fetch(url.toString())
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.complaints.length > 0) {
                            const tbody = document.querySelector('#complaintsTable tbody');
                            
                            data.complaints.forEach(complaint => {
                                const row = createComplaintRow(complaint);
                                tbody.appendChild(row);
                            });
                            
                            loadedComplaints += data.complaints.length;
                            updateComplaintCount();
                        }
                        
                        isLoading = false;
                        if (loadingMore) loadingMore.classList.remove('active');
                    })
                    .catch(error => {
                        console.error('Error loading more complaints:', error);
                        isLoading = false;
                        if (loadingMore) loadingMore.classList.remove('active');
                        currentPage--; // Revert page number on error
                    });
            }
            
            function createComplaintRow(complaint) {
                const createdTime = new Date(complaint.created_at).getTime();
                const daysOld = Math.floor((Date.now() - createdTime) / (1000 * 60 * 60 * 24));
                const isLongPending = ['pending', 'in_progress'].includes(complaint.status) && daysOld >= 14;
                
                // Determine age indicator class
                let ageClass = 'age-normal';
                if (isLongPending) {
                    ageClass = 'age-critical';
                } else if (daysOld >= 7) {
                    ageClass = 'age-warning';
                }
                
                const row = document.createElement('tr');
                row.className = isLongPending ? 'long-pending' : '';
                
                row.innerHTML = `
                    <td>
                        <div class="user-info">
                            <div class="user-name">${complaint.user_name}</div>
                            <div class="user-role">${complaint.user_role}</div>
                        </div>
                    </td>
                    <td>
                        <span class="category-badge">${complaint.category}</span>
                    </td>
                    <td>
                        <span class="status-badge status-${complaint.status.replace('_', '-')}">
                            ${complaint.status.charAt(0).toUpperCase() + complaint.status.slice(1)}
                        </span>
                    </td>
                    <td>
                        <div class="technician-info ${!complaint.tech_name ? 'technician-unassigned' : ''}">
                            ${complaint.tech_name || 'Not assigned'}
                        </div>
                    </td>
                    <td>
                        <div class="date-info">${new Date(complaint.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                    </td>
                    <td>
                        <span class="age-indicator ${ageClass}">
                            ${daysOld} days
                        </span>
                    </td>
                    <td>
                        <a href="complaint_details.php?token=${encodeURIComponent(complaint.token)}" class="action-link">View Details</a>
                    </td>
                `;
                
                return row;
            }
            
            function updateComplaintCount() {
                const countElement = document.querySelector('.pagination-info');
                if (countElement) {
                    countElement.textContent = `Showing ${loadedComplaints.toLocaleString()} of ${totalComplaints.toLocaleString()} complaints • Scroll to load more`;
                }
            }

            // Database Cleanup Toggle with Auto-scroll
            const cleanupToggle = document.getElementById('cleanupToggle');
            const cleanupSection = document.getElementById('cleanupSection');
            const cleanupArrow = document.getElementById('cleanupArrow');
            
            if (cleanupToggle && cleanupSection && cleanupArrow) {
                cleanupToggle.addEventListener('click', () => {
                    const isHidden = cleanupSection.classList.contains('hidden');
                    
                    if (isHidden) {
                        // Show the section first
                        cleanupSection.classList.remove('hidden');
                        cleanupArrow.classList.add('rotate-180');
                        
                        // Then scroll to it smoothly
                        setTimeout(() => {
                            cleanupSection.scrollIntoView({ 
                                behavior: 'smooth', 
                                block: 'start',
                                inline: 'nearest'
                            });
                        }, 100);
                    } else {
                        // Just hide it
                        cleanupSection.classList.add('hidden');
                        cleanupArrow.classList.remove('rotate-180');
                    }
                });
            }
        });
    </script>
</body>
</html> 