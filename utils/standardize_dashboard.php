<?php
/**
 * Dashboard Standardization Script
 * 
 * This script helps standardize dashboard files by applying common patterns and fixes:
 * - Using absolute paths with $root_path
 * - Standardizing session management
 * - Preventing redirect loops
 * - Adding proper error logging
 * - Consistent sidebar inclusion
 * - Fixed CSS references
 */

// Display errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Path resolution
$root_path = dirname(__DIR__);

// Function to backup a file before modifying it
function backup_file($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $backup_path = $file_path . '.bak.' . date('YmdHis');
    return copy($file_path, $backup_path) ? $backup_path : false;
}

// Function to standardize a dashboard file
function standardize_dashboard($file_path, $role) {
    global $root_path;
    
    // Validate inputs
    if (empty($file_path) || empty($role)) {
        return [
            'success' => false,
            'message' => 'File path and role are required.'
        ];
    }
    
    // Check if file exists
    if (!file_exists($file_path)) {
        return [
            'success' => false,
            'message' => 'File not found: ' . $file_path
        ];
    }
    
    // Create backup
    $backup_path = backup_file($file_path);
    if (!$backup_path) {
        return [
            'success' => false,
            'message' => 'Failed to create backup of the original file.'
        ];
    }
    
    // Read the file
    $content = file_get_contents($file_path);
    if ($content === false) {
        return [
            'success' => false,
            'message' => 'Failed to read the file.'
        ];
    }
    
    // Transformations to apply:
    
    // 1. Ensure proper PHP opening
    if (!preg_match('/^<\?php/', $content)) {
        $content = "<?php\n// dashboard_{$role}.php\n" . $content;
    }
    
    // 2. Add root_path if not present
    if (!strpos($content, '$root_path')) {
        $content = preg_replace('/^(<\?php.*)$/m', 
            '$1' . "\n\$root_path = dirname(dirname(dirname(__DIR__)));\n", 
            $content, 1);
    }
    
    // 3. Include session management if not present
    if (!strpos($content, 'employee_session.php')) {
        $content = preg_replace('/^(\$root_path.*)$/m', 
            '$1' . "\nrequire_once \$root_path . '/config/session/employee_session.php';\n", 
            $content, 1);
    }
    
    // 4. Add error logging setup
    if (!strpos($content, 'error_reporting')) {
        $content = preg_replace('/^(require_once.*employee_session\.php.*)$/m', 
            '$1' . "\n\n// Set up proper error logging\nini_set('display_errors', 1);\nini_set('display_startup_errors', 1);\nerror_reporting(E_ALL);\n", 
            $content, 1);
    }
    
    // 5. Add session validation with redirect loop prevention if not present
    if (!strpos($content, '$_SESSION[\'redirect_attempts\']')) {
        $session_validation = <<<EOT

// Validate user session
if (!isset(\$_SESSION['employee_id']) || !isset(\$_SESSION['role'])) {
    error_log('{$role} Dashboard - Session invalid, employee_id or role not set');
    
    // Clear any redirect attempts that might cause loops
    \$_SESSION['redirect_attempts'] = (\$_SESSION['redirect_attempts'] ?? 0) + 1;
    
    if (\$_SESSION['redirect_attempts'] > 3) {
        // Too many redirects, show an error instead
        error_log('{$role} Dashboard - Too many redirect attempts, showing error');
        echo "Error: Session validation failed. Please <a href='{\$root_path}/logout.php'>logout</a> and login again.";
        exit();
    }
    
    header('Location: ' . \$root_path . '/pages/auth/employee_login.php');
    exit();
}

// Validate correct role
if (\$_SESSION['role'] !== '{$role}') {
    error_log('{$role} Dashboard - Invalid role: ' . \$_SESSION['role']);
    
    // Clear any redirect attempts that might cause loops
    \$_SESSION['redirect_attempts'] = (\$_SESSION['redirect_attempts'] ?? 0) + 1;
    
    if (\$_SESSION['redirect_attempts'] > 3) {
        // Too many redirects, show an error instead
        error_log('{$role} Dashboard - Too many redirect attempts, showing error');
        echo "Error: You don't have permission to access this page. Please <a href='{\$root_path}/logout.php'>logout</a> and login with appropriate credentials.";
        exit();
    }
    
    header('Location: ' . \$root_path . '/pages/auth/employee_login.php');
    exit();
}

// Reset redirect counter on successful validation
\$_SESSION['redirect_attempts'] = 0;

EOT;

        $content = preg_replace('/(error_reporting\(E_ALL\);.*)$/m', 
            '$1' . $session_validation, 
            $content, 1);
    }
    
    // 6. Add database connection if not present
    if (!strpos($content, 'config/db.php')) {
        $content = preg_replace('/(\$_SESSION\[\'redirect_attempts\'\] = 0;.*)$/m', 
            '$1' . "\n\n// Database connection\nrequire_once \$root_path . '/config/db.php';\n\$employee_id = \$_SESSION['employee_id'];\n", 
            $content, 1);
    }
    
    // 7. Fix CSS paths
    $content = preg_replace('/href=["\']\.\.\/\.\.\/assets\/css\/([^"\']*)["\']/', 
        'href="<?php echo $root_path; ?>/assets/css/$1"', 
        $content);
    
    // 8. Fix sidebar inclusion
    $content = preg_replace('/include [\'"]\.\.\/\.\.\/includes\/sidebar_' . $role . '\.php[\'"];/', 
        'include $root_path . \'/includes/sidebar_' . $role . '.php\';', 
        $content);
    
    // Write the updated content back to the file
    if (file_put_contents($file_path, $content) === false) {
        return [
            'success' => false,
            'message' => 'Failed to write the updated content to the file.'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Dashboard file successfully standardized.',
        'backup_path' => $backup_path
    ];
}

// Handle form submission
$result = null;
$file_path = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $file_path = $_POST['file_path'] ?? '';
    
    if (!empty($role) && !empty($file_path)) {
        // Convert to absolute path if relative
        if (!preg_match('/^[A-Za-z]:\\\\/', $file_path)) {
            $file_path = $root_path . '/' . ltrim($file_path, '/\\');
        }
        
        $result = standardize_dashboard($file_path, $role);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Standardization Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        pre {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .instructions {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>Dashboard Standardization Tool</h1>
    
    <div class="instructions">
        <h2>Instructions</h2>
        <p>This tool helps standardize dashboard files by applying common patterns and fixes:</p>
        <ul>
            <li>Using absolute paths with $root_path</li>
            <li>Standardizing session management</li>
            <li>Preventing redirect loops</li>
            <li>Adding proper error logging</li>
            <li>Consistent sidebar inclusion</li>
            <li>Fixed CSS references</li>
        </ul>
        <p><strong>Note:</strong> A backup of the original file will be created before any changes are made.</p>
    </div>
    
    <form method="post" action="">
        <div class="form-group">
            <label for="role">Role:</label>
            <input type="text" id="role" name="role" required placeholder="e.g., doctor, nurse, pharmacist" 
                   value="<?php echo htmlspecialchars($role); ?>">
        </div>
        
        <div class="form-group">
            <label for="file_path">Dashboard File Path:</label>
            <input type="text" id="file_path" name="file_path" required 
                   placeholder="e.g., pages/management/role/dashboard.php" 
                   value="<?php echo htmlspecialchars($file_path); ?>">
        </div>
        
        <button type="submit">Standardize Dashboard</button>
    </form>
    
    <?php if ($result): ?>
        <div class="result <?php echo $result['success'] ? 'success' : 'error'; ?>">
            <h3><?php echo $result['success'] ? 'Success!' : 'Error'; ?></h3>
            <p><?php echo htmlspecialchars($result['message']); ?></p>
            <?php if ($result['success'] && !empty($result['backup_path'])): ?>
                <p>A backup of the original file was created at: <code><?php echo htmlspecialchars($result['backup_path']); ?></code></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>