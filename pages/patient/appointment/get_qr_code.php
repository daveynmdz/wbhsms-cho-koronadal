<?php
/**
 * Get QR Code for Appointment
 * Returns QR code image data for patient appointments
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Validate input
$appointment_id = intval($_GET['appointment_id'] ?? 0);
if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

$patient_id = $_SESSION['patient_id'];

try {
    // Verify appointment belongs to the logged-in patient and get QR code
    $stmt = $conn->prepare("
        SELECT appointment_id, qr_code_path, status, scheduled_date, scheduled_time
        FROM appointments 
        WHERE appointment_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit();
    }

    // Check if appointment has QR code
    if (!$appointment['qr_code_path']) {
        echo json_encode(['success' => false, 'message' => 'QR code not available for this appointment']);
        exit();
    }

    // Check appointment status
    if ($appointment['status'] === 'cancelled') {
        echo json_encode(['success' => false, 'message' => 'QR code not available for cancelled appointments']);
        exit();
    }

    // Convert BLOB to base64 for display
    $qr_image_base64 = base64_encode($appointment['qr_code_path']);
    
    // Prepare appointment details for QR validation
    $appointment_ref = 'APT-' . str_pad($appointment_id, 8, '0', STR_PAD_LEFT);
    
    echo json_encode([
        'success' => true,
        'message' => 'QR code retrieved successfully',
        'qr_image' => $qr_image_base64,
        'appointment_id' => $appointment_ref,
        'status' => $appointment['status'],
        'scheduled_date' => $appointment['scheduled_date'],
        'scheduled_time' => $appointment['scheduled_time']
    ]);

} catch (Exception $e) {
    error_log("Error retrieving QR code for appointment $appointment_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred while retrieving QR code']);
}
?>