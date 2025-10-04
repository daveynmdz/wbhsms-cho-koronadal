<?php
/**
 * Automatic Status Updater
 * 
 * This script handles automatic status updates for appointments and referrals
 * based on business rules and time-based logic.
 */

// Include database connection
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';

class AutomaticStatusUpdater {
    private $conn;
    
    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }
    
    /**
     * Update expired appointments from 'confirmed' to 'cancelled'
     * An appointment is considered expired if the scheduled date/time has passed
     */
    public function updateExpiredAppointments() {
        try {
            // Get current datetime
            $current_datetime = date('Y-m-d H:i:s');
            
            // Update appointments where scheduled datetime has passed and status is still 'confirmed'
            $sql = "UPDATE appointments 
                    SET status = 'cancelled', 
                        cancellation_reason = 'Automatically cancelled - appointment time has passed',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE status = 'confirmed' 
                    AND CONCAT(scheduled_date, ' ', scheduled_time) < ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $current_datetime);
            $stmt->execute();
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            // Log the automatic cancellations
            if ($affected_rows > 0) {
                $this->logAutomaticUpdates('appointments', 'expired_cancelled', $affected_rows);
            }
            
            return [
                'success' => true,
                'appointments_updated' => $affected_rows,
                'message' => "Updated {$affected_rows} expired appointments"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update referrals that have been used for appointments
     * When a referral is used to book an appointment, mark it as 'accepted'
     */
    public function updateUsedReferrals() {
        try {
            // Update referrals that are linked to appointments but still marked as 'active'
            $sql = "UPDATE referrals r
                    INNER JOIN appointments a ON r.referral_id = a.referral_id
                    SET r.status = 'accepted',
                        r.updated_at = CURRENT_TIMESTAMP
                    WHERE r.status = 'active'
                    AND a.status IN ('confirmed', 'completed')";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            // Log the automatic updates
            if ($affected_rows > 0) {
                $this->logAutomaticUpdates('referrals', 'used_accepted', $affected_rows);
            }
            
            return [
                'success' => true,
                'referrals_updated' => $affected_rows,
                'message' => "Updated {$affected_rows} used referrals"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update expired referrals that haven't been used
     * Mark referrals as 'expired' if they're older than 30 days and still 'active'
     */
    public function updateExpiredReferrals() {
        try {
            // Set referral expiry period (30 days)
            $expiry_days = 30;
            $expiry_date = date('Y-m-d H:i:s', strtotime("-{$expiry_days} days"));
            
            // Update referrals that are older than expiry period and still active
            // Note: Using 'cancelled' status since 'expired' is not in the enum
            $sql = "UPDATE referrals 
                    SET status = 'cancelled',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE status = 'active' 
                    AND referral_date < ?
                    AND referral_id NOT IN (
                        SELECT DISTINCT referral_id 
                        FROM appointments 
                        WHERE referral_id IS NOT NULL 
                        AND status IN ('confirmed', 'completed')
                    )";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("s", $expiry_date);
            $stmt->execute();
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            // Log the automatic updates
            if ($affected_rows > 0) {
                $this->logAutomaticUpdates('referrals', 'expired', $affected_rows);
            }
            
            return [
                'success' => true,
                'referrals_updated' => $affected_rows,
                'message' => "Updated {$affected_rows} expired referrals"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Run all automatic status updates
     */
    public function runAllUpdates() {
        $results = [
            'appointments' => $this->updateExpiredAppointments(),
            'used_referrals' => $this->updateUsedReferrals(),
            'expired_referrals' => $this->updateExpiredReferrals()
        ];
        
        $total_updates = 0;
        $errors = [];
        
        foreach ($results as $type => $result) {
            if ($result['success']) {
                $key = $type === 'appointments' ? 'appointments_updated' : 'referrals_updated';
                $total_updates += $result[$key] ?? 0;
            } else {
                $errors[] = "{$type}: " . $result['error'];
            }
        }
        
        return [
            'success' => empty($errors),
            'total_updates' => $total_updates,
            'details' => $results,
            'errors' => $errors
        ];
    }
    
    /**
     * Log automatic updates for audit purposes
     */
    private function logAutomaticUpdates($table, $action, $count) {
        try {
            // Create a simple log entry (you can enhance this based on your logging needs)
            $log_message = "Automatic update: {$table} - {$action} - {$count} records updated";
            error_log($log_message);
            
            // You could also insert into a dedicated log table if needed
            // Example:
            // $sql = "INSERT INTO system_logs (action, details, created_at) VALUES (?, ?, NOW())";
            // $stmt = $this->conn->prepare($sql);
            // $stmt->bind_param("ss", $action, $log_message);
            // $stmt->execute();
            // $stmt->close();
            
        } catch (Exception $e) {
            error_log("Failed to log automatic update: " . $e->getMessage());
        }
    }
}

// If this script is called directly (not included), run the updates
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    $updater = new AutomaticStatusUpdater($conn);
    $result = $updater->runAllUpdates();
    
    if ($result['success']) {
        echo "Status updates completed successfully. Total updates: " . $result['total_updates'] . "\n";
        if ($result['total_updates'] > 0) {
            foreach ($result['details'] as $type => $details) {
                echo "- {$type}: " . $details['message'] . "\n";
            }
        }
    } else {
        echo "Status updates completed with errors:\n";
        foreach ($result['errors'] as $error) {
            echo "- {$error}\n";
        }
    }
}

?>