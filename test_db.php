<?php
/**
 * Quick Database Connection Test
 * Access this file directly to test database connectivity
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Database Connection Test</h1>";

try {
    // Load environment
    $root_path = dirname(__DIR__);
    require_once $root_path . '/config/env.php';
    
    if ($pdo) {
        echo "<p style='color: green;'>✅ <strong>SUCCESS!</strong> Database connection is working.</p>";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients");
        $result = $stmt->fetch();
        echo "<p>Found {$result['count']} patients in database.</p>";
        
        echo "<p><a href='/'>← Go to Home Page</a></p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>FAILED!</strong> Database connection failed.</p>";
        if (isset($db_connection_error)) {
            echo "<p><strong>Error:</strong> " . htmlspecialchars($db_connection_error) . "</p>";
        }
        echo "<p><a href='scripts/setup/database_diagnostic.php'>Run Full Diagnostic</a></p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ <strong>ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>