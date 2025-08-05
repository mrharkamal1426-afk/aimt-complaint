<?php
// Database Configuration
$conn = new mysqli(
  getenv('DB_HOST'),
  getenv('DB_USERNAME'),
  getenv('DB_PASSWORD'),
  getenv('DB_DATABASE')
);

// Establish connection
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        die('Database connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
