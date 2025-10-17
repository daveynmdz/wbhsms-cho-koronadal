<?php
require_once 'config/db.php';

echo "<h2>Testing Status Mapping Fix</h2>";

// Test the mapping function
function mapStatusForDatabase($status) {
    // Map longer status values to shorter ones if database column is too small
    $statusMap = [
        'dispensed' => 'dispensed',
        'unavailable' => 'unavailabl',  // Truncated to 10 chars if needed
        'not yet dispensed' => 'pending'
    ];
    
    return isset($statusMap[$status]) ? $statusMap[$status] : $status;
}

echo "<h3>Status Mapping Test</h3>";
$testStatuses = ['dispensed', 'unavailable', 'not yet dispensed'];

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Original Status</th><th>Mapped Status</th><th>Length</th>";
echo "</tr>";

foreach ($testStatuses as $status) {
    $mapped = mapStatusForDatabase($status);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($status) . "</td>";
    echo "<td>" . htmlspecialchars($mapped) . "</td>";
    echo "<td>" . strlen($mapped) . " chars</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Instructions for Production</h3>";
echo "<div style='background-color: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
echo "<p><strong>1. Database Fix (Recommended):</strong></p>";
echo "<p>Run this SQL on your production database:</p>";
echo "<pre>ALTER TABLE prescribed_medications MODIFY COLUMN status ENUM('pending', 'dispensed', 'unavailabl') DEFAULT 'pending';</pre>";
echo "<p><strong>2. Alternative - Use the mapping fix:</strong></p>";
echo "<p>The code now maps 'unavailable' to 'unavailabl' (10 chars) to fit smaller columns.</p>";
echo "<p>Deploy the updated API file to production and test.</p>";
echo "</div>";

// Test if we can insert the mapped values
echo "<h3>Database Insert Test</h3>";
try {
    $conn->begin_transaction();
    
    // Test insert with mapped values
    $testQuery = "INSERT INTO prescribed_medications (prescription_id, medication_name, dosage, frequency, duration, instructions, status) VALUES (?, 'Test Med', '500mg', 'Once daily', '7 days', 'Take with food', ?)";
    $testStmt = $conn->prepare($testQuery);
    
    if ($testStmt) {
        $testPrescriptionId = 1; // Use existing prescription ID
        $mappedStatus = mapStatusForDatabase('unavailable');
        
        $testStmt->bind_param("is", $testPrescriptionId, $mappedStatus);
        $result = $testStmt->execute();
        
        if ($result) {
            echo "<p style='color: green;'>✓ Successfully inserted test record with mapped status: '$mappedStatus'</p>";
            
            // Clean up test record
            $deleteQuery = "DELETE FROM prescribed_medications WHERE medication_name = 'Test Med' AND prescription_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $testPrescriptionId);
            $deleteStmt->execute();
            
        } else {
            echo "<p style='color: red;'>✗ Failed to insert test record: " . $testStmt->error . "</p>";
        }
    }
    
    $conn->rollback(); // Rollback any changes
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database test failed: " . $e->getMessage() . "</p>";
    $conn->rollback();
}

?>

<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>