<?php
/**
 * Production Security Check Script
 * Run this script to verify your production security settings
 * Version 2.0 - Enhanced security validation
 */

echo "=== WBHSMS Production Security Check v2.0 ===\n\n";

$issues_found = 0;
$warnings_found = 0;

// Check if we're in production mode
$app_debug = getenv('APP_DEBUG');
$smtp_pass = getenv('SMTP_PASS');

echo "1. Debug Mode Status:\n";
if ($app_debug === '1') {
    echo "   ❌ CRITICAL: APP_DEBUG is enabled! This will show OTP codes in production.\n";
    echo "   📝 Set APP_DEBUG=0 in your environment variables.\n";
    $issues_found++;
} elseif ($app_debug === '0') {
    echo "   ✅ APP_DEBUG is properly disabled.\n";
} else {
    echo "   ⚠️  APP_DEBUG is not set (defaults to disabled).\n";
    echo "   📝 Recommended: Explicitly set APP_DEBUG=0 in your environment.\n";
    $warnings_found++;
}

echo "\n2. Email Configuration:\n";
if (empty($smtp_pass) || $smtp_pass === 'disabled') {
    echo "   ❌ CRITICAL: SMTP is not configured! OTP emails won't be sent.\n";
    echo "   📝 Configure SMTP_PASS and other email settings in your environment.\n";
    echo "   📝 Without SMTP, users won't receive OTP codes by email.\n";
    $issues_found++;
} else {
    echo "   ✅ SMTP appears to be configured.\n";
    
    // Additional SMTP checks
    $smtp_host = getenv('SMTP_HOST');
    $smtp_user = getenv('SMTP_USER');
    if (empty($smtp_host)) {
        echo "   ⚠️  SMTP_HOST not configured.\n";
        $warnings_found++;
    }
    if (empty($smtp_user)) {
        echo "   ⚠️  SMTP_USER not configured.\n";
        $warnings_found++;
    }
}

echo "\n3. Database Security:\n";
$db_pass = getenv('DB_PASSWORD');
if (empty($db_pass)) {
    echo "   ⚠️  Database password not set or empty.\n";
    echo "   📝 Ensure DB_PASSWORD is configured for production.\n";
    $warnings_found++;
} else {
    echo "   ✅ Database password is configured.\n";
}

// Check database connection
try {
    $root_path = dirname(dirname(__DIR__));
    require_once $root_path . '/config/env.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        echo "   ✅ Database connection successful.\n";
    } else {
        echo "   ❌ Database connection failed.\n";
        $issues_found++;
    }
} catch (Exception $e) {
    echo "   ❌ Database connection error: " . $e->getMessage() . "\n";
    $issues_found++;
}

echo "\n4. Security File Checks:\n";
$security_files = [
    'pages/patient/registration/registration_otp.php',
    'pages/patient/registration/register_patient.php',
    'pages/patient/registration/resend_registration_otp.php'
];

foreach ($security_files as $file) {
    $full_path = dirname(dirname(__DIR__)) . '/' . $file;
    if (file_exists($full_path)) {
        $content = file_get_contents($full_path);
        if (strpos($content, "getenv('APP_DEBUG')") !== false) {
            echo "   ✅ {$file} - APP_DEBUG protection implemented\n";
        } else {
            echo "   ⚠️  {$file} - APP_DEBUG protection missing\n";
            $warnings_found++;
        }
    } else {
        echo "   ❌ {$file} - File not found\n";
        $issues_found++;
    }
}

echo "\n5. Current Environment Status:\n";
$env_vars = [
    'APP_DEBUG' => getenv('APP_DEBUG') ?: 'not set',
    'APP_ENV' => getenv('APP_ENV') ?: 'not set',
    'DB_HOST' => getenv('DB_HOST') ?: 'not set',
    'DB_DATABASE' => getenv('DB_DATABASE') ?: 'not set',
    'DB_USERNAME' => getenv('DB_USERNAME') ?: 'not set',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ? '***configured***' : 'not set',
    'SMTP_HOST' => getenv('SMTP_HOST') ?: 'not set',
    'SMTP_USER' => getenv('SMTP_USER') ?: 'not set',
    'SMTP_PASS' => getenv('SMTP_PASS') ? '***configured***' : 'not set',
    'SMTP_FROM' => getenv('SMTP_FROM') ?: 'not set',
    'SMTP_FROM_NAME' => getenv('SMTP_FROM_NAME') ?: 'not set'
];

foreach ($env_vars as $key => $value) {
    echo "   {$key}: {$value}\n";
}

echo "\n6. Production Readiness Summary:\n";
if ($issues_found === 0 && $warnings_found === 0) {
    echo "   🟢 EXCELLENT: All security checks passed!\n";
    echo "   ✅ Ready for production deployment.\n";
} elseif ($issues_found === 0) {
    echo "   🟡 GOOD: No critical issues found.\n";
    echo "   ⚠️  {$warnings_found} warnings to address.\n";
    echo "   ✅ Ready for production deployment with minor improvements.\n";
} else {
    echo "   🔴 CRITICAL: {$issues_found} critical issues found.\n";
    echo "   ❌ NOT READY for production deployment.\n";
    echo "   📝 Address all critical issues before deploying.\n";
}

echo "\n=== Security Recommendations ===\n";
echo "📝 Ensure the following environment variables are set:\n";
echo "   CRITICAL:\n";
echo "   - APP_DEBUG=0 (disable debug mode)\n";
echo "   - SMTP_PASS=your.email.password (for OTP emails)\n";
echo "   - DB_PASSWORD=secure.database.password\n";
echo "\n   RECOMMENDED:\n";
echo "   - APP_ENV=production\n";
echo "   - SMTP_HOST=smtp.gmail.com\n";
echo "   - SMTP_PORT=587\n";
echo "   - SMTP_USER=your.email@domain.com\n";
echo "   - SMTP_FROM=noreply@chokoronadal.gov.ph\n";
echo "   - SMTP_FROM_NAME=CHO Koronadal Health System\n";

echo "\n=== End Security Check ===\n";
echo "\n💡 For Coolify deployment:\n";
echo "   1. Set all environment variables in Coolify environment settings\n";
echo "   2. Restart your application after changing environment variables\n";
echo "   3. Test OTP functionality thoroughly after deployment\n";
echo "   4. Monitor logs for any errors during registration process\n";
echo "   5. Verify no debug messages appear in the user interface\n";