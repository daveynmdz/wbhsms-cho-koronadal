<!DOCTYPE html>
<html>
<head>
    <title>Debug JavaScript Issues</title>
</head>
<body>
    <h1>Debug Appointments JavaScript</h1>
    
    <p>Testing if showCancelModal function is accessible:</p>
    <button onclick="testFunction()">Test Function Accessibility</button>
    
    <br><br>
    
    <p>Testing onclick with sample data:</p>
    <button onclick="showCancelModal(123, 'APT-00000123')">Test Cancel Modal</button>
    
    <div id="results" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc;">
        <h3>Results:</h3>
        <div id="output"></div>
    </div>

    <script>
        // Define the function locally for testing
        function showCancelModal(appointmentId, appointmentNumber) {
            console.log('showCancelModal called successfully!');
            console.log('Appointment ID:', appointmentId);
            console.log('Appointment Number:', appointmentNumber);
            
            document.getElementById('output').innerHTML += 
                '<p><strong>SUCCESS:</strong> showCancelModal(' + appointmentId + ', "' + appointmentNumber + '") executed successfully!</p>';
            
            alert('Function works! ID: ' + appointmentId + ', Number: ' + appointmentNumber);
        }
        
        function testFunction() {
            try {
                if (typeof showCancelModal === 'function') {
                    document.getElementById('output').innerHTML += 
                        '<p style="color: green;"><strong>PASS:</strong> showCancelModal function is defined and accessible</p>';
                } else {
                    document.getElementById('output').innerHTML += 
                        '<p style="color: red;"><strong>FAIL:</strong> showCancelModal is not a function</p>';
                }
            } catch (e) {
                document.getElementById('output').innerHTML += 
                    '<p style="color: red;"><strong>ERROR:</strong> ' + e.message + '</p>';
            }
        }
        
        // Test on page load
        window.onload = function() {
            document.getElementById('output').innerHTML = 
                '<p><strong>Page loaded.</strong> Testing function availability...</p>';
            testFunction();
        };
    </script>
</body>
</html>