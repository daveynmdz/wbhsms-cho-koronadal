<?php
// cancel_appointment.php - Cancel appointment endpoint
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, return error
if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$patient_id = $_SESSION['patient_id'];
$appointment_id = (int)$input['appointment_id'];
$cancellation_reason = isset($input['cancellation_reason']) ? trim($input['cancellation_reason']) : 'No reason provided';

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if appointment exists and belongs to the patient
    $stmt = $conn->prepare("
        SELECT appointment_id, status, scheduled_date, scheduled_time
        FROM appointments 
        WHERE appointment_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();
    
    if (!$appointment) {
        throw new Exception('Appointment not found or access denied');
    }
    
    if ($appointment['status'] !== 'confirmed') {
        throw new Exception('Only confirmed appointments can be cancelled');
    }
    
    // Check if appointment is in the future
    $appointment_datetime = $appointment['scheduled_date'] . ' ' . $appointment['scheduled_time'];
    if (strtotime($appointment_datetime) <= time()) {
        throw new Exception('Cannot cancel past appointments');
    }
    
    // Update appointment status to cancelled
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET status = 'cancelled', cancellation_reason = ?, updated_at = NOW()
        WHERE appointment_id = ? AND patient_id = ?
    ");
    $stmt->bind_param("sii", $cancellation_reason, $appointment_id, $patient_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to cancel appointment');
    }
    
    $stmt->close();
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>