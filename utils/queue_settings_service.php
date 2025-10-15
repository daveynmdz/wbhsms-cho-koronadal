<?php
/**
 * Queue Settings Service
 * CHO Koronadal Queue Management System
 * 
 * Purpose: Manage queue system settings and overrides
 */

class QueueSettingsService {
    private $pdo;
    private static $cache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get a queue setting value
     */
    public function getSetting($key, $default = 'false') {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM queue_settings WHERE setting_key = ? AND enabled = 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $value = $result ? $result['setting_value'] : $default;
            
            // Cache the result
            self::$cache[$key] = $value;
            
            return $value;
        } catch (Exception $e) {
            error_log("Queue Settings Error: " . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Update a queue setting
     */
    public function updateSetting($key, $value) {
        try {
            // Clear cache
            unset(self::$cache[$key]);
            
            $stmt = $this->pdo->prepare("
                UPDATE queue_settings 
                SET setting_value = ?, updated_at = NOW() 
                WHERE setting_key = ?
            ");
            
            $result = $stmt->execute([$value, $key]);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => "Setting '$key' updated to '$value'"];
            } else {
                // Setting doesn't exist, create it
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO queue_settings (setting_key, setting_value, enabled) 
                    VALUES (?, ?, 1)
                ");
                $insertStmt->execute([$key, $value]);
                return ['success' => true, 'message' => "Setting '$key' created with value '$value'"];
            }
        } catch (Exception $e) {
            error_log("Queue Settings Update Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update setting: ' . $e->getMessage()];
        }
    }
    

    
    /**
     * Check if queueing is currently allowed
     */
    public function isQueueingAllowed() {
        $testingMode = $this->getSetting('testing_mode');
        $ignoreTimeConstraints = $this->getSetting('ignore_time_constraints');
        $queueOverrideMode = $this->getSetting('queue_override_mode');
        
        // If any override is active, allow queueing
        if ($testingMode === 'true' || $ignoreTimeConstraints === 'true' || $queueOverrideMode === 'true') {
            return true;
        }
        
        // Otherwise, check normal business rules (time constraints, etc.)
        return $this->checkBusinessHours();
    }
    
    /**
     * Check if current time is within business hours
     */
    private function checkBusinessHours() {
        $currentTime = date('H:i:s');
        $currentDay = date('w'); // 0 = Sunday, 6 = Saturday
        
        // CHO business hours: Monday-Friday 7:00 AM - 5:00 PM
        $startTime = '07:00:00';
        $endTime = '17:00:00';
        
        // Check if it's a weekday
        if ($currentDay >= 1 && $currentDay <= 5) {
            return ($currentTime >= $startTime && $currentTime <= $endTime);
        }
        
        return false; // Weekend
    }
    
    /**
     * Check if all stations should be forced open
     */
    public function shouldForceStationsOpen() {
        return $this->getSetting('force_all_stations_open') === 'true';
    }
    

    
    /**
     * Toggle a boolean setting - Updated to return proper response
     */
    public function toggleSetting($key) {
        try {
            $currentValue = $this->getSetting($key, '0');
            $newValue = ($currentValue === '1') ? '0' : '1';
            
            return $this->updateSetting($key, $newValue);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to toggle setting: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all settings as array
     */
    public function getAllSettings() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM queue_settings WHERE enabled = 1");
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if testing mode is enabled
     */
    public function isTestingMode() {
        return $this->getSetting('testing_mode', '0') === '1';
    }
    
    /**
     * Check if time constraints should be ignored
     */
    public function shouldIgnoreTimeConstraints() {
        return $this->getSetting('ignore_time_constraints', '0') === '1';
    }
    
    /**
     * Check if queue override mode is enabled
     */
    public function isQueueOverrideModeEnabled() {
        return $this->getSetting('queue_override_mode', '0') === '1';
    }
    
    /**
     * Check if all stations should be forced open - Updated
     */
    public function shouldForceAllStationsOpen() {
        return $this->getSetting('force_all_stations_open', '0') === '1';
    }
    
    /**
     * Initialize default settings if they don't exist
     */
    public function initializeDefaults() {
        $defaults = [
            'testing_mode' => '0',
            'ignore_time_constraints' => '0',
            'queue_override_mode' => '0',
            'force_all_stations_open' => '0',
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
        foreach ($defaults as $key => $value) {
            // Only set if doesn't exist
            $existing = $this->getSetting($key, null);
            if ($existing === null) {
                $this->updateSetting($key, $value);
            }
        }
    }
    
    /**
     * Get updated system status for UI
     */
    public function getSystemStatus() {
        return [
            'testing_mode' => $this->getSetting('testing_mode', '0'),
            'ignore_time_constraints' => $this->getSetting('ignore_time_constraints', '0'),
            'queue_override_mode' => $this->getSetting('queue_override_mode', '0'),
            'force_all_stations_open' => $this->getSetting('force_all_stations_open', '0'),
            'last_updated' => $this->getSetting('last_updated', date('Y-m-d H:i:s'))
        ];
    }
}
?>