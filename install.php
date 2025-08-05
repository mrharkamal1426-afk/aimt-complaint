<?php
// Secure Installation Script
// This script should be deleted after successful installation

// Error reporting for debugging during installation
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';
$success = '';
$permission_results = [];
$installation_log = [];
$needs_installation = false;
$existing_config = [
    'host' => '',
    'user' => '',
    'pass' => '',
    'name' => ''
];
$existing_config = [
    'host' => '',
    'user' => '',
    'pass' => '',
    'name' => ''
];

// Function to log installation steps
function log_installation_step($message, $type = 'info') {
    global $installation_log;
    $installation_log[] = [
        'time' => date('Y-m-d H:i:s'),
        'type' => $type,
        'message' => $message
    ];
}

// Function to check if all required tables exist and have correct structure
function checkDatabaseTables($mysqli) {
    $required_tables = [
        'users' => ['id', 'full_name', 'phone', 'role', 'username', 'password_hash'],
        'complaints' => ['id', 'user_id', 'token', 'category', 'description', 'status'],
        'complaint_history' => ['id', 'complaint_id', 'status', 'note'],
        'special_codes' => ['code', 'role', 'generated_by'],
        'hostel_issues' => ['id', 'hostel_type', 'issue_type', 'status'],
        'hostel_issue_votes' => ['id', 'issue_id', 'user_id'],
        'suggestions' => ['id', 'user_id', 'suggestion'],
        'user_notifications' => ['id', 'user_id', 'type']
    ];

    $missing_tables = [];
    $missing_columns = [];

    foreach ($required_tables as $table => $required_columns) {
        // Check if table exists
        $result = $mysqli->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $missing_tables[] = $table;
            continue;
        }

        // Check columns
        $result = $mysqli->query("SHOW COLUMNS FROM `$table`");
        $existing_columns = [];
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }

        foreach ($required_columns as $column) {
            if (!in_array($column, $existing_columns)) {
                $missing_columns[] = "$table.$column";
            }
        }
    }

    return [
        'missing_tables' => $missing_tables,
        'missing_columns' => $missing_columns
    ];
}

// Check existing configuration
if (file_exists('includes/config.php')) {
    // Include the config file but suppress any errors
    @include 'includes/config.php';
    $existing_config = [
        'host' => defined('DB_HOST') ? DB_HOST : '',
        'user' => defined('DB_USER') ? DB_USER : '',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
        'name' => defined('DB_NAME') ? DB_NAME : ''
    ];

    // Only try to connect if we have all required values
    if (!empty($existing_config['host']) && !empty($existing_config['user']) && !empty($existing_config['name'])) {
        try {
            $test_mysqli = @new mysqli(
                $existing_config['host'],
                $existing_config['user'],
                $existing_config['pass'],
                $existing_config['name']
            );

            if ($test_mysqli->connect_error) {
                $error = "Database connection failed: " . $test_mysqli->connect_error;
                $needs_installation = true;
            } else {
                // Check tables
                $check_result = checkDatabaseTables($test_mysqli);
                if (!empty($check_result['missing_tables']) || !empty($check_result['missing_columns'])) {
                    $error = "Database structure incomplete.<br>";
                    if (!empty($check_result['missing_tables'])) {
                        $error .= "Missing tables: " . implode(', ', $check_result['missing_tables']) . "<br>";
                    }
                    if (!empty($check_result['missing_columns'])) {
                        $error .= "Missing columns: " . implode(', ', $check_result['missing_columns']);
                    }
                    $needs_installation = true;
                }
                $test_mysqli->close();
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
            $needs_installation = true;
        }
    } else {
        $error = "Invalid configuration found.";
        $needs_installation = true;
    }
} else {
    $error = "Configuration file not found.";
    $needs_installation = true;
}



// Function to set file permissions
function setFilePermissions($path, $permissions = 0644) {
    if (is_file($path)) {
        return chmod($path, $permissions);
    }
    return false;
}

// Function to set directory permissions
function setDirectoryPermissions($path, $permissions = 0755) {
    if (is_dir($path)) {
        return chmod($path, $permissions);
    }
    return false;
}

// Function to validate schema
function validateSchema($schema_content) {
    $errors = [];
    
    // Required tables with their minimum required columns
    $required_tables = [
        'users' => ['id', 'full_name', 'phone', 'role', 'username', 'password_hash'],
        'complaints' => ['id', 'user_id', 'token', 'category', 'description', 'status'],
        'complaint_history' => ['id', 'complaint_id', 'status', 'note'],
        'special_codes' => ['code', 'role', 'generated_by'],
        'hostel_issues' => ['id', 'hostel_type', 'issue_type', 'status'],
        'hostel_issue_votes' => ['id', 'issue_id', 'user_id'],
        'suggestions' => ['id', 'user_id', 'suggestion'],
        'user_notifications' => ['id', 'user_id', 'type']
    ];
    
    foreach ($required_tables as $table => $required_columns) {
        if (stripos($schema_content, "CREATE TABLE") === false || 
            stripos($schema_content, "CREATE TABLE IF NOT EXISTS `$table`") === false) {
            $errors[] = "Missing required table: $table";
        }
    }
    
    // Check for trigger definition
    $trigger_sql = @file_get_contents('trigger.sql');
    if (!$trigger_sql || stripos($trigger_sql, 'CREATE TRIGGER') === false) {
        $errors[] = "Missing trigger definition";
    }
    
    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim($_POST['db_host'] ?? '');
    $db_user = trim($_POST['db_user'] ?? '');
    $db_pass = $_POST['db_pass'] ?? '';
    $db_name = trim($_POST['db_name'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $drop_existing = isset($_POST['drop_existing']) && $_POST['drop_existing'] === '1';
    
    // Validation
    if (empty($db_host) || empty($db_user) || empty($db_name) || empty($admin_password)) {
        $error = 'All fields are required.';
    } elseif (strlen($admin_password) < 8) {
        $error = 'Admin password must be at least 8 characters long.';
    } else {
        try {
            log_installation_step("Starting installation process");
            
            // Read and validate schema
            $schema = file_get_contents('schema.sql');
            if (!$schema) {
                throw new Exception("Could not read schema.sql file.");
            }
            
            // Remove BOM if present and clean the schema
            $schema = preg_replace('/^\xEF\xBB\xBF/', '', $schema); // Remove BOM
            $schema = preg_replace('/--[^\n]*/', '', $schema); // Remove comments
            $schema = preg_replace('/\s+/', ' ', $schema); // Normalize whitespace
            $schema = trim($schema); // Remove leading/trailing whitespace
            
            log_installation_step("Validating schema structure");
            $schema_errors = validateSchema($schema);
            if (!empty($schema_errors)) {
                throw new Exception("Schema validation failed: " . implode(', ', $schema_errors));
            }
            
            // Test database connection
            log_installation_step("Testing database connection");
            $mysqli = new mysqli($db_host, $db_user, $db_pass);
            if ($mysqli->connect_error) {
                throw new Exception("Database connection failed: " . $mysqli->connect_error);
            }
            
            // Create database if it doesn't exist
            log_installation_step("Creating database if not exists");
            if (!$mysqli->query("CREATE DATABASE IF NOT EXISTS `$db_name`")) {
                throw new Exception("Could not create database: " . $mysqli->error);
            }
            
            // Select the database
            if (!$mysqli->select_db($db_name)) {
                throw new Exception("Could not select database: " . $mysqli->error);
            }
            
            // Check if tables exist
            log_installation_step("Checking existing tables");
            $tables_exist = false;
            $result = $mysqli->query("SHOW TABLES");
            if ($result && $result->num_rows > 0) {
                $tables_exist = true;
            }
            
            if ($tables_exist && !$drop_existing) {
                throw new Exception("Database tables already exist. Check 'Drop existing tables' if you want to reinstall.");
            }
            
            // Drop existing tables if requested
            if ($drop_existing && $tables_exist) {
                log_installation_step("Dropping existing tables");
                $drop_order = [
                    'user_notifications',
                    'hostel_issue_votes',
                    'suggestions',
                    'complaint_history',
                    'hostel_issues',
                    'complaints',
                    'special_codes',
                    'users'
                ];
                
                foreach ($drop_order as $table) {
                    $mysqli->query("DROP TABLE IF EXISTS `$table`");
                }
            }
            
            // Execute SQL statements
            $statements = array_filter(
                explode(';', $schema),
                function($sql) {
                    return trim($sql) !== '';
                }
            );
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        if (!$mysqli->query($statement)) {
                            throw new Exception($mysqli->error);
                        }
                    } catch (Exception $e) {
                        throw new Exception("SQL Error: " . $e->getMessage() . "\nIn statement:\n" . $statement);
                    }
                }
            }
            
            // Create trigger separately
            log_installation_step("Creating database trigger");
            $trigger_sql = file_get_contents('trigger.sql');
            if ($trigger_sql === false) {
                throw new Exception("Could not read trigger.sql file");
            }
            
            if (!$mysqli->multi_query($trigger_sql)) {
                throw new Exception("Failed to create trigger: " . $mysqli->error);
            }
            
            // Handle results from multi_query
            do {
                if ($result = $mysqli->store_result()) {
                    $result->free();
                }
            } while ($mysqli->more_results() && $mysqli->next_result());
            
            // Create includes directory if it doesn't exist
            log_installation_step("Creating includes directory if not exists");
            if (!is_dir('includes')) {
                if (!mkdir('includes', 0755, true)) {
                    throw new Exception("Could not create includes directory.");
                }
                $permission_results[] = "✓ Created includes directory";
            }
            
            // Create config file
            log_installation_step("Creating configuration file");
            $config_content = "<?php
// Database Configuration
define('DB_HOST', '" . addslashes($db_host) . "');
define('DB_USER', '" . addslashes($db_user) . "');
define('DB_PASS', '" . addslashes($db_pass) . "');
define('DB_NAME', '" . addslashes($db_name) . "');

// Establish connection
try {
    \$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (\$mysqli->connect_error) {
        die('Database connection failed: ' . \$mysqli->connect_error);
    }
    \$mysqli->set_charset('utf8mb4');
} catch (Exception \$e) {
    die('Database connection failed: ' . \$e->getMessage());
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>";
            
            if (file_put_contents('includes/config.php', $config_content) === false) {
                throw new Exception("Could not create config file.");
            }
            $permission_results[] = "✓ Created config.php file";

            // Create .htaccess in includes directory to prevent direct access
            $htaccess_content = "# Block direct access to all files in this directory
Order allow,deny
Deny from all";
            
            if (file_put_contents('includes/.htaccess', $htaccess_content) === false) {
                throw new Exception("Could not create .htaccess file in includes directory.");
            }
            $permission_results[] = "✓ Created .htaccess in includes directory";

            // Create functions.php if it doesn't exist
            if (!file_exists('includes/functions.php')) {
                $functions_content = "<?php
// Common functions used throughout the application

// Redirect helper function
function redirect(\$url) {
    header('Location: ' . \$url);
    exit();
}

// Sanitize input helper
function sanitize_input(\$input) {
    return htmlspecialchars(trim(\$input), ENT_QUOTES, 'UTF-8');
}

// Generate random token
function generate_token(\$length = 32) {
    return bin2hex(random_bytes(\$length));
}

// Check if user is logged in
function is_logged_in() {
    return isset(\$_SESSION['user_id']) && !empty(\$_SESSION['user_id']);
}

// Check user role
function has_role(\$required_role) {
    return isset(\$_SESSION['role']) && \$_SESSION['role'] === \$required_role;
}

// Format date
function format_date(\$date) {
    return date('M j, Y g:i A', strtotime(\$date));
}
?>";
                if (file_put_contents('includes/functions.php', $functions_content) === false) {
                    throw new Exception("Could not create functions.php file.");
                }
                $permission_results[] = "✓ Created functions.php file";
            }

            // Create hostel_issue_functions.php if it doesn't exist
            if (!file_exists('includes/hostel_issue_functions.php')) {
                $hostel_functions_content = "<?php
// Functions specific to hostel issues

// Get total votes for an issue
function get_issue_votes(\$issue_id) {
    global \$mysqli;
    \$stmt = \$mysqli->prepare('SELECT COUNT(*) FROM hostel_issue_votes WHERE issue_id = ?');
    \$stmt->bind_param('i', \$issue_id);
    \$stmt->execute();
    \$result = \$stmt->get_result();
    return \$result->fetch_row()[0];
}

// Check if user has voted
function has_user_voted(\$issue_id, \$user_id) {
    global \$mysqli;
    \$stmt = \$mysqli->prepare('SELECT 1 FROM hostel_issue_votes WHERE issue_id = ? AND user_id = ?');
    \$stmt->bind_param('ii', \$issue_id, \$user_id);
    \$stmt->execute();
    return \$stmt->get_result()->num_rows > 0;
}

// Get hostel issues with vote counts
function get_hostel_issues_with_votes(\$hostel_type = null) {
    global \$mysqli;
    \$sql = 'SELECT hi.*, COUNT(hiv.id) as vote_count 
             FROM hostel_issues hi 
             LEFT JOIN hostel_issue_votes hiv ON hi.id = hiv.issue_id';
    if (\$hostel_type) {
        \$sql .= ' WHERE hi.hostel_type = ?';
    }
    \$sql .= ' GROUP BY hi.id ORDER BY vote_count DESC';
    
    \$stmt = \$mysqli->prepare(\$sql);
    if (\$hostel_type) {
        \$stmt->bind_param('s', \$hostel_type);
    }
    \$stmt->execute();
    return \$stmt->get_result();
}
?>";
                if (file_put_contents('includes/hostel_issue_functions.php', $hostel_functions_content) === false) {
                    throw new Exception("Could not create hostel_issue_functions.php file.");
                }
                $permission_results[] = "✓ Created hostel_issue_functions.php file";
            }
            
            // Create superadmin user
            log_installation_step("Creating superadmin account");
            $admin_username = 'admin';
            $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $admin_query = "INSERT INTO users (full_name, phone, role, special_code, username, password_hash) 
                           VALUES ('System Administrator', '0000000000', 'superadmin', 'ADMIN001', ?, ?)";
            $stmt = $mysqli->prepare($admin_query);
            if (!$stmt) {
                throw new Exception("Failed to prepare admin user creation: " . $mysqli->error);
            }
            $stmt->bind_param('ss', $admin_username, $password_hash);
            
            if (!$stmt->execute()) {
                throw new Exception("Could not create admin user: " . $stmt->error);
            }
            
            // Set file permissions
            log_installation_step("Setting file permissions");
            $files_to_set = [
                'includes/config.php' => 0644,
                'includes/functions.php' => 0644,
                'includes/hostel_issue_functions.php' => 0644,
                'index.php' => 0644,
                'login.php' => 0644,
                'register.php' => 0644,
                'logout.php' => 0644,
                'forgot_password.php' => 0644,
                '.htaccess' => 0644,
                'includes/.htaccess' => 0644,
                'assets/.htaccess' => 0644,
                'logs/.htaccess' => 0644
            ];
            
            $directories_to_set = [
                'includes' => 0755,
                'assets' => 0755,
                'assets/css' => 0755,
                'assets/js' => 0755,
                'assets/images' => 0755,
                'logs' => 0755,
                'user' => 0755,
                'technician' => 0755,
                'superadmin' => 0755,
                'templates' => 0755
            ];
            
            foreach ($files_to_set as $file => $permission) {
                if (file_exists($file)) {
                    if (setFilePermissions($file, $permission)) {
                        $permission_results[] = "✓ Set permissions for $file";
                    } else {
                        $permission_results[] = "⚠ Could not set permissions for $file";
                    }
                }
            }
            
            foreach ($directories_to_set as $dir => $permission) {
                if (is_dir($dir)) {
                    if (setDirectoryPermissions($dir, $permission)) {
                        $permission_results[] = "✓ Set permissions for directory $dir";
                    } else {
                        $permission_results[] = "⚠ Could not set permissions for directory $dir";
                    }
                }
            }
            
            // Create logs directory if it doesn't exist
            if (!is_dir('logs')) {
                if (mkdir('logs', 0755, true)) {
                    $permission_results[] = "✓ Created logs directory";
                } else {
                    $permission_results[] = "⚠ Could not create logs directory";
                }
            }
            
            log_installation_step("Installation completed successfully", "success");
            $success = 'Installation completed successfully! You can now login with admin/' . htmlspecialchars($admin_password);
            
        } catch (Exception $e) {
            log_installation_step("Installation failed: " . $e->getMessage(), "error");
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Portal Installation</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 800px; 
            margin: 50px auto; 
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold;
            color: #333;
        }
        input[type="text"], 
        input[type="password"] { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            font-size: 16px;
        }
        input[type="checkbox"] {
            margin-right: 8px;
        }
        button { 
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        button:hover { 
            background: #0056b3; 
        }
        .error { 
            color: #dc3545; 
            padding: 15px;
            margin-bottom: 20px;
            background: #ffe6e6;
            border-radius: 4px;
        }
        .success { 
            color: #28a745;
            padding: 15px;
            margin-bottom: 20px;
            background: #e6ffe6;
            border-radius: 4px;
        }
        .warning { 
            background: #fff3cd; 
            border: 1px solid #ffeeba; 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px;
            color: #856404;
        }
        .permission-results { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 15px 0; 
            max-height: 300px; 
            overflow-y: auto;
        }
        .permission-results ul { 
            margin: 0; 
            padding-left: 20px; 
        }
        .permission-results li { 
            margin-bottom: 5px; 
        }
        .installation-log {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            max-height: 200px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 14px;
        }
        .log-entry {
            margin-bottom: 5px;
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .log-entry.error { color: #dc3545; }
        .log-entry.success { color: #28a745; }
        .log-entry.info { color: #17a2b8; }
        .log-time {
            color: #6c757d;
            margin-right: 10px;
        }
        .status-box {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .status-error {
            background: #ffe6e6;
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        .status-success {
            background: #e6ffe6;
            border: 1px solid #28a745;
            color: #28a745;
        }
        .existing-config {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
    <h1>Complaint Portal Installation</h1>
    
    <div class="warning">
        <strong>Security Warning:</strong> This installation script should be deleted immediately after successful installation.
    </div>
    
        <?php if (!$needs_installation): ?>
            <div class="status-box status-success">
                <h3>✓ System is properly installed</h3>
                <p>All database tables and configurations are correct. You can proceed to use the system.</p>
                <p><strong>Important:</strong> Please delete this install.php file now for security!</p>
                <p><a href="index.php" class="button">Go to Homepage</a></p>
            </div>
        <?php else: ?>
    <?php if ($error): ?>
                <div class="status-box status-error">
                    <h3>System needs installation</h3>
                    <p><?php echo $error; ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($existing_config)): ?>
                <div class="existing-config">
                    <h3>Existing Configuration</h3>
                    <p>Host: <?php echo htmlspecialchars($existing_config['host']); ?></p>
                    <p>User: <?php echo htmlspecialchars($existing_config['user']); ?></p>
                    <p>Database: <?php echo htmlspecialchars($existing_config['name']); ?></p>
                </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
                
                <?php if (!empty($installation_log)): ?>
                    <div class="installation-log">
                        <h3>Installation Log:</h3>
                        <?php foreach ($installation_log as $log): ?>
                            <div class="log-entry <?php echo $log['type']; ?>">
                                <span class="log-time">[<?php echo $log['time']; ?>]</span>
                                <?php echo htmlspecialchars($log['message']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
        
        <?php if (!empty($permission_results)): ?>
            <div class="permission-results">
                <h3>Permission Settings:</h3>
                <ul>
                    <?php foreach ($permission_results as $result): ?>
                        <li><?php echo htmlspecialchars($result); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <p><strong>Important:</strong> Please delete this install.php file now for security!</p>
    <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">Database Username:</label>
                <input type="text" id="db_user" name="db_user" value="root" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass" placeholder="Enter your database password" value="<?php echo !empty($existing_config['pass']) ? htmlspecialchars($existing_config['pass']) : ''; ?>">
                <small style="color: #666; display: block; margin-top: 5px;">Enter the password for your MySQL user account</small>
            </div>
            
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="complaint_portal" required>
            </div>
            
            <div class="form-group">
                <label for="admin_password">Admin Password (min 8 characters):</label>
                <input type="password" id="admin_password" name="admin_password" required>
            </div>
                    
            <div class="form-group">
                <label style="display: flex; align-items: center; font-weight: normal;">
                    <input type="checkbox" id="drop_existing" name="drop_existing" value="1">
                    Drop existing tables (use this if tables already exist)
                </label>
            </div>
            
            <button type="submit">Install System</button>
        </form>
    <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html> 