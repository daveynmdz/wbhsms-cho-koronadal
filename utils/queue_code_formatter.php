<?php

/**
 * Queue Code Formatter
 * Purpose: Provides consistent queue code formatting for patient displays
 * 
 * Converts system queue codes (DDMMYY-HHA-###) to patient-friendly format (HHM-###)
 * Example: 151024-08A-001 -> 08A-001
 */

if (!function_exists('formatQueueCodeForDisplay')) {
    /**
     * Format queue code for patient display
     * 
     * @param string $queueCode The full queue code (DDMMYY-HHA-###)
     * @return string The formatted display code (HHM-###)
     */
    function formatQueueCodeForDisplay($queueCode) {
        if (empty($queueCode)) {
            return '';
        }
        
        // Queue code format: DDMMYY-HHA-### where HH=hour, A=time slot letter, ###=sequence
        // We want to display: HHM-### (where M is the minute representation)
        
        $parts = explode('-', $queueCode);
        
        if (count($parts) >= 3) {
            // Extract HHA (hour + time slot) and ### (sequence)
            $timeSlot = $parts[1]; // HHA format
            $sequence = $parts[2]; // ### format
            
            // Convert HHA to HHM format for better user understanding
            // A=00-14min, B=15-29min, C=30-44min, D=45-59min
            $hour = substr($timeSlot, 0, 2);
            $slot = substr($timeSlot, 2, 1);
            
            $minuteMapping = [
                'A' => '0',
                'B' => '1', 
                'C' => '3',
                'D' => '4'
            ];
            
            $minute = $minuteMapping[$slot] ?? '0';
            
            return $hour . $minute . '-' . $sequence;
        }
        
        // Fallback: return original code if format is unexpected
        return $queueCode;
    }
}

if (!function_exists('formatQueueCodeForSystem')) {
    /**
     * Generate system queue code from components
     * 
     * @param string $date Date in Y-m-d format
     * @param int $hour Hour (0-23)
     * @param string $timeSlot Time slot letter (A-D)
     * @param int $sequence Sequence number
     * @return string The full system queue code (DDMMYY-HHA-###)
     */
    function formatQueueCodeForSystem($date, $hour, $timeSlot, $sequence) {
        $dateFormatted = date('dmy', strtotime($date));
        $hourFormatted = str_pad($hour, 2, '0', STR_PAD_LEFT);
        $sequenceFormatted = str_pad($sequence, 3, '0', STR_PAD_LEFT);
        
        return $dateFormatted . '-' . $hourFormatted . $timeSlot . '-' . $sequenceFormatted;
    }
}

if (!function_exists('parseQueueCode')) {
    /**
     * Parse queue code into components
     * 
     * @param string $queueCode The queue code to parse
     * @return array Components: ['date', 'hour', 'timeSlot', 'sequence', 'displayCode']
     */
    function parseQueueCode($queueCode) {
        $parts = explode('-', $queueCode);
        
        if (count($parts) >= 3) {
            $datePart = $parts[0]; // DDMMYY
            $timePart = $parts[1]; // HHA
            $sequence = $parts[2]; // ###
            
            // Parse date
            $day = substr($datePart, 0, 2);
            $month = substr($datePart, 2, 2);
            $year = '20' . substr($datePart, 4, 2);
            $date = $year . '-' . $month . '-' . $day;
            
            // Parse time
            $hour = substr($timePart, 0, 2);
            $timeSlot = substr($timePart, 2, 1);
            
            return [
                'date' => $date,
                'hour' => intval($hour),
                'timeSlot' => $timeSlot,
                'sequence' => intval($sequence),
                'displayCode' => formatQueueCodeForDisplay($queueCode),
                'fullCode' => $queueCode
            ];
        }
        
        return [
            'date' => null,
            'hour' => null,
            'timeSlot' => null,
            'sequence' => null,
            'displayCode' => $queueCode,
            'fullCode' => $queueCode
        ];
    }
}