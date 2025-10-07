<?php
// Simple debug script to test JavaScript functionality
session_start();

// Sample data for testing
$sample_appointments = [
    [
        'appointment_id' => 1,
        'status' => 'confirmed',
        'facility_name' => 'Test Facility',
        'service_name' => 'General Consultation',
        'scheduled_date' => '2024-10-08',
        'scheduled_time' => '09:00:00'
    ],
    [
        'appointment_id' => 2,
        'status' => 'pending',
        'facility_name' => 'Test Clinic',
        'service_name' => 'Blood Test',
        'scheduled_date' => '2024-10-09',
        'scheduled_time' => '10:30:00'
    ]
];

$sample_patient = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'username' => 'johndoe',
    'contact_num' => '09123456789'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Appointments JavaScript</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .appointment-card { 
            border: 1px solid #ccc; 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 8px; 
        }
        .btn { 
            padding: 8px 16px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            background: #dc3545; 
            color: white; 
        }
        .btn:hover { background: #c82333; }
    </style>
</head>
<body>
    <h1>Debug Appointments JavaScript</h1>
    
    <h2>Test Appointments with Cancel Buttons</h2>
    
    <?php foreach ($sample_appointments as $appointment): ?>
        <?php 
        $safe_appointment_id = intval($appointment['appointment_id']);
        $safe_appointment_number = 'APT-' . str_pad($safe_appointment_id, 8, '0', STR_PAD_LEFT);
        ?>
        <div class="appointment-card">
            <h3>Appointment #<?php echo $safe_appointment_number; ?></h3>
            <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?></p>
            <p><strong>Facility:</strong> <?php echo htmlspecialchars($appointment['facility_name']); ?></p>
            <p><strong>Date:</strong> <?php echo $appointment['scheduled_date']; ?> at <?php echo $appointment['scheduled_time']; ?></p>
            <p><strong>Status:</strong> <?php echo ucfirst($appointment['status']); ?></p>
            
            <button class="btn" onclick="showCancelModal(<?php echo $safe_appointment_id; ?>, '<?php echo htmlspecialchars($safe_appointment_number, ENT_QUOTES, 'UTF-8'); ?>')">
                Cancel Appointment
            </button>
        </div>
    <?php endforeach; ?>
    
    <div id="debug-output" style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
        <h3>Debug Output</h3>
        <div id="console-output"></div>
    </div>

    <script>
        // Debug logging
        function debugLog(message) {
            const output = document.getElementById('console-output');
            const timestamp = new Date().toLocaleTimeString();
            output.innerHTML += `<p>[${timestamp}] ${message}</p>`;
            console.log(message);
        }

        debugLog('JavaScript loaded successfully');

        // Test data
        const appointments = <?php echo json_encode($sample_appointments ?: []); ?>;
        const patientInfo = <?php echo json_encode($sample_patient ?: []); ?>;
        
        debugLog('Sample data loaded: ' + appointments.length + ' appointments');

        // Cancel modal functionality
        function showCancelModal(appointmentId, appointmentNumber) {
            debugLog(`showCancelModal called with ID: ${appointmentId}, Number: ${appointmentNumber}`);
            
            const confirmation = confirm(`Cancel appointment ${appointmentNumber}?\n\nThis is a test - no actual cancellation will occur.`);
            
            if (confirmation) {
                debugLog(`User confirmed cancellation of appointment ${appointmentNumber}`);
                alert(`Test successful! In the real system, appointment ${appointmentNumber} would be cancelled.`);
            } else {
                debugLog(`User cancelled the cancellation dialog for appointment ${appointmentNumber}`);
            }
        }

        // Test function existence
        if (typeof showCancelModal === 'function') {
            debugLog('✅ showCancelModal function is properly defined');
        } else {
            debugLog('❌ showCancelModal function is NOT defined');
        }

        // Test onclick handlers
        debugLog('Checking onclick handlers...');
        const cancelButtons = document.querySelectorAll('.btn');
        debugLog(`Found ${cancelButtons.length} cancel buttons`);

        // Page load complete
        debugLog('Page initialization complete');
    </script>
</body>
</html>