<?php
// test_db.php - Quick database test for appointment system
header('Content-Type: application/json');

$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';

$tests = [];
$all_passed = true;

try {
    // Test 1: Database connection
    if (isset($conn) && !$conn->connect_error) {
        $tests['database_connection'] = 'PASS';
    } else {
        $tests['database_connection'] = 'FAIL - ' . ($conn->connect_error ?? 'Connection object not found');
        $all_passed = false;
    }

    // Test 2: Check appointments table
    $result = $conn->query("DESCRIBE appointments");
    if ($result && $result->num_rows > 0) {
        $tests['appointments_table'] = 'PASS';
    } else {
        $tests['appointments_table'] = 'FAIL - Table not found or inaccessible';
        $all_passed = false;
    }

    // Test 3: Check services table
    $result = $conn->query("SELECT COUNT(*) as count FROM services");
    if ($result) {
        $row = $result->fetch_assoc();
        $tests['services_table'] = 'PASS - ' . $row['count'] . ' services found';
    } else {
        $tests['services_table'] = 'FAIL - ' . $conn->error;
        $all_passed = false;
    }

    // Test 4: Check facilities table
    $result = $conn->query("SELECT COUNT(*) as count FROM facilities WHERE status = 'active'");
    if ($result) {
        $row = $result->fetch_assoc();
        $tests['facilities_table'] = 'PASS - ' . $row['count'] . ' active facilities found';
    } else {
        $tests['facilities_table'] = 'FAIL - ' . $conn->error;
        $all_passed = false;
    }

    // Test 5: Check patients table
    $result = $conn->query("SELECT COUNT(*) as count FROM patients");
    if ($result) {
        $row = $result->fetch_assoc();
        $tests['patients_table'] = 'PASS - ' . $row['count'] . ' patients found';
    } else {
        $tests['patients_table'] = 'FAIL - ' . $conn->error;
        $all_passed = false;
    }

    // Test 6: Test a sample appointment query
    $stmt = $conn->prepare("
        SELECT COUNT(*) as booking_count
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        WHERE a.scheduled_date = ? 
        AND s.name = ? 
        AND f.type = ?
        AND a.status = 'confirmed'
    ");
    
    if ($stmt) {
        $test_date = '2025-09-30';
        $test_service = 'Primary Care';
        $test_facility_type = 'Barangay Health Center';
        
        $stmt->bind_param("sss", $test_date, $test_service, $test_facility_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $row = $result->fetch_assoc();
            $tests['appointment_query'] = 'PASS - Query executed successfully';
        } else {
            $tests['appointment_query'] = 'FAIL - Query execution failed';
            $all_passed = false;
        }
        $stmt->close();
    } else {
        $tests['appointment_query'] = 'FAIL - Query preparation failed: ' . $conn->error;
        $all_passed = false;
    }

} catch (Exception $e) {
    $tests['exception'] = 'FAIL - Exception: ' . $e->getMessage();
    $all_passed = false;
}

// Return results
echo json_encode([
    'success' => $all_passed,
    'tests' => $tests,
    'timestamp' => date('Y-m-d H:i:s')
]);

if (isset($conn)) {
    $conn->close();
}
?>