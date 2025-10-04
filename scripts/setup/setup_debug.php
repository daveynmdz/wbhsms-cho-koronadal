<?php
/**
 * Comprehensive Session Debug Utility
 * 
 * This file helps diagnose session-related issues in the system.
 * Place at root of project for easy access.
 */

// Start with clean output
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Create a safe function to display session data without breaking it
function safe_print_r($var, $mask_sensitive = true) {
    $output = print_r($var, true);
    
    // Mask sensitive data if requested
    if ($mask_sensitive && is_array($var)) {
        foreach (['password', 'csrf_token', 'auth_token'] as $sensitive) {
            if (isset($var[$sensitive])) {
                $pattern = '/\[' . $sensitive . '\] => (.*)/';
                $output = preg_replace($pattern, '[$1] => ***MASKED***', $output);
            }
        }
    }
    
    return $output;
}

// Page styling
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Debug Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #2980b9;
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        pre {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            overflow: auto;
            border-radius: 4px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .warning {
            color: orange;
        }
        .actions {
            margin: 20px 0;
            padding: 10px;
            background: #eaf2f8;
            border-radius: 4px;
        }
        .actions a {
            display: inline-block;
            margin-right: 15px;
            padding: 8px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .actions a:hover {
            background: #2980b9;
        }
        .actions a.warning {
            background: #e74c3c;
        }
        .actions a.warning:hover {
            background: #c0392b;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .collapsible {
            cursor: pointer;
            padding: 10px;
            background-color: #f1f1f1;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 15px;
            margin-bottom: 1px;
        }
        .active, .collapsible:hover {
            background-color: #ddd;
        }
        .content {
            padding: 0 18px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease-out;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>';

echo '<h1>WBHSMS Session Debug Utility</h1>';

// Display messages if present
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $type = htmlspecialchars($_GET['type']);
    echo '<div class="message ' . $type . '">' . $message . '</div>';
}

// Action buttons
echo '<div class="actions">
    <a href="?action=clear_session">Clear Session</a>
    <a href="?action=regenerate_id">Regenerate Session ID</a>
    <a href="?action=view_session">View Session</a>
    <a href="?action=test_login_redirect">Test Login Redirect</a>
    <a href="../../index.php">Go to Homepage</a>
    <a href="../../pages/management/auth/employee_login.php">Go to Login Page</a>
    <a href="?action=logout" class="warning">Logout</a>
</div>';

// Process Actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch($action) {
        case 'clear_session':
            session_start();
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: setup_debug.php?message=Session cleared successfully&type=success');
            exit;
            break;
            
        case 'regenerate_id':
            session_start();
            session_regenerate_id(true);
            header('Location: setup_debug.php?message=Session ID regenerated successfully&type=success');
            exit;
            break;
            
        case 'logout':
            session_start();
            $_SESSION = array();
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            session_destroy();
            header('Location: pages/management/auth/employee_login.php?logged_out=1');
            exit;
            break;
            
        case 'test_login_redirect':
            // Set a minimal test session
            session_start();
            $_SESSION['employee_id'] = 9999;
            $_SESSION['role'] = 'doctor';
            $_SESSION['employee_number'] = 'TEST9999';
            $_SESSION['employee_first_name'] = 'Test';
            $_SESSION['employee_last_name'] = 'User';
            header('Location: pages/management/doctor/dashboard.php');
            exit;
            break;
    }
}

// Ensure we're using the right session
if (session_status() === PHP_SESSION_NONE) {
    session_name('EMPLOYEE_SESSID');
    session_start();
}

// Show session status
echo '<h2>Session Status</h2>';
$status_map = [
    PHP_SESSION_DISABLED => '<span class="error">PHP_SESSION_DISABLED (Sessions disabled)</span>',
    PHP_SESSION_NONE => '<span class="warning">PHP_SESSION_NONE (No session started)</span>',
    PHP_SESSION_ACTIVE => '<span class="success">PHP_SESSION_ACTIVE (Session active)</span>'
];

echo '<table>
    <tr><th>Property</th><th>Value</th></tr>
    <tr><td>Session status</td><td>' . $status_map[session_status()] . '</td></tr>
    <tr><td>Session name</td><td>' . session_name() . '</td></tr>
    <tr><td>Session ID</td><td>' . session_id() . '</td></tr>
    <tr><td>Session save path</td><td>' . session_save_path() . '</td></tr>
</table>';

// Show session data in a collapsible section
echo '<h2>Session Data</h2>';
echo '<button type="button" class="collapsible">Click to ' . (empty($_SESSION) ? 'see (empty)' : 'expand') . '</button>';
echo '<div class="content">';
if (empty($_SESSION)) {
    echo "<p class='warning'>No session data found.</p>";
} else {
    echo "<pre>" . safe_print_r($_SESSION) . "</pre>";
}
echo '</div>';

// Show cookie information
echo '<h2>Cookie Information</h2>';
echo '<button type="button" class="collapsible">Click to ' . (empty($_COOKIE) ? 'see (empty)' : 'expand') . '</button>';
echo '<div class="content">';
if (empty($_COOKIE)) {
    echo "<p class='warning'>No cookies found.</p>";
} else {
    echo "<pre>";
    // Mask session IDs for security
    $cookies = $_COOKIE;
    foreach ($cookies as $name => $value) {
        if (strpos($name, 'SESSID') !== false) {
            $cookies[$name] = substr($value, 0, 5) . '...[masked]...';
        }
    }
    echo safe_print_r($cookies);
    echo "</pre>";
}
echo '</div>';

// Show server variables related to session
echo '<h2>Server Variables</h2>';
echo '<button type="button" class="collapsible">Click to expand</button>';
echo '<div class="content">';
$relevant_vars = [
    'SCRIPT_NAME', 'REQUEST_URI', 'QUERY_STRING', 'REQUEST_METHOD',
    'DOCUMENT_ROOT', 'HTTP_HOST', 'HTTP_REFERER', 'HTTP_USER_AGENT',
    'REMOTE_ADDR', 'REMOTE_PORT', 'PHP_SELF'
];

echo "<table>";
echo "<tr><th>Variable</th><th>Value</th></tr>";
foreach ($relevant_vars as $var) {
    if (isset($_SERVER[$var])) {
        echo "<tr><td>$var</td><td>" . htmlspecialchars($_SERVER[$var]) . "</td></tr>";
    }
}
echo "</table>";
echo '</div>';

// Database connection test
echo '<h2>Database Connection Test</h2>';
echo '<button type="button" class="collapsible">Click to run test</button>';
echo '<div class="content">';
try {
    require_once 'config/db.php';
    
    echo "<table>";
    echo "<tr><th>Connection</th><th>Status</th></tr>";
    
    // Test MySQLi connection
    if (isset($conn)) {
        if (!$conn->connect_error) {
            echo "<tr><td>MySQLi Connection</td><td><span class='success'>Connected successfully</span></td></tr>";
            
            // Test basic query
            $result = $conn->query("SELECT DATABASE() as db");
            if ($result && $row = $result->fetch_assoc()) {
                echo "<tr><td>Active Database</td><td><span class='success'>" . $row['db'] . "</span></td></tr>";
            }
            
            // Test employees table
            $result = $conn->query("SHOW TABLES LIKE 'employees'");
            echo "<tr><td>Employees Table</td><td>";
            if ($result && $result->num_rows > 0) {
                echo "<span class='success'>Exists</span>";
                
                // Check employee count
                $result = $conn->query("SELECT COUNT(*) as count FROM employees");
                if ($result && $row = $result->fetch_assoc()) {
                    echo " (" . $row['count'] . " records)";
                }
            } else {
                echo "<span class='error'>Missing</span>";
            }
            echo "</td></tr>";
            
            // Test roles table
            $result = $conn->query("SHOW TABLES LIKE 'roles'");
            echo "<tr><td>Roles Table</td><td>";
            if ($result && $result->num_rows > 0) {
                echo "<span class='success'>Exists</span>";
                
                // Show available roles
                $result = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_id");
                if ($result && $result->num_rows > 0) {
                    echo "<ul>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<li>ID: " . $row['role_id'] . " - " . $row['role_name'] . "</li>";
                    }
                    echo "</ul>";
                }
            } else {
                echo "<span class='error'>Missing</span>";
            }
            echo "</td></tr>";
            
        } else {
            echo "<tr><td>MySQLi Connection</td><td><span class='error'>Failed: " . $conn->connect_error . "</span></td></tr>";
        }
    } else {
        echo "<tr><td>MySQLi Connection</td><td><span class='warning'>Not initialized</span></td></tr>";
    }
    
    // Test PDO connection
    if (isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT 1");
            echo "<tr><td>PDO Connection</td><td><span class='success'>Connected successfully</span></td></tr>";
        } catch (PDOException $e) {
            echo "<tr><td>PDO Connection</td><td><span class='error'>Failed: " . $e->getMessage() . "</span></td></tr>";
        }
    } else {
        echo "<tr><td>PDO Connection</td><td><span class='warning'>Not initialized</span></td></tr>";
    }
    
    echo "</table>";
} catch (Exception $e) {
    echo "<p class='error'>Error testing database connection: " . $e->getMessage() . "</p>";
}
echo '</div>';

// Session configuration
echo '<h2>PHP Session Configuration</h2>';
echo '<button type="button" class="collapsible">Click to expand</button>';
echo '<div class="content">';
echo "<table>";
echo "<tr><th>Directive</th><th>Value</th></tr>";

$session_directives = [
    'session.save_handler', 'session.save_path', 'session.use_cookies',
    'session.use_only_cookies', 'session.name', 'session.auto_start',
    'session.cookie_lifetime', 'session.cookie_path', 'session.cookie_domain',
    'session.cookie_httponly', 'session.cookie_samesite', 'session.use_strict_mode',
    'session.use_trans_sid', 'session.cache_limiter', 'session.cache_expire',
    'session.gc_maxlifetime', 'session.gc_probability', 'session.gc_divisor'
];

foreach ($session_directives as $directive) {
    echo "<tr><td>$directive</td><td>" . ini_get($directive) . "</td></tr>";
}
echo "</table>";
echo '</div>';

// Add JavaScript for collapsible sections
echo '<script>
var coll = document.getElementsByClassName("collapsible");
var i;

for (i = 0; i < coll.length; i++) {
  coll[i].addEventListener("click", function() {
    this.classList.toggle("active");
    var content = this.nextElementSibling;
    if (content.style.maxHeight) {
      content.style.maxHeight = null;
    } else {
      content.style.maxHeight = content.scrollHeight + "px";
    }
  });
}
</script>';

echo '</body></html>';

// Clear output buffer and send
$output = ob_get_clean();
echo $output;