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
            $this->conn->beginTransaction();
            
            // Get appointment details for visit creation and queue numbering
            $stmt = $this->conn->prepare("
                SELECT a.facility_id, a.scheduled_date, a.scheduled_time 
                FROM appointments a 
                WHERE a.appointment_id = ?
            ");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            
            if (!$stmt->execute([$patient_id, $facility_id, $appointment_id, $appointment['scheduled_date']])) {
                throw new Exception("Failed to create visit record");
            }
            
            $visit_id = $this->conn->lastInsertId();
            
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
                error_log("No open stations found for service_id: $service_id, facility_id: $facility_id");
                throw new Exception("No open stations available for this service. Please try again later or contact staff.");
            }

            error_log("Creating queue entry with: visit_id=$visit_id, appointment_id=$appointment_id, patient_id=$patient_id, service_id=$service_id, station_id=$station_id, queue_type=$queue_type");

            $stmt = $this->conn->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, station_id,
                    queue_type, queue_number, queue_code, priority_level, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'waiting')
            ");
            
            if (!$stmt->execute([
                $visit_id, $appointment_id, $patient_id, $service_id, $station_id,
                $queue_type, $queue_number, $queue_code, $priority_level
            ])) {
                throw new Exception("Failed to create queue entry");
            }
            
            $queue_entry_id = $this->conn->lastInsertId();
            
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
            if ($this->conn->inTransaction()) {
                $this->conn->rollback();
            }
            error_log("QueueManagementService::createQueueEntry failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
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
            $this->conn->beginTransaction();
            
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
            
            if (!$stmt->execute($params)) {
                throw new Exception("Failed to update queue entry");
            }
            
            $affected_rows = $stmt->rowCount();
            
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
            $this->conn->beginTransaction();
            
            // Find the queue entry for this appointment
            $stmt = $this->conn->prepare("
                SELECT queue_entry_id, status 
                FROM queue_entries 
                WHERE appointment_id = ? AND status NOT IN ('done', 'cancelled')
            ");
            $stmt->execute([$appointment_id]);
            $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            
            if (!$stmt->execute([$cancellation_reason, $queue_entry_id])) {
                throw new Exception("Failed to cancel queue entry");
            }
            
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
            $this->conn->beginTransaction();
            
            // Validate queue entry exists and status is 'waiting'
            $stmt = $this->conn->prepare("
                SELECT qe.status, qe.visit_id, qe.queue_number, qe.queue_type, 
                       qe.priority_level, p.first_name, p.last_name
                FROM queue_entries qe
                JOIN patients p ON qe.patient_id = p.patient_id
                WHERE qe.queue_entry_id = ?
            ");
            $stmt->execute([$queue_entry_id]);
            $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            
            if (!$stmt->execute([$visit_id])) {
                throw new Exception("Failed to update visit time_in");
            }
            
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
            
            if (!$stmt->execute($params)) {
                throw new Exception("Failed to update queue entry status");
            }
            
            $affected_rows = $stmt->rowCount();
            
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
            $this->conn->beginTransaction();
            
            // Get current status
            $stmt = $this->conn->prepare("SELECT status FROM queue_entries WHERE queue_entry_id = ?");
            $stmt->execute([$queue_entry_id]);
            $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            
            if (!$stmt->execute([$remarks, $queue_entry_id])) {
                throw new Exception("Failed to reinstate queue entry");
            }
            
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
            $stmt->execute([$appointment_id]);
            $queue_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            $stmt->execute([$queue_type, $date]);
            $queue_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
        $stmt->execute([$scheduled_date, $slot_pattern]);
        $seq_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
            $stmt->execute([
                $queue_entry_id, $final_action, $old_status, $new_status, 
                $remarks, $performed_by
            ]);
            
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
            $stmt->execute([$date]);
            $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
        
        $stmt->execute([$employee_id, $date, $date]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
        
        $stmt->execute([$date, $date]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign employee to station using date ranges (much more efficient!)
     */
    public function assignEmployeeToStation($employee_id, $station_id, $start_date, $assignment_type = 'permanent', $shift_start = '08:00:00', $shift_end = '17:00:00', $assigned_by = null, $end_date = null) {
        try {
            // Debug logging
            error_log("assignEmployeeToStation called with: employee_id=$employee_id, station_id=$station_id, start_date=$start_date, assignment_type=$assignment_type");
            
            $this->conn->beginTransaction();
            
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
            $existing_stmt->execute([$employee_id, $station_id, $start_date]);
            $existing_result = $existing_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($existing_result) > 0) {
                $existing = $existing_result[0];
                
                // If there's an inactive assignment, reactivate it instead of creating new
                if ($existing['is_active'] == 0) {
                    $reactivate_stmt = $this->conn->prepare("
                        UPDATE assignment_schedules 
                        SET is_active = 1, end_date = ?, assignment_type = ?, 
                            shift_start_time = ?, shift_end_time = ?, assigned_by = ?, assigned_at = NOW()
                        WHERE schedule_id = ?
                    ");
                    $reactivate_stmt->execute([
                        $final_end_date, $assignment_type, $shift_start, $shift_end, $assigned_by, $existing['schedule_id']
                    ]);
                    
                    if ($reactivate_stmt->rowCount() === 0) {
                        throw new Exception("Failed to reactivate assignment");
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
            $check_stmt->execute([$station_id, $employee_id, $check_end_date, $start_date]);
            $check_result = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($check_result) > 0) {
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
            $emp_check_stmt->execute([
                $employee_id, $station_id, $check_end_date, $start_date,
                $shift_start, $shift_start, $shift_end, $shift_end, $shift_start, $shift_end
            ]);
            $emp_result = $emp_check_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($emp_result) > 0) {
                $conflict = $emp_result[0];
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
            
            $stmt->execute([
                $employee_id, $station_id, $start_date, $final_end_date, 
                $shift_start, $shift_end, $assignment_type, $assigned_by
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Failed to insert assignment");
            }
            
            $schedule_id = $this->conn->lastInsertId();
            $affected_rows = $stmt->rowCount();
            
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
            $this->conn->beginTransaction();
            
            // Find current active assignment that covers this date
            $current_stmt = $this->conn->prepare("
                SELECT schedule_id, start_date, end_date, shift_start_time, shift_end_time, assignment_type
                FROM assignment_schedules 
                WHERE station_id = ? 
                AND is_active = 1
                AND start_date <= ?
                AND (end_date IS NULL OR end_date >= ?)
            ");
            $current_stmt->execute([$station_id, $reassign_date, $reassign_date]);
            $current_assignment = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
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
            $update_stmt->execute([$end_current_date, $current_assignment['schedule_id']]);
            
            // Create new assignment starting from reassign_date
            $insert_stmt = $this->conn->prepare("
                INSERT INTO assignment_schedules 
                (employee_id, station_id, start_date, end_date, shift_start_time, shift_end_time, assignment_type, assigned_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Keep the same shift times and assignment type
            $insert_stmt->execute([
                $new_employee_id, $station_id, $reassign_date, $current_assignment['end_date'],
                $current_assignment['shift_start_time'], $current_assignment['shift_end_time'], 
                $current_assignment['assignment_type'], $assigned_by
            ]);
            
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
        $stmt->execute([$is_active, $station_id]);
        return $stmt->rowCount() > 0;
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
        
        if ($status_filter) {
            $base_query .= " AND qe.status = ?";
            $params[] = $status_filter;
        }
        
        $base_query .= " ORDER BY 
            FIELD(qe.priority_level, 'emergency', 'priority', 'normal'),
            qe.queue_number ASC
        ";
        
        if ($limit !== null && is_numeric($limit)) {
            $base_query .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->conn->prepare($base_query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            $stmt->execute([$station_id]);
            
            if ($next_patient = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
        
        $stmt->execute([$station_id, $date]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            $stmt->execute([$service_id]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
            
            if ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
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
            $affected_rows = $stmt->rowCount();
            
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
        
        $stmt->execute([$facility_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt->execute([$station_id, $before_date]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Copy assignments from one date to another
     */
    public function copyAssignments($source_date, $target_date, $assigned_by) {
        try {
            $this->conn->beginTransaction();
            
            // First, clear existing assignments for target date
            $this->clearAssignments($target_date);
            
            // Get all assignments from source date
            $stmt = $this->conn->prepare("
                SELECT station_id, employee_id, shift_start_time, shift_end_time, assignment_type
                FROM assignment_schedules 
                WHERE start_date = ? AND is_active = 1
            ");
            $stmt->execute([$source_date]);
            
            $copied_count = 0;
            while ($assignment = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Insert into target date
                $insert_stmt = $this->conn->prepare("
                    INSERT INTO assignment_schedules 
                    (station_id, employee_id, start_date, shift_start_time, shift_end_time, assignment_type, assigned_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insert_stmt->execute([
                    $assignment['station_id'], 
                    $assignment['employee_id'], 
                    $target_date,
                    $assignment['shift_start_time'], 
                    $assignment['shift_end_time'], 
                    $assignment['assignment_type'],
                    $assigned_by
                ]);
                
                $copied_count++;
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
            return $stmt->execute([$target_date, $target_date]);
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
        $stmt->execute([$station_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $stmt->execute([$employee_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $employee_conflict->execute([$employee_id, $station_id, $check_end, $start_date]);
        $employee_conflicts = $employee_conflict->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        $station_conflict->execute([$station_id, $employee_id, $check_end, $start_date]);
        $station_conflicts = $station_conflict->fetchAll(PDO::FETCH_ASSOC);
        
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
            $this->conn->beginTransaction();
            
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
            $current_stmt->execute([$station_id, $removal_date, $removal_date]);
            $current_assignment = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
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
                $update_stmt->execute([$current_assignment['schedule_id']]);
                
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
                $update_stmt->execute([$end_date, $current_assignment['schedule_id']]);
                
                $message = "Assignment ended successfully. {$employee_name}'s assignment to {$station_name} has been ended as of {$end_date}.";
            }
            
            $affected_rows = $update_stmt->rowCount();
            
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
                
                // Don't fail if logging fails
                try {
                    $log_stmt->execute([
                        $current_assignment['schedule_id'],
                        $current_assignment['employee_id'],
                        $station_id,
                        $action_type,
                        $removal_date,
                        $performed_by,
                        $notes
                    ]);
                } catch (Exception $e) {
                    // Ignore logging errors
                }
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

    // ============================================
    // CHECK-IN OPERATIONS FOR PATIENT MANAGEMENT
    // ============================================

    /**
     * Check-in a patient for their appointment
     * Register patient arrival, assign to queue, and create visit entry
     * 
     * @param int $appointment_id Appointment ID to check-in
     * @param int $employee_id Employee performing the check-in
     * @return array Result with success status and queue details
     */
    public function checkin_patient($appointment_id, $employee_id) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Validate the appointment
            $stmt = $this->conn->prepare("
                SELECT 
                    a.appointment_id,
                    a.patient_id,
                    a.facility_id,
                    a.service_id,
                    a.status,
                    a.scheduled_date,
                    a.scheduled_time,
                    p.isSenior,
                    p.isPWD,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                WHERE a.appointment_id = ? AND a.status NOT IN ('cancelled', 'completed')
            ");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                throw new Exception("Appointment not found or already cancelled/completed");
            }
            
            // 2. Check for an open triage station
            $station_stmt = $this->conn->prepare("
                SELECT 
                    s.station_id,
                    s.station_name,
                    s.station_type,
                    COUNT(qe.queue_entry_id) as waiting_count
                FROM stations s
                LEFT JOIN queue_entries qe ON s.station_id = qe.station_id 
                    AND qe.status = 'waiting' 
                    AND DATE(qe.created_at) = CURDATE()
                WHERE s.station_type = 'triage' 
                AND s.is_open = 1 
                AND s.is_active = 1
                GROUP BY s.station_id
                ORDER BY waiting_count ASC, s.station_number ASC
                LIMIT 1
            ");
            $station_stmt->execute();
            $station = $station_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$station) {
                throw new Exception("No open triage stations available");
            }
            
            // 3. Create new record in visits
            $visit_stmt = $this->conn->prepare("
                INSERT INTO visits (
                    patient_id, facility_id, appointment_id, 
                    visit_date, time_in, visit_status, 
                    created_at, updated_at
                ) VALUES (?, ?, ?, CURDATE(), NOW(), 'ongoing', NOW(), NOW())
            ");
            $visit_stmt->execute([
                $appointment['patient_id'], 
                $appointment['facility_id'], 
                $appointment_id
            ]);
            
            $visit_id = $this->conn->lastInsertId();
            
            // 4. Generate new queue_code
            $queue_prefix = strtoupper(substr($station['station_type'], 0, 3)) . date('d');
            
            // Get sequential number for today
            $seq_stmt = $this->conn->prepare("
                SELECT COUNT(*) + 1 as seq_num
                FROM queue_entries 
                WHERE DATE(created_at) = CURDATE()
            ");
            $seq_stmt->execute();
            $seq_data = $seq_stmt->fetch(PDO::FETCH_ASSOC);
            $seq_num = (int)$seq_data['seq_num'];
            
            $queue_code = $queue_prefix . '-' . str_pad($seq_num, 3, '0', STR_PAD_LEFT);
            
            // 5. Determine priority based on patient status
            $priority = 'normal';
            if ($appointment['isSenior'] || $appointment['isPWD']) {
                $priority = 'priority';
            }
            
            // 6. Insert into queue_entries
            $queue_stmt = $this->conn->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, station_id,
                    queue_type, queue_number, queue_code, priority_level, status,
                    time_in, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 'triage', ?, ?, ?, 'waiting', NOW(), NOW(), NOW())
            ");
            $queue_stmt->execute([
                $visit_id, $appointment_id, $appointment['patient_id'], 
                $appointment['service_id'], $station['station_id'],
                $seq_num, $queue_code, $priority
            ]);
            
            $queue_entry_id = $this->conn->lastInsertId();
            
            // 7. Update appointment status to 'checked_in'
            $appt_stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'checked_in', updated_at = NOW()
                WHERE appointment_id = ?
            ");
            $appt_stmt->execute([$appointment_id]);
            
            // 8. Insert audit log
            $this->logQueueAction($queue_entry_id, 'created', null, 'waiting', 
                "Patient checked in from appointment - Queue: {$queue_code}", $employee_id);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Patient successfully checked in and added to Triage queue",
                'data' => [
                    'queue_code' => $queue_code,
                    'station_name' => $station['station_name'],
                    'patient_name' => $appointment['patient_name'],
                    'priority' => $priority,
                    'queue_entry_id' => $queue_entry_id,
                    'visit_id' => $visit_id
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Check-in failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Flag a patient for compliance or administrative issues
     * 
     * @param int $patient_id Patient ID to flag
     * @param string $flag_type Type of flag (false_senior, false_philhealth, etc.)
     * @param string $remarks Detailed remarks about the flag
     * @param int $employee_id Employee creating the flag
     * @param int|null $appointment_id Optional appointment ID related to the flag
     * @return array Result with success status
     */
    public function flag_patient($patient_id, $flag_type, $remarks, $employee_id, $appointment_id = null) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Insert new row into patient_flags
            $flag_stmt = $this->conn->prepare("
                INSERT INTO patient_flags (
                    patient_id, appointment_id, flag_type, remarks, 
                    flagged_by, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $flag_stmt->bind_param("iissi", 
                $patient_id, $appointment_id, $flag_type, $remarks, $employee_id
            );
            
            if (!$flag_stmt->execute()) {
                throw new Exception("Failed to create patient flag: " . $flag_stmt->error);
            }
            $flag_stmt->close();
            
            $message = "Patient flag recorded successfully";
            
            // 2. Handle special flag types
            if ($flag_type === 'false_patient_booked') {
                // Cancel any active appointments for this patient
                $cancel_stmt = $this->conn->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', 
                        cancellation_reason = 'Auto-cancelled due to false booking flag',
                        updated_at = NOW()
                    WHERE patient_id = ? 
                    AND status IN ('confirmed', 'checked_in')
                ");
                $cancel_stmt->bind_param("i", $patient_id);
                $cancel_stmt->execute();
                $cancelled_count = $cancel_stmt->affected_rows;
                $cancel_stmt->close();
                
                // Log the cancellations if there's a specific appointment ID
                if ($appointment_id && $cancelled_count > 0) {
                    $log_stmt = $this->conn->prepare("
                        INSERT INTO appointment_logs (
                            appointment_id, patient_id, action, reason, 
                            performed_by, created_at
                        ) VALUES (?, ?, 'cancelled', 'Auto-cancelled due to false booking flag', ?, NOW())
                    ");
                    $log_stmt->bind_param("iii", $appointment_id, $patient_id, $employee_id);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                
                $message .= ". {$cancelled_count} active appointment(s) have been cancelled";
            }
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Patient flag failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Flag operation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel an appointment and record the reason
     * 
     * @param int $appointment_id Appointment ID to cancel
     * @param string $reason Cancellation reason
     * @param int $employee_id Employee performing the cancellation
     * @return array Result with success status
     */
    public function cancel_appointment($appointment_id, $reason, $employee_id) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Validate appointment exists and get details
            $appt_stmt = $this->conn->prepare("
                SELECT 
                    a.appointment_id,
                    a.patient_id,
                    a.status,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                WHERE a.appointment_id = ?
            ");
            $appt_stmt->bind_param("i", $appointment_id);
            $appt_stmt->execute();
            $result = $appt_stmt->get_result();
            $appointment = $result->fetch_assoc();
            $appt_stmt->close();
            
            if (!$appointment) {
                throw new Exception("Appointment not found");
            }
            
            if ($appointment['status'] === 'completed') {
                throw new Exception("Cannot cancel a completed appointment");
            }
            
            if ($appointment['status'] === 'cancelled') {
                throw new Exception("Appointment is already cancelled");
            }
            
            // 2. Update appointments table
            $update_stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'cancelled', 
                    cancellation_reason = ?,
                    updated_at = NOW()
                WHERE appointment_id = ?
            ");
            $update_stmt->bind_param("si", $reason, $appointment_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update appointment status: " . $update_stmt->error);
            }
            $update_stmt->close();
            
            // 3. Insert into appointment_logs
            $log_stmt = $this->conn->prepare("
                INSERT INTO appointment_logs (
                    appointment_id, patient_id, action, reason, 
                    performed_by, created_at
                ) VALUES (?, ?, 'cancelled', ?, ?, NOW())
            ");
            $log_stmt->bind_param("iisi", 
                $appointment_id, $appointment['patient_id'], $reason, $employee_id
            );
            
            if (!$log_stmt->execute()) {
                throw new Exception("Failed to create appointment log: " . $log_stmt->error);
            }
            $log_stmt->close();
            
            // 4. Cancel any related queue entries
            $queue_cancel_result = $this->cancelQueueEntry($appointment_id, $reason, $employee_id);
            // Don't fail if no queue entry exists (appointment might not have been checked in yet)
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Appointment cancelled successfully",
                'data' => [
                    'appointment_id' => $appointment_id,
                    'patient_name' => $appointment['patient_name'],
                    'reason' => $reason
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Appointment cancellation failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Cancellation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get comprehensive patient details for check-in interface
     * 
     * @param int $patient_id Patient ID
     * @param int|null $appointment_id Optional appointment ID for appointment-specific details
     * @return array Patient information including flags and appointment history
     */
    public function getPatientCheckInDetails($patient_id, $appointment_id = null) {
        try {
            // Get patient basic information
            $patient_stmt = $this->conn->prepare("
                SELECT 
                    p.*,
                    b.barangay_name as barangay,
                    TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                FROM patients p
                LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
                WHERE p.patient_id = ?
            ");
            $patient_stmt->bind_param("i", $patient_id);
            $patient_stmt->execute();
            $patient_result = $patient_stmt->get_result();
            $patient = $patient_result->fetch_assoc();
            $patient_stmt->close();
            
            if (!$patient) {
                return [
                    'success' => false,
                    'message' => 'Patient not found'
                ];
            }
            
            // Get appointment details if provided
            $appointment = null;
            if ($appointment_id) {
                $appt_stmt = $this->conn->prepare("
                    SELECT 
                        a.*,
                        s.name as service_name,
                        DATE_FORMAT(a.scheduled_date, '%M %d, %Y') as formatted_date,
                        TIME_FORMAT(a.scheduled_time, '%h:%i %p') as formatted_time
                    FROM appointments a
                    LEFT JOIN services s ON a.service_id = s.service_id
                    WHERE a.appointment_id = ? AND a.patient_id = ?
                ");
                $appt_stmt->bind_param("ii", $appointment_id, $patient_id);
                $appt_stmt->execute();
                $appt_result = $appt_stmt->get_result();
                $appointment = $appt_result->fetch_assoc();
                $appt_stmt->close();
            }
            
            // Get recent patient flags
            $flags_stmt = $this->conn->prepare("
                SELECT 
                    pf.*,
                    CONCAT(e.first_name, ' ', e.last_name) as flagged_by_name
                FROM patient_flags pf
                LEFT JOIN employees e ON pf.flagged_by = e.employee_id
                WHERE pf.patient_id = ?
                ORDER BY pf.created_at DESC
                LIMIT 5
            ");
            $flags_stmt->bind_param("i", $patient_id);
            $flags_stmt->execute();
            $flags_result = $flags_stmt->get_result();
            $flags = $flags_result->fetch_all(MYSQLI_ASSOC);
            $flags_stmt->close();
            
            // Get recent visit history
            $visits_stmt = $this->conn->prepare("
                SELECT 
                    v.visit_date,
                    v.visit_status,
                    f.name as facility_name
                FROM visits v
                LEFT JOIN facilities f ON v.facility_id = f.facility_id
                WHERE v.patient_id = ?
                ORDER BY v.visit_date DESC
                LIMIT 3
            ");
            $visits_stmt->bind_param("i", $patient_id);
            $visits_stmt->execute();
            $visits_result = $visits_stmt->get_result();
            $visits = $visits_result->fetch_all(MYSQLI_ASSOC);
            $visits_stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'patient' => $patient,
                    'appointment' => $appointment,
                    'flags' => $flags,
                    'recent_visits' => $visits
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get patient check-in details failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve patient details: ' . $e->getMessage()
            ];
        }
    }

    // ============================================
    // PATIENT ROUTING METHODS FOR HEALTHCARE WORKFLOW
    // ============================================

    /**
     * Route patient to a different station (Lab, Pharmacy, etc.)
     * Creates a new queue entry at the target station type
     * 
     * @param int $queue_entry_id Current queue entry ID
     * @param string $target_station_type Target station type (lab, pharmacy, consultation)
     * @param int $employee_id Employee performing the routing
     * @param string $remarks Routing notes/reason
     * @return array Result with success status
     */
    public function routePatientToStation($queue_entry_id, $target_station_type, $employee_id, $remarks = '') {
        try {
            $this->conn->beginTransaction();
            
            // 1. Get current queue entry details
            $current_stmt = $this->conn->prepare("
                SELECT 
                    qe.visit_id,
                    qe.appointment_id,
                    qe.patient_id,
                    qe.service_id,
                    qe.status,
                    qe.queue_code,
                    s.station_type as current_station_type,
                    s.station_name as current_station_name,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM queue_entries qe
                JOIN stations s ON qe.station_id = s.station_id
                JOIN patients p ON qe.patient_id = p.patient_id
                WHERE qe.queue_entry_id = ?
            ");
            $current_stmt->execute([$queue_entry_id]);
            $current_entry = $current_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current_entry) {
                throw new Exception("Queue entry not found");
            }
            
            if ($current_entry['status'] !== 'in_progress') {
                throw new Exception("Can only route patients who are currently in progress");
            }
            
            // 2. Find available station of target type
            $target_stmt = $this->conn->prepare("
                SELECT 
                    s.station_id,
                    s.station_name,
                    COUNT(qe.queue_entry_id) as waiting_count
                FROM stations s
                LEFT JOIN queue_entries qe ON s.station_id = qe.station_id 
                    AND qe.status = 'waiting' 
                    AND DATE(qe.created_at) = CURDATE()
                WHERE s.station_type = ? 
                AND s.is_active = 1 
                AND s.is_open = 1
                GROUP BY s.station_id
                ORDER BY waiting_count ASC, s.station_number ASC
                LIMIT 1
            ");
            $target_stmt->execute([$target_station_type]);
            $target_station = $target_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$target_station) {
                throw new Exception("No open {$target_station_type} stations available");
            }
            
            // 3. Complete current queue entry
            $complete_stmt = $this->conn->prepare("
                UPDATE queue_entries 
                SET status = 'done',
                    time_completed = NOW(),
                    turnaround_time = TIMESTAMPDIFF(MINUTE, time_in, NOW()),
                    remarks = ?
                WHERE queue_entry_id = ?
            ");
            $routing_remarks = "Referred to {$target_station_type}: " . $remarks;
            $complete_stmt->execute([$routing_remarks, $queue_entry_id]);
            
            // 4. Generate new queue number for target station
            $queue_num_stmt = $this->conn->prepare("
                SELECT COUNT(*) + 1 as next_number
                FROM queue_entries 
                WHERE station_id = ? AND DATE(created_at) = CURDATE()
            ");
            $queue_num_stmt->execute([$target_station['station_id']]);
            $queue_num_data = $queue_num_stmt->fetch(PDO::FETCH_ASSOC);
            $new_queue_number = $queue_num_data['next_number'];
            
            // 5. Create new queue entry at target station
            $new_queue_stmt = $this->conn->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, station_id,
                    queue_type, queue_number, queue_code, priority_level, status,
                    time_in, remarks, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'normal', 'waiting', NOW(), ?, NOW(), NOW())
            ");
            
            $new_queue_code = strtoupper(substr($target_station_type, 0, 3)) . '-' . str_pad($new_queue_number, 3, '0', STR_PAD_LEFT);
            $new_remarks = "Referred from {$current_entry['current_station_name']}: " . $remarks;
            
            $new_queue_stmt->execute([
                $current_entry['visit_id'],
                $current_entry['appointment_id'],
                $current_entry['patient_id'],
                $current_entry['service_id'],
                $target_station['station_id'],
                $target_station_type,
                $new_queue_number,
                $new_queue_code,
                $new_remarks
            ]);
            
            $new_queue_entry_id = $this->conn->lastInsertId();
            
            // 6. Log both actions
            $this->logQueueAction($queue_entry_id, 'completed', 'in_progress', 'done', 
                "Patient routed to {$target_station_type}", $employee_id);
            
            $this->logQueueAction($new_queue_entry_id, 'created', null, 'waiting', 
                "Patient routed from {$current_entry['current_station_type']} - {$remarks}", $employee_id);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Patient successfully routed to {$target_station['station_name']}",
                'data' => [
                    'patient_name' => $current_entry['patient_name'],
                    'from_station' => $current_entry['current_station_name'],
                    'to_station' => $target_station['station_name'],
                    'new_queue_code' => $new_queue_code,
                    'new_queue_entry_id' => $new_queue_entry_id
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Patient routing failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Complete patient visit - marks all queue entries as done and visit as completed
     * 
     * @param int $queue_entry_id Current queue entry ID
     * @param int $employee_id Employee completing the visit
     * @param string $remarks Completion notes
     * @return array Result with success status
     */
    public function completePatientVisit($queue_entry_id, $employee_id, $remarks = '') {
        try {
            $this->conn->beginTransaction();
            
            // 1. Get queue entry and visit details
            $entry_stmt = $this->conn->prepare("
                SELECT 
                    qe.visit_id,
                    qe.appointment_id,
                    qe.patient_id,
                    qe.status,
                    v.visit_status,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM queue_entries qe
                JOIN visits v ON qe.visit_id = v.visit_id
                JOIN patients p ON qe.patient_id = p.patient_id
                WHERE qe.queue_entry_id = ?
            ");
            $entry_stmt->execute([$queue_entry_id]);
            $entry_data = $entry_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$entry_data) {
                throw new Exception("Queue entry not found");
            }
            
            // 2. Complete current queue entry
            $complete_stmt = $this->conn->prepare("
                UPDATE queue_entries 
                SET status = 'done',
                    time_completed = NOW(),
                    turnaround_time = TIMESTAMPDIFF(MINUTE, time_in, NOW()),
                    remarks = ?
                WHERE queue_entry_id = ?
            ");
            $final_remarks = "Visit completed: " . $remarks;
            $complete_stmt->execute([$final_remarks, $queue_entry_id]);
            
            // 3. Mark visit as completed
            $visit_stmt = $this->conn->prepare("
                UPDATE visits 
                SET visit_status = 'completed',
                    time_out = NOW(),
                    attending_employee_id = ?,
                    remarks = ?,
                    updated_at = NOW()
                WHERE visit_id = ?
            ");
            $visit_stmt->execute([$employee_id, $final_remarks, $entry_data['visit_id']]);
            
            // 4. Mark appointment as completed
            $appt_stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = 'completed',
                    updated_at = NOW()
                WHERE appointment_id = ?
            ");
            $appt_stmt->execute([$entry_data['appointment_id']]);
            
            // 5. Log the completion
            $this->logQueueAction($queue_entry_id, 'completed', 'in_progress', 'done', 
                "Visit completed - no further treatment needed: " . $remarks, $employee_id);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => "Patient visit completed successfully",
                'data' => [
                    'patient_name' => $entry_data['patient_name'],
                    'visit_id' => $entry_data['visit_id'],
                    'appointment_id' => $entry_data['appointment_id']
                ]
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Visit completion failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get patient's current queue status and routing history
     * 
     * @param int $patient_id Patient ID
     * @param int|null $visit_id Optional specific visit ID
     * @return array Current queue information and routing history
     */
    public function getPatientQueueStatus($patient_id, $visit_id = null) {
        try {
            // Get current active queue entries
            $current_stmt = $this->conn->prepare("
                SELECT 
                    qe.queue_entry_id,
                    qe.queue_code,
                    qe.status,
                    qe.priority_level,
                    qe.time_in,
                    qe.time_started,
                    s.station_name,
                    s.station_type,
                    v.visit_status
                FROM queue_entries qe
                JOIN stations s ON qe.station_id = s.station_id
                JOIN visits v ON qe.visit_id = v.visit_id
                WHERE qe.patient_id = ? 
                " . ($visit_id ? "AND qe.visit_id = ?" : "") . "
                AND qe.status IN ('waiting', 'in_progress')
                AND DATE(qe.created_at) = CURDATE()
                ORDER BY qe.created_at DESC
            ");
            
            if ($visit_id) {
                $current_stmt->bind_param("ii", $patient_id, $visit_id);
            } else {
                $current_stmt->bind_param("i", $patient_id);
            }
            
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_queues = $current_result->fetch_all(MYSQLI_ASSOC);
            $current_stmt->close();
            
            // Get routing history for today
            $history_stmt = $this->conn->prepare("
                SELECT 
                    qe.queue_code,
                    qe.status,
                    qe.time_in,
                    qe.time_started,
                    qe.time_completed,
                    qe.remarks,
                    s.station_name,
                    s.station_type
                FROM queue_entries qe
                JOIN stations s ON qe.station_id = s.station_id
                WHERE qe.patient_id = ?
                " . ($visit_id ? "AND qe.visit_id = ?" : "") . "
                AND DATE(qe.created_at) = CURDATE()
                ORDER BY qe.created_at ASC
            ");
            
            if ($visit_id) {
                $history_stmt->bind_param("ii", $patient_id, $visit_id);
            } else {
                $history_stmt->bind_param("i", $patient_id);
            }
            
            $history_stmt->execute();
            $history_result = $history_stmt->get_result();
            $routing_history = $history_result->fetch_all(MYSQLI_ASSOC);
            $history_stmt->close();
            
            return [
                'success' => true,
                'data' => [
                    'current_queues' => $current_queues,
                    'routing_history' => $routing_history
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get patient queue status failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

?>
