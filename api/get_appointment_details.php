<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Include session and database
$root_path = dirname(__DIR__);
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and authorized
if (!is_employee_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'debug' => 'Session not found']);
    exit();
}

$employee_id = get_employee_session('employee_id');
$role = get_employee_session('role');

if (!$employee_id || !$role) {
    echo json_encode(['success' => false, 'message' => 'Session data incomplete', 'debug' => 'employee_id or role missing']);
    exit();
}

$authorized_roles = ['admin', 'dho', 'bhw', 'doctor', 'nurse'];
if (!in_array(strtolower($role), $authorized_roles)) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions', 'role' => $role]);
    exit();
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$appointment_id = $_GET['appointment_id'] ?? '';

if (empty($appointment_id) || !is_numeric($appointment_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    $sql = "
        SELECT a.appointment_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
               a.cancellation_reason, a.created_at, a.updated_at,
               p.first_name, p.last_name, p.middle_name, p.username as patient_id,
               p.contact_number, p.date_of_birth, p.sex,
               f.name as facility_name, f.district as facility_district,
               b.barangay_name,
               s.name as service_name,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN services s ON a.service_id = s.service_id
        WHERE a.appointment_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }

    // Format the data with safe null handling
    $appointment['patient_name'] = trim(($appointment['last_name'] ?? '') . ', ' . ($appointment['first_name'] ?? '') . ' ' . ($appointment['middle_name'] ?? ''));
    $appointment['appointment_date'] = $appointment['scheduled_date'] ? date('F j, Y', strtotime($appointment['scheduled_date'])) : 'N/A';
    $appointment['time_slot'] = $appointment['scheduled_time'] ? date('g:i A', strtotime($appointment['scheduled_time'])) : 'N/A';
    $appointment['status'] = ucfirst($appointment['status'] ?? 'unknown');
    
    // Ensure service_name is available, provide fallback
    if (empty($appointment['service_name'])) {
        $appointment['service_name'] = 'General Consultation';
    }
    
    // Format cancellation details if applicable
    if (!empty($appointment['cancellation_reason'])) {
        $appointment['cancel_reason'] = $appointment['cancellation_reason'];
        $appointment['cancelled_at'] = $appointment['updated_at'] ? date('M j, Y g:i A', strtotime($appointment['updated_at'])) : 'N/A';
    }

    echo json_encode(['success' => true, 'appointment' => $appointment]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>