<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "<br>";
echo "Current working directory: " . getcwd() . "<br>";

try {
    echo "Testing includes...<br>";
    
    // Test root path
    $root_path = dirname(__DIR__);
    echo "Root path: " . $root_path . "<br>";
    
    // Test session file
    $session_file = $root_path . '/config/session/employee_session.php';
    echo "Session file path: " . $session_file . "<br>";
    echo "Session file exists: " . (file_exists($session_file) ? 'YES' : 'NO') . "<br>";
    
    if (file_exists($session_file)) {
        require_once $session_file;
        echo "Session file loaded successfully<br>";
    }
    
    // Test db.php
    $db_file = $root_path . '/config/db.php';
    echo "DB file path: " . $db_file . "<br>";
    echo "DB file exists: " . (file_exists($db_file) ? 'YES' : 'NO') . "<br>";
    
    if (file_exists($db_file)) {
        require_once $db_file;
        echo "DB file loaded successfully<br>";
        echo "PDO object exists: " . (isset($pdo) ? 'YES' : 'NO') . "<br>";
    }
    
    // Test queue management service
    $queue_file = $root_path . '/utils/queue_management_service.php';
    echo "Queue service file path: " . $queue_file . "<br>";
    echo "Queue service file exists: " . (file_exists($queue_file) ? 'YES' : 'NO') . "<br>";
    
    if (file_exists($queue_file)) {
        require_once $queue_file;
        echo "Queue service file loaded successfully<br>";
        
        if (isset($pdo)) {
            $queueService = new QueueManagementService($pdo);
            echo "QueueManagementService instantiated successfully<br>";
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "<br>";
    echo "Stack trace: " . $e->getTraceAsString() . "<br>";
}
?>