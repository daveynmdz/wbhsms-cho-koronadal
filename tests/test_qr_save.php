<?php
/**
 * Direct QR Code Database Saving Test
 */

require_once '../config/db.php';
require_once '../utils/qr_code_generator.php';

echo "=== QR CODE DATABASE SAVING TEST ===\n\n";

try {
    // Find an existing appointment to test with
    $stmt = $pdo->prepare("SELECT appointment_id FROM appointments ORDER BY appointment_id DESC LIMIT 1");
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo "No appointments found to test with\n";
        exit;
    }
    
    $test_appointment_id = $appointment['appointment_id'];
    echo "Testing with appointment ID: {$test_appointment_id}\n";
    
    // Generate QR code
    echo "\n1. Generating QR code...\n";
    $qr_result = QRCodeGenerator::generateAppointmentQR($test_appointment_id, [
        'patient_id' => 8,
        'scheduled_date' => '2025-10-15',
        'scheduled_time' => '10:00:00',
        'facility_id' => 1,
        'service_id' => 1
    ]);
    
    if ($qr_result['success']) {
        echo "   ✓ QR code generated successfully\n";
        echo "   ✓ Verification code: " . $qr_result['verification_code'] . "\n";
        echo "   ✓ QR data length: " . strlen($qr_result['qr_image_data']) . " bytes\n";
        
        // Test saving to database
        echo "\n2. Testing database save with MySQLi...\n";
        
        $save_result = QRCodeGenerator::saveQRToAppointment(
            $test_appointment_id, 
            $qr_result['qr_image_data'], 
            $conn
        );
        
        if ($save_result) {
            echo "   ✓ QR code saved successfully with MySQLi\n";
            
            // Verify it was saved
            $stmt = $conn->prepare("SELECT qr_code_path FROM appointments WHERE appointment_id = ?");
            $stmt->bind_param("i", $test_appointment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            if ($data && !empty($data['qr_code_path'])) {
                echo "   ✓ QR code verified in database\n";
                echo "   ✓ Stored data length: " . strlen($data['qr_code_path']) . " bytes\n";
                
                // Test if data matches
                if ($data['qr_code_path'] === $qr_result['qr_image_data']) {
                    echo "   ✓ Stored data matches original\n";
                } else {
                    echo "   ⚠ Stored data differs from original\n";
                }
            } else {
                echo "   ✗ QR code not found in database after save\n";
            }
        } else {
            echo "   ✗ Failed to save QR code to database\n";
        }
        
        // Test with PDO
        echo "\n3. Testing database save with PDO...\n";
        
        $save_result_pdo = QRCodeGenerator::saveQRToAppointment(
            $test_appointment_id, 
            $qr_result['qr_image_data'], 
            $pdo
        );
        
        if ($save_result_pdo) {
            echo "   ✓ QR code saved successfully with PDO\n";
        } else {
            echo "   ✗ Failed to save QR code with PDO\n";
        }
        
    } else {
        echo "   ✗ QR code generation failed: " . $qr_result['error'] . "\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>