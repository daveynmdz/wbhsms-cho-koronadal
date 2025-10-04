<?php
// test_email_config.php - Quick test to check email configuration
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Email Configuration Test</h1>";

// Test 1: Check if .env file loads properly
echo "<h2>1. Environment Variables Check</h2>";
$root_path = dirname(__DIR__);
require_once $root_path . '/config/env.php';

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Variable</th><th>Value</th><th>Status</th></tr>";

$env_vars = [
    'SMTP_HOST' => $_ENV['SMTP_HOST'] ?? 'NOT SET',
    'SMTP_USERNAME' => $_ENV['SMTP_USERNAME'] ?? 'NOT SET', 
    'SMTP_PASSWORD' => ($_ENV['SMTP_PASSWORD'] ?? '') ? '*****(SET)' : 'NOT SET',
    'SMTP_PORT' => $_ENV['SMTP_PORT'] ?? 'NOT SET',
    'SMTP_ENCRYPTION' => $_ENV['SMTP_ENCRYPTION'] ?? 'NOT SET',
    'FROM_EMAIL' => $_ENV['FROM_EMAIL'] ?? 'NOT SET',
    'FROM_NAME' => $_ENV['FROM_NAME'] ?? 'NOT SET'
];

foreach ($env_vars as $var => $value) {
    $status = ($value === 'NOT SET') ? '❌ Missing' : '✅ OK';
    echo "<tr><td>$var</td><td>$value</td><td>$status</td></tr>";
}
echo "</table>";

// Test 2: Check if EmailConfig class works
echo "<h2>2. EmailConfig Class Test</h2>";
try {
    require_once $root_path . '/config/email.php';
    $config = EmailConfig::getSMTPConfig();
    echo "✅ EmailConfig class loaded successfully<br>";
    echo "SMTP Config: <pre>" . print_r($config, true) . "</pre>";
} catch (Exception $e) {
    echo "❌ EmailConfig error: " . $e->getMessage() . "<br>";
}

// Test 3: Check PHPMailer availability
echo "<h2>3. PHPMailer Availability</h2>";
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer is available<br>";
} else {
    echo "❌ PHPMailer not found<br>";
}

// Test 4: Test email sending (if credentials are available)
echo "<h2>4. Email Sending Test</h2>";
if (!empty($_ENV['SMTP_USERNAME']) && !empty($_ENV['SMTP_PASSWORD'])) {
    echo "🔧 Attempting to send test email...<br>";
    
    try {
        $result = sendEmail(
            'test@example.com',  // This will fail but we can see the error
            'Test User',
            'Test Subject - CHO Koronadal',
            '<h1>Test Email</h1><p>This is a test email from CHO Koronadal system.</p>',
            'Test Email - This is a test email from CHO Koronadal system.'
        );
        
        if ($result['success']) {
            echo "✅ Email sending test passed<br>";
        } else {
            echo "⚠️ Email test result: " . $result['message'] . "<br>";
            if (isset($result['technical_error'])) {
                echo "Technical error: " . $result['technical_error'] . "<br>";
            }
        }
    } catch (Exception $e) {
        echo "❌ Email test failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ SMTP credentials not configured - skipping email test<br>";
}

echo "<hr>";
echo "<h2>Troubleshooting Tips:</h2>";
echo "<ul>";
echo "<li>If SMTP credentials are missing, update .env file with your Gmail credentials</li>";
echo "<li>For Gmail, use an App Password, not your regular password</li>";
echo "<li>Make sure 2-factor authentication is enabled on your Gmail account</li>";
echo "<li>Generate an App Password in Gmail Settings > Security > App passwords</li>";
echo "<li>Check that the email address in patient records is valid</li>";
echo "</ul>";

?>