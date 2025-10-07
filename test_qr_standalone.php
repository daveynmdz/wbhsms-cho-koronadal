<?php
/**
 * Standalone QR Code Test Script
 * This will help us debug QR generation issues
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>QR Code Generation Test</h2>";

// Test 1: Check if QR library exists
echo "<h3>Test 1: Check QR Library</h3>";
$qr_lib_path = __DIR__ . '/includes/phpqrcode.php';
if (file_exists($qr_lib_path)) {
    echo "✅ QR library found at: " . $qr_lib_path . "<br>";
    require_once $qr_lib_path;
    
    if (class_exists('QRcode')) {
        echo "✅ QRcode class loaded successfully<br>";
    } else {
        echo "❌ QRcode class not found after including library<br>";
    }
} else {
    echo "❌ QR library not found at: " . $qr_lib_path . "<br>";
}

// Test 2: Check QR Generator class
echo "<h3>Test 2: Check QR Generator Class</h3>";
$qr_gen_path = __DIR__ . '/utils/appointment_qr_generator.php';
if (file_exists($qr_gen_path)) {
    echo "✅ QR Generator found at: " . $qr_gen_path . "<br>";
    require_once $qr_gen_path;
    
    if (class_exists('AppointmentQRGenerator')) {
        echo "✅ AppointmentQRGenerator class loaded successfully<br>";
    } else {
        echo "❌ AppointmentQRGenerator class not found after including file<br>";
    }
} else {
    echo "❌ QR Generator not found at: " . $qr_gen_path . "<br>";
}

// Test 3: Test directory creation and permissions
echo "<h3>Test 3: Check Directory Permissions</h3>";
$qr_dir = __DIR__ . '/assets/qr/appointments/';
echo "QR Directory: " . $qr_dir . "<br>";

if (is_dir($qr_dir)) {
    echo "✅ Directory exists<br>";
} else {
    echo "❌ Directory does not exist, trying to create...<br>";
    if (mkdir($qr_dir, 0755, true)) {
        echo "✅ Directory created successfully<br>";
    } else {
        echo "❌ Failed to create directory<br>";
    }
}

if (is_writable($qr_dir)) {
    echo "✅ Directory is writable<br>";
} else {
    echo "❌ Directory is not writable<br>";
}

// Test 4: Try basic QR generation
echo "<h3>Test 4: Basic QR Code Generation</h3>";
try {
    if (class_exists('QRcode')) {
        $test_data = "TEST QR CODE - " . date('Y-m-d H:i:s');
        $test_file = $qr_dir . 'test_qr.png';
        
        echo "Generating QR with data: " . htmlspecialchars($test_data) . "<br>";
        echo "Target file: " . $test_file . "<br>";
        
        QRcode::png($test_data, $test_file, QR_ECLEVEL_M, 8, 2);
        
        if (file_exists($test_file)) {
            $file_size = filesize($test_file);
            echo "✅ QR code generated successfully! File size: " . $file_size . " bytes<br>";
            echo "<img src='/wbhsms-cho-koronadal/assets/qr/appointments/test_qr.png' alt='Test QR Code' style='border:1px solid #ccc; margin:10px;'><br>";
        } else {
            echo "❌ QR code file was not created<br>";
        }
    } else {
        echo "❌ QRcode class not available for testing<br>";
    }
} catch (Exception $e) {
    echo "❌ Error during QR generation: " . $e->getMessage() . "<br>";
}

// Test 5: Test AppointmentQRGenerator
echo "<h3>Test 5: Test AppointmentQRGenerator</h3>";
try {
    if (class_exists('AppointmentQRGenerator')) {
        $generator = new AppointmentQRGenerator();
        echo "✅ AppointmentQRGenerator instance created<br>";
        
        // Test with sample data
        $test_appointment_id = 999;
        $test_patient_id = 7;
        $test_referral_id = null;
        $test_facility_id = 2;
        $test_facility_type = 'bhc';
        
        echo "Testing with: appointment_id=$test_appointment_id, patient_id=$test_patient_id, facility_id=$test_facility_id, facility_type=$test_facility_type<br>";
        
        $result = $generator->generateAppointmentQR(
            $test_appointment_id,
            $test_patient_id,
            $test_referral_id,
            $test_facility_id,
            $test_facility_type
        );
        
        if ($result['success']) {
            echo "✅ AppointmentQRGenerator worked successfully!<br>";
            echo "QR Data: " . htmlspecialchars($result['qr_json']) . "<br>";
            echo "QR File: " . $result['qr_filepath'] . "<br>";
            echo "File Size: " . $result['file_size'] . " bytes<br>";
            
            if (file_exists($result['qr_filepath'])) {
                $relative_path = str_replace(__DIR__, '/wbhsms-cho-koronadal', $result['qr_filepath']);
                echo "<img src='" . $relative_path . "' alt='Appointment QR Code' style='border:1px solid #ccc; margin:10px;'><br>";
            }
        } else {
            echo "❌ AppointmentQRGenerator failed: " . $result['message'] . "<br>";
        }
    } else {
        echo "❌ AppointmentQRGenerator class not available<br>";
    }
} catch (Exception $e) {
    echo "❌ Error testing AppointmentQRGenerator: " . $e->getMessage() . "<br>";
    echo "Stack trace:<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<a href='/wbhsms-cho-koronadal/pages/patient/appointment/book_appointment.php'>← Back to Appointment Booking</a>";
?>