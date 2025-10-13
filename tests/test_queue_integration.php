<!DOCTYPE html>
<html>
<head>
    <title>Queue Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🔬 Queue Integration Test</h1>
    <p><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
// Include required files
$root_path = dirname(__DIR__);
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

echo "<div class='test-section'>";
echo "<h2>📊 Current Database State</h2>";

// Check appointments with queue information
echo "<h3>Appointments with Queue Data</h3>";
$sql = "SELECT a.appointment_id, a.patient_id, a.status as appointment_status,
               a.scheduled_date, a.scheduled_time,
               qe.queue_entry_id, qe.queue_number, qe.queue_type, 
               qe.priority_level, qe.status as queue_status,
               qe.time_in, qe.time_started, qe.time_completed,
               p.first_name, p.last_name, s.name as service_name
        FROM appointments a
        LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN services s ON a.service_id = s.service_id
        ORDER BY a.created_at DESC
        LIMIT 10";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Appt ID</th><th>Patient</th><th>Service</th><th>Date/Time</th><th>Appt Status</th><th>Queue #</th><th>Queue Type</th><th>Queue Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['appointment_id'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . $row['service_name'] . "</td>";
        echo "<td>" . $row['scheduled_date'] . " " . $row['scheduled_time'] . "</td>";
        echo "<td><strong>" . $row['appointment_status'] . "</strong></td>";
        echo "<td>" . ($row['queue_number'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['queue_type'] ?? 'N/A') . "</td>";
        echo "<td><strong>" . ($row['queue_status'] ?? 'N/A') . "</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No appointments found.</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>🧪 Testing Queue Management Service</h2>";

try {
    $queue_service = new QueueManagementService($pdo);
    
    // Test 1: Get queue statistics
    echo "<h3>Test 1: Queue Statistics</h3>";
    $stats_result = $queue_service->getQueueStatistics();
    if ($stats_result['success']) {
        echo "<div class='success'>✅ Queue statistics retrieved successfully</div>";
        if (!empty($stats_result['statistics'])) {
            echo "<table>";
            echo "<tr><th>Queue Type</th><th>Total</th><th>Waiting</th><th>In Progress</th><th>Done</th><th>Cancelled</th><th>No Show</th><th>Avg Wait (min)</th></tr>";
            foreach ($stats_result['statistics'] as $stat) {
                echo "<tr>";
                echo "<td>" . $stat['queue_type'] . "</td>";
                echo "<td>" . $stat['total_entries'] . "</td>";
                echo "<td>" . $stat['waiting'] . "</td>";
                echo "<td>" . $stat['in_progress'] . "</td>";
                echo "<td>" . $stat['done'] . "</td>";
                echo "<td>" . $stat['cancelled'] . "</td>";
                echo "<td>" . $stat['no_show'] . "</td>";
                echo "<td>" . number_format($stat['avg_waiting_time'] ?? 0, 1) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No queue statistics available for today.</p>";
        }
    } else {
        echo "<div class='error'>❌ Failed to get queue statistics: " . $stats_result['error'] . "</div>";
    }
    
    // Test 2: Get current queue for consultation
    echo "<h3>Test 2: Current Consultation Queue</h3>";
    $queue_result = $queue_service->getQueueByTypeAndDate('consultation');
    if ($queue_result['success']) {
        echo "<div class='success'>✅ Consultation queue retrieved successfully</div>";
        echo "<p>Date: " . $queue_result['date'] . " | Queue Type: " . $queue_result['queue_type'] . "</p>";
        
        if (!empty($queue_result['queue_list'])) {
            echo "<table>";
            echo "<tr><th>Queue #</th><th>Patient</th><th>Service</th><th>Priority</th><th>Status</th><th>Time In</th><th>Facility</th></tr>";
            foreach ($queue_result['queue_list'] as $queue_item) {
                echo "<tr>";
                echo "<td><strong>" . $queue_item['queue_number'] . "</strong></td>";
                echo "<td>" . $queue_item['first_name'] . " " . $queue_item['last_name'] . "</td>";
                echo "<td>" . $queue_item['service_name'] . "</td>";
                echo "<td>" . ucfirst($queue_item['priority_level']) . "</td>";
                echo "<td><strong>" . ucfirst(str_replace('_', ' ', $queue_item['status'])) . "</strong></td>";
                echo "<td>" . $queue_item['time_in'] . "</td>";
                echo "<td>" . $queue_item['facility_name'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No patients in consultation queue today.</p>";
        }
    } else {
        echo "<div class='error'>❌ Failed to get consultation queue: " . $queue_result['error'] . "</div>";
    }
    
    // Test 3: Test queue entry creation (if we have sample data)
    echo "<h3>Test 3: Queue Entry Creation Test</h3>";
    
    // Find a recent appointment without queue entry for testing
    $test_sql = "SELECT a.appointment_id, a.patient_id, a.service_id 
                 FROM appointments a 
                 LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id 
                 WHERE qe.queue_entry_id IS NULL 
                 AND a.status = 'confirmed'
                 LIMIT 1";
    
    $test_result = $conn->query($test_sql);
    if ($test_result && $test_result->num_rows > 0) {
        $test_appointment = $test_result->fetch_assoc();
        
        echo "<p>Testing queue creation for appointment ID: " . $test_appointment['appointment_id'] . "</p>";
        
        $create_result = $queue_service->createQueueEntry(
            $test_appointment['appointment_id'],
            $test_appointment['patient_id'],
            $test_appointment['service_id'],
            'consultation',
            'normal',
            null // System-generated for test
        );
        
        if ($create_result['success']) {
            echo "<div class='success'>✅ Test queue entry created successfully!</div>";
            echo "<p>Queue Number: " . $create_result['queue_number'] . "</p>";
            echo "<p>Queue Type: " . $create_result['queue_type'] . "</p>";
            echo "<p>Priority Level: " . $create_result['priority_level'] . "</p>";
        } else {
            echo "<div class='error'>❌ Failed to create test queue entry: " . $create_result['error'] . "</div>";
        }
    } else {
        echo "<p>No suitable appointment found for testing queue creation.</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception during testing: " . $e->getMessage() . "</div>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>📋 Queue Logs (Recent Activity)</h2>";

$logs_sql = "SELECT ql.*, qe.queue_number, qe.queue_type, 
                    p.first_name, p.last_name,
                    e.first_name as employee_first_name, e.last_name as employee_last_name
             FROM queue_logs ql
             LEFT JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
             LEFT JOIN patients p ON qe.patient_id = p.patient_id
             LEFT JOIN employees e ON ql.performed_by = e.employee_id
             ORDER BY ql.created_at DESC
             LIMIT 10";

$logs_result = $conn->query($logs_sql);
if ($logs_result && $logs_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Date/Time</th><th>Action</th><th>Patient</th><th>Queue #</th><th>Old Status</th><th>New Status</th><th>Performed By</th><th>Remarks</th></tr>";
    while ($log = $logs_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $log['created_at'] . "</td>";
        echo "<td><strong>" . ucfirst($log['action']) . "</strong></td>";
        echo "<td>" . ($log['first_name'] ? $log['first_name'] . " " . $log['last_name'] : 'N/A') . "</td>";
        echo "<td>" . ($log['queue_number'] ?? 'N/A') . "</td>";
        echo "<td>" . ($log['old_status'] ?? 'N/A') . "</td>";
        echo "<td><strong>" . ucfirst(str_replace('_', ' ', $log['new_status'])) . "</strong></td>";
        echo "<td>" . ($log['employee_first_name'] ? $log['employee_first_name'] . " " . $log['employee_last_name'] : 'System') . "</td>";
        echo "<td>" . ($log['remarks'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No queue logs found.</p>";
}

echo "</div>";

echo "<div class='test-section'>";
echo "<h2>✅ Integration Status Summary</h2>";

// Check integration completeness
$integration_checks = [
    'Queue tables exist' => false,
    'Appointments have queue entries' => false,
    'Queue logs are being created' => false,
    'Queue service is functional' => false
];

// Check if queue tables exist
$tables_sql = "SHOW TABLES LIKE 'queue_%'";
$tables_result = $conn->query($tables_sql);
if ($tables_result && $tables_result->num_rows >= 2) {
    $integration_checks['Queue tables exist'] = true;
}

// Check if recent appointments have queue entries
$recent_sql = "SELECT COUNT(*) as total_appointments,
                      SUM(CASE WHEN qe.queue_entry_id IS NOT NULL THEN 1 ELSE 0 END) as with_queue
               FROM appointments a
               LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
               WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$recent_result = $conn->query($recent_sql);
if ($recent_result) {
    $recent_data = $recent_result->fetch_assoc();
    if ($recent_data['total_appointments'] > 0 && $recent_data['with_queue'] > 0) {
        $integration_checks['Appointments have queue entries'] = true;
    }
}

// Check if queue logs exist
$logs_count_sql = "SELECT COUNT(*) as log_count FROM queue_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$logs_count_result = $conn->query($logs_count_sql);
if ($logs_count_result) {
    $logs_count = $logs_count_result->fetch_assoc();
    if ($logs_count['log_count'] > 0) {
        $integration_checks['Queue logs are being created'] = true;
    }
}

// Queue service functionality was tested above
try {
    $queue_service = new QueueManagementService($pdo);
    $test_stats = $queue_service->getQueueStatistics();
    if ($test_stats['success']) {
        $integration_checks['Queue service is functional'] = true;
    }
} catch (Exception $e) {
    // Service not functional
}

echo "<ul>";
foreach ($integration_checks as $check => $status) {
    if ($status) {
        echo "<li class='success'>✅ {$check}</li>";
    } else {
        echo "<li class='error'>❌ {$check}</li>";
    }
}
echo "</ul>";

$passed_checks = array_sum($integration_checks);
$total_checks = count($integration_checks);

if ($passed_checks === $total_checks) {
    echo "<div class='success'>";
    echo "<h3>🎉 Queue Integration is COMPLETE and WORKING!</h3>";
    echo "<p>All integration checks passed. The queue system is ready for use.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ Queue Integration is PARTIAL</h3>";
    echo "<p>Passed: {$passed_checks}/{$total_checks} checks. Some components may need attention.</p>";
    echo "</div>";
}

echo "</div>";
?>

<div class="test-section">
    <h2>📝 Next Steps</h2>
    <p>If the integration is working correctly, you can:</p>
    <ol>
        <li><strong>Create appointments</strong> - New appointments will automatically generate queue entries</li>
        <li><strong>View queue information</strong> - Check the updated appointments page to see queue numbers and status</li>
        <li><strong>Manage queues</strong> - Use the queue management API for staff interfaces</li>
        <li><strong>Monitor logs</strong> - All queue actions are logged for audit purposes</li>
    </ol>
    
    <h3>🔧 Staff Interface</h3>
    <p>To build the staff queue management interface, you can use the API endpoint:</p>
    <ul>
        <li><code>GET /api/queue_management.php?action=queue_list&queue_type=consultation</code> - Get current queue</li>
        <li><code>PUT /api/queue_management.php</code> with action=update_status - Update queue status</li>
        <li><code>GET /api/queue_management.php?action=statistics</code> - Get queue statistics</li>
    </ul>
</div>

</body>
</html>