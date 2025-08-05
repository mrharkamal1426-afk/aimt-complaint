<?php
require_once __DIR__.'/../includes/config.php';

// Preserve logout message if it exists
$logout_message = $_SESSION['logout_message'] ?? '';

session_unset();
session_destroy();

// Redirect to login page with logout message
if (!empty($logout_message)) {
    header('Location: login.php?logout_message=' . urlencode($logout_message));
} else {
    header('Location: login.php');
}
exit; 