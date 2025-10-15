<?php
/**
 * Direct AJAX endpoint test for appointment details
 */

// Set up session like the main checkin page
session_start();
$_SESSION['employee_id'] = 1;
$_SESSION['role'] = 'admin';

// Set proper headers for JSON response
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

echo "<h2>Testing AJAX Appointment Details Endpoint</h2>";

// Get a sample appointment
$stmt = $pdo->query("SELECT appointment_id, patient_id FROM appointments WHERE facility_id = 1 LIMIT 1");
$sample = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sample) {
    echo "<p>No appointments found to test with</p>";
    exit;
}

echo "<p>Testing with appointment ID: {$sample['appointment_id']}, patient ID: {$sample['patient_id']}</p>";

// Simulate the AJAX request
$_POST['action'] = 'get_appointment_details';
$_POST['ajax'] = '1';
$_POST['appointment_id'] = $sample['appointment_id'];
$_POST['patient_id'] = $sample['patient_id'];

echo "<h3>Direct Endpoint Test</h3>";
echo "<iframe src='test_ajax_direct.php' width='100%' height='400'></iframe>";

echo "<h3>cURL Test</h3>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/wbhsms-cho-koronadal-1/pages/queueing/checkin.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'action' => 'get_appointment_details',
    'ajax' => '1',
    'appointment_id' => $sample['appointment_id'],
    'patient_id' => $sample['patient_id']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: {$http_code}</p>";
echo "<h4>Raw Response:</h4>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px; max-height: 300px; overflow: auto;'>";
echo htmlspecialchars($response);
echo "</pre>";

// Try to parse as JSON
$header_size = strpos($response, "\r\n\r\n");
$body = substr($response, $header_size + 4);

echo "<h4>Response Body Only:</h4>";
echo "<pre style='background: #f0f8ff; padding: 10px; border-radius: 5px;'>";
echo htmlspecialchars($body);
echo "</pre>";

$json_data = json_decode($body, true);
if ($json_data === null) {
    echo "<p>❌ JSON parsing failed: " . json_last_error_msg() . "</p>";
} else {
    echo "<p>✅ JSON parsing successful</p>";
    echo "<p>Response data:</p>";
    echo "<pre>";
    print_r($json_data);
    echo "</pre>";
}
?>