<?php
// Cleanup duplicate assignment records
// Run this to fix the current duplicate issue

require_once 'config/db.php';

echo "<h2>Assignment Cleanup Tool</h2>";

// Step 1: Show current problematic records
echo "<h3>Current Duplicate/Conflicting Records:</h3>";
$stmt = $conn->prepare("
    SELECT 
        asch.schedule_id,
        asch.employee_id,
        CONCAT(e.first_name, ' ', e.last_name) as employee_name,
        asch.station_id,
        s.station_name,
        asch.start_date,
        asch.end_date,
        asch.is_active,
        asch.assigned_at
    FROM assignment_schedules asch
    JOIN employees e ON asch.employee_id = e.employee_id  
    JOIN stations s ON asch.station_id = s.station_id
    WHERE (asch.employee_id, asch.station_id, asch.start_date) IN (
        SELECT employee_id, station_id, start_date 
        FROM assignment_schedules 
        GROUP BY employee_id, station_id, start_date 
        HAVING COUNT(*) > 1
    )
    ORDER BY asch.employee_id, asch.station_id, asch.start_date, asch.assigned_at
");
$stmt->execute();
$duplicates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($duplicates)) {
    echo "<p>No duplicate records found.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Employee</th><th>Station</th><th>Start Date</th><th>End Date</th><th>Active</th><th>Assigned At</th><th>Action</th></tr>";
    
    foreach ($duplicates as $dup) {
        echo "<tr>";
        echo "<td>{$dup['employee_name']}</td>";
        echo "<td>{$dup['station_name']}</td>";
        echo "<td>{$dup['start_date']}</td>";
        echo "<td>" . ($dup['end_date'] ?: 'Ongoing') . "</td>";
        echo "<td>" . ($dup['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$dup['assigned_at']}</td>";
        echo "<td>";
        if (!$dup['is_active']) {
            echo "<a href='?cleanup=1&remove_id={$dup['schedule_id']}' onclick='return confirm(\"Remove this inactive assignment?\")'>Remove</a>";
        } else {
            echo "Keep (Active)";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Step 2: Handle cleanup requests
if (isset($_GET['cleanup']) && isset($_GET['remove_id'])) {
    $remove_id = intval($_GET['remove_id']);
    
    $delete_stmt = $conn->prepare("DELETE FROM assignment_schedules WHERE schedule_id = ? AND is_active = 0");
    $delete_stmt->bind_param("i", $remove_id);
    
    if ($delete_stmt->execute()) {
        echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✓ Successfully removed inactive assignment record (ID: $remove_id)";
        echo "</div>";
        echo "<script>setTimeout(function(){ window.location.href = 'cleanup_assignments.php'; }, 2000);</script>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✗ Failed to remove assignment record: " . $delete_stmt->error;
        echo "</div>";
    }
}

// Step 3: Show specific employee 86 issue
echo "<h3>Employee 86 (Camila Sophia Ramos) Assignments:</h3>";
$stmt = $conn->prepare("
    SELECT 
        asch.*,
        s.station_name
    FROM assignment_schedules asch
    JOIN stations s ON asch.station_id = s.station_id
    WHERE asch.employee_id = 86
    ORDER BY asch.assigned_at DESC
");
$stmt->execute();
$emp86_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($emp86_assignments)) {
    echo "<p>No assignments found for employee 86.</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Station</th><th>Start Date</th><th>End Date</th><th>Active</th><th>Type</th><th>Assigned At</th></tr>";
    
    foreach ($emp86_assignments as $assign) {
        echo "<tr style='background-color: " . ($assign['is_active'] ? '#d4edda' : '#f8d7da') . "'>";
        echo "<td>{$assign['station_name']}</td>";
        echo "<td>{$assign['start_date']}</td>";
        echo "<td>" . ($assign['end_date'] ?: 'Ongoing') . "</td>";
        echo "<td>" . ($assign['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "<td>{$assign['assignment_type']}</td>";
        echo "<td>{$assign['assigned_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<br><hr><br>";
echo "<h3>Quick Actions:</h3>";
echo "<a href='cleanup_assignments.php?cleanup_all=1' onclick='return confirm(\"Remove ALL inactive assignments? This cannot be undone!\")' style='background-color: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Remove All Inactive Assignments</a><br><br>";

if (isset($_GET['cleanup_all'])) {
    $cleanup_stmt = $conn->prepare("DELETE FROM assignment_schedules WHERE is_active = 0");
    
    if ($cleanup_stmt->execute()) {
        $removed_count = $cleanup_stmt->affected_rows;
        echo "<div style='background-color: #d4edda; color: #155724; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✓ Successfully removed $removed_count inactive assignment records";
        echo "</div>";
        echo "<script>setTimeout(function(){ window.location.href = 'cleanup_assignments.php'; }, 2000);</script>";
    } else {
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "✗ Failed to cleanup assignments: " . $cleanup_stmt->error;
        echo "</div>";
    }
}

echo "<a href='debug_assignment.php'>Go to Debug Page</a> | ";
echo "<a href='pages/queueing/staff_assignments.php'>Back to Staff Assignments</a>";
?>