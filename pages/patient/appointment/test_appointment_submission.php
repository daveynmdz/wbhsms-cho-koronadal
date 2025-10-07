<?php
// test_appointment_submission.php - Test the fixed appointment submission

session_start();
require_once '../../../config/db.php';

echo "<h1>🧪 Appointment Submission Test</h1>";

// Simulate patient session
$_SESSION['patient_id'] = 7; // David Diaz's patient ID from the logs

echo "<h2>1. Session Test</h2>";
echo "Patient ID from session: " . ($_SESSION['patient_id'] ?? 'Not set') . "<br>";

echo "<h2>2. Test JSON Data Parsing</h2>";

// Test JSON input parsing
$test_json = json_encode([
    'patient_id' => 7,
    'facility_type' => 'bhc',
    'service' => 'General Consultation',
    'appointment_date' => '2025-10-15',
    'appointment_time' => '09:00',
    'referral_id' => null
]);

echo "Test JSON: " . htmlspecialchars($test_json) . "<br><br>";

// Parse it like the submit_appointment.php would
$parsed_data = json_decode($test_json, true);
echo "Parsed data:<br>";
foreach ($parsed_data as $key => $value) {
    echo "  - $key: " . ($value ?? 'null') . "<br>";
}

echo "<h2>3. Database Connection Test</h2>";
if ($conn) {
    echo "✅ Database connection successful<br>";
    
    // Test patient lookup
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $_SESSION['patient_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
    $stmt->close();
    
    if ($patient) {
        echo "✅ Patient found: " . $patient['first_name'] . " " . $patient['last_name'] . "<br>";
    } else {
        echo "❌ Patient not found<br>";
    }
} else {
    echo "❌ Database connection failed<br>";
}

echo "<h2>4. Service and Facility Validation Test</h2>";

// Test service lookup
$test_service = 'Primary Care';
$stmt = $conn->prepare("SELECT service_id FROM services WHERE name = ?");
$stmt->bind_param("s", $test_service);
$stmt->execute();
$result = $stmt->get_result();
$service_row = $result->fetch_assoc();
$stmt->close();

if ($service_row) {
    echo "✅ Service '$test_service' found - ID: " . $service_row['service_id'] . "<br>";
} else {
    echo "❌ Service '$test_service' not found<br>";
    
    // Show available services
    $stmt = $conn->prepare("SELECT name FROM services LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "Available services: ";
    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row['name'];
    }
    echo implode(', ', $services) . "<br>";
    $stmt->close();
}

echo "<h2>5. Referral Status Test</h2>";
$stmt = $conn->prepare("SELECT referral_id, status, referring_facility_id FROM referrals WHERE patient_id = ? LIMIT 3");
$stmt->bind_param("i", $_SESSION['patient_id']);
$stmt->execute();
$result = $stmt->get_result();
$referrals = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($referrals) {
    echo "Found " . count($referrals) . " referrals:<br>";
    foreach ($referrals as $ref) {
        echo "  - ID: {$ref['referral_id']}, Status: {$ref['status']}, From Facility: {$ref['referring_facility_id']}<br>";
    }
} else {
    echo "No referrals found for patient ID " . $_SESSION['patient_id'] . "<br>";
}

echo "<h2>6. Simulated Appointment Submission Test</h2>";

// Create a test submission
echo "<form method='POST' action='submit_appointment.php'>";
echo "<input type='hidden' name='patient_id' value='7'>";
echo "<input type='hidden' name='facility_type' value='bhc'>";
echo "<input type='hidden' name='service' value='General Consultation'>";
echo "<input type='hidden' name='appointment_date' value='2025-10-15'>";
echo "<input type='hidden' name='appointment_time' value='09:00'>";
echo "<button type='submit' style='background: #0077b6; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test Form Submission</button>";
echo "</form>";

echo "<br><h3>JavaScript Test (Console)</h3>";
echo "<button onclick='testJsonSubmission()' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Test JSON Submission</button>";

?>
<script>
function testJsonSubmission() {
    const testData = {
        patient_id: 7,
        facility_type: 'bhc',
        service: 'General Consultation',
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
        try {
            const data = JSON.parse(text);
            console.log('✅ Parsed response:', data);
            alert('Test completed - check console for details');
        } catch (e) {
            console.error('❌ Failed to parse response as JSON:', e);
            alert('Test failed - server returned invalid JSON. Check console.');
        }
    })
    .catch(error => {
        console.error('❌ Fetch error:', error);
        alert('Network error occurred. Check console.');
    });
}
</script>