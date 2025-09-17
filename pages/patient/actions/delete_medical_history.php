    <?php
session_start();
require_once __DIR__ . '/../../config/db.php';

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

$allowed_tables = [
    'allergies',
    'past_medical_conditions',
    'chronic_illnesses',
    'family_history',
    'surgical_history',
    'current_medications',
    'immunizations'
];


if (!in_array($table, $allowed_tables) || !$id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit();
}

// Build SQL
$sql = "DELETE FROM $table WHERE id = ? AND patient_id = ?";
$stmt = $pdo->prepare($sql);
try {
    $stmt->execute([$id, $patient_id]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
