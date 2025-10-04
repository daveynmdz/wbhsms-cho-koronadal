<?php
/**
 * Appointment Logging Helper
 * Provides utility functions for logging appointment actions to appointment_logs table
 */

class AppointmentLogger {
    
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Log appointment action to appointment_logs table
     * 
     * @param int $appointment_id Appointment identifier
     * @param int $patient_id Patient identifier
     * @param string $action Action type (created, confirmed, cancelled, completed, rescheduled, updated)
     * @param string $old_status Previous appointment status
     * @param string $new_status New appointment status
     * @param string $old_scheduled_date Previous scheduled date (for rescheduling)
     * @param string $new_scheduled_date New scheduled date
     * @param string $old_scheduled_time Previous scheduled time (for rescheduling)
     * @param string $new_scheduled_time New scheduled time
     * @param string $reason Reason for the action
     * @param string $notes Additional notes
     * @param string $created_by_type Who initiated the action (patient, employee, system)
     * @param int $created_by_id ID of the person who initiated the action
     * @return bool Success status
     */
    public function logAppointmentAction(
        $appointment_id, 
        $patient_id, 
        $action, 
        $old_status = null, 
        $new_status = null, 
        $old_scheduled_date = null, 
        $new_scheduled_date = null, 
        $old_scheduled_time = null, 
        $new_scheduled_time = null, 
        $reason = null, 
        $notes = null, 
        $created_by_type = 'patient', 
        $created_by_id = null
    ) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO appointment_logs (
                    appointment_id, patient_id, action, old_status, new_status,
                    old_scheduled_date, new_scheduled_date, old_scheduled_time, new_scheduled_time,
                    reason, notes, created_by_type, created_by_id, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            
            $stmt->bind_param("iisssssssssiss", 
                $appointment_id, $patient_id, $action, $old_status, $new_status,
                $old_scheduled_date, $new_scheduled_date, $old_scheduled_time, $new_scheduled_time,
                $reason, $notes, $created_by_type, $created_by_id, $ip_address, $user_agent
            );
            
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to log appointment action: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log appointment creation
     * 
     * @param int $appointment_id
     * @param int $patient_id
     * @param string $scheduled_date
     * @param string $scheduled_time
     * @param string $created_by_type
     * @param int $created_by_id
     * @return bool
     */
    public function logAppointmentCreation($appointment_id, $patient_id, $scheduled_date, $scheduled_time, $created_by_type = 'patient', $created_by_id = null) {
        return $this->logAppointmentAction(
            $appointment_id,
            $patient_id,
            'created',
            null,
            'confirmed',
            null,
            $scheduled_date,
            null,
            $scheduled_time,
            'Appointment created',
            null,
            $created_by_type,
            $created_by_id
        );
    }
    
    /**
     * Log appointment rescheduling
     * 
     * @param int $appointment_id
     * @param int $patient_id
     * @param string $old_date
     * @param string $new_date
     * @param string $old_time
     * @param string $new_time
     * @param string $reason
     * @param string $created_by_type
     * @param int $created_by_id
     * @return bool
     */
    public function logAppointmentReschedule($appointment_id, $patient_id, $old_date, $new_date, $old_time, $new_time, $reason = null, $created_by_type = 'patient', $created_by_id = null) {
        return $this->logAppointmentAction(
            $appointment_id,
            $patient_id,
            'rescheduled',
            'confirmed',
            'confirmed',
            $old_date,
            $new_date,
            $old_time,
            $new_time,
            $reason ?? 'Appointment rescheduled',
            null,
            $created_by_type,
            $created_by_id
        );
    }
    
    /**
     * Log appointment update (non-scheduling changes)
     * 
     * @param int $appointment_id
     * @param int $patient_id
     * @param string $reason
     * @param string $notes
     * @param string $created_by_type
     * @param int $created_by_id
     * @return bool
     */
    public function logAppointmentUpdate($appointment_id, $patient_id, $reason = null, $notes = null, $created_by_type = 'patient', $created_by_id = null) {
        return $this->logAppointmentAction(
            $appointment_id,
            $patient_id,
            'updated',
            'confirmed',
            'confirmed',
            null,
            null,
            null,
            null,
            $reason ?? 'Appointment updated',
            $notes,
            $created_by_type,
            $created_by_id
        );
    }
    
    /**
     * Log appointment completion
     * 
     * @param int $appointment_id
     * @param int $patient_id
     * @param string $notes
     * @param string $created_by_type
     * @param int $created_by_id
     * @return bool
     */
    public function logAppointmentCompletion($appointment_id, $patient_id, $notes = null, $created_by_type = 'employee', $created_by_id = null) {
        return $this->logAppointmentAction(
            $appointment_id,
            $patient_id,
            'completed',
            'confirmed',
            'completed',
            null,
            null,
            null,
            null,
            'Appointment completed',
            $notes,
            $created_by_type,
            $created_by_id
        );
    }
}

?>