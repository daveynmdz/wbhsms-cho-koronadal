<?php
require_once '../config/db.php';

echo "<h2>Database Content Check</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
    .success { color: green; }
    .error { color: red; }
    .warning { color: orange; }
</style>";

try {
    // Check patients
    echo "<div class='section'>";
    echo "<h3>Patients Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM patients");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Patients: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>Patient ID</th><th>Name</th><th>Patient Number</th><th>Contact</th></tr>";
        $result = $conn->query("SELECT patient_id, first_name, last_name, patient_number, contact_number FROM patients LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['patient_id'] . "</td>";
            echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
            echo "<td>" . $row['patient_number'] . "</td>";
            echo "<td>" . $row['contact_number'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check facilities
    echo "<div class='section'>";
    echo "<h3>Facilities Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM facilities");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Facilities: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>Facility ID</th><th>Name</th><th>Type</th><th>Status</th></tr>";
        $result = $conn->query("SELECT facility_id, facility_name, facility_type, status FROM facilities LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['facility_id'] . "</td>";
            echo "<td>" . $row['facility_name'] . "</td>";
            echo "<td>" . $row['facility_type'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check services
    echo "<div class='section'>";
    echo "<h3>Services Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM services");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Services: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>Service ID</th><th>Name</th><th>Description</th><th>Billable</th></tr>";
        $result = $conn->query("SELECT service_id, name, description, is_billable FROM services LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['service_id'] . "</td>";
            echo "<td>" . $row['name'] . "</td>";
            echo "<td>" . $row['description'] . "</td>";
            echo "<td>" . ($row['is_billable'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check appointments
    echo "<div class='section'>";
    echo "<h3>Appointments Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Appointments: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Patient ID</th><th>Facility ID</th><th>Service ID</th><th>Date</th><th>Time</th><th>Status</th></tr>";
        $result = $conn->query("SELECT appointment_id, patient_id, facility_id, service_id, scheduled_date, scheduled_time, status FROM appointments LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['appointment_id'] . "</td>";
            echo "<td>" . $row['patient_id'] . "</td>";
            echo "<td>" . $row['facility_id'] . "</td>";
            echo "<td>" . $row['service_id'] . "</td>";
            echo "<td>" . $row['scheduled_date'] . "</td>";
            echo "<td>" . $row['scheduled_time'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check queue entries
    echo "<div class='section'>";
    echo "<h3>Queue Entries Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM queue_entries");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Queue Entries: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>Queue ID</th><th>Queue Number</th><th>Patient ID</th><th>Appointment ID</th><th>Visit ID</th><th>Status</th></tr>";
        $result = $conn->query("SELECT queue_entry_id, queue_number, patient_id, appointment_id, visit_id, status FROM queue_entries LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['queue_entry_id'] . "</td>";
            echo "<td>" . $row['queue_number'] . "</td>";
            echo "<td>" . $row['patient_id'] . "</td>";
            echo "<td>" . $row['appointment_id'] . "</td>";
            echo "<td>" . $row['visit_id'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Check visits
    echo "<div class='section'>";
    echo "<h3>Visits Table</h3>";
    $result = $conn->query("SELECT COUNT(*) as count FROM visits");
    $count = $result->fetch_assoc()['count'];
    echo "<p class='success'>Total Visits: $count</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>Visit ID</th><th>Patient ID</th><th>Appointment ID</th><th>Facility ID</th><th>Visit Date</th><th>Status</th></tr>";
        $result = $conn->query("SELECT visit_id, patient_id, appointment_id, facility_id, visit_date, status FROM visits LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['visit_id'] . "</td>";
            echo "<td>" . $row['patient_id'] . "</td>";
            echo "<td>" . $row['appointment_id'] . "</td>";
            echo "<td>" . $row['facility_id'] . "</td>";
            echo "<td>" . $row['visit_date'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Test file includes
    echo "<div class='section'>";
    echo "<h3>Required Files Check</h3>";
    
    $files_to_check = [
        '../utils/queue_management_service.php',
        '../utils/appointment_logger.php',
        '../pages/management/models/QueueModel.php'
    ];
    
    foreach ($files_to_check as $file) {
        if (file_exists($file)) {
            echo "<p class='success'>✅ $file exists</p>";
        } else {
            echo "<p class='error'>❌ $file missing</p>";
        }
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "</div>";
}

$conn->close();
?>