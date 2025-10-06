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
            
            // Get facility_id using helper method
            $facility_id = $this->getFacilityIdFromAppointment($appointment_id);
            if (!$facility_id) {
                throw new Exception("Could not retrieve facility_id for appointment");
            }
            
            // Create visit record first
            $stmt = $this->conn->prepare("
                INSERT INTO visits (
                    patient_id, facility_id, appointment_id, visit_date, 
                    visit_status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, 'ongoing', NOW(), NOW())
            ");
            $stmt->bind_param("iiis", 
                $patient_id, $facility_id, $appointment_id, $appointment['scheduled_date']
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create visit record: " . $stmt->error);
            }
            
            $visit_id = $this->conn->insert_id;
            $stmt->close();
            
            // Generate structured queue code (CHO appointments only)
            $queue_data = $this->generateQueueCode($appointment_id);
            if (!$queue_data) {
                // Not a CHO appointment - still create entry but without queue code
                $queue_number = null;
                $queue_code = null;
            } else {
                list($queue_code, $queue_number) = $queue_data;
            }
            
            // Insert queue entry with queue code support
            // Get station_id for this service at the facility (only OPEN stations)
            $station_id = $this->getDefaultStationForService($service_id, $facility_id);
            
            // Check if we found an open station
            if (!$station_id) {
                throw new Exception("No open stations available for this service. Please try again later or contact staff.");
            }
            
            $stmt = $this->conn->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, station_id,
                    queue_type, queue_number, queue_code, priority_level, status, 
                    time_in, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting', NOW(), NOW(), NOW())
            ");
            
            $stmt->bind_param("iiiiisiss", 
                $visit_id, $appointment_id, $patient_id, $service_id, $station_id,
                $queue_type, $queue_number, $queue_code, $priority_level
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create queue entry: " . $stmt->error);
            }
            
            $queue_entry_id = $this->conn->insert_id;
            $stmt->close();
            
            // Log the queue creation with queue code in remarks
            $remarks = $queue_code ? "Queue created with code: {$queue_code}" : 'Queue entry created for non-CHO appointment';
            $this->logQueueAction($queue_entry_id, 'created', null, 'waiting', $remarks, $performed_by);
            
            // Commit transaction
            $this->conn->commit();
            
            $message = $queue_code ? 
                "Queue entry created successfully with code: {$queue_code}" : 
                "Queue entry created successfully (CHO appointments only get queue codes)";
            
            return [
                'success' => true,
                'queue_entry_id' => $queue_entry_id,
                'visit_id' => $visit_id,
                'queue_number' => $queue_number,
                'queue_code' => $queue_code,
                'queue_type' => $queue_type,
                'priority_level' => $priority_level,
                'status' => 'waiting',
                'message' => $message
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
     * Generate structured queue code for CHO appointments only
     * Format: DDMMYY-SLOT-###
     * Example: 100725-08A-001
     * 
     * @param int $appointment_id Appointment ID to get date and time
     * @return array|null [queue_code, queue_number] or null if not CHO
     * @throws Exception If time slot is full (20 patients)
     */
    private function generateQueueCode($appointment_id) {
        // Get appointment details
        $stmt = $this->conn->prepare("
            SELECT scheduled_date, scheduled_time, facility_id, service_id
            FROM appointments
            WHERE appointment_id = ?
        ");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();
        $stmt->close();
        
        if (!$appointment) {
            throw new Exception("Appointment not found");
        }
        
        // Only generate queue codes for City Health Office (facility_id = 1)
        if ($appointment['facility_id'] != 1) {
            return null;
        }
        
        $scheduled_date = $appointment['scheduled_date'];
        $scheduled_time = $appointment['scheduled_time'];
        
        // Build queue code components
        $date_part = date('dmy', strtotime($scheduled_date));
        $slot_code = $this->getTimeSlotCode($scheduled_time);
        
        // Count existing queue entries for this date and slot
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) + 1 as seq_num
            FROM queue_entries qe
            INNER JOIN appointments a ON qe.appointment_id = a.appointment_id
            WHERE DATE(a.scheduled_date) = ? 
            AND qe.queue_type = 'consultation' 
            AND qe.queue_code LIKE ?
            AND qe.status NOT IN ('cancelled')
        ");
        $slot_pattern = "%-{$slot_code}-%";
        $stmt->bind_param("ss", $scheduled_date, $slot_pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        $seq_data = $result->fetch_assoc();
        $stmt->close();
        
        $seq_num = (int)$seq_data['seq_num'];
        
        // Enforce 20-patient limit per time slot
        if ($seq_num > 20) {
            throw new Exception("Time slot is full. Please select another slot.");
        }
        
        // Build final queue code
        $queue_code = "{$date_part}-{$slot_code}-" . str_pad($seq_num, 3, '0', STR_PAD_LEFT);
        
        return [$queue_code, $seq_num];
    }
    
    /**
     * Convert appointment time to slot code
     * 
     * @param string $time Time in HH:MM format
     * @return string Slot code (08A, 09A, etc.)
     */
    private function getTimeSlotCode($time) {
        $hour = (int)date('H', strtotime($time));
        switch (true) {
            case ($hour >= 8 && $hour < 9): return '08A';
            case ($hour >= 9 && $hour < 10): return '09A';
            case ($hour >= 10 && $hour < 11): return '10A';
            case ($hour >= 11 && $hour < 12): return '11A';
            case ($hour >= 12 && $hour < 13): return '12N';
            case ($hour >= 13 && $hour < 14): return '01P';
            case ($hour >= 14 && $hour < 15): return '02P';
            case ($hour >= 15 && $hour < 16): return '03P';
            case ($hour >= 16 && $hour < 17): return '04P';
            default: return 'XX';
        }
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
    /**
     * Enhanced queue action logging with comprehensive audit trail
     * Maps all possible queue operations to proper action types
     */
    private function logQueueAction($queue_entry_id, $action, $old_status, $new_status, $remarks = null, $performed_by = null) {
        try {
            // Map action types to standardized values for better audit trail
            $action_map = [
                'created' => 'created',
                'status_changed' => 'status_changed', 
                'called' => 'status_changed',
                'in_progress' => 'status_changed',
                'done' => 'status_changed',
                'completed' => 'status_changed',
                'skipped' => 'skipped',
                'no_show' => 'status_changed',
                'cancelled' => 'cancelled',
                'reinstated' => 'reinstated',
                'moved' => 'moved'
            ];
            
            $final_action = $action_map[$action] ?? 'status_changed';
            
            $stmt = $this->conn->prepare("
                INSERT INTO queue_logs (
                    queue_entry_id, action, old_status, new_status, 
                    remarks, performed_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssi", 
                $queue_entry_id, $final_action, $old_status, $new_status, 
                $remarks, $performed_by
            );
            $stmt->execute();
            $stmt->close();
            
            // Enhanced logging for debugging
            error_log("Queue action logged: entry_id=$queue_entry_id, action=$final_action, $old_status->$new_status, by=$performed_by");
            
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
    
    // ============================================
    // STATION MANAGEMENT METHODS
    // ============================================
    
    /**
     * Get active station assignment for an employee on a specific date
     * Uses new assignment_schedules table with date ranges
     */
    public function getActiveStationByEmployee($employee_id, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                asch.schedule_id,
                asch.employee_id,
                asch.station_id,
                asch.start_date,
                asch.end_date,
                asch.shift_start_time,
                asch.shift_end_time,
                asch.assignment_type,
                s.station_name, 
                s.station_type, 
                s.station_number, 
                sv.name as service_name
            FROM assignment_schedules asch
            JOIN stations s ON asch.station_id = s.station_id
            JOIN services sv ON s.service_id = sv.service_id
            WHERE asch.employee_id = ? 
            AND asch.is_active = 1
            AND asch.start_date <= ?
            AND (asch.end_date IS NULL OR asch.end_date >= ?)
            AND s.is_active = 1
            ORDER BY asch.start_date DESC
            LIMIT 1
        ");
        
        $stmt->bind_param("iss", $employee_id, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }

    /**
     * Get all stations with their current assignments for a specific date
     * Uses new assignment_schedules table with date ranges
     */
    public function getAllStationsWithAssignments($date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                s.station_id,
                s.station_name,
                s.station_type,
                s.station_number,
                s.is_active,
                s.is_open,
                sv.name as service_name,
                asch.schedule_id,
                asch.employee_id,
                asch.start_date,
                asch.end_date,
                asch.shift_start_time,
                asch.shift_end_time,
                asch.assignment_type,
                asch.is_active as assignment_status,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                r.role_name as employee_role
            FROM stations s
            JOIN services sv ON s.service_id = sv.service_id
            LEFT JOIN assignment_schedules asch ON s.station_id = asch.station_id 
                AND asch.is_active = 1
                AND asch.start_date <= ?
                AND (asch.end_date IS NULL OR asch.end_date >= ?)
            LEFT JOIN employees e ON asch.employee_id = e.employee_id AND e.status = 'active'
            LEFT JOIN roles r ON e.role_id = r.role_id
            ORDER BY 
                FIELD(s.station_type, 'checkin', 'triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document'),
                s.station_number
        ");
        
        $stmt->bind_param("ss", $date, $date);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Assign employee to station using date ranges (much more efficient!)
     */
    public function assignEmployeeToStation($employee_id, $station_id, $start_date, $assignment_type = 'permanent', $shift_start = '08:00:00', $shift_end = '17:00:00', $assigned_by = null, $end_date = null) {
        try {
            // Debug logging
            error_log("assignEmployeeToStation called with: employee_id=$employee_id, station_id=$station_id, start_date=$start_date, assignment_type=$assignment_type");
            
            $this->conn->begin_transaction();
            
            // Set end_date based on assignment type first
            if ($assignment_type === 'permanent') {
                $final_end_date = null; // NULL means permanent
            } elseif ($assignment_type === 'temporary' && !$end_date) {
                // Default temporary assignment is 30 days
                $final_end_date = date('Y-m-d', strtotime($start_date . ' + 30 days'));
            } else {
                $final_end_date = $end_date;
            }
            
            // Check if there's already an existing assignment for this exact combination
            $existing_check = "
                SELECT schedule_id, is_active, end_date 
                FROM assignment_schedules 
                WHERE employee_id = ? AND station_id = ? AND start_date = ?
            ";
            $existing_stmt = $this->conn->prepare($existing_check);
            $existing_stmt->bind_param("iis", $employee_id, $station_id, $start_date);
            $existing_stmt->execute();
            $existing_result = $existing_stmt->get_result();
            
            if ($existing_result->num_rows > 0) {
                $existing = $existing_result->fetch_assoc();
                
                // If there's an inactive assignment, reactivate it instead of creating new
                if ($existing['is_active'] == 0) {
                    $reactivate_stmt = $this->conn->prepare("
                        UPDATE assignment_schedules 
                        SET is_active = 1, end_date = ?, assignment_type = ?, 
                            shift_start_time = ?, shift_end_time = ?, assigned_by = ?, assigned_at = NOW()
                        WHERE schedule_id = ?
                    ");
                    $reactivate_stmt->bind_param("ssssii", 
                        $final_end_date, $assignment_type, $shift_start, $shift_end, $assigned_by, $existing['schedule_id']
                    );
                    
                    if (!$reactivate_stmt->execute()) {
                        throw new Exception("Failed to reactivate assignment: " . $reactivate_stmt->error);
                    }
                    
                    $this->conn->commit();
                    return [
                        'success' => true,
                        'schedule_id' => $existing['schedule_id'],
                        'message' => "Employee assignment reactivated successfully"
                    ];
                } else {
                    $this->conn->rollback();
                    return [
                        'success' => false,
                        'error' => 'Employee is already assigned to this station on this date'
                    ];
                }
            }
            
            // Check if station already has an active assignment that overlaps with this period
            $station_overlap_check = "
                SELECT schedule_id FROM assignment_schedules 
                WHERE station_id = ? 
                AND employee_id != ?
                AND is_active = 1
                AND start_date <= ?
                AND (end_date IS NULL OR end_date >= ?)
            ";
            $check_stmt = $this->conn->prepare($station_overlap_check);
            $check_end_date = $end_date ?: $start_date;
            $check_stmt->bind_param("iiss", $station_id, $employee_id, $check_end_date, $start_date);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'error' => 'Station already has an overlapping assignment for this period'
                ];
            }
            
            // CRITICAL: Check if employee is already assigned to another station during overlapping times
            // Only check for ACTIVE assignments that actually overlap the requested period
            $employee_overlap_check = "
                SELECT 
                    sch.schedule_id,
                    s.station_name,
                    sch.start_date,
                    sch.end_date,
                    sch.shift_start_time,
                    sch.shift_end_time,
                    sch.is_active
                FROM assignment_schedules sch
                JOIN stations s ON sch.station_id = s.station_id
                WHERE sch.employee_id = ? 
                AND sch.station_id != ?
                AND sch.is_active = 1
                AND sch.start_date <= ?
                AND (sch.end_date IS NULL OR sch.end_date >= ?)
                AND (
                    (sch.shift_start_time <= ? AND sch.shift_end_time > ?) OR
                    (sch.shift_start_time < ? AND sch.shift_end_time >= ?) OR
                    (sch.shift_start_time >= ? AND sch.shift_end_time <= ?)
                )
            ";
            $emp_check_stmt = $this->conn->prepare($employee_overlap_check);
            $emp_check_stmt->bind_param("iissssssss", 
                $employee_id, $station_id, $check_end_date, $start_date,
                $shift_start, $shift_start, $shift_end, $shift_end, $shift_start, $shift_end
            );
            $emp_check_stmt->execute();
            $emp_result = $emp_check_stmt->get_result();
            
            if ($emp_result->num_rows > 0) {
                $conflict = $emp_result->fetch_assoc();
                $this->conn->rollback();
                return [
                    'success' => false,
                    'error' => "Employee is already assigned to '{$conflict['station_name']}' during overlapping time period ({$conflict['start_date']} to " . 
                              ($conflict['end_date'] ?: 'ongoing') . ", {$conflict['shift_start_time']} - {$conflict['shift_end_time']}). " .
                              "An employee cannot be assigned to multiple stations at the same time. " .
                              "(Assignment Status: {$conflict['is_active']})"
                ];
            }
            
            // Insert the assignment using date range
            $stmt = $this->conn->prepare("
                INSERT INTO assignment_schedules 
                (employee_id, station_id, start_date, end_date, shift_start_time, shift_end_time, assignment_type, assigned_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("iisssssi", 
                $employee_id, $station_id, $start_date, $final_end_date, 
                $shift_start, $shift_end, $assignment_type, $assigned_by
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert assignment: " . $stmt->error);
            }
            
            $schedule_id = $this->conn->insert_id;
            $affected_rows = $stmt->affected_rows;
            
            if ($affected_rows === 0) {
                throw new Exception("No rows were inserted during assignment creation");
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'schedule_id' => $schedule_id,
                'message' => $assignment_type === 'permanent' 
                    ? "Employee assigned permanently to station (Schedule ID: $schedule_id)" 
                    : "Employee assigned to station from $start_date to $final_end_date (Schedule ID: $schedule_id)"
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Assignment failed with exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => 'Assignment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reassign station to different employee
     */
    public function reassignStation($station_id, $new_employee_id, $reassign_date, $assigned_by = null) {
        try {
            $this->conn->begin_transaction();
            
            // Find current active assignment that covers this date
            $current_stmt = $this->conn->prepare("
                SELECT schedule_id, start_date, end_date, shift_start_time, shift_end_time, assignment_type
                FROM assignment_schedules 
                WHERE station_id = ? 
                AND is_active = 1
                AND start_date <= ?
                AND (end_date IS NULL OR end_date >= ?)
            ");
            $current_stmt->bind_param("iss", $station_id, $reassign_date, $reassign_date);
            $current_stmt->execute();
            $current_assignment = $current_stmt->get_result()->fetch_assoc();
            
            if (!$current_assignment) {
                $this->conn->rollback();
                return ['success' => false, 'error' => 'No active assignment found for this station and date'];
            }
            
            // End the current assignment on the day before reassignment
            $end_current_date = date('Y-m-d', strtotime($reassign_date . ' - 1 day'));
            $update_stmt = $this->conn->prepare("
                UPDATE assignment_schedules 
                SET end_date = ? 
                WHERE schedule_id = ?
            ");
            $update_stmt->bind_param("si", $end_current_date, $current_assignment['schedule_id']);
            $update_stmt->execute();
            
            // Create new assignment starting from reassign_date
            $insert_stmt = $this->conn->prepare("
                INSERT INTO assignment_schedules 
                (employee_id, station_id, start_date, end_date, shift_start_time, shift_end_time, assignment_type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Keep the same shift times and assignment type
            $insert_stmt->bind_param("iissssi", 
                $new_employee_id, $station_id, $reassign_date, $current_assignment['end_date'],
                $current_assignment['shift_start_time'], $current_assignment['shift_end_time'], 
                $current_assignment['assignment_type'], $assigned_by
            );
            $insert_stmt->execute();
            
            $this->conn->commit();
            return ['success' => true, 'message' => 'Station reassigned successfully'];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activate/Deactivate station
     */
    public function toggleStationStatus($station_id, $is_active) {
        $stmt = $this->conn->prepare("UPDATE stations SET is_active = ? WHERE station_id = ?");
        $stmt->bind_param("ii", $is_active, $station_id);
        return $stmt->execute();
    }

    /**
     * Get queue entries for a specific station
     */
    public function getStationQueue($station_id, $status_filter = null, $date = null, $limit = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $base_query = "
            SELECT 
                qe.queue_entry_id,
                qe.queue_number,
                qe.priority_level,
                qe.status,
                qe.time_in,
                qe.time_started,
                qe.time_completed,
                qe.waiting_time,
                qe.turnaround_time,
                qe.remarks,
                qe.appointment_id,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                p.patient_id,
                sv.name as service_name,
                s.station_name,
                s.station_type
            FROM queue_entries qe
            JOIN patients p ON qe.patient_id = p.patient_id
            JOIN services sv ON qe.service_id = sv.service_id
            JOIN stations s ON qe.station_id = s.station_id
            WHERE qe.station_id = ? AND DATE(qe.created_at) = ?
        ";
        
        $params = [$station_id, $date];
        $types = "is";
        
        if ($status_filter) {
            $base_query .= " AND qe.status = ?";
            $params[] = $status_filter;
            $types .= "s";
        }
        
        $base_query .= " ORDER BY 
            FIELD(qe.priority_level, 'emergency', 'priority', 'normal'),
            qe.queue_number ASC
        ";
        
        if ($limit !== null) {
            $base_query .= " LIMIT ?";
            $params[] = $limit;
            $types .= "i";
        }
        
        $stmt = $this->conn->prepare($base_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Call next patient in queue for a specific station
     */
    public function callNextPatient($station_type, $station_id, $employee_id) {
        try {
            // Find next waiting patient for this station
            $stmt = $this->conn->prepare("
                SELECT qe.queue_entry_id
                FROM queue_entries qe
                WHERE qe.station_id = ? 
                AND qe.status = 'waiting' 
                AND DATE(qe.created_at) = CURDATE()
                ORDER BY 
                    FIELD(qe.priority_level, 'emergency', 'priority', 'normal'),
                    qe.queue_number ASC
                LIMIT 1
            ");
            
            $stmt->bind_param("i", $station_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($next_patient = $result->fetch_assoc()) {
                // Update the next patient to in_progress
                $update_result = $this->updateQueueStatus(
                    $next_patient['queue_entry_id'], 
                    'in_progress', 
                    'waiting', 
                    $employee_id, 
                    'Called to station'
                );
                
                return $update_result;
            } else {
                return [
                    'success' => false,
                    'error' => 'No patients waiting in queue'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error calling next patient: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get queue statistics for station
     */
    public function getStationQueueStats($station_id, $date = null) {
        if ($date === null) {
            $date = date('Y-m-d');
        }
        
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(CASE WHEN qe.status = 'waiting' THEN 1 END) as waiting_count,
                COUNT(CASE WHEN qe.status = 'in_progress' THEN 1 END) as in_progress_count,
                COUNT(CASE WHEN qe.status = 'done' THEN 1 END) as completed_count,
                COUNT(CASE WHEN qe.status = 'skipped' THEN 1 END) as skipped_count,
                COUNT(CASE WHEN qe.status = 'no_show' THEN 1 END) as no_show_count,
                AVG(qe.turnaround_time) as avg_turnaround_time,
                MIN(CASE WHEN qe.status = 'waiting' THEN qe.queue_number END) as next_queue_number
            FROM queue_entries qe
            WHERE qe.station_id = ? AND DATE(qe.created_at) = ?
        ");
        
        $stmt->bind_param("is", $station_id, $date);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Helper method to get facility_id from appointments table
     * 
     * @param int $appointment_id
     * @return int|null facility_id or null if appointment not found
     */
    private function getFacilityIdFromAppointment($appointment_id) {
        try {
            $stmt = $this->conn->prepare("SELECT facility_id FROM appointments WHERE appointment_id = ?");
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $stmt->close();
            
            return $appointment ? (int)$appointment['facility_id'] : null;
        } catch (Exception $e) {
            error_log("Failed to get facility_id from appointment: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get default station for a service at facility (for queue_entries.station_id)
     */
    private function getDefaultStationForService($service_id, $facility_id = 1) {
        try {
            // First priority: Find OPEN stations for this service
            $stmt = $this->conn->prepare("
                SELECT station_id 
                FROM stations 
                WHERE service_id = ? AND is_active = 1 AND is_open = 1
                ORDER BY station_number ASC 
                LIMIT 1
            ");
            $stmt->bind_param("i", $service_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                return $row['station_id'];
            }
            
            // Fallback: return first active AND OPEN station for facility
            $stmt2 = $this->conn->prepare("
                SELECT station_id 
                FROM stations 
                WHERE is_active = 1 AND is_open = 1
                ORDER BY station_id ASC 
                LIMIT 1
            ");
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            
            if ($row2 = $result2->fetch_assoc()) {
                return $row2['station_id'];
            }
            
            return null; // No active stations found
            
        } catch (Exception $e) {
            error_log("Failed to get default station for service: " . $e->getMessage());
            return null;
        }
    }

    /**
     * MIGRATION HELPER: Update existing queue_entries with station_id
     * Run this once to populate station_id for existing queue entries
     */
    public function migrateQueueEntriesStationId() {
        try {
            // Update queue_entries with NULL station_id
            $stmt = $this->conn->prepare("
                UPDATE queue_entries qe
                JOIN services sv ON qe.service_id = sv.service_id  
                JOIN stations s ON sv.service_id = s.service_id
                SET qe.station_id = s.station_id
                WHERE qe.station_id IS NULL
                AND s.is_active = 1
                AND s.station_number = 1
            ");
            
            $stmt->execute();
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            return [
                'success' => true,
                'message' => "Updated {$affected_rows} queue entries with station_id"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Migration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all active employees for assignment dropdown
     */
    public function getActiveEmployees($facility_id = 1) {
        $stmt = $this->conn->prepare("
            SELECT 
                e.employee_id,
                CONCAT(e.first_name, ' ', e.last_name) as full_name,
                r.role_name,
                e.employee_number,
                f.name as facility_name
            FROM employees e
            JOIN roles r ON e.role_id = r.role_id
            JOIN facilities f ON e.facility_id = f.facility_id
            WHERE e.status = 'active' AND e.facility_id = ?
            ORDER BY e.last_name, e.first_name
        ");
        
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get allowed roles for a specific station type
     * Based on healthcare workflow and station requirements
     */
    public function getAllowedRolesForStation($station_type) {
        $role_mapping = [
            'checkin' => ['records_officer', 'nurse'],
            'triage' => ['nurse'],
            'billing' => ['cashier'],
            'consultation' => ['doctor'],
            'lab' => ['laboratory_tech'],
            'pharmacy' => ['pharmacist'],
            'document' => ['records_officer']
        ];
        
        return $role_mapping[$station_type] ?? [];
    }
    
    /**
     * Get most recent assignment for a station before a given date
     */
    public function getMostRecentAssignment($station_id, $before_date) {
        $stmt = $this->conn->prepare("
            SELECT employee_id, shift_start_time as shift_start, shift_end_time as shift_end
            FROM assignment_schedules 
            WHERE station_id = ? AND start_date < ? AND is_active = 1
            ORDER BY start_date DESC, schedule_id DESC
            LIMIT 1
        ");
        $stmt->bind_param("is", $station_id, $before_date);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
    
    /**
     * Copy assignments from one date to another
     */
    public function copyAssignments($source_date, $target_date, $assigned_by) {
        try {
            $this->conn->begin_transaction();
            
            // First, clear existing assignments for target date
            $this->clearAssignments($target_date);
            
            // Get all assignments from source date
            $stmt = $this->conn->prepare("
                SELECT station_id, employee_id, shift_start_time, shift_end_time, assignment_type
                FROM assignment_schedules 
                WHERE start_date = ? AND is_active = 1
            ");
            $stmt->bind_param("s", $source_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $copied_count = 0;
            while ($assignment = $result->fetch_assoc()) {
                // Insert into target date
                $insert_stmt = $this->conn->prepare("
                    INSERT INTO assignment_schedules 
                    (station_id, employee_id, start_date, shift_start_time, shift_end_time, assignment_type, assigned_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insert_stmt->bind_param("iissssi", 
                    $assignment['station_id'], 
                    $assignment['employee_id'], 
                    $target_date,
                    $assignment['shift_start_time'], 
                    $assignment['shift_end_time'], 
                    $assignment['assignment_type'],
                    $assigned_by
                );
                
                if ($insert_stmt->execute()) {
                    $copied_count++;
                }
            }
            
            $this->conn->commit();
            return $copied_count > 0;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }
    
    /**
     * Clear all assignments for a specific date (deactivate assignments covering that date)
     */
    public function clearAssignments($target_date) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE assignment_schedules 
                SET is_active = 0 
                WHERE is_active = 1
                AND start_date <= ?
                AND (end_date IS NULL OR end_date >= ?)
            ");
            $stmt->bind_param("ss", $target_date, $target_date);
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get assignment history for a station
     */
    public function getStationAssignmentHistory($station_id, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT 
                asch.*,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                r.role_name as employee_role,
                s.station_name
            FROM assignment_schedules asch
            JOIN employees e ON asch.employee_id = e.employee_id
            JOIN roles r ON e.role_id = r.role_id
            JOIN stations s ON asch.station_id = s.station_id
            WHERE asch.station_id = ?
            ORDER BY asch.start_date DESC, asch.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $station_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get employee assignment history
     */
    public function getEmployeeAssignmentHistory($employee_id, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT 
                asch.*,
                s.station_name,
                s.station_type,
                sv.name as service_name
            FROM assignment_schedules asch
            JOIN stations s ON asch.station_id = s.station_id
            JOIN services sv ON s.service_id = sv.service_id
            WHERE asch.employee_id = ?
            ORDER BY asch.start_date DESC, asch.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $employee_id, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Generate queue entry (wrapper for createQueueEntry for backward compatibility)
     * This method matches the call signature used in checkin.php
     * 
     * @param int $patient_id
     * @param int $facility_id (ignored - retrieved from appointment)
     * @param int $service_id
     * @param int $appointment_id
     * @param string $queue_type
     * @param string $priority_level
     * @return array Result with success status and queue details
     */
    public function generateQueue($patient_id, $facility_id, $service_id, $appointment_id, $queue_type = 'consultation', $priority_level = 'normal') {
        // Note: facility_id parameter is ignored and retrieved from appointments table instead
        // Call the existing createQueueEntry method with proper parameter order
        return $this->createQueueEntry($appointment_id, $patient_id, $service_id, $queue_type, $priority_level, null);
    }

    /**
     * Check for assignment conflicts before creating new assignment
     */
    public function checkAssignmentConflicts($employee_id, $station_id, $start_date, $end_date = null) {
        // Check employee conflicts (employee assigned to multiple stations)
        $employee_conflict = $this->conn->prepare("
            SELECT 
                asch.schedule_id,
                s.station_name,
                asch.start_date,
                asch.end_date
            FROM assignment_schedules asch
            JOIN stations s ON asch.station_id = s.station_id
            WHERE asch.employee_id = ? 
            AND asch.station_id != ?
            AND asch.is_active = 1
            AND asch.start_date <= ?
            AND (asch.end_date IS NULL OR asch.end_date >= ?)
        ");
        
        $check_end = $end_date ?: $start_date;
        $employee_conflict->bind_param("iiss", $employee_id, $station_id, $check_end, $start_date);
        $employee_conflict->execute();
        $employee_conflicts = $employee_conflict->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Check station conflicts (station assigned to multiple employees)
        $station_conflict = $this->conn->prepare("
            SELECT 
                asch.schedule_id,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                asch.start_date,
                asch.end_date
            FROM assignment_schedules asch
            JOIN employees e ON asch.employee_id = e.employee_id
            WHERE asch.station_id = ? 
            AND asch.employee_id != ?
            AND asch.is_active = 1
            AND asch.start_date <= ?
            AND (asch.end_date IS NULL OR asch.end_date >= ?)
        ");
        
        $station_conflict->bind_param("iiss", $station_id, $employee_id, $check_end, $start_date);
        $station_conflict->execute();
        $station_conflicts = $station_conflict->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'has_conflicts' => !empty($employee_conflicts) || !empty($station_conflicts),
            'employee_conflicts' => $employee_conflicts,
            'station_conflicts' => $station_conflicts
        ];
    }
    
    /**
     * Remove employee assignment from station using date ranges
     * Supports both ending assignments and deactivating them
     */
    public function removeEmployeeAssignment($station_id, $removal_date, $removal_type = 'end_assignment', $performed_by = null) {
        try {
            $this->conn->begin_transaction();
            
            // Find current active assignment with employee details
            $current_stmt = $this->conn->prepare("
                SELECT 
                    sch.schedule_id, 
                    sch.employee_id,
                    sch.start_date, 
                    sch.end_date,
                    sch.assignment_type,
                    sch.shift_start_time,
                    sch.shift_end_time,
                    e.first_name,
                    e.last_name,
                    s.station_name
                FROM assignment_schedules sch
                JOIN employees e ON sch.employee_id = e.employee_id
                JOIN stations s ON sch.station_id = s.station_id
                WHERE sch.station_id = ? 
                AND sch.is_active = 1
                AND sch.start_date <= ?
                AND (sch.end_date IS NULL OR sch.end_date >= ?)
            ");
            $current_stmt->bind_param("iss", $station_id, $removal_date, $removal_date);
            $current_stmt->execute();
            $current_assignment = $current_stmt->get_result()->fetch_assoc();
            
            if (!$current_assignment) {
                $this->conn->rollback();
                return [
                    'success' => false, 
                    'error' => 'No active assignment found for this station on the specified date'
                ];
            }
            
            $employee_name = $current_assignment['first_name'] . ' ' . $current_assignment['last_name'];
            $station_name = $current_assignment['station_name'];
            
            // Validate removal date
            if ($removal_date < $current_assignment['start_date']) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'error' => "Cannot remove assignment before it started ({$current_assignment['start_date']})"
                ];
            }
            
            if ($removal_type === 'deactivate') {
                // Deactivate the assignment (keeps record intact, just marks as inactive)
                $update_stmt = $this->conn->prepare("
                    UPDATE assignment_schedules 
                    SET is_active = 0, 
                        assigned_at = NOW()
                    WHERE schedule_id = ?
                ");
                $update_stmt->bind_param("i", $current_assignment['schedule_id']);
                
                $message = "Assignment deactivated successfully. {$employee_name} has been temporarily removed from {$station_name}. The assignment record is preserved and can be reactivated later.";
                
            } else { // end_assignment
                // End the assignment by setting end_date to the day before removal date
                $end_date = date('Y-m-d', strtotime($removal_date . ' - 1 day'));
                
                // Validate that end_date is not before start_date
                if ($end_date < $current_assignment['start_date']) {
                    $end_date = $current_assignment['start_date']; // Same day assignment
                }
                
                $update_stmt = $this->conn->prepare("
                    UPDATE assignment_schedules 
                    SET end_date = ?,
                        assigned_at = NOW()
                    WHERE schedule_id = ?
                ");
                $update_stmt->bind_param("si", $end_date, $current_assignment['schedule_id']);
                
                $message = "Assignment ended successfully. {$employee_name}'s assignment to {$station_name} has been ended as of {$end_date}.";
            }
            
            $result = $update_stmt->execute();
            $affected_rows = $update_stmt->affected_rows;
            
            if ($affected_rows === 0) {
                $this->conn->rollback();
                return [
                    'success' => false,
                    'error' => 'Failed to update assignment record'
                ];
            }
            
            // Log the removal action (optional - could be added to an audit table)
            if ($performed_by) {
                $log_stmt = $this->conn->prepare("
                    INSERT INTO assignment_logs (
                        schedule_id, employee_id, station_id, action_type, 
                        action_date, performed_by, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $action_type = $removal_type === 'deactivate' ? 'deactivated' : 'ended';
                $notes = $removal_type === 'deactivate' 
                    ? 'Assignment temporarily deactivated'
                    : "Assignment ended on {$removal_date}";
                
                $log_stmt->bind_param("iiiisis", 
                    $current_assignment['schedule_id'],
                    $current_assignment['employee_id'],
                    $station_id,
                    $action_type,
                    $removal_date,
                    $performed_by,
                    $notes
                );
                
                // Don't fail if logging fails
                @$log_stmt->execute();
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => $message,
                'details' => [
                    'employee_name' => $employee_name,
                    'station_name' => $station_name,
                    'removal_type' => $removal_type,
                    'removal_date' => $removal_date,
                    'end_date' => $removal_type === 'end_assignment' ? $end_date : null
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false, 
                'error' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}

?>