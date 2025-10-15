<?php
/**
 * QUICK QUEUE SYSTEM TEST
 * This test verifies that the HHM-XXX queue code system works correctly
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

echo "🚀 TESTING QUEUE SYSTEM WITH HHM-XXX FORMAT\n";
echo "=============================================\n\n";

try {
    $queueService = new QueueManagementService($pdo);
    
    // Test 1: Check HHM-XXX queue code generation
    echo "TEST 1: Queue Code Generation\n";
    echo "-----------------------------\n";
    
    // Create a test appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, service_id, scheduled_date, scheduled_time, status, facility_id)
        VALUES (7, 1, CURDATE(), '09:00:00', 'confirmed', 4)
    ");
    $stmt->execute();
    $test_appointment_id = $pdo->lastInsertId();
    
    // Test check-in with HHM-XXX format
    $result = $queueService->checkin_patient($test_appointment_id, 1);
    
    if ($result['success']) {
        $queue_code = $result['data']['queue_code'];
        echo "✅ Check-in successful!\n";
        echo "   Queue Code: {$queue_code}\n";
        
        // Verify format
        if (preg_match('/^\d{2}[AP]-\d{3}$/', $queue_code)) {
            echo "✅ Queue code format is correct (HHM-XXX)\n";
        } else {
            echo "❌ Queue code format is WRONG! Expected HHM-XXX\n";
        }
        
        $queue_entry_id = $result['data']['queue_entry_id'];
        
        // Test 2: Station routing with SAME queue code
        echo "\nTEST 2: Station Routing (Single Queue Entry)\n";
        echo "---------------------------------------------\n";
        
        // Route to consultation
        $route_result = $queueService->routePatientToStation($queue_entry_id, 'consultation', 1, 'Test routing');
        
        if ($route_result['success']) {
            $same_queue_code = $route_result['data']['queue_code'];
            echo "✅ Routing successful!\n";
            echo "   From: {$route_result['data']['from_station']}\n";
            echo "   To: {$route_result['data']['to_station']}\n";
            echo "   Queue Code: {$same_queue_code}\n";
            
            // Verify SAME queue code
            if ($queue_code === $same_queue_code) {
                echo "✅ SAME queue code maintained through routing!\n";
            } else {
                echo "❌ Queue code CHANGED! This is WRONG!\n";
                echo "   Original: {$queue_code}\n";
                echo "   After routing: {$same_queue_code}\n";
            }
            
            // Verify SAME queue entry ID
            if ($queue_entry_id == $route_result['data']['queue_entry_id']) {
                echo "✅ SAME queue entry ID maintained!\n";
            } else {
                echo "❌ Queue entry ID CHANGED! This violates single-entry system!\n";
            }
            
        } else {
            echo "❌ Routing failed: " . $route_result['error'] . "\n";
        }
        
        // Test 3: Check database for multiple queue entries (should be only ONE)
        echo "\nTEST 3: Database Verification\n";
        echo "-----------------------------\n";
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, GROUP_CONCAT(queue_code) as all_codes
            FROM queue_entries 
            WHERE appointment_id = ?
        ");
        $stmt->execute([$test_appointment_id]);
        $db_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($db_check['count'] == 1) {
            echo "✅ Only ONE queue entry exists in database\n";
            echo "   Queue Code: {$db_check['all_codes']}\n";
        } else {
            echo "❌ MULTIPLE queue entries found! Count: {$db_check['count']}\n";
            echo "   All codes: {$db_check['all_codes']}\n";
        }
        
    } else {
        echo "❌ Check-in failed: " . $result['message'] . "\n";
    }
    
    // Cleanup
    echo "\nCLEANUP\n";
    echo "-------\n";
    $pdo->prepare("DELETE FROM queue_logs WHERE queue_entry_id IN (SELECT queue_entry_id FROM queue_entries WHERE appointment_id = ?)")->execute([$test_appointment_id]);
    $pdo->prepare("DELETE FROM queue_entries WHERE appointment_id = ?")->execute([$test_appointment_id]);
    $pdo->prepare("DELETE FROM visits WHERE appointment_id = ?")->execute([$test_appointment_id]);
    $pdo->prepare("DELETE FROM appointments WHERE appointment_id = ?")->execute([$test_appointment_id]);
    echo "✅ Test data cleaned up\n";
    
} catch (Exception $e) {
    echo "❌ TEST FAILED: " . $e->getMessage() . "\n";
}

echo "\n🏁 TEST COMPLETE\n";
?>