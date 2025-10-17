<?php
require_once 'config/db.php';

echo "<h2>Prescription Logs Debug Test</h2>";

try {
    // Check table structure
    echo "<h3>Current prescription_logs table structure:</h3>";
    $structureQuery = "DESCRIBE prescription_logs";
    $result = $conn->query($structureQuery);
    
    if ($result) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test a simple insert to see if it works
    echo "<h3>Testing prescription_logs insert:</h3>";
    
    $testInsert = "INSERT INTO prescription_logs (prescription_id, action_type, field_changed, old_value, new_value, changed_by_employee_id, change_reason) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $testStmt = $conn->prepare($testInsert);
    
    if ($testStmt) {
        echo "<div style='color: green;'>✅ Prepare statement successful</div>";
        
        // Test values
        $testPrescriptionId = 1;
        $testActionType = 'test_action';
        $testFieldChanged = 'test_field';
        $testOldValue = 'old_test_value';
        $testNewValue = 'new_test_value';
        $testEmployeeId = 1;
        $testReason = 'Testing prescription logs functionality';
        
        $testStmt->bind_param("issssss", 
            $testPrescriptionId, 
            $testActionType, 
            $testFieldChanged, 
            $testOldValue, 
            $testNewValue, 
            $testEmployeeId, 
            $testReason
        );
        
        if ($testStmt->execute()) {
            echo "<div style='color: green;'>✅ Test insert successful! Log ID: " . $conn->insert_id . "</div>";
        } else {
            echo "<div style='color: red;'>❌ Test insert failed: " . $testStmt->error . "</div>";
        }
    } else {
        echo "<div style='color: red;'>❌ Prepare statement failed: " . $conn->error . "</div>";
    }
    
    // Show recent logs
    echo "<h3>Recent log entries:</h3>";
    $recentQuery = "SELECT * FROM prescription_logs ORDER BY created_at DESC LIMIT 10";
    $recentResult = $conn->query($recentQuery);
    
    if ($recentResult && $recentResult->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Log ID</th><th>Prescription ID</th><th>Action Type</th><th>Field Changed</th><th>Old Value</th><th>New Value</th><th>Employee ID</th><th>Reason</th><th>Created At</th></tr>";
        
        while ($row = $recentResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['log_id'] . "</td>";
            echo "<td>" . $row['prescription_id'] . "</td>";
            echo "<td>" . $row['action_type'] . "</td>";
            echo "<td>" . $row['field_changed'] . "</td>";
            echo "<td>" . htmlspecialchars($row['old_value'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['new_value'] ?? '') . "</td>";
            echo "<td>" . $row['changed_by_employee_id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['change_reason'] ?? '') . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div>No log entries found</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}
?>

<p><a href="pages/prescription-management/prescription_management.php">Go to Prescription Management</a></p>