<?php
/**
 * Test AJAX appointment details functionality
 */

require_once __DIR__ . '/../config/db.php';

echo "<h2>Testing AJAX Appointment Details</h2>";

try {
    // Check if facilities table exists
    echo "<h3>1. Checking Facilities Table</h3>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'facilities'");
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        echo "<p>✅ Facilities table exists</p>";
        
        $stmt = $pdo->query("DESCRIBE facilities");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p>Facilities table structure:</p><ul>";
        foreach ($columns as $column) {
            echo "<li>{$column['Field']} ({$column['Type']})</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>❌ Facilities table does NOT exist</p>";
    }
    
    // Test the appointment details query
    echo "<h3>2. Testing Appointment Details Query</h3>";
    
    // Get a sample appointment first
    $stmt = $pdo->query("SELECT appointment_id, patient_id FROM appointments WHERE facility_id = 1 LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sample) {
        echo "<p>Testing with appointment ID: {$sample['appointment_id']}, patient ID: {$sample['patient_id']}</p>";
        
        // Test the actual query used in the AJAX handler
        $query = "
            SELECT a.*, 
                   p.first_name, p.last_name, p.middle_name, p.date_of_birth, 
                   p.sex as gender, p.isSenior, p.isPWD, p.email, p.contact_number as phone,
                   b.barangay_name,
                   s.name as service_name,
                   " . ($exists ? "f.name as facility_name," : "'CHO Koronadal' as facility_name,") . "
                   r.referral_reason, r.referred_by, r.referral_num,
                   v.visit_id as already_checked_in,
                   CASE 
                       WHEN p.isSenior = 1 OR p.isPWD = 1 THEN 'priority'
                       ELSE 'normal'
                   END as priority_status,
                   a.qr_code_path
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
            LEFT JOIN services s ON a.service_id = s.service_id
            " . ($exists ? "LEFT JOIN facilities f ON a.facility_id = f.facility_id" : "") . "
            LEFT JOIN referrals r ON a.referral_id = r.referral_id
            LEFT JOIN visits v ON a.appointment_id = v.appointment_id AND v.facility_id = a.facility_id
            WHERE a.appointment_id = ? AND a.patient_id = ? AND a.facility_id = 1
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$sample['appointment_id'], $sample['patient_id']]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment) {
            echo "<p>✅ Query successful! Found appointment data:</p>";
            echo "<ul>";
            echo "<li>Patient: {$appointment['first_name']} {$appointment['last_name']}</li>";
            echo "<li>Service: {$appointment['service_name']}</li>";
            echo "<li>Date: {$appointment['scheduled_date']}</li>";
            echo "<li>Time: {$appointment['scheduled_time']}</li>";
            echo "<li>Status: {$appointment['status']}</li>";
            echo "</ul>";
            
            // Test JSON encoding
            echo "<h4>Testing JSON Encoding</h4>";
            $json_response = json_encode(['success' => true, 'appointment' => $appointment]);
            if ($json_response === false) {
                echo "<p>❌ JSON encoding failed: " . json_last_error_msg() . "</p>";
            } else {
                echo "<p>✅ JSON encoding successful</p>";
                echo "<p>Response length: " . strlen($json_response) . " characters</p>";
            }
        } else {
            echo "<p>❌ Query returned no results</p>";
        }
    } else {
        echo "<p>❌ No appointments found to test with</p>";
    }
    
    // Test the AJAX endpoint directly
    echo "<h3>3. Testing AJAX Endpoint</h3>";
    echo "<form method='POST' action='../pages/queueing/checkin.php' target='_blank'>";
    echo "<input type='hidden' name='action' value='get_appointment_details'>";
    echo "<input type='hidden' name='ajax' value='1'>";
    if ($sample) {
        echo "<input type='hidden' name='appointment_id' value='{$sample['appointment_id']}'>";
        echo "<input type='hidden' name='patient_id' value='{$sample['patient_id']}'>";
        echo "<button type='submit'>Test AJAX Endpoint</button>";
    } else {
        echo "<p>No appointment data to test with</p>";
    }
    echo "</form>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>