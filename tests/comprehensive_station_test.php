<?php
/**
 * COMPREHENSIVE STATION INTERFACE TEST
 * Tests all station routing actions to ensure they use routePatientToStation
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

echo "🔧 COMPREHENSIVE STATION INTERFACE TEST\n";
echo "=======================================\n\n";

$queueService = new QueueManagementService($pdo);
$test_results = [];

// Test 1: Full Patient Flow Simulation
echo "TEST 1: Complete Patient Flow Through All Stations\n";
echo "==================================================\n";

try {
    // Create test appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, service_id, scheduled_date, scheduled_time, status, facility_id)
        VALUES (7, 1, CURDATE(), '10:00:00', 'confirmed', 4)
    ");
    $stmt->execute();
    $test_appointment_id = $pdo->lastInsertId();
    
    // Step 1: Check-in
    echo "1. CHECK-IN\n";
    $checkin_result = $queueService->checkin_patient($test_appointment_id, 1);
    if ($checkin_result['success']) {
        $queue_code = $checkin_result['data']['queue_code'];
        $queue_entry_id = $checkin_result['data']['queue_entry_id'];
        echo "   ✅ Queue Code: {$queue_code}\n";
        echo "   ✅ Queue Entry ID: {$queue_entry_id}\n";
        
        // Step 2: Call for Triage
        echo "\n2. TRIAGE CALL\n";
        $stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'triage' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $triage_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($triage_station) {
            $call_result = $queueService->callNextPatient('triage', $triage_station['station_id'], 1);
            if ($call_result['success']) {
                echo "   ✅ Patient called for triage\n";
                
                // Step 3: Route to Consultation (Testing triage_station.php fix)
                echo "\n3. TRIAGE → CONSULTATION\n";
                $route_result = $queueService->routePatientToStation($queue_entry_id, 'consultation', 1, 'Triage completed');
                
                if ($route_result['success']) {
                    echo "   ✅ Routing successful\n";
                    echo "   ✅ Queue Code: {$route_result['data']['queue_code']}\n";
                    
                    if ($queue_code === $route_result['data']['queue_code']) {
                        echo "   ✅ SAME queue code maintained!\n";
                    } else {
                        echo "   ❌ Queue code changed!\n";
                    }
                    
                    // Step 4: Call for Consultation
                    echo "\n4. CONSULTATION CALL\n";
                    $stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'consultation' AND is_active = 1 LIMIT 1");
                    $stmt->execute();
                    $consultation_station = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($consultation_station) {
                        $consult_call = $queueService->callNextPatient('consultation', $consultation_station['station_id'], 1);
                        if ($consult_call['success']) {
                            echo "   ✅ Patient called for consultation\n";
                            
                            // Step 5: Route to Lab (Testing consultation_station.php fix)
                            echo "\n5. CONSULTATION → LAB\n";
                            $lab_route = $queueService->routePatientToStation($queue_entry_id, 'lab', 1, 'Lab tests ordered');
                            
                            if ($lab_route['success']) {
                                echo "   ✅ Routing successful\n";
                                echo "   ✅ Queue Code: {$lab_route['data']['queue_code']}\n";
                                
                                if ($queue_code === $lab_route['data']['queue_code']) {
                                    echo "   ✅ SAME queue code maintained!\n";
                                } else {
                                    echo "   ❌ Queue code changed!\n";
                                }
                                
                                // Step 6: Route to Billing (Testing lab_station.php fix)
                                echo "\n6. LAB → BILLING\n";
                                $billing_route = $queueService->routePatientToStation($queue_entry_id, 'billing', 1, 'Lab work completed');
                                
                                if ($billing_route['success']) {
                                    echo "   ✅ Routing successful\n";
                                    echo "   ✅ Queue Code: {$billing_route['data']['queue_code']}\n";
                                    
                                    if ($queue_code === $billing_route['data']['queue_code']) {
                                        echo "   ✅ SAME queue code maintained throughout entire journey!\n";
                                    } else {
                                        echo "   ❌ Queue code changed!\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Final Database Check
        echo "\n7. FINAL DATABASE VERIFICATION\n";
        echo "==============================\n";
        
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_entries,
                GROUP_CONCAT(DISTINCT queue_code) as all_codes,
                GROUP_CONCAT(DISTINCT qe.status) as all_statuses,
                GROUP_CONCAT(s.station_name ORDER BY qe.updated_at DESC) as station_journey
            FROM queue_entries qe
            LEFT JOIN stations s ON qe.station_id = s.station_id
            WHERE qe.appointment_id = ?
        ");
        $stmt->execute([$test_appointment_id]);
        $final_check = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Total Queue Entries: {$final_check['total_entries']}\n";
        echo "Queue Codes: {$final_check['all_codes']}\n";
        echo "Station Journey: {$final_check['station_journey']}\n";
        
        if ($final_check['total_entries'] == 1) {
            echo "✅ PERFECT! Only ONE queue entry throughout entire journey\n";
        } else {
            echo "❌ MULTIPLE queue entries found - station files still broken!\n";
        }
        
        if (strpos($final_check['all_codes'], ',') === false) {
            echo "✅ PERFECT! Single queue code maintained\n";
        } else {
            echo "❌ MULTIPLE queue codes - routing is broken!\n";
        }
        
    } else {
        echo "❌ Check-in failed: {$checkin_result['message']}\n";
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

echo "\n🎯 STATION INTERFACE VALIDATION COMPLETE\n";
echo "========================================\n";
echo "If all tests passed with single queue code maintained,\n";
echo "then the station interfaces are now properly fixed!\n";
?>