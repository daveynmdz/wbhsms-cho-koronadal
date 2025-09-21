<?php
// get_patient_vitals.php
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Check if role is authorized
$authorized_roles = ['Doctor', 'BHW', 'DHO', 'Records Officer', 'Admin'];
if (!in_array($_SESSION['role'], $authorized_roles)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized role']);
    exit();
}

require_once '../../../config/db.php';

$patient_id = $_GET['patient_id'] ?? '';
if (empty($patient_id) || !is_numeric($patient_id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit();
}

try {
    // Get latest vitals from various possible tables
    $vitals = null;
    
    // Try to get from vitals table first
    $stmt = $conn->prepare("
        SELECT height, weight, bp, cardiac_rate, temperature, resp_rate, 
               DATE_FORMAT(created_at, '%M %d, %Y %h:%i %p') as date
        FROM vitals 
        WHERE patient_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $vitals = $result->fetch_assoc();
    $stmt->close();
    
    // If no vitals found, try appointments table
    if (!$vitals) {
        $stmt = $conn->prepare("
            SELECT height, weight, bp, cardiac_rate, temperature, resp_rate,
                   DATE_FORMAT(date, '%M %d, %Y') as date
            FROM appointments 
            WHERE patient_id = ? 
            ORDER BY date DESC 
            LIMIT 1
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals = $result->fetch_assoc();
        $stmt->close();
    }
    
    if ($vitals) {
        echo json_encode([
            'success' => true, 
            'vitals' => $vitals
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'No vitals data found'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
