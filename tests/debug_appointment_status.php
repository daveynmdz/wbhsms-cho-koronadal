<?php
require_once 'config/db.php';

// Check the specific appointment that's causing issues
$appointment_id = 8; // APT-00000008

echo "Checking appointment ID: $appointment_id\n";

$query = "SELECT appointment_id, patient_id, status, LENGTH(status) as status_length, 
          CASE WHEN status IS NULL THEN 'NULL' 
               WHEN status = '' THEN 'EMPTY' 
               ELSE CONCAT('VALUE: [', status, ']') END as status_debug,
          scheduled_date, scheduled_time, created_at 
          FROM appointments WHERE appointment_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo "Raw data for appointment $appointment_id:\n";
    foreach ($row as $key => $value) {
        echo "$key: " . ($value === null ? 'NULL' : "'$value'") . "\n";
    }
} else {
    echo "Appointment $appointment_id not found\n";
}

$conn->close();
?>