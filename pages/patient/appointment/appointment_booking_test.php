<?php
// appointment_booking_test.php - Simple test for appointment booking functionality

session_start();
require_once '../../../config/db.php';

// Set up test session
$_SESSION['patient_id'] = 7; // David Diaz from logs

echo "<!DOCTYPE html>";
echo "<html><head><title>Appointment Booking Test</title></head><body>";
echo "<h1>🧪 Appointment Booking Fix Test</h1>";

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>🔧 Fixes Applied:</h2>";
echo "<ol>";
echo "<li><strong>JSON Input Handling:</strong> submit_appointment.php now accepts both JSON and form data</li>";
echo "<li><strong>Patient ID from Session:</strong> Automatically uses session patient_id if not provided</li>";
echo "<li><strong>Service Name Fix:</strong> Changed 'Primary Care' to 'General Consultation' in JavaScript</li>";
echo "<li><strong>Better Error Messages:</strong> More detailed validation and debugging information</li>";
echo "<li><strong>Referral Handling:</strong> Improved null/empty referral_id handling</li>";
echo "</ol>";
echo "</div>";

echo "<h2>📋 Test Appointment Booking</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<p><strong>Test Patient:</strong> " . ($_SESSION['patient_id'] ?? 'Not set') . "</p>";

// Check patient exists
if (isset($_SESSION['patient_id'])) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $_SESSION['patient_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    $stmt->close();
    
    if ($patient) {
        echo "<p><strong>Patient Name:</strong> " . $patient['first_name'] . " " . $patient['last_name'] . "</p>";
        echo "<p><strong>Email:</strong> " . ($patient['email'] ?? 'Not set') . "</p>";
    }
}

echo "</div>";

?>

<div style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3>🚀 Quick Test Forms</h3>
    
    <h4>1. JSON Submission Test (Simulates JavaScript)</h4>
    <button onclick="testJsonSubmission()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
        Test JSON Booking (BHC)
    </button>
    
    <h4>2. Form Submission Test</h4>
    <form method="POST" action="submit_appointment.php" style="margin: 10px 0;">
        <input type="hidden" name="patient_id" value="<?php echo $_SESSION['patient_id'] ?? ''; ?>">
        <input type="hidden" name="facility_type" value="bhc">
        <input type="hidden" name="service" value="General Consultation">
        <input type="hidden" name="appointment_date" value="2025-10-15">
        <input type="hidden" name="appointment_time" value="09:00">
        <button type="submit" style="background: #0077b6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            Test Form Booking (BHC)
        </button>
    </form>
</div>

<div id="test-results" style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; display: none;">
    <h3>📊 Test Results</h3>
    <pre id="result-content"></pre>
</div>

<script>
function testJsonSubmission() {
    const testData = {
        patient_id: <?php echo $_SESSION['patient_id'] ?? 'null'; ?>,
        facility_type: 'bhc',
        service: 'Primary Care',
        appointment_date: '2025-10-15',
        appointment_time: '09:00',
        referral_id: null
    };
    
    console.log('🧪 Testing JSON submission with data:', testData);
    
    fetch('submit_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(testData)
    })
    .then(response => response.text())
    .then(text => {
        console.log('📤 Raw server response:', text);
        
        // Show results
        document.getElementById('test-results').style.display = 'block';
        document.getElementById('result-content').textContent = text;
        
        try {
            const data = JSON.parse(text);
            console.log('✅ Parsed response:', data);
            
            if (data.success) {
                alert('✅ Test successful! Appointment booked: ' + data.appointment_num);
            } else {
                alert('❌ Test failed: ' + data.message);
            }
        } catch (e) {
            console.error('❌ Failed to parse response as JSON:', e);
            alert('❌ Test failed - server returned invalid JSON. Check results below.');
        }
    })
    .catch(error => {
        console.error('❌ Fetch error:', error);
        alert('❌ Network error occurred. Check console.');
    });
}
</script>

<?php
echo "<h2>🔍 Debugging Information</h2>";
echo "<p><a href='debug_services.php' target='_blank' style='color: #0077b6;'>→ Check Service Names</a></p>";
echo "<p><a href='test_appointment_submission.php' target='_blank' style='color: #0077b6;'>→ Detailed Submission Test</a></p>";
echo "<p><a href='book_appointment.php' style='color: #28a745;'>→ Go to Real Booking Form</a></p>";

echo "</body></html>";
?>