<?php
/**
 * Test to verify the MySQLi beginTransaction() fix
 * This test checks that QueueManagementService works correctly with PDO connections
 */

// Get the root path
$root_path = dirname(__DIR__);

// Include database configuration (provides both $conn and $pdo)
require_once $root_path . '/config/db.php';

// Include the QueueManagementService
require_once $root_path . '/utils/queue_management_service.php';

echo "<!DOCTYPE html><html><head><title>MySQLi beginTransaction() Fix Test</title></head><body>";
echo "<h1>🔧 MySQLi beginTransaction() Fix Test</h1>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Verify QueueManagementService can be instantiated with PDO
echo "<h2>Test 1: QueueManagementService Instantiation</h2>";
try {
    $queue_service = new QueueManagementService($pdo);
    echo "<div style='color: green;'>✅ QueueManagementService successfully instantiated with PDO connection</div>";
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Failed to instantiate QueueManagementService: " . $e->getMessage() . "</div>";
    exit();
}

// Test 2: Verify PDO beginTransaction() method works
echo "<h2>Test 2: PDO Transaction Methods</h2>";
try {
    $pdo->beginTransaction();
    echo "<div style='color: green;'>✅ PDO beginTransaction() method works</div>";
    $pdo->rollback();
    echo "<div style='color: green;'>✅ PDO rollback() method works</div>";
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ PDO transaction methods failed: " . $e->getMessage() . "</div>";
}

// Test 3: Verify MySQLi connection still works
echo "<h2>Test 3: MySQLi Connection Verification</h2>";
try {
    $result = $conn->query("SELECT 1 as test");
    if ($result && $result->fetch_assoc()) {
        echo "<div style='color: green;'>✅ MySQLi connection is working</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ MySQLi connection issue</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ MySQLi connection failed: " . $e->getMessage() . "</div>";
}

// Test 4: Try to call a simple QueueManagementService method
echo "<h2>Test 4: QueueManagementService Method Call</h2>";
try {
    $stats_result = $queue_service->getQueueStatistics();
    if (isset($stats_result['success'])) {
        echo "<div style='color: green;'>✅ QueueManagementService method call successful</div>";
        echo "<div style='color: blue;'>📊 Statistics returned: " . ($stats_result['success'] ? 'Success' : 'Failed') . "</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ QueueManagementService method returned unexpected format</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ QueueManagementService method call failed: " . $e->getMessage() . "</div>";
}

// Test 5: Verify database connections are different objects
echo "<h2>Test 5: Connection Types Verification</h2>";
echo "<div style='color: blue;'>📋 PDO connection type: " . get_class($pdo) . "</div>";
echo "<div style='color: blue;'>📋 MySQLi connection type: " . get_class($conn) . "</div>";

if (get_class($pdo) === 'PDO' && get_class($conn) === 'mysqli') {
    echo "<div style='color: green;'>✅ Both connection types are correctly available</div>";
} else {
    echo "<div style='color: red;'>❌ Connection types are incorrect</div>";
}

echo "<h2>🎯 Test Summary</h2>";
echo "<p>This test verifies that the beginTransaction() error has been fixed by ensuring:</p>";
echo "<ul>";
echo "<li>QueueManagementService is instantiated with PDO (not MySQLi)</li>";
echo "<li>PDO transaction methods are available</li>";
echo "<li>Both PDO and MySQLi connections work independently</li>";
echo "<li>QueueManagementService methods can be called successfully</li>";
echo "</ul>";

echo "<p><strong>If all tests pass, the beginTransaction() error should be resolved.</strong></p>";
echo "</body></html>";
?>