<?php
require_once 'config/db.php';

echo "<h2>Medication Status Debug</h2>";

// Check medication statuses for recent prescriptions
$sql = "SELECT p.prescription_id, 
               COALESCE(p.status, 'NULL') as prescription_status,
               pm.prescribed_medication_id,
               pm.medication_name,
               COALESCE(pm.status, 'NULL') as medication_status
        FROM prescriptions p 
        LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
        WHERE p.prescription_id IN (5, 6, 7, 8)
        ORDER BY p.prescription_id, pm.prescribed_medication_id";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Prescription ID</th><th>Prescription Status</th><th>Medication ID</th><th>Medication Name</th><th>Medication Status</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescribed_medication_id'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['medication_name'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['medication_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found</p>";
}

// Test the current Recently Dispensed query
echo "<h2>Current Recently Dispensed Query Results</h2>";

$recentDispensedSql = "SELECT p.prescription_id, 
                       p.prescription_date,
                       p.updated_at as dispensed_date,
                       pt.first_name, pt.last_name, pt.middle_name, 
                       COALESCE(pt.username, pt.patient_id) as patient_id_display,
                       e.first_name as doctor_first_name, e.last_name as doctor_last_name,
                       '' as pharmacist_first_name, '' as pharmacist_last_name
                       FROM prescriptions p 
                       LEFT JOIN patients pt ON p.patient_id = pt.patient_id
                       LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
                       WHERE (
                           COALESCE(p.status, 'active') = 'dispensed' 
                           OR (
                               SELECT COUNT(*) FROM prescribed_medications pm1 WHERE pm1.prescription_id = p.prescription_id
                           ) > 0 
                           AND (
                               SELECT COUNT(*) FROM prescribed_medications pm2 
                               WHERE pm2.prescription_id = p.prescription_id 
                               AND pm2.status IN ('dispensed', 'unavailable')
                           ) = (
                               SELECT COUNT(*) FROM prescribed_medications pm3 WHERE pm3.prescription_id = p.prescription_id
                           )
                       )
                       ORDER BY p.updated_at DESC
                       LIMIT 20";

$result2 = $conn->query($recentDispensedSql);
if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Prescription ID</th><th>Patient Name</th><th>Date</th></tr>";
    
    while ($row = $result2->fetch_assoc()) {
        $patientName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        echo "<tr>";
        echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($patientName) . "</td>";
        echo "<td>" . htmlspecialchars($row['dispensed_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No recently dispensed prescriptions found with current query</p>";
}

// Test a simpler query to find completed prescriptions
echo "<h2>Simple Completed Prescriptions Query</h2>";

$simpleSql = "SELECT p.prescription_id, 
                     COALESCE(p.status, 'NULL') as prescription_status,
                     COUNT(pm.prescribed_medication_id) as total_meds,
                     SUM(CASE WHEN pm.status IN ('dispensed', 'unavailable') THEN 1 ELSE 0 END) as completed_meds
              FROM prescriptions p 
              LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
              WHERE p.prescription_id IN (5, 6, 7, 8)
              GROUP BY p.prescription_id, p.status
              HAVING total_meds > 0 AND completed_meds = total_meds";

$result3 = $conn->query($simpleSql);
if ($result3 && $result3->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Prescription ID</th><th>Prescription Status</th><th>Total Meds</th><th>Completed Meds</th></tr>";
    
    while ($row = $result3->fetch_assoc()) {
        echo "<tr>";
        echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['total_meds']) . "</td>";
        echo "<td>" . htmlspecialchars($row['completed_meds']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No completed prescriptions found with simple query</p>";
}
?>