<?php
$root_path = dirname(dirname(dirname(dirname(__DIR__))));

// Include necessary configuration files
require_once $root_path . '/config/session/employee_session.php';

// Simple debug output
echo "<h1>Debug Information</h1>";
echo "<p>Root path: " . $root_path . "</p>";
echo "<p>Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";

// Check if session functions exist
if (function_exists('is_employee_logged_in')) {
    echo "<p>is_employee_logged_in function exists</p>";
    $logged_in = is_employee_logged_in();
    echo "<p>Logged in: " . ($logged_in ? 'Yes' : 'No') . "</p>";
    
    if ($logged_in) {
        echo "<p>Employee data available</p>";
        $employee = $_SESSION['employee'] ?? null;
        if ($employee) {
            echo "<p>Employee role: " . htmlspecialchars($employee['role'] ?? 'Unknown') . "</p>";
        } else {
            echo "<p>No employee data in session</p>";
        }
    }
} else {
    echo "<p>is_employee_logged_in function does NOT exist</p>";
}

// Check database connection
try {
    require_once $root_path . '/config/db.php';
    echo "<p>Database config loaded successfully</p>";
    
    if (isset($pdo)) {
        echo "<p>PDO connection available</p>";
    } else {
        echo "<p>PDO connection NOT available</p>";
    }
} catch (Exception $e) {
    echo "<p>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>