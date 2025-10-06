<?php
/**
 * Migration Script: Populate station_id in queue_entries
 * Run this ONCE to update existing queue entries with proper station_id values
 */

require_once 'config/db.php';
require_once 'utils/queue_management_service.php';

echo "<h2>Queue Entries Station ID Migration</h2>";

// Initialize queue management service
$queueService = new QueueManagementService($conn);

echo "<h3>Step 1: Check current queue_entries without station_id</h3>";
$check_stmt = $conn->prepare("SELECT COUNT(*) as null_count FROM queue_entries WHERE station_id IS NULL");
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$null_count = $check_result->fetch_assoc()['null_count'];
echo "<p>Found <strong>{$null_count}</strong> queue entries with NULL station_id</p>";

if ($null_count > 0) {
    echo "<h3>Step 2: Running migration...</h3>";
    $migration_result = $queueService->migrateQueueEntriesStationId();
    
    if ($migration_result['success']) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0;'>";
        echo "<strong>✅ SUCCESS:</strong> " . $migration_result['message'];
        echo "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; background: #fff0f0;'>";
        echo "<strong>❌ ERROR:</strong> " . $migration_result['error'];
        echo "</div>";
    }
    
    echo "<h3>Step 3: Verification</h3>";
    $verify_stmt = $conn->prepare("SELECT COUNT(*) as remaining_null FROM queue_entries WHERE station_id IS NULL");
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $remaining_null = $verify_result->fetch_assoc()['remaining_null'];
    
    if ($remaining_null == 0) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; background: #f0fff0;'>";
        echo "<strong>✅ VERIFICATION PASSED:</strong> All queue entries now have station_id populated";
        echo "</div>";
    } else {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange; background: #fff8f0;'>";
        echo "<strong>⚠️ WARNING:</strong> {$remaining_null} queue entries still have NULL station_id";
        echo "</div>";
    }
} else {
    echo "<div style='color: blue; padding: 10px; border: 1px solid blue; background: #f0f8ff;'>";
    echo "<strong>ℹ️ INFO:</strong> No migration needed. All queue entries already have station_id populated.";
    echo "</div>";
}

echo "<h3>Current Queue Entries Status</h3>";
$status_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_entries,
        COUNT(station_id) as with_station_id,
        COUNT(*) - COUNT(station_id) as without_station_id
    FROM queue_entries
");
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$status = $status_result->fetch_assoc();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th>Total Entries</th><th>With Station ID</th><th>Without Station ID</th>";
echo "</tr>";
echo "<tr>";
echo "<td>{$status['total_entries']}</td>";
echo "<td style='color: green;'>{$status['with_station_id']}</td>";
echo "<td style='color: " . ($status['without_station_id'] > 0 ? 'red' : 'green') . ";'>{$status['without_station_id']}</td>";
echo "</tr>";
echo "</table>";

echo "<h3>Sample Queue Entries with Station Assignment</h3>";
$sample_stmt = $conn->prepare("
    SELECT 
        qe.queue_entry_id,
        qe.queue_number,
        qe.station_id,
        s.station_name,
        s.station_type,
        sv.name as service_name,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name
    FROM queue_entries qe
    LEFT JOIN stations s ON qe.station_id = s.station_id
    LEFT JOIN services sv ON qe.service_id = sv.service_id  
    LEFT JOIN patients p ON qe.patient_id = p.patient_id
    ORDER BY qe.queue_entry_id DESC
    LIMIT 5
");
$sample_stmt->execute();
$sample_result = $sample_stmt->get_result();

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th>Queue ID</th><th>Queue #</th><th>Station ID</th><th>Station Name</th><th>Type</th><th>Service</th><th>Patient</th>";
echo "</tr>";

while ($row = $sample_result->fetch_assoc()) {
    $station_status = $row['station_id'] ? 'green' : 'red';
    echo "<tr>";
    echo "<td>{$row['queue_entry_id']}</td>";
    echo "<td>{$row['queue_number']}</td>";
    echo "<td style='color: {$station_status};'>" . ($row['station_id'] ?: 'NULL') . "</td>";
    echo "<td>{$row['station_name']}</td>";
    echo "<td>{$row['station_type']}</td>";
    echo "<td>{$row['service_name']}</td>";
    echo "<td>{$row['patient_name']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>✅ Queue Management Service updated to use station_id directly</li>";
echo "<li>✅ Queue creation now populates station_id automatically</li>";  
echo "<li>✅ Station-specific queue filtering now works correctly</li>";
echo "<li>✅ Enhanced queue logging implemented</li>";
echo "<li>🔄 Test the queue management functionality in the application</li>";
echo "</ol>";

echo "<p><em>Migration completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>