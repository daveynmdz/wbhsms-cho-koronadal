<?php
/**
 * Quick diagnostic for patient billing production issue
 * This helps identify what's causing the blank page
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Start output buffering
ob_start();

echo "Starting diagnostic...\n";

try {
    // Test 1: Check if we can set up basic paths
    $root_path = dirname(dirname(dirname(__DIR__)));
    echo "✓ Root path established: " . $root_path . "\n";
    
    // Test 2: Check if env.php loads
    require_once $root_path . '/config/env.php';
    echo "✓ Environment configuration loaded\n";
    
    // Test 3: Check if db.php loads
    require_once $root_path . '/config/db.php';
    echo "✓ Database configuration loaded\n";
    
    // Test 4: Test database connection
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "✓ PDO database connection available\n";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        if ($result && $result['test'] == 1) {
            echo "✓ Database query successful\n";
        } else {
            echo "✗ Database query failed\n";
        }
    } else {
        echo "✗ PDO database connection not available\n";
    }
    
    // Test 5: Check session configuration
    require_once $root_path . '/config/session/patient_session.php';
    echo "✓ Patient session configuration loaded\n";
    
    // Test 6: Check if session functions exist
    if (function_exists('is_patient_logged_in')) {
        echo "✓ Patient session functions available\n";
    } else {
        echo "✗ Patient session functions not available\n";
    }
    
    echo "\n=== DIAGNOSTIC COMPLETE ===\n";
    echo "If you see this message, the basic configuration is working.\n";
    echo "The issue might be elsewhere in the billing.php file.\n";
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

// Output the buffered content
ob_end_flush();
?>