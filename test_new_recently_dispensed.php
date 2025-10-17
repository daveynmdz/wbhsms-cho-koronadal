<?php
require_once 'config/db.php';

echo "<h2>Testing New Recently Dispensed Query</h2>";

// Test the new Recently Dispensed query
$recentDispensedSql = "
    (
        -- Get prescriptions with explicit 'dispensed' status
        SELECT DISTINCT p.prescription_id, 
               p.prescription_date,
               p.updated_at as dispensed_date,
               pt.first_name, pt.last_name, pt.middle_name, 
               COALESCE(pt.username, pt.patient_id) as patient_id_display,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               '' as pharmacist_first_name, '' as pharmacist_last_name,
               p.status as prescription_status,
               'explicit_status' as source_reason
        FROM prescriptions p 
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        WHERE p.status = 'dispensed'
    )
    UNION
    (
        -- Get prescriptions where ALL medications are processed (dispensed OR unavailable)
        -- regardless of prescription status (handles NULL status prescriptions)
        SELECT p.prescription_id, 
               p.prescription_date,
               p.updated_at as dispensed_date,
               pt.first_name, pt.last_name, pt.middle_name, 
               COALESCE(pt.username, pt.patient_id) as patient_id_display,
               e.first_name as doctor_first_name, e.last_name as doctor_last_name,
               '' as pharmacist_first_name, '' as pharmacist_last_name,
               COALESCE(p.status, 'NULL') as prescription_status,
               'all_medications_processed' as source_reason
        FROM prescriptions p 
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        INNER JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
        GROUP BY p.prescription_id, p.prescription_date, p.updated_at, 
                 pt.first_name, pt.last_name, pt.middle_name, pt.username, pt.patient_id,
                 e.first_name, e.last_name, p.status
        HAVING COUNT(pm.prescribed_medication_id) > 0 
           AND SUM(CASE WHEN pm.status IN ('dispensed', 'unavailable') THEN 1 ELSE 0 END) = COUNT(pm.prescribed_medication_id)
           AND COALESCE(p.status, '') != 'dispensed'  -- Avoid duplicates from first query
    )
    ORDER BY dispensed_date DESC
    LIMIT 20";

$result = $conn->query($recentDispensedSql);

if ($result && $result->num_rows > 0) {
    echo "<p><strong>SUCCESS:</strong> Found " . $result->num_rows . " recently dispensed prescriptions!</p>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Prescription ID</th><th>Patient Name</th><th>Prescription Status</th><th>Source Reason</th><th>Date</th>";
    echo "</tr>";
    
    while ($row = $result->fetch_assoc()) {
        $patientName = trim($row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name']);
        $bgColor = $row['source_reason'] == 'all_medications_processed' ? '#e8f5e8' : '#f8f8f8';
        
        echo "<tr style='background-color: $bgColor;'>";
        echo "<td><strong>RX-" . sprintf('%06d', $row['prescription_id']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($patientName) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['source_reason']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dispensed_date']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>Legend:</strong></p>";
    echo "<ul>";
    echo "<li><span style='background-color: #f8f8f8; padding: 2px 4px;'>Gray background</span> - Prescription has explicit 'dispensed' status</li>";
    echo "<li><span style='background-color: #e8f5e8; padding: 2px 4px;'>Green background</span> - Prescription status is NULL/empty but all medications are processed</li>";
    echo "</ul>";
    
} else {
    echo "<p><strong>No recently dispensed prescriptions found.</strong></p>";
}

// Also show medication details for verification
echo "<h3>Medication Status Details (for verification)</h3>";
$medSql = "SELECT p.prescription_id, 
                  COALESCE(p.status, 'NULL') as prescription_status,
                  pm.prescribed_medication_id,
                  pm.medication_name,
                  COALESCE(pm.status, 'NULL') as medication_status
           FROM prescriptions p 
           LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
           WHERE p.prescription_id IN (5, 6, 7, 8)
           ORDER BY p.prescription_id, pm.prescribed_medication_id";

$medResult = $conn->query($medSql);
if ($medResult && $medResult->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>Prescription ID</th><th>Prescription Status</th><th>Medication</th><th>Medication Status</th>";
    echo "</tr>";
    
    while ($row = $medResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['prescription_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['medication_name'] ?: 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($row['medication_status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>