<?php
session_start();
require_once __DIR__ . '/../../../config/db.php';

// Only allow logged-in patients
$patient_id = isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
if (!$patient_id) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit();
}

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? '';
$password = $_POST['password'] ?? '';

$allowed_tables = [
    'allergies',
    'past_medical_conditions',
    'chronic_illnesses',
    'family_history',
    'surgical_history',
    'current_medications',
    'immunizations'
];

if (!in_array($table, $allowed_tables) || !$id || !$password) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request. All fields are required.']);
    exit();
}

try {
    // Verify patient password
    $stmt = $pdo->prepare("SELECT password_hash FROM patients WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Patient not found.']);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $patient['password_hash'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
        exit();
    }
    
    // Delete the record
    $sql = "DELETE FROM $table WHERE id = ? AND patient_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $patient_id]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Record not found or already deleted.']);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
