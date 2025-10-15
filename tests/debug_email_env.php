<?php
// Debug Environment Variables
// Path: c:\xampp\htdocs\wbhsms-cho-koronadal-1\tests\debug_email_env.php

echo "<h2>Email Environment Debug</h2>\n";

// Load environment
require_once dirname(__DIR__) . '/config/env.php';

echo "<h3>Environment Variables:</h3>\n";
echo "SMTP_PASS: " . (getenv('SMTP_PASS') ?: 'NOT SET') . "\n";
echo "SMTP_PASSWORD: " . (getenv('SMTP_PASSWORD') ?: 'NOT SET') . "\n";
echo "SMTP_USER: " . (getenv('SMTP_USER') ?: 'NOT SET') . "\n";
echo "SMTP_USERNAME: " . (getenv('SMTP_USERNAME') ?: 'NOT SET') . "\n";
echo "SMTP_HOST: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";

echo "\n<h3>\$_ENV Array:</h3>\n";
echo "SMTP_PASS: " . ($_ENV['SMTP_PASS'] ?? 'NOT SET') . "\n";
echo "SMTP_PASSWORD: " . ($_ENV['SMTP_PASSWORD'] ?? 'NOT SET') . "\n";
echo "SMTP_USER: " . ($_ENV['SMTP_USER'] ?? 'NOT SET') . "\n";
echo "SMTP_USERNAME: " . ($_ENV['SMTP_USERNAME'] ?? 'NOT SET') . "\n";

echo "\n<h3>Email Bypass Check (FIXED):</h3>\n";
$bypassEmail = empty(getenv('SMTP_PASS')) || getenv('SMTP_PASS') === 'disabled';
echo "Bypass Email: " . ($bypassEmail ? 'YES (THIS IS WHY EMAILS ARE NOT SENT)' : 'NO - EMAILS SHOULD WORK') . "\n";

// Check .env file content
echo "\n<h3>.env File Content:</h3>\n";
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile);
    foreach ($lines as $line) {
        if (strpos($line, 'SMTP') !== false) {
            echo htmlspecialchars(trim($line)) . "\n";
        }
    }
} else {
    echo ".env file not found\n";
}

echo "\n<h3>Test Email Configuration (FIXED):</h3>\n";
echo "Host: " . (getenv('SMTP_HOST') ?: 'NOT SET') . "\n";
echo "Port: " . (getenv('SMTP_PORT') ?: 'NOT SET') . "\n";
echo "User: " . (getenv('SMTP_USER') ?: getenv('SMTP_USERNAME') ?: 'NOT SET') . "\n";
echo "Pass: " . ((getenv('SMTP_PASS') ?: getenv('SMTP_PASSWORD') ?: '') ? 'SET' : 'NOT SET') . "\n";
?>