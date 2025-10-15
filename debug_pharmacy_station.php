<?php
/**
 * Debug version of Pharmacy Station
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "=== PHARMACY STATION DEBUG ===<br>";
    
    // Step 1: Session check
    echo "1. Checking session...<br>";
    if (session_status() === PHP_SESSION_NONE) {
        echo "- Starting session<br>";
        session_name('EMPLOYEE_SESSID');
        session_start();
    } else {
        echo "- Session already active<br>";
    }
    
    echo "- Session ID: " . session_id() . "<br>";
    echo "- Session data: " . json_encode($_SESSION) . "<br>";
    
    // Step 2: Check if user is logged in
    echo "2. Authentication check...<br>";
    if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
        echo "- FAIL: User not logged in<br>";
        echo "- Redirecting to login...<br>";
        header("Location: ../management/auth/employee_login.php");
        exit();
    } else {
        echo "- PASS: User authenticated<br>";
        echo "- Employee ID: " . $_SESSION['employee_id'] . "<br>";
        echo "- Role: " . $_SESSION['role'] . "<br>";
    }
    
    // Step 3: Include dependencies
    echo "3. Loading dependencies...<br>";
    $root_path = dirname(dirname(__DIR__));
    echo "- Root path: $root_path<br>";
    
    require_once $root_path . '/config/db.php';
    echo "- Database connection loaded<br>";
    
    require_once $root_path . '/utils/queue_management_service.php';
    echo "- Queue management service loaded<br>";
    
    // Step 4: Initialize variables
    echo "4. Initializing variables...<br>";
    $employee_id = $_SESSION['employee_id'];
    $employee_role = $_SESSION['role'];
    $queueService = new QueueManagementService($pdo);
    echo "- Queue service instantiated<br>";
    
    // Step 5: Check authorization
    echo "5. Authorization check...<br>";
    $allowed_roles = ['pharmacist', 'admin', 'nurse'];
    if (!in_array(strtolower($employee_role), $allowed_roles)) {
        echo "- FAIL: Role '$employee_role' not authorized for pharmacy operations<br>";
        $redirect_url = "../management/" . strtolower($employee_role) . "/dashboard.php";
        echo "- Would redirect to: $redirect_url<br>";
        // Don't actually redirect for debugging
        exit();
    } else {
        echo "- PASS: Role '$employee_role' authorized<br>";
    }
    
    // Step 6: Get station assignment
    echo "6. Getting station assignment...<br>";
    $current_date = date('Y-m-d');
    echo "- Current date: $current_date<br>";
    
    $assignment_query = "SELECT sa.*, s.station_name, s.station_type 
                         FROM station_assignments sa 
                         JOIN stations s ON sa.station_id = s.station_id 
                         WHERE sa.employee_id = ? 
                         AND s.station_type = 'pharmacy'
                         AND sa.assigned_date <= ? 
                         AND (sa.end_date IS NULL OR sa.end_date >= ?)
                         AND sa.status = 'active'
                         ORDER BY sa.assigned_date DESC LIMIT 1";
    
    $stmt = $pdo->prepare($assignment_query);
    $stmt->execute([$employee_id, $current_date, $current_date]);
    $pharmacy_station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pharmacy_station) {
        echo "- Station assignment found: " . $pharmacy_station['station_name'] . "<br>";
    } else {
        echo "- No station assignment found<br>";
        
        // Admin fallback
        if (strtolower($employee_role) === 'admin') {
            echo "- Checking available stations for admin...<br>";
            $stations_query = "SELECT s.station_id, s.station_name, s.station_type, s.is_active 
                               FROM stations s 
                               WHERE s.station_type = 'pharmacy' AND s.is_active = 1
                               ORDER BY s.station_name";
            $stmt = $pdo->prepare($stations_query);
            $stmt->execute();
            $available_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "- Available pharmacy stations: " . count($available_stations) . "<br>";
            
            if (!empty($available_stations)) {
                $pharmacy_station = $available_stations[0];
                $pharmacy_station['assignment_id'] = null;
                $pharmacy_station['employee_id'] = $employee_id;
                echo "- Using first available station: " . $pharmacy_station['station_name'] . "<br>";
            } else {
                echo "- ERROR: No available pharmacy stations found<br>";
                // Show all stations for debugging
                $all_stations = $pdo->query("SELECT * FROM stations WHERE station_type = 'pharmacy'")->fetchAll(PDO::FETCH_ASSOC);
                echo "- All pharmacy stations in database:<br>";
                foreach ($all_stations as $station) {
                    echo "  * ID: {$station['station_id']}, Name: {$station['station_name']}, Active: " . ($station['is_active'] ? 'Yes' : 'No') . "<br>";
                }
            }
        }
    }
    
    // Step 7: Test queue operations
    if ($pharmacy_station) {
        echo "7. Testing queue operations...<br>";
        $station_id = $pharmacy_station['station_id'];
        echo "- Station ID: $station_id<br>";
        
        try {
            $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
            echo "- Waiting queue: " . count($waiting_queue) . " patients<br>";
            
            $queue_stats = $queueService->getStationQueueStats($station_id, $current_date);
            echo "- Queue stats retrieved successfully<br>";
            
            echo "<br><strong>✅ ALL TESTS PASSED!</strong><br>";
            echo "<p><a href='pharmacy_station.php'>Try accessing pharmacy station now</a></p>";
            
        } catch (Exception $e) {
            echo "- ERROR in queue operations: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "7. Cannot test queue operations - no station available<br>";
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