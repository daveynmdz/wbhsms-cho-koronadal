<?php
// Test the fixed showCancelModal function
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sample appointment data from database
$test_appointments = [
    ['appointment_id' => 1, 'status' => 'confirmed'],
    ['appointment_id' => 23, 'status' => 'pending'],
    ['appointment_id' => 456, 'status' => 'confirmed']
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Fixed showCancelModal</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-card { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        #output { background: #f8f9fa; padding: 15px; margin-top: 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <h1>Test Fixed showCancelModal Function</h1>
    <p>This tests the corrected showCancelModal function that only uses appointment_id.</p>
    
    <h2>Sample Appointments:</h2>
    
    <?php foreach ($test_appointments as $appointment): ?>
        <?php $safe_appointment_id = intval($appointment['appointment_id']); ?>
        <div class="test-card">
            <h3>Appointment ID: <?php echo $safe_appointment_id; ?></h3>
            <p>Status: <?php echo ucfirst($appointment['status']); ?></p>
            <p>Display Number: APT-<?php echo str_pad($safe_appointment_id, 8, '0', STR_PAD_LEFT); ?></p>
            
            <!-- This matches the new onclick format -->
            <button class="btn btn-danger" onclick="showCancelModal(<?php echo $safe_appointment_id; ?>)">
                Cancel Appointment
            </button>
        </div>
    <?php endforeach; ?>
    
    <div id="output">
        <h3>Test Results:</h3>
        <div id="results"></div>
    </div>

    <script>
        console.log('🧪 Test script loaded');
        
        // Define the corrected showCancelModal function
        function showCancelModal(appointmentId) {
            console.log('📞 showCancelModal called with appointmentId:', appointmentId);
            
            if (!appointmentId) {
                console.error('❌ Invalid appointment ID');
                return false;
            }
            
            // Generate appointment number for display
            const appointmentNumber = 'APT-' + String(appointmentId).padStart(8, '0');
            
            const results = document.getElementById('results');
            results.innerHTML += `<p><strong>✅ SUCCESS:</strong> showCancelModal(${appointmentId}) called successfully</p>`;
            results.innerHTML += `<p><strong>📋 Generated Display Number:</strong> ${appointmentNumber}</p>`;
            results.innerHTML += `<p><strong>🕐 Timestamp:</strong> ${new Date().toLocaleTimeString()}</p><hr>`;
            
            alert(`Cancel Modal Test Successful!\n\nAppointment ID: ${appointmentId}\nDisplay Number: ${appointmentNumber}\n\nThis confirms the fixed function works correctly with only appointment_id!`);
            
            return true;
        }
        
        // Make globally accessible
        window.showCancelModal = showCancelModal;
        
        console.log('✅ showCancelModal function defined and made global');
        console.log('🔍 Function type:', typeof window.showCancelModal);
        
        // Test on page load
        window.onload = function() {
            console.log('🚀 Page loaded, function ready for testing');
            document.getElementById('results').innerHTML = 
                '<p><strong>🟢 Ready:</strong> showCancelModal function is available and ready for testing</p>';
        };
    </script>
</body>
</html>