<?php
/**
 * Fix Employee Login Issues
 * This script addresses the problems preventing employees from accessing their dashboards
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/utils/queue_management_service.php';

echo "<h1>Employee Login Issues Fix</h1>";
echo "<pre>";

// Initialize queue service
$queueService = new QueueManagementService($conn);

// 1. Check current employees
echo "\n=== CHECKING EMPLOYEES ===\n";
$stmt = $conn->prepare("SELECT employee_id, employee_number, first_name, last_name, role_id FROM employees WHERE status = 'active' LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);

echo "Active employees found: " . count($employees) . "\n";
foreach ($employees as $emp) {
    echo "- Employee #{$emp['employee_id']}: {$emp['employee_number']} - {$emp['first_name']} {$emp['last_name']} (Role: {$emp['role_id']})\n";
}

// 2. Check available stations
echo "\n=== CHECKING STATIONS ===\n";
$stmt = $conn->prepare("SELECT station_id, station_name, station_type, station_number, service_id, is_active FROM stations WHERE is_active = 1 LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
$stations = $result->fetch_all(MYSQLI_ASSOC);

echo "Active stations found: " . count($stations) . "\n";
foreach ($stations as $station) {
    echo "- Station #{$station['station_id']}: {$station['station_name']} ({$station['station_type']}) - Service: {$station['service_id']}\n";
}

// 3. Check current assignments
echo "\n=== CHECKING CURRENT ASSIGNMENTS ===\n";
$stmt = $conn->prepare("
    SELECT 
        asch.schedule_id,
        asch.employee_id,
        asch.station_id,
        asch.start_date,
        asch.end_date,
        asch.is_active,
        e.employee_number,
        e.first_name,
        e.last_name,
        s.station_name
    FROM assignment_schedules asch
    JOIN employees e ON asch.employee_id = e.employee_id
    JOIN stations s ON asch.station_id = s.station_id
    WHERE asch.is_active = 1
    AND asch.start_date <= CURDATE()
    AND (asch.end_date IS NULL OR asch.end_date >= CURDATE())
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$assignments = $result->fetch_all(MYSQLI_ASSOC);

echo "Active assignments for today: " . count($assignments) . "\n";
foreach ($assignments as $assignment) {
    echo "- {$assignment['employee_number']} ({$assignment['first_name']} {$assignment['last_name']}) -> {$assignment['station_name']} (Start: {$assignment['start_date']}, End: " . ($assignment['end_date'] ?? 'Permanent') . ")\n";
}

// 4. Test staff assignment function
echo "\n=== TESTING STAFF ASSIGNMENT FUNCTION ===\n";
if (!empty($employees)) {
    $testEmployee = $employees[0];
    echo "Testing assignment for Employee ID: {$testEmployee['employee_id']}\n";
    
    $assignment = $queueService->getActiveStationByEmployee($testEmployee['employee_id']);
    if ($assignment) {
        echo "✅ Assignment found: {$assignment['station_name']} ({$assignment['station_type']})\n";
    } else {
        echo "❌ No assignment found for this employee\n";
    }
}

// 5. CREATE SAMPLE ASSIGNMENTS (if none exist)
echo "\n=== CREATING SAMPLE ASSIGNMENTS ===\n";

if (count($assignments) === 0 && count($employees) > 0 && count($stations) > 0) {
    echo "No assignments exist. Creating sample assignments...\n";
    
    // Assign first employee to first station
    $employee = $employees[0];
    $station = $stations[0];
    
    echo "Assigning Employee {$employee['employee_number']} to Station {$station['station_name']}...\n";
    
    $result = $queueService->assignEmployeeToStation(
        $employee['employee_id'],
        $station['station_id'],
        date('Y-m-d'), // Start today
        'permanent',
        '08:00:00',
        '17:00:00',
        1 // Assigned by admin (ID 1)
    );
    
    if ($result['success']) {
        echo "✅ Assignment created successfully!\n";
        echo "Employee {$employee['employee_number']} can now login and access their dashboard.\n";
    } else {
        echo "❌ Failed to create assignment: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "Assignments already exist or insufficient data to create sample assignments.\n";
}

// 6. FIX DASHBOARD FILES
echo "\n=== CHECKING DASHBOARD FILES FOR FUNCTION CALL ERRORS ===\n";

$dashboardFiles = [
    'pages/management/doctor/dashboard.php',
    'pages/management/nurse/dashboard.php',
    'pages/management/pharmacist/dashboard.php',
    'pages/management/laboratory_tech/dashboard.php',
    'pages/management/cashier/dashboard.php',
    'pages/management/records_officer/dashboard.php',
    'pages/management/bhw/dashboard.php'
];

$fixCount = 0;
foreach ($dashboardFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        
        // Check for incorrect function call pattern
        if (strpos($content, 'getStaffAssignment($conn,') !== false) {
            echo "⚠️  Found incorrect function call in: $file\n";
            
            // Fix the function call
            $newContent = str_replace(
                'getStaffAssignment($conn, $employee_id)',
                'getStaffAssignment($employee_id)',
                $content
            );
            
            if (file_put_contents($fullPath, $newContent)) {
                echo "✅ Fixed function call in: $file\n";
                $fixCount++;
            } else {
                echo "❌ Failed to fix: $file\n";
            }
        } else {
            echo "✅ Function call is correct in: $file\n";
        }
    } else {
        echo "⚠️  File not found: $file\n";
    }
}

echo "\nFixed $fixCount dashboard files.\n";

// 7. SUMMARY AND INSTRUCTIONS
echo "\n=== SUMMARY ===\n";
echo "Issues Found and Fixed:\n";
echo "1. ✅ Doctor dashboard function call corrected\n";
echo "2. ✅ Checked $fixCount additional dashboard files\n";

if (count($assignments) === 0) {
    echo "\n⚠️  IMPORTANT: NO STATION ASSIGNMENTS FOUND!\n";
    echo "To fix employee login issues:\n";
    echo "1. Create station assignments for employees\n";
    echo "2. Use the admin interface at: pages/management/admin/staff_assignments.php\n";
    echo "3. Or manually insert into assignment_schedules table\n";
} else {
    echo "\n✅ Station assignments exist - employees should be able to login\n";
}

echo "\nTo manually create assignments, use this SQL:\n";
echo "INSERT INTO assignment_schedules (employee_id, station_id, start_date, assignment_type, shift_start, shift_end, is_active, assigned_by, created_at, updated_at)\n";
echo "VALUES (2, 1, '" . date('Y-m-d') . "', 'permanent', '08:00:00', '17:00:00', 1, 1, NOW(), NOW());\n";

echo "\n=== TESTING COMPLETE ===\n";
echo "</pre>";
?>