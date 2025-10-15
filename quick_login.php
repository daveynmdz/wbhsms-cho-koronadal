<?php
/**
 * Quick Employee Login for Testing Station Access
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('EMPLOYEE_SESSID');
    session_start();
}

$root_path = __DIR__;
require_once $root_path . '/config/db.php';

$message = '';
$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = $_POST['employee_number'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($employee_number)) {
        try {
            // Check for admin user (for testing)
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE employee_number = ? OR email = ? LIMIT 1");
            $stmt->execute([$employee_number, $employee_number]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                // For testing, accept any password for admin
                if ($employee['role'] === 'admin' || password_verify($password, $employee['password'] ?? '')) {
                    // Set session variables
                    $_SESSION['employee_id'] = $employee['employee_id'];
                    $_SESSION['role'] = $employee['role'];
                    $_SESSION['first_name'] = $employee['first_name'];
                    $_SESSION['last_name'] = $employee['last_name'];
                    $_SESSION['employee_number'] = $employee['employee_number'];
                    
                    // Redirect to triage station
                    header("Location: pages/queueing/triage_station.php");
                    exit();
                }
            }
            
            $error = "Invalid credentials";
            
        } catch (Exception $e) {
            $error = "Login error: " . $e->getMessage();
        }
    }
}

// Check if already logged in
if (!empty($_SESSION['employee_id'])) {
    $message = "Already logged in as: " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . " (" . $_SESSION['role'] . ")";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Employee Login - Test Station Access</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .links { margin-top: 20px; }
        .links a { display: block; margin: 5px 0; color: #007bff; text-decoration: none; }
        .links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h2>Quick Employee Login</h2>
    <p><small>For testing station access</small></p>
    
    <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="message success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if (empty($_SESSION['employee_id'])): ?>
        <form method="POST">
            <div class="form-group">
                <label for="employee_number">Employee Number or Email:</label>
                <input type="text" id="employee_number" name="employee_number" required 
                       placeholder="Enter employee number or email" 
                       value="<?php echo htmlspecialchars($_POST['employee_number'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Enter password">
            </div>
            
            <button type="submit">Login</button>
        </form>
        
        <div class="message info">
            <strong>Test Accounts:</strong><br>
            Try using any admin employee account from your database
        </div>
    <?php endif; ?>
    
    <div class="links">
        <h4>Quick Links (after login):</h4>
        <a href="pages/queueing/triage_station.php">Triage Station</a>
        <a href="pages/queueing/consultation_station.php">Consultation Station</a>
        <a href="pages/queueing/lab_station.php">Laboratory Station</a>
        <a href="pages/queueing/pharmacy_station.php">Pharmacy Station</a>
        <a href="pages/queueing/billing_station.php">Billing Station</a>
        <a href="pages/queueing/document_station.php">Document Station</a>
        <a href="pages/queueing/checkin.php">Check-in Station</a>
        
        <h4>Debug Links:</h4>
        <a href="test_triage_auth_bypass.php">Test Triage (Auth Bypass)</a>
        <a href="debug_tables.php">Check Database Tables</a>
    </div>
    
    <?php if (!empty($_SESSION['employee_id'])): ?>
        <div class="links">
            <a href="?logout=1" onclick="return confirm('Logout?')">Logout</a>
        </div>
        
        <?php if (isset($_GET['logout'])): ?>
            <?php session_destroy(); header("Location: quick_login.php"); exit(); ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>