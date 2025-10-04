<?php
/**
 * Cron Job Script for Automatic Status Updates
 * 
 * This script is designed to be run via cron job (scheduled task)
 * to automatically update appointment and referral statuses.
 * 
 * Recommended cron schedule:
 * - Every hour: 0 [asterisk] [asterisk] [asterisk] [asterisk] php /path/to/cron_status_updater.php
 * - Every 30 minutes: [asterisk]/30 [asterisk] [asterisk] [asterisk] [asterisk] php /path/to/cron_status_updater.php
 * - Daily at 6 AM: 0 6 [asterisk] [asterisk] [asterisk] php /path/to/cron_status_updater.php
 */

// Set error reporting for cron environment
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in cron
ini_set('log_errors', 1);     // Log errors instead

// Include required files
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/automatic_status_updater.php';

// Function to log cron job execution
function logCronExecution($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] [{$level}] CRON STATUS UPDATER: {$message}\n";
    
    // Log to a specific file (you may need to create this directory)
    $log_file = dirname(__DIR__) . '/logs/status_updater_cron.log';
    $log_dir = dirname($log_file);
    
    // Create log directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Write to log file
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    // Also log to system log
    error_log($log_message);
}

try {
    logCronExecution("Starting automatic status updates");
    
    // Create updater instance
    $updater = new AutomaticStatusUpdater($conn);
    
    // Run all updates
    $result = $updater->runAllUpdates();
    
    if ($result['success']) {
        $message = "Status updates completed successfully. Total updates: " . $result['total_updates'];
        logCronExecution($message);
        
        // Log details if there were updates
        if ($result['total_updates'] > 0) {
            foreach ($result['details'] as $type => $details) {
                if ($details['success']) {
                    logCronExecution("- {$type}: " . $details['message']);
                }
            }
        }
    } else {
        $message = "Status updates completed with errors";
        logCronExecution($message, 'ERROR');
        
        foreach ($result['errors'] as $error) {
            logCronExecution("- {$error}", 'ERROR');
        }
    }
    
    // Close database connection
    if (isset($conn)) {
        $conn->close();
    }
    
    logCronExecution("Cron job completed");
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    $error_message = "Fatal error in cron job: " . $e->getMessage();
    logCronExecution($error_message, 'FATAL');
    
    // Close database connection if it exists
    if (isset($conn)) {
        $conn->close();
    }
    
    // Exit with error code
    exit(1);
}
?>