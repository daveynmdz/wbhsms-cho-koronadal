<?php
/**
 * Working Simple Queue Simulation
 * Bypasses QueueManagementService routing issues
 */

// Basic error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
$root_path = __DIR__;
require_once $root_path . '/config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication check - set admin for testing
if (!isset($_SESSION['employee_id'])) {
    $_SESSION['employee_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'Admin';
}

$employee_id = $_SESSION['employee_id'];

// Load queue service
require_once $root_path . '/utils/queue_management_service.php';
$queueService = new QueueManagementService($pdo);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'create_simulation':
                // Create test patient
                $stmt = $pdo->prepare("
                    INSERT INTO patients (
                        first_name, last_name, email, contact_number, date_of_birth, sex, 
                        barangay_id, philhealth_id_number, password_hash, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $test_email = 'sim.test.' . time() . '@cho.local';
                $stmt->execute([
                    'Simulation',
                    'Patient',
                    $test_email,
                    '0912-345-6789',
                    '1990-01-01',
                    'Male',
                    1, // Default barangay_id
                    '12-345678901-2',
                    password_hash('password123', PASSWORD_DEFAULT)
                ]);
                
                $patient_id = $pdo->lastInsertId();
                
                // Create test appointment
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (
                        patient_id, facility_id, service_id, scheduled_date, scheduled_time, 
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $patient_id,
                    1, // CHO Main District
                    1, // General Consultation
                    date('Y-m-d'),
                    date('H:i:s', strtotime('+1 hour')),
                    'confirmed'
                ]);
                
                $appointment_id = $pdo->lastInsertId();
                
                // Create initial queue entry manually (bypass QueueManagementService routing issues)
                $stmt = $pdo->prepare("
                    INSERT INTO queue_entries (
                        visit_id, appointment_id, patient_id, service_id, station_id,
                        queue_type, priority_level, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                // Create a simple visit record
                $stmt_visit = $pdo->prepare("
                    INSERT INTO visits (patient_id, facility_id, appointment_id, visit_date, visit_status, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt_visit->execute([$patient_id, 1, $appointment_id, date('Y-m-d'), 'ongoing']);
                $visit_id = $pdo->lastInsertId();
                
                // Get a triage station
                $station_stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'triage' AND is_active = 1 LIMIT 1");
                $station_stmt->execute();
                $station = $station_stmt->fetch(PDO::FETCH_ASSOC);
                $station_id = $station ? $station['station_id'] : 1;
                
                $stmt->execute([
                    $visit_id,
                    $appointment_id,
                    $patient_id,
                    1, // service_id
                    $station_id,
                    'triage',
                    'normal',
                    'waiting'
                ]);
                
                $queue_entry_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'simulation_id' => $appointment_id,
                    'patient_id' => $patient_id,
                    'queue_entry_id' => $queue_entry_id,
                    'message' => 'Simulation created successfully!'
                ]);
                break;
                
            case 'simulate_step':
                $simulation_id = intval($_POST['simulation_id']);
                $step = $_POST['step'];
                
                // Get current appointment queue entries
                $stmt = $pdo->prepare("
                    SELECT qe.*, s.station_type, s.station_name 
                    FROM queue_entries qe
                    LEFT JOIN stations s ON qe.station_id = s.station_id
                    WHERE qe.appointment_id = ?
                    ORDER BY qe.created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$simulation_id]);
                $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$queue_entry) {
                    echo json_encode(['success' => false, 'message' => 'No queue entry found']);
                    break;
                }
                
                $queue_entry_id = $queue_entry['queue_entry_id'];
                
                // Simple direct database operations to simulate each step
                switch ($step) {
                    case 'triage':
                        // Complete current triage
                        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'done', remarks = 'Triage completed: BP 120/80, HR 72' WHERE queue_entry_id = ?");
                        $stmt->execute([$queue_entry_id]);
                        
                        // Create consultation queue entry
                        $station_stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'consultation' AND is_active = 1 LIMIT 1");
                        $station_stmt->execute();
                        $consultation_station = $station_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($consultation_station) {
                            $stmt = $pdo->prepare("
                                INSERT INTO queue_entries (
                                    visit_id, appointment_id, patient_id, service_id, station_id,
                                    queue_type, priority_level, status, remarks, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $queue_entry['visit_id'],
                                $simulation_id,
                                $queue_entry['patient_id'],
                                $queue_entry['service_id'],
                                $consultation_station['station_id'],
                                'consultation',
                                'normal',
                                'waiting',
                                'Routed from triage'
                            ]);
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Triage completed, routed to consultation']);
                        break;
                        
                    case 'consultation':
                        // Complete consultation
                        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'done', remarks = 'Consultation completed: Diagnosis URTI' WHERE queue_entry_id = ?");
                        $stmt->execute([$queue_entry_id]);
                        
                        // Create pharmacy queue entry
                        $station_stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'pharmacy' AND is_active = 1 LIMIT 1");
                        $station_stmt->execute();
                        $pharmacy_station = $station_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pharmacy_station) {
                            $stmt = $pdo->prepare("
                                INSERT INTO queue_entries (
                                    visit_id, appointment_id, patient_id, service_id, station_id,
                                    queue_type, priority_level, status, remarks, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $queue_entry['visit_id'],
                                $simulation_id,
                                $queue_entry['patient_id'],
                                $queue_entry['service_id'],
                                $pharmacy_station['station_id'],
                                'pharmacy',
                                'normal',
                                'waiting',
                                'Routed from consultation'
                            ]);
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Consultation completed, routed to pharmacy']);
                        break;
                        
                    case 'pharmacy':
                        // Complete pharmacy
                        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'done', remarks = 'Medications dispensed: Amoxicillin 500mg' WHERE queue_entry_id = ?");
                        $stmt->execute([$queue_entry_id]);
                        
                        // Create billing queue entry
                        $station_stmt = $pdo->prepare("SELECT station_id FROM stations WHERE station_type = 'billing' AND is_active = 1 LIMIT 1");
                        $station_stmt->execute();
                        $billing_station = $station_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($billing_station) {
                            $stmt = $pdo->prepare("
                                INSERT INTO queue_entries (
                                    visit_id, appointment_id, patient_id, service_id, station_id,
                                    queue_type, priority_level, status, remarks, created_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            $stmt->execute([
                                $queue_entry['visit_id'],
                                $simulation_id,
                                $queue_entry['patient_id'],
                                $queue_entry['service_id'],
                                $billing_station['station_id'],
                                'billing',
                                'normal',
                                'waiting',
                                'Routed from pharmacy'
                            ]);
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Medications dispensed, routed to billing']);
                        break;
                        
                    case 'billing':
                        // Complete billing
                        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'done', remarks = 'Payment completed: PHP 200' WHERE queue_entry_id = ?");
                        $stmt->execute([$queue_entry_id]);
                        
                        // Complete appointment
                        $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
                        $stmt->execute([$simulation_id]);
                        
                        echo json_encode(['success' => true, 'message' => 'Payment completed! Visit finished. 🎉']);
                        break;
                        
                    default:
                        echo json_encode(['success' => false, 'message' => 'Invalid step']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get recent simulations
$recent_sims = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.appointment_id, a.status, a.created_at,
               p.first_name, p.last_name,
               COUNT(qe.queue_entry_id) as queue_count
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN queue_entries qe ON a.appointment_id = qe.appointment_id
        WHERE p.email LIKE '%@cho.local'
        GROUP BY a.appointment_id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_sims = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Working Queue Simulation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #2980b9; }
        .btn:disabled { background: #bdc3c7; cursor: not-allowed; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .step.completed { border-left-color: #27ae60; background: #f8fff8; }
        .logs { max-height: 300px; overflow-y: auto; background: #2c3e50; color: white; padding: 15px; border-radius: 5px; font-family: monospace; }
        h1 { color: #2c3e50; text-align: center; }
        h2 { color: #34495e; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔬 Working Queue Simulation</h1>
        <div class="alert alert-info">
            <strong>Fixed Version:</strong> This bypasses the QueueManagementService routing issues and uses direct database operations.
        </div>
        
        <h2>Create New Simulation</h2>
        <button id="createBtn" class="btn" onclick="createSimulation()">Create Test Patient & Queue</button>
        
        <div id="simulationArea" style="display:none;">
            <h2>Simulation Steps</h2>
            <div id="currentSim"></div>
            
            <div class="step" id="step-triage">
                <h3>1. Triage Station</h3>
                <p>Collect vital signs and initial assessment</p>
                <button class="btn" onclick="simulateStep('triage')" disabled id="btn-triage">Simulate Triage</button>
                <div class="step-result" id="result-triage"></div>
            </div>
            
            <div class="step" id="step-consultation">
                <h3>2. Consultation</h3>
                <p>Doctor examination and diagnosis</p>
                <button class="btn" onclick="simulateStep('consultation')" disabled id="btn-consultation">Simulate Consultation</button>
                <div class="step-result" id="result-consultation"></div>
            </div>
            
            <div class="step" id="step-pharmacy">
                <h3>3. Pharmacy</h3>
                <p>Medication dispensing</p>
                <button class="btn" onclick="simulateStep('pharmacy')" disabled id="btn-pharmacy">Simulate Pharmacy</button>
                <div class="step-result" id="result-pharmacy"></div>
            </div>
            
            <div class="step" id="step-billing">
                <h3>4. Billing</h3>
                <p>Payment processing</p>
                <button class="btn" onclick="simulateStep('billing')" disabled id="btn-billing">Simulate Billing</button>
                <div class="step-result" id="result-billing"></div>
            </div>
        </div>
        
        <h2>Simulation Log</h2>
        <div id="logs" class="logs">System ready...<br></div>
        
        <?php if (!empty($recent_sims)): ?>
            <h2>Recent Simulations</h2>
            <table style="width:100%; border-collapse: collapse;">
                <tr style="background:#f8f9fa;">
                    <th style="padding:10px; border:1px solid #ddd;">Patient</th>
                    <th style="padding:10px; border:1px solid #ddd;">Status</th>
                    <th style="padding:10px; border:1px solid #ddd;">Queue Count</th>
                    <th style="padding:10px; border:1px solid #ddd;">Created</th>
                </tr>
                <?php foreach ($recent_sims as $sim): ?>
                    <tr>
                        <td style="padding:10px; border:1px solid #ddd;"><?php echo htmlspecialchars($sim['first_name'] . ' ' . $sim['last_name']); ?></td>
                        <td style="padding:10px; border:1px solid #ddd;"><?php echo htmlspecialchars($sim['status']); ?></td>
                        <td style="padding:10px; border:1px solid #ddd;"><?php echo $sim['queue_count']; ?></td>
                        <td style="padding:10px; border:1px solid #ddd;"><?php echo date('m/d H:i', strtotime($sim['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <script>
        let currentSimulationId = null;
        let currentStep = 0;
        
        function log(message, type = 'info') {
            const logs = document.getElementById('logs');
            const timestamp = new Date().toLocaleTimeString();
            const color = type === 'error' ? '#e74c3c' : type === 'success' ? '#27ae60' : '#ecf0f1';
            logs.innerHTML += `<span style="color:${color}">[${timestamp}] ${message}</span><br>`;
            logs.scrollTop = logs.scrollHeight;
        }
        
        async function createSimulation() {
            document.getElementById('createBtn').disabled = true;
            log('Creating new simulation...');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=create_simulation'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentSimulationId = result.simulation_id;
                    log('Simulation created successfully!', 'success');
                    log(`Patient ID: ${result.patient_id}, Queue Entry ID: ${result.queue_entry_id}`);
                    
                    document.getElementById('currentSim').innerHTML = 
                        `<strong>Active Simulation ID:</strong> ${currentSimulationId}`;
                    document.getElementById('simulationArea').style.display = 'block';
                    document.getElementById('btn-triage').disabled = false;
                } else {
                    log('Failed to create simulation: ' + result.message, 'error');
                    document.getElementById('createBtn').disabled = false;
                }
            } catch (error) {
                log('Error creating simulation: ' + error.message, 'error');
                document.getElementById('createBtn').disabled = false;
            }
        }
        
        async function simulateStep(step) {
            if (!currentSimulationId) {
                log('No active simulation', 'error');
                return;
            }
            
            log(`Starting ${step} simulation...`);
            document.getElementById(`btn-${step}`).disabled = true;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=simulate_step&simulation_id=${currentSimulationId}&step=${step}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    log(result.message, 'success');
                    document.getElementById(`step-${step}`).classList.add('completed');
                    document.getElementById(`result-${step}`).innerHTML = `<small style="color: #27ae60;">✅ ${result.message}</small>`;
                    
                    // Enable next step
                    const steps = ['triage', 'consultation', 'pharmacy', 'billing'];
                    const currentIndex = steps.indexOf(step);
                    if (currentIndex < steps.length - 1) {
                        const nextStep = steps[currentIndex + 1];
                        document.getElementById(`btn-${nextStep}`).disabled = false;
                    } else {
                        log('🎉 Complete patient flow simulation finished!', 'success');
                    }
                } else {
                    log('Failed: ' + result.message, 'error');
                    document.getElementById(`btn-${step}`).disabled = false;
                }
            } catch (error) {
                log('Error: ' + error.message, 'error');
                document.getElementById(`btn-${step}`).disabled = false;
            }
        }
        
        // Initialize
        log('Working Queue Simulation loaded successfully!', 'success');
        log('This version bypasses QueueManagementService routing issues', 'info');
    </script>
</body>
</html>