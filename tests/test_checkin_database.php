<?php
/**
 * Test Check-in Database Queries
 * Tests all database queries used in checkin.php to ensure they work correctly
 */

require_once __DIR__ . '/../config/db.php';

echo "<h2>Testing Check-in Database Queries</h2>";

try {
    echo "<h3>1. Testing Services Query</h3>";
    $stmt = $pdo->prepare("SELECT service_id, name FROM services ORDER BY name");
    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Services query successful. Found " . count($services) . " services:</p>";
    echo "<ul>";
    foreach ($services as $service) {
        echo "<li>ID: {$service['service_id']}, Name: {$service['name']}</li>";
    }
    echo "</ul>";
    
    echo "<h3>2. Testing Barangays Query</h3>";
    $stmt = $pdo->prepare("SELECT DISTINCT b.barangay_name FROM barangay b 
                          INNER JOIN patients p ON b.barangay_id = p.barangay_id 
                          ORDER BY b.barangay_name");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>✅ Barangays query successful. Found " . count($barangays) . " barangays with patients:</p>";
    echo "<ul>";
    foreach (array_slice($barangays, 0, 10) as $barangay) {
        echo "<li>{$barangay}</li>";
    }
    if (count($barangays) > 10) {
        echo "<li>... and " . (count($barangays) - 10) . " more</li>";
    }
    echo "</ul>";
    
    echo "<h3>3. Testing Today's Statistics Queries</h3>";
    $today = date('Y-m-d');
    
    // Total appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $total_appointments = $stmt->fetchColumn();
    echo "<p>✅ Total appointments today: {$total_appointments}</p>";
    
    // Checked-in patients today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM visits WHERE DATE(visit_date) = ? AND facility_id = 1");
    $stmt->execute([$today]);
    $checked_in = $stmt->fetchColumn();
    echo "<p>✅ Checked-in patients today: {$checked_in}</p>";
    
    // Completed appointments today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE DATE(scheduled_date) = ? AND facility_id = 1 AND status = 'completed'");
    $stmt->execute([$today]);
    $completed = $stmt->fetchColumn();
    echo "<p>✅ Completed appointments today: {$completed}</p>";
    
    // Priority patients in queue today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM queue_entries q 
                          JOIN appointments a ON q.appointment_id = a.appointment_id 
                          WHERE DATE(q.created_at) = ? AND q.priority_level IN ('priority', 'emergency') AND a.facility_id = 1");
    $stmt->execute([$today]);
    $priority = $stmt->fetchColumn();
    echo "<p>✅ Priority patients in queue today: {$priority}</p>";
    
    echo "<h3>4. Testing Search Query (Sample)</h3>";
    $query = "
        SELECT a.appointment_id, a.patient_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
               a.referral_id, a.qr_code_path, a.cancellation_reason,
               p.first_name, p.last_name, p.date_of_birth, p.isSenior, p.isPWD, p.philhealth_id_number,
               b.barangay_name,
               s.name as service_name,
               r.referral_reason, r.referred_by,
               v.visit_id, v.visit_status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN referrals r ON a.referral_id = r.referral_id
        LEFT JOIN visits v ON a.appointment_id = v.appointment_id AND DATE(v.visit_date) = DATE(a.scheduled_date)
        WHERE a.facility_id = 1 AND DATE(a.scheduled_date) = ? 
        ORDER BY a.scheduled_time ASC
        LIMIT 5
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$today]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Search query successful. Found " . count($search_results) . " appointments for today:</p>";
    
    if (!empty($search_results)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";
        echo "<tr>";
        echo "<th>ID</th><th>Patient</th><th>Service</th><th>Time</th><th>Status</th>";
        echo "</tr>";
        
        foreach ($search_results as $row) {
            echo "<tr>";
            echo "<td>{$row['appointment_id']}</td>";
            echo "<td>{$row['first_name']} {$row['last_name']}</td>";
            echo "<td>{$row['service_name']}</td>";
            echo "<td>{$row['scheduled_time']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No appointments found for today. This is normal if no appointments are scheduled.</p>";
    }
    
    echo "<h3>5. Testing QR Scan Query (Sample)</h3>";
    // Test with a known appointment ID if exists
    if (!empty($search_results)) {
        $test_appointment_id = $search_results[0]['appointment_id'];
        
        $query = "
            SELECT a.appointment_id, a.patient_id, a.scheduled_date, a.scheduled_time, a.status, a.service_id,
                   a.referral_id, a.qr_code_path,
                   p.first_name, p.last_name, p.date_of_birth, p.isSenior, p.isPWD, p.philhealth_id_number,
                   b.barangay_name,
                   s.name as service_name,
                   r.referral_reason, r.referred_by
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
            LEFT JOIN services s ON a.service_id = s.service_id
            LEFT JOIN referrals r ON a.referral_id = r.referral_id
            WHERE a.appointment_id = ? AND a.facility_id = 1
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$test_appointment_id]);
        $qr_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($qr_result) {
            echo "<p>✅ QR scan query successful for appointment ID {$test_appointment_id}</p>";
            echo "<p>Patient: {$qr_result['first_name']} {$qr_result['last_name']}</p>";
            echo "<p>Service: {$qr_result['service_name']}</p>";
        }
    } else {
        echo "<p>⚠️ No appointments available to test QR scan query</p>";
    }
    
    echo "<h3>✅ All Database Tests Completed Successfully!</h3>";
    echo "<p>The check-in page should now work without database errors.</p>";
    
} catch (Exception $e) {
    echo "<h3>❌ Database Test Failed</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>