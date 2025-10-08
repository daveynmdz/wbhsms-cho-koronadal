<?php
/**
 * Test script to debug the patient details API
 */

// Turn off error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../config/session/employee_session.php';
require_once '../../config/db.php';

// Test with some sample data - you'll need to replace these with actual IDs from your database
$test_appointment_id = 23; // Replace with actual appointment ID
$test_patient_id = 7; // Replace with actual patient ID

echo "<h2>Testing Patient Details API</h2>";
echo "<p>Testing with Appointment ID: $test_appointment_id, Patient ID: $test_patient_id</p>";

try {
    // Test patient query
    echo "<h3>1. Testing Patient Query:</h3>";
    $stmt = $pdo->prepare("
        SELECT p.*, b.barangay_name as barangay,
               TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
        FROM patients p 
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        WHERE p.patient_id = ?
    ");
    $stmt->execute([$test_patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        echo "<pre>" . print_r($patient, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>No patient found with ID: $test_patient_id</p>";
    }
    
    // Test appointment query
    echo "<h3>2. Testing Appointment Query:</h3>";
    $stmt = $pdo->prepare("
        SELECT a.*, 
               DATE_FORMAT(a.scheduled_date, '%M %d, %Y') as formatted_date,
               TIME_FORMAT(a.scheduled_time, '%h:%i %p') as formatted_time
        FROM appointments a
        WHERE a.appointment_id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$test_appointment_id, $test_patient_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment) {
        echo "<pre>" . print_r($appointment, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>No appointment found with ID: $test_appointment_id for patient: $test_patient_id</p>";
    }
    
    // Test the API endpoint directly
    echo "<h3>3. Testing API Endpoint:</h3>";
    $url = "get_patient_details.php?appointment_id=$test_appointment_id&patient_id=$test_patient_id";
    echo "<p>API URL: <a href='$url' target='_blank'>$url</a></p>";
    
    // Show available appointments for testing
    echo "<h3>4. Available Appointments for Testing:</h3>";
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.patient_id, p.first_name, p.last_name, a.scheduled_date, a.status
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.patient_id 
        WHERE DATE(a.scheduled_date) = CURDATE() 
        ORDER BY a.scheduled_time 
        LIMIT 10
    ");
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($appointments) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Appointment ID</th><th>Patient ID</th><th>Patient Name</th><th>Date</th><th>Status</th><th>Test Link</th></tr>";
        foreach ($appointments as $appt) {
            $test_url = "get_patient_details.php?appointment_id={$appt['appointment_id']}&patient_id={$appt['patient_id']}";
            echo "<tr>";
            echo "<td>{$appt['appointment_id']}</td>";
            echo "<td>{$appt['patient_id']}</td>";
            echo "<td>{$appt['first_name']} {$appt['last_name']}</td>";
            echo "<td>{$appt['scheduled_date']}</td>";
            echo "<td>{$appt['status']}</td>";
            echo "<td><a href='$test_url' target='_blank'>Test API</a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No appointments found for today.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>