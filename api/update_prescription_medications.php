<?php
// Include employee session configuration
$root_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Set JSON content type
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check permissions - only Admin (role_id=1) and Pharmacist (role_id=9) can update
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [1, 9])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['prescription_id']) || !isset($input['medication_statuses'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }
    
    $prescriptionId = intval($input['prescription_id']);
    $medicationStatuses = $input['medication_statuses'];
    
    if (!$prescriptionId) {
        echo json_encode(['success' => false, 'message' => 'Invalid prescription ID']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // First, reset all medications for this prescription to 'pending'
        $resetQuery = "UPDATE prescribed_medications SET status = 'pending', updated_at = NOW() WHERE prescription_id = ?";
        $resetStmt = $conn->prepare($resetQuery);
        $resetStmt->bind_param("i", $prescriptionId);
        $resetStmt->execute();
        
        // Update each medication status
        if (!empty($medicationStatuses)) {
            $updateQuery = "UPDATE prescribed_medications SET status = ?, updated_at = NOW() WHERE prescribed_medication_id = ? AND prescription_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            
            foreach ($medicationStatuses as $medStatus) {
                $medicationId = intval($medStatus['medication_id']);
                $status = $medStatus['status']; // 'dispensed' or 'unavailable'
                
                // Validate status
                if (!in_array($status, ['dispensed', 'unavailable'])) {
                    throw new Exception('Invalid medication status: ' . $status);
                }
                
                $updateStmt->bind_param("sii", $status, $medicationId, $prescriptionId);
                $updateStmt->execute();
            }
        }
        
        // Check if all medications are now dispensed
        $checkQuery = "
            SELECT 
                COUNT(*) as total_medications,
                SUM(CASE WHEN status = 'dispensed' THEN 1 ELSE 0 END) as dispensed_count
            FROM prescribed_medications 
            WHERE prescription_id = ?";
        
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $prescriptionId);
        $checkStmt->execute();
        $result = $checkStmt->get_result()->fetch_assoc();
        
        $prescriptionStatusUpdated = false;
        $newPrescriptionStatus = null;
        
        // Update prescription status if all medications are dispensed
        if ($result['total_medications'] > 0 && $result['dispensed_count'] == $result['total_medications']) {
            $updatePrescriptionQuery = "UPDATE prescriptions SET status = 'dispensed', updated_at = NOW() WHERE prescription_id = ?";
            $updatePrescriptionStmt = $conn->prepare($updatePrescriptionQuery);
            $updatePrescriptionStmt->bind_param("i", $prescriptionId);
            $updatePrescriptionStmt->execute();
            
            $prescriptionStatusUpdated = true;
            $newPrescriptionStatus = 'dispensed';
        } else {
            // If not all medications are dispensed, set prescription status to 'in_progress'
            $updatePrescriptionQuery = "UPDATE prescriptions SET status = 'in_progress', updated_at = NOW() WHERE prescription_id = ?";
            $updatePrescriptionStmt = $conn->prepare($updatePrescriptionQuery);
            $updatePrescriptionStmt->bind_param("i", $prescriptionId);
            $updatePrescriptionStmt->execute();
            
            $newPrescriptionStatus = 'in_progress';
        }
        
        // Log the action
        $logQuery = "INSERT INTO prescription_logs (prescription_id, employee_id, action, details, created_at) VALUES (?, ?, ?, ?, NOW())";
        $logStmt = $conn->prepare($logQuery);
        $action = 'medication_status_update';
        $details = json_encode([
            'updated_by' => $_SESSION['employee_id'],
            'role_id' => $_SESSION['role_id'],
            'medication_statuses' => $medicationStatuses,
            'prescription_status' => $newPrescriptionStatus
        ]);
        $logStmt->bind_param("iiss", $prescriptionId, $_SESSION['employee_id'], $action, $details);
        $logStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Medication statuses updated successfully',
            'prescription_status_updated' => $prescriptionStatusUpdated,
            'new_status' => $newPrescriptionStatus
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Prescription medication update error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating medication statuses: ' . $e->getMessage()
    ]);
}
?>