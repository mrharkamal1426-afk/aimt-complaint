<?php
require_once __DIR__.'/../includes/config.php';

// Enable strict error reporting for mysqli to catch issues
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__.'/../includes/functions.php';

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);
$user_id = $_SESSION['user_id'];
$suggestion_id = $data['suggestion_id'] ?? null;
$vote_type = $data['vote_type'] ?? null;

// Validate inputs
if (!$suggestion_id || !in_array($vote_type, ['upvote', 'downvote'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

try {
    // Start transaction
    $mysqli->begin_transaction();

    // Check if user has already voted
    $stmt = $mysqli->prepare("SELECT vote_type FROM suggestion_votes WHERE suggestion_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $suggestion_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_vote = $result->fetch_assoc();
    $stmt->close();

    if ($existing_vote) {
        // If same vote type, remove the vote
        if ($existing_vote['vote_type'] === $vote_type) {
            $stmt = $mysqli->prepare("DELETE FROM suggestion_votes WHERE suggestion_id = ? AND user_id = ?");
            $stmt->bind_param('ii', $suggestion_id, $user_id);
            $stmt->execute();
            $stmt->close();
        } 
        // If different vote type, update the vote
        else {
            $stmt = $mysqli->prepare("UPDATE suggestion_votes SET vote_type = ? WHERE suggestion_id = ? AND user_id = ?");
            $stmt->bind_param('sii', $vote_type, $suggestion_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } 
    // If no existing vote, create new vote
    else {
        $stmt = $mysqli->prepare("INSERT INTO suggestion_votes (suggestion_id, user_id, vote_type) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $suggestion_id, $user_id, $vote_type);
        $stmt->execute();
        $stmt->close();
    }

    // Sync aggregate counts in suggestions table so that other modules
    // (e.g. admin dashboard) relying on the `upvotes` and `downvotes` columns
    // get up-to-date values without extra sub-queries.
    // Recalculate totals using separate queries to avoid MySQL
    // "You can't specify target table for update in FROM clause" error.
    $upvote_count   = 0;
    $downvote_count = 0;

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id = ? AND vote_type = 'upvote'");
    $stmt->bind_param('i', $suggestion_id);
    $stmt->execute();
    $stmt->bind_result($upvote_count);
    $stmt->fetch();
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM suggestion_votes WHERE suggestion_id = ? AND vote_type = 'downvote'");
    $stmt->bind_param('i', $suggestion_id);
    $stmt->execute();
    $stmt->bind_result($downvote_count);
    $stmt->fetch();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE suggestions SET upvotes = ?, downvotes = ? WHERE id = ?");
    $stmt->bind_param('iii', $upvote_count, $downvote_count, $suggestion_id);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $mysqli->rollback();
    header('Content-Type: application/json');
    // Return error details (in production consider logging instead)
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 