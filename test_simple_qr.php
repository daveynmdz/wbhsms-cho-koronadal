<?php
/**
 * Simple QR Code Generation Test
 * 
 * Run this script via browser to test QR generation
 * Access via: http://localhost:8080/wbhsms-cho-koronadal/test_simple_qr.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Simple QR Code Test</h1>";
echo "<p>Testing Date: " . date('Y-m-d H:i:s') . "</p>";

try {
    // Include QR library
    require_once 'includes/phpqrcode.php';
    
    // Test data - exactly like what will be in appointment QR codes
    $test_data = [
        'appointment_id' => 'APT-00000024',
        'patient_id' => '35',
        'referral_id' => null
    ];
    
    $json_data = json_encode($test_data);
    echo "<h2>Test Data:</h2>";
    echo "<pre>" . htmlspecialchars($json_data) . "</pre>";
    
    // Create QR directory if it doesn't exist
    $qr_dir = 'assets/qr/appointments/';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
        echo "<p>✅ Created QR directory: {$qr_dir}</p>";
    }
    
    // Generate QR code
    $qr_filename = 'test-qr-' . time() . '.png';
    $qr_path = $qr_dir . $qr_filename;
    
    QRcode::png($json_data, $qr_path, QR_ECLEVEL_M, 8, 2);
    
    if (file_exists($qr_path)) {
        $file_size = filesize($qr_path);
        echo "<p>✅ QR code generated successfully!</p>";
        echo "<p><strong>File:</strong> {$qr_path}</p>";
        echo "<p><strong>Size:</strong> {$file_size} bytes</p>";
        
        // Display the QR code
        echo "<h2>Generated QR Code:</h2>";
        echo "<img src='{$qr_path}' alt='Test QR Code' style='border: 2px solid #0077b6; border-radius: 5px;' />";
        
        echo "<h2>Instructions:</h2>";
        echo "<ol>";
        echo "<li>The QR code above contains the JSON data shown above</li>";
        echo "<li>You can scan this with any QR scanner to verify the JSON content</li>";
        echo "<li>This same format will be used for appointment confirmations</li>";
        echo "<li>The check-in system should be able to parse this JSON automatically</li>";
        echo "</ol>";
        
        // Clean up after 10 seconds (for demo)
        echo "<p><small>Note: This test file will remain in {$qr_path} for inspection</small></p>";
        
    } else {
        echo "<p>❌ Failed to generate QR code file</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li><a href='pages/patient/appointment/test_qr_email.php'>Run Full QR & Email Test</a></li>";
echo "<li><a href='pages/patient/appointment/book_appointment.php'>Book a Test Appointment</a></li>";
echo "<li><a href='pages/queueing/checkin.php'>Test QR Scanner (requires HTTPS)</a></li>";
echo "</ol>";
?>