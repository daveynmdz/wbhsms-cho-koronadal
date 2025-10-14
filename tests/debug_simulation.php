<?php
/**
 * Debug Queue Simulation - Raw Response Checker
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
    
    // Add debug output before JSON
    ob_start();
    
    $action = $_POST['action'];
    
    try {
        if ($action === 'simulate_step') {
            $simulation_id = intval($_POST['simulation_id']);
            $step = $_POST['step'];
            
            // Debug: Check what we received
            error_log("Debug: Simulating step $step for appointment $simulation_id");
            
            // Get current queue entry
            $stmt = $pdo->prepare("
                SELECT qe.*, s.station_type 
                FROM queue_entries qe
                LEFT JOIN stations s ON qe.station_id = s.station_id
                WHERE qe.appointment_id = ?
                ORDER BY qe.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$simulation_id]);
            $queue_entry = $stmt->fetch(PDO::FETCH_ASSOC);
            
            error_log("Debug: Found queue entry: " . json_encode($queue_entry));
            
            if (!$queue_entry) {
                echo json_encode(['success' => false, 'message' => 'No queue entry found for this appointment']);
                exit();
            }
            
            $queue_entry_id = $queue_entry['queue_entry_id'];
            
            // Simple simulation for triage
            if ($step === 'triage') {
                if ($queue_entry['status'] === 'done' && $queue_entry['station_type'] === 'triage') {
                    // Route to consultation
                    $result = $queueService->routePatientToStation($queue_entry_id, 'consultation', $employee_id, 'Routed to consultation after triage');
                    echo json_encode(['success' => true, 'message' => 'Triage already completed, routed to consultation']);
                } else {
                    // Complete triage and route
                    $queueService->updateQueueStatus($queue_entry_id, 'done', $queue_entry['status'], $employee_id, 'Triage completed');
                    $result = $queueService->routePatientToStation($queue_entry_id, 'consultation', $employee_id, 'Routed to consultation');
                    echo json_encode(['success' => true, 'message' => 'Triage completed, routed to consultation']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Only triage step supported in debug mode']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Exception in simulation: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
    }
    
    // Capture any unexpected output
    $output = ob_get_clean();
    if (!empty($output)) {
        error_log("Unexpected output: " . $output);
    }
    
    exit();
}

// If not AJAX, show test form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Queue Simulation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        .btn { background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .logs { background: #2c3e50; color: white; padding: 15px; border-radius: 5px; font-family: monospace; height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Queue Simulation</h1>
        
        <p>Test the triage simulation with raw response debugging.</p>
        
        <button class="btn" onclick="testTriage()">Test Triage Step</button>
        
        <h3>Debug Log</h3>
        <div id="logs" class="logs">Debug mode ready...<br></div>
        
        <h3>Raw Response</h3>
        <div id="rawResponse" class="logs">No response yet...<br></div>
    </div>

    <script>
        function log(message) {
            const logs = document.getElementById('logs');
            const timestamp = new Date().toLocaleTimeString();
            logs.innerHTML += `[${timestamp}] ${message}<br>`;
            logs.scrollTop = logs.scrollHeight;
        }
        
        function showRaw(response) {
            const rawDiv = document.getElementById('rawResponse');
            rawDiv.innerHTML = `Raw Response:<br>${response}<br>`;
        }
        
        async function testTriage() {
            log('Testing triage simulation...');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=simulate_step&simulation_id=43&step=triage'
                });
                
                // Get raw response text first
                const rawText = await response.text();
                log('Received response: ' + rawText.length + ' characters');
                showRaw(rawText);
                
                // Try to parse as JSON
                try {
                    const result = JSON.parse(rawText);
                    log('JSON parsed successfully: ' + JSON.stringify(result));
                } catch (jsonError) {
                    log('JSON parse error: ' + jsonError.message);
                    log('Raw text: ' + rawText);
                }
                
            } catch (error) {
                log('Fetch error: ' + error.message);
            }
        }
        
        log('Debug simulation ready');
    </script>
</body>
</html>