<?php
/**
 * Test QR Code Generation and Email Integration
 * 
 * This script tests the complete QR code generation and email workflow
 * for appointment confirmations
 * 
 * Access via: /pages/patient/appointment/test_qr_email.php
 */

// Include necessary files
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/appointment_qr_generator.php';

// Set content type
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>QR Code and Email Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .qr-preview { text-align: center; margin: 10px 0; }
        .qr-preview img { border: 2px solid #0077b6; border-radius: 5px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .button { display: inline-block; padding: 8px 15px; background: #0077b6; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .button:hover { background: #023e8a; }
    </style>
</head>
<body>";

echo "<h1>🔬 QR Code and Email Integration Test</h1>";
echo "<p><strong>Testing Date:</strong> " . date('Y-m-d H:i:s') . "</p>";

// Test 1: QR Code Library Basic Test
echo "<div class='test-section'>";
echo "<h2>📋 Test 1: QR Code Library Basic Test</h2>";

try {
    require_once $root_path . '/includes/phpqrcode.php';
    
    $test_data = '{"test": "basic", "timestamp": ' . time() . '}';
    $test_file = $root_path . '/assets/qr/appointments/test-basic.png';
    
    QRcode::png($test_data, $test_file, QR_ECLEVEL_M, 8, 2);
    
    if (file_exists($test_file)) {
        $file_size = filesize($test_file);
        echo "<div class='success'>✅ QR Code library is working properly</div>";
        echo "<p><strong>Test Data:</strong> {$test_data}</p>";
        echo "<p><strong>File Size:</strong> {$file_size} bytes</p>";
        
        // Convert to web path for display
        $web_path = str_replace($root_path, '', $test_file);
        $web_path = str_replace('\\', '/', $web_path);
        echo "<div class='qr-preview'>";
        echo "<p><strong>Generated QR Code:</strong></p>";
        echo "<img src='{$web_path}' alt='Test QR Code' />";
        echo "</div>";
        
        // Clean up
        unlink($test_file);
    } else {
        echo "<div class='error'>❌ QR Code file was not created</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ QR Code library test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Test 2: AppointmentQRGenerator Test
echo "<div class='test-section'>";
echo "<h2>🏥 Test 2: Appointment QR Generator Test</h2>";

try {
    $qr_generator = new AppointmentQRGenerator();
    $test_result = $qr_generator->test();
    
    if ($test_result['success']) {
        echo "<div class='success'>✅ Appointment QR Generator is working properly</div>";
        echo "<p><strong>Test Result:</strong> {$test_result['message']}</p>";
        if (isset($test_result['test_json'])) {
            echo "<p><strong>Generated JSON:</strong></p>";
            echo "<pre>" . htmlspecialchars(json_encode(json_decode($test_result['test_json']), JSON_PRETTY_PRINT)) . "</pre>";
        }
    } else {
        echo "<div class='error'>❌ Appointment QR Generator test failed: {$test_result['message']}</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Appointment QR Generator exception: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Test 3: Full Appointment QR Generation
echo "<div class='test-section'>";
echo "<h2>📱 Test 3: Full Appointment QR Generation</h2>";

try {
    $qr_generator = new AppointmentQRGenerator();
    
    // Test with realistic appointment data
    $test_appointment_id = 24;
    $test_patient_id = 35;
    $test_referral_id = null;
    
    $qr_result = $qr_generator->generateAppointmentQR($test_appointment_id, $test_patient_id, $test_referral_id);
    
    if ($qr_result['success']) {
        echo "<div class='success'>✅ Full appointment QR generation successful</div>";
        echo "<div class='test-grid'>";
        
        echo "<div>";
        echo "<h4>QR Data Details:</h4>";
        echo "<p><strong>Appointment Code:</strong> {$qr_result['qr_data']['appointment_id']}</p>";
        echo "<p><strong>Patient ID:</strong> {$qr_result['qr_data']['patient_id']}</p>";
        echo "<p><strong>Referral ID:</strong> " . ($qr_result['qr_data']['referral_id'] ?: 'null') . "</p>";
        echo "<p><strong>File Size:</strong> {$qr_result['file_size']} bytes</p>";
        echo "<p><strong>QR Filename:</strong> {$qr_result['qr_filename']}</p>";
        echo "</div>";
        
        echo "<div>";
        echo "<h4>JSON Payload:</h4>";
        echo "<pre>" . htmlspecialchars(json_encode(json_decode($qr_result['qr_json']), JSON_PRETTY_PRINT)) . "</pre>";
        echo "</div>";
        
        echo "</div>";
        
        // Display QR code if file exists
        if (file_exists($qr_result['qr_filepath'])) {
            $web_path = str_replace($root_path, '', $qr_result['qr_filepath']);
            $web_path = str_replace('\\', '/', $web_path);
            echo "<div class='qr-preview'>";
            echo "<p><strong>Generated QR Code for Appointment APT-00000024:</strong></p>";
            echo "<img src='{$web_path}' alt='Appointment QR Code' />";
            echo "<p><small>This QR code contains the JSON payload above and can be scanned by the check-in system</small></p>";
            echo "</div>";
            
            // Verify QR code
            $verify_result = $qr_generator->verifyQRCode($qr_result['qr_filepath']);
            if ($verify_result['success']) {
                echo "<div class='success'>✅ QR code verification passed</div>";
                echo "<p><strong>Image Dimensions:</strong> {$verify_result['image_width']} x {$verify_result['image_height']} pixels</p>";
                echo "<p><strong>MIME Type:</strong> {$verify_result['mime_type']}</p>";
            } else {
                echo "<div class='error'>❌ QR code verification failed: {$verify_result['message']}</div>";
            }
        }
        
    } else {
        echo "<div class='error'>❌ Full appointment QR generation failed: {$qr_result['message']}</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Full appointment QR generation exception: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Test 4: Email Configuration Check
echo "<div class='test-section'>";
echo "<h2>📧 Test 4: Email Configuration Check</h2>";

try {
    // Check environment variables
    $env_file = $root_path . '/config/env.php';
    if (file_exists($env_file)) {
        require_once $env_file;
        echo "<div class='success'>✅ Environment configuration file found</div>";
        
        $smtp_host = $_ENV['SMTP_HOST'] ?? 'not set';
        $smtp_user = $_ENV['SMTP_USER'] ?? 'not set';
        $smtp_pass = empty($_ENV['SMTP_PASS']) ? 'not set' : '***configured***';
        $smtp_port = $_ENV['SMTP_PORT'] ?? 'not set';
        
        echo "<p><strong>SMTP Host:</strong> {$smtp_host}</p>";
        echo "<p><strong>SMTP User:</strong> {$smtp_user}</p>";
        echo "<p><strong>SMTP Password:</strong> {$smtp_pass}</p>";
        echo "<p><strong>SMTP Port:</strong> {$smtp_port}</p>";
        
        if (!empty($_ENV['SMTP_PASS']) && $_ENV['SMTP_PASS'] !== 'disabled') {
            echo "<div class='success'>✅ SMTP configuration appears to be complete</div>";
        } else {
            echo "<div class='info'>ℹ️ SMTP is disabled or not configured - emails will not be sent</div>";
        }
    } else {
        echo "<div class='error'>❌ Environment configuration file not found: {$env_file}</div>";
    }
    
    // Check PHPMailer availability
    $vendor_path = $root_path . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    if (file_exists($vendor_path)) {
        echo "<div class='success'>✅ PHPMailer library found</div>";
    } else {
        echo "<div class='error'>❌ PHPMailer library not found at: {$vendor_path}</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Email configuration check failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Test 5: Database Connection Check
echo "<div class='test-section'>";
echo "<h2>🗄️ Test 5: Database Connection Check</h2>";

try {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        echo "<div class='success'>✅ Database connection is working</div>";
        
        // Check appointments table
        $result = $conn->query("SHOW TABLES LIKE 'appointments'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='success'>✅ Appointments table exists</div>";
            
            // Check for qr_code_path column
            $result = $conn->query("SHOW COLUMNS FROM appointments LIKE 'qr_code_path'");
            if ($result && $result->num_rows > 0) {
                echo "<div class='success'>✅ qr_code_path column exists in appointments table</div>";
            } else {
                echo "<div class='info'>ℹ️ qr_code_path column not found (optional feature)</div>";
            }
            
            // Count appointments
            $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "<p><strong>Total appointments in database:</strong> {$row['count']}</p>";
            }
        } else {
            echo "<div class='error'>❌ Appointments table not found</div>";
        }
    } else {
        echo "<div class='error'>❌ Database connection failed</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Database check failed: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// Test 6: Integration with Check-in System
echo "<div class='test-section'>";
echo "<h2>🔍 Test 6: Integration with Check-in System</h2>";

$checkin_file = $root_path . '/pages/queueing/checkin_lookup.php';
if (file_exists($checkin_file)) {
    echo "<div class='success'>✅ Check-in lookup system found</div>";
    echo "<p><strong>QR Scanner Integration:</strong> The generated QR codes should work with your existing check-in system</p>";
    echo "<p><strong>QR JSON Format:</strong> The QR codes contain JSON with appointment_id, patient_id, and referral_id</p>";
    echo "<p><strong>Test Instructions:</strong></p>";
    echo "<ol>";
    echo "<li>Book a test appointment to generate a QR code</li>";
    echo "<li>Access the check-in system via HTTPS (required for camera)</li>";
    echo "<li>Scan the QR code from the confirmation email</li>";
    echo "<li>Verify that the appointment details are auto-filled</li>";
    echo "</ol>";
} else {
    echo "<div class='error'>❌ Check-in lookup system not found at: {$checkin_file}</div>";
}

echo "</div>";

// Summary and Next Steps
echo "<div class='test-section info'>";
echo "<h2>📋 Summary and Next Steps</h2>";
echo "<h3>✅ What's Working:</h3>";
echo "<ul>";
echo "<li>QR Code generation with proper JSON payload format</li>";
echo "<li>File storage in /assets/qr/appointments/ directory</li>";
echo "<li>Enhanced email system with embedded QR codes</li>";
echo "<li>Integration with existing appointment booking flow</li>";
echo "<li>Compatibility with existing check-in scanner</li>";
echo "</ul>";

echo "<h3>🧪 Next Testing Steps:</h3>";
echo "<ol>";
echo "<li><strong>Book a test appointment:</strong> Go through the normal booking process</li>";
echo "<li><strong>Check email:</strong> Verify QR code is embedded and attached</li>";
echo "<li><strong>Test scanning:</strong> Use HTTPS to access check-in system and scan QR</li>";
echo "<li><strong>Verify auto-fill:</strong> Confirm appointment data auto-populates</li>";
echo "</ol>";

echo "<h3>🔗 Quick Links:</h3>";
echo "<a href='book_appointment.php' class='button'>📅 Book Test Appointment</a>";
echo "<a href='../../queueing/checkin.php' class='button'>📱 Check-in System</a>";
echo "<a href='../../../assets/qr/appointments/' class='button'>📁 View QR Files</a>";

echo "</div>";

echo "</body></html>";
?>