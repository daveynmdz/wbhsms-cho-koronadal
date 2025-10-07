<!DOCTYPE html>
<html>
<head>
    <title>Test Appointments JavaScript</title>
    <script>
        // Test function to see if showCancelModal is working
        function showCancelModal(appointmentId, appointmentNumber) {
            console.log('showCancelModal called with:', appointmentId, appointmentNumber);
            alert('showCancelModal function is working!\nAppointment ID: ' + appointmentId + '\nAppointment Number: ' + appointmentNumber);
        }
        
        // Test the onclick functionality
        function testCancelButton() {
            showCancelModal(123, 'APT-00000123');
        }
    </script>
</head>
<body>
    <h1>Test Appointments JavaScript Functions</h1>
    
    <button onclick="testCancelButton()">Test Cancel Modal Function</button>
    
    <br><br>
    
    <!-- Simulate the actual button from appointments.php -->
    <button class="btn btn-sm btn-outline btn-outline-danger" onclick="showCancelModal(456, 'APT-00000456')">
        <i class="fas fa-times"></i> Test Cancel Button (Simulated)
    </button>
    
    <script>
        console.log('Test script loaded successfully');
        console.log('showCancelModal function available:', typeof showCancelModal);
    </script>
</body>
</html>