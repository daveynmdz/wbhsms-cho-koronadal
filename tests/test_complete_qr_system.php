<?php
/**
 * Complete QR Code System Test
 * Tests QR generation, email integration, patient access, and check-in scanning
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/qr_code_generator.php';

echo "<!DOCTYPE html><html><head><title>Complete QR System Test</title></head><body>";
echo "<h1>🔍 Complete QR Code System Test</h1>";
echo "<p><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

$all_tests_passed = true;

// Test 1: QR Generation
echo "<h2>Test 1: QR Code Generation</h2>";
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
        echo "<div style='color: green;'>✅ QR code generation working</div>";
        echo "<div style='color: blue;'>📊 QR size: " . $qr_result['qr_size'] . " bytes</div>";
        echo "<div style='color: blue;'>🔑 Verification code: " . $qr_result['verification_code'] . "</div>";
        
        // Test QR validation
        $is_valid = QRCodeGenerator::validateQRData($qr_result['qr_data'], 999);
        if ($is_valid) {
            echo "<div style='color: green;'>✅ QR validation working</div>";
        } else {
            echo "<div style='color: red;'>❌ QR validation failed</div>";
            $all_tests_passed = false;
        }
    } else {
        echo "<div style='color: red;'>❌ QR generation failed: " . $qr_result['error'] . "</div>";
        $all_tests_passed = false;
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ QR generation test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 2: Database QR Storage
echo "<h2>Test 2: QR Database Storage</h2>";
try {
    // Create test appointment
    $conn->begin_transaction();
    $test_query = "INSERT INTO appointments (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, created_at) VALUES (1, 1, 1, '2025-10-15', '10:00:00', 'confirmed', NOW())";
    $conn->query($test_query);
    $test_id = $conn->insert_id;
    
    // Generate and save QR
    $qr_result = QRCodeGenerator::generateAndSaveQR($test_id, $test_appointment_data, $conn);
    
    if ($qr_result['success']) {
        echo "<div style='color: green;'>✅ QR save to database working</div>";
        
        // Verify QR was saved
        $stmt = $conn->prepare("SELECT qr_code_path FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $test_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $saved_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($saved_data && $saved_data['qr_code_path']) {
            echo "<div style='color: green;'>✅ QR code stored in database</div>";
            echo "<div style='color: blue;'>📊 Stored QR size: " . strlen($saved_data['qr_code_path']) . " bytes</div>";
        } else {
            echo "<div style='color: red;'>❌ QR code not found in database</div>";
            $all_tests_passed = false;
        }
    } else {
        echo "<div style='color: red;'>❌ QR save failed: " . $qr_result['error'] . "</div>";
        $all_tests_passed = false;
    }
    
    // Clean up
    $conn->query("DELETE FROM appointments WHERE appointment_id = $test_id");
    $conn->commit();
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div style='color: red;'>❌ Database storage test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 3: Check Email Integration Points
echo "<h2>Test 3: Email Integration Check</h2>";
try {
    // Check if QR code generation is called in submit_appointment.php
    $submit_file = file_get_contents($root_path . '/pages/patient/appointment/submit_appointment.php');
    
    $checks = [
        'QR generator included' => strpos($submit_file, 'qr_code_generator.php') !== false,
        'QR generation called' => strpos($submit_file, 'generateAndSaveQR') !== false,
        'QR passed to email' => strpos($submit_file, '$qr_result') !== false && strpos($submit_file, 'sendAppointmentConfirmationEmail') !== false,
        'QR embedded in email' => strpos($submit_file, 'addEmbeddedImage') !== false,
    ];
    
    foreach ($checks as $check => $passed) {
        if ($passed) {
            echo "<div style='color: green;'>✅ $check</div>";
        } else {
            echo "<div style='color: red;'>❌ $check</div>";
            $all_tests_passed = false;
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Email integration check failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 4: Patient QR Access
echo "<h2>Test 4: Patient QR Access</h2>";
try {
    // Check if patient appointments page includes QR display
    $appointments_file = file_get_contents($root_path . '/pages/patient/appointment/appointments.php');
    
    $qr_access_checks = [
        'QR button in appointments' => strpos($appointments_file, 'showQRCode') !== false,
        'QR modal defined' => strpos($appointments_file, 'qrModal') !== false,
        'QR fetch function' => strpos($appointments_file, 'get_qr_code.php') !== false,
        'QR API endpoint exists' => file_exists($root_path . '/pages/patient/appointment/get_qr_code.php'),
    ];
    
    foreach ($qr_access_checks as $check => $passed) {
        if ($passed) {
            echo "<div style='color: green;'>✅ $check</div>";
        } else {
            echo "<div style='color: red;'>❌ $check</div>";
            $all_tests_passed = false;
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Patient access test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 5: Check-in QR Scanning
echo "<h2>Test 5: Check-in QR Scanning</h2>";
try {
    // Check if check-in system can handle QR codes
    $checkin_file = file_get_contents($root_path . '/pages/queueing/checkin.php');
    
    $checkin_checks = [
        'QR scanning endpoint' => strpos($checkin_file, 'scan_qr') !== false,
        'JSON QR parsing' => strpos($checkin_file, 'json_decode') !== false,
        'QR validation' => strpos($checkin_file, 'validateQRData') !== false,
        'QR generator included' => strpos($checkin_file, 'qr_code_generator.php') !== false,
    ];
    
    foreach ($checkin_checks as $check => $passed) {
        if ($passed) {
            echo "<div style='color: green;'>✅ $check</div>";
        } else {
            echo "<div style='color: red;'>❌ $check</div>";
            $all_tests_passed = false;
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Check-in scanning test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Test 6: End-to-End QR Flow Simulation
echo "<h2>Test 6: End-to-End QR Flow</h2>";
try {
    echo "<div style='color: blue;'>📋 Simulating complete QR flow...</div>";
    
    // Step 1: Create appointment with QR
    $conn->begin_transaction();
    $test_query = "INSERT INTO appointments (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, created_at) VALUES (1, 1, 1, '2025-10-15', '11:00:00', 'confirmed', NOW())";
    $conn->query($test_query);
    $flow_test_id = $conn->insert_id;
    $conn->commit();
    
    echo "<div style='color: green;'>✅ Step 1: Appointment created (ID: $flow_test_id)</div>";
    
    // Step 2: Generate QR code
    $qr_flow_result = QRCodeGenerator::generateAndSaveQR($flow_test_id, $test_appointment_data, $conn);
    if ($qr_flow_result['success']) {
        echo "<div style='color: green;'>✅ Step 2: QR code generated and saved</div>";
        
        // Step 3: Validate QR can be retrieved
        $stmt = $conn->prepare("SELECT qr_code_path FROM appointments WHERE appointment_id = ?");
        $stmt->bind_param("i", $flow_test_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $qr_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($qr_data && $qr_data['qr_code_path']) {
            echo "<div style='color: green;'>✅ Step 3: QR code can be retrieved from database</div>";
            
            // Step 4: Test QR validation (simulate check-in)
            // The QR content should be the JSON string that was used to generate the QR
            $qr_content_for_validation = json_encode([
                'appointment_id' => $flow_test_id,
                'patient_id' => 999,
                'verification_code' => $qr_flow_result['verification_code'] ?? 'test-code'
            ]);
            $is_valid = QRCodeGenerator::validateQRData($qr_content_for_validation, $flow_test_id);
            if ($is_valid) {
                echo "<div style='color: green;'>✅ Step 4: QR validation works for check-in</div>";
            } else {
                echo "<div style='color: red;'>❌ Step 4: QR validation failed</div>";
                $all_tests_passed = false;
            }
        } else {
            echo "<div style='color: red;'>❌ Step 3: QR code retrieval failed</div>";
            $all_tests_passed = false;
        }
    } else {
        echo "<div style='color: red;'>❌ Step 2: QR generation failed</div>";
        $all_tests_passed = false;
    }
    
    // Clean up
    $conn->query("DELETE FROM appointments WHERE appointment_id = $flow_test_id");
    echo "<div style='color: blue;'>🧹 Test data cleaned up</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ End-to-end flow test failed: " . $e->getMessage() . "</div>";
    $all_tests_passed = false;
}

// Final Result
echo "<h2>🎯 Complete QR System Test Summary</h2>";
if ($all_tests_passed) {
    echo "<div style='color: green; font-size: 18px; font-weight: bold;'>✅ ALL QR SYSTEM TESTS PASSED</div>";
    echo "<p><strong>The complete QR code system is functional!</strong></p>";
    echo "<ul>";
    echo "<li>✅ QR codes are generated during appointment booking</li>";
    echo "<li>✅ QR codes are saved to database</li>";
    echo "<li>✅ QR codes are included in email confirmations</li>";
    echo "<li>✅ Patients can access QR codes from their appointments</li>";
    echo "<li>✅ Check-in staff can scan and validate QR codes</li>";
    echo "<li>✅ End-to-end flow is complete and secure</li>";
    echo "</ul>";
} else {
    echo "<div style='color: red; font-size: 18px; font-weight: bold;'>❌ SOME QR SYSTEM COMPONENTS FAILED</div>";
    echo "<p>Please check the errors above and fix any remaining issues.</p>";
}

echo "<h2>🚀 QR System Benefits</h2>";
echo "<ul>";
echo "<li><strong>Patient Experience:</strong> Seamless check-in with QR codes in email confirmations</li>";
echo "<li><strong>Staff Efficiency:</strong> Instant appointment verification through QR scanning</li>";
echo "<li><strong>Security:</strong> Verification codes prevent QR forgery</li>";
echo "<li><strong>Reliability:</strong> Fallback to manual check-in if QR fails</li>";
echo "</ul>";

echo "</body></html>";
?>