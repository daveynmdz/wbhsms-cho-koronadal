<?php
require_once 'config/db.php';

echo "<h2>COMPREHENSIVE PRODUCTION SIMULATION TEST</h2>";

// First, let's check the actual column definition
echo "<h3>1. Current Database Schema Check</h3>";
$result = $conn->query("SHOW COLUMNS FROM prescribed_medications LIKE 'status'");
if ($result && $row = $result->fetch_assoc()) {
    echo "<p><strong>Current status column:</strong> " . htmlspecialchars($row['Type']) . "</p>";
    
    // Check if it's an ENUM and what values are allowed
    if (strpos($row['Type'], 'enum') !== false) {
        echo "<p style='color: orange;'><strong>WARNING:</strong> Column is ENUM type. The values 'dispensed' and 'unavailable' may not be in the allowed list!</p>";
        echo "<p><strong>Allowed values:</strong> " . htmlspecialchars($row['Type']) . "</p>";
    }
} else {
    echo "<p style='color: red;'>Could not get column info</p>";
}

// Test the actual API endpoint with real data
echo "<h3>2. API Endpoint Test</h3>";

// Create a test prescription and medication if needed
$testPrescriptionId = null;
$testMedicationId = null;

try {
    // Find an existing prescription to test with
    $findPrescription = $conn->query("SELECT prescription_id FROM prescriptions LIMIT 1");
    if ($findPrescription && $row = $findPrescription->fetch_assoc()) {
        $testPrescriptionId = $row['prescription_id'];
        
        // Find a medication for this prescription
        $findMedication = $conn->query("SELECT prescribed_medication_id FROM prescribed_medications WHERE prescription_id = $testPrescriptionId LIMIT 1");
        if ($findMedication && $medRow = $findMedication->fetch_assoc()) {
            $testMedicationId = $medRow['prescribed_medication_id'];
        }
    }
    
    if ($testPrescriptionId && $testMedicationId) {
        echo "<p>Using test prescription ID: $testPrescriptionId, medication ID: $testMedicationId</p>";
        
        // Test the API call that was failing
        $testData = [
            'prescription_id' => $testPrescriptionId,
            'medication_statuses' => [
                [
                    'prescribed_medication_id' => $testMedicationId,
                    'status' => 'unavailable'  // This was causing the error
                ]
            ]
        ];
        
        // Simulate the API call
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/wbhsms-cho-koronadal-1/api/update_prescription_medications.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<h4>API Response:</h4>";
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        if ($httpCode === 200) {
            $jsonResponse = json_decode($response, true);
            if ($jsonResponse && isset($jsonResponse['success'])) {
                if ($jsonResponse['success']) {
                    echo "<p style='color: green; font-weight: bold;'>✓ API CALL SUCCESSFUL!</p>";
                } else {
                    echo "<p style='color: red; font-weight: bold;'>✗ API CALL FAILED: " . htmlspecialchars($jsonResponse['message']) . "</p>";
                }
            }
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ HTTP ERROR: $httpCode</p>";
        }
        
    } else {
        echo "<p style='color: red;'>No test data available - please create a prescription first</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test direct database insert with problematic values
echo "<h3>3. Direct Database Insert Test</h3>";

try {
    $conn->begin_transaction();
    
    // Test inserting 'unavailable' directly
    $testInsert = "UPDATE prescribed_medications SET status = 'unavailable' WHERE prescribed_medication_id = ? LIMIT 1";
    $stmt = $conn->prepare($testInsert);
    
    if ($testMedicationId && $stmt) {
        $stmt->bind_param("i", $testMedicationId);
        $result = $stmt->execute();
        
        if ($result) {
            echo "<p style='color: green;'>✓ Direct database update with 'unavailable' SUCCESSFUL</p>";
        } else {
            echo "<p style='color: red;'>✗ Direct database update FAILED: " . $stmt->error . "</p>";
            echo "<p><strong>This confirms the column size issue!</strong></p>";
        }
    }
    
    $conn->rollback(); // Always rollback test changes
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Database test error: " . htmlspecialchars($e->getMessage()) . "</p>";
    $conn->rollback();
}

// Show the exact SQL that would fix this
echo "<h3>4. DEFINITIVE SOLUTION</h3>";
echo "<div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
echo "<p><strong>If the tests above failed, run this SQL on your production database:</strong></p>";
echo "<pre style='background-color: #f8f9fa; padding: 10px; border-radius: 4px;'>";
echo "-- Check current status column\n";
echo "SHOW COLUMNS FROM prescribed_medications LIKE 'status';\n\n";
echo "-- Fix the column to allow longer values\n";
echo "ALTER TABLE prescribed_medications \n";
echo "MODIFY COLUMN status VARCHAR(20) DEFAULT 'not yet dispensed';\n\n";
echo "-- Also fix prescriptions table\n";
echo "ALTER TABLE prescriptions \n";
echo "MODIFY COLUMN status VARCHAR(20) DEFAULT 'active';\n\n";
echo "-- Verify the fix\n";
echo "SHOW COLUMNS FROM prescribed_medications LIKE 'status';\n";
echo "SHOW COLUMNS FROM prescriptions LIKE 'status';";
echo "</pre>";
echo "</div>";

echo "<h3>5. CONFIDENCE LEVEL</h3>";
if (isset($jsonResponse) && $jsonResponse['success']) {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✓ HIGH CONFIDENCE - The fix is working locally</p>";
    echo "<p>Deploy the updated API file to production and it should work.</p>";
} else {
    echo "<p style='color: orange; font-size: 18px; font-weight: bold;'>⚠ MEDIUM CONFIDENCE - Database schema issue detected</p>";
    echo "<p>The API code fix is ready, but you MUST run the SQL schema fix on production database first.</p>";
}

?>

<style>
pre { background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; border: 1px solid #ddd; }
</style>