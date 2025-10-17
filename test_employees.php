<?php
require_once 'config/db.php';

echo "<h2>Available Employees for Testing</h2>";

try {
    $query = "SELECT employee_id, first_name, last_name, email, role_id, status FROM employees WHERE status = 'active' ORDER BY role_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role ID</th><th>Status</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['employee_id'] . "</td>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
            echo "<td>" . $row['email'] . "</td>";
            echo "<td>" . $row['role_id'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Role Reference:</h3>";
        echo "Role 1 = Admin<br>";
        echo "Role 2 = Doctor<br>";
        echo "Role 3 = Nurse<br>";
        echo "Role 9 = Pharmacist<br>";
        echo "Other roles may exist...<br>";
        
    } else {
        echo "No active employees found.";
    }
    
    // Check if prescriptions exist
    echo "<h2>Available Prescriptions for Testing</h2>";
    $prescQuery = "SELECT p.prescription_id, CONCAT(pt.first_name, ' ', pt.last_name) as patient_name, p.status, p.prescribed_date 
                   FROM prescriptions p 
                   JOIN patients pt ON p.patient_id = pt.patient_id 
                   ORDER BY p.prescribed_date DESC LIMIT 10";
    $prescResult = $conn->query($prescQuery);
    
    if ($prescResult && $prescResult->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>Prescription ID</th><th>Patient</th><th>Status</th><th>Date</th></tr>";
        
        while ($row = $prescResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['prescription_id'] . "</td>";
            echo "<td>" . $row['patient_name'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['prescribed_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No prescriptions found.";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>