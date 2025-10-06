<?php
/**
 * Get Patient Details API
 * Used by checkin.php to fetch patient and appointment details for the modal
 */

// Include employee session configuration first
require_once '../../config/session/employee_session.php';
require_once '../../config/db.php';

// Set JSON header
header('Content-Type: application/json');

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
               TIME_FORMAT(a.scheduled_time, '%h:%i %p') as formatted_time,
               s.name as service_type
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
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
    
    // Convert boolean fields to proper booleans
    $patient['isSenior'] = (bool) $patient['isSenior'];
    $patient['isPWD'] = (bool) $patient['isPWD'];
    
    // Return the data
    echo json_encode([
        'success' => true,
        'patient' => $patient,
        'appointment' => $appointment
    ]);
    
} catch (Exception $e) {
    error_log("Get patient details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>