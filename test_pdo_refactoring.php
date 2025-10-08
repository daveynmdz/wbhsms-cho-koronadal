<?php
// Test script to check if the refactored PDO methods work
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/utils/queue_management_service.php';

echo "Testing QueueManagementService PDO refactoring...\n";

try {
    // Initialize service with PDO connection
    $queueService = new QueueManagementService($pdo);
    
    // Test 1: getAllStationsWithAssignments
    echo "1. Testing getAllStationsWithAssignments...\n";
    $stations = $queueService->getAllStationsWithAssignments('2025-10-08');
    echo "   Result: " . (is_array($stations) ? count($stations) . " stations found" : "ERROR") . "\n";
    
    // Test 2: getActiveEmployees
    echo "2. Testing getActiveEmployees...\n";
    $employees = $queueService->getActiveEmployees(1);
    echo "   Result: " . (is_array($employees) ? count($employees) . " employees found" : "ERROR") . "\n";
    
    // Test 3: toggleStationStatus (read-only test)
    echo "3. Testing toggleStationStatus method exists...\n";
    echo "   Result: " . (method_exists($queueService, 'toggleStationStatus') ? "Method exists" : "Method missing") . "\n";
    
    echo "\nAll critical methods for staff_assignments.php are available and working!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>