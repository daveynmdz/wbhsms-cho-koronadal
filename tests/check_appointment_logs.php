<?php
require_once 'config/db.php';

echo "<h2>Appointment Logs</h2>";

// Get all appointment logs, most recent first
$query = "SELECT al.*, 
                 CONCAT('APT-', LPAD(al.appointment_id, 8, '0')) as appointment_number,
                 p.first_name, p.last_name
          FROM appointment_logs al
          LEFT JOIN patients p ON al.patient_id = p.patient_id
          ORDER BY al.created_at DESC
          LIMIT 20";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>
            <th>Log ID</th>
            <th>Appointment</th>
            <th>Patient</th>
            <th>Action</th>
            <th>Status Change</th>
            <th>Reason</th>
            <th>Created By</th>
            <th>IP Address</th>
            <th>Date/Time</th>
          </tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['log_id'] . "</td>";
        echo "<td>" . $row['appointment_number'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . ucfirst($row['action']) . "</td>";
        echo "<td>" . ($row['old_status'] ?? 'NULL') . " → " . ($row['new_status'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['reason'] ?? '') . "</td>";
        echo "<td>" . ucfirst($row['created_by_type']) . " (ID: " . ($row['created_by_id'] ?? 'NULL') . ")</td>";
        echo "<td>" . htmlspecialchars($row['ip_address'] ?? '') . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No appointment logs found.</p>";
}

// Also check if there are any logs for appointment ID 8 specifically
echo "<h3>Logs for Appointment ID 8 (APT-00000008)</h3>";
$query = "SELECT * FROM appointment_logs WHERE appointment_id = 8 ORDER BY created_at DESC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
        echo "<strong>Action:</strong> " . $row['action'] . "<br>";
        echo "<strong>Status:</strong> " . ($row['old_status'] ?? 'NULL') . " → " . ($row['new_status'] ?? 'NULL') . "<br>";
        echo "<strong>Reason:</strong> " . htmlspecialchars($row['reason'] ?? '') . "<br>";
        echo "<strong>Date:</strong> " . $row['created_at'] . "<br>";
        echo "</div>";
    }
} else {
    echo "<p>No logs found for appointment ID 8.</p>";
}

$conn->close();
?>