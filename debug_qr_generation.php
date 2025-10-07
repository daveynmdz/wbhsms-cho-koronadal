<?php
/**
 * Quick Debug Script for QR Generation Issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>QR Generation Debug</h2>";

// Test 1: Check if phpqrcode.php exists
echo "<h3>1. QR Library Check</h3>";
$phpqrcode_path = __DIR__ . '/includes/phpqrcode.php';
echo "Looking for phpqrcode.php at: " . $phpqrcode_path . "<br>";
if (file_exists($phpqrcode_path)) {
    echo "✅ phpqrcode.php found<br>";
    require_once $phpqrcode_path;
    if (class_exists('QRcode')) {
        echo "✅ QRcode class available<br>";
    } else {
        echo "❌ QRcode class NOT available<br>";
    }
} else {
    echo "❌ phpqrcode.php NOT found<br>";
}

// Test 2: Check AppointmentQRGenerator
echo "<h3>2. AppointmentQRGenerator Class Check</h3>";
$generator_path = __DIR__ . '/utils/appointment_qr_generator.php';
echo "Looking for AppointmentQRGenerator at: " . $generator_path . "<br>";
if (file_exists($generator_path)) {
    echo "✅ appointment_qr_generator.php found<br>";
    require_once $generator_path;
    if (class_exists('AppointmentQRGenerator')) {
        echo "✅ AppointmentQRGenerator class available<br>";
        
        // Test 3: Try to create instance
        echo "<h3>3. Instance Creation Test</h3>";
        try {
            $generator = new AppointmentQRGenerator();
            echo "✅ AppointmentQRGenerator instance created successfully<br>";
            
            // Test 4: Directory check
            echo "<h3>4. Directory Check</h3>";
            $qr_dir = __DIR__ . '/assets/qr/appointments/';
            echo "QR Directory: " . $qr_dir . "<br>";
            if (is_dir($qr_dir)) {
                echo "✅ QR directory exists<br>";
                if (is_writable($qr_dir)) {
                    echo "✅ QR directory is writable<br>";
                } else {
                    echo "❌ QR directory is NOT writable<br>";
                }
            } else {
                echo "❌ QR directory does NOT exist<br>";
            }
            
            // Test 5: Try actual QR generation
            echo "<h3>5. QR Generation Test</h3>";
            $result = $generator->generateAppointmentQR(30, 7, null, 2, 'bhc');
            echo "QR Generation Result: <pre>" . print_r($result, true) . "</pre>";
            
            if ($result['success']) {
                echo "✅ QR generation SUCCESS<br>";
                echo "File path: " . $result['qr_filepath'] . "<br>";
                if (file_exists($result['qr_filepath'])) {
                    echo "✅ QR file exists<br>";
                    echo "File size: " . filesize($result['qr_filepath']) . " bytes<br>";
                    echo "QR URL: " . $result['qr_url'] . "<br>";
                    echo '<img src="' . $result['qr_url'] . '" alt="Test QR Code" style="border: 2px solid #0077b6;">';
                } else {
                    echo "❌ QR file does NOT exist<br>";
                }
            } else {
                echo "❌ QR generation FAILED: " . $result['message'] . "<br>";
            }
            
        } catch (Exception $e) {
            echo "❌ Exception during instance creation: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ AppointmentQRGenerator class NOT available<br>";
    }
} else {
    echo "❌ appointment_qr_generator.php NOT found<br>";
}

echo "<h3>6. Recent Appointment Check</h3>";
try {
    require_once __DIR__ . '/config/db.php';
    
    $stmt = $conn->prepare("SELECT * FROM appointments ORDER BY appointment_id DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    
    if ($appointment) {
        echo "Latest appointment: <pre>" . print_r($appointment, true) . "</pre>";
        
        // Check if QR file exists for this appointment
        $expected_qr = __DIR__ . '/assets/qr/appointments/QR-APT-' . str_pad($appointment['appointment_id'], 8, '0', STR_PAD_LEFT) . '.png';
        echo "Expected QR file: " . $expected_qr . "<br>";
        if (file_exists($expected_qr)) {
            echo "✅ QR file exists for latest appointment<br>";
        } else {
            echo "❌ QR file does NOT exist for latest appointment<br>";
        }
    } else {
        echo "No appointments found<br>";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}
?>