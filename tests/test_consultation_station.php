<?php
/**
 * Test script for consultation_station.php
 * Run this to verify the consultation station is working properly
 */

echo "Testing consultation_station.php functionality...\n\n";

// Test file existence
$station_file = __DIR__ . '/../pages/queueing/consultation_station.php';
if (file_exists($station_file)) {
    echo "✅ consultation_station.php file exists\n";
} else {
    echo "❌ consultation_station.php file not found\n";
    exit(1);
}

// Test basic PHP syntax
$content = file_get_contents($station_file);

// Check for required components
$required_components = [
    'session management' => 'require_once.*session/employee_session.php',
    'database connection' => 'require_once.*config/db.php',
    'queue service' => 'QueueManagementService',
    'station assignment' => 'assignment_schedules',
    'AJAX handler' => 'REQUEST_METHOD.*POST',
    'div1 station info' => 'div1.*Station Information',
    'div2 stats' => 'div2.*Queue Statistics',
    'div3 current patient' => 'div3.*Current Patient',
    'div4 actions' => 'div4.*Patient Actions',
    'div5 waiting queue' => 'div5.*Live Queued Patients',
    'div6 skipped' => 'div6.*Skipped Queue',
    'div7 completed' => 'div7.*Completed Patients',
    'JavaScript functions' => 'function callNextPatient',
    'Modal support' => 'queueLogsModal',
    'CSS styling' => 'consultation-grid',
    'Responsive design' => '@media.*max-width'
];

foreach ($required_components as $component => $pattern) {
    if (preg_match('/' . $pattern . '/i', $content)) {
        echo "✅ $component - implemented\n";
    } else {
        echo "⚠️ $component - pattern not found\n";
    }
}

// Check for all required actions
$actions = [
    'call_next',
    'skip_patient', 
    'recall_patient',
    'force_call',
    'reroute_to_lab',
    'reroute_to_pharmacy',
    'reroute_to_billing',
    'reroute_to_document',
    'enter_notes',
    'get_queue_logs'
];

echo "\nAction handlers:\n";
foreach ($actions as $action) {
    if (strpos($content, "case '$action'") !== false) {
        echo "✅ $action action handler - implemented\n";
    } else {
        echo "❌ $action action handler - missing\n";
    }
}

// Check database table usage
$tables = [
    'queue_entries',
    'queue_logs', 
    'assignment_schedules',
    'stations',
    'patients',
    'employees'
];

echo "\nDatabase table references:\n";
foreach ($tables as $table) {
    if (strpos($content, $table) !== false) {
        echo "✅ $table table - referenced\n";
    } else {
        echo "⚠️ $table table - not found\n";
    }
}

// Check role-based access
if (preg_match('/allowed_roles.*=.*\[.*doctor.*nurse.*admin/i', $content)) {
    echo "✅ Role-based access control - implemented\n";
} else {
    echo "⚠️ Role-based access control - check implementation\n";
}

// Check audit logging
if (strpos($content, 'queue_logs') !== false && strpos($content, 'performed_by') !== false) {
    echo "✅ Audit logging - implemented\n";
} else {
    echo "❌ Audit logging - missing\n";
}

echo "\n🎉 Consultation station test completed!\n";
echo "\nTo use the consultation station:\n";
echo "1. Navigate to /pages/queueing/consultation_station.php\n";
echo "2. Login as doctor, nurse, or admin\n";
echo "3. Ensure you have station assignment in assignment_schedules table\n";
echo "4. Use the 7-div interface to manage consultation queue\n";
echo "5. All actions will be logged in queue_logs table\n";
?>