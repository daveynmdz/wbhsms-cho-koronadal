<?php
// utils/staff_assignment.php - Backward compatibility wrapper
// This file provides compatibility for old function names used by dashboard files

require_once __DIR__ . '/queue_management_service.php';

// Initialize the service if database connection exists
if (isset($pdo)) {
    $queueService = new QueueManagementService($pdo);
} elseif (isset($conn)) {
    // Fallback to MySQLi - but QueueManagementService expects PDO
    // We need to use the global PDO connection
    global $pdo;
    if ($pdo) {
        $queueService = new QueueManagementService($pdo);
    }
}

/**
 * Get staff assignment for an employee on a specific date
 * Compatibility function for dashboard files
 */
function getStaffAssignment($employee_id, $date = null) {
    global $queueService, $pdo, $conn;
    
    // Try to get PDO connection first
    if (!$queueService) {
        if (isset($pdo) && $pdo) {
            $queueService = new QueueManagementService($pdo);
        } elseif (isset($conn) && $conn) {
            // Fallback: need to create PDO from MySQLi config
            try {
                require_once __DIR__ . '/../config/db.php';
                if (isset($pdo) && $pdo) {
                    $queueService = new QueueManagementService($pdo);
                }
            } catch (Exception $e) {
                error_log('Staff assignment: Failed to get PDO connection: ' . $e->getMessage());
                return null;
            }
        }
    }
    
    if (!$queueService) {
        error_log('Staff assignment: No database connection available');
        return null;
    }
    
    if ($date === null) {
        $date = date('Y-m-d');
    }
    
    try {
        return $queueService->getActiveStationByEmployee($employee_id, $date);
    } catch (Exception $e) {
        error_log('Staff assignment error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get all assignments for a specific date
 * Compatibility function for admin staff assignments page
 */
function getAllAssignmentsForDate($pdo, $date) {
    $queueService = new QueueManagementService($pdo);
    return $queueService->getAllStationsWithAssignments($date);
}

/**
 * Assign staff to station
 * Compatibility function with new signature
 */
function assignStaffToStation($conn, $employee_id, $station_type, $station_number, $assigned_date, $shift_start = '08:00:00', $shift_end = '17:00:00', $assigned_by = null) {
    global $pdo;
    $queueService = new QueueManagementService($pdo);
    
    // Find station ID based on type and number (for backward compatibility)
    $stmt = $conn->prepare("SELECT station_id FROM stations WHERE station_type = ? AND station_number = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("si", $station_type, $station_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $station = $result->fetch_assoc();
    
    if (!$station) {
        return false;
    }
    
    $result = $queueService->assignEmployeeToStation(
        $employee_id, 
        $station['station_id'], 
        $assigned_date, 
        'permanent', // Default to permanent
        $shift_start, 
        $shift_end, 
        $assigned_by
    );
    
    return $result['success'] ?? false;
}

/**
 * Unassign staff from station
 * Compatibility function
 */
function unassignStaffFromStation($conn, $employee_id, $station_type, $station_number, $assigned_date) {
    global $pdo;
    $queueService = new QueueManagementService($pdo);
    
    // Find station ID based on type and number
    $stmt = $conn->prepare("SELECT station_id FROM stations WHERE station_type = ? AND station_number = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("si", $station_type, $station_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $station = $result->fetch_assoc();
    
    if (!$station) {
        return false;
    }
    
    $result = $queueService->removeEmployeeAssignment(
        $station['station_id'], 
        $assigned_date, 
        'end_assignment'
    );
    
    return $result['success'] ?? false;
}

/**
 * Get employee assignment history
 * New function for enhanced functionality
 */
function getEmployeeAssignmentHistory($employee_id, $limit = 10) {
    global $queueService, $pdo;
    
    if (!$queueService && $pdo) {
        $queueService = new QueueManagementService($pdo);
    }
    
    if (!$queueService) {
        return [];
    }
    
    return $queueService->getEmployeeAssignmentHistory($employee_id, $limit);
}

/**
 * Get station assignment history
 * New function for enhanced functionality
 */
function getStationAssignmentHistory($station_id, $limit = 10) {
    global $queueService, $pdo;
    
    if (!$queueService && $pdo) {
        $queueService = new QueueManagementService($pdo);
    }
    
    if (!$queueService) {
        return [];
    }
    
    return $queueService->getStationAssignmentHistory($station_id, $limit);
}
?>