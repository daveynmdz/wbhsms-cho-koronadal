<?php
// Simple test for staff_assignments.php functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing staff assignments functionality...\n";

try {
    // Include the same files that staff_assignments.php includes
    $root_path = dirname(__DIR__);
    require_once $root_path . '/config/db.php';
    require_once $root_path . '/utils/queue_management_service.php';
    
    echo "✓ Files included successfully\n";
    
    // Initialize service
    $queueService = new QueueManagementService($pdo);
    echo "✓ QueueManagementService initialized\n";
    
    // Test the exact call that's failing in staff_assignments.php
    $date = '2025-10-08';
    echo "Testing getAllStationsWithAssignments('$date')...\n";
    
    $stations = $queueService->getAllStationsWithAssignments($date);
    echo "✓ getAllStationsWithAssignments returned: " . count($stations) . " stations\n";
    
    // Test other critical methods
    $employees = $queueService->getActiveEmployees(1);
    echo "✓ getActiveEmployees returned: " . count($employees) . " employees\n";
    
    echo "\n🎉 All tests passed! The critical functionality is working.\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>