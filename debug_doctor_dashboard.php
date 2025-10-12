<?php
// Debug script for doctor dashboard issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Doctor Dashboard Debug</h2>";

// Test 1: Path Resolution
$root_path = dirname(dirname(dirname(__DIR__)));
echo "<p><strong>Root Path:</strong> " . $root_path . "</p>";

// Test 2: Session Configuration
try {
    require_once $root_path . '/config/session/employee_session.php';
    echo "<p><strong>Session Config:</strong> ✅ Loaded successfully</p>";
    
    echo "<p><strong>Session Data:</strong><pre>";
    print_r($_SESSION);
    echo "</pre></p>";
    
} catch (Exception $e) {
    echo "<p><strong>Session Config Error:</strong> " . $e->getMessage() . "</p>";
}

// Test 3: Database Connection
try {
    require_once $root_path . '/config/db.php';
    echo "<p><strong>Database Config:</strong> ✅ Loaded successfully</p>";
    echo "<p><strong>MySQLi Connection:</strong> " . ($conn ? '✅ Connected' : '❌ Failed') . "</p>";
    echo "<p><strong>PDO Connection:</strong> " . ($pdo ? '✅ Connected' : '❌ Failed') . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Database Error:</strong> " . $e->getMessage() . "</p>";
}

// Test 4: Staff Assignment Utility
try {
    require_once $root_path . '/utils/staff_assignment.php';
    echo "<p><strong>Staff Assignment Utility:</strong> ✅ Loaded successfully</p>";
    
    if (function_exists('getStaffAssignment')) {
        echo "<p><strong>getStaffAssignment Function:</strong> ✅ Available</p>";
    } else {
        echo "<p><strong>getStaffAssignment Function:</strong> ❌ Not found</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>Staff Assignment Error:</strong> " . $e->getMessage() . "</p>";
}

// Test 5: Required Session Variables
$required_session_vars = ['employee_id', 'role', 'employee_first_name', 'employee_last_name', 'employee_number'];
echo "<p><strong>Required Session Variables:</strong></p><ul>";
foreach ($required_session_vars as $var) {
    $status = isset($_SESSION[$var]) ? '✅' : '❌';
    $value = isset($_SESSION[$var]) ? $_SESSION[$var] : 'NOT SET';
    echo "<li>$var: $status $value</li>";
}
echo "</ul>";

// Test 6: File Permissions
$files_to_check = [
    $root_path . '/config/session/employee_session.php',
    $root_path . '/config/db.php',
    $root_path . '/utils/staff_assignment.php',
    $root_path . '/includes/sidebar_doctor.php'
];

echo "<p><strong>File Accessibility:</strong></p><ul>";
foreach ($files_to_check as $file) {
    $status = file_exists($file) ? '✅ Exists' : '❌ Missing';
    $readable = is_readable($file) ? '& Readable' : '& Not Readable';
    echo "<li>" . basename($file) . ": $status $readable</li>";
}
echo "</ul>";
?>