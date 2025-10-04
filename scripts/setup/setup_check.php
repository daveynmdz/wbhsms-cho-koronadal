<?php
/**
 * XAMPP Setup Validation Script
 * CHO Koronadal WBHSMS
 */

$checks = [];
$overall_status = true;

// Check PHP version
$php_version = phpversion();
$checks['PHP Version'] = [
    'status' => version_compare($php_version, '7.4', '>='),
    'message' => $php_version . (version_compare($php_version, '7.4', '>=') ? ' ✅' : ' ❌ (Need 7.4+)'),
    'required' => true
];

// Check required PHP extensions  
$required_extensions = ['mysqli', 'pdo', 'pdo_mysql'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $checks["PHP Extension: $ext"] = [
        'status' => $loaded,
        'message' => $loaded ? 'Loaded ✅' : 'Missing ❌',
        'required' => true
    ];
    if (!$loaded) $overall_status = false;
}

// Check if .env file exists
$env_exists = file_exists(__DIR__ . '/.env');
$checks['.env Configuration'] = [
    'status' => $env_exists,
    'message' => $env_exists ? 'Found ✅' : 'Copy .env.example to .env ⚠️',
    'required' => false
];

// Check database connection
$db_status = true;
$db_message = '';
try {
    if ($env_exists) {
        require_once __DIR__ . '/config/db.php';
        $db_message = 'Connected successfully ✅';
    } else {
        $db_status = false;
        $db_message = 'Cannot test - no .env file ⚠️';
    }
} catch (Exception $e) {
    $db_status = false;
    $db_message = 'Connection failed: ' . $e->getMessage() . ' ❌';
    $overall_status = false;
}

$checks['Database Connection'] = [
    'status' => $db_status,
    'message' => $db_message,
    'required' => true
];

// Check if critical files exist
$critical_files = [
    'index.php' => 'Homepage',
    'config/env.php' => 'Environment Config',
    'config/db.php' => 'Database Config',
    'database/wbhsms_cho.sql' => 'Database Schema',
    'pages/patient/auth/patient_login.php' => 'Patient Login'
];

foreach ($critical_files as $file => $desc) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $checks[$desc] = [
        'status' => $exists,
        'message' => $exists ? 'Found ✅' : 'Missing ❌',
        'required' => true
    ];
    if (!$exists) $overall_status = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XAMPP Setup Check - CHO Koronadal WBHSMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .status-overall {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 30px;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .checks-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .checks-table th, .checks-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .checks-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .check-pass {
            color: #28a745;
        }
        .check-fail {
            color: #dc3545;
        }
        .check-warn {
            color: #ffc107;
        }
        .actions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-success:hover {
            background: #1e7e34;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 CHO Koronadal WBHSMS</h1>
            <h2>XAMPP Setup Validation</h2>
        </div>

        <div class="status-overall <?= $overall_status ? 'status-success' : 'status-warning' ?>">
            <?php if ($overall_status): ?>
                🎉 System Ready! All critical checks passed.
            <?php else: ?>
                ⚠️ Setup Issues Found - Please review the checks below.
            <?php endif; ?>
        </div>

        <table class="checks-table">
            <thead>
                <tr>
                    <th>Component</th>
                    <th>Status</th>
                    <th>Required</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($checks as $name => $check): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($name) ?></strong></td>
                    <td class="<?= $check['status'] ? 'check-pass' : ($check['required'] ? 'check-fail' : 'check-warn') ?>">
                        <?= htmlspecialchars($check['message']) ?>
                    </td>
                    <td><?= $check['required'] ? '✅ Yes' : '⚠️ Optional' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <h3>Next Steps:</h3>
            <?php if ($overall_status): ?>
                <p>✅ Your XAMPP setup is ready! You can now use the system.</p>
                <a href="testdb.php" class="btn">🗄️ Test Database</a>
                <a href="../../index.php" class="btn btn-success">🏠 Go to Homepage</a>
                <a href="../../pages/patient/auth/patient_login.php" class="btn">👤 Patient Login</a>
            <?php else: ?>
                <p>❌ Please fix the issues above before proceeding:</p>
                <ul>
                    <li>Make sure XAMPP Apache and MySQL are running</li>
                    <li>Copy .env.example to .env</li>
                    <li>Create database 'wbhsms_cho' in phpMyAdmin</li>
                    <li>Import database/wbhsms_cho.sql</li>
                </ul>
                <a href="" class="btn">🔄 Refresh Check</a>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 30px; font-size: 12px; color: #666;">
            <p>City Health Office of Koronadal - Healthcare Management System</p>
        </div>
    </div>
</body>
</html>