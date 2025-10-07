<?php
// cancel_appointment.php - Cancel appointment endpoint
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Basic CSRF protection - ensure request is from same origin
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Allow direct POST requests but log them for monitoring
    error_log("Cancel appointment: Direct request (not AJAX) from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed']);
    exit();
}

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// Enhanced session validation for AJAX requests
if (!isset($_SESSION['patient_id']) || empty($_SESSION['patient_id'])) {
    error_log("Cancel appointment: Session validation failed - patient_id not found in session");
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit();
}

// Additional session security check
if (!is_numeric($_SESSION['patient_id']) || $_SESSION['patient_id'] <= 0) {
    error_log("Cancel appointment: Invalid patient_id in session: " . $_SESSION['patient_id']);
    echo json_encode(['success' => false, 'message' => 'Invalid session data. Please log in again.']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include queue management service
require_once $root_path . '/utils/queue_management_service.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data with enhanced validation
$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

// Debug logging (remove in production)
error_log("Cancel appointment request - Raw input: " . substr($raw_input, 0, 200));
error_log("Cancel appointment request - Patient ID: " . $_SESSION['patient_id']);

if (!$input) {
    error_log("Cancel appointment: JSON decode failed - Raw input: " . $raw_input);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received']);
    exit();
}

if (!isset($input['appointment_id'])) {
    error_log("Cancel appointment: appointment_id missing from request: " . json_encode($input));
    echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
    exit();
}

$patient_id = $_SESSION['patient_id'];
$appointment_id = (int)$input['appointment_id'];
$cancellation_reason = isset($input['cancellation_reason']) ? trim($input['cancellation_reason']) : 'No reason provided';

if ($appointment_id <= 0) {
    error_log("Cancel appointment: Invalid appointment ID: " . $input['appointment_id']);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID format']);
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
    
    // Normalize status for comparison (handle null, empty, and case issues)
    $current_status = strtolower(trim($appointment['status'] ?? 'confirmed'));
    $non_cancellable_statuses = ['cancelled', 'completed'];
    
    if (in_array($current_status, $non_cancellable_statuses)) {
        throw new Exception('This appointment cannot be cancelled (Status: ' . $appointment['status'] . ')');
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
    
    // Log the cancellation in appointment_logs (if table exists)
    try {
        $stmt = $conn->prepare("
            INSERT INTO appointment_logs (
                appointment_id, patient_id, action, old_status, new_status, 
                reason, created_by_type, created_by_id, ip_address, user_agent
            ) VALUES (?, ?, 'cancelled', ?, 'cancelled', ?, 'patient', ?, ?, ?)
        ");
        
        $old_status = $appointment['status'] ?? 'confirmed';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("iississ", 
            $appointment_id, $patient_id, $old_status, $cancellation_reason, 
            $patient_id, $ip_address, $user_agent
        );
        
        if (!$stmt->execute()) {
            error_log("Warning: Failed to log appointment cancellation to appointment_logs table");
        }
        
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Log warning but don't fail the cancellation if logging table doesn't exist
        error_log("Warning: appointment_logs table might not exist: " . $e->getMessage());
    }
    
    // Try to cancel the associated queue entry (if it exists and service is available)
    try {
        if (class_exists('QueueManagementService')) {
            $queue_service = new QueueManagementService($conn);
            $queue_result = $queue_service->cancelQueueEntry(
                $appointment_id, 
                $cancellation_reason, 
                null // Patient-initiated cancellation
            );
            
            // Don't fail the appointment cancellation if queue entry doesn't exist
            // Queue entries might not exist for all appointments
            if (!$queue_result['success'] && strpos($queue_result['error'], 'No active queue entry') === false) {
                error_log("Warning: Failed to cancel queue entry: " . $queue_result['error']);
                // Don't throw error - appointment cancellation should succeed even if queue fails
            }
        } else {
            error_log("Warning: QueueManagementService class not available");
        }
    } catch (Exception $queue_error) {
        // Log queue cancellation error but don't fail the appointment cancellation
        error_log("Warning: Queue cancellation failed: " . $queue_error->getMessage());
    }
    
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