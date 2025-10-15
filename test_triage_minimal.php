<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "=== MINIMAL TRIAGE STATION TEST ===<br>";
    
    // Simulate being logged in as admin for testing
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['employee_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
    
    echo "1. Session set up<br>";
    
    $root_path = dirname(__DIR__);
    echo "2. Root path: $root_path<br>";
    
    require_once $root_path . '/config/db.php';
    echo "3. Database connected<br>";
    
    require_once $root_path . '/utils/queue_management_service.php';
    echo "4. Queue service loaded<br>";
    
    $queueService = new QueueManagementService($pdo);
    echo "5. Queue service instantiated<br>";
    
    // Test basic functionality
    $employee_id = $_SESSION['employee_id'];
    $employee_role = $_SESSION['role'];
    
    echo "6. Employee ID: $employee_id, Role: $employee_role<br>";
    
    // Check authorization
    $allowed_roles = ['nurse', 'admin', 'doctor'];
    $authorized = in_array(strtolower($employee_role), $allowed_roles);
    echo "7. Authorization check: " . ($authorized ? 'PASSED' : 'FAILED') . "<br>";
    
    if (!$authorized) {
        echo "Not authorized for triage operations<br>";
        exit();
    }
    
    // Get station assignment
    $current_date = date('Y-m-d');
    echo "8. Current date: $current_date<br>";
    
    $assignment_query = "SELECT sa.*, s.station_name, s.station_type 
                         FROM station_assignments sa 
                         JOIN stations s ON sa.station_id = s.station_id 
                         WHERE sa.employee_id = ? 
                         AND s.station_type = 'triage'
                         AND sa.assigned_date <= ? 
                         AND (sa.end_date IS NULL OR sa.end_date >= ?)
                         AND sa.status = 'active'
                         ORDER BY sa.assigned_date DESC LIMIT 1";
    $stmt = $pdo->prepare($assignment_query);
    $stmt->execute([$employee_id, $current_date, $current_date]);
    $triage_station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "9. Station assignment query executed<br>";
    
    if ($triage_station) {
        echo "10. Assigned station: " . $triage_station['station_name'] . "<br>";
    } else {
        echo "10. No station assignment found<br>";
        
        // Get available stations for admin
        if (strtolower($employee_role) === 'admin') {
            $stations_query = "SELECT s.station_id, s.station_name, s.station_type, s.is_active 
                               FROM stations s 
                               WHERE s.station_type = 'triage' AND s.is_active = 1
                               ORDER BY s.station_name";
            $stmt = $pdo->prepare($stations_query);
            $stmt->execute();
            $available_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($available_stations)) {
                $triage_station = $available_stations[0];
                echo "11. Using first available station: " . $triage_station['station_name'] . "<br>";
            } else {
                echo "11. No available triage stations found<br>";
            }
        }
    }
    
    if ($triage_station) {
        $station_id = $triage_station['station_id'];
        echo "12. Station ID: $station_id<br>";
        
        // Test queue data retrieval
        try {
            $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
            echo "13. Waiting queue: " . count($waiting_queue) . " patients<br>";
            
            $queue_stats = $queueService->getStationQueueStats($station_id, $current_date);
            echo "14. Queue stats retrieved successfully<br>";
            
            echo "<br><strong>SUCCESS!</strong> All basic functionality working.<br>";
            
        } catch (Exception $e) {
            echo "13. Error getting queue data: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "12. Cannot proceed without station assignment<br>";
    }
    
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