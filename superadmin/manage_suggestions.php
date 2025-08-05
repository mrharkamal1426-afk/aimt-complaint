<?php

session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../auth/login.php');
    exit;
}

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

$success_message = '';
$error_message = '';

// Handle status updates with remarks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $suggestion_id = (int)$_POST['suggestion_id'];
    $new_status = $_POST['status'];
    $remark = isset($_POST['remark']) ? trim($_POST['remark']) : '';
    
    // Validate status value against allowed ENUM values
    $allowed_statuses = ['pending', 'approved', 'rejected', 'implemented'];
    if (!in_array($new_status, $allowed_statuses)) {
        $error_message = "Invalid status value.";
    } else {
        $stmt = $mysqli->prepare("UPDATE suggestions SET status = ?, admin_remark = ? WHERE id = ?");
        $stmt->bind_param('ssi', $new_status, $remark, $suggestion_id);
        if ($stmt->execute()) {
            $success_message = "Suggestion status updated successfully!";
        } else {
            $error_message = "Error updating suggestion status.";
        }
        $stmt->close();
    }
}

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'pending';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'most_upvotes';

// Build the query
$query = "
    SELECT 
        s.*,
        u.full_name,
        u.hostel_type,
        COALESCE(s.category, 'other') as category,
        COALESCE(s.status, 'pending') as status,
        COALESCE(s.upvotes, 0) as upvotes,
        COALESCE(s.downvotes, 0) as downvotes,
        s.admin_remark,
        CASE 
            WHEN u.role IS NOT NULL THEN CONCAT(UCASE(LEFT(u.role, 1)), SUBSTRING(u.role, 2))
            ELSE 'User'
        END as anonymous_name
    FROM suggestions s 
    JOIN users u ON s.user_id = u.id 
    WHERE 1=1
";
$params = [];
$types = '';

if ($category) {
    $query .= " AND s.category = ?";
    $params[] = $category;
    $types .= 's';
}

if ($status) {
    $query .= " AND s.status = ?";
    $params[] = $status;
    $types .= 's';
}

// Add sorting
switch ($sort) {
    case 'most_upvotes':
        $query .= " ORDER BY s.upvotes DESC";
        break;
    case 'most_downvotes':
        $query .= " ORDER BY s.downvotes DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY s.created_at ASC";
        break;
    default: // newest
        $query .= " ORDER BY s.created_at DESC";
}

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$suggestions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suggestions - AIMT Complaint Portal</title>
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            --surface-gradient: linear-gradient(135deg, #EEF2FF 0%, #F5F3FF 100%);
            --card-gradient: linear-gradient(160deg, #ffffff 0%, #f8fafc 100%);
        }
        
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--surface-gradient);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Decorative background patterns */
        body::before,
        body::after {
            content: '';
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            z-index: -1;
            opacity: 0.1;
            filter: blur(80px);
        }

        body::before {
            background: #818CF8;
            top: -100px;
            right: -100px;
        }

        body::after {
            background: #C084FC;
            bottom: -100px;
            left: -100px;
        }

        .page-container {
            position: relative;
            z-index: 1;
            backdrop-filter: blur(100px);
            min-height: 100vh;
        }

        .suggestion-card {
            background: var(--card-gradient);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.05);
            animation: cardEntrance 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .suggestion-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .suggestion-card:hover::before {
            opacity: 1;
        }
        
        .suggestion-card:hover {
            transform: translateY(-3px) scale(1.01);
            box-shadow: 0 12px 20px -10px rgba(79, 70, 229, 0.2);
            border-color: rgba(79, 70, 229, 0.1);
        }

        .btn-primary {
            background: var(--primary-gradient);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .filters-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-input {
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            border-color: #818CF8;
        }

        .remark-section {
            background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
            border: 1px solid #F59E0B;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 100%;
            box-sizing: border-box;
        }

        .remark-section p {
            word-wrap: break-word;
            overflow-wrap: break-word;
            white-space: pre-wrap;
            max-width: 100%;
            margin: 0;
            line-height: 1.5;
            word-break: break-word;
        }

        .remark-section .flex {
            align-items: flex-start;
            gap: 0.5rem;
        }

        .remark-section .flex > div {
            flex: 1;
            min-width: 0;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Additional text wrapping utilities */
        .break-words {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .overflow-wrap-anywhere {
            overflow-wrap: anywhere;
        }

        .whitespace-pre-wrap {
            white-space: pre-wrap;
        }

        .animate-fade {
            animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .suggestion-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .filters-card {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .mobile-stack {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .mobile-full-width {
                width: 100%;
            }
            
            .mobile-text-sm {
                font-size: 0.875rem;
            }
            
            .mobile-padding {
                padding: 0.75rem;
            }
        }

        /* Touch-friendly improvements */
        .touch-target {
            min-height: 44px;
            min-width: 44px;
        }
        
        .mobile-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .mobile-action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            width: 100%;
        }
        
        .mobile-action-buttons .btn-primary {
            width: 100%;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="page-container p-3 md:p-6">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="mb-6 md:mb-8">
                <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-indigo-600 mb-4 group transition-colors duration-300">
                    <svg class="w-5 h-5 transform transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    <span class="font-medium mobile-text-sm">Back to Dashboard</span>
                </a>
                
                <h1 class="text-2xl md:text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600 mb-2">Manage Suggestions</h1>
                <p class="text-gray-600 mobile-text-sm">Review and manage student and staff suggestions</p>
                <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-700">
                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <strong>Note:</strong> Suggestions are displayed anonymously to users (showing only hostel type). Full names are visible here for moderation purposes.
                    </p>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 animate-fade">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <span class="mobile-text-sm"><?php echo $success_message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 animate-fade">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span class="mobile-text-sm"><?php echo $error_message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="mobile-form-group">
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select class="form-input w-full px-3 py-3 md:py-2 border border-gray-200 rounded-lg touch-target" id="category" name="category">
                            <option value="">All Categories</option>
                            <option value="academics" <?php echo $category === 'academics' ? 'selected' : ''; ?>>Academics</option>
                            <option value="infrastructure" <?php echo $category === 'infrastructure' ? 'selected' : ''; ?>>Infrastructure</option>
                            <option value="hostel" <?php echo $category === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                            <option value="mess" <?php echo $category === 'mess' ? 'selected' : ''; ?>>Mess</option>
                            <option value="sports" <?php echo $category === 'sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="mobile-form-group">
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select class="form-input w-full px-3 py-3 md:py-2 border border-gray-200 rounded-lg touch-target" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="implemented" <?php echo $status === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                        </select>
                    </div>
                    <div class="mobile-form-group">
                        <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select class="form-input w-full px-3 py-3 md:py-2 border border-gray-200 rounded-lg touch-target" id="sort" name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="most_upvotes" <?php echo $sort === 'most_upvotes' ? 'selected' : ''; ?>>Most Upvotes</option>
                            <option value="most_downvotes" <?php echo $sort === 'most_downvotes' ? 'selected' : ''; ?>>Most Downvotes</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary text-white px-6 py-3 md:py-2 rounded-lg font-semibold w-full touch-target">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                            </svg>
                            <span class="mobile-text-sm">Apply Filters</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Suggestions List -->
            <div class="space-y-4">
                <?php if (empty($suggestions)): ?>
                    <div class="text-center py-12 bg-white rounded-xl shadow-sm mobile-padding">
                        <svg class="w-12 h-12 md:w-16 md:h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mobile-text-sm">No suggestions found</h3>
                        <p class="text-gray-500 mt-1 mobile-text-sm">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($suggestions as $suggestion): ?>
                        <div class="suggestion-card rounded-xl p-4 md:p-6">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 mobile-text-sm">
                                        <?php echo htmlspecialchars($suggestion['title']); ?>
                                    </h3>
                                    <div class="flex flex-wrap gap-2 md:gap-3 text-xs md:text-sm text-gray-500 mb-3">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                            </svg>
                                            <span class="mobile-text-sm"><?php echo htmlspecialchars($suggestion['full_name']); ?></span>
                                            <span class="text-gray-400">(<?php echo htmlspecialchars($suggestion['anonymous_name']); ?>)</span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                                            </svg>
                                            <span class="mobile-text-sm"><?php echo date('M j, Y', strtotime($suggestion['created_at'])); ?></span>
                                        </span>
                                        <?php if ($suggestion['hostel_type']): ?>
                                            <span class="flex items-center gap-1">
                                                <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                                </svg>
                                                <span class="mobile-text-sm"><?php echo ucfirst($suggestion['hostel_type']); ?> Hostel</span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-2 md:px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium">
                                            <?php echo ucfirst($suggestion['category']); ?>
                                        </span>
                                        <span class="px-2 md:px-3 py-1 rounded-full text-xs font-medium status-badge
                                            <?php 
                                            switch($suggestion['status']) {
                                                case 'pending':
                                                    echo 'bg-yellow-50 text-yellow-700';
                                                    break;
                                                case 'approved':
                                                    echo 'bg-green-50 text-green-700';
                                                    break;
                                                case 'rejected':
                                                    echo 'bg-red-50 text-red-700';
                                                    break;
                                                case 'implemented':
                                                    echo 'bg-blue-50 text-blue-700';
                                                    break;
                                                default:
                                                    echo 'bg-gray-50 text-gray-700';
                                            }
                                            ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $suggestion['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="text-gray-600 leading-relaxed break-words mobile-text-sm">
                                    <?php echo nl2br(htmlspecialchars($suggestion['suggestion'])); ?>
                                </p>
                            </div>

                            <?php if (!empty($suggestion['admin_remark'])): ?>
                                <div class="remark-section mb-4">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 md:w-5 md:h-5 text-amber-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                        <div class="flex-1 min-w-0 break-words">
                                            <h4 class="font-medium text-amber-800 mb-1 mobile-text-sm">Admin Remark</h4>
                                            <p class="text-amber-700 text-xs md:text-sm break-words overflow-wrap-anywhere whitespace-pre-wrap leading-relaxed"><?php echo nl2br(htmlspecialchars($suggestion['admin_remark'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex flex-col md:flex-row md:items-center md:justify-between pt-4 border-t border-gray-100 gap-4">
                                <div class="flex items-center gap-4">
                                    <span class="flex items-center gap-1 text-green-600">
                                        <svg class="w-6 h-6 md:w-7 md:h-7 text-green-500 drop-shadow-sm" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M3 10l7-7 7 7H4v7h12v-7z"/>
                                        </svg>
                                        <span class="font-bold text-lg mobile-text-sm"><?php echo (int)$suggestion['upvotes']; ?></span>
                                    </span>
                                    <span class="flex items-center gap-1 text-red-600">
                                        <svg class="w-6 h-6 md:w-7 md:h-7 text-red-500 drop-shadow-sm" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M17 10l-7 7-7-7h12V3H4v7z"/>
                                        </svg>
                                        <span class="font-bold text-lg mobile-text-sm"><?php echo (int)$suggestion['downvotes']; ?></span>
                                    </span>
                                </div>
                                
                                <form method="POST" action="" class="mobile-action-buttons">
                                    <input type="hidden" name="suggestion_id" value="<?php echo $suggestion['id']; ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-3">
                                        <select name="status" class="form-input px-3 py-3 md:py-2 border border-gray-200 rounded-lg text-sm touch-target">
                                            <option value="pending" <?php echo $suggestion['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo $suggestion['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $suggestion['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="implemented" <?php echo $suggestion['status'] === 'implemented' ? 'selected' : ''; ?>>Implemented</option>
                                        </select>
                                        <textarea name="remark" placeholder="Add a remark (optional)" 
                                                  class="form-input px-3 py-3 md:py-2 border border-gray-200 rounded-lg text-sm resize-none touch-target" 
                                                  rows="1"><?php echo htmlspecialchars($suggestion['admin_remark'] ?? ''); ?></textarea>
                                        <button type="submit" name="update_status" class="btn-primary text-white px-4 py-3 md:py-2 rounded-lg text-sm font-medium touch-target">
                                            Update
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 
