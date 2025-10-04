<?php
/**
 * Test script for appointment booking and queueing integration
 * This script tests the enhanced queue management with time slots and visit creation
 */

// Include necessary files
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';
require_once $root_path . '/utils/appointment_logger.php';

echo "<h2>Testing Enhanced Appointment Booking & Queueing Integration</h2>";

try {
    // Test 1: Queue number generation for time slots
    echo "<h3>Test 1: Time Slot Queue Number Generation</h3>";
    
    // First, get or create a test patient
    $stmt = $conn->prepare("SELECT patient_id FROM patients LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $existing_patient = $result->fetch_assoc();
    $stmt->close();
    
    $test_patient_id = null;
    
    if ($existing_patient) {
        $test_patient_id = $existing_patient['patient_id'];
        echo "✅ Using existing patient ID: $test_patient_id<br>";
    } else {
        // Create a test patient
        $stmt = $conn->prepare("
            INSERT INTO patients (
                first_name, last_name, date_of_birth, gender, 
                contact_number, email, address, patient_number,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $test_patient_number = 'TEST-' . date('Ymd-His');
        $stmt->bind_param("ssssssss", 
            'Test', 'Patient', '1990-01-01', 'Male',
            '09123456789', 'test@example.com', 'Test Address', $test_patient_number
        );
        
        if ($stmt->execute()) {
            $test_patient_id = $conn->insert_id;
            $stmt->close();
            echo "✅ Created test patient with ID: $test_patient_id<br>";
        } else {
            throw new Exception("Failed to create test patient: " . $stmt->error);
        }
    }
    
    // Get or verify facility exists
    $stmt = $conn->prepare("SELECT facility_id FROM facilities WHERE facility_type = 'cho' LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $facility = $result->fetch_assoc();
    $stmt->close();
    
    if (!$facility) {
        throw new Exception("No CHO facility found in database");
    }
    $test_facility_id = $facility['facility_id'];
    echo "✅ Using facility ID: $test_facility_id<br>";
    
    // Get or verify service exists
    $stmt = $conn->prepare("SELECT service_id FROM services LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();
    
    if (!$service) {
        throw new Exception("No services found in database");
    }
    $test_service_id = $service['service_id'];
    echo "✅ Using service ID: $test_service_id<br>";
    
    // Create a test appointment first
    $stmt = $conn->prepare("
        INSERT INTO appointments (
            patient_id, facility_id, referral_id, service_id, 
            scheduled_date, scheduled_time, status, created_at, updated_at
        ) VALUES (?, ?, NULL, ?, '2025-10-04', '09:00:00', 'confirmed', NOW(), NOW())
    ");
    
    $stmt->bind_param("iii", $test_patient_id, $test_facility_id, $test_service_id);
    
    if ($stmt->execute()) {
        $test_appointment_id = $conn->insert_id;
        $stmt->close();
        echo "✅ Test appointment created with ID: $test_appointment_id<br>";
        
        // Test queue creation
        $queue_service = new QueueManagementService($conn);
        $result = $queue_service->createQueueEntry(
            $test_appointment_id, 
            $test_patient_id,
            $test_service_id,
            'consultation', 
            'normal',
            null
        );
        
        if ($result['success']) {
            echo "✅ Queue entry created successfully<br>";
            echo "- Queue Number: " . $result['queue_number'] . "<br>";
            echo "- Visit ID: " . $result['visit_id'] . "<br>";
            echo "- Queue Type: " . $result['queue_type'] . "<br>";
            echo "- Priority Level: " . $result['priority_level'] . "<br>";
        } else {
            echo "❌ Queue creation failed: " . $result['error'] . "<br>";
        }
        
        // Test appointment logging
        echo "<h3>Test 2: Appointment Logging</h3>";
        $appointment_logger = new AppointmentLogger($conn);
        $log_result = $appointment_logger->logAppointmentCreation(
            $test_appointment_id,
            $test_patient_id,
            '2025-10-04',
            '09:00:00',
            'system',
            null
        );
        
        if ($log_result) {
            echo "✅ Appointment creation logged successfully<br>";
        } else {
            echo "❌ Appointment logging failed<br>";
        }
        
        // Test slot limit (create more appointments for same slot)
        echo "<h3>Test 3: Time Slot Limit (20 patients max)</h3>";
        $slot_full = false;
        $created_appointments = [$test_appointment_id];
        
        for ($i = 2; $i <= 21; $i++) {
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    patient_id, facility_id, referral_id, service_id, 
                    scheduled_date, scheduled_time, status, created_at, updated_at
                ) VALUES (?, ?, NULL, ?, '2025-10-04', '09:00:00', 'confirmed', NOW(), NOW())
            ");
            $stmt->bind_param("iii", $test_patient_id, $test_facility_id, $test_service_id);
            
            if ($stmt->execute()) {
                $appointment_id = $conn->insert_id;
                $created_appointments[] = $appointment_id;
                $stmt->close();
                
                $result = $queue_service->createQueueEntry(
                    $appointment_id, 
                    $test_patient_id,
                    $test_service_id,
                    'consultation', 
                    'normal',
                    null
                );
                
                if (!$result['success']) {
                    echo "❌ Slot full at patient $i: " . $result['error'] . "<br>";
                    $slot_full = true;
                    break;
                } else {
                    echo "✅ Patient $i added to queue (Queue #" . $result['queue_number'] . ")<br>";
                }
            }
        }
        
        if (!$slot_full) {
            echo "⚠️ Warning: Slot limit of 20 patients not enforced properly<br>";
        }
        
        // Clean up test data
        echo "<h3>Cleanup</h3>";
        $appointment_ids = implode(',', $created_appointments);
        $conn->query("DELETE FROM queue_logs WHERE queue_entry_id IN (SELECT queue_entry_id FROM queue_entries WHERE appointment_id IN ($appointment_ids))");
        $conn->query("DELETE FROM queue_entries WHERE appointment_id IN ($appointment_ids)");
        $conn->query("DELETE FROM visits WHERE appointment_id IN ($appointment_ids)");
        $conn->query("DELETE FROM appointment_logs WHERE appointment_id IN ($appointment_ids)");
        $conn->query("DELETE FROM appointments WHERE appointment_id IN ($appointment_ids)");
        
        // Only delete test patient if we created it
        if (!$existing_patient) {
            $conn->query("DELETE FROM patients WHERE patient_id = $test_patient_id");
            echo "✅ Test patient deleted<br>";
        }
        
        echo "✅ Test data cleaned up<br>";
        
    } else {
        echo "❌ Failed to create test appointment: " . $stmt->error . "<br>";
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
}

echo "<h3>Integration Test Complete</h3>";
echo "<p><a href='../index.php'>← Back to Homepage</a></p>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Appointment Queueing Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { color: #2c3e50; }
        h3 { color: #34495e; margin-top: 30px; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
    </style>
</head>
<body>
</body>
</html>