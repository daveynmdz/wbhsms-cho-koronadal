<?php
/**
 * Queue Simulation Script
 * Purpose: Simulate normal patient flow through the CHO Koronadal queue system
 * 
 * This script demonstrates the complete patient journey:
 * 1. Check-in (Appointment verification, queue entry creation)
 * 2. Triage (Vital signs, initial assessment)
 * 3. Consultation (Doctor examination)
 * 4. Laboratory (if needed)
 * 5. Pharmacy (if medications prescribed)
 * 6. Billing (payment processing)
 * 7. Document Station (certificates if needed)
 */

// Include necessary files
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// Check authentication for simulation access
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header("Location: ../management/auth/employee_login.php");
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$queueService = new QueueManagementService($pdo);
$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Check if role is authorized (admin or testing purposes)
if (!in_array(strtolower($employee_role), ['admin', 'doctor', 'nurse'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle AJAX requests for simulation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $simulation_id = $_POST['simulation_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'create_simulation':
                $result = createSimulation();
                echo json_encode($result);
                break;
                
            case 'simulate_checkin':
                $result = simulateCheckin($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_triage':
                $result = simulateTriage($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_consultation':
                $result = simulateConsultation($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_laboratory':
                $result = simulateLaboratory($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_pharmacy':
                $result = simulatePharmacy($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_billing':
                $result = simulateBilling($simulation_id);
                echo json_encode($result);
                break;
                
            case 'simulate_document':
                $result = simulateDocument($simulation_id);
                echo json_encode($result);
                break;
                
            case 'get_status':
                $result = getSimulationStatus($simulation_id);
                echo json_encode($result);
                break;
                
            case 'reset_simulation':
                $result = resetSimulation($simulation_id);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

/**
 * Create a new simulation with test patient and appointment
 */
function createSimulation() {
    global $pdo, $queueService, $employee_id;
    
    try {
        $pdo->beginTransaction();
        
        // Create test patient if not exists
        $test_patient_id = createTestPatient();
        
        // Create test appointment
        $appointment_data = createTestAppointment($test_patient_id);
        
        // For simulation, we just create the appointment - queue entry will be created during check-in
        // This simulates the patient having a confirmed appointment but not yet checked in
        
        $pdo->commit();
        
        return [
            'success' => true,
            'simulation_id' => $appointment_data['appointment_id'],
            'patient_id' => $test_patient_id,
            'appointment_id' => $appointment_data['appointment_id'],
            'message' => 'Simulation created successfully - Patient ready for check-in'
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Create test patient for simulation
 */
function createTestPatient() {
    global $pdo;
    
    $first_name = 'Test Patient ' . date('His');
    $last_name = 'Simulation';
    $email = 'test.simulation.' . time() . '@cho.local';
    
    // Check if test patient already exists
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($existing = $stmt->fetch()) {
        return $existing['patient_id'];
    }
    
    // Create new test patient
    $stmt = $pdo->prepare("
        INSERT INTO patients (
            first_name, last_name, email, phone_number, date_of_birth, gender, address,
            philhealth_number, emergency_contact_name, emergency_contact_phone, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $first_name,
        $last_name,
        $email,
        '0912-345-6789',
        '1990-01-01',
        'Male',
        'Test Address, Koronadal City',
        '12-345678901-2',
        'Emergency Contact',
        '0912-987-6543'
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Create test appointment for simulation
 */
function createTestAppointment($patient_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO appointments (
            patient_id, facility_id, service_id, scheduled_date, scheduled_time, 
            status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $scheduled_date = date('Y-m-d');
    $scheduled_time = date('H:i:s', strtotime('+1 hour'));
    
    $stmt->execute([
        $patient_id,
        1, // CHO Main District facility_id
        1, // General Consultation service_id
        $scheduled_date,
        $scheduled_time,
        'confirmed'
    ]);
    
    return [
        'appointment_id' => $pdo->lastInsertId(),
        'scheduled_date' => $scheduled_date,
        'scheduled_time' => $scheduled_time
    ];
}

/**
 * Simulate check-in process
 */
function simulateCheckin($simulation_id) {
    global $pdo, $queueService, $employee_id;
    
    try {
        // Get appointment details
        $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$simulation_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found'];
        }
        
        // Check if already checked in
        $existing_queue = getCurrentQueueEntry($simulation_id);
        if ($existing_queue) {
            return ['success' => false, 'message' => 'Patient already checked in'];
        }
        
        // Generate HHM-XXX queue code format
        $current_time = date('H:i');
        $hour = date('H');
        $meridiem = $hour < 12 ? 'A' : 'P';
        $hour_12 = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
        $hour_12 = str_pad($hour_12, 2, '0', STR_PAD_LEFT);
        
        // Get the next number in line for today
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM queue_entries qe 
            WHERE DATE(qe.time_in) = ? 
            AND qe.queue_code LIKE ?
        ");
        $time_prefix = $hour_12 . $meridiem;
        $stmt->execute([$today, $time_prefix . '-%']);
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_number = ($count_result['count'] ?? 0) + 1;
        
        $queue_code = $time_prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        // Create single queue entry that will follow patient through all stations
        $stmt = $pdo->prepare("
            INSERT INTO queue_entries (
                appointment_id, patient_id, service_id, queue_code, 
                status, priority, time_in, created_by, created_at
            ) VALUES (?, ?, ?, ?, 'waiting', 'normal', NOW(), ?, NOW())
        ");
        
        $stmt->execute([
            $appointment['appointment_id'],
            $appointment['patient_id'],
            $appointment['service_id'],
            $queue_code,
            $employee_id
        ]);
        
        $queue_entry_id = $pdo->lastInsertId();
        
        // Update appointment status to checked_in
        updateAppointmentStatus($simulation_id, 'checked_in');
        
        // Log the check-in action
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'check_in', NULL, 'waiting', ?, 'Patient checked in successfully', NOW())
        ");
        $stmt->execute([$queue_entry_id, $employee_id]);
        
        return [
            'success' => true,
            'message' => "Patient checked in successfully! Queue Code: {$queue_code}",
            'queue_code' => $queue_code,
            'queue_entry_id' => $queue_entry_id,
            'next_station' => 'triage'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate triage process
 */
function simulateTriage($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a triage station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'triage' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $triage_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$triage_station) {
            return ['success' => false, 'message' => 'No triage station available'];
        }
        
        // Update queue entry to assign to triage station and set to in_progress
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$triage_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log the triage start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'waiting', 'in_progress', ?, 'Patient called for triage assessment', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate triage completion
        sleep(1); // Simulate processing time
        
        // Complete triage
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'completed_station', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log triage completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_station', 'in_progress', 'completed_station', ?, 
                      'Vitals collected: BP 120/80, HR 72, Temp 36.5°C, Weight 70kg', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        return [
            'success' => true,
            'message' => 'Triage completed, patient ready for consultation',
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => 'consultation',
            'vitals' => 'BP 120/80, HR 72, Temp 36.5°C, Weight 70kg'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Triage failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate consultation process
 */
function simulateConsultation($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a consultation station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'consultation' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $consultation_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$consultation_station) {
            return ['success' => false, 'message' => 'No consultation station available'];
        }
        
        // Update queue entry to assign to consultation station
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$consultation_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log consultation start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'completed_station', 'in_progress', ?, 'Patient called for doctor consultation', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate consultation time
        sleep(1);
        
        // Complete consultation
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'completed_station', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log consultation completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_station', 'in_progress', 'completed_station', ?, 
                      'Consultation completed. Diagnosis: Upper respiratory tract infection', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // For simulation, randomly choose next station
        $next_station = (rand(1, 2) === 1) ? 'lab' : 'pharmacy';
        
        return [
            'success' => true,
            'message' => "Consultation completed, patient ready for {$next_station}",
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => $next_station,
            'diagnosis' => 'Upper respiratory tract infection',
            'treatment' => 'Prescribed antibiotics and rest'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Consultation failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate laboratory process
 */
function simulateLaboratory($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a lab station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'lab' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $lab_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lab_station) {
            return ['success' => false, 'message' => 'No laboratory station available'];
        }
        
        // Update queue entry to assign to lab station
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$lab_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log lab start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'completed_station', 'in_progress', ?, 'Patient called for laboratory tests', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate lab processing time
        sleep(1);
        
        // Complete lab work
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'completed_station', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log lab completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_station', 'in_progress', 'completed_station', ?, 
                      'Lab tests completed: CBC normal, Urinalysis normal', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        return [
            'success' => true,
            'message' => 'Lab work completed, patient ready for pharmacy',
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => 'pharmacy',
            'lab_results' => 'CBC: Normal, Urinalysis: Normal'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Laboratory failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate pharmacy process
 */
function simulatePharmacy($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a pharmacy station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'pharmacy' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $pharmacy_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$pharmacy_station) {
            return ['success' => false, 'message' => 'No pharmacy station available'];
        }
        
        // Update queue entry to assign to pharmacy station
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$pharmacy_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log pharmacy start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'completed_station', 'in_progress', ?, 'Patient called for medication dispensing', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate dispensing time
        sleep(1);
        
        // Complete pharmacy
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'completed_station', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log pharmacy completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_station', 'in_progress', 'completed_station', ?, 
                      'Medications dispensed: Amoxicillin 500mg (7 days), Paracetamol 500mg (as needed)', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        return [
            'success' => true,
            'message' => 'Medications dispensed, patient ready for billing',
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => 'billing',
            'medications' => 'Amoxicillin 500mg (7 days), Paracetamol 500mg (as needed)'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Pharmacy failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate billing process
 */
function simulateBilling($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a billing station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'billing' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $billing_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$billing_station) {
            return ['success' => false, 'message' => 'No billing station available'];
        }
        
        // Update queue entry to assign to billing station
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$billing_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log billing start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'completed_station', 'in_progress', ?, 'Patient called for billing and payment', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate billing processing
        sleep(1);
        
        // Complete billing
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'completed_station', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log billing completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_station', 'in_progress', 'completed_station', ?, 
                      'Payment processed: PHP 250.00 (Consultation: PHP 150, Medications: PHP 100)', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        return [
            'success' => true,
            'message' => 'Payment completed, patient ready for document station',
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => 'document',
            'total_amount' => 'PHP 250.00',
            'breakdown' => 'Consultation: PHP 150, Medications: PHP 100'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Billing failed: ' . $e->getMessage()];
    }
}

/**
 * Simulate document station process
 */
function simulateDocument($simulation_id) {
    global $pdo, $employee_id;
    
    $queue_entry = getCurrentQueueEntry($simulation_id);
    if (!$queue_entry) {
        return ['success' => false, 'message' => 'No queue entry found'];
    }
    
    try {
        // Get a document station
        $stmt = $pdo->prepare("
            SELECT station_id FROM stations 
            WHERE station_type = 'document' AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute();
        $document_station = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$document_station) {
            return ['success' => false, 'message' => 'No document station available'];
        }
        
        // Update queue entry to assign to document station
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET station_id = ?, status = 'in_progress', time_started = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$document_station['station_id'], $queue_entry['queue_entry_id']]);
        
        // Log document start
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'call_patient', 'completed_station', 'in_progress', ?, 'Patient called for medical certificate processing', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Simulate document processing
        sleep(1);
        
        // Complete document processing and entire visit
        $stmt = $pdo->prepare("
            UPDATE queue_entries 
            SET status = 'done', time_completed = NOW()
            WHERE queue_entry_id = ?
        ");
        $stmt->execute([$queue_entry['queue_entry_id']]);
        
        // Log final completion
        $stmt = $pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                employee_id, remarks, created_at
            ) VALUES (?, 'complete_visit', 'in_progress', 'done', ?, 
                      'Medical certificate issued. Patient visit completed.', NOW())
        ");
        $stmt->execute([$queue_entry['queue_entry_id'], $employee_id]);
        
        // Update appointment status to completed
        updateAppointmentStatus($simulation_id, 'completed');
        
        return [
            'success' => true,
            'message' => 'Medical certificate issued. Patient visit completed!',
            'queue_code' => $queue_entry['queue_code'],
            'next_station' => 'completed',
            'document' => 'Medical Certificate for 3 days rest'
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Document processing failed: ' . $e->getMessage()];
    }
}

/**
 * Get current queue entry for simulation
 */
function getCurrentQueueEntry($appointment_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT qe.*, s.station_type, s.station_name 
        FROM queue_entries qe
        LEFT JOIN stations s ON qe.station_id = s.station_id
        WHERE qe.appointment_id = ?
        ORDER BY qe.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$appointment_id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update appointment status
 */
function updateAppointmentStatus($appointment_id, $status) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE appointment_id = ?");
    return $stmt->execute([$status, $appointment_id]);
}

/**
 * Get simulation status
 */
function getSimulationStatus($simulation_id) {
    global $pdo;
    
    try {
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, p.first_name, p.last_name 
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ?
        ");
        $stmt->execute([$simulation_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Simulation not found'];
        }
        
        // Get all queue entries for this appointment
        $stmt = $pdo->prepare("
            SELECT qe.*, s.station_name, s.station_type 
            FROM queue_entries qe
            LEFT JOIN stations s ON qe.station_id = s.station_id
            WHERE qe.appointment_id = ?
            ORDER BY qe.created_at DESC
        ");
        $stmt->execute([$simulation_id]);
        $queue_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get queue logs
        $stmt = $pdo->prepare("
            SELECT ql.*, qe.station_id, s.station_name 
            FROM queue_logs ql
            JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
            LEFT JOIN stations s ON qe.station_id = s.station_id
            WHERE qe.appointment_id = ?
            ORDER BY ql.created_at DESC
        ");
        $stmt->execute([$simulation_id]);
        $queue_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'appointment' => $appointment,
            'queue_entries' => $queue_entries,
            'queue_logs' => $queue_logs
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Reset simulation
 */
function resetSimulation($simulation_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Delete queue logs
        $stmt = $pdo->prepare("
            DELETE ql FROM queue_logs ql
            JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
            WHERE qe.appointment_id = ?
        ");
        $stmt->execute([$simulation_id]);
        
        // Delete queue entries
        $stmt = $pdo->prepare("DELETE FROM queue_entries WHERE appointment_id = ?");
        $stmt->execute([$simulation_id]);
        
        // Delete visits
        $stmt = $pdo->prepare("DELETE FROM visits WHERE appointment_id = ?");
        $stmt->execute([$simulation_id]);
        
        // Delete appointment
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE appointment_id = ?");
        $stmt->execute([$simulation_id]);
        
        $pdo->commit();
        
        return ['success' => true, 'message' => 'Simulation reset successfully'];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Get current simulations for display
$current_simulations = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.scheduled_date, a.scheduled_time, a.status,
               p.first_name, p.last_name,
               COUNT(qe.queue_entry_id) as queue_count
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
        WHERE p.email LIKE '%@cho.local'
        GROUP BY a.appointment_id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $current_simulations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = $e->getMessage();
}

$activePage = 'queue_management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Simulation - CHO Koronadal</title>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .simulation-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .simulation-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .simulation-controls {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .control-card {
            flex: 1;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            min-width: 300px;
        }
        
        .station-flow {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .station-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
            transition: all 0.3s ease;
        }
        
        .station-card.completed {
            border-left-color: #27ae60;
            background: #f8fff8;
        }
        
        .station-card.active {
            border-left-color: #f39c12;
            background: #fffdf8;
        }
        
        .station-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .station-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .station-btn:hover {
            background: #2980b9;
        }
        
        .station-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }
        
        .status-log {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 400px;
            overflow-y: auto;
        }
        
        .log-entry {
            padding: 10px;
            border-left: 3px solid #3498db;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .log-entry.success {
            border-left-color: #27ae60;
        }
        
        .log-entry.error {
            border-left-color: #e74c3c;
        }
        
        .simulation-info {
            background: #ecf0f1;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .current-simulations {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .sim-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .sim-table th,
        .sim-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .sim-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s ease;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #ecf0f1;
            border-radius: 5px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #27ae60);
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="homepage">
        <!-- Include Sidebar -->
        <?php include '../../includes/sidebar_admin.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="simulation-container">
                <!-- Header -->
                <div class="simulation-header">
                    <h1><i class="fas fa-flask"></i> Queue System Simulation</h1>
                    <p>Simulate normal patient flow through the CHO Koronadal queue management system</p>
                </div>
                
                <!-- Current Simulations -->
                <div class="current-simulations">
                    <h2><i class="fas fa-list"></i> Recent Simulations</h2>
                    <?php if (!empty($current_simulations)): ?>
                        <table class="sim-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Date/Time</th>
                                    <th>Status</th>
                                    <th>Queue Entries</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_simulations as $sim): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sim['first_name'] . ' ' . $sim['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sim['scheduled_date'] . ' ' . $sim['scheduled_time']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $sim['status']; ?>">
                                                <?php echo ucfirst($sim['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $sim['queue_count']; ?></td>
                                        <td>
                                            <button class="btn-success" onclick="loadSimulation(<?php echo $sim['appointment_id']; ?>)">
                                                <i class="fas fa-play"></i> Load
                                            </button>
                                            <button class="btn-danger" onclick="resetSimulation(<?php echo $sim['appointment_id']; ?>)">
                                                <i class="fas fa-trash"></i> Reset
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No simulations found. Create a new simulation to get started.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Simulation Controls -->
                <div class="simulation-controls">
                    <div class="control-card">
                        <h3><i class="fas fa-play-circle"></i> Simulation Control</h3>
                        <button id="createSimBtn" class="btn-primary" onclick="createSimulation()">
                            <i class="fas fa-plus"></i> Create New Simulation
                        </button>
                        <div class="simulation-info" id="simulationInfo" style="display: none;">
                            <strong>Current Simulation:</strong>
                            <div id="patientInfo"></div>
                            <div id="appointmentInfo"></div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                        <div class="loading" id="loading">
                            <i class="fas fa-spinner fa-spin"></i> Processing...
                        </div>
                    </div>
                </div>
                
                <!-- Station Flow -->
                <div class="station-flow" id="stationFlow" style="display: none;">
                    <div class="station-card" id="checkin-card">
                        <h3><i class="fas fa-clipboard-check"></i> Check-In</h3>
                        <p>Verify appointment and create queue entry</p>
                        <button class="station-btn" id="checkin-btn" onclick="simulateStation('checkin')" disabled>
                            Simulate Check-In
                        </button>
                        <div class="station-status" id="checkin-status"></div>
                    </div>
                    
                    <div class="station-card" id="triage-card">
                        <h3><i class="fas fa-heartbeat"></i> Triage</h3>
                        <p>Collect vital signs and initial assessment</p>
                        <button class="station-btn" id="triage-btn" onclick="simulateStation('triage')" disabled>
                            Simulate Triage
                        </button>
                        <div class="station-status" id="triage-status"></div>
                    </div>
                    
                    <div class="station-card" id="consultation-card">
                        <h3><i class="fas fa-user-md"></i> Consultation</h3>
                        <p>Doctor examination and diagnosis</p>
                        <button class="station-btn" id="consultation-btn" onclick="simulateStation('consultation')" disabled>
                            Simulate Consultation
                        </button>
                        <div class="station-status" id="consultation-status"></div>
                    </div>
                    
                    <div class="station-card" id="laboratory-card">
                        <h3><i class="fas fa-microscope"></i> Laboratory</h3>
                        <p>Lab tests and sample collection</p>
                        <button class="station-btn" id="laboratory-btn" onclick="simulateStation('laboratory')" disabled>
                            Simulate Laboratory
                        </button>
                        <div class="station-status" id="laboratory-status"></div>
                    </div>
                    
                    <div class="station-card" id="pharmacy-card">
                        <h3><i class="fas fa-pills"></i> Pharmacy</h3>
                        <p>Medication dispensing and counseling</p>
                        <button class="station-btn" id="pharmacy-btn" onclick="simulateStation('pharmacy')" disabled>
                            Simulate Pharmacy
                        </button>
                        <div class="station-status" id="pharmacy-status"></div>
                    </div>
                    
                    <div class="station-card" id="billing-card">
                        <h3><i class="fas fa-receipt"></i> Billing</h3>
                        <p>Payment processing and invoicing</p>
                        <button class="station-btn" id="billing-btn" onclick="simulateStation('billing')" disabled>
                            Simulate Billing
                        </button>
                        <div class="station-status" id="billing-status"></div>
                    </div>
                    
                    <div class="station-card" id="document-card">
                        <h3><i class="fas fa-file-alt"></i> Document</h3>
                        <p>Medical certificates and documentation</p>
                        <button class="station-btn" id="document-btn" onclick="simulateStation('document')" disabled>
                            Simulate Document
                        </button>
                        <div class="station-status" id="document-status"></div>
                    </div>
                </div>
                
                <!-- Status Log -->
                <div class="status-log" id="statusLog" style="display: none;">
                    <h3><i class="fas fa-history"></i> Simulation Log</h3>
                    <div id="logEntries"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let currentSimulationId = null;
        let currentStep = 0;
        const totalSteps = 7; // Total number of stations
        
        // Station progression order
        const stationOrder = ['checkin', 'triage', 'consultation', 'laboratory', 'pharmacy', 'billing', 'document'];
        
        async function createSimulation() {
            showLoading(true);
            
            try {
                const response = await fetch('queue_simulation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'ajax=1&action=create_simulation'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentSimulationId = result.simulation_id;
                    updateSimulationInfo(result);
                    showStationFlow(true);
                    enableStation('checkin');
                    addLogEntry('success', `Simulation created successfully! Queue Code: ${result.queue_code || 'N/A'}`);
                    document.getElementById('createSimBtn').style.display = 'none';
                } else {
                    addLogEntry('error', 'Failed to create simulation: ' + result.message);
                }
            } catch (error) {
                addLogEntry('error', 'Error creating simulation: ' + error.message);
            }
            
            showLoading(false);
        }
        
        async function simulateStation(station) {
            if (!currentSimulationId) {
                addLogEntry('error', 'No active simulation');
                return;
            }
            
            showLoading(true);
            disableAllStations();
            
            try {
                const response = await fetch('queue_simulation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=1&action=simulate_${station}&simulation_id=${currentSimulationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    markStationCompleted(station);
                    addLogEntry('success', result.message);
                    
                    // Add additional info to station status
                    const statusDiv = document.getElementById(`${station}-status`);
                    if (result.vitals) statusDiv.innerHTML += `<br><small><strong>Vitals:</strong> ${result.vitals}</small>`;
                    if (result.diagnosis) statusDiv.innerHTML += `<br><small><strong>Diagnosis:</strong> ${result.diagnosis}</small>`;
                    if (result.lab_results) statusDiv.innerHTML += `<br><small><strong>Lab Results:</strong> ${result.lab_results}</small>`;
                    if (result.medications) statusDiv.innerHTML += `<br><small><strong>Medications:</strong> ${result.medications}</small>`;
                    if (result.total_amount) statusDiv.innerHTML += `<br><small><strong>Amount:</strong> ${result.total_amount}</small>`;
                    if (result.document) statusDiv.innerHTML += `<br><small><strong>Document:</strong> ${result.document}</small>`;
                    
                    // Enable next station or complete simulation
                    if (result.next_station && result.next_station !== 'completed') {
                        enableStation(result.next_station);
                    } else {
                        addLogEntry('success', 'Simulation completed successfully! 🎉');
                        updateProgress(100);
                    }
                    
                    currentStep++;
                    updateProgress((currentStep / totalSteps) * 100);
                } else {
                    addLogEntry('error', 'Failed to simulate ' + station + ': ' + result.message);
                    enableStation(station); // Re-enable if failed
                }
            } catch (error) {
                addLogEntry('error', 'Error simulating ' + station + ': ' + error.message);
                enableStation(station); // Re-enable if failed
            }
            
            showLoading(false);
        }
        
        async function loadSimulation(simulationId) {
            currentSimulationId = simulationId;
            showLoading(true);
            
            try {
                const response = await fetch('queue_simulation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=1&action=get_status&simulation_id=${simulationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    updateSimulationInfo({
                        patient_id: result.appointment.patient_id,
                        appointment_id: result.appointment.appointment_id,
                        simulation_id: simulationId
                    });
                    
                    showStationFlow(true);
                    document.getElementById('createSimBtn').style.display = 'none';
                    
                    // Update stations based on queue entries
                    result.queue_entries.forEach(entry => {
                        if (entry.status === 'done') {
                            markStationCompleted(entry.station_type);
                        }
                    });
                    
                    // Update log with existing entries
                    document.getElementById('logEntries').innerHTML = '';
                    result.queue_logs.forEach(log => {
                        addLogEntry('success', `${log.action}: ${log.remarks || log.new_status}`);
                    });
                    
                    addLogEntry('success', 'Simulation loaded successfully');
                } else {
                    addLogEntry('error', 'Failed to load simulation: ' + result.message);
                }
            } catch (error) {
                addLogEntry('error', 'Error loading simulation: ' + error.message);
            }
            
            showLoading(false);
        }
        
        async function resetSimulation(simulationId) {
            if (!confirm('Are you sure you want to reset this simulation? This will delete all data.')) {
                return;
            }
            
            showLoading(true);
            
            try {
                const response = await fetch('queue_simulation.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ajax=1&action=reset_simulation&simulation_id=${simulationId}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    addLogEntry('success', 'Simulation reset successfully');
                    location.reload(); // Refresh page to update the list
                } else {
                    addLogEntry('error', 'Failed to reset simulation: ' + result.message);
                }
            } catch (error) {
                addLogEntry('error', 'Error resetting simulation: ' + error.message);
            }
            
            showLoading(false);
        }
        
        function updateSimulationInfo(data) {
            const infoDiv = document.getElementById('simulationInfo');
            const patientDiv = document.getElementById('patientInfo');
            const appointmentDiv = document.getElementById('appointmentInfo');
            
            patientDiv.innerHTML = `Patient ID: ${data.patient_id}`;
            appointmentDiv.innerHTML = `Appointment ID: ${data.appointment_id}`;
            
            infoDiv.style.display = 'block';
        }
        
        function showStationFlow(show) {
            document.getElementById('stationFlow').style.display = show ? 'grid' : 'none';
            document.getElementById('statusLog').style.display = show ? 'block' : 'none';
        }
        
        function enableStation(station) {
            const btn = document.getElementById(`${station}-btn`);
            if (btn) {
                btn.disabled = false;
                document.getElementById(`${station}-card`).classList.add('active');
            }
        }
        
        function disableAllStations() {
            stationOrder.forEach(station => {
                const btn = document.getElementById(`${station}-btn`);
                if (btn) {
                    btn.disabled = true;
                }
            });
        }
        
        function markStationCompleted(station) {
            const card = document.getElementById(`${station}-card`);
            const btn = document.getElementById(`${station}-btn`);
            const status = document.getElementById(`${station}-status`);
            
            if (card) {
                card.classList.remove('active');
                card.classList.add('completed');
            }
            
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-check"></i> Completed';
            }
            
            if (status) {
                status.innerHTML = '<i class="fas fa-check-circle" style="color: #27ae60;"></i> Completed';
            }
        }
        
        function showLoading(show) {
            document.getElementById('loading').style.display = show ? 'block' : 'none';
        }
        
        function addLogEntry(type, message) {
            const logDiv = document.getElementById('logEntries');
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span>${message}</span>
                    <small>${new Date().toLocaleTimeString()}</small>
                </div>
            `;
            
            logDiv.insertBefore(entry, logDiv.firstChild);
            
            // Limit to 20 entries
            while (logDiv.children.length > 20) {
                logDiv.removeChild(logDiv.lastChild);
            }
        }
        
        function updateProgress(percentage) {
            document.getElementById('progressFill').style.width = percentage + '%';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            addLogEntry('success', 'Queue Simulation System Ready');
        });
    </script>
</body>
</html>