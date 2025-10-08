<?php
/**
 * Debug Check-In Process
 * Test the database queries and check-in functionality
 */

require_once '../../config/session/employee_session.php';
require_once '../../config/db.php';

echo "<h2>Check-In Debug Test</h2>";

$today = date('Y-m-d');

try {
    echo "<h3>1. Database Connection</h3>";
    echo "✓ Database connection successful<br>";
    
    echo "<h3>2. Available Appointments Today</h3>";
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.patient_id, p.first_name, p.last_name, 
               a.scheduled_date, a.scheduled_time, a.status, a.service_id
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.patient_id 
        WHERE DATE(a.scheduled_date) = ? AND a.facility_id = 1
        ORDER BY a.scheduled_time
    ");
    $stmt->execute([$today]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($appointments) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>App ID</th><th>Patient ID</th><th>Name</th><th>Date</th><th>Time</th><th>Status</th><th>Service ID</th></tr>";
        foreach ($appointments as $appt) {
            echo "<tr>";
            echo "<td>{$appt['appointment_id']}</td>";
            echo "<td>{$appt['patient_id']}</td>";
            echo "<td>{$appt['first_name']} {$appt['last_name']}</td>";
            echo "<td>{$appt['scheduled_date']}</td>";
            echo "<td>{$appt['scheduled_time']}</td>";
            echo "<td>{$appt['status']}</td>";
            echo "<td>{$appt['service_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No appointments found for today ($today)<br>";
    }
    
    echo "<h3>3. Available Triage Stations</h3>";
    $stmt = $pdo->prepare("SELECT station_id, station_name, station_type, is_active, is_open FROM stations WHERE station_type = 'triage'");
    $stmt->execute();
    $stations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($stations) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Station ID</th><th>Name</th><th>Type</th><th>Active</th><th>Open</th></tr>";
        foreach ($stations as $station) {
            echo "<tr>";
            echo "<td>{$station['station_id']}</td>";
            echo "<td>{$station['station_name']}</td>";
            echo "<td>{$station['station_type']}</td>";
            echo "<td>" . ($station['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "<td>" . ($station['is_open'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No triage stations found<br>";
    }
    
    echo "<h3>4. Current User Session</h3>";
    echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";
    echo "Employee ID: " . ($_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? 'Not set') . "<br>";
    
    echo "<h3>5. Station Assignment Check</h3>";
    $employee_id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];
    if ($employee_id) {
        $stmt = $pdo->prepare("
            SELECT a.schedule_id, a.employee_id, s.station_name, s.station_type, a.start_date, a.end_date, a.is_active
            FROM assignment_schedules a
            JOIN stations s ON a.station_id = s.station_id
            WHERE a.employee_id = ? AND s.station_type = 'checkin' 
            AND a.is_active = 1
            AND DATE(?) BETWEEN a.start_date AND COALESCE(a.end_date, DATE(?))
        ");
        $stmt->execute([$employee_id, $today, $today]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($assignments) {
            echo "✓ User is assigned to check-in station<br>";
            foreach ($assignments as $assignment) {
                echo "- {$assignment['station_name']} (ID: {$assignment['schedule_id']}) from {$assignment['start_date']} to " . ($assignment['end_date'] ?? 'ongoing') . "<br>";
            }
        } else {
            echo "⚠ User is not assigned to check-in station (will need admin role)<br>";
        }
    } else {
        echo "❌ No employee ID in session<br>";
    }
    
    echo "<h3>6. Today's Statistics</h3>";
    
    // Total appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $total = $stmt->fetchColumn();
    echo "Total appointments: $total<br>";
    
    // Checked-in patients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $checked_in = $stmt->fetchColumn();
    echo "Checked-in patients: $checked_in<br>";
    
    // Completed appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1 AND status = 'completed'");
    $stmt->execute([$today]);
    $completed = $stmt->fetchColumn();
    echo "Completed appointments: $completed<br>";
    
    echo "<h3>7. Test API Endpoint</h3>";
    if ($appointments) {
        $test_appt = $appointments[0];
        $api_url = "get_patient_details.php?appointment_id={$test_appt['appointment_id']}&patient_id={$test_appt['patient_id']}";
        echo "Test API: <a href='$api_url' target='_blank'>$api_url</a><br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>

<style>
    table { margin: 10px 0; }
    th, td { padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>