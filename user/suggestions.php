<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}



// Get user details from database using mysqli
$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $suggestion = isset($_POST['suggestion']) ? trim($_POST['suggestion']) : '';
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    
    // Validate inputs
    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($suggestion)) $errors[] = "Suggestion description is required";
    if (empty($category)) $errors[] = "Category is required";
    
    if (empty($errors)) {
        $stmt = $mysqli->prepare("INSERT INTO suggestions (user_id, title, suggestion, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $user_id, $title, $suggestion, $category);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Your suggestion has been submitted successfully!";
        header('Location: suggestions.php');
        exit();
    }
}

// Handle delete request
if (isset($_POST['delete_suggestion'])) {
    $suggestion_id = (int)$_POST['delete_suggestion'];
    
    // Verify the suggestion belongs to the current user
    $stmt = $mysqli->prepare("SELECT user_id FROM suggestions WHERE id = ?");
    $stmt->bind_param('i', $suggestion_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $suggestion = $result->fetch_assoc();
    $stmt->close();
    
    if ($suggestion && $suggestion['user_id'] == $user_id) {
        // Delete the suggestion
        $stmt = $mysqli->prepare("DELETE FROM suggestions WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $suggestion_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['success'] = "Your suggestion has been deleted successfully!";
        header('Location: suggestions.php');
        exit();
    } else {
        $_SESSION['error'] = "You can only delete your own suggestions!";
        header('Location: suggestions.php');
        exit();
    }
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'active';

// Build the query based on filter
$suggestions_query = "
    SELECT s.*, 
           CASE 
               WHEN s.user_id = ? THEN u.full_name 
               ELSE CONCAT(UCASE(LEFT(u.role, 1)), SUBSTRING(u.role, 2))
           END as display_name,
           u.hostel_type,
           u.role,
           (SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id = s.id AND vote_type = 'upvote') as upvotes,
           (SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id = s.id AND vote_type = 'downvote') as downvotes,
           (SELECT vote_type FROM suggestion_votes WHERE suggestion_id = s.id AND user_id = ? LIMIT 1) as user_vote,
           s.admin_remark
    FROM suggestions s
    JOIN users u ON s.user_id = u.id
    WHERE 1=1
";

// Add status filter based on the filter parameter
switch ($filter) {
    case 'approved':
        $suggestions_query .= " AND s.status = 'approved'";
        break;
    case 'implemented':
        $suggestions_query .= " AND s.status = 'implemented'";
        break;
    case 'rejected':
        $suggestions_query .= " AND s.status = 'rejected'";
        break;
    default: // 'active' - show pending suggestions
        $suggestions_query .= " AND (s.status = 'pending' OR s.status IS NULL)";
        break;
}

$suggestions_query .= " ORDER BY upvotes DESC, s.created_at DESC";

$stmt = $mysqli->prepare($suggestions_query);
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$suggestions = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get categories for dropdown
$categories = ['academics', 'infrastructure', 'hostel', 'mess', 'sports', 'other'];

// Get counts for different statuses
$counts_query = "
    SELECT 
        SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'implemented' THEN 1 ELSE 0 END) as implemented_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM suggestions
";
$counts_result = $mysqli->query($counts_query);
$counts = $counts_result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suggestions Portal</title>
    
    <!-- Modern CSS Reset -->
    <link rel="stylesheet" href="https://unpkg.com/modern-css-reset/dist/reset.min.css">
    
    <!-- Google Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Day.js for relative time -->
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/relativeTime.js"></script>
    
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
        /* Only keep unique custom effects above. Remove layout/spacing custom CSS below. */
        /* Primary gradient button */
        .btn-primary {
            background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
            box-shadow: 0 4px 14px rgba(79,70,229,0.25);
            transition: background 0.3s, transform 0.2s;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #4338CA 0%, #6D28D9 100%);
            transform: translateY(-1px);
        }
        /* Responsive badge placement */
        .most-voted-badge {
            position:absolute;
            right:0.75rem; /* 3 */
            top:0.25rem;
        }
        @media (min-width: 640px){
            .most-voted-badge{right:1rem;top:1rem;}
        }
    </style>
</head>
<body class="min-h-screen w-full bg-gradient-to-br from-indigo-50 to-purple-50">
    <main class="max-w-3xl w-full mx-auto px-2 sm:px-4 md:px-6 py-4 md:py-8">
        <!-- Back Button -->
        <a href="dashboard.php" class="inline-flex items-center gap-2 text-gray-600 hover:text-indigo-600 mb-6 group transition-colors duration-300">
            <svg class="w-5 h-5 transform transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            <span class="font-medium">Back to Dashboard</span>
        </a>

        <div class="text-center mb-8">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-2">Suggestions Portal</h1>
            <p class="text-gray-600">Share your ideas to make our campus better</p>
        </div>

        <!-- Guidelines Button & Section -->
        <div class="mb-6">
            <button onclick="toggleGuidelines()" 
                    class="w-full flex items-center justify-between gap-4 bg-gradient-to-r from-indigo-500 to-purple-500 text-white rounded-lg p-4 shadow-sm hover:shadow-md transition-all duration-300 hover:scale-[1.01] focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-white/15">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    </span>
                    <span class="text-base sm:text-lg font-bold">Guidelines & Tips</span>
                </div>
                <svg id="guidelineArrow" class="w-6 h-6 transform transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="guidelineContent" class="hidden mt-3 bg-white rounded-xl shadow-lg overflow-hidden transition-all duration-300">
                <div class="p-5">
                    <div class="text-center mb-4">
                        <h3 class="text-xl font-semibold text-indigo-600 mb-1">Welcome to Suggestions Portal!</h3>
                        <p class="text-gray-600">Help us improve by sharing your thoughtful ideas</p>
                    </div>

                    <div class="grid gap-3">
                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-4 rounded-lg border border-indigo-100">
                            <div class="flex items-start gap-3">
                                <div class="bg-white p-2 rounded-full">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-indigo-900">Be Specific</h4>
                                    <p class="text-sm text-indigo-700 mt-1">Clearly describe your idea and how it can be implemented</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-emerald-50 to-green-50 p-4 rounded-lg border border-green-100">
                            <div class="flex items-start gap-3">
                                <div class="bg-white p-2 rounded-full">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-emerald-900">Be Constructive</h4>
                                    <p class="text-sm text-emerald-700 mt-1">Focus on solutions that benefit everyone</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gradient-to-r from-blue-50 to-sky-50 p-4 rounded-lg border border-blue-100">
                            <div class="flex items-start gap-3">
                                <div class="bg-white p-2 rounded-full">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-medium text-blue-900">Be Respectful</h4>
                                    <p class="text-sm text-blue-700 mt-1">Keep suggestions professional and courteous</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-lg border border-purple-100">
                        <p class="text-center text-purple-800 font-medium">
                            💡 Your ideas matter! Let's make our campus better together.
                        </p>
                    </div>

                    <div class="mt-4 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-100">
                        <div class="flex items-start gap-3">
                            <div class="bg-white p-2 rounded-full">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-medium text-blue-900">Privacy Protection</h4>
                                <p class="text-sm text-blue-700 mt-1">Your suggestions are posted anonymously to encourage honest feedback. Only hostel type (Boys/Girls) is shown to other users.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 animate-fade">
                <?php echo $_SESSION['success']; ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 animate-fade">
                <?php echo $_SESSION['error']; ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Share Suggestion Button Only, Bold and Modern -->
        <div class="w-full flex justify-center mb-8">
            <button onclick="toggleForm()" class="w-full sm:w-auto px-8 py-4 text-lg sm:text-xl font-extrabold tracking-wide rounded-2xl shadow-lg bg-gradient-to-r from-violet-600 via-indigo-500 to-blue-500 hover:from-violet-700 hover:via-indigo-600 hover:to-blue-700 text-white flex items-center justify-center gap-3 transition-all duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Share Your Suggestion
        </button>
        </div>

        <!-- Filter Buttons -->
        <div class="mb-6 flex flex-wrap justify-center gap-2 sm:gap-3">
            <a href="?filter=active" 
               class="px-4 py-2 rounded-lg font-medium transition-all duration-200 <?php echo $filter === 'active' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'; ?>">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Active (<?php echo $counts['active_count'] ?? 0; ?>)
                </span>
            </a>
            <a href="?filter=approved" 
               class="px-4 py-2 rounded-lg font-medium transition-all duration-200 <?php echo $filter === 'approved' ? 'bg-green-600 text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'; ?>">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Approved (<?php echo $counts['approved_count'] ?? 0; ?>)
                </span>
            </a>
            <a href="?filter=implemented" 
               class="px-4 py-2 rounded-lg font-medium transition-all duration-200 <?php echo $filter === 'implemented' ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'; ?>">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Implemented (<?php echo $counts['implemented_count'] ?? 0; ?>)
                </span>
            </a>
            <a href="?filter=rejected" 
               class="px-4 py-2 rounded-lg font-medium transition-all duration-200 <?php echo $filter === 'rejected' ? 'bg-red-600 text-white shadow-lg' : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-200'; ?>">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Rejected (<?php echo $counts['rejected_count'] ?? 0; ?>)
                </span>
            </a>
        </div>

        <!-- Recent Suggestions Heading -->
        <div class="mb-6 text-center">
            <h2 class="text-xl sm:text-2xl md:text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-purple-600">
                <?php 
                switch($filter) {
                    case 'approved':
                        echo 'Approved Suggestions';
                        break;
                    case 'implemented':
                        echo 'Implemented Suggestions';
                        break;
                    case 'rejected':
                        echo 'Rejected Suggestions';
                        break;
                    default:
                        echo 'Active Suggestions';
                        break;
                }
                ?>
            </h2>
            <p class="text-gray-600 mt-2">
                <?php 
                switch($filter) {
                    case 'approved':
                        echo 'Suggestions that have been approved by the administration';
                        break;
                    case 'implemented':
                        echo 'Suggestions that have been successfully implemented';
                        break;
                    case 'rejected':
                        echo 'Suggestions that were not approved';
                        break;
                    default:
                        echo 'Pending and in-progress suggestions from your fellow students and staff';
                        break;
                }
                ?>
            </p>
        </div>

        <div id="suggestionForm" class="hidden mb-8 animate-fade w-full max-w-xl mx-auto">
            <form method="POST" class="bg-white rounded-xl shadow-lg p-4 sm:p-6 w-full">
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 text-red-700 p-4 rounded-lg mb-6">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" required maxlength="100"
                               class="form-input w-full px-4 py-2 border border-gray-200 rounded-lg"
                               placeholder="Brief title for your suggestion"
                               value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>">
                        <p class="mt-1 text-sm text-gray-500">Keep it clear and concise (max 100 characters)</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" required
                                class="form-input w-full px-4 py-2 border border-gray-200 rounded-lg">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo (isset($category) && $category === $cat) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="mt-1 text-sm text-gray-500">Choose the most relevant category for your suggestion</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Suggestion Details</label>
                        <textarea name="suggestion" required rows="4"
                                  class="form-input w-full px-4 py-2 border border-gray-200 rounded-lg"
                                  placeholder="Describe your suggestion in detail"><?php echo isset($suggestion) ? htmlspecialchars($suggestion) : ''; ?></textarea>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="button" onclick="toggleForm()"
                                class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                class="flex-1 btn-primary text-white px-4 py-2 rounded-lg">
                            Submit
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Suggestions List -->
        <div class="space-y-4">
            <?php if (empty($suggestions)): ?>
                <div class="text-center py-12 bg-white rounded-xl shadow-sm">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900">
                        <?php 
                        switch($filter) {
                            case 'approved':
                                echo 'No approved suggestions yet';
                                break;
                            case 'implemented':
                                echo 'No implemented suggestions yet';
                                break;
                            case 'rejected':
                                echo 'No rejected suggestions';
                                break;
                            default:
                                echo 'No active suggestions yet';
                                break;
                        }
                        ?>
                    </h3>
                    <p class="text-gray-500 mt-1">
                        <?php 
                        switch($filter) {
                            case 'approved':
                                echo 'Check back later for approved suggestions';
                                break;
                            case 'implemented':
                                echo 'Check back later for implemented suggestions';
                                break;
                            case 'rejected':
                                echo 'No suggestions have been rejected yet';
                                break;
                            default:
                                echo 'Be the first to share your suggestion!';
                                break;
                        }
                        ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-6">
                    <?php 
                    // Find the most voted suggestion (highest upvotes)
                    $maxUpvotes = 0;
                    foreach ($suggestions as $s) {
                        if ($s['upvotes'] > $maxUpvotes) $maxUpvotes = $s['upvotes'];
                    }
                    $cardIndex = 0;
                    foreach ($suggestions as $suggestion): ?>
                        <div class="suggestion-card rounded-xl p-4 sm:p-6 bg-white border border-gray-200 shadow-md w-full transition-transform duration-200 hover:shadow-xl hover:scale-[1.01] relative opacity-0 translate-y-6">
                            <?php if ($suggestion['upvotes'] == $maxUpvotes && $maxUpvotes > 0): ?>
                                <div class="most-voted-badge z-10">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gradient-to-r from-yellow-200 via-amber-100 to-yellow-300 border border-yellow-300 shadow-sm text-xs font-semibold text-yellow-800" style="backdrop-filter: blur(2px);">
                                        <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967a1 1 0 00.95.69h4.178c.969 0 1.371 1.24.588 1.81l-3.385 2.46a1 1 0 00-.364 1.118l1.287 3.966c.3.922-.755 1.688-1.54 1.118l-3.385-2.46a1 1 0 00-1.175 0l-3.385 2.46c-.784.57-1.838-.196-1.54-1.118l1.287-3.966a1 1 0 00-.364-1.118l-3.385-2.46c-.783-.57-.38-1.81.588-1.81h4.178a1 1 0 00.95-.69l1.286-3.967z"/></svg>
                                        Most Voted
                                    </span>
                                </div>
                                <?php endif; ?>
                            <div class="card-header relative pb-2 mb-2 flex flex-col gap-2">
                                <h3 class="text-xl sm:text-2xl font-bold text-gray-900 mb-2 break-words w-full pr-24 sm:pr-32">
                                    <?php echo htmlspecialchars($suggestion['title']); ?>
                                </h3>
                                <div class="flex flex-wrap items-center gap-2 mb-1 text-xs">
                                    <span class="text-gray-500 font-medium">Submitted by</span>
                                    <span class="font-semibold text-gray-800"><?php echo htmlspecialchars($suggestion['display_name']); ?></span>
                                            <?php if ($suggestion['user_id'] == $user_id): ?>
                                        <span class="ml-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">You</span>
                                            <?php endif; ?>
                                    <?php if (!empty($suggestion['hostel_type'])): ?>
                                        <?php if ($suggestion['user_id'] == $user_id): ?><span class="mx-1 text-gray-300">•</span><?php endif; ?>
                                        <span class="flex items-center gap-1 bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full font-medium">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"/>
                                                </svg>
                                                <?php echo ucfirst($suggestion['hostel_type']); ?> Hostel
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs mt-1">
                                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full font-medium">
                                        <?php echo ucfirst($suggestion['category'] ?? 'other'); ?>
                                        </span>
                                    <span class="px-2 py-0.5 rounded-full font-medium status-badge
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
                            <div class="mt-4">
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 sm:p-5">
                                    <p class="text-gray-700 leading-relaxed break-words">
                                    <?php echo nl2br(htmlspecialchars($suggestion['suggestion'])); ?>
                                </p>
                                </div>
                            </div>
                            <?php if (!empty($suggestion['admin_remark'])): ?>
                                <div class="mt-4 p-4 bg-gradient-to-r from-yellow-50 to-amber-50 border border-amber-300 border-l-4 border-l-amber-600 rounded-lg shadow min-w-0 break-words" style="word-break: break-word; overflow-wrap: anywhere;">
                                    <div class="flex items-start gap-2 min-w-0">
                                        <svg class="w-5 h-5 text-amber-700 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                        <div class="min-w-0 break-words" style="word-break: break-word; overflow-wrap: anywhere;">
                                            <h4 class="font-medium text-amber-900 mb-1">Admin Remark</h4>
                                            <p class="text-amber-800 text-sm break-words" style="word-break: break-word; overflow-wrap: anywhere;">
                                                <?php echo nl2br(htmlspecialchars($suggestion['admin_remark'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pt-4 mt-4 border-t border-gray-100 gap-2">
                                <div class="flex items-center gap-4">
                                    <button onclick="vote(<?php echo $suggestion['id']; ?>, 'upvote')" 
                                            class="vote-button flex items-center gap-1 px-3 py-2 rounded-lg font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-400
                                                <?php echo $suggestion['user_vote'] === 'upvote' ? 'bg-green-100 text-green-700 shadow active-upvote ring-2 ring-green-400' : 'bg-gray-50 text-gray-500 hover:bg-green-50 hover:text-green-600'; ?>"
                                            title="Upvote" aria-label="Upvote this suggestion">
                                        <svg class="w-5 h-5 <?php echo $suggestion['user_vote'] === 'upvote' ? 'text-green-600' : 'text-gray-400'; ?>" fill="<?php echo $suggestion['user_vote'] === 'upvote' ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                                        </svg>
                                        <span class="font-bold text-base" title="<?php echo $suggestion['upvotes']; ?> people found this helpful."><?php echo $suggestion['upvotes']; ?></span>
                                    </button>
                                    <button onclick="vote(<?php echo $suggestion['id']; ?>, 'downvote')" 
                                            class="vote-button flex items-center gap-1 px-3 py-2 rounded-lg font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-400
                                                <?php echo $suggestion['user_vote'] === 'downvote' ? 'bg-red-100 text-red-700 shadow active-downvote ring-2 ring-red-400' : 'bg-gray-50 text-gray-500 hover:bg-red-50 hover:text-red-600'; ?>"
                                            title="Downvote" aria-label="Downvote this suggestion">
                                        <svg class="w-5 h-5 <?php echo $suggestion['user_vote'] === 'downvote' ? 'text-red-600' : 'text-gray-400'; ?>" fill="<?php echo $suggestion['user_vote'] === 'downvote' ? 'currentColor' : 'none'; ?>" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        <span class="font-bold text-base" title="<?php echo $suggestion['downvotes']; ?> people found this unhelpful."><?php echo $suggestion['downvotes']; ?></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php $cardIndex++; endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>
        // Show guidelines only on the very first visit using localStorage
        document.addEventListener('DOMContentLoaded', function() {
            const guidelineKey = 'suggestions_guidelines_shown';
            if (!localStorage.getItem(guidelineKey)) {
                setTimeout(() => {
                    toggleGuidelines();
                    // Auto-hide after 4 seconds
                    setTimeout(() => {
                        if (document.getElementById('guidelineContent') && 
                            !document.getElementById('guidelineContent').classList.contains('hidden')) {
                            toggleGuidelines();
                        }
                    }, 4000);
                }, 500);
                localStorage.setItem(guidelineKey, 'true');
            }
        });

        function toggleForm() {
            const form = document.getElementById('suggestionForm');
            form.classList.toggle('hidden');
            if (!form.classList.contains('hidden')) {
                form.scrollIntoView({ behavior: 'smooth' });
                document.querySelector('input[name="title"]').focus();
            }
        }

        function vote(suggestionId, voteType) {
            fetch('vote_suggestion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    suggestion_id: suggestionId,
                    vote_type: voteType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Just reload the page without triggering guidelines
                    window.location.reload();
                } else {
                    alert(data.message || 'Error voting on suggestion');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error voting on suggestion');
            });
        }

        function toggleGuidelines() {
            const content = document.getElementById('guidelineContent');
            const arrow = document.getElementById('guidelineArrow');
            
            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                arrow.classList.add('rotate-180');
                requestAnimationFrame(() => {
                    content.classList.add('show');
                    content.classList.remove('hide');
                });
            } else {
                content.classList.add('hide');
                content.classList.remove('show');
                arrow.classList.remove('rotate-180');
                setTimeout(() => {
                    content.classList.add('hidden');
                }, 300);
            }
        }

        // Relative time for suggestion dates
        document.addEventListener('DOMContentLoaded', function() {
            if (window.dayjs) {
                dayjs.extend(window.dayjs_plugin_relativeTime);
                document.querySelectorAll('[data-timestamp]').forEach(function(el) {
                    const ts = el.getAttribute('data-timestamp');
                    if (ts) {
                        const rel = dayjs(ts).fromNow();
                        el.textContent = rel;
                    }
                });
            }
        });

        // Animate suggestion cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.suggestion-card');
            cards.forEach((card, i) => {
                setTimeout(() => {
                    card.classList.remove('opacity-0', 'translate-y-6');
                    card.classList.add('opacity-100', 'translate-y-0', 'transition-all', 'duration-700');
                }, 100 + i * 80);
            });
        });
    </script>
</body>
</html> 