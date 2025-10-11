<?php
/**
 * AJAX Handler for Patient Check-In Operations
 * Handles check-in, flagging, and appointment cancellation requests
 * 
 * WBHSMS - City Health Office Queueing System
 * Created: October 2025
 */

// Include employee session configuration first
require_once '../../config/session/employee_session.php';
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
    $queueService = new QueueManagementService($pdo);
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
        
        // Call check-in service
        $result = $queueService->checkin_patient($appointment_id, $employee_id);
        
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
// INVALID ACTION HANDLER
// ==========================================

// If we reach here, the action is not supported
echo json_encode([
    'success' => false,
    'message' => 'Invalid action specified. Supported actions: checkin_patient, flag_patient, cancel_appointment, get_patient_details.'
]);

?>