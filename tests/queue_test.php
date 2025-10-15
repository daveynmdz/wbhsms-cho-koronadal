<?php
/**
 * Quick Test Data Setup for Queue Simulation
 * Creates test patient and runs a complete queue simulation
 */

// Include necessary files
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$queueService = new QueueManagementService($pdo);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Queue Simulation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .success { border-left-color: #27ae60; background: #f8fff8; }
        .error { border-left-color: #e74c3c; background: #fff8f8; }
        .button { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
        .button:hover { background: #2980b9; }
        h1 { color: #2c3e50; text-align: center; }
        h2 { color: #34495e; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        .code { background: #2c3e50; color: #ecf0f1; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔬 Queue Simulation Test</h1>
        <p>This page will help you test the queue management system by creating sample data and running simulations.</p>";

try {
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        
        if ($action === 'create_test_data') {
            echo "<h2>Creating Test Data...</h2>";
            
            // Create test patient
            $stmt = $pdo->prepare("
                INSERT INTO patients (
                    first_name, last_name, email, phone_number, date_of_birth, gender, address,
                    philhealth_number, emergency_contact_name, emergency_contact_phone, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $test_email = 'queue.test.' . time() . '@cho.local';
            $stmt->execute([
                'Queue Test',
                'Patient',
                $test_email,
                '0912-345-6789',
                '1990-01-01',
                'Male',
                'Test Address, Koronadal City',
                '12-345678901-2',
                'Emergency Contact',
                '0912-987-6543'
            ]);
            
            $patient_id = $pdo->lastInsertId();
            echo "<div class='step success'>✅ Created test patient (ID: {$patient_id})</div>";
            
            // Create test appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (
                    patient_id, facility_id, service_id, scheduled_date, scheduled_time, 
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $scheduled_date = date('Y-m-d');
            $scheduled_time = date('H:i:s', strtotime('+1 hour'));
            
            $stmt->execute([
                $patient_id,
                1, // CHO Main District facility_id
                1, // General Consultation service_id
                $scheduled_date,
                $scheduled_time,
                'confirmed'
            ]);
            
            $appointment_id = $pdo->lastInsertId();
            echo "<div class='step success'>✅ Created test appointment (ID: {$appointment_id})</div>";
            
            // Create queue entry
            $queue_result = $queueService->createQueueEntry(
                $appointment_id,
                $patient_id,
                1, // General Consultation service_id
                'consultation',
                'normal',
                1 // Admin employee_id
            );
            
            if ($queue_result['success']) {
                echo "<div class='step success'>✅ Created queue entry (ID: {$queue_result['queue_entry_id']})</div>";
                if ($queue_result['queue_code']) {
                    echo "<div class='code'>Queue Code: {$queue_result['queue_code']}</div>";
                }
                
                echo "<h2>Next Steps:</h2>";
                echo "<a href='queue_simulation.php' class='button'>🚀 Go to Queue Simulation</a>";
                echo "<a href='dashboard.php' class='button'>📊 Go to Dashboard</a>";
                
            } else {
                echo "<div class='step error'>❌ Failed to create queue entry: " . $queue_result['message'] . "</div>";
            }
            
        } elseif ($action === 'test_complete_flow') {
            echo "<h2>Testing Complete Patient Flow...</h2>";
            
            // Get the latest test appointment
            $stmt = $pdo->prepare("
                SELECT a.*, p.first_name, p.last_name 
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                WHERE p.email LIKE '%@cho.local'
                ORDER BY a.created_at DESC
                LIMIT 1
            ");
            $stmt->execute();
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                echo "<div class='step error'>❌ No test appointment found. Create test data first.</div>";
            } else {
                echo "<div class='step'>👤 Patient: {$appointment['first_name']} {$appointment['last_name']}</div>";
                echo "<div class='step'>📅 Appointment: {$appointment['scheduled_date']} {$appointment['scheduled_time']}</div>";
                
                // Simulate each station
                $stations = ['triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document'];
                
                foreach ($stations as $station) {
                    echo "<div class='step'>🏥 Simulating {$station} station...</div>";
                    // Here you could add actual simulation calls
                }
                
                echo "<div class='step success'>✅ Complete patient flow simulation ready!</div>";
                echo "<a href='queue_simulation.php?load={$appointment['appointment_id']}' class='button'>🔬 Run Full Simulation</a>";
            }
        }
        
    } else {
        // Show options
        echo "<h2>Available Actions:</h2>";
        echo "<a href='?action=create_test_data' class='button'>📝 Create Test Data</a>";
        echo "<a href='?action=test_complete_flow' class='button'>🔄 Test Complete Flow</a>";
        echo "<a href='queue_simulation.php' class='button'>🚀 Go to Simulation</a>";
        
        echo "<h2>Queue System Status:</h2>";
        
        // Show current status
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM stations WHERE is_active = 1");
        $result = $stmt->fetch();
        echo "<div class='step'>🏥 Active stations: {$result['count']}</div>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM queue_entries WHERE DATE(created_at) = CURDATE()");
        $result = $stmt->fetch();
        echo "<div class='step'>📋 Today's queue entries: {$result['count']}</div>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(created_at) = CURDATE()");
        $result = $stmt->fetch();
        echo "<div class='step'>📅 Today's appointments: {$result['count']}</div>";
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE email LIKE '%@cho.local'");
        $result = $stmt->fetch();
        echo "<div class='step'>🧪 Test patients: {$result['count']}</div>";
        
        echo "<h2>How the Queue Simulation Works:</h2>";
        echo "<div class='step'>
            <strong>Normal Patient Flow:</strong><br>
            1. <strong>Check-in:</strong> Patient arrives, appointment verified, queue entry created<br>
            2. <strong>Triage:</strong> Vital signs collected, initial assessment<br>
            3. <strong>Consultation:</strong> Doctor examination and diagnosis<br>
            4. <strong>Laboratory:</strong> Tests and sample collection (if needed)<br>
            5. <strong>Pharmacy:</strong> Medication dispensing (if prescribed)<br>
            6. <strong>Billing:</strong> Payment processing and invoicing<br>
            7. <strong>Document:</strong> Medical certificates and final documentation
        </div>";
        
        echo "<div class='step'>
            <strong>Features:</strong><br>
            • Real-time queue status tracking<br>
            • Automatic routing between stations<br>
            • Complete audit trail in queue logs<br>
            • Simulation of typical processing times<br>
            • Visual progress tracking<br>
            • Error handling and rollback
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='step error'>❌ Error: " . $e->getMessage() . "</div>";
}

echo "</div></body></html>";
?>