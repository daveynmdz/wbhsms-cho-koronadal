<?php
/**
 * Simulated Appointment QR Generation Test
 * This mimics exactly what happens in submit_appointment.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simulated Appointment QR Test</h2>";

// Simulate the exact parameters from a real appointment (like appointment 30)
$appointment_id = 31; // New test appointment
$patient_id = 7;
$referral_id = null;
$facility_id = 2;
$facility_type = 'bhc';

echo "<h3>Test Parameters:</h3>";
echo "Appointment ID: $appointment_id<br>";
echo "Patient ID: $patient_id<br>";
echo "Referral ID: " . ($referral_id ?: 'null') . "<br>";
echo "Facility ID: $facility_id<br>";
echo "Facility Type: $facility_type<br>";

echo "<h3>Step-by-Step QR Generation:</h3>";

try {
    // Step 1: Load the class
    echo "1. Loading AppointmentQRGenerator...<br>";
    require_once __DIR__ . '/utils/appointment_qr_generator.php';
    
    if (!class_exists('AppointmentQRGenerator')) {
        throw new Exception('AppointmentQRGenerator class not found after include');
    }
    echo "✅ Class loaded<br>";
    
    // Step 2: Create instance
    echo "2. Creating QR Generator instance...<br>";
    $qr_generator = new AppointmentQRGenerator();
    echo "✅ Instance created<br>";
    
    // Step 3: Generate QR
    echo "3. Generating QR code...<br>";
    $qr_result = $qr_generator->generateAppointmentQR(
        $appointment_id,
        $patient_id,
        $referral_id,
        $facility_id,
        $facility_type
    );
    
    echo "QR Generation Result:<br>";
    echo "<pre>" . print_r($qr_result, true) . "</pre>";
    
    if ($qr_result['success']) {
        echo "✅ QR Generation SUCCESS<br>";
        
        echo "<h3>4. File Verification:</h3>";
        echo "Expected file: " . $qr_result['qr_filepath'] . "<br>";
        
        if (file_exists($qr_result['qr_filepath'])) {
            echo "✅ File exists on filesystem<br>";
            echo "File size: " . filesize($qr_result['qr_filepath']) . " bytes<br>";
            
            // Try to display the QR code
            echo "<h3>5. QR Code Display Test:</h3>";
            echo "Public URL: " . $qr_result['qr_url'] . "<br>";
            echo '<img src="' . $qr_result['qr_url'] . '" alt="Generated QR Code" style="border: 2px solid #0077b6; margin: 10px;">';
            
            // Test direct file access
            $direct_url = '/wbhsms-cho-koronadal/assets/qr/appointments/' . $qr_result['qr_filename'];
            echo "<br>Direct URL: " . $direct_url . "<br>";
            echo '<img src="' . $direct_url . '" alt="Direct QR Code" style="border: 2px solid #dc3545; margin: 10px;">';
            
        } else {
            echo "❌ File does NOT exist on filesystem<br>";
        }
    } else {
        echo "❌ QR Generation FAILED: " . $qr_result['message'] . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h3>6. Directory Investigation:</h3>";
$expected_dir = __DIR__ . '/assets/qr/appointments/';
echo "Expected QR directory: " . $expected_dir . "<br>";

if (is_dir($expected_dir)) {
    echo "✅ Directory exists<br>";
    
    $files = scandir($expected_dir);
    echo "Files in directory: <br>";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "- $file (" . filesize($expected_dir . $file) . " bytes)<br>";
        }
    }
} else {
    echo "❌ Directory does NOT exist<br>";
}

echo "<h3>7. Web Access Test:</h3>";
echo "Testing if files are accessible via web...<br>";

// Test with the test_qr.png file we know exists
echo '<img src="/wbhsms-cho-koronadal/assets/qr/appointments/test_qr.png" alt="Test QR" style="border: 2px solid #28a745;" onerror="this.style.border=\'2px solid #dc3545\'; this.alt=\'Failed to load\';">';
echo "<br>If you see a QR image above, web access works. If not, there's a web server configuration issue.<br>";
?>