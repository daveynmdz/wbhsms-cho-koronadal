<?php
/**
 * Email Test Script
 * Use this to test your email configuration before testing the full appointment system
 */

// Set error reporting for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include required files
$root_path = dirname(__DIR__);
require_once $root_path . '/config/email.php';

echo "<h2>Email Configuration Test</h2>";

// Test 1: Check if EmailConfig class is available
echo "<h3>1. Checking EmailConfig class...</h3>";
if (class_exists('EmailConfig')) {
    echo "✅ EmailConfig class is available<br>";
} else {
    echo "❌ EmailConfig class not found<br>";
    exit;
}

// Test 2: Check SMTP configuration
echo "<h3>2. Checking SMTP configuration...</h3>";
$config = EmailConfig::getSMTPConfig();
if (!empty($config['username']) && !empty($config['password'])) {
    echo "✅ SMTP credentials are configured<br>";
    echo "Host: " . htmlspecialchars($config['host']) . "<br>";
    echo "Port: " . htmlspecialchars($config['port']) . "<br>";
    echo "Username: " . htmlspecialchars($config['username']) . "<br>";
    echo "Encryption: " . htmlspecialchars($config['encryption']) . "<br>";
} else {
    echo "❌ SMTP credentials not configured. Please update your .env file<br>";
    echo "Current config:<br>";
    echo "<pre>";
    foreach ($config as $key => $value) {
        if ($key === 'password') {
            echo "$key: " . (empty($value) ? '(empty)' : '(configured)') . "\n";
        } else {
            echo "$key: " . htmlspecialchars($value) . "\n";
        }
    }
    echo "</pre>";
    echo "<br><strong>To fix:</strong><br>";
    echo "1. Copy .env.example to .env<br>";
    echo "2. Update SMTP_USERNAME and SMTP_PASSWORD in .env file<br>";
    exit;
}

// Test 3: Test email sending (only if configuration is ready)
echo "<h3>3. Testing email sending...</h3>";

// Check if we should send a test email
if (isset($_GET['send_test']) && $_GET['send_test'] === '1') {
    $test_email = $_GET['email'] ?? '';
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo "❌ Please provide a valid email address<br>";
    } else {
        echo "Sending test email to: " . htmlspecialchars($test_email) . "<br>";
        
        // Prepare test email data
        $test_data = [
            'appointment_num' => 'TEST-12345678',
            'patient_name' => 'Test Patient',
            'facility_name' => 'Test Health Center',
            'service' => 'Primary Care',
            'formatted_date' => 'January 1, 2025 (Wednesday)',
            'formatted_time' => '10:00 AM',
            'referral_num' => ''
        ];
        
        $template = EmailConfig::getAppointmentConfirmationTemplate($test_data);
        
        $result = sendEmail(
            $test_email,
            'Test Patient',
            $template['subject'],
            $template['html_body'],
            $template['text_body']
        );
        
        if ($result['success']) {
            echo "✅ Test email sent successfully!<br>";
            echo "Message: " . htmlspecialchars($result['message']) . "<br>";
        } else {
            echo "❌ Test email failed to send<br>";
            echo "Error: " . htmlspecialchars($result['message']) . "<br>";
            if (isset($result['technical_error'])) {
                echo "Technical details: " . htmlspecialchars($result['technical_error']) . "<br>";
            }
        }
    }
} else {
    echo "Ready to test email sending.<br>";
    echo '<form method="GET">';
    echo '<input type="hidden" name="send_test" value="1">';
    echo 'Test Email Address: <input type="email" name="email" required placeholder="your-email@example.com">';
    echo ' <button type="submit">Send Test Email</button>';
    echo '</form>';
}

echo "<br><h3>4. Troubleshooting Tips</h3>";
echo "<ul>";
echo "<li>Make sure your .env file has correct SMTP credentials</li>";
echo "<li>For Gmail: Use an app password, not your regular password</li>";
echo "<li>Check your spam folder for test emails</li>";
echo "<li>Enable DEBUG_EMAIL=true in .env for detailed logs</li>";
echo "<li>Check PHP error logs for detailed error messages</li>";
echo "</ul>";

echo "<br><strong>Next steps:</strong><br>";
echo "1. If test email works, your appointment confirmation emails will work too<br>";
echo "2. Test the appointment booking system<br>";
echo "3. Check that patients receive confirmation emails<br>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #0077b6; }
h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
form { background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
input[type="email"] { padding: 5px; width: 250px; }
button { padding: 5px 15px; background: #0077b6; color: white; border: none; border-radius: 3px; cursor: pointer; }
button:hover { background: #005577; }
</style>