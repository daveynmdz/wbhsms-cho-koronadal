<?php
/**
 * Dashboard Standardization Utility
 * 
 * This script helps standardize dashboard files across the system by applying
 * consistent patterns for absolute paths, session handling, and proper includes.
 * 
 * Usage: 
 * - Copy this file to the root directory of your project
 * - Run it from the command line: php update_dashboard_template.php path/to/dashboard.php role_name
 * - Or include it in another PHP file and call the function directly
 * 
 * @author CHO Koronadal Web Development Team
 * @version 1.0
 */

// Prevent direct access if this file is accessed via a web browser
if (php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_URI'])) {
    header('HTTP/1.0 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    echo '<p>This script can only be run from the command line or included in another PHP file.</p>';
    exit;
}

/**
 * Standardize a dashboard file
 * 
 * @param string $filePath The path to the dashboard file to standardize
 * @param string $role The role name for this dashboard (e.g., 'admin', 'doctor', 'nurse')
 * @param bool $backup Whether to create a backup of the original file (default: true)
 * @return bool True on success, false on failure
 */
function standardize_dashboard($filePath, $role, $backup = true) {
    // Check if the file exists
    if (!file_exists($filePath)) {
        echo "Error: File not found: {$filePath}\n";
        return false;
    }

    // Make a backup if requested
    if ($backup) {
        $backupPath = $filePath . '.bak.' . date('Ymd-His');
        if (!copy($filePath, $backupPath)) {
            echo "Warning: Failed to create backup file {$backupPath}\n";
        } else {
            echo "Backup created: {$backupPath}\n";
        }
    }

    // Read the current file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "Error: Failed to read file {$filePath}\n";
        return false;
    }

    // Calculate the proper role for the session check (lowercase)
    $roleLower = strtolower($role);
    
    // Generate the standard header with proper path handling and session management
    $standardHeader = <<<EOT
<?php
// dashboard_{$roleLower}.php
// Using the same approach as admin dashboard for consistency
\$root_path = dirname(dirname(dirname(__DIR__)));
require_once \$root_path . '/config/session/employee_session.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Authentication check - refactored to eliminate redirect loops
// Check 1: Is the user logged in at all?
if (!isset(\$_SESSION['employee_id']) || empty(\$_SESSION['employee_id'])) {
    // User is not logged in - redirect to login
    error_log('{$role} Dashboard: No session found, redirecting to login');
    header('Location: ../auth/employee_login.php');
    exit();
}

// Check 2: Does the user have the correct role?
if (!isset(\$_SESSION['role']) || strtolower(\$_SESSION['role']) !== '{$roleLower}') {
    // User is logged in but has wrong role - log and redirect
    error_log('Access denied to {$roleLower} dashboard - User: ' . \$_SESSION['employee_id'] . ' with role: ' . 
              (\$_SESSION['role'] ?? 'none'));
    
    // Clear any redirect loop detection
    unset(\$_SESSION['redirect_attempt']);
    
    // Return to login with access denied message
    \$_SESSION['flash'] = array('type' => 'error', 'msg' => 'Access denied. You do not have permission to view that page.');
    header('Location: ../auth/employee_login.php?access_denied=1');
    exit();
}

// Log session data for debugging
error_log('{$role} Dashboard - Session Data: ' . print_r(\$_SESSION, true));

// DB - Use the absolute path like admin dashboard
require_once \$root_path . '/config/db.php';

// Debug connection status
error_log('DB Connection Status: MySQLi=' . (\$conn ? 'Connected' : 'Failed') . ', PDO=' . (\$pdo ? 'Connected' : 'Failed'));

\$employee_id = \$_SESSION['employee_id'];
\$employee_role = \$_SESSION['role'];

EOT;

    // Replace any existing PHP opening tag and header code with our standardized version
    $pattern = '/^<\?php.*?(?=<!DOCTYPE|<html)/s';
    $newContent = preg_replace($pattern, $standardHeader, $content);

    // If no changes were made, the file might not have the expected structure
    if ($newContent === $content) {
        echo "Warning: Could not properly replace the header code. The file structure might be different than expected.\n";
        
        // Try to insert our header at the beginning
        $newContent = $standardHeader . "\n" . $content;
    }

    // Make sure we have the proper sidebar include
    $sidebarPattern = '/include.*sidebar_.*\.php/';
    $sidebarReplacement = "include \$root_path . '/includes/sidebar_{$roleLower}.php';";
    $newContent = preg_replace($sidebarPattern, $sidebarReplacement, $newContent);

    // Ensure proper absolute path for CSS includes
    $cssPattern = '/<link rel="stylesheet" href="(\.\.\/)*assets\//';
    $cssReplacement = '<link rel="stylesheet" href="<?php echo $root_path; ?>/assets/';
    $newContent = preg_replace($cssPattern, $cssReplacement, $newContent);

    // Write the updated content back to the file
    if (file_put_contents($filePath, $newContent) === false) {
        echo "Error: Failed to write updated content to {$filePath}\n";
        return false;
    }

    echo "Successfully updated {$filePath} with standardized template\n";
    return true;
}

// Execute the script if it's run directly from command line
if (php_sapi_name() === 'cli' && isset($argv)) {
    // Check if arguments are provided
    if (count($argv) < 3) {
        echo "Usage: php update_dashboard_template.php path/to/dashboard.php role_name [backup=1|0]\n";
        echo "Example: php update_dashboard_template.php pages/management/admin/dashboard.php admin\n";
        exit(1);
    }

    $filePath = $argv[1];
    $role = $argv[2];
    $backup = isset($argv[3]) ? (bool)$argv[3] : true;

    standardize_dashboard($filePath, $role, $backup);
}