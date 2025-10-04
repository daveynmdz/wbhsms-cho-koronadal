<?php
/**
 * Appointment Status Update API
 * For healthcare staff to update appointment statuses
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Include session and database
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';

// Simple authentication check (should be replaced with proper staff session)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$appointment_id = $input['appointment_id'] ?? '';
$new_status = $input['new_status'] ?? '';
$cancellation_reason = $input['cancellation_reason'] ?? null;

// Validate required fields
if (empty($appointment_id) || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Validate cancellation reason for cancelled appointments
if ($new_status === 'cancelled' && empty($cancellation_reason)) {
    echo json_encode(['success' => false, 'message' => 'Cancellation reason required']);
    exit();
}

$conn->begin_transaction();

try {
    // Get current appointment details
    $stmt = $conn->prepare("
        SELECT a.*, r.referral_id, r.status as referral_status 
        FROM appointments a 
        LEFT JOIN referrals r ON a.referral_id = r.referral_id 
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
    
    $old_status = $appointment['status'];
    
    // Update appointment status
    if ($new_status === 'cancelled') {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = ?, cancellation_reason = ?, cancelled_at = NOW(), updated_at = NOW()
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("ssi", $new_status, $cancellation_reason, $appointment_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE appointments 
            SET status = ?, updated_at = NOW()
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("si", $new_status, $appointment_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update appointment status');
    }
    $stmt->close();
    
    // Handle referral status updates based on business rules
    if ($appointment['referral_id']) {
        $referral_id = $appointment['referral_id'];
        
        switch ($new_status) {
            case 'cancelled':
                // If appointment is cancelled, revert referral to active (can be used again)
                if ($appointment['referral_status'] === 'accepted') {
                    $stmt = $conn->prepare("
                        UPDATE referrals 
                        SET status = 'active', 
                            updated_at = NOW(),
                            notes = CONCAT(COALESCE(notes, ''), 'Appointment cancelled - referral reactivated. ')
                        WHERE referral_id = ?
                    ");
                    $stmt->bind_param("i", $referral_id);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
                
            case 'completed':
                // When appointment is completed, mark referral as used/fulfilled
                $stmt = $conn->prepare("
                    UPDATE referrals 
                    SET status = 'accepted', 
                        updated_at = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), 'Appointment completed successfully. ')
                    WHERE referral_id = ?
                ");
                $stmt->bind_param("i", $referral_id);
                $stmt->execute();
                $stmt->close();
                break;
                
            case 'confirmed':
                // Keep referral as accepted when confirmed
                break;
                
            case 'pending':
                // If reverting to pending, keep referral as accepted
                break;
        }
    }
    
    // Log the status change
    $stmt = $conn->prepare("
        INSERT INTO appointment_status_log (appointment_id, old_status, new_status, changed_by, change_reason, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $changed_by = $_SESSION['user_id'] ?? $_SESSION['patient_id'] ?? 'system';
    $change_reason = $cancellation_reason ?: "Status changed from $old_status to $new_status";
    $stmt->bind_param("issss", $appointment_id, $old_status, $new_status, $changed_by, $change_reason);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment status updated successfully',
        'old_status' => $old_status,
        'new_status' => $new_status,
        'appointment_id' => $appointment_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>