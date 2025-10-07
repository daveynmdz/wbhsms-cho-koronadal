<?php
// test_database_columns.php - Verify database schema

require_once '../../../config/db.php';

echo "<h1>🔍 Database Schema Verification</h1>";

// Test appointments table structure
echo "<h2>📋 Appointments Table Structure</h2>";

$result = $conn->query("DESCRIBE appointments");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . ($row['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Could not retrieve appointments table structure<br>";
}

echo "<h2>✅ Key Findings</h2>";
echo "<ul>";
echo "<li><strong>Date Column:</strong> scheduled_date (not appointment_date)</li>";
echo "<li><strong>Time Column:</strong> scheduled_time (not appointment_time)</li>";
echo "<li><strong>No appointment_num column:</strong> Will generate based on appointment_id</li>";
echo "<li><strong>Default Status:</strong> confirmed (not pending)</li>";
echo "</ul>";

echo "<h2>🧪 Test Insert Query</h2>";
echo "<p>Testing the corrected insert query structure:</p>";
echo "<pre>";
echo "INSERT INTO appointments (\n";
echo "    patient_id, service_id, facility_id,\n";
echo "    scheduled_date, scheduled_time, status, referral_id,\n";
echo "    created_at, updated_at\n";
echo ") VALUES (?, ?, ?, ?, ?, 'confirmed', ?, NOW(), NOW())";
echo "</pre>";

// Test if we can prepare the query
$test_stmt = $conn->prepare("
    INSERT INTO appointments (
        patient_id, service_id, facility_id,
        scheduled_date, scheduled_time, status, referral_id,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, 'confirmed', ?, NOW(), NOW())
");

if ($test_stmt) {
    echo "<p style='color: green;'>✅ Insert query preparation successful!</p>";
    $test_stmt->close();
} else {
    echo "<p style='color: red;'>❌ Insert query preparation failed: " . $conn->error . "</p>";
}

echo "<h2>🔧 Fixed Issues Summary</h2>";
echo "<ol>";
echo "<li>Changed 'appointment_date' → 'scheduled_date'</li>";
echo "<li>Changed 'appointment_time' → 'scheduled_time'</li>";
echo "<li>Removed appointment_num column from insert</li>";
echo "<li>Changed status from 'pending' → 'confirmed'</li>";
echo "<li>Generate appointment_num after insert using appointment_id</li>";
echo "</ol>";

?>