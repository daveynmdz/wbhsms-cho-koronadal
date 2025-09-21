<?php
// Employee logout with enhanced security
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '0') === '1';
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? '1' : '0');
error_reporting(E_ALL);

// Hide errors in production
if (!$debug) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CSRF protection for logout
$expected_token = $_SESSION['csrf_token'] ?? '';
$provided_token = $_POST['csrf_token'] ?? $_GET['token'] ?? '';

// Check if user is logged in
if (empty($_SESSION['employee_id'])) {
    // Not logged in, redirect to login
    header('Location: employee_login.php');
    exit;
}

// If this is a POST request (form submission) or GET with valid token
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (!empty($provided_token) && hash_equals($expected_token, $provided_token))) {
    
    // Log the logout event (optional - for audit trails)
    if (!empty($_SESSION['employee_username'])) {
        error_log('[employee_logout] User logged out: ' . $_SESSION['employee_username'] . ' from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    }
    
    // Clear all session data
    $_SESSION = array();
    
    // Delete the session cookie if it exists
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Start a new session for the flash message
    session_start();
    
    // Redirect to login with success message
    header('Location: employee_login.php?logged_out=1');
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
            
            <a href="../dashboard/dashboard_<?php echo strtolower($_SESSION['role'] ?? 'employee'); ?>.php" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</body>
</html>