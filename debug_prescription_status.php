<?php
// Quick debug script to check prescription statuses
$root_path = __DIR__;
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

echo "<h2>Current Prescription Statuses (Last 10)</h2>";

try {
    $query = "
        SELECT p.prescription_id, p.status, p.updated_at,
               pt.first_name, pt.last_name
        FROM prescriptions p
        LEFT JOIN patients pt ON p.patient_id = pt.patient_id
        ORDER BY p.prescription_id DESC
        LIMIT 10
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>Prescription ID</th><th>Patient</th><th>Status</th><th>Updated At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            $statusColor = $row['status'] === 'dispensed' ? 'background: #d4edda;' : '';
            echo "<tr style='$statusColor'>";
            echo "<td>RX-" . sprintf('%06d', $row['prescription_id']) . "</td>";
            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
            echo "<td><strong>{$row['status']}</strong></td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Also show count by status
        echo "<h3>Count by Status</h3>";
        $countQuery = "SELECT status, COUNT(*) as count FROM prescriptions GROUP BY status";
        $countResult = $conn->query($countQuery);
        
        if ($countResult) {
            echo "<table border='1' style='margin-top: 10px; border-collapse: collapse;'>";
            echo "<tr style='background: #f0f0f0;'><th>Status</th><th>Count</th></tr>";
            
            while ($row = $countResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['status']}</td>";
                echo "<td>{$row['count']}</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>