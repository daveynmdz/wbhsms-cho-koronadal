<?php
// Debug script for prescription medication update
$root_path = __DIR__; // Current directory is the root
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

header('Content-Type: application/json');

error_log("=== Medication Update Debug ===");
error_log("Session employee_id: " . ($_SESSION['employee_id'] ?? 'Not set'));
error_log("Session role_id: " . ($_SESSION['role_id'] ?? 'Not set'));
error_log("POST data: " . file_get_contents('php://input'));

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access', 'debug' => 'No employee_id in session']);
    exit();
}

// Check permissions
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [1, 9])) {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions', 'debug' => 'Role ID: ' . ($_SESSION['role_id'] ?? 'Not set')]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['prescription_id']) || !isset($input['medication_statuses'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data', 'debug' => $input]);
    exit();
}

// Test basic database connectivity
try {
    $testQuery = $conn->prepare("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Failed to prepare test query: " . $conn->error);
    }
    $testQuery->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Debug successful',
        'debug' => [
            'employee_id' => $_SESSION['employee_id'],
            'role_id' => $_SESSION['role_id'],
            'input' => $input,
            'db_connection' => 'OK'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'employee_id' => $_SESSION['employee_id'],
            'role_id' => $_SESSION['role_id'],
            'input' => $input
        ]
    ]);
}
?>