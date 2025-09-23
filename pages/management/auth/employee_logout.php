<?php
// Employee logout with enhanced security
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Include employee session configuration
require_once __DIR__ . '/../../../config/session/employee_session.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSRF protection for logout
$expected_token = $_SESSION['csrf_token'] ?? '';
$provided_token = $_POST['csrf_token'] ?? $_GET['token'] ?? '';

// Dynamic base URL function - same as in sidebar_admin.php
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $requestUri = $_SERVER['REQUEST_URI'];
    $uriParts = explode('/', trim($requestUri, '/'));
    
    $baseFolder = '';
    if (count($uriParts) > 0) {
        if (strpos($requestUri, '/management/') !== false || 
            strpos($requestUri, '/dashboard/') !== false ||
            strpos($requestUri, '/pages/') !== false) {
            $baseFolder = $uriParts[0];
        }
    }
    
    $baseUrl = $protocol . $host;
    if (!empty($baseFolder)) {
        $baseUrl .= '/' . $baseFolder;
    }
    
    return $baseUrl;
}

// Check if user is logged in
if (empty($_SESSION['employee_id'])) {
    // Not logged in, redirect to login - using dynamic path
    $baseUrl = getBaseUrl();
    header('Location: ' . $baseUrl . '/pages/management/auth/employee_login.php');
    exit;
}

// If this is a POST request (form submission) or GET with valid token
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (!empty($provided_token) && hash_equals($expected_token, $provided_token))) {
    
    // Log the logout event (optional - for audit trails)
    if (!empty($_SESSION['employee_username'])) {
        error_log('[employee_logout] User logged out: ' . $_SESSION['employee_username'] . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // Use the employee session clear function
    clear_employee_session();
    
    // Start a new session for the flash message
    session_start();
    
    // Use dynamic path to ensure this works in both local and production
    $baseUrl = getBaseUrl();
    header('Location: ' . $baseUrl . '/pages/management/auth/employee_login.php?logged_out=1');
    exit;
}

// If we get here, it's a GET request without proper token
// Show logout confirmation form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - CHO Employee Portal</title>
    <link rel="stylesheet" href="../../../assets/css/login.css">
    <style>
        .logout-form {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .logout-form h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .logout-form p {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background: #dc3545;
            color: white;
        }
        .btn-primary:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="logout-form">
        <h2>Confirm Logout</h2>
        <p>Are you sure you want to logout from the employee portal?</p>
        
        <div class="button-group">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($expected_token); ?>">
                <button type="submit" class="btn btn-primary">Yes, Logout</button>
            </form>
            
            <?php 
            $role = strtolower($_SESSION['role'] ?? 'admin');
            $baseUrl = getBaseUrl();
            $dashboardPath = $baseUrl . "/pages/management/{$role}/dashboard.php";
            ?>
            <a href="<?php echo $dashboardPath; ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</body>
</html>