<?php
/**
 * Create Station Assignments for Employees
 * Run this script to assign employees to stations so they can access their dashboards
 */

// Include database connection
$root_path = __DIR__;
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

echo "<!DOCTYPE html>";
echo "<html><head><title>Create Station Assignments</title><style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;}</style></head><body>";

echo "<h1>Station Assignment Creator</h1>";

// Initialize queue service
$queueService = new QueueManagementService($conn);

// Handle form submission
if ($_POST && isset($_POST['employee_id']) && isset($_POST['station_id'])) {
    $employee_id = intval($_POST['employee_id']);
    $station_id = intval($_POST['station_id']);
    $start_date = $_POST['start_date'] ?: date('Y-m-d');
    
    echo "<div style='background:#f0f0f0;padding:15px;margin:15px 0;border-radius:5px;'>";
    echo "<h3>Creating Assignment...</h3>";
    
    $result = $queueService->assignEmployeeToStation(
        $employee_id,
        $station_id,
        $start_date,
        'permanent',
        '08:00:00',
        '17:00:00',
        1 // Assigned by admin (ID 1)
    );
    
    if ($result['success']) {
        echo "<p class='success'>✅ Success! Employee has been assigned to station.</p>";
    } else {
        echo "<p class='error'>❌ Error: " . htmlspecialchars($result['error'] ?? 'Failed to create assignment') . "</p>";
    }
    echo "</div>";
}

// Get all active employees
$stmt = $conn->prepare("SELECT employee_id, employee_number, first_name, last_name, role_id FROM employees WHERE status = 'active' ORDER BY employee_number");
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all roles
$stmt = $conn->prepare("SELECT role_id, role_name FROM roles");
$stmt->execute();
$roles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$roleMap = [];
foreach ($roles as $role) {
    $roleMap[$role['role_id']] = $role['role_name'];
}

// Get all active stations
$stmt = $conn->prepare("SELECT station_id, station_name, station_type, station_number, service_id FROM stations WHERE is_active = 1 ORDER BY station_type, station_number");
$stmt->execute();
$stations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get current assignments
$stmt = $conn->prepare("
    SELECT 
        asch.schedule_id,
        asch.employee_id,
        asch.station_id,
        asch.start_date,
        asch.end_date,
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
    ORDER BY e.employee_number
");
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<h2>Current Assignments</h2>";
if (count($assignments) > 0) {
    echo "<table>";
    echo "<tr><th>Employee</th><th>Name</th><th>Station</th><th>Start Date</th><th>End Date</th></tr>";
    foreach ($assignments as $assignment) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($assignment['employee_number']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['station_name']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['start_date']) . "</td>";
        echo "<td>" . htmlspecialchars($assignment['end_date'] ?: 'Permanent') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No current assignments found! This is why employees cannot access their dashboards.</p>";
}

echo "<h2>Create New Assignment</h2>";
echo "<form method='post' style='background:#f9f9f9;padding:20px;border-radius:5px;'>";
echo "<table style='border:none;'>";
echo "<tr><td style='border:none;'><label for='employee_id'>Employee:</label></td>";
echo "<td style='border:none;'><select name='employee_id' id='employee_id' required style='width:100%;padding:5px;'>";
echo "<option value=''>Select Employee</option>";
foreach ($employees as $emp) {
    $roleName = $roleMap[$emp['role_id']] ?? 'Unknown';
    echo "<option value='{$emp['employee_id']}'>{$emp['employee_number']} - {$emp['first_name']} {$emp['last_name']} ({$roleName})</option>";
}
echo "</select></td></tr>";

echo "<tr><td style='border:none;'><label for='station_id'>Station:</label></td>";
echo "<td style='border:none;'><select name='station_id' id='station_id' required style='width:100%;padding:5px;'>";
echo "<option value=''>Select Station</option>";
foreach ($stations as $station) {
    echo "<option value='{$station['station_id']}'>{$station['station_name']} ({$station['station_type']})</option>";
}
echo "</select></td></tr>";

echo "<tr><td style='border:none;'><label for='start_date'>Start Date:</label></td>";
echo "<td style='border:none;'><input type='date' name='start_date' id='start_date' value='" . date('Y-m-d') . "' style='width:100%;padding:5px;'></td></tr>";

echo "<tr><td colspan='2' style='border:none;'><input type='submit' value='Create Assignment' style='background:#28a745;color:white;padding:10px 20px;border:none;border-radius:5px;cursor:pointer;'></td></tr>";
echo "</table>";
echo "</form>";

echo "<h2>Available Employees</h2>";
echo "<table>";
echo "<tr><th>Employee Number</th><th>Name</th><th>Role</th><th>Has Assignment</th></tr>";
foreach ($employees as $emp) {
    $hasAssignment = false;
    foreach ($assignments as $assignment) {
        if ($assignment['employee_id'] == $emp['employee_id']) {
            $hasAssignment = true;
            break;
        }
    }
    $roleName = $roleMap[$emp['role_id']] ?? 'Unknown';
    $status = $hasAssignment ? "<span class='success'>✅ Assigned</span>" : "<span class='error'>❌ No Assignment</span>";
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($emp['employee_number']) . "</td>";
    echo "<td>" . htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) . "</td>";
    echo "<td>" . htmlspecialchars($roleName) . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Available Stations</h2>";
echo "<table>";
echo "<tr><th>Station ID</th><th>Station Name</th><th>Type</th><th>Number</th></tr>";
foreach ($stations as $station) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($station['station_id']) . "</td>";
    echo "<td>" . htmlspecialchars($station['station_name']) . "</td>";
    echo "<td>" . htmlspecialchars($station['station_type']) . "</td>";
    echo "<td>" . htmlspecialchars($station['station_number']) . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<div style='background:#e7f3ff;padding:15px;margin:20px 0;border-radius:5px;'>";
echo "<h3>Quick Fix Instructions:</h3>";
echo "<ol>";
echo "<li><strong>Identify Employees:</strong> Look at the 'Available Employees' table above to see who needs assignments</li>";
echo "<li><strong>Create Assignments:</strong> Use the form above to assign employees to appropriate stations</li>";
echo "<li><strong>Test Login:</strong> After creating assignments, employees should be able to access their dashboards</li>";
echo "<li><strong>Role Matching:</strong> Make sure employees are assigned to stations that match their roles (doctors to consultation stations, nurses to triage, etc.)</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
?>