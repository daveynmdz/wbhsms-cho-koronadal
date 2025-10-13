<?php
// Simple test for doctor dashboard paths
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Doctor Dashboard Path Test</h1>";

// Test path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
echo "<p>Root path: " . $root_path . "</p>";

// Test required files exist
$files = [
    '/config/session/employee_session.php',
    '/config/db.php', 
    '/utils/staff_assignment.php',
    '/includes/sidebar_doctor.php'
];

foreach ($files as $file) {
    $full_path = $root_path . $file;
    if (file_exists($full_path)) {
        echo "<p>✅ {$file} - EXISTS</p>";
    } else {
        echo "<p>❌ {$file} - MISSING</p>";
    }
}

// Test session
session_start();
echo "<p>Session started successfully</p>";

echo "<h2>Session Variables:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

?>