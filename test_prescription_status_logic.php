<?php
require_once 'config/db.php';

echo "<h2>Testing Updated Prescription Status Logic</h2>";

// Test update prescription medications API call with the new logic
echo "<h3>Testing API Update Logic</h3>";

// Check current status of prescriptions and medications
$testSql = "SELECT p.prescription_id, 
                   COALESCE(p.status, 'NULL') as prescription_status,
                   COUNT(pm.prescribed_medication_id) as total_meds,
                   SUM(CASE WHEN pm.status IN ('dispensed', 'unavailable') THEN 1 ELSE 0 END) as completed_meds,
                   SUM(CASE WHEN pm.status = 'dispensed' THEN 1 ELSE 0 END) as dispensed_meds,
                   SUM(CASE WHEN pm.status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_meds,
                   SUM(CASE WHEN pm.status = 'not yet dispensed' THEN 1 ELSE 0 END) as pending_meds
            FROM prescriptions p 
            LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
            WHERE p.prescription_id IN (5, 6, 7, 8)
            GROUP BY p.prescription_id, p.status
            ORDER BY p.prescription_id";

$result = $conn->query($testSql);
if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Prescription ID</th><th>Current Status</th><th>Total Meds</th><th>Completed</th><th>Dispensed</th><th>Unavailable</th><th>Pending</th><th>Should Be</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $shouldBe = '';
        if ($row['total_meds'] > 0 && $row['completed_meds'] == $row['total_meds']) {
            $shouldBe = 'issued';
        } else {
            $shouldBe = 'active';
        }
        
        $bgColor = ($shouldBe === 'issued') ? '#e8f5e8' : '#f8f8f8';
        
        echo "<tr style='background-color: $bgColor;'>";
        echo "<td><strong>RX-" . sprintf('%06d', $row['prescription_id']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['total_meds']) . "</td>";
        echo "<td>" . htmlspecialchars($row['completed_meds']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dispensed_meds']) . "</td>";
        echo "<td>" . htmlspecialchars($row['unavailable_meds']) . "</td>";
        echo "<td>" . htmlspecialchars($row['pending_meds']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($shouldBe) . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Legend:</strong></p>";
    echo "<ul>";
    echo "<li><span style='background-color: #e8f5e8; padding: 2px 4px;'>Green</span> - Should be 'issued' (all medications processed)</li>";
    echo "<li><span style='background-color: #f8f8f8; padding: 2px 4px;'>Gray</span> - Should be 'active' (some medications still pending)</li>";
    echo "</ul>";
}

// Test the Recently Dispensed query
echo "<h3>Testing Recently Dispensed Query (status = 'issued')</h3>";
$recentSql = "SELECT p.prescription_id, 
                     COALESCE(p.status, 'NULL') as prescription_status,
                     pt.first_name, pt.last_name, pt.middle_name
              FROM prescriptions p 
              LEFT JOIN patients pt ON p.patient_id = pt.patient_id
              WHERE p.status = 'issued'
              ORDER BY p.updated_at DESC
              LIMIT 10";

$recentResult = $conn->query($recentSql);
if ($recentResult && $recentResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Prescription ID</th><th>Status</th><th>Patient Name</th>";
    echo "</tr>";
    
    while ($row = $recentResult->fetch_assoc()) {
        $patientName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        echo "<tr>";
        echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($patientName) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No prescriptions with status 'issued' found.</p>";
}

echo "<h3>Current Database Status Enum Values</h3>";
echo "<p>Prescriptions table status enum: 'active', 'issued', 'cancelled', 'dispensed'</p>";
echo "<p>Prescribed_medications table status enum: 'not yet dispensed', 'dispensed', 'unavailable'</p>";
?>

<script>
console.log('Test script loaded - check browser console for any errors');
</script>