<?php
/**
 * Queue Model
 * Handles all database operations for queue_entries and queue_logs tables
 * 
 * This model provides data access methods for:
 * - Creating new queue entries with auto-generated queue numbers
 * - Updating queue status with comprehensive logging
 * - Retrieving active queues by service with proper ordering
 * - Managing queue transfers between services
 * - Reinstating previously skipped/cancelled entries
 * 
 * All operations use PDO for security and include transaction management
 * All status changes are logged to queue_logs table for audit trail
 */

class QueueModel {
    
    private $pdo;
    
    /**
     * Constructor - Initialize PDO connection
     */
    public function __construct() {
        // Include database connection
        $root_path = dirname(dirname(dirname(__DIR__)));
        require_once $root_path . '/config/db.php';
        
        global $pdo;
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new queue entry with auto-generated queue number
     * 
     * @param int $appointment_id Appointment identifier
     * @param int $patient_id Patient identifier  
     * @param int $service_id Service identifier
     * @param string $queue_type Type of queue (triage, consultation, lab, etc.)
     * @param string $priority_level Priority level (normal, priority, emergency)
     * @return array Result with success status and queue entry details
     * @throws Exception For database errors or validation failures
     */
    public function createQueueEntry($appointment_id, $patient_id, $service_id, $queue_type, $priority_level = 'normal') {
        try {
            $this->pdo->beginTransaction();
            
            // Get visit_id from appointment (required for queue_entries)
            $visitStmt = $this->pdo->prepare("
                SELECT visit_id 
                FROM appointments 
                WHERE appointment_id = ?
            ");
            $visitStmt->execute([$appointment_id]);
            $visit = $visitStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$visit) {
                throw new Exception("Appointment not found: $appointment_id");
            }
            
            $visit_id = $visit['visit_id'];
            
            // Generate queue number (max per service for today + 1)
            $queueNumber = $this->generateQueueNumber($service_id, $queue_type, $priority_level);
            
            // Insert new queue entry
            $insertStmt = $this->pdo->prepare("
                INSERT INTO queue_entries (
                    visit_id, appointment_id, patient_id, service_id, 
                    queue_type, queue_number, priority_level, status,
                    time_in, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting', NOW(), NOW())
            ");
            
            $insertStmt->execute([
                $visit_id, $appointment_id, $patient_id, $service_id,
                $queue_type, $queueNumber, $priority_level
            ]);
            
            $queueEntryId = $this->pdo->lastInsertId();
            
            // Log the creation
            $this->logQueueAction($queueEntryId, 'created', null, 'waiting', 'Queue entry created', null);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'queue_entry_id' => $queueEntryId,
                'queue_number' => $queueNumber,
                'priority_level' => $priority_level,
                'status' => 'waiting',
                'message' => 'Queue entry created successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to create queue entry: " . $e->getMessage());
        }
    }
    
    /**
     * Update queue entry status and log the change
     * 
     * @param int $queue_entry_id Queue entry identifier
     * @param string $new_status New status value
     * @param int $performed_by Employee ID performing the action
     * @param string $remarks Optional remarks about the status change
     * @return array Result with success status and updated details
     * @throws Exception For database errors or invalid status transitions
     */
    public function updateQueueStatus($queue_entry_id, $new_status, $performed_by, $remarks = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current queue entry details
            $currentStmt = $this->pdo->prepare("
                SELECT status, queue_number, service_id, patient_id
                FROM queue_entries 
                WHERE queue_entry_id = ?
            ");
            $currentStmt->execute([$queue_entry_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception("Queue entry not found: $queue_entry_id");
            }
            
            $old_status = $current['status'];
            
            // Validate status transition
            $this->validateStatusTransition($old_status, $new_status);
            
            // Update timestamps based on status
            $timeFields = [];
            $timeValues = [];
            
            if ($new_status === 'in_progress' && $old_status === 'waiting') {
                $timeFields[] = 'time_started = NOW()';
                // Calculate waiting time
                $timeFields[] = 'waiting_time = TIMESTAMPDIFF(MINUTE, time_in, NOW())';
            } elseif (in_array($new_status, ['done', 'cancelled', 'no_show'])) {
                $timeFields[] = 'time_completed = NOW()';
                // Calculate turnaround time if not already set
                if ($old_status !== 'done') {
                    $timeFields[] = 'turnaround_time = TIMESTAMPDIFF(MINUTE, time_in, NOW())';
                }
            }
            
            // Build update query
            $updateFields = 'status = ?, updated_at = NOW()';
            $updateValues = [$new_status];
            
            if (!empty($timeFields)) {
                $updateFields .= ', ' . implode(', ', $timeFields);
            }
            
            if ($remarks) {
                $updateFields .= ', remarks = ?';
                $updateValues[] = $remarks;
            }
            
            $updateValues[] = $queue_entry_id;
            
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_entries 
                SET $updateFields
                WHERE queue_entry_id = ?
            ");
            
            $updateStmt->execute($updateValues);
            
            // Log the status change
            $this->logQueueAction($queue_entry_id, 'status_changed', $old_status, $new_status, $remarks, $performed_by);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'queue_entry_id' => $queue_entry_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'queue_number' => $current['queue_number'],
                'message' => "Status updated from '$old_status' to '$new_status'"
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to update queue status: " . $e->getMessage());
        }
    }
    
    /**
     * Get active queue entries for a specific service
     * 
     * @param int $service_id Service identifier
     * @param string $queue_type Queue type filter
     * @return array Array of queue entries ordered by priority and creation time
     * @throws Exception For database errors
     */
    public function getActiveQueueByService($service_id, $queue_type) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    qe.queue_entry_id,
                    qe.queue_number,
                    qe.priority_level,
                    qe.status,
                    qe.time_in,
                    qe.time_started,
                    qe.waiting_time,
                    qe.remarks,
                    qe.patient_id,
                    qe.appointment_id,
                    p.first_name,
                    p.last_name,
                    p.patient_number,
                    a.appointment_date,
                    a.appointment_time,
                    s.service_name
                FROM queue_entries qe
                INNER JOIN patients p ON qe.patient_id = p.patient_id
                INNER JOIN appointments a ON qe.appointment_id = a.appointment_id
                INNER JOIN services s ON qe.service_id = s.service_id
                WHERE qe.service_id = ? 
                  AND qe.queue_type = ?
                  AND qe.status IN ('waiting', 'in_progress')
                  AND DATE(qe.time_in) = CURDATE()
                ORDER BY 
                    CASE qe.priority_level 
                        WHEN 'emergency' THEN 1
                        WHEN 'priority' THEN 2  
                        WHEN 'normal' THEN 3
                    END,
                    qe.time_in ASC
            ");
            
            $stmt->execute([$service_id, $queue_type]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate estimated wait times
            foreach ($results as &$entry) {
                $entry['estimated_wait'] = $this->calculateEstimatedWait($entry, $results);
                $entry['patient_name'] = $entry['first_name'] . ' ' . $entry['last_name'];
            }
            
            return [
                'success' => true,
                'service_id' => $service_id,
                'queue_type' => $queue_type,
                'total_waiting' => count(array_filter($results, fn($e) => $e['status'] === 'waiting')),
                'total_in_progress' => count(array_filter($results, fn($e) => $e['status'] === 'in_progress')),
                'queue_entries' => $results
            ];
            
        } catch (Exception $e) {
            throw new Exception("Failed to get active queue: " . $e->getMessage());
        }
    }
    
    /**
     * Reassign a queue entry to a different service
     * 
     * @param int $queue_entry_id Queue entry identifier
     * @param int $new_service_id Target service identifier
     * @param string $queue_type Queue type for new service
     * @param int $performed_by Employee ID performing the reassignment
     * @return array Result with success status and new queue details
     * @throws Exception For database errors or validation failures
     */
    public function reassignQueue($queue_entry_id, $new_service_id, $queue_type, $performed_by) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current queue entry details
            $currentStmt = $this->pdo->prepare("
                SELECT service_id, status, priority_level, patient_id
                FROM queue_entries 
                WHERE queue_entry_id = ?
            ");
            $currentStmt->execute([$queue_entry_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception("Queue entry not found: $queue_entry_id");
            }
            
            // Validate reassignment is allowed
            if ($current['status'] === 'done') {
                throw new Exception("Cannot reassign completed queue entry");
            }
            
            $old_service_id = $current['service_id'];
            
            // Generate new queue number for target service
            $newQueueNumber = $this->generateQueueNumber($new_service_id, $queue_type, $current['priority_level']);
            
            // Update queue entry
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_entries 
                SET service_id = ?, 
                    queue_type = ?, 
                    queue_number = ?,
                    status = 'waiting',
                    time_started = NULL,
                    time_completed = NULL,
                    waiting_time = NULL,
                    turnaround_time = NULL,
                    updated_at = NOW()
                WHERE queue_entry_id = ?
            ");
            
            $updateStmt->execute([$new_service_id, $queue_type, $newQueueNumber, $queue_entry_id]);
            
            // Log the reassignment
            $remarks = "Moved from service $old_service_id to service $new_service_id";
            $this->logQueueAction($queue_entry_id, 'moved', $current['status'], 'waiting', $remarks, $performed_by);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'queue_entry_id' => $queue_entry_id,
                'old_service_id' => $old_service_id,
                'new_service_id' => $new_service_id,
                'new_queue_number' => $newQueueNumber,
                'message' => 'Queue entry reassigned successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to reassign queue: " . $e->getMessage());
        }
    }
    
    /**
     * Reinstate a previously skipped, cancelled, or no-show patient
     * 
     * @param int $queue_entry_id Queue entry identifier
     * @param int $performed_by Employee ID performing the reinstatement
     * @param string $remarks Reason for reinstatement
     * @return array Result with success status and updated details
     * @throws Exception For database errors or validation failures
     */
    public function reinstateQueue($queue_entry_id, $performed_by, $remarks) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current queue entry details
            $currentStmt = $this->pdo->prepare("
                SELECT status, service_id, queue_type, priority_level
                FROM queue_entries 
                WHERE queue_entry_id = ?
            ");
            $currentStmt->execute([$queue_entry_id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                throw new Exception("Queue entry not found: $queue_entry_id");
            }
            
            $old_status = $current['status'];
            
            // Validate reinstatement is allowed
            if (!in_array($old_status, ['skipped', 'cancelled', 'no_show'])) {
                throw new Exception("Can only reinstate skipped, cancelled, or no-show entries. Current status: $old_status");
            }
            
            // Generate new queue number (patient goes to end of queue)
            $newQueueNumber = $this->generateQueueNumber($current['service_id'], $current['queue_type'], $current['priority_level']);
            
            // Update queue entry
            $updateStmt = $this->pdo->prepare("
                UPDATE queue_entries 
                SET status = 'waiting',
                    queue_number = ?,
                    time_started = NULL,
                    time_completed = NULL,
                    waiting_time = NULL,
                    turnaround_time = NULL,
                    remarks = ?,
                    updated_at = NOW()
                WHERE queue_entry_id = ?
            ");
            
            $updateStmt->execute([$newQueueNumber, $remarks, $queue_entry_id]);
            
            // Log the reinstatement
            $this->logQueueAction($queue_entry_id, 'reinstated', $old_status, 'waiting', $remarks, $performed_by);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'queue_entry_id' => $queue_entry_id,
                'old_status' => $old_status,
                'new_status' => 'waiting',
                'new_queue_number' => $newQueueNumber,
                'message' => 'Patient reinstated successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw new Exception("Failed to reinstate queue: " . $e->getMessage());
        }
    }
    
    /**
     * Generate next queue number for a service/type/priority combination
     * 
     * @param int $service_id Service identifier
     * @param string $queue_type Queue type
     * @param string $priority_level Priority level
     * @return int Next queue number
     */
    private function generateQueueNumber($service_id, $queue_type, $priority_level) {
        // Get max queue number for today for this service and type
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(MAX(queue_number), 0) as max_number
            FROM queue_entries 
            WHERE service_id = ? 
              AND queue_type = ?
              AND DATE(time_in) = CURDATE()
        ");
        
        $stmt->execute([$service_id, $queue_type]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result['max_number'] ?? 0) + 1;
    }
    
    /**
     * Log queue action to queue_logs table
     * 
     * @param int $queue_entry_id Queue entry identifier
     * @param string $action Action type
     * @param string $old_status Previous status
     * @param string $new_status New status
     * @param string $remarks Optional remarks
     * @param int $performed_by Employee ID
     */
    private function logQueueAction($queue_entry_id, $action, $old_status, $new_status, $remarks, $performed_by) {
        $stmt = $this->pdo->prepare("
            INSERT INTO queue_logs (
                queue_entry_id, action, old_status, new_status, 
                remarks, performed_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $queue_entry_id, $action, $old_status, $new_status,
            $remarks, $performed_by
        ]);
    }
    
    /**
     * Validate status transition is allowed
     * 
     * @param string $old_status Current status
     * @param string $new_status Target status
     * @throws Exception For invalid transitions
     */
    private function validateStatusTransition($old_status, $new_status) {
        $validTransitions = [
            'waiting' => ['in_progress', 'skipped', 'cancelled', 'no_show'],
            'in_progress' => ['done', 'skipped', 'cancelled'],
            'skipped' => ['waiting', 'cancelled'],
            'done' => [], // Generally final state
            'cancelled' => [], // Generally final state  
            'no_show' => ['waiting'] // Can be reinstated
        ];
        
        if (!isset($validTransitions[$old_status]) || 
            !in_array($new_status, $validTransitions[$old_status])) {
            throw new Exception("Invalid status transition from '$old_status' to '$new_status'");
        }
    }
    
    /**
     * Calculate estimated wait time for a queue entry
     * 
     * @param array $entry Current queue entry
     * @param array $allEntries All entries in the queue
     * @return int Estimated wait time in minutes
     */
    private function calculateEstimatedWait($entry, $allEntries) {
        if ($entry['status'] === 'in_progress') {
            return 0; // Currently being served
        }
        
        // Count entries ahead in queue with same or higher priority
        $ahead = 0;
        foreach ($allEntries as $other) {
            if ($other['queue_entry_id'] === $entry['queue_entry_id']) {
                break; // Found current entry, stop counting
            }
            
            $otherPriority = $this->getPriorityValue($other['priority_level']);
            $currentPriority = $this->getPriorityValue($entry['priority_level']);
            
            if ($otherPriority <= $currentPriority) {
                $ahead++;
            }
        }
        
        // Estimate 10 minutes per patient (configurable)
        return $ahead * 10;
    }
    
    /**
     * Get numeric value for priority level for comparison
     * 
     * @param string $priority Priority level
     * @return int Numeric priority value (lower = higher priority)
     */
    private function getPriorityValue($priority) {
        switch ($priority) {
            case 'emergency': return 1;
            case 'priority': return 2;
            case 'normal': return 3;
            default: return 3;
        }
    }
}

?>