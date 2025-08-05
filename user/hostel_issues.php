<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/hostel_issue_functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    redirect('../login.php?error=unauthorized');
}

$user_id = $_SESSION['user_id'];

// Fetch student's hostel type from DB
$student_hostel = 'boys';
$stmt = $mysqli->prepare("SELECT hostel_type FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($db_hostel_type);
if ($stmt->fetch() && in_array($db_hostel_type, ['boys', 'girls'])) {
    $student_hostel = $db_hostel_type;
}
$stmt->close();

// Handle form submission for new issue or voting
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hostel_type = $student_hostel;
    $issue_type = $_POST['issue_type'] ?? '';
    $action = $_POST['action'] ?? '';
    $issue_id = $_POST['issue_id'] ?? null;
    $error = '';

    if ($action === 'vote' && $issue_id) {
        // Vote for an existing issue
        $stmt = $mysqli->prepare("INSERT IGNORE INTO hostel_issue_votes (issue_id, user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $issue_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } elseif ($action === 'new') {
        // Add new issue if not exists
        if ($hostel_type && $issue_type) {
            // Check if already exists
            $stmt = $mysqli->prepare("SELECT id FROM hostel_issues WHERE hostel_type = ? AND issue_type = ? AND status != 'resolved'");
            $stmt->bind_param('ss', $hostel_type, $issue_type);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 0) {
                $stmt->close();
                $stmt = $mysqli->prepare("INSERT INTO hostel_issues (hostel_type, issue_type) VALUES (?, ?)");
                $stmt->bind_param('ss', $hostel_type, $issue_type);
                $stmt->execute();
                $new_issue_id = $stmt->insert_id;
                $stmt->close();
                
                // Auto-assign the issue based on specialization
                autoAssignHostelIssue($mysqli, $new_issue_id);
                
                // Auto-vote for the new issue
                $stmt = $mysqli->prepare("INSERT INTO hostel_issue_votes (issue_id, user_id) VALUES (?, ?)");
                $stmt->bind_param('ii', $new_issue_id, $user_id);
                $stmt->execute();
                $stmt->close();
                header("Location: hostel_issues.php?hostel_type=$hostel_type");
                exit;
            } else {
                $stmt->close();
                $error = 'Issue already exists.';
            }
        } else {
            $error = 'Please select both hostel type and issue type.';
        }
    } elseif ($action === 'delete' && $issue_id) {
        // Only allow delete if the user is the creator (first voter) of the issue
        $stmt = $mysqli->prepare("SELECT user_id FROM hostel_issue_votes WHERE issue_id = ? ORDER BY vote_time ASC, id ASC LIMIT 1");
        $stmt->bind_param('i', $issue_id);
        $stmt->execute();
        $stmt->bind_result($creator_id);
        $stmt->fetch();
        $stmt->close();
        if ($creator_id == $user_id) {
            // Delete votes first due to FK constraint
            $stmt = $mysqli->prepare("DELETE FROM hostel_issue_votes WHERE issue_id = ?");
            $stmt->bind_param('i', $issue_id);
            $stmt->execute();
            $stmt->close();
            // Delete the issue
            $stmt = $mysqli->prepare("DELETE FROM hostel_issues WHERE id = ?");
            $stmt->bind_param('i', $issue_id);
            $stmt->execute();
            $stmt->close();
            header("Location: hostel_issues.php?hostel_type=$hostel_type");
            exit;
        } else {
            $error = 'Only the user who raised the issue can delete it.';
        }
    }
}

// Hostel and issue types
$hostel_types = ['boys' => 'Boys', 'girls' => 'Girls'];
$issue_types = [
    'wifi' => 'Wi-Fi not working',
    'water' => 'No water',
    'mess' => 'Mess food issue',
    'electricity' => 'Electricity problem',
    'cleanliness' => 'Cleanliness',
    'other' => 'Other',
];

// Determine selected hostel type (default to user's last selection or boys)
$selected_hostel = $student_hostel;

// Fetch active issues for selected hostel
$stmt = $mysqli->prepare("SELECT hi.*, 
    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id) as votes,
    (SELECT COUNT(*) FROM hostel_issue_votes v WHERE v.issue_id = hi.id AND v.user_id = ?) as user_voted
    FROM hostel_issues hi
    WHERE hi.hostel_type = ? AND hi.status != 'resolved'
    ORDER BY hi.created_at DESC");
$stmt->bind_param('is', $user_id, $selected_hostel);
$stmt->execute();
$issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel-Wide Complaints</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen font-['Inter']">
    <div class="max-w-lg mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4 text-blue-700">Hostel-Wide Complaints</h1>
        <form method="post" class="bg-white rounded-lg shadow p-4 mb-6 flex flex-col gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Hostel Type</label>
                <div class="p-2 bg-gray-100 rounded"><?= $hostel_types[$student_hostel] ?></div>
                <input type="hidden" name="hostel_type" value="<?= htmlspecialchars($student_hostel) ?>">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Issue Category</label>
                <select name="issue_type" class="w-full border rounded p-2" required>
                    <option value="">Select issue</option>
                    <?php foreach ($issue_types as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="action" value="new" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Report New Issue</button>
            <?php if (!empty($error)): ?>
                <div class="text-red-600 text-sm mt-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </form>

        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-semibold mb-3">Active Issues (<?= $hostel_types[$selected_hostel] ?> Hostel)</h2>
            <?php if ($issues): ?>
                <ul class="divide-y">
                    <?php foreach ($issues as $issue): ?>
                        <li class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <span class="font-medium text-gray-800"><?= $issue_types[$issue['issue_type']] ?? ucfirst($issue['issue_type']) ?></span>
                                <span class="ml-2 text-xs px-2 py-1 rounded bg-gray-100 text-gray-600">Status: <?= ucfirst(str_replace('_',' ',$issue['status'])) ?></span>
                            </div>
                            <div class="flex items-center gap-2 mt-2 sm:mt-0">
                                <span class="text-blue-700 font-bold text-lg"><?= $issue['votes'] ?></span>
                                <span class="text-gray-500 text-sm">vote(s)</span>
                                <?php
                                // Check if current user is the creator (first voter) of the issue
                                $stmt = $mysqli->prepare("SELECT user_id FROM hostel_issue_votes WHERE issue_id = ? ORDER BY vote_time ASC, id ASC LIMIT 1");
                                $stmt->bind_param('i', $issue['id']);
                                $stmt->execute();
                                $stmt->bind_result($creator_id);
                                $stmt->fetch();
                                $stmt->close();
                                ?>
                                <?php if ($creator_id == $user_id): ?>
                                    <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this hostel-wide complaint?');">
                                        <input type="hidden" name="hostel_type" value="<?= htmlspecialchars($selected_hostel) ?>">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <button type="submit" name="action" value="delete" class="ml-2 bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 text-sm">Delete</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($issue['user_voted'] == 0): ?>
                                    <form method="post" class="inline">
                                        <input type="hidden" name="hostel_type" value="<?= htmlspecialchars($selected_hostel) ?>">
                                        <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                        <button type="submit" name="action" value="vote" class="ml-2 bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 text-sm">Yes, I have this issue too</button>
                                    </form>
                                <?php else: ?>
                                    <span class="ml-2 text-green-700 text-xs font-semibold">Voted</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="text-gray-500 text-center py-6">No active issues for this hostel. Be the first to report!</div>
            <?php endif; ?>
        </div>
        
        <!-- Back to Dashboard Button -->
        <div class="mt-6 text-center">
            <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html> 