<?php
require_once 'config/db.php';

echo "<h2>Database Schema Debug for Production</h2>";

// Check the prescribed_medications table structure
echo "<h3>prescribed_medications Table Structure</h3>";
$result = $conn->query("DESCRIBE prescribed_medications");

if ($result) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $highlight = ($row['Field'] === 'status') ? 'background-color: #ffcccc;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['Type']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

// Check the prescriptions table structure too
echo "<h3>prescriptions Table Structure</h3>";
$result2 = $conn->query("DESCRIBE prescriptions");

if ($result2) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th>";
    echo "</tr>";
    
    while ($row = $result2->fetch_assoc()) {
        $highlight = ($row['Field'] === 'status') ? 'background-color: #ffcccc;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($row['Type']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

// Show current status values in the table
echo "<h3>Current Status Values in prescribed_medications</h3>";
$statusResult = $conn->query("SELECT DISTINCT status, CHAR_LENGTH(status) as length FROM prescribed_medications WHERE status IS NOT NULL");

if ($statusResult) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Status Value</th><th>Character Length</th>";
    echo "</tr>";
    
    while ($row = $statusResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['length']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Error: " . $conn->error . "</p>";
}

// Test the values we're trying to insert
echo "<h3>Testing Status Values We're Trying to Insert</h3>";
$testValues = ['dispensed', 'unavailable', 'not yet dispensed'];

echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>Value</th><th>Character Length</th><th>Notes</th>";
echo "</tr>";

foreach ($testValues as $value) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($value) . "</td>";
    echo "<td>" . strlen($value) . "</td>";
    echo "<td>" . (strlen($value) > 10 ? '<strong style="color: red;">May be too long if column is VARCHAR(10) or smaller</strong>' : 'Should fit') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Recommended Fix</h3>";
echo "<div style='background-color: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
echo "<p><strong>If the status column is too small (e.g., VARCHAR(10) or ENUM with limited values):</strong></p>";
echo "<p><strong>Option 1 - Alter the column size:</strong></p>";
echo "<pre>ALTER TABLE prescribed_medications MODIFY COLUMN status VARCHAR(20) DEFAULT 'not yet dispensed';</pre>";
echo "<p><strong>Option 2 - Use shorter status values in the code:</strong></p>";
echo "<ul>";
echo "<li>'dispensed' → 'dispensed' (9 chars - OK)</li>";
echo "<li>'unavailable' → 'unavailable' (11 chars - may be too long)</li>";
echo "<li>'not yet dispensed' → 'pending' (7 chars - shorter)</li>";
echo "</ul>";
echo "</div>";

?>

<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; }
</style>