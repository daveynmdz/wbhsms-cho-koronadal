<?php
/**
 * Generate Test Data for Queue System Testing
 * 
 * Creates realistic test data for today's date including:
 * - Station assignments
 * - Queue entries with proper timestamps
 * - Various queue statuses for comprehensive testing
 */

require_once 'config/db.php';

echo "<h2>Queue System Test Data Generator</h2>\n";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<hr>\n";

try {
    $conn->begin_transaction();
    
    // Get today's date
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    echo "<h3>🏗️ Creating Test Data for {$today}</h3>\n";
    
    // 1. Clean existing test data for today (optional - uncomment if needed)
    /*
    echo "<p>Cleaning existing test data for today...</p>\n";
    $conn->query("DELETE FROM queue_logs WHERE queue_entry_id IN (SELECT queue_entry_id FROM queue_entries WHERE DATE(created_at) = CURDATE())");
    $conn->query("DELETE FROM queue_entries WHERE DATE(created_at) = CURDATE()");
    */
    
    // 2. Ensure we have active stations
    echo "<h4>Step 1: Verifying Station Setup</h4>\n";
    $station_check = $conn->query("SELECT COUNT(*) as count FROM stations WHERE is_active = 1");
    $station_count = $station_check->fetch_assoc()['count'];
    
    if ($station_count == 0) {
        // Create basic stations if none exist
        $stations_sql = "
            INSERT INTO stations (station_name, station_type, station_number, service_id, is_active) VALUES
            ('Triage Station 1', 'triage', 1, 1, 1),
            ('Consultation Room 1', 'consultation', 1, 2, 1),
            ('Consultation Room 2', 'consultation', 2, 2, 1),
            ('Laboratory Station', 'lab', 1, 3, 1),
            ('Pharmacy Counter', 'pharmacy', 1, 4, 1)
        ";
        $conn->query($stations_sql);
        echo "<p>✅ Created 5 basic stations</p>\n";
    } else {
        echo "<p>✅ Found {$station_count} active stations</p>\n";
    }
    
    // Get available stations
    $stations_result = $conn->query("SELECT station_id, station_name, service_id FROM stations WHERE is_active = 1 LIMIT 5");
    $stations = $stations_result->fetch_all(MYSQLI_ASSOC);
    
    // 3. Create station assignments for today
    echo "<h4>Step 2: Creating Station Assignments</h4>\n";
    
    // Get available employees
    $employees_result = $conn->query("
        SELECT employee_id, CONCAT(first_name, ' ', last_name) as name, role_id 
        FROM employees 
        WHERE status = 'active' 
        LIMIT 5
    ");
    $employees = $employees_result->fetch_all(MYSQLI_ASSOC);
    
    if (count($employees) == 0) {
        echo "<p>❌ No active employees found. Please ensure you have employee records.</p>\n";
        throw new Exception("No active employees available for assignment");
    }
    
    // Clear existing assignments for today and create new ones
    $conn->query("UPDATE assignment_schedules SET is_active = 0 WHERE start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())");
    
    $assignment_count = 0;
    foreach ($stations as $i => $station) {
        if (isset($employees[$i])) {
            $employee = $employees[$i];
            
            $assign_stmt = $conn->prepare("
                INSERT INTO assignment_schedules 
                (employee_id, station_id, start_date, shift_start_time, shift_end_time, assignment_type, assigned_by, created_at)
                VALUES (?, ?, ?, '08:00:00', '17:00:00', 'temporary', 1, NOW())
            ");
            
            $assign_stmt->bind_param("iis", $employee['employee_id'], $station['station_id'], $today);
            
            if ($assign_stmt->execute()) {
                echo "<p>✅ Assigned {$employee['name']} to {$station['station_name']}</p>\n";
                $assignment_count++;
            }
        }
    }
    
    echo "<p><strong>Created {$assignment_count} station assignments for today</strong></p>\n";
    
    // 4. Create test appointments and queue entries
    echo "<h4>Step 3: Creating Queue Entries</h4>\n";
    
    // Get available patients
    $patients_result = $conn->query("
        SELECT patient_id, CONCAT(first_name, ' ', last_name) as name 
        FROM patients 
        WHERE status = 'active' 
        ORDER BY RAND() 
        LIMIT 10
    ");
    $patients = $patients_result->fetch_all(MYSQLI_ASSOC);
    
    if (count($patients) == 0) {
        echo "<p>❌ No active patients found. Please ensure you have patient records.</p>\n";
        throw new Exception("No active patients available for queue entries");
    }
    
    // Create test appointments first
    $appointment_ids = [];
    foreach ($patients as $i => $patient) {
        $station = $stations[$i % count($stations)];
        $service_id = $station['service_id'];
        
        // Create appointment for today
        $scheduled_time = date('H:i:s', strtotime('09:00:00 +' . ($i * 30) . ' minutes'));
        
        $appt_stmt = $conn->prepare("
            INSERT INTO appointments 
            (patient_id, facility_id, service_id, scheduled_date, scheduled_time, status, appointment_type, created_at)
            VALUES (?, 1, ?, ?, ?, 'confirmed', 'regular', NOW())
        ");
        
        $appt_stmt->bind_param("iiss", $patient['patient_id'], $service_id, $today, $scheduled_time);
        
        if ($appt_stmt->execute()) {
            $appointment_ids[] = $conn->insert_id;
            echo "<p>✅ Created appointment for {$patient['name']} at {$scheduled_time}</p>\n";
        }
    }
    
    // Create queue entries with various statuses
    $queue_statuses = ['waiting', 'waiting', 'waiting', 'in_progress', 'done', 'done', 'skipped', 'no_show', 'waiting', 'waiting'];
    $priorities = ['normal', 'normal', 'priority', 'normal', 'emergency', 'normal', 'normal', 'normal', 'priority', 'normal'];
    
    $queue_count = 0;
    foreach ($appointment_ids as $i => $appointment_id) {
        $patient = $patients[$i];
        $station = $stations[$i % count($stations)];
        $status = $queue_statuses[$i % count($queue_statuses)];
        $priority = $priorities[$i % count($priorities)];
        
        // Create visit record first
        $visit_stmt = $conn->prepare("
            INSERT INTO visits 
            (patient_id, facility_id, appointment_id, visit_date, visit_status, created_at, updated_at)
            VALUES (?, 1, ?, ?, 'ongoing', NOW(), NOW())
        ");
        
        $visit_stmt->bind_param("iis", $patient['patient_id'], $appointment_id, $today);
        $visit_stmt->execute();
        $visit_id = $conn->insert_id;
        
        // Create queue entry
        $queue_number = $i + 1;
        $queue_code = date('dmy') . '-08A-' . str_pad($queue_number, 3, '0', STR_PAD_LEFT);
        
        // Calculate realistic timestamps based on status
        $time_in = date('Y-m-d H:i:s', strtotime($today . ' 08:00:00 +' . ($i * 20) . ' minutes'));
        $time_started = null;
        $time_completed = null;
        $waiting_time = null;
        $turnaround_time = null;
        
        if (in_array($status, ['in_progress', 'done', 'skipped', 'no_show'])) {
            $time_started = date('Y-m-d H:i:s', strtotime($time_in . ' +' . rand(5, 30) . ' minutes'));
            $waiting_time = rand(5, 30);
            
            if (in_array($status, ['done', 'skipped', 'no_show'])) {
                $time_completed = date('Y-m-d H:i:s', strtotime($time_started . ' +' . rand(10, 45) . ' minutes'));
                $turnaround_time = $waiting_time + rand(10, 45);
            }
        }
        
        $queue_stmt = $conn->prepare("
            INSERT INTO queue_entries 
            (visit_id, appointment_id, patient_id, service_id, station_id, queue_type, queue_number, queue_code, 
             priority_level, status, time_in, time_started, time_completed, waiting_time, turnaround_time, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'consultation', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $queue_stmt->bind_param("iiiisissssssii", 
            $visit_id, $appointment_id, $patient['patient_id'], $station['service_id'], $station['station_id'],
            $queue_number, $queue_code, $priority, $status, $time_in, $time_started, $time_completed, 
            $waiting_time, $turnaround_time
        );
        
        if ($queue_stmt->execute()) {
            $queue_entry_id = $conn->insert_id;
            
            // Create audit log entries
            $log_stmt = $conn->prepare("
                INSERT INTO queue_logs 
                (queue_entry_id, action, old_status, new_status, remarks, performed_by, created_at)
                VALUES (?, 'created', NULL, 'waiting', 'Queue entry created for testing', 1, ?)
            ");
            
            $log_stmt->bind_param("is", $queue_entry_id, $time_in);
            $log_stmt->execute();
            
            // Add status transition logs for non-waiting entries
            if ($status != 'waiting') {
                $transition_time = $time_started ?: $time_in;
                
                if ($status == 'in_progress') {
                    $log_stmt2 = $conn->prepare("
                        INSERT INTO queue_logs 
                        (queue_entry_id, action, old_status, new_status, remarks, performed_by, created_at)
                        VALUES (?, 'status_changed', 'waiting', 'in_progress', 'Patient called to station', 1, ?)
                    ");
                    $log_stmt2->bind_param("is", $queue_entry_id, $transition_time);
                    $log_stmt2->execute();
                }
                
                if (in_array($status, ['done', 'skipped', 'no_show'])) {
                    // Add in_progress log first if not already in_progress
                    if ($status != 'in_progress') {
                        $log_stmt3 = $conn->prepare("
                            INSERT INTO queue_logs 
                            (queue_entry_id, action, old_status, new_status, remarks, performed_by, created_at)
                            VALUES (?, 'status_changed', 'waiting', 'in_progress', 'Patient called to station', 1, ?)
                        ");
                        $log_stmt3->bind_param("is", $queue_entry_id, $transition_time);
                        $log_stmt3->execute();
                    }
                    
                    // Add final status log
                    $final_action = $status == 'done' ? 'status_changed' : ($status == 'skipped' ? 'skipped' : 'status_changed');
                    $final_time = $time_completed ?: $transition_time;
                    
                    $log_stmt4 = $conn->prepare("
                        INSERT INTO queue_logs 
                        (queue_entry_id, action, old_status, new_status, remarks, performed_by, created_at)
                        VALUES (?, ?, 'in_progress', ?, 'Status updated during testing', 1, ?)
                    ");
                    $log_stmt4->bind_param("isss", $queue_entry_id, $final_action, $status, $final_time);
                    $log_stmt4->execute();
                }
            }
            
            echo "<p>✅ Created queue entry for {$patient['name']} - Status: {$status}, Priority: {$priority}</p>\n";
            $queue_count++;
        }
    }
    
    $conn->commit();
    
    // 5. Summary Report
    echo "<h3>🎉 Test Data Creation Complete!</h3>\n";
    echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 8px; margin: 10px 0;'>\n";
    echo "<h4>Summary:</h4>\n";
    echo "<ul>\n";
    echo "<li><strong>Station Assignments:</strong> {$assignment_count} active assignments for today</li>\n";
    echo "<li><strong>Queue Entries:</strong> {$queue_count} entries created with realistic timestamps</li>\n";
    echo "<li><strong>Date:</strong> {$today}</li>\n";
    echo "<li><strong>Time Range:</strong> 08:00 - 12:00 (appointments spread throughout morning)</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
    
    // 6. Verification Queries
    echo "<h4>🔍 Verification Queries</h4>\n";
    echo "<p>Run these in phpMyAdmin to verify the test data:</p>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>\n";
    echo "-- Check queue entries for today\n";
    echo "SELECT COUNT(*) as queue_count FROM queue_entries WHERE DATE(created_at) = CURDATE();\n\n";
    
    echo "-- Check active station assignments\n";
    echo "SELECT COUNT(*) as assignment_count FROM assignment_schedules WHERE is_active = 1;\n\n";
    
    echo "-- View today's queue with patient names\n";
    echo "SELECT \n";
    echo "    qe.queue_entry_id, qe.queue_number, qe.status, qe.priority_level,\n";
    echo "    CONCAT(p.first_name, ' ', p.last_name) as patient_name,\n";
    echo "    s.station_name,\n";
    echo "    qe.time_in\n";
    echo "FROM queue_entries qe\n";
    echo "JOIN patients p ON qe.patient_id = p.patient_id\n";
    echo "JOIN stations s ON qe.station_id = s.station_id\n";
    echo "WHERE DATE(qe.created_at) = CURDATE()\n";
    echo "ORDER BY qe.queue_number;\n\n";
    
    echo "-- Check audit logs\n";
    echo "SELECT COUNT(*) as log_count FROM queue_logs ql\n";
    echo "JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id\n";
    echo "WHERE DATE(qe.created_at) = CURDATE();\n";
    echo "</pre>\n";
    
    // 7. Next Steps
    echo "<h4>📋 Next Steps for Testing</h4>\n";
    echo "<ol>\n";
    echo "<li><strong>Verify Test Data:</strong> Run the verification queries above</li>\n";
    echo "<li><strong>Run Audit Scripts:</strong>\n";
    echo "<ul>\n";
    echo "<li>Access <code>/test_queue_audit_integrity.php</code></li>\n";
    echo "<li>Access <code>/analyze_database_indexes.php</code></li>\n";
    echo "</ul></li>\n";
    echo "<li><strong>Test Station Interface:</strong> Log in as assigned employees and test queue operations</li>\n";
    echo "<li><strong>Follow QA Checklist:</strong> Use the manual QA checklist to verify all functionality</li>\n";
    echo "</ol>\n";
    
    echo "<div style='background: #d4edda; padding: 10px; border-left: 4px solid #28a745; margin: 10px 0;'>\n";
    echo "<strong>✅ Success!</strong> Test data has been created successfully. You can now proceed with the manual QA testing checklist.\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    $conn->rollback();
    echo "<div style='background: #f8d7da; padding: 10px; border-left: 4px solid #dc3545; margin: 10px 0;'>\n";
    echo "<strong>❌ Error:</strong> " . htmlspecialchars($e->getMessage()) . "\n";
    echo "</div>\n";
    
    echo "<h4>🔧 Troubleshooting Steps:</h4>\n";
    echo "<ol>\n";
    echo "<li>Verify database tables exist (patients, employees, stations, services)</li>\n";
    echo "<li>Ensure you have at least one active patient and employee record</li>\n";
    echo "<li>Check database permissions and connection</li>\n";
    echo "<li>Review any specific error messages above</li>\n";
    echo "</ol>\n";
}

echo "<hr>\n";
echo "<p><em>Queue System Test Data Generator v1.0 - CHO Koronadal WBHSMS</em></p>\n";
?>