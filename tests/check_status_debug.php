<?php
/**
 * Check Current Appointment and Referral Statuses
 * Debug tool to see what statuses are currently in the database
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';

echo "<h2>Current Database Status Check</h2>";

// Check appointments table structure
echo "<h3>Appointments Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE appointments");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error checking appointments table: " . $e->getMessage() . "<br>";
}

// Check referrals table structure
echo "<h3>Referrals Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE referrals");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error checking referrals table: " . $e->getMessage() . "<br>";
}

// Check current appointments
echo "<h3>Current Appointments in Database</h3>";
try {
    $stmt = $conn->prepare("
        SELECT 
            a.appointment_id,
            a.patient_id,
            a.status,
            a.scheduled_date,
            a.scheduled_time,
            a.created_at,
            a.updated_at,
            f.name as facility_name,
            s.name as service_name,
            r.referral_num,
            r.status as referral_status
        FROM appointments a
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN referrals r ON a.referral_id = r.referral_id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($appointments)) {
        echo "<p><strong>No appointments found in database</strong></p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th><th>Patient ID</th><th>Status</th><th>Date</th><th>Time</th>";
        echo "<th>Facility</th><th>Service</th><th>Referral</th><th>Ref Status</th><th>Created</th>";
        echo "</tr>";
        
        foreach ($appointments as $appt) {
            echo "<tr>";
            echo "<td>{$appt['appointment_id']}</td>";
            echo "<td>{$appt['patient_id']}</td>";
            echo "<td><strong style='color: " . getStatusColor($appt['status']) . ";'>{$appt['status']}</strong></td>";
            echo "<td>{$appt['scheduled_date']}</td>";
            echo "<td>{$appt['scheduled_time']}</td>";
            echo "<td>{$appt['facility_name']}</td>";
            echo "<td>{$appt['service_name']}</td>";
            echo "<td>" . ($appt['referral_num'] ?: 'None') . "</td>";
            echo "<td>" . ($appt['referral_status'] ? "<strong style='color: " . getStatusColor($appt['referral_status']) . ";'>{$appt['referral_status']}</strong>" : 'N/A') . "</td>";
            echo "<td>{$appt['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching appointments: " . $e->getMessage() . "<br>";
}

// Check current referrals
echo "<h3>Current Referrals in Database</h3>";
try {
    $stmt = $conn->prepare("
        SELECT 
            r.referral_id,
            r.patient_id,
            r.referral_num,
            r.status,
            r.referral_reason,
            r.referred_by,
            r.referred_to,
            r.referral_date,
            r.expiry_date,
            r.created_at,
            r.updated_at,
            r.notes
        FROM referrals r
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $referrals = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if (empty($referrals)) {
        echo "<p><strong>No referrals found in database</strong></p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr>";
        echo "<th>ID</th><th>Patient ID</th><th>Ref Number</th><th>Status</th>";
        echo "<th>Reason</th><th>Referred By</th><th>Date</th><th>Expiry</th><th>Notes</th>";
        echo "</tr>";
        
        foreach ($referrals as $ref) {
            echo "<tr>";
            echo "<td>{$ref['referral_id']}</td>";
            echo "<td>{$ref['patient_id']}</td>";
            echo "<td>{$ref['referral_num']}</td>";
            echo "<td><strong style='color: " . getStatusColor($ref['status']) . ";'>{$ref['status']}</strong></td>";
            echo "<td>" . substr($ref['referral_reason'], 0, 30) . "...</td>";
            echo "<td>{$ref['referred_by']}</td>";
            echo "<td>{$ref['referral_date']}</td>";
            echo "<td>{$ref['expiry_date']}</td>";
            echo "<td>" . ($ref['notes'] ? substr($ref['notes'], 0, 50) . "..." : 'None') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching referrals: " . $e->getMessage() . "<br>";
}

// Check status distribution
echo "<h3>Status Distribution</h3>";
try {
    echo "<h4>Appointment Status Count</h4>";
    $result = $conn->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status ORDER BY count DESC");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td><strong style='color: " . getStatusColor($row['status']) . ";'>{$row['status']}</strong></td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h4>Referral Status Count</h4>";
    $result = $conn->query("SELECT status, COUNT(*) as count FROM referrals GROUP BY status ORDER BY count DESC");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td><strong style='color: " . getStatusColor($row['status']) . ";'>{$row['status']}</strong></td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error checking status distribution: " . $e->getMessage() . "<br>";
}

function getStatusColor($status) {
    switch(strtolower($status)) {
        case 'pending': return '#ff9800';
        case 'confirmed': return '#4caf50';
        case 'completed': return '#2196f3';
        case 'cancelled': return '#f44336';
        case 'active': return '#4caf50';
        case 'accepted': return '#2196f3';
        case 'expired': return '#f44336';
        case 'issued': return '#9c27b0';
        default: return '#757575';
    }
}

$conn->close();
?>