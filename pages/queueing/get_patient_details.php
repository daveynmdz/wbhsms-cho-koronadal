<?php
/**
 * Get Patient Details API
 * Used by checkin.php to fetch patient and appointment details for the modal
 */

// Turn off error display to prevent JSON corruption
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include employee session configuration first
require_once '../../config/session/employee_session.php';
require_once '../../config/db.php';

// Set JSON header
header('Content-Type: application/json');

// Clear any output buffer to ensure clean JSON
if (ob_get_level()) {
    ob_clean();
}

// Check database connection
if (!isset($pdo) || !$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

// Check if user is logged in and authorized
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = $_SESSION['role'] ?? '';

if (!isset($_SESSION['employee_id']) || !in_array($user_role, $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Get parameters
$appointment_id = $_GET['appointment_id'] ?? 0;
$patient_id = $_GET['patient_id'] ?? 0;

if (!$appointment_id || !$patient_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit();
}

try {
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT p.*, b.barangay_name as barangay,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p 
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
        exit();
    }
    
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, 
               DATE_FORMAT(a.scheduled_date, '%M %d, %Y') as formatted_date,
               TIME_FORMAT(a.scheduled_time, '%h:%i %p') as formatted_time
        FROM appointments a
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$appointment_id, $patient_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        exit();
    }
    
    // Format appointment details
    $appointment['appointment_date'] = $appointment['formatted_date'];
    $appointment['appointment_time'] = $appointment['formatted_time'];
    
    // Get service name if service_id exists
    if (isset($appointment['service_id']) && $appointment['service_id']) {
        $service_stmt = $pdo->prepare("SELECT name FROM services WHERE service_id = ?");
        $service_stmt->execute([$appointment['service_id']]);
        $service_name = $service_stmt->fetchColumn();
        $appointment['service_type'] = $service_name ?: 'General Consultation';
    } else {
        $appointment['service_type'] = 'General Consultation';
    }
    
    // Convert boolean fields to proper booleans
    $patient['isSenior'] = (bool) $patient['isSenior'];
    $patient['isPWD'] = (bool) $patient['isPWD'];
    
    // Clean string fields to prevent JSON issues
    foreach ($patient as $key => $value) {
        if (is_string($value)) {
            $patient[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
    }
    
    foreach ($appointment as $key => $value) {
        if (is_string($value)) {
            $appointment[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
    }
    
    // Return the data
    $response = [
        'success' => true,
        'patient' => $patient,
        'appointment' => $appointment
    ];
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        echo json_encode(['success' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()]);
    } else {
        echo $json;
    }
    
} catch (Exception $e) {
    error_log("Get patient details error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database error occurred',
        'debug_message' => $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine()
    ]);
}
?>