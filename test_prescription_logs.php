<?php
require_once 'config/db.php';

echo "<h2>Prescription Logs Table Test</h2>";

try {
    // Test if prescription_logs table exists and is accessible
    $testQuery = "SELECT COUNT(*) as log_count FROM prescription_logs";
    $result = $conn->query($testQuery);
    
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<div style='color: green;'>✅ prescription_logs table exists and is accessible</div>";
        echo "<div>Current log entries: " . $row['log_count'] . "</div>";
    } else {
        echo "<div style='color: red;'>❌ Error accessing prescription_logs table: " . $conn->error . "</div>";
    }
    
    // Test table structure
    $structureQuery = "DESCRIBE prescription_logs";
    $structureResult = $conn->query($structureQuery);
    
    if ($structureResult) {
        echo "<h3>Table Structure:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        
        while ($row = $structureResult->fetch_assoc()) {
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
    
    // Test recent logs if any exist
    $recentLogsQuery = "SELECT * FROM prescription_logs ORDER BY created_at DESC LIMIT 5";
    $recentResult = $conn->query($recentLogsQuery);
    
    if ($recentResult && $recentResult->num_rows > 0) {
        echo "<h3>Recent Log Entries:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Log ID</th><th>Prescription ID</th><th>Action</th><th>Details</th><th>Employee ID</th><th>Created At</th></tr>";
        
        while ($row = $recentResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['log_id'] . "</td>";
            echo "<td>" . $row['prescription_id'] . "</td>";
            echo "<td>" . $row['action'] . "</td>";
            echo "<td>" . htmlspecialchars($row['details'] ?? '') . "</td>";
            echo "<td>" . $row['employee_id'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div>No log entries yet - this is normal for a new installation</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . $e->getMessage() . "</div>";
}
?>

<p><a href="pages/prescription-management/prescription_management.php">Go to Prescription Management</a></p>