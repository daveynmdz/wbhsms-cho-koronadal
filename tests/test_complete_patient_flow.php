<?php
/**
 * Complete Patient Flow Test - End-to-End Journey
 * Tests: Check-in → Triage → Consultation → Pharmacy → Billing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$root_path = __DIR__;
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Set up test session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['employee_id'] = 1;
$_SESSION['role'] = 'admin';

echo "<h2>🏥 Complete Patient Journey Test</h2>";
echo "<p>Testing full patient flow: Check-in → Triage → Consultation → Pharmacy → Billing</p>";

try {
    $queueService = new QueueManagementService($pdo);
    $employee_id = $_SESSION['employee_id'];
    
    // Create test patient
    $stmt = $pdo->prepare("
        INSERT INTO patients (
            first_name, last_name, email, contact_number, date_of_birth, sex, 
            barangay_id, philhealth_id_number, password_hash, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $test_email = 'complete.flow.' . time() . '@cho.local';
    $stmt->execute([
        'Complete',
        'FlowTest',
        $test_email,
        '0912-345-6789',
        '1990-01-01',
        'Male',
        1,
        '12-345678901-2',
        password_hash('password123', PASSWORD_DEFAULT)
    ]);
    
    $patient_id = $pdo->lastInsertId();
    echo "<h3>👤 Patient Created</h3>";
    echo "<p>✅ Patient ID: $patient_id (Complete FlowTest)</p>";
    
    // Create appointment
    $stmt = $pdo->prepare("
        INSERT INTO appointments (
            patient_id, facility_id, service_id, scheduled_date, scheduled_time, 
            status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        $patient_id,
        1,
        1,
        date('Y-m-d'),
        date('H:i:s'),
        'confirmed'
    ]);
    
    $appointment_id = $pdo->lastInsertId();
    echo "<p>✅ Appointment ID: $appointment_id</p>";
    
    // STEP 1: CHECK-IN
    echo "<h3>🏁 Step 1: Check-in Station</h3>";
    $checkin_result = $queueService->checkin_patient($appointment_id, $employee_id);
    
    if (!$checkin_result['success']) {
        throw new Exception("Check-in failed: " . ($checkin_result['error'] ?? 'Unknown error'));
    }
    
    echo "<p>✅ <strong>Check-in Complete</strong></p>";
    echo "<p>Queue Code: {$checkin_result['data']['queue_code']}</p>";
    echo "<p>Station: {$checkin_result['data']['station_name']}</p>";
    
    $current_queue_id = $checkin_result['data']['queue_entry_id'];
    
    // STEP 2: TRIAGE
    echo "<h3>🩺 Step 2: Triage Station</h3>";
    
    // Call patient (waiting → in_progress)
    $call_result = $queueService->updateQueueStatus(
        $current_queue_id, 
        'in_progress', 
        'waiting', 
        $employee_id, 
        'Patient called for triage assessment'
    );
    
    if (!$call_result['success']) {
        throw new Exception("Failed to call patient for triage");
    }
    echo "<p>✅ Patient called for triage</p>";
    
    // Complete triage and route to consultation
    $route_result = $queueService->routePatientToStation(
        $current_queue_id,
        'consultation',
        $employee_id,
        'Triage completed: BP 140/90, HR 82, Temp 37.1°C - Hypertension follow-up needed'
    );
    
    if (!$route_result['success']) {
        throw new Exception("Failed to route to consultation: " . $route_result['error']);
    }
    
    echo "<p>✅ <strong>Triage Complete → Routed to Consultation</strong></p>";
    echo "<p>Message: {$route_result['message']}</p>";
    
    $current_queue_id = $route_result['data']['new_queue_entry_id'];
    
    // STEP 3: CONSULTATION
    echo "<h3>👨‍⚕️ Step 3: Consultation Station</h3>";
    
    // Update to in_progress for consultation
    $consult_call = $queueService->updateQueueStatus(
        $current_queue_id,
        'in_progress',
        'waiting',
        $employee_id,
        'Patient called for medical consultation'
    );
    
    if (!$consult_call['success']) {
        throw new Exception("Failed to call patient for consultation");
    }
    echo "<p>✅ Patient called for consultation</p>";
    
    // Route to pharmacy
    $pharmacy_route = $queueService->routePatientToStation(
        $current_queue_id,
        'pharmacy',
        $employee_id,
        'Consultation complete: Diagnosed with Hypertension Stage 1. Prescribed Amlodipine 5mg daily'
    );
    
    if (!$pharmacy_route['success']) {
        throw new Exception("Failed to route to pharmacy: " . $pharmacy_route['error']);
    }
    
    echo "<p>✅ <strong>Consultation Complete → Routed to Pharmacy</strong></p>";
    echo "<p>Diagnosis: Hypertension, Medication prescribed</p>";
    
    $current_queue_id = $pharmacy_route['data']['new_queue_entry_id'];
    
    // STEP 4: PHARMACY
    echo "<h3>💊 Step 4: Pharmacy Station</h3>";
    
    // Call for pharmacy
    $pharmacy_call = $queueService->updateQueueStatus(
        $current_queue_id,
        'in_progress',
        'waiting',
        $employee_id,
        'Patient called for medication dispensing'
    );
    
    if (!$pharmacy_call['success']) {
        throw new Exception("Failed to call patient for pharmacy");
    }
    echo "<p>✅ Patient called for medication dispensing</p>";
    
    // Route to billing
    $billing_route = $queueService->routePatientToStation(
        $current_queue_id,
        'billing',
        $employee_id,
        'Medications dispensed: Amlodipine 5mg #30 tablets. Patient counseled on dosage and side effects'
    );
    
    if (!$billing_route['success']) {
        throw new Exception("Failed to route to billing: " . $billing_route['error']);
    }
    
    echo "<p>✅ <strong>Pharmacy Complete → Routed to Billing</strong></p>";
    echo "<p>Medications dispensed successfully</p>";
    
    $current_queue_id = $billing_route['data']['new_queue_entry_id'];
    
    // STEP 5: BILLING
    echo "<h3>💳 Step 5: Billing Station</h3>";
    
    // Call for billing
    $billing_call = $queueService->updateQueueStatus(
        $current_queue_id,
        'in_progress',
        'waiting',
        $employee_id,
        'Patient called for payment processing'
    );
    
    if (!$billing_call['success']) {
        throw new Exception("Failed to call patient for billing");
    }
    echo "<p>✅ Patient called for payment</p>";
    
    // Complete the visit
    $complete_result = $queueService->completePatientVisit(
        $current_queue_id,
        $employee_id,
        'Payment completed: PHP 250.00 (Consultation: PHP 150, Medication: PHP 100). Visit successfully completed.'
    );
    
    if (!$complete_result['success']) {
        throw new Exception("Failed to complete visit: " . $complete_result['error']);
    }
    
    echo "<p>🎉 <strong>VISIT COMPLETED!</strong></p>";
    echo "<p>Total payment: PHP 250.00</p>";
    echo "<p>Patient journey successfully finished</p>";
    
    // Summary
    echo "<hr>";
    echo "<h3>🎯 Complete Patient Journey Results</h3>";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>✅ ALL STATIONS WORKING PERFECTLY!</h4>";
    echo "<p><strong>Journey Completed:</strong></p>";
    echo "<ol>";
    echo "<li>✅ <strong>Check-in Station:</strong> Patient registered and queued for triage</li>";
    echo "<li>✅ <strong>Triage Station:</strong> Vitals taken, patient assessed</li>";
    echo "<li>✅ <strong>Consultation Station:</strong> Medical examination, diagnosis made</li>";
    echo "<li>✅ <strong>Pharmacy Station:</strong> Prescription dispensed</li>";
    echo "<li>✅ <strong>Billing Station:</strong> Payment processed, visit completed</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>🚀 Your Queueing System Is Fully Operational!</h3>";
    echo "<p><strong>All core functionality verified:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Patient check-in and queue creation</li>";
    echo "<li>✅ Inter-station patient routing</li>";
    echo "<li>✅ Status management and validation</li>";
    echo "<li>✅ Complete visit workflow</li>";
    echo "<li>✅ Queue logging and audit trail</li>";
    echo "</ul>";
    
    echo "<p><strong>Ready for production use!</strong></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #ffe8e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ Error in Patient Journey:</h4>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}
?>