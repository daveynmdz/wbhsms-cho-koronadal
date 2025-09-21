<?php
// system_check.php - Comprehensive system health check for registration functionality
session_start();
require_once '../../../config/env.php';

// Load PHPMailer classes
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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration System Health Check</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 40px; line-height: 1.6; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px; }
        .test-section { margin: 20px 0; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; }
        .success { color: #155724; background: #d4edda; padding: 8px 12px; border-radius: 4px; }
        .error { color: #721c24; background: #f8d7da; padding: 8px 12px; border-radius: 4px; }
        .warning { color: #856404; background: #fff3cd; padding: 8px 12px; border-radius: 4px; }
        .info { color: #0c5460; background: #d1ecf1; padding: 8px 12px; border-radius: 4px; }
        .test-item { margin: 10px 0; padding: 8px; border-left: 4px solid #ccc; }
        .test-item.pass { border-left-color: #28a745; }
        .test-item.fail { border-left-color: #dc3545; }
        .test-item.warn { border-left-color: #ffc107; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        h1, h2, h3 { color: #333; }
        .status-icon { font-weight: bold; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏥 CHO Koronadal - Registration System Health Check</h1>
        <p>This page performs comprehensive tests on all registration system components.</p>
        <p><strong>Timestamp:</strong> <?= date('Y-m-d H:i:s') ?></p>
    </div>

<?php

function outputTest($name, $result, $message = '', $details = '') {
    $status = $result ? 'pass' : 'fail';
    $icon = $result ? '✅' : '❌';
    echo "<div class='test-item {$status}'>";
    echo "<span class='status-icon'>{$icon}</span>";
    echo "<strong>{$name}</strong>";
    if ($message) echo " - {$message}";
    if ($details) echo "<pre>{$details}</pre>";
    echo "</div>";
    return $result;
}

function outputWarning($name, $message, $details = '') {
    echo "<div class='test-item warn'>";
    echo "<span class='status-icon'>⚠️</span>";
    echo "<strong>{$name}</strong> - {$message}";
    if ($details) echo "<pre>{$details}</pre>";
    echo "</div>";
}

function outputInfo($name, $message) {
    echo "<div class='test-item'>";
    echo "<span class='status-icon'>ℹ️</span>";
    echo "<strong>{$name}</strong> - {$message}";
    echo "</div>";
}

$allTests = [];

// === DATABASE TESTS ===
echo "<div class='test-section'>";
echo "<h2>🗄️ Database Connectivity</h2>";

try {
    $stmt = $pdo->query("SELECT 1");
    $allTests[] = outputTest("Database Connection", true, "Successfully connected to database");
    
    // Test patients table structure
    $stmt = $pdo->query("DESCRIBE patients");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['patient_id', 'username', 'first_name', 'last_name', 'email', 'contact_number', 'password_hash'];
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        $allTests[] = outputTest("Patients Table Structure", true, "All required columns present");
    } else {
        $allTests[] = outputTest("Patients Table Structure", false, "Missing columns: " . implode(', ', $missingColumns));
    }
    
    // Test barangay table
    $stmt = $pdo->query("SELECT COUNT(*) FROM barangay WHERE status = 'active'");
    $barangayCount = $stmt->fetchColumn();
    $allTests[] = outputTest("Barangay Data", $barangayCount > 0, "Found {$barangayCount} active barangays");
    
    // Test emergency_contact table
    $stmt = $pdo->query("DESCRIBE emergency_contact");
    $emergencyColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredEmergencyColumns = ['patient_id', 'emergency_first_name', 'emergency_last_name', 'emergency_relationship', 'emergency_contact_number'];
    $missingEmergencyColumns = array_diff($requiredEmergencyColumns, $emergencyColumns);
    
    if (empty($missingEmergencyColumns)) {
        $allTests[] = outputTest("Emergency Contact Table", true, "All required columns present");
    } else {
        $allTests[] = outputTest("Emergency Contact Table", false, "Missing columns: " . implode(', ', $missingEmergencyColumns));
    }
    
} catch (Exception $e) {
    $allTests[] = outputTest("Database Connection", false, "Failed to connect: " . $e->getMessage());
}

echo "</div>";

// === ENVIRONMENT VARIABLES ===
echo "<div class='test-section'>";
echo "<h2>🔧 Environment Configuration</h2>";

$requiredEnvVars = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_PORT'];
foreach ($requiredEnvVars as $var) {
    $value = $_ENV[$var] ?? '';
    if ($var === 'SMTP_PASS') {
        $display = $value ? '***SET***' : 'NOT SET';
        $isSet = !empty($value);
        if ($value === 'disabled') {
            outputWarning($var, "Set to 'disabled' (development mode)", $display);
        } else {
            $allTests[] = outputTest($var, $isSet, $display);
        }
    } else {
        $allTests[] = outputTest($var, !empty($value), $value ?: 'NOT SET');
    }
}

echo "</div>";

// === FILE PERMISSIONS ===
echo "<div class='test-section'>";
echo "<h2>📁 File System</h2>";

$registrationFiles = [
    '../patient_registration.php',
    '../register_patient.php', 
    '../registration_otp.php',
    '../registration_success.php',
    '../resend_registration_otp.php'
];

foreach ($registrationFiles as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    $readable = $exists && is_readable($path);
    
    if ($exists && $readable) {
        $allTests[] = outputTest($file, true, "File exists and is readable");
    } elseif ($exists) {
        $allTests[] = outputTest($file, false, "File exists but is not readable");
    } else {
        $allTests[] = outputTest($file, false, "File does not exist");
    }
}

// Check log file writability
$logFile = __DIR__ . '/mail_error.log';
$logWritable = is_writable(dirname($logFile));
if ($logWritable) {
    $allTests[] = outputTest("Log Directory Writable", true, "Can write to " . dirname($logFile));
} else {
    $allTests[] = outputTest("Log Directory Writable", false, "Cannot write to " . dirname($logFile));
}

echo "</div>";

// === PHPMAILER TESTS ===
echo "<div class='test-section'>";
echo "<h2>📧 Email System</h2>";

$allTests[] = outputTest("PHPMailer Class", class_exists('\PHPMailer\PHPMailer\PHPMailer'), "PHPMailer loaded successfully");

// Test SMTP connection
$bypassEmail = empty($_ENV['SMTP_PASS']) || $_ENV['SMTP_PASS'] === 'disabled';

if ($bypassEmail) {
    outputInfo("Email Mode", "Development mode - emails bypassed");
    outputInfo("SMTP Test", "Skipped in development mode");
} else {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USER'] ?? '';
        $mail->Password = $_ENV['SMTP_PASS'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
        $mail->Timeout = 10; // Short timeout for testing
        
        if ($mail->smtpConnect()) {
            $allTests[] = outputTest("SMTP Connection", true, "Successfully connected to SMTP server");
            $mail->smtpClose();
        } else {
            $allTests[] = outputTest("SMTP Connection", false, "Failed to connect to SMTP server");
        }
    } catch (Exception $e) {
        $allTests[] = outputTest("SMTP Connection", false, "SMTP Error: " . $e->getMessage());
    }
}

echo "</div>";

// === SESSION TESTS ===
echo "<div class='test-section'>";
echo "<h2>🍪 Session Management</h2>";

$sessionWorking = session_status() === PHP_SESSION_ACTIVE;
$allTests[] = outputTest("Session Status", $sessionWorking, $sessionWorking ? "Session active" : "Session not active");

if ($sessionWorking) {
    // Test session write
    $_SESSION['test_key'] = 'test_value';
    $sessionWrite = isset($_SESSION['test_key']) && $_SESSION['test_key'] === 'test_value';
    $allTests[] = outputTest("Session Write", $sessionWrite, "Can write to session");
    
    if ($sessionWrite) {
        unset($_SESSION['test_key']);
        $sessionDelete = !isset($_SESSION['test_key']);
        $allTests[] = outputTest("Session Delete", $sessionDelete, "Can delete from session");
    }
}

echo "</div>";

// === REGISTRATION FLOW TESTS ===
echo "<div class='test-section'>";
echo "<h2>🔄 Registration Flow Logic</h2>";

// Test age calculation (JavaScript equivalent in PHP)
function calculateAge($birthDate) {
    $today = new DateTime();
    $birth = new DateTime($birthDate);
    $age = $today->diff($birth)->y;
    return $age;
}

// Test age scenarios
$testDOBs = [
    '2010-01-01' => 'Minor (Emergency contact required)',
    '1980-01-01' => 'Adult (No special requirements)',
    '1960-01-01' => 'Senior Citizen eligible'
];

foreach ($testDOBs as $dob => $description) {
    $age = calculateAge($dob);
    outputInfo("Age Calculation", "DOB: {$dob} → Age: {$age} → {$description}");
}

// Test password validation
function validatePassword($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/\d/', $password);
}

$testPasswords = [
    'Password123' => true,
    'password123' => false,
    'PASSWORD123' => false,
    'Password' => false,
    'Pass123' => false
];

foreach ($testPasswords as $password => $expected) {
    $result = validatePassword($password);
    $allTests[] = outputTest("Password Validation", $result === $expected, "'{$password}' → " . ($result ? 'Valid' : 'Invalid'));
}

echo "</div>";

// === SECURITY TESTS ===
echo "<div class='test-section'>";
echo "<h2>🔒 Security Checks</h2>";

// Check if error display is properly configured
$displayErrors = ini_get('display_errors');
$debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';

if ($debug) {
    outputWarning("Debug Mode", "Debug mode is enabled (development)", "display_errors: " . $displayErrors);
} else {
    $allTests[] = outputTest("Production Security", $displayErrors === '0' || $displayErrors === '', "Error display properly configured");
}

// Check session security settings
$sessionSecure = ini_get('session.cookie_secure');
$sessionHttpOnly = ini_get('session.cookie_httponly');
$sessionSameSite = ini_get('session.cookie_samesite');

outputInfo("Session Cookie Secure", $sessionSecure ? 'Enabled' : 'Disabled');
outputInfo("Session HTTP Only", $sessionHttpOnly ? 'Enabled' : 'Disabled');  
outputInfo("Session SameSite", $sessionSameSite ?: 'Not set');

echo "</div>";

// === OVERALL SUMMARY ===
echo "<div class='test-section'>";
echo "<h2>📊 Summary</h2>";

$totalTests = count($allTests);
$passedTests = count(array_filter($allTests));
$failedTests = $totalTests - $passedTests;

if ($failedTests === 0) {
    echo "<div class='success'>";
    echo "<h3>🎉 All Systems Operational</h3>";
    echo "<p>All {$totalTests} tests passed. The registration system is ready for use.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ Issues Detected</h3>";
    echo "<p>{$failedTests} out of {$totalTests} tests failed. Please address the issues above before using the registration system.</p>";
    echo "</div>";
}

echo "<div class='info'>";
echo "<p><strong>Test Results:</strong> {$passedTests} passed, {$failedTests} failed out of {$totalTests} total tests.</p>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>✅ Test the registration form at <a href='patient_registration.php'>patient_registration.php</a></li>";
echo "<li>✅ Check debug information at <a href='debug_registration.php'>debug_registration.php</a></li>";
echo "<li>✅ Test direct registration at <a href='test_registration_no_email.php'>test_registration_no_email.php</a></li>";
echo "</ul>";
echo "</div>";

echo "</div>";
?>

    <div class="test-section">
        <h2>🔗 Quick Links</h2>
        <p><a href="../patient_registration.php">Patient Registration Form</a></p>
        <p><a href="debug_registration.php">Debug Information</a></p>
        <p><a href="test_registration_no_email.php">Test Registration (No Email)</a></p>
        <p><a href="test_registration.php">Database Overview & Testing</a></p>
        <p><a href="../../auth/patient_login.php">Patient Login</a></p>
        <p><a href="system_check.php">Refresh System Check</a></p>
    </div>

</body>
</html>