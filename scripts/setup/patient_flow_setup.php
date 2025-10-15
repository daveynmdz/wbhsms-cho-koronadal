<?php
/**
 * Setup Script for Enhanced Patient Flow System
 * Creates audit tables and validates system readiness
 */

require_once '../../config/db.php';
require_once '../../utils/patient_flow_audit_logger.php';

$results = [];
$success = true;

try {
    // Initialize audit logger
    $auditLogger = new PatientFlowAuditLogger($pdo);
    
    // Create audit tables
    $results[] = ['action' => 'Create Audit Tables', 'status' => 'Processing...'];
    $audit_tables_created = $auditLogger->createAuditTables();
    
    if ($audit_tables_created) {
        $results[count($results)-1]['status'] = '✅ Success';
    } else {
        $results[count($results)-1]['status'] = '❌ Failed';
        $success = false;
    }
    
    // Check if is_philhealth column exists in patients table
    $results[] = ['action' => 'Check PhilHealth Column', 'status' => 'Processing...'];
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM patients LIKE 'is_philhealth'");
        if ($check_column->rowCount() == 0) {
            // Add column if it doesn't exist
            $pdo->exec("ALTER TABLE patients ADD COLUMN is_philhealth BOOLEAN DEFAULT 0");
            $results[count($results)-1]['status'] = '✅ Added is_philhealth column';
        } else {
            $results[count($results)-1]['status'] = '✅ Column exists';
        }
    } catch (Exception $e) {
        $results[count($results)-1]['status'] = '❌ Error: ' . $e->getMessage();
        $success = false;
    }
    
    // Check if philhealth_id_number column exists
    $results[] = ['action' => 'Check PhilHealth ID Column', 'status' => 'Processing...'];
    try {
        $check_column = $pdo->query("SHOW COLUMNS FROM patients LIKE 'philhealth_id_number'");
        if ($check_column->rowCount() == 0) {
            // Add column if it doesn't exist
            $pdo->exec("ALTER TABLE patients ADD COLUMN philhealth_id_number VARCHAR(15) NULL");
            $results[count($results)-1]['status'] = '✅ Added philhealth_id_number column';
        } else {
            $results[count($results)-1]['status'] = '✅ Column exists';
        }
    } catch (Exception $e) {
        $results[count($results)-1]['status'] = '❌ Error: ' . $e->getMessage();
        $success = false;
    }
    
    // Validate core tables exist
    $core_tables = ['patients', 'queue_entries', 'queue_logs', 'visits', 'stations', 'assignment_schedules'];
    foreach ($core_tables as $table) {
        $results[] = ['action' => "Validate table: {$table}", 'status' => 'Processing...'];
        try {
            $check_table = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($check_table->rowCount() > 0) {
                $results[count($results)-1]['status'] = '✅ Table exists';
            } else {
                $results[count($results)-1]['status'] = '❌ Table missing';
                $success = false;
            }
        } catch (Exception $e) {
            $results[count($results)-1]['status'] = '❌ Error: ' . $e->getMessage();
            $success = false;
        }
    }
    
} catch (Exception $e) {
    $results[] = ['action' => 'System Setup', 'status' => '❌ Fatal Error: ' . $e->getMessage()];
    $success = false;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Flow System Setup</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .setup-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 Patient Flow System Setup</h1>
            <p>Enhanced PhilHealth-aware Queue Management System</p>
        </div>

        <h2>Setup Results</h2>
        <?php foreach ($results as $result): ?>
            <div class="setup-item">
                <strong><?= htmlspecialchars($result['action']) ?></strong><br>
                <span><?= htmlspecialchars($result['status']) ?></span>
            </div>
        <?php endforeach; ?>

        <div style="margin-top: 30px; text-align: center;">
            <?php if ($success): ?>
                <div class="success">
                    <h3>✅ Setup Completed Successfully!</h3>
                    <p>Your Patient Flow System is ready for deployment.</p>
                </div>
                <a href="../queueing/checkin.php" class="btn">Go to Check-In</a>
                <a href="../queueing/dashboard.php" class="btn">Queue Dashboard</a>
            <?php else: ?>
                <div class="error">
                    <h3>❌ Setup Issues Detected</h3>
                    <p>Please resolve the issues above before proceeding.</p>
                </div>
                <button onclick="window.location.reload()" class="btn">Retry Setup</button>
            <?php endif; ?>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #e9ecef; border-radius: 5px;">
            <h3>System Features Enabled</h3>
            <ul>
                <li>✅ PhilHealth status capture and validation during check-in</li>
                <li>✅ Automatic routing based on PhilHealth membership</li>
                <li>✅ Double consultation workflow for non-PhilHealth patients</li>
                <li>✅ Service ID restrictions (lab-only, document services)</li>
                <li>✅ Time-based lab requeue rules (4:00 PM cutoff)</li>
                <li>✅ Comprehensive audit logging and patient journey tracking</li>
                <li>✅ Next-day referral system for late lab completions</li>
            </ul>
        </div>
    </div>
</body>
</html>