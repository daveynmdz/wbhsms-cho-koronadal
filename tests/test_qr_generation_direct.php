<?php
/**
 * Direct QR Code Generation Test
 */

require_once '../config/db.php';
require_once '../utils/qr_code_generator.php';

echo "=== Direct QR Code Generation Test ===\n\n";

try {
    // Test QR code generation for a sample appointment
    $test_appointment_id = 999999; // Use a test ID
    $test_data = [
        'patient_id' => 1,
        'scheduled_date' => '2025-10-14',
        'scheduled_time' => '10:00:00',
        'facility_id' => 1,
        'service_id' => 1
    ];
    
    echo "Testing QR code generation...\n";
    
    // Test using generateAppointmentQR method
    $qr_result = QRCodeGenerator::generateAppointmentQR($test_appointment_id, $test_data);
    
    if ($qr_result['success']) {
        echo "✓ QR code generation: SUCCESS\n";
        echo "  QR Data: " . substr($qr_result['qr_data'], 0, 50) . "...\n";
        echo "  Verification Code: " . $qr_result['verification_code'] . "\n";
        echo "  QR Image Size: " . strlen($qr_result['qr_image_data']) . " bytes\n";
        
        // Check if the QR image data is valid base64
        $decoded = base64_decode($qr_result['qr_image_data']);
        $is_valid_base64 = base64_encode($decoded) === $qr_result['qr_image_data'];
        echo "  Image Format: " . ($is_valid_base64 ? "Valid Base64" : "Raw Binary") . "\n";
        
    } else {
        echo "✗ QR code generation: FAILED\n";
        echo "  Error: " . $qr_result['error'] . "\n";
    }
    
    echo "\n=== Test Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>