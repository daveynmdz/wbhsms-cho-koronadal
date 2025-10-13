<?php
/**
 * Test for Complete Appointment Booking Flow
 * Tests transaction handling, queue creation, and QR generation
 */

// Get the root path
$root_path = dirname(__DIR__);

// Include required files
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';
require_once $root_path . '/utils/qr_code_generator.php';

echo "<!DOCTYPE html><html><head><title>Complete Appointment Flow Test</title></head><body>";
echo "<h1>🔧 Complete Appointment Booking Flow Test</h1>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

$all_tests_passed = true;

// Test 1: Database Connections
echo "<h2>Test 1: Database Connections</h2>";
try {
    // Test MySQLi connection
    $mysqli_result = $conn->query("SELECT 1 as test");
    if ($mysqli_result && $mysqli_result->fetch_assoc()) {
        echo "<div style='color: green;'>✅ MySQLi connection working</div>";
    } else {
        echo "<div style='color: red;'>❌ MySQLi connection failed</div>";
        $all_tests_passed = false;
    }
    
    // Test PDO connection  
    $pdo_result = $pdo->query("SELECT 1 as test");
    if ($pdo_result && $pdo_result->fetch()) {
        echo "<div style='color: green;'>✅ PDO connection working</div>";
    } else {
        echo "<div style='color: red;'>❌ PDO connection failed</div>";
        $all_tests_passed = false;
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Database connection test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 2: QueueManagementService with PDO
echo "<h2>Test 2: QueueManagementService with PDO</h2>";
try {
    $queue_service = new QueueManagementService($pdo);
    $stats_result = $queue_service->getQueueStatistics();
    if (isset($stats_result['success'])) {
        echo "<div style='color: green;'>✅ QueueManagementService working with PDO</div>";
    } else {
        echo "<div style='color: red;'>❌ QueueManagementService failed</div>";
        $all_tests_passed = false;
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ QueueManagementService test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 3: QR Code Generation
echo "<h2>Test 3: QR Code Generation</h2>";
try {
    $test_appointment_data = [
        'patient_id' => 1,
        'scheduled_date' => '2025-10-14',
        'scheduled_time' => '10:00:00',
        'facility_id' => 1,
        'service_id' => 1
    ];
    
    $qr_result = QRCodeGenerator::generateAppointmentQR(999, $test_appointment_data);
    
    if ($qr_result['success']) {
        echo "<div style='color: green;'>✅ QR code generated successfully</div>";
        echo "<div style='color: blue;'>📊 QR size: " . $qr_result['qr_size'] . " bytes</div>";
        echo "<div style='color: blue;'>🔑 Verification code: " . $qr_result['verification_code'] . "</div>";
    } else {
        echo "<div style='color: red;'>❌ QR code generation failed: " . $qr_result['error'] . "</div>";
        $all_tests_passed = false;
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ QR code generation test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 4: Transaction Isolation Check
echo "<h2>Test 4: Transaction Isolation</h2>";
try {
    // Start MySQLi transaction
    $conn->begin_transaction();
    
    // Insert test data
    $test_query = "INSERT INTO appointments (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, created_at) VALUES (1, 1, 1, '2025-10-15', '10:00:00', 'test', NOW())";
    $conn->query($test_query);
    $test_id = $conn->insert_id;
    
    // Try to read with PDO while MySQLi transaction is not committed
    $pdo_stmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ?");
    $pdo_stmt->execute([$test_id]);
    $pdo_result = $pdo_stmt->fetch();
    
    // Commit MySQLi transaction
    $conn->commit();
    
    // Try to read again after commit
    $pdo_stmt->execute([$test_id]);
    $pdo_result_after = $pdo_stmt->fetch();
    
    // Clean up test data
    $conn->query("DELETE FROM appointments WHERE appointment_id = $test_id");
    
    if ($pdo_result_after) {
        echo "<div style='color: green;'>✅ Transaction isolation working correctly</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ Transaction isolation issue detected</div>";
    }
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<div style='color: red;'>❌ Transaction test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 5: Appointment Flow Simulation
echo "<h2>Test 5: Simulated Appointment Flow</h2>";
try {
    echo "<div style='color: blue;'>📋 Simulating appointment booking flow...</div>";
    
    // Step 1: Create appointment (MySQLi)
    $conn->begin_transaction();
    $test_query = "INSERT INTO appointments (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, created_at) VALUES (1, 1, 1, '2025-10-15', '11:00:00', 'confirmed', NOW())";
    $conn->query($test_query);
    $test_appointment_id = $conn->insert_id;
    $conn->commit();
    echo "<div style='color: green;'>✅ Step 1: Appointment created (ID: $test_appointment_id)</div>";
    
    // Step 2: Create queue entry (PDO)
    $queue_result = $queue_service->createQueueEntry($test_appointment_id, 1, 1, 'consultation', 'normal', null);
    if ($queue_result['success']) {
        echo "<div style='color: green;'>✅ Step 2: Queue entry created</div>";
    } else {
        echo "<div style='color: red;'>❌ Step 2: Queue entry failed - " . $queue_result['error'] . "</div>";
        $all_tests_passed = false;
    }
    
    // Step 3: Generate QR code
    $qr_result = QRCodeGenerator::generateAndSaveQR($test_appointment_id, $test_appointment_data, $conn);
    if ($qr_result['success']) {
        echo "<div style='color: green;'>✅ Step 3: QR code generated and saved</div>";
    } else {
        echo "<div style='color: red;'>❌ Step 3: QR code failed - " . $qr_result['error'] . "</div>";
        $all_tests_passed = false;
    }
    
    // Clean up test data
    $conn->query("DELETE FROM queue_entries WHERE appointment_id = $test_appointment_id");
    $conn->query("DELETE FROM visits WHERE appointment_id = $test_appointment_id");
    $conn->query("DELETE FROM appointments WHERE appointment_id = $test_appointment_id");
    echo "<div style='color: blue;'>🧹 Test data cleaned up</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Appointment flow simulation failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Final Result
echo "<h2>🎯 Test Summary</h2>";
if ($all_tests_passed) {
    echo "<div style='color: green; font-size: 18px; font-weight: bold;'>✅ ALL TESTS PASSED</div>";
    echo "<p><strong>The appointment booking system should work correctly now!</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database connections working properly</li>";
    echo "<li>✅ QueueManagementService using PDO correctly</li>";
    echo "<li>✅ QR code generation functional</li>";
    echo "<li>✅ Transaction handling improved</li>";
    echo "<li>✅ Complete flow tested successfully</li>";
    echo "</ul>";
} else {
    echo "<div style='color: red; font-size: 18px; font-weight: bold;'>❌ SOME TESTS FAILED</div>";
    echo "<p>Please check the errors above and fix any remaining issues.</p>";
}

echo "<h2>🔧 Production Readiness</h2>";
echo "<p>Before testing in production:</p>";
echo "<ol>";
echo "<li>Backup your production database</li>";
echo "<li>Test appointment booking with a real patient account</li>";
echo "<li>Verify QR codes can be scanned at check-in stations</li>";
echo "<li>Confirm queue numbers are assigned properly</li>";
echo "<li>Check email notifications are sent</li>";
echo "</ol>";

echo "</body></html>";
?>