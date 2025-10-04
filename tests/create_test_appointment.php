<?php
require_once 'config/db.php';

// Create a test appointment for patient ID 7 (David)
$patient_id = 7;
$facility_id = 4; // Zone II Health Center
$service_id = 1; // Primary Care
$scheduled_date = '2025-10-05'; // Future date
$scheduled_time = '10:00:00';

$stmt = $conn->prepare("
    INSERT INTO appointments (
        patient_id, facility_id, service_id, 
        scheduled_date, scheduled_time, status, created_at
    ) VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())
");

$stmt->bind_param("iiiss", $patient_id, $facility_id, $service_id, $scheduled_date, $scheduled_time);

if ($stmt->execute()) {
    $new_appointment_id = $conn->insert_id;
    echo "Test appointment created successfully!<br>";
    echo "Appointment ID: " . $new_appointment_id . "<br>";
    echo "Appointment Number: APT-" . str_pad($new_appointment_id, 8, '0', STR_PAD_LEFT) . "<br>";
    echo "Patient ID: " . $patient_id . "<br>";
    echo "Date: " . $scheduled_date . " at " . $scheduled_time . "<br>";
    echo "Status: confirmed<br>";
    echo "<br>";
    echo "<a href='pages/patient/appointment/appointments.php'>Go to Appointments Page</a>";
} else {
    echo "Error creating test appointment: " . $conn->error;
}

$stmt->close();
$conn->close();
?>