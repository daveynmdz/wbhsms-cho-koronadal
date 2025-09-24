<?php
// Set content type for JSON response FIRST
header('Content-Type: application/json');

// Disable HTML error display to avoid corrupting JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../../../config/session/patient_session.php';
require_once __DIR__ . '/../../../../config/db.php';

// Only allow logged-in patients
$patient_id = isset($_SESSION['patient_id']) ? $_SESSION['patient_id'] : null;
if (!$patient_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit();
}

$table = $_POST['table'] ?? '';
$id = $_POST['id'] ?? '';
$password = $_POST['password'] ?? '';
$na_removal = $_POST['na_removal'] ?? false;

$allowed_tables = [
    'allergies',
    'past_medical_conditions',
    'chronic_illnesses',
    'family_history',
    'surgical_history',
    'current_medications',
    'immunizations'
];

// Special handling for N/A removal
if ($na_removal && in_array($table, $allowed_tables)) {
    try {
        // Find and delete N/A records for this table and patient
        switch($table) {
            case 'allergies':
                $sql = "DELETE FROM allergies WHERE patient_id = ? AND LOWER(allergen) = 'not applicable'";
                break;
            case 'past_medical_conditions':
                $sql = "DELETE FROM past_medical_conditions WHERE patient_id = ? AND LOWER(`condition`) = 'not applicable'";
                break;
            case 'chronic_illnesses':
                $sql = "DELETE FROM chronic_illnesses WHERE patient_id = ? AND LOWER(illness) = 'not applicable'";
                break;
            case 'family_history':
                $sql = "DELETE FROM family_history WHERE patient_id = ? AND LOWER(`condition`) = 'not applicable'";
                break;
            case 'surgical_history':
                $sql = "DELETE FROM surgical_history WHERE patient_id = ? AND LOWER(surgery) = 'not applicable'";
                break;
            case 'current_medications':
                $sql = "DELETE FROM current_medications WHERE patient_id = ? AND LOWER(medication) = 'not applicable'";
                break;
            case 'immunizations':
                $sql = "DELETE FROM immunizations WHERE patient_id = ? AND LOWER(vaccine) = 'not applicable'";
                break;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patient_id]);
        
        echo json_encode(['success' => true, 'message' => 'N/A status removed successfully.']);
        exit();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit();
    }
}

if (!in_array($table, $allowed_tables) || !$id || !$password) {
    http_response_code(400);
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
        echo json_encode(['success' => false, 'error' => 'Patient not found.']);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $patient['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Incorrect password.']);
        exit();
    }
    
    // Delete the record
    $sql = "DELETE FROM $table WHERE id = ? AND patient_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $patient_id]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Record not found or already deleted.']);
        exit();
    }
    
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
