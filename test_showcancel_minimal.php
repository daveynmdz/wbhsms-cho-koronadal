<?php
// Test if there's a PHP parsing issue
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing PHP output...";

// Sample appointment data
$test_appointment = [
    'appointment_id' => 123,
    'status' => 'confirmed'
];

$safe_appointment_id = intval($test_appointment['appointment_id']);
$safe_appointment_number = 'APT-' . str_pad($safe_appointment_id, 8, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test showCancelModal Issue</title>
</head>
<body>
    <h1>Testing showCancelModal Function</h1>
    
    <p>Testing if PHP variables output correctly:</p>
    <p>Safe ID: <?php echo $safe_appointment_id; ?></p>
    <p>Safe Number: <?php echo htmlspecialchars($safe_appointment_number, ENT_QUOTES, 'UTF-8'); ?></p>
    
    <p>Testing button onclick:</p>
    <button onclick="showCancelModal(<?php echo $safe_appointment_id; ?>, '<?php echo htmlspecialchars($safe_appointment_number, ENT_QUOTES, 'UTF-8'); ?>')">
        Test Cancel Button
    </button>
    
    <div id="output"></div>

    <script>
        console.log('Script starting...');
        
        // Define function at the very top
        function showCancelModal(appointmentId, appointmentNumber) {
            console.log('showCancelModal called:', appointmentId, appointmentNumber);
            document.getElementById('output').innerHTML = 
                '<p style="color: green;">✅ Function called successfully!<br>' +
                'ID: ' + appointmentId + '<br>' +
                'Number: ' + appointmentNumber + '</p>';
            alert('Success!\nID: ' + appointmentId + '\nNumber: ' + appointmentNumber);
        }
        
        console.log('Function defined. Type:', typeof showCancelModal);
        
        // Test that function exists
        if (typeof showCancelModal === 'function') {
            console.log('✅ showCancelModal is available');
            document.getElementById('output').innerHTML = '<p style="color: blue;">✅ Function is properly defined</p>';
        } else {
            console.error('❌ showCancelModal is NOT available');
            document.getElementById('output').innerHTML = '<p style="color: red;">❌ Function is NOT defined</p>';
        }
    </script>
</body>
</html>