<?php
// Simple test to check database structure
$root_path = dirname(__DIR__);
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

header('Content-Type: application/json');

try {
    // Test basic patients table
    $test_sql = "SELECT patient_id, first_name, last_name, username, barangay FROM patients LIMIT 5";
    if ($stmt = $conn->prepare($test_sql)) {
        $stmt->execute();
        $result = $stmt->get_result();
        $patients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => 'Patients table test',
            'data' => $patients
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to prepare patients query: ' . $conn->error
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>