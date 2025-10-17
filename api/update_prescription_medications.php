<?php
// Include employee session configuration
$root_path = dirname(__DIR__);
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Set JSON content type and error handling
header('Content-Type: application/json');

// Global error handler to ensure JSON responses
function handleError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

try {

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
        if (!$resetStmt) {
            throw new Exception('Failed to prepare reset query: ' . $conn->error);
        }
        $resetStmt->bind_param("i", $prescriptionId);
        $resetStmt->execute();
        
        // Update each medication status
        if (!empty($medicationStatuses)) {
            $updateQuery = "UPDATE prescribed_medications SET status = ?, updated_at = NOW() WHERE prescribed_medication_id = ? AND prescription_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            if (!$updateStmt) {
                throw new Exception('Failed to prepare update query: ' . $conn->error);
            }
            
            foreach ($medicationStatuses as $medStatus) {
                $prescribedMedicationId = intval($medStatus['prescribed_medication_id']);
                $status = $medStatus['status']; // 'dispensed' or 'unavailable'
                
                // Validate status
                if (!in_array($status, ['dispensed', 'unavailable'])) {
                    throw new Exception('Invalid medication status: ' . $status);
                }
                
                // Check if the medication exists for this prescription before updating
                $checkMedQuery = "SELECT prescribed_medication_id, medication_name FROM prescribed_medications WHERE prescribed_medication_id = ? AND prescription_id = ?";
                $checkMedStmt = $conn->prepare($checkMedQuery);
                if ($checkMedStmt) {
                    $checkMedStmt->bind_param("ii", $prescribedMedicationId, $prescriptionId);
                    $checkMedStmt->execute();
                    $medExists = $checkMedStmt->get_result();
                    
                    if ($medExists->num_rows === 0) {
                        error_log("ERROR: Medication ID $prescribedMedicationId does not exist for prescription $prescriptionId");
                        throw new Exception("Medication ID $prescribedMedicationId not found for this prescription");
                    } else {
                        $medData = $medExists->fetch_assoc();
                        error_log("Found medication: ID $prescribedMedicationId - {$medData['medication_name']}");
                    }
                }
                
                // Debug: Log each medication update
                error_log("Updating medication ID $prescribedMedicationId to status '$status' for prescription $prescriptionId");
                
                $updateStmt->bind_param("sii", $status, $prescribedMedicationId, $prescriptionId);
                $result = $updateStmt->execute();
                
                if (!$result) {
                    throw new Exception("Failed to update medication ID $prescribedMedicationId: " . $updateStmt->error);
                }
                
                $affectedRows = $updateStmt->affected_rows;
                error_log("Medication update result: affected_rows = $affectedRows");
                
                if ($affectedRows === 0) {
                    error_log("Warning: No rows affected for medication ID $prescribedMedicationId - may not exist");
                }
            }
        }
        
        // Check if all medications are now processed (dispensed OR unavailable)
        $checkQuery = "
            SELECT 
                COUNT(*) as total_medications,
                SUM(CASE WHEN status = 'dispensed' THEN 1 ELSE 0 END) as dispensed_count,
                SUM(CASE WHEN status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_count,
                SUM(CASE WHEN status IN ('dispensed', 'unavailable') THEN 1 ELSE 0 END) as completed_count
            FROM prescribed_medications 
            WHERE prescription_id = ?";
        
        $checkStmt = $conn->prepare($checkQuery);
        if (!$checkStmt) {
            throw new Exception('Failed to prepare check query: ' . $conn->error);
        }
        $checkStmt->bind_param("i", $prescriptionId);
        $checkStmt->execute();
        $result = $checkStmt->get_result()->fetch_assoc();
        
        // Debug: Log the medication counts
        error_log("Medication counts for prescription $prescriptionId:");
        error_log("  Total: " . $result['total_medications']);
        error_log("  Dispensed: " . $result['dispensed_count']);
        error_log("  Unavailable: " . $result['unavailable_count']);
        error_log("  Completed: " . $result['completed_count']);
        
        // Debug: Also query the actual medication statuses
        $debugQuery = "SELECT prescribed_medication_id, status FROM prescribed_medications WHERE prescription_id = ?";
        $debugStmt = $conn->prepare($debugQuery);
        if ($debugStmt) {
            $debugStmt->bind_param("i", $prescriptionId);
            $debugStmt->execute();
            $debugResult = $debugStmt->get_result();
            error_log("Individual medication statuses:");
            while ($row = $debugResult->fetch_assoc()) {
                error_log("  Medication " . $row['prescribed_medication_id'] . ": " . $row['status']);
            }
        }
        
        $prescriptionStatusUpdated = false;
        $newPrescriptionStatus = null;
        
        // Update prescription status based on new logic:
        // - 'issued' when ALL medications are processed (dispensed OR unavailable) - ready for printing
        // - No 'pending' status, only 'not yet dispensed' at medication level
        if ($result['total_medications'] > 0 && $result['completed_count'] == $result['total_medications']) {
            // All medications processed (some dispensed, some unavailable) = prescription is 'issued' and ready for printing
            $updatePrescriptionQuery = "UPDATE prescriptions SET status = 'issued', updated_at = NOW() WHERE prescription_id = ?";
            $updatePrescriptionStmt = $conn->prepare($updatePrescriptionQuery);
            if (!$updatePrescriptionStmt) {
                throw new Exception('Failed to prepare prescription update query: ' . $conn->error);
            }
            $updatePrescriptionStmt->bind_param("i", $prescriptionId);
            $updatePrescriptionStmt->execute();
            
            $prescriptionStatusUpdated = true;
            $newPrescriptionStatus = 'issued';
        } else {
            // If not all medications are processed, keep prescription status as 'active' (some medications still 'not yet dispensed')
            $updatePrescriptionQuery = "UPDATE prescriptions SET status = 'active', updated_at = NOW() WHERE prescription_id = ?";
            $updatePrescriptionStmt = $conn->prepare($updatePrescriptionQuery);
            $updatePrescriptionStmt->bind_param("i", $prescriptionId);
            $updatePrescriptionStmt->execute();
            
            $newPrescriptionStatus = 'active';
        }
        
        // Log the action (optional - skip if table doesn't exist)
        try {
            // Check if prescription_logs table exists first
            $checkTableStmt = $conn->prepare("SHOW TABLES LIKE 'prescription_logs'");
            if ($checkTableStmt) {
                $checkTableStmt->execute();
                $tableExists = $checkTableStmt->get_result()->num_rows > 0;
                
                if ($tableExists) {
                    $logQuery = "INSERT INTO prescription_logs (prescription_id, prescribed_medication_id, action_type, field_changed, old_value, new_value, changed_by_employee_id, change_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $logStmt = $conn->prepare($logQuery);
                    if ($logStmt) {
                        $actionType = 'medication_updated';
                        $fieldChanged = 'medication_statuses';
                        $oldValue = 'pending';
                        $newValue = json_encode($medicationStatuses);
                        $reason = 'Medication status updated via prescription management';
                        $nullValue = null; // Store null in a variable for bind_param
                        $employeeId = $_SESSION['employee_id']; // Store session value in variable
                        
                        $logStmt->bind_param("iissssis", 
                            $prescriptionId, 
                            $nullValue, // prescribed_medication_id - null for prescription-level changes
                            $actionType, 
                            $fieldChanged, 
                            $oldValue, 
                            $newValue, 
                            $employeeId, 
                            $reason
                        );
                        $logStmt->execute();
                        error_log("Prescription medication update logged successfully");
                    }
                } else {
                    error_log("Prescription logs table does not exist - skipping logging");
                }
            }
        } catch (Exception $logError) {
            // Log table might not exist or other issues - continue without logging
            error_log("Prescription log failed: " . $logError->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        // Create informative success message
        $successMessage = 'Medication statuses updated successfully';
        if ($prescriptionStatusUpdated && $newPrescriptionStatus === 'dispensed') {
            $successMessage .= '. Prescription completed - all medications have been processed (dispensed or marked unavailable).';
        } else if ($newPrescriptionStatus === 'in_progress') {
            $successMessage .= '. Prescription is in progress - some medications still pending.';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $successMessage,
            'prescription_status_updated' => $prescriptionStatusUpdated,
            'new_status' => $newPrescriptionStatus,
            'details' => [
                'total_medications' => $result['total_medications'],
                'dispensed_count' => $result['dispensed_count'], 
                'unavailable_count' => $result['unavailable_count'],
                'completed_count' => $result['completed_count']
            ]
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

} catch (Exception $e) {
    handleError('System error: ' . $e->getMessage(), 500);
}
?>