<?php
/**
 * Database Index Optimization Script
 * 
 * Checks and suggests database indexes for optimal queue performance
 * Run this to analyze current indexing and get optimization recommendations
 */

require_once 'config/db.php';

echo "<h2>Database Index Optimization Analysis</h2>\n";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<hr>\n";

try {
    // 1. Check current indexes on queue_entries
    echo "<h3>Current Indexes on queue_entries</h3>\n";
    $result = $conn->query("SHOW INDEX FROM queue_entries");
    $queue_indexes = [];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>Key Name</th><th>Column</th><th>Unique</th><th>Type</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        $queue_indexes[$row['Column_name']] = $row;
        echo "<tr>\n";
        echo "<td>{$row['Key_name']}</td>\n";
        echo "<td>{$row['Column_name']}</td>\n";
        echo "<td>" . ($row['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>\n";
        echo "<td>{$row['Index_type']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 2. Check current indexes on queue_logs
    echo "<h3>Current Indexes on queue_logs</h3>\n";
    $result = $conn->query("SHOW INDEX FROM queue_logs");
    $log_indexes = [];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>Key Name</th><th>Column</th><th>Unique</th><th>Type</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        $log_indexes[$row['Column_name']] = $row;
        echo "<tr>\n";
        echo "<td>{$row['Key_name']}</td>\n";
        echo "<td>{$row['Column_name']}</td>\n";
        echo "<td>" . ($row['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>\n";
        echo "<td>{$row['Index_type']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 3. Check current indexes on assignment_schedules
    echo "<h3>Current Indexes on assignment_schedules</h3>\n";
    $result = $conn->query("SHOW INDEX FROM assignment_schedules");
    $assignment_indexes = [];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>Key Name</th><th>Column</th><th>Unique</th><th>Type</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        $assignment_indexes[$row['Column_name']] = $row;
        echo "<tr>\n";
        echo "<td>{$row['Key_name']}</td>\n";
        echo "<td>{$row['Column_name']}</td>\n";
        echo "<td>" . ($row['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>\n";
        echo "<td>{$row['Index_type']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 4. Analyze and recommend optimizations
    echo "<h3>Index Optimization Recommendations</h3>\n";
    
    $recommendations = [];
    $sql_commands = [];
    
    // Critical indexes for queue_entries
    if (!isset($queue_indexes['station_id'])) {
        $recommendations[] = "🔴 CRITICAL: Add index on queue_entries.station_id (heavily used in station queries)";
        $sql_commands[] = "ALTER TABLE queue_entries ADD INDEX idx_station_id (station_id);";
    } else {
        $recommendations[] = "✅ queue_entries.station_id is properly indexed";
    }
    
    if (!isset($queue_indexes['status'])) {
        $recommendations[] = "🟡 IMPORTANT: Add index on queue_entries.status (used in queue filtering)";
        $sql_commands[] = "ALTER TABLE queue_entries ADD INDEX idx_status (status);";
    } else {
        $recommendations[] = "✅ queue_entries.status is properly indexed";
    }
    
    if (!isset($queue_indexes['created_at'])) {
        $recommendations[] = "🟡 IMPORTANT: Add index on queue_entries.created_at (used in date filtering)";
        $sql_commands[] = "ALTER TABLE queue_entries ADD INDEX idx_created_at (created_at);";
    } else {
        $recommendations[] = "✅ queue_entries.created_at is properly indexed";
    }
    
    // Critical indexes for queue_logs
    if (!isset($log_indexes['queue_entry_id'])) {
        $recommendations[] = "🔴 CRITICAL: Add index on queue_logs.queue_entry_id (used in audit queries)";
        $sql_commands[] = "ALTER TABLE queue_logs ADD INDEX idx_queue_entry_id (queue_entry_id);";
    } else {
        $recommendations[] = "✅ queue_logs.queue_entry_id is properly indexed";
    }
    
    if (!isset($log_indexes['created_at'])) {
        $recommendations[] = "🟡 PERFORMANCE: Add index on queue_logs.created_at (used in log filtering)";
        $sql_commands[] = "ALTER TABLE queue_logs ADD INDEX idx_log_created_at (created_at);";
    } else {
        $recommendations[] = "✅ queue_logs.created_at is properly indexed";
    }
    
    // Composite indexes for common query patterns
    $composite_checks = [
        'station_status_date' => "SELECT 1 FROM information_schema.statistics WHERE table_name = 'queue_entries' AND index_name LIKE '%station%status%' OR index_name LIKE '%status%station%'",
        'entry_action_date' => "SELECT 1 FROM information_schema.statistics WHERE table_name = 'queue_logs' AND index_name LIKE '%entry%action%' OR index_name LIKE '%action%entry%'"
    ];
    
    foreach ($composite_checks as $check_name => $sql) {
        $result = $conn->query($sql);
        if ($result->num_rows == 0) {
            if ($check_name == 'station_status_date') {
                $recommendations[] = "🟡 OPTIMIZATION: Consider composite index on queue_entries (station_id, status, created_at)";
                $sql_commands[] = "ALTER TABLE queue_entries ADD INDEX idx_station_status_date (station_id, status, created_at);";
            } elseif ($check_name == 'entry_action_date') {
                $recommendations[] = "🟡 OPTIMIZATION: Consider composite index on queue_logs (queue_entry_id, action, created_at)";
                $sql_commands[] = "ALTER TABLE queue_logs ADD INDEX idx_entry_action_date (queue_entry_id, action, created_at);";
            }
        }
    }
    
    // Assignment_schedules indexes
    if (!isset($assignment_indexes['employee_id'])) {
        $recommendations[] = "🟡 IMPORTANT: Add index on assignment_schedules.employee_id";
        $sql_commands[] = "ALTER TABLE assignment_schedules ADD INDEX idx_employee_id (employee_id);";
    }
    
    if (!isset($assignment_indexes['station_id'])) {
        $recommendations[] = "🟡 IMPORTANT: Add index on assignment_schedules.station_id";
        $sql_commands[] = "ALTER TABLE assignment_schedules ADD INDEX idx_assignment_station_id (station_id);";
    }
    
    // Display recommendations
    echo "<ul>\n";
    foreach ($recommendations as $rec) {
        echo "<li>{$rec}</li>\n";
    }
    echo "</ul>\n";
    
    // 5. SQL Commands to Execute
    if (!empty($sql_commands)) {
        echo "<h3>SQL Commands to Execute</h3>\n";
        echo "<p>Copy and paste these commands into phpMyAdmin to optimize database performance:</p>\n";
        echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
        echo "-- Database Index Optimization Commands\n";
        echo "-- Execute these one by one in phpMyAdmin\n\n";
        
        foreach ($sql_commands as $i => $cmd) {
            echo "-- Command " . ($i + 1) . "\n";
            echo $cmd . "\n\n";
        }
        
        echo "-- Verify indexes after execution\n";
        echo "SHOW INDEX FROM queue_entries;\n";
        echo "SHOW INDEX FROM queue_logs;\n";
        echo "SHOW INDEX FROM assignment_schedules;\n";
        echo "</pre>\n";
    } else {
        echo "<h3 style='color: green;'>✅ Database is Fully Optimized</h3>\n";
        echo "<p>All critical indexes are in place. No optimization needed!</p>\n";
    }
    
    // 6. Performance Testing Queries
    echo "<h3>Performance Testing Queries</h3>\n";
    echo "<p>Use EXPLAIN before these queries to verify index usage:</p>\n";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
    echo "-- Test query performance with EXPLAIN\n";
    echo "EXPLAIN SELECT * FROM queue_entries WHERE station_id = 1 AND status = 'waiting' AND DATE(created_at) = CURDATE();\n\n";
    echo "EXPLAIN SELECT * FROM queue_logs WHERE queue_entry_id = 1 ORDER BY created_at;\n\n";
    echo "EXPLAIN SELECT * FROM assignment_schedules WHERE employee_id = 1 AND is_active = 1;\n\n";
    echo "-- Check query execution time\n";
    echo "SET profiling = 1;\n";
    echo "SELECT COUNT(*) FROM queue_entries WHERE station_id = 1 AND DATE(created_at) = CURDATE();\n";
    echo "SHOW PROFILES;\n";
    echo "</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><em>Database Index Optimization Analyzer v1.0 - CHO Koronadal WBHSMS</em></p>\n";
?>