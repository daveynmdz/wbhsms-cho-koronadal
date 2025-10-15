<?php
/**
 * Queue Code Formatter Helper
 * Provides standardized queue code formatting for public displays and patient interfaces
 */

// Convert full queue code to simplified patient display format (HHM-###)
function formatQueueCodeForPublicDisplay($full_queue_code)
{
    if (empty($full_queue_code)) return '';
    
    // Handle QueueManagementService format: DDMMYY-08A-001
    if (preg_match('/\d{6}-(\d{2}[AP])-(\d{3})/', $full_queue_code, $matches)) {
        $time_slot = $matches[1]; // e.g., "08A"
        $sequence = $matches[2];  // e.g., "001"
        
        // Convert time slot to simplified format
        $hour = substr($time_slot, 0, 2);
        $period = substr($time_slot, 2, 1);
        $simplified_time = $hour . $period . 'M'; // e.g., "08AM", "01PM"
        
        return $simplified_time . '-' . $sequence;
    }
    
    // Handle legacy CHO format: CHO-YYYYMMDD-XXX
    if (preg_match('/CHO-\d{8}-(\d{3})/', $full_queue_code, $matches)) {
        $sequence = $matches[1];
        $current_hour = date('H');
        $period = $current_hour < 12 ? 'A' : 'P';
        $display_hour = str_pad($current_hour > 12 ? $current_hour - 12 : ($current_hour == 0 ? 12 : $current_hour), 2, '0', STR_PAD_LEFT);
        
        return $display_hour . $period . 'M-' . $sequence;
    }
    
    // If no pattern matches, return as-is
    return $full_queue_code;
}

// Convert full queue code to simplified patient format for queue status pages
function formatQueueCodeForPatient($full_queue_code)
{
    return formatQueueCodeForPublicDisplay($full_queue_code);
}

// Get the admin/staff view of full queue code (for internal use)
function getFullQueueCode($full_queue_code)
{
    return $full_queue_code;
}
?>