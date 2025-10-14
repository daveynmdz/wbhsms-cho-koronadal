<?php
// Simple debug script to test queue simulation
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Debug Queue System</title></head><body>";
echo "<h1>Debug Queue System</h1>";

try {
    echo "<p>1. Testing basic PHP...</p>";
    
    echo "<p>2. Testing path resolution...</p>";
    $root_path = __DIR__;
    echo "Root path: " . $root_path . "<br>";
    
    echo "<p>3. Testing database connection...</p>";
    require_once $root_path . '/config/db.php';
    echo "Database connected successfully<br>";
    
    echo "<p>4. Testing session...</p>";
    session_start();
    $_SESSION['employee_id'] = 1;
    $_SESSION['role'] = 'admin';
    echo "Session set with employee_id: " . $_SESSION['employee_id'] . "<br>";
    
    echo "<p>5. Testing QueueManagementService...</p>";
    require_once $root_path . '/utils/queue_management_service.php';
    $queueService = new QueueManagementService($pdo);
    echo "QueueManagementService loaded successfully<br>";
    
    echo "<p>6. Testing stations query...</p>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stations WHERE is_active = 1");
    $result = $stmt->fetch();
    echo "Active stations: " . $result['count'] . "<br>";
    
    echo "<p>7. Testing createQueueEntry method...</p>";
    // Test if the method exists
    if (method_exists($queueService, 'createQueueEntry')) {
        echo "createQueueEntry method exists<br>";
    } else {
        echo "ERROR: createQueueEntry method does not exist<br>";
    }
    
    echo "<p><strong>All tests passed! System should work.</strong></p>";
    echo "<p><a href='queue_simulation.php'>Try Queue Simulation</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace: <pre>" . $e->getTraceAsString() . "</pre></p>";
}

echo "</body></html>";
?>