<?php
// quick_appointment_test.php - Quick test of the fixed appointment booking

session_start();
$_SESSION['patient_id'] = 7; // David Diaz

?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Appointment Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-radius: 5px; color: #155724; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-radius: 5px; color: #0c5460; margin: 10px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .test-button { background: #28a745; }
        .test-button:hover { background: #1e7e34; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Quick Appointment Booking Test</h1>
        
        <div class="info">
            <h3>🔧 Database Schema Fixes Applied:</h3>
            <ul>
                <li>✅ Fixed column names: appointment_date → scheduled_date</li>
                <li>✅ Fixed column names: appointment_time → scheduled_time</li>
                <li>✅ Removed non-existent appointment_num column</li>
                <li>✅ Changed status from 'pending' → 'confirmed'</li>
                <li>✅ Generate appointment number after insert</li>
            </ul>
        </div>

        <h2>📋 Test Patient Information</h2>
        <p><strong>Patient ID:</strong> <?php echo $_SESSION['patient_id'] ?? 'Not set'; ?></p>
        <p><strong>Test Date:</strong> 2025-10-15 (Tomorrow)</p>
        <p><strong>Test Time:</strong> 09:00 AM</p>

        <div id="test-results" style="display: none;">
            <h3>📊 Test Results</h3>
            <pre id="result-content"></pre>
        </div>

        <h2>🚀 Test Actions</h2>
        
        <button class="test-button" onclick="testDatabaseSchema()">
            1. Test Database Schema
        </button>
        
        <button class="test-button" onclick="testAppointmentBooking()">
            2. Test Appointment Booking
        </button>
        
        <button onclick="window.open('appointment_booking_test.php', '_blank')">
            3. Open Full Test Suite
        </button>
        
        <button onclick="window.open('book_appointment.php', '_blank')">
            4. Go to Real Booking Form
        </button>

        <div id="status-area"></div>
    </div>

    <script>
        function showResult(title, content, type = 'info') {
            const statusArea = document.getElementById('status-area');
            const className = type === 'success' ? 'success' : type === 'error' ? 'error' : 'info';
            
            statusArea.innerHTML = `
                <div class="${className}">
                    <h3>${title}</h3>
                    <pre>${content}</pre>
                </div>
            `;
            
            console.log(`${title}:`, content);
        }

        function testDatabaseSchema() {
            showResult('🔍 Testing Database Schema', 'Checking appointments table structure...');
            
            fetch('test_database_columns.php')
                .then(response => response.text())
                .then(html => {
                    if (html.includes('Insert query preparation successful')) {
                        showResult('✅ Database Schema Test', 'Database schema is correct!\nAll column names match the actual table structure.', 'success');
                    } else {
                        showResult('❌ Database Schema Test', 'Schema issues detected. Check test_database_columns.php', 'error');
                    }
                })
                .catch(error => {
                    showResult('❌ Schema Test Error', error.toString(), 'error');
                });
        }

        function testAppointmentBooking() {
            showResult('🧪 Testing Appointment Booking', 'Submitting test appointment...');
            
            const testData = {
                patient_id: <?php echo $_SESSION['patient_id'] ?? 'null'; ?>,
                facility_type: 'bhc',
                service: 'Primary Care',
                appointment_date: '2025-10-15',
                appointment_time: '09:00',
                referral_id: null
            };

            console.log('📤 Sending test data:', testData);

            fetch('submit_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(testData)
            })
            .then(response => response.text())
            .then(text => {
                console.log('📥 Raw response:', text);
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        showResult('✅ Appointment Booking Success!', 
                            `Appointment created successfully!\n` +
                            `Appointment ID: ${data.appointment_id}\n` +
                            `Appointment Number: ${data.appointment_num}\n` +
                            `Facility: ${data.facility_name}\n` +
                            `QR Generated: ${data.qr_generated ? 'Yes' : 'No'}\n` +
                            `Queue Created: ${data.has_queue ? 'Yes (Queue #' + data.queue_number + ')' : 'No (BHC - No Queue Required)'}`, 
                            'success');
                    } else {
                        showResult('❌ Booking Failed', `Error: ${data.message}`, 'error');
                    }
                } catch (e) {
                    showResult('❌ Response Parse Error', `Invalid JSON response:\n${text}`, 'error');
                }
            })
            .catch(error => {
                showResult('❌ Network Error', error.toString(), 'error');
            });
        }

        // Auto-run schema test on page load
        window.addEventListener('load', function() {
            setTimeout(testDatabaseSchema, 1000);
        });
    </script>
</body>
</html>