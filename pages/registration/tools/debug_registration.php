<?php
// Debug registration process
session_start();
require_once '../../../config/env.php';

// Load PHPMailer classes at the top level
if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
    $vendorAutoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        require_once $vendorAutoload;
    } else {
        require_once '../../../phpmailer/phpmailer/src/PHPMailer.php';
        require_once '../../../phpmailer/phpmailer/src/SMTP.php';
        require_once '../../../phpmailer/phpmailer/src/Exception.php';
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h1>Registration Debug</h1>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT 1");
    echo "<p>✅ Database connection: OK</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection: FAILED - " . $e->getMessage() . "</p>";
}

// Test environment variables
echo "<h2>Environment Variables:</h2>";
echo "<p>SMTP_HOST: " . ($_ENV['SMTP_HOST'] ?? 'NOT SET') . "</p>";
echo "<p>SMTP_USER: " . ($_ENV['SMTP_USER'] ?? 'NOT SET') . "</p>";
echo "<p>SMTP_PASS: " . (($_ENV['SMTP_PASS'] ?? '') ? '***SET***' : 'NOT SET') . "</p>";
echo "<p>SMTP_PORT: " . ($_ENV['SMTP_PORT'] ?? 'NOT SET') . "</p>";

// Test barangay loading
try {
    $stmt = $pdo->prepare("SELECT barangay_id, barangay_name FROM barangay WHERE status = 'active' ORDER BY barangay_name LIMIT 5");
    $stmt->execute();
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>✅ Sample Barangays:</h2>";
    foreach ($barangays as $brgy) {
        echo "<p>ID: {$brgy['barangay_id']}, Name: {$brgy['barangay_name']}</p>";
    }
} catch (Exception $e) {
    echo "<h2>❌ Barangay loading failed: " . $e->getMessage() . "</h2>";
}

// Test SMTP connection
echo "<h2>SMTP Test:</h2>";
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'] ?? '';
    $mail->Password = $_ENV['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
    
    // Try to connect (without sending)
    if ($mail->smtpConnect()) {
        echo "<p>✅ SMTP connection: OK</p>";
        $mail->smtpClose();
    } else {
        echo "<p>❌ SMTP connection: FAILED</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ SMTP test failed: " . $e->getMessage() . "</p>";
}

// Show recent error logs
echo "<h2>Recent Mail Errors:</h2>";
$mailLogPath = __DIR__ . '/mail_error.log';
if (file_exists($mailLogPath)) {
    $lines = file($mailLogPath);
    $recentLines = array_slice($lines, -5);
    foreach ($recentLines as $line) {
        echo "<p>" . htmlspecialchars($line) . "</p>";
    }
} else {
    echo "<p>No mail error log found</p>";
}

echo "<hr>";
echo "<p><a href='../patient_registration.php'>← Back to Registration</a></p>";
echo "<p><a href='system_check.php'>System Health Check</a></p>";
?>