<?php
/**
 * Path Resolution Test Script
 * 
 * This script tests if all the critical paths are resolving correctly
 * on both local and remote server environments.
 */

echo "<h2>Path Resolution Test</h2>\n";
echo "<p>Testing from: " . __FILE__ . "</p>\n";

// Test 1: Patient Login Path Resolution
echo "<h3>1. Patient Login Path Resolution</h3>\n";
$patient_auth_dir = __DIR__ . '/pages/patient/auth';
$root_from_patient_auth = dirname(dirname(dirname($patient_auth_dir . '/dummy')));
$patient_session_path = $root_from_patient_auth . '/config/session/patient_session.php';
$patient_db_path = $root_from_patient_auth . '/config/db.php';

echo "Patient auth directory: $patient_auth_dir<br>\n";
echo "Root path from patient auth: $root_from_patient_auth<br>\n";
echo "Patient session path: $patient_session_path<br>\n";
echo "Patient session exists: " . (file_exists($patient_session_path) ? "✅ YES" : "❌ NO") . "<br>\n";
echo "Patient db path: $patient_db_path<br>\n";
echo "Patient db exists: " . (file_exists($patient_db_path) ? "✅ YES" : "❌ NO") . "<br>\n";

// Test 2: Employee Login Path Resolution
echo "<h3>2. Employee Login Path Resolution</h3>\n";
$employee_auth_dir = __DIR__ . '/pages/management/auth';
$root_from_employee_auth = dirname(dirname(dirname($employee_auth_dir . '/dummy')));
$employee_session_path = $root_from_employee_auth . '/config/session/employee_session.php';
$employee_db_path = $root_from_employee_auth . '/config/db.php';

echo "Employee auth directory: $employee_auth_dir<br>\n";
echo "Root path from employee auth: $root_from_employee_auth<br>\n";
echo "Employee session path: $employee_session_path<br>\n";
echo "Employee session exists: " . (file_exists($employee_session_path) ? "✅ YES" : "❌ NO") . "<br>\n";
echo "Employee db path: $employee_db_path<br>\n";
echo "Employee db exists: " . (file_exists($employee_db_path) ? "✅ YES" : "❌ NO") . "<br>\n";

// Test 3: Database Connection
echo "<h3>3. Database Connection Test</h3>\n";
try {
    include_once __DIR__ . '/config/env.php';
    echo "Environment file loaded: ✅ YES<br>\n";
    
    // Test if database variables are loaded
    $db_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($db_vars as $var) {
        $value = $_ENV[$var] ?? 'NOT SET';
        $masked_value = ($var === 'DB_PASS') ? str_repeat('*', strlen($value)) : $value;
        echo "$var: $masked_value<br>\n";
    }
    
    // Test PDO connection (if available)
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "PDO connection: ✅ AVAILABLE<br>\n";
    } else {
        echo "PDO connection: ❌ NOT AVAILABLE<br>\n";
    }
    
} catch (Exception $e) {
    echo "Database connection error: ❌ " . $e->getMessage() . "<br>\n";
}

// Test 4: Session Configuration
echo "<h3>4. Session Configuration Test</h3>\n";
echo "Session status: " . session_status() . " (1=disabled, 2=active, 3=none)<br>\n";
if (session_status() === PHP_SESSION_NONE) {
    echo "Starting session...<br>\n";
    session_start();
}
echo "Session ID: " . session_id() . "<br>\n";
echo "Session save path: " . session_save_path() . "<br>\n";

// Test 5: File Permissions (useful for remote servers)
echo "<h3>5. File Permissions Test</h3>\n";
$test_paths = [
    __DIR__ . '/config',
    __DIR__ . '/config/session',
    __DIR__ . '/pages',
    __DIR__ . '/pages/patient',
    __DIR__ . '/pages/management'
];

foreach ($test_paths as $path) {
    if (is_dir($path)) {
        $readable = is_readable($path) ? "✅" : "❌";
        $writable = is_writable($path) ? "✅" : "❌";
        echo "Directory $path - Readable: $readable, Writable: $writable<br>\n";
    }
}

echo "<h3>Summary</h3>\n";
echo "<p>If all tests show ✅, your path resolution should work correctly on both local and remote servers.</p>\n";
echo "<p>If you see ❌, there are still path or permission issues to resolve.</p>\n";
?>