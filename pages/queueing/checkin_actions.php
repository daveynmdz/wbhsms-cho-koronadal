<?php
/**
 * AJAX Handler for Patient Check-In Operations
 * Handles check-in, flagging, and appointment cancellation requests
 * 
 * WBHSMS - City Health Office Queueing System
 * Created: October 2025
 */

// Start session and include required files
session_start();
require_once '../../config/db.php';
require_once '../../utils/queue_management_service.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// ==========================================
// SESSION & ACCESS CONTROL VALIDATION
// ==========================================

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Please log in to continue.'
    ]);
    exit;
}

// Define allowed roles for check-in operations
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = strtolower($_SESSION['role']);

if (!in_array($user_role, $allowed_roles)) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Insufficient permissions for this operation.'
    ]);
    exit;
}

// Get current employee ID
$employee_id = (int)$_SESSION['employee_id'];

// ==========================================
// REQUEST VALIDATION
// ==========================================

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

// Get and validate action parameter
$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required action parameter.'
    ]);
    exit;
}

// ==========================================
// INITIALIZE QUEUE MANAGEMENT SERVICE
// ==========================================

try {
    $queueService = new QueueManagementService($conn);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Service initialization failed: ' . $e->getMessage()
    ]);
    exit;
}

// ==========================================
// HANDLE CHECK-IN REQUEST
// ==========================================

if ($action === 'checkin_patient') {
    try {
        // Validate required fields
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        
        if (!$appointment_id || $appointment_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing or invalid appointment ID.'
            ]);
            exit;
        }
        
        // Enhanced check-in logic with facility-specific handling
        $result = enhancedCheckinPatient($conn, $appointment_id, $employee_id);
        
        // Return service response
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Check-in failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// HANDLE FLAG PATIENT REQUEST
// ==========================================

if ($action === 'flag_patient') {
    try {
        // Validate required fields
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $flag_type = filter_input(INPUT_POST, 'flag_type', FILTER_SANITIZE_STRING);
        $remarks = filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING);
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT); // Optional
        
        // Validate required fields
        if (!$patient_id || $patient_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing or invalid patient ID.'
            ]);
            exit;
        }
        
        if (empty($flag_type)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing flag type.'
            ]);
            exit;
        }
        
        if (empty($remarks)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing flag remarks.'
            ]);
            exit;
        }
        
        // Validate flag type against allowed values
        $valid_flag_types = [
            'false_senior', 'false_philhealth', 'false_patient_booked', 
            'incomplete_documents', 'behavioral_issue', 'medical_alert', 'other'
        ];
        
        if (!in_array($flag_type, $valid_flag_types)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid flag type specified.'
            ]);
            exit;
        }
        
        // Call flag service
        $result = $queueService->flag_patient($patient_id, $flag_type, $remarks, $employee_id, $appointment_id);
        
        // Return service response
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Patient flagging failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// HANDLE APPOINTMENT CANCELLATION
// ==========================================

if ($action === 'cancel_appointment') {
    try {
        // Validate required fields
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        
        if (!$appointment_id || $appointment_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing or invalid appointment ID.'
            ]);
            exit;
        }
        
        if (empty($reason)) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing cancellation reason.'
            ]);
            exit;
        }
        
        // Validate reason length (prevent excessive text)
        if (strlen($reason) > 500) {
            echo json_encode([
                'success' => false,
                'message' => 'Cancellation reason too long. Maximum 500 characters allowed.'
            ]);
            exit;
        }
        
        // Call cancellation service
        $result = $queueService->cancel_appointment($appointment_id, $reason, $employee_id);
        
        // Return service response
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment cancellation failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// HANDLE GET PATIENT DETAILS REQUEST
// ==========================================

if ($action === 'get_patient_details') {
    try {
        // Validate required fields
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT); // Optional
        
        if (!$patient_id || $patient_id <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Missing or invalid patient ID.'
            ]);
            exit;
        }
        
        // Call patient details service
        $result = $queueService->getPatientCheckInDetails($patient_id, $appointment_id);
        
        // Return service response
        echo json_encode($result);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve patient details: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// HANDLE SEARCH APPOINTMENTS REQUEST
// ==========================================

if ($action === 'search_appointments') {
    try {
        // Get search filters
        $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_SANITIZE_STRING);
        $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $barangay = filter_input(INPUT_POST, 'barangay', FILTER_SANITIZE_STRING);
        $appointment_date = filter_input(INPUT_POST, 'appointment_date', FILTER_SANITIZE_STRING);
        
        // Build search query
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        // Base query
        $sql = "
            SELECT 
                a.appointment_id,
                a.patient_id,
                a.scheduled_date,
                a.scheduled_time,
                a.status,
                a.service_id,
                p.first_name,
                p.last_name,
                p.contact_number,
                p.isSenior,
                p.isPWD,
                b.barangay_name as barangay,
                s.name as service_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
            LEFT JOIN services s ON a.service_id = s.service_id
        ";
        
        // Add search conditions
        if (!empty($appointment_id)) {
            // Handle both APT-00000024 and 24 formats
            $clean_id = preg_replace('/^APT-?0*/', '', $appointment_id);
            if (is_numeric($clean_id)) {
                $where_conditions[] = "a.appointment_id = ?";
                $params[] = (int)$clean_id;
                $param_types .= 'i';
            }
        }
        
        if ($patient_id && $patient_id > 0) {
            $where_conditions[] = "a.patient_id = ?";
            $params[] = $patient_id;
            $param_types .= 'i';
        }
        
        if (!empty($last_name)) {
            $where_conditions[] = "p.last_name LIKE ?";
            $params[] = '%' . $last_name . '%';
            $param_types .= 's';
        }
        
        if (!empty($barangay)) {
            $where_conditions[] = "b.barangay_name = ?";
            $params[] = $barangay;
            $param_types .= 's';
        }
        
        if (!empty($appointment_date)) {
            $where_conditions[] = "DATE(a.scheduled_date) = ?";
            $params[] = $appointment_date;
            $param_types .= 's';
        }
        
        // If no specific filters, default to today's appointments
        if (empty($where_conditions)) {
            $where_conditions[] = "DATE(a.scheduled_date) = CURDATE()";
        }
        
        // Complete the query
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
        $sql .= " ORDER BY a.scheduled_date DESC, a.scheduled_time ASC";
        $sql .= " LIMIT 50"; // Limit results for performance
        
        // Execute query
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $appointments = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Format the results
        $formatted_appointments = [];
        foreach ($appointments as $appointment) {
            $formatted_appointments[] = [
                'appointment_id' => $appointment['appointment_id'],
                'patient_id' => $appointment['patient_id'],
                'patient_name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'contact_number' => $appointment['contact_number'],
                'barangay' => $appointment['barangay'],
                'service_name' => $appointment['service_name'],
                'scheduled_date' => $appointment['scheduled_date'],
                'scheduled_time' => $appointment['scheduled_time'],
                'status' => $appointment['status'],
                'isSenior' => (bool)$appointment['isSenior'],
                'isPWD' => (bool)$appointment['isPWD']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => count($formatted_appointments) . ' appointment(s) found',
            'data' => $formatted_appointments
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Search failed: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ==========================================
// INVALID ACTION HANDLER
// ==========================================

// ==========================================
// ENHANCED CHECK-IN FUNCTION
// ==========================================

/**
 * Enhanced check-in logic with facility-specific handling
 * 
 * @param mysqli $conn Database connection
 * @param int $appointment_id Appointment ID
 * @param int $employee_id Employee performing check-in
 * @return array Check-in result
 */
function enhancedCheckinPatient($conn, $appointment_id, $employee_id) {
    try {
        $conn->begin_transaction();
        
        // Get appointment and facility details
        $stmt = $conn->prepare("
            SELECT a.*, f.facility_id, f.name as facility_name, f.type as facility_type,
                   p.first_name, p.last_name, p.isSenior, p.isPWD
            FROM appointments a
            JOIN facilities f ON a.facility_id = f.facility_id
            JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();
        
        if (!$appointment) {
            throw new Exception('Appointment not found');
        }
        
        // Check if already checked in
        if ($appointment['status'] === 'checked_in') {
            throw new Exception('Patient has already been checked in for this appointment');
        }
        
        // Validate appointment status
        if (!in_array($appointment['status'], ['confirmed', 'pending'])) {
            throw new Exception('Appointment cannot be checked in with status: ' . $appointment['status']);
        }
        
        $is_cho_facility = ($appointment['facility_id'] == 1); // City Health Office Main District
        $patient_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        
        // 1. Always create a visit record
        $stmt = $conn->prepare("
            INSERT INTO visits (patient_id, facility_id, appointment_id, visit_date, time_in, attendance_status, created_at)
            VALUES (?, ?, ?, CURDATE(), NOW(), 'on_time', NOW())
        ");
        $stmt->bind_param("iii", 
            $appointment['patient_id'], 
            $appointment['facility_id'], 
            $appointment_id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create visit record: ' . $stmt->error);
        }
        $visit_id = $conn->insert_id;
        $stmt->close();
        
        // 2. Update appointment status to checked_in
        $stmt = $conn->prepare("UPDATE appointments SET status = 'checked_in', updated_at = NOW() WHERE appointment_id = ?");
        $stmt->bind_param("i", $appointment_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update appointment status: ' . $stmt->error);
        }
        $stmt->close();
        
        $queue_result = null;
        
        // 3. Create queue entry ONLY for CHO facilities (facility_id = 1)
        if ($is_cho_facility) {
            try {
                $queueService = new QueueManagementService($conn);
                
                // Determine priority level
                $priority_level = ($appointment['isSenior'] || $appointment['isPWD']) ? 'priority' : 'normal';
                
                $queue_result = $queueService->createQueueEntry(
                    $appointment_id,
                    $appointment['patient_id'],
                    $appointment['service_id'],
                    'consultation',
                    $priority_level,
                    $employee_id
                );
                
                if (!$queue_result['success']) {
                    // Log but don't fail the check-in
                    error_log("Queue creation failed for appointment {$appointment_id}: " . ($queue_result['error'] ?? 'Unknown error'));
                }
                
            } catch (Exception $e) {
                // Log but don't fail the check-in
                error_log("Queue service error for appointment {$appointment_id}: " . $e->getMessage());
            }
        }
        
        $conn->commit();
        
        // Prepare success response based on facility type
        if ($is_cho_facility) {
            if ($queue_result && $queue_result['success']) {
                $response = [
                    'success' => true,
                    'message' => "Check-in successful for {$patient_name}",
                    'facility_message' => 'Queue number assigned - please proceed to the waiting area',
                    'visit_id' => $visit_id,
                    'queue_number' => $queue_result['queue_number'],
                    'queue_type' => $queue_result['queue_type'] ?? 'consultation',
                    'priority_level' => $queue_result['priority_level'] ?? 'normal',
                    'facility_type' => $appointment['facility_type'],
                    'has_queue' => true
                ];
            } else {
                $response = [
                    'success' => true,
                    'message' => "Check-in successful for {$patient_name}",
                    'facility_message' => 'Visit recorded - queue assignment pending',
                    'visit_id' => $visit_id,
                    'queue_number' => null,
                    'facility_type' => $appointment['facility_type'],
                    'has_queue' => false,
                    'queue_error' => $queue_result['error'] ?? 'Queue service unavailable'
                ];
            }
        } else {
            // BHC/DHO facilities - no queue required
            $response = [
                'success' => true,
                'message' => "Check-in successful for {$patient_name}",
                'facility_message' => 'Visit recorded — no queue required for this facility',
                'visit_id' => $visit_id,
                'queue_number' => null,
                'facility_type' => $appointment['facility_type'],
                'has_queue' => false,
                'no_queue_required' => true
            ];
        }
        
        $response['appointment_id'] = $appointment_id;
        $response['patient_name'] = $patient_name;
        $response['facility_name'] = $appointment['facility_name'];
        $response['is_cho_facility'] = $is_cho_facility;
        
        return $response;
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Enhanced check-in failed for appointment {$appointment_id}: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Check-in failed: ' . $e->getMessage(),
            'appointment_id' => $appointment_id
        ];
    }
}

// If we reach here, the action is not supported
echo json_encode([
    'success' => false,
    'message' => 'Invalid action specified. Supported actions: checkin_patient, flag_patient, cancel_appointment, get_patient_details, search_appointments.'
]);

?>