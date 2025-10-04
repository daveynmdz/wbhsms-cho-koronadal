<?php
/**
 * Queue Management Service
 * 
 * This class handles all queue operations for the appointment system,
 * integrating appointments with queue_entries and queue_logs tables.
 */

class QueueManagementService {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Create a queue entry when an appointment is booked
     * 
     * @param int $appointment_id
     * @param int $patient_id
     * @param int $service_id
     * @param string $queue_type (triage, consultation, lab, prescription, billing, document)
     * @param string $priority_level (normal, priority, emergency)
     * @param int|null $performed_by Employee ID who created the entry (null for patient-initiated)
     * @return array Result with success status and queue details
     */
    public function createQueueEntry($appointment_id, $patient_id, $service_id, $queue_type = 'consultation', $priority_level = 'normal', $performed_by = null) {
        try {
            // Start transaction
            $this->conn->begin_transaction();
            
            // Get appointment details for visit creation and queue numbering
            $stmt = $this->conn->prepare("
                SELECT a.facility_id, a.scheduled_date, a.scheduled_time 
                FROM appointments a 
                WHERE a.appointment_id = ?
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $stmt->close();
            
            if (!$appointment) {
                throw new Exception("Appointment not found: $appointment_id");
            }
            
            // Create visit record first
            $stmt = $this->conn->prepare("
                INSERT INTO visits (
                    patient_id, facility_id, appointment_id, visit_date, 
                    visit_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'ongoing', NOW(), NOW())
            ");
            $stmt->bind_param("iiis", 
                $patient_id, $appointment['facility_id'], $appointment_id, $appointment['scheduled_date']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create visit record: " . $stmt->error);
            }
            
            $visit_id = $this->conn->insert_id;
            $stmt->close();
            
            // Generate time slot-based queue number
            $queue_number = $this->generateQueueNumber($appointment_id);
            
            // Insert queue entry
            $stmt = $this->conn->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, 
                    queue_type, queue_number, priority_level, status, 
                    time_in, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting', NOW(), NOW(), NOW())
            ");
            
            $stmt->bind_param("iiiisis", 
                $visit_id, $appointment_id, $patient_id, $service_id,
                $queue_type, $queue_number, $priority_level
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create queue entry: " . $stmt->error);
            }
            
            $queue_entry_id = $this->conn->insert_id;
            $stmt->close();
            
            // Log the queue creation
            $this->logQueueAction($queue_entry_id, 'created', null, 'waiting', 'Queue entry created for appointment', $performed_by);
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'queue_entry_id' => $queue_entry_id,
                'visit_id' => $visit_id,
                'queue_number' => $queue_number,
                'queue_type' => $queue_type,
                'priority_level' => $priority_level,
                'status' => 'waiting',
                'message' => 'Queue entry created successfully with visit record'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update queue entry status
     * 
     * @param int $queue_entry_id
     * @param string $new_status
     * @param string $old_status
     * @param int $performed_by Employee ID
     * @param string|null $remarks Optional remarks
     * @return array Result with success status
     */
    public function updateQueueStatus($queue_entry_id, $new_status, $old_status, $performed_by, $remarks = null) {
        try {
            $this->conn->begin_transaction();
            
            // Update queue entry
            $update_fields = ['status = ?', 'updated_at = NOW()'];
            $params = [$new_status];
            $types = 's';
            
            // Set timestamps based on status
            if ($new_status === 'in_progress') {
                $update_fields[] = 'time_started = NOW()';
                // Calculate waiting time
                $update_fields[] = 'waiting_time = TIMESTAMPDIFF(MINUTE, time_in, NOW())';
            } elseif (in_array($new_status, ['done', 'cancelled', 'no_show'])) {
                $update_fields[] = 'time_completed = NOW()';
                // Calculate turnaround time
                $update_fields[] = 'turnaround_time = TIMESTAMPDIFF(MINUTE, time_in, NOW())';
            }
            
            if ($remarks) {
                $update_fields[] = 'remarks = ?';
                $params[] = $remarks;
                $types .= 's';
            }
            
            $params[] = $queue_entry_id;
            $types .= 'i';
            
            $sql = "UPDATE queue_entries SET " . implode(', ', $update_fields) . " WHERE queue_entry_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update queue entry: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows === 0) {
                throw new Exception("Queue entry not found or no changes made");
            }
            
            // Log the status change
            $this->logQueueAction($queue_entry_id, 'status_changed', $old_status, $new_status, $remarks, $performed_by);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Queue status updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel queue entry when appointment is cancelled
     * 
     * @param int $appointment_id
     * @param string $cancellation_reason
     * @param int|null $performed_by Employee ID (null if patient-initiated)
     * @return array Result with success status
     */
    public function cancelQueueEntry($appointment_id, $cancellation_reason, $performed_by = null) {
        try {
            $this->conn->begin_transaction();
            
            // Find the queue entry for this appointment
            $stmt = $this->conn->prepare("
                SELECT queue_entry_id, status 
                FROM queue_entries 
                WHERE appointment_id = ? AND status NOT IN ('done', 'cancelled')
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue_entry = $result->fetch_assoc();
            $stmt->close();
            
            if (!$queue_entry) {
                throw new Exception("No active queue entry found for this appointment");
            }
            
            $old_status = $queue_entry['status'];
            $queue_entry_id = $queue_entry['queue_entry_id'];
            
            // Update queue entry to cancelled
            $stmt = $this->conn->prepare("
                UPDATE queue_entries 
                SET status = 'cancelled', 
                    time_completed = NOW(),
                    turnaround_time = TIMESTAMPDIFF(MINUTE, time_in, NOW()),
                    remarks = ?,
                    updated_at = NOW()
                WHERE queue_entry_id = ?
            ");
            $stmt->bind_param("si", $cancellation_reason, $queue_entry_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to cancel queue entry: " . $stmt->error);
            }
            $stmt->close();
            
            // Log the cancellation
            $this->logQueueAction($queue_entry_id, 'cancelled', $old_status, 'cancelled', $cancellation_reason, $performed_by);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Queue entry cancelled successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check in a queue entry (mark patient as arrived)
     * 
     * @param int $queue_entry_id
     * @param int $employee_id Employee ID performing the check-in
     * @param string|null $remarks Optional remarks
     * @return array Result with success status and queue details
     */
    public function checkInQueueEntry($queue_entry_id, $employee_id, $remarks = null) {
        try {
            $this->conn->begin_transaction();
            
            // Validate queue entry exists and status is 'waiting'
            $stmt = $this->conn->prepare("
                SELECT qe.status, qe.visit_id, qe.queue_number, qe.queue_type, 
                       qe.priority_level, p.first_name, p.last_name
                FROM queue_entries qe
                JOIN patients p ON qe.patient_id = p.patient_id
                WHERE qe.queue_entry_id = ?
            ");
            $stmt->bind_param("i", $queue_entry_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue_entry = $result->fetch_assoc();
            $stmt->close();
            
            if (!$queue_entry) {
                throw new Exception("Queue entry not found");
            }
            
            if ($queue_entry['status'] !== 'waiting') {
                throw new Exception("Queue entry status must be 'waiting' to check in. Current status: " . $queue_entry['status']);
            }
            
            $visit_id = $queue_entry['visit_id'];
            $old_status = $queue_entry['status'];
            
            // Update visits.time_in to NOW() for the related visit_id
            $stmt = $this->conn->prepare("
                UPDATE visits 
                SET time_in = NOW(), updated_at = NOW()
                WHERE visit_id = ?
            ");
            $stmt->bind_param("i", $visit_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update visit time_in: " . $stmt->error);
            }
            $stmt->close();
            
            // Update queue_entries status to 'arrived'
            $update_fields = ['status = ?', 'updated_at = NOW()'];
            $params = ['arrived'];
            $types = 's';
            
            if ($remarks) {
                $update_fields[] = 'remarks = ?';
                $params[] = $remarks;
                $types .= 's';
            }
            
            $params[] = $queue_entry_id;
            $types .= 'i';
            
            $sql = "UPDATE queue_entries SET " . implode(', ', $update_fields) . " WHERE queue_entry_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update queue entry status: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows === 0) {
                throw new Exception("Queue entry not found or no changes made");
            }
            
            // Log the status change in queue_logs
            $log_remarks = $remarks ? "Check-in completed. Remarks: $remarks" : "Check-in completed";
            $this->logQueueAction($queue_entry_id, 'status_changed', $old_status, 'arrived', $log_remarks, $employee_id);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Patient checked in successfully',
                'queue_details' => [
                    'queue_entry_id' => $queue_entry_id,
                    'visit_id' => $visit_id,
                    'queue_number' => $queue_entry['queue_number'],
                    'queue_type' => $queue_entry['queue_type'],
                    'priority_level' => $queue_entry['priority_level'],
                    'patient_name' => $queue_entry['first_name'] . ' ' . $queue_entry['last_name'],
                    'old_status' => $old_status,
                    'new_status' => 'arrived'
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reinstate a patient who was marked as no-show
     * 
     * @param int $queue_entry_id
     * @param int $performed_by Employee ID
     * @param string|null $remarks
     * @return array Result with success status
     */
    public function reinstateQueueEntry($queue_entry_id, $performed_by, $remarks = null) {
        try {
            $this->conn->begin_transaction();
            
            // Get current status
            $stmt = $this->conn->prepare("SELECT status FROM queue_entries WHERE queue_entry_id = ?");
            $stmt->bind_param("i", $queue_entry_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue_entry = $result->fetch_assoc();
            $stmt->close();
            
            if (!$queue_entry) {
                throw new Exception("Queue entry not found");
            }
            
            $old_status = $queue_entry['status'];
            
            if ($old_status !== 'no_show') {
                throw new Exception("Can only reinstate no-show patients");
            }
            
            // Update status back to waiting
            $stmt = $this->conn->prepare("
                UPDATE queue_entries 
                SET status = 'waiting',
                    time_completed = NULL,
                    turnaround_time = NULL,
                    remarks = ?,
                    updated_at = NOW()
                WHERE queue_entry_id = ?
            ");
            $stmt->bind_param("si", $remarks, $queue_entry_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to reinstate queue entry: " . $stmt->error);
            }
            $stmt->close();
            
            // Log the reinstatement
            $this->logQueueAction($queue_entry_id, 'reinstated', $old_status, 'waiting', $remarks, $performed_by);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Patient reinstated successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get queue information for an appointment
     * 
     * @param int $appointment_id
     * @return array Queue information
     */
    public function getQueueInfoByAppointment($appointment_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT qe.*, p.first_name, p.last_name, s.name as service_name,
                       (SELECT COUNT(*) FROM queue_entries qe2 
                        WHERE qe2.queue_type = qe.queue_type 
                        AND DATE(qe2.created_at) = DATE(qe.created_at)
                        AND qe2.queue_number < qe.queue_number 
                        AND qe2.status = 'waiting') as position_in_queue
                FROM queue_entries qe
                JOIN patients p ON qe.patient_id = p.patient_id
                JOIN services s ON qe.service_id = s.service_id
                WHERE qe.appointment_id = ?
            ");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue_info = $result->fetch_assoc();
            $stmt->close();
            
            return [
                'success' => true,
                'queue_info' => $queue_info
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get current queue status for a specific queue type and date
     * 
     * @param string $queue_type
     * @param string $date
     * @return array Queue list
     */
    public function getQueueByTypeAndDate($queue_type, $date = null) {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }
            
            $stmt = $this->conn->prepare("
                SELECT qe.*, p.first_name, p.last_name, s.name as service_name,
                       a.scheduled_time, f.name as facility_name
                FROM queue_entries qe
                JOIN patients p ON qe.patient_id = p.patient_id
                JOIN services s ON qe.service_id = s.service_id
                JOIN appointments a ON qe.appointment_id = a.appointment_id
                JOIN facilities f ON a.facility_id = f.facility_id
                WHERE qe.queue_type = ? 
                AND DATE(qe.created_at) = ?
                ORDER BY qe.priority_level DESC, qe.queue_number ASC
            ");
            $stmt->bind_param("ss", $queue_type, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $queue_list = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return [
                'success' => true,
                'queue_list' => $queue_list,
                'date' => $date,
                'queue_type' => $queue_type
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate time slot-based queue number with 20 patient limit per slot
     * 
     * @param int $appointment_id Appointment ID to get date and time
     * @return int Queue number
     * @throws Exception If time slot is full (20 patients)
     */
    private function generateQueueNumber($appointment_id) {
        // Get appointment date and time
        $stmt = $this->conn->prepare("
            SELECT scheduled_date, scheduled_time, facility_id
            FROM appointments 
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();
        
        if (!$appointment) {
            throw new Exception("Appointment not found for queue number generation");
        }
        
        $scheduled_date = $appointment['scheduled_date'];
        $scheduled_time = $appointment['scheduled_time'];
        $facility_id = $appointment['facility_id'];
        
        // Count existing queue entries for this date, time slot, and facility
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as patient_count, COALESCE(MAX(qe.queue_number), 0) as max_number
            FROM queue_entries qe
            INNER JOIN appointments a ON qe.appointment_id = a.appointment_id
            WHERE a.scheduled_date = ? 
              AND a.scheduled_time = ?
              AND a.facility_id = ?
              AND qe.status NOT IN ('cancelled')
        ");
        $stmt->bind_param("ssi", $scheduled_date, $scheduled_time, $facility_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $slot_info = $result->fetch_assoc();
        $stmt->close();
        
        $current_count = (int)$slot_info['patient_count'];
        $max_number = (int)$slot_info['max_number'];
        
        // Check if time slot is full (20 patient limit)
        if ($current_count >= 20) {
            throw new Exception("Time slot is full. Maximum 20 patients allowed per time slot.");
        }
        
        return $max_number + 1;
    }
    
    /**
     * Log queue actions for audit trail
     * 
     * @param int $queue_entry_id
     * @param string $action
     * @param string|null $old_status
     * @param string $new_status
     * @param string|null $remarks
     * @param int|null $performed_by
     */
    private function logQueueAction($queue_entry_id, $action, $old_status, $new_status, $remarks = null, $performed_by = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO queue_logs (
                    queue_entry_id, action, old_status, new_status, 
                    remarks, performed_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssi", 
                $queue_entry_id, $action, $old_status, $new_status, 
                $remarks, $performed_by
            );
            $stmt->execute();
            $stmt->close();
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Failed to log queue action: " . $e->getMessage());
        }
    }
    
    /**
     * Get queue statistics for dashboard
     * 
     * @param string $date
     * @return array Statistics
     */
    public function getQueueStatistics($date = null) {
        try {
            if (!$date) {
                $date = date('Y-m-d');
            }
            
            $stmt = $this->conn->prepare("
                SELECT 
                    queue_type,
                    COUNT(*) as total_entries,
                    SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
                    AVG(waiting_time) as avg_waiting_time,
                    AVG(turnaround_time) as avg_turnaround_time
                FROM queue_entries 
                WHERE DATE(created_at) = ?
                GROUP BY queue_type
            ");
            $stmt->bind_param("s", $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $statistics = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            return [
                'success' => true,
                'statistics' => $statistics,
                'date' => $date
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>