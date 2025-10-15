<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "=== TRIAGE STATION DEBUG ===<br>";
    
    // Test includes step by step
    echo "1. Setting root path...<br>";
    $root_path = dirname(dirname(__DIR__));
    echo "Root path: $root_path<br>";
    
    echo "2. Including employee session...<br>";
    require_once $root_path . '/config/session/employee_session.php';
    echo "Session included successfully<br>";
    
    echo "3. Checking session status...<br>";
    echo "Session status: " . session_status() . "<br>";
    echo "Session ID: " . session_id() . "<br>";
    
    echo "4. Session variables:<br>";
    if (isset($_SESSION)) {
        foreach ($_SESSION as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                echo "- $key: $value<br>";
            } else {
                echo "- $key: " . gettype($value) . "<br>";
            }
        }
    } else {
        echo "No session variables<br>";
    }
    
    echo "5. Including database connection...<br>";
    require_once $root_path . '/config/db.php';
    echo "Database included successfully<br>";
    echo "PDO available: " . (isset($pdo) ? 'YES' : 'NO') . "<br>";
    
    echo "6. Testing database connection...<br>";
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT 1");
        echo "Database query successful<br>";
    }
    
    echo "7. Including queue management service...<br>";
    require_once $root_path . '/utils/queue_management_service.php';
    echo "Queue service included successfully<br>";
    
    echo "8. Creating queue service instance...<br>";
    $queueService = new QueueManagementService($pdo);
    echo "Queue service instantiated successfully<br>";
    
    echo "9. Testing basic queue service method...<br>";
    $test_result = $queueService->getStationQueueStats(1, date('Y-m-d'));
    echo "Queue stats test: " . (is_array($test_result) ? 'SUCCESS' : 'FAILED') . "<br>";
    
    echo "<br>=== ALL TESTS PASSED ===<br>";
    
} catch (Exception $e) {
    echo "<br><strong>EXCEPTION:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack trace:</strong><br><pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "<br><strong>FATAL ERROR:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Stack trace:</strong><br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>