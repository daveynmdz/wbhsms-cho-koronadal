<?php
/**
 * Queue Audit Integrity Validator
 * 
 * This script validates the integrity of queue_entries and queue_logs relationship
 * Ensures every queue operation has proper audit trail logging
 * 
 * Usage: Run this script via browser or CLI to verify queue audit integrity
 */

require_once 'config/db.php';
require_once 'config/session/employee_session.php';

// Override session requirement for testing
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = 1; // Default for testing
    $_SESSION['role'] = 'admin';
    $_SESSION['employee_name'] = 'Test Admin';
}

echo "<h2>Queue Audit Integrity Report</h2>\n";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<hr>\n";

try {
    // Configuration
    $test_date = date('Y-m-d'); // Today's date
    $show_details = isset($_GET['details']) ? true : false;
    
    echo "<h3>Testing Date: {$test_date}</h3>\n";
    echo "<p><a href='?details=1'>Show Details</a> | <a href='?'>Summary Only</a></p>\n";
    echo "<hr>\n";
    
    // 1. Get all queue entries for today
    $queue_query = "
        SELECT 
            qe.queue_entry_id,
            qe.queue_code,
            qe.queue_number,
            qe.status,
            qe.priority_level,
            qe.created_at,
            qe.updated_at,
            qe.station_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            s.name as service_name,
            st.station_name
        FROM queue_entries qe
        LEFT JOIN patients p ON qe.patient_id = p.patient_id
        LEFT JOIN services s ON qe.service_id = s.service_id
        LEFT JOIN stations st ON qe.station_id = st.station_id
        WHERE DATE(qe.created_at) = ?
        ORDER BY qe.created_at ASC
    ";
    
    $stmt = $conn->prepare($queue_query);
    $stmt->bind_param("s", $test_date);
    $stmt->execute();
    $queue_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo "<h3>Queue Entries Analysis</h3>\n";
    echo "<p>Found " . count($queue_entries) . " queue entries for {$test_date}</p>\n";
    
    if (empty($queue_entries)) {
        echo "<p><strong>No queue entries found for today. Create some test appointments to validate the audit trail.</strong></p>\n";
        exit;
    }
    
    // 2. Check each queue entry for corresponding logs
    $missing_logs = [];
    $entries_with_logs = 0;
    $total_logs_found = 0;
    
    if ($show_details) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th>Queue ID</th><th>Queue Code</th><th>Patient</th><th>Status</th><th>Station</th><th>Logs Count</th><th>Log Actions</th>\n";
        echo "</tr>\n";
    }
    
    foreach ($queue_entries as $entry) {
        $queue_entry_id = $entry['queue_entry_id'];
        
        // Get logs for this queue entry
        $log_query = "
            SELECT 
                log_id,
                action,
                old_status,
                new_status,
                remarks,
                performed_by,
                created_at
            FROM queue_logs 
            WHERE queue_entry_id = ?
            ORDER BY created_at ASC
        ";
        
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("i", $queue_entry_id);
        $log_stmt->execute();
        $logs = $log_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $log_count = count($logs);
        $total_logs_found += $log_count;
        
        if ($log_count > 0) {
            $entries_with_logs++;
        } else {
            $missing_logs[] = $entry;
        }
        
        if ($show_details) {
            $log_actions = [];
            foreach ($logs as $log) {
                $log_actions[] = $log['action'] . "({$log['old_status']}→{$log['new_status']})";
            }
            
            echo "<tr>\n";
            echo "<td>{$queue_entry_id}</td>\n";
            echo "<td>" . ($entry['queue_code'] ?: 'N/A') . "</td>\n";
            echo "<td>" . htmlspecialchars($entry['patient_name']) . "</td>\n";
            echo "<td>{$entry['status']}</td>\n";
            echo "<td>" . ($entry['station_name'] ?: 'No Station') . "</td>\n";
            echo "<td style='text-align: center;'>{$log_count}</td>\n";
            echo "<td>" . implode(', ', $log_actions) . "</td>\n";
            echo "</tr>\n";
        }
    }
    
    if ($show_details) {
        echo "</table>\n";
    }
    
    // 3. Summary Report
    echo "<h3>Audit Summary</h3>\n";
    echo "<ul>\n";
    echo "<li><strong>Total Queue Entries:</strong> " . count($queue_entries) . "</li>\n";
    echo "<li><strong>Entries with Logs:</strong> {$entries_with_logs}</li>\n";
    echo "<li><strong>Entries Missing Logs:</strong> " . count($missing_logs) . "</li>\n";
    echo "<li><strong>Total Log Records:</strong> {$total_logs_found}</li>\n";
    echo "<li><strong>Average Logs per Entry:</strong> " . (count($queue_entries) > 0 ? round($total_logs_found / count($queue_entries), 2) : 0) . "</li>\n";
    echo "</ul>\n";
    
    // 4. Missing Logs Details
    if (!empty($missing_logs)) {
        echo "<h3 style='color: red;'>⚠️ Entries Missing Audit Logs</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
        echo "<tr style='background-color: #ffe6e6;'>\n";
        echo "<th>Queue ID</th><th>Queue Code</th><th>Patient</th><th>Status</th><th>Created</th><th>Station</th>\n";
        echo "</tr>\n";
        
        foreach ($missing_logs as $entry) {
            echo "<tr>\n";
            echo "<td>{$entry['queue_entry_id']}</td>\n";
            echo "<td>" . ($entry['queue_code'] ?: 'N/A') . "</td>\n";
            echo "<td>" . htmlspecialchars($entry['patient_name']) . "</td>\n";
            echo "<td>{$entry['status']}</td>\n";
            echo "<td>{$entry['created_at']}</td>\n";
            echo "<td>" . ($entry['station_name'] ?: 'No Station') . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<p><strong>ACTION REQUIRED:</strong> The above queue entries are missing audit logs. This indicates a logging failure during queue operations.</p>\n";
    } else {
        echo "<h3 style='color: green;'>✅ All Queue Entries Have Audit Logs</h3>\n";
        echo "<p>Excellent! Every queue entry has corresponding audit trail records.</p>\n";
    }
    
    // 5. Station ID Validation
    echo "<h3>Station ID Validation</h3>\n";
    $entries_without_station = 0;
    foreach ($queue_entries as $entry) {
        if (empty($entry['station_id'])) {
            $entries_without_station++;
        }
    }
    
    if ($entries_without_station > 0) {
        echo "<p style='color: orange;'>⚠️ Found {$entries_without_station} queue entries without station_id. This may affect queue routing.</p>\n";
    } else {
        echo "<p style='color: green;'>✅ All queue entries have valid station_id assignments.</p>\n";
    }
    
    // 6. Performance Metrics
    echo "<h3>Performance Metrics</h3>\n";
    
    // Check if proper indexes exist
    $index_query = "SHOW INDEX FROM queue_entries WHERE Column_name = 'station_id'";
    $index_result = $conn->query($index_query);
    $station_id_indexed = $index_result->num_rows > 0;
    
    $index_query2 = "SHOW INDEX FROM queue_logs WHERE Column_name = 'queue_entry_id'";
    $index_result2 = $conn->query($index_query2);
    $queue_entry_id_indexed = $index_result2->num_rows > 0;
    
    echo "<ul>\n";
    echo "<li><strong>queue_entries.station_id indexed:</strong> " . ($station_id_indexed ? "✅ Yes" : "❌ No - Consider adding index") . "</li>\n";
    echo "<li><strong>queue_logs.queue_entry_id indexed:</strong> " . ($queue_entry_id_indexed ? "✅ Yes" : "❌ No - Consider adding index") . "</li>\n";
    echo "</ul>\n";
    
    // 7. Status Distribution
    echo "<h3>Status Distribution</h3>\n";
    $status_counts = [];
    foreach ($queue_entries as $entry) {
        $status = $entry['status'];
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
    
    echo "<ul>\n";
    foreach ($status_counts as $status => $count) {
        $percentage = round(($count / count($queue_entries)) * 100, 1);
        echo "<li><strong>{$status}:</strong> {$count} ({$percentage}%)</li>\n";
    }
    echo "</ul>\n";
    
    // 8. Recommendations
    echo "<h3>Recommendations</h3>\n";
    echo "<ul>\n";
    
    if (!empty($missing_logs)) {
        echo "<li>🔴 <strong>Critical:</strong> Fix logging for " . count($missing_logs) . " entries missing audit trails</li>\n";
    }
    
    if ($entries_without_station > 0) {
        echo "<li>🟡 <strong>Important:</strong> Run migration script to populate missing station_id values</li>\n";
    }
    
    if (!$station_id_indexed) {
        echo "<li>🟡 <strong>Performance:</strong> Add index on queue_entries(station_id) for better query performance</li>\n";
    }
    
    if (!$queue_entry_id_indexed) {
        echo "<li>🟡 <strong>Performance:</strong> Add index on queue_logs(queue_entry_id) for better audit queries</li>\n";
    }
    
    if (count($queue_entries) > 50 && $total_logs_found > 200) {
        echo "<li>🟡 <strong>Optimization:</strong> Consider implementing log archiving for old audit records</li>\n";
    }
    
    if (empty($missing_logs) && $entries_without_station == 0 && $station_id_indexed && $queue_entry_id_indexed) {
        echo "<li>🟢 <strong>Excellent:</strong> Queue audit integrity is perfect! No issues detected.</li>\n";
    }
    
    echo "</ul>\n";
    
    // 9. SQL Commands for Manual Verification
    echo "<h3>Manual Verification Commands</h3>\n";
    echo "<p>Use these SQL commands in phpMyAdmin for additional verification:</p>\n";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
    echo "-- Check for queue entries without logs\n";
    echo "SELECT qe.queue_entry_id, qe.status, qe.created_at\n";
    echo "FROM queue_entries qe\n";
    echo "LEFT JOIN queue_logs ql ON qe.queue_entry_id = ql.queue_entry_id\n";
    echo "WHERE DATE(qe.created_at) = CURDATE() AND ql.queue_entry_id IS NULL;\n\n";
    
    echo "-- Check for entries without station_id\n";
    echo "SELECT queue_entry_id, patient_id, service_id, status\n";
    echo "FROM queue_entries\n";
    echo "WHERE station_id IS NULL AND DATE(created_at) = CURDATE();\n\n";
    
    echo "-- Performance analysis - slow queries\n";
    echo "EXPLAIN SELECT * FROM queue_entries WHERE station_id = 1 AND DATE(created_at) = CURDATE();\n";
    echo "EXPLAIN SELECT * FROM queue_logs WHERE queue_entry_id = 1;\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Check database connection and table structure.</p>\n";
}

echo "<hr>\n";
echo "<p><em>Queue Audit Integrity Validator v1.0 - CHO Koronadal WBHSMS</em></p>\n";
?>