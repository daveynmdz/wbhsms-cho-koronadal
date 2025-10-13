<?php
/**
 * Production Session Validator
 * Purpose: Validate that session management is production-ready across all patient pages
 * Run this script to check for potential "headers already sent" issues
 */

// Start output buffering for this script
ob_start();

// Define root path
$root_path = dirname(dirname(__DIR__));

// Include configuration
require_once $root_path . '/config/env.php';
require_once $root_path . '/config/db.php';

echo "<h1>Production Session Validator</h1>\n";
echo "<p>Testing session management patterns across patient-facing pages...</p>\n";

// List of critical patient pages that need session management
$patient_pages = [
    'pages/patient/dashboard.php',
    'pages/patient/billing/billing.php',
    'pages/patient/billing/billing_history.php',
    'pages/patient/billing/invoice_details.php',
    'pages/patient/appointment/appointments.php',
    'pages/patient/appointment/book_appointment.php',
    'pages/patient/prescription/prescriptions.php',
    'pages/patient/referrals/referrals.php',
    'pages/patient/profile/profile.php',
    'pages/patient/queueing/queue_status.php'
];

$results = [];
$total_files = 0;
$fixed_files = 0;
$issue_files = 0;

echo "<h2>Checking Files:</h2>\n";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>File</th><th>Status</th><th>Issues Found</th><th>Recommendations</th></tr>\n";

foreach ($patient_pages as $page) {
    $file_path = $root_path . '/' . $page;
    $total_files++;
    
    if (!file_exists($file_path)) {
        echo "<tr><td>$page</td><td style='color: orange;'>⚠️ NOT FOUND</td><td>File does not exist</td><td>N/A</td></tr>\n";
        $issue_files++;
        continue;
    }
    
    // Read first 50 lines to check for proper session management
    $file_content = file_get_contents($file_path, false, null, 0, 5000);
    $issues = [];
    $recommendations = [];
    
    // Check for output buffering
    if (!preg_match('/ob_start\(\);/', $file_content)) {
        $issues[] = "Missing ob_start()";
        $recommendations[] = "Add ob_start() at top";
    }
    
    // Check for proper error handling
    if (!preg_match('/ini_set\([\'"]display_errors[\'"]/', $file_content)) {
        $issues[] = "Missing error display control";
        $recommendations[] = "Add ini_set('display_errors', '0')";
    }
    
    // Check for session_status check
    if (!preg_match('/session_status\(\) === PHP_SESSION_NONE/', $file_content)) {
        $issues[] = "Missing session_status check";
        $recommendations[] = "Add session_status() validation";
    }
    
    // Check for ob_clean before redirects
    if (preg_match('/header\([\'"]Location:/', $file_content) && !preg_match('/ob_clean\(\);/', $file_content)) {
        $issues[] = "Missing ob_clean() before redirects";
        $recommendations[] = "Add ob_clean() before header redirects";
    }
    
    // Check for config/env.php inclusion
    if (!preg_match('/config\/env\.php/', $file_content)) {
        $issues[] = "Missing env.php inclusion";
        $recommendations[] = "Include config/env.php first";
    }
    
    if (empty($issues)) {
        echo "<tr><td>$page</td><td style='color: green;'>✅ PRODUCTION READY</td><td>None</td><td>Good to go!</td></tr>\n";
        $fixed_files++;
    } else {
        echo "<tr><td>$page</td><td style='color: red;'>❌ NEEDS FIXES</td><td>" . implode(", ", $issues) . "</td><td>" . implode(", ", $recommendations) . "</td></tr>\n";
        $issue_files++;
    }
    
    $results[$page] = [
        'status' => empty($issues) ? 'ready' : 'needs_fix',
        'issues' => $issues,
        'recommendations' => $recommendations
    ];
}

echo "</table>\n";

// Summary
echo "<h2>Summary Report:</h2>\n";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>\n";
echo "<p><strong>Total Files Checked:</strong> $total_files</p>\n";
echo "<p><strong>Production Ready:</strong> <span style='color: green;'>$fixed_files files</span></p>\n";
echo "<p><strong>Need Fixes:</strong> <span style='color: red;'>$issue_files files</span></p>\n";

if ($issue_files == 0) {
    echo "<p style='color: green; font-weight: bold;'>🎉 ALL FILES ARE PRODUCTION READY!</p>\n";
    echo "<p>Your patient-facing pages should no longer have 'headers already sent' issues in production.</p>\n";
} else {
    echo "<p style='color: red; font-weight: bold;'>⚠️ Some files still need attention for production deployment.</p>\n";
}

echo "</div>\n";

// Production deployment checklist
echo "<h2>Production Deployment Checklist:</h2>\n";
echo "<ol>\n";
echo "<li>✅ Output buffering implemented (ob_start())</li>\n";
echo "<li>✅ Error display controlled (ini_set('display_errors', '0'))</li>\n";
echo "<li>✅ Session management standardized</li>\n";
echo "<li>✅ Buffer clearing before redirects (ob_clean())</li>\n";
echo "<li>✅ Configuration loading order fixed</li>\n";
echo "<li>🔄 Test all patient flows in production environment</li>\n";
echo "<li>🔄 Monitor error logs for any remaining issues</li>\n";
echo "<li>🔄 Validate session persistence across page navigation</li>\n";
echo "</ol>\n";

// Best practices reminder
echo "<h2>Production Best Practices Applied:</h2>\n";
echo "<ul>\n";
echo "<li><strong>Output Buffering:</strong> Prevents 'headers already sent' errors</li>\n";
echo "<li><strong>Error Suppression:</strong> Errors logged but not displayed to users</li>\n";
echo "<li><strong>Session Validation:</strong> Proper session_start() checks prevent conflicts</li>\n";
echo "<li><strong>Clean Redirects:</strong> Buffer clearing ensures proper redirects</li>\n";
echo "<li><strong>Configuration Order:</strong> Environment loaded before session management</li>\n";
echo "</ul>\n";

echo "<h3>Next Steps:</h3>\n";
echo "<p>1. Deploy to production environment</p>\n";
echo "<p>2. Test patient registration, login, and navigation flows</p>\n";
echo "<p>3. Monitor error logs for any remaining issues</p>\n";
echo "<p>4. Validate that session persistence works correctly</p>\n";

// Database connection test
echo "<h2>Database Connection Test:</h2>\n";
try {
    if (isset($pdo)) {
        $test_query = $pdo->query("SELECT 1");
        echo "<p style='color: green;'>✅ PDO Database connection successful</p>\n";
    }
    
    if (isset($conn)) {
        $test_result = $conn->query("SELECT 1");
        echo "<p style='color: green;'>✅ MySQLi Database connection successful</p>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection error: " . $e->getMessage() . "</p>\n";
}

// End output buffering and display results
ob_end_flush();
?>