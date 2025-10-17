<?php
// Quick diagnostic script to check prescription medication statuses
$root_path = __DIR__;
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Check the last few prescriptions and their medication statuses
echo "<h2>Prescription Medication Status Diagnostic</h2>";

try {
    // Get recent prescriptions
    $query = "
        SELECT p.prescription_id, p.status as prescription_status, p.created_at,
               pt.first_name, pt.last_name
        FROM prescriptions p
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id
        ORDER BY p.prescription_id DESC
        LIMIT 5
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($prescription = $result->fetch_assoc()) {
            echo "<h3>Prescription ID: {$prescription['prescription_id']} - Status: {$prescription['prescription_status']}</h3>";
            echo "<p>Patient: {$prescription['first_name']} {$prescription['last_name']}</p>";
            
            // Get medications for this prescription
            $medQuery = "
                SELECT prescribed_medication_id, medication_name, dosage, frequency, instructions, status
                FROM prescribed_medications 
                WHERE prescription_id = ? 
                ORDER BY prescribed_medication_id
            ";
            
            $medStmt = $conn->prepare($medQuery);
            if ($medStmt) {
                $medStmt->bind_param("i", $prescription['prescription_id']);
                $medStmt->execute();
                $medResult = $medStmt->get_result();
                
                echo "<table border='1' style='width:100%; margin-bottom:20px;'>";
                echo "<tr><th>Med ID</th><th>Medication</th><th>Dosage</th><th>Status</th></tr>";
                
                while ($med = $medResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$med['prescribed_medication_id']}</td>";
                    echo "<td>{$med['medication_name']}</td>";
                    echo "<td>{$med['dosage']}</td>";
                    echo "<td><strong>{$med['status']}</strong></td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            }
        }
    }
    
    // Also show the count query results for recent prescriptions
    echo "<h3>Count Analysis for Recent Prescriptions</h3>";
    
    $countQuery = "
        SELECT 
            p.prescription_id,
            p.status as prescription_status,
            COUNT(pm.prescribed_medication_id) as total_medications,
            SUM(CASE WHEN pm.status = 'dispensed' THEN 1 ELSE 0 END) as dispensed_count,
            SUM(CASE WHEN pm.status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_count,
            SUM(CASE WHEN pm.status IN ('dispensed', 'unavailable') THEN 1 ELSE 0 END) as completed_count
        FROM prescriptions p
        LEFT JOIN prescribed_medications pm ON p.prescription_id = pm.prescription_id
        WHERE p.prescription_id >= (SELECT MAX(prescription_id) - 4 FROM prescriptions)
        GROUP BY p.prescription_id
        ORDER BY p.prescription_id DESC
    ";
    
    $countResult = $conn->query($countQuery);
    
    if ($countResult) {
        echo "<table border='1' style='width:100%;'>";
        echo "<tr><th>Prescription ID</th><th>Prescription Status</th><th>Total</th><th>Dispensed</th><th>Unavailable</th><th>Completed</th></tr>";
        
        while ($row = $countResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['prescription_id']}</td>";
            echo "<td>{$row['prescription_status']}</td>";
            echo "<td>{$row['total_medications']}</td>";
            echo "<td>{$row['dispensed_count']}</td>";
            echo "<td>{$row['unavailable_count']}</td>";
            echo "<td>{$row['completed_count']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>