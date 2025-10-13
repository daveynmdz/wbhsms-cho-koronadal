<?php
// Include patient session configuration FIRST
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// Check if user is logged in
if (!isset($_SESSION['patient_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Set content type to JSON
header('Content-Type: application/json');

$patient_id = $_SESSION['patient_id'];
$prescription_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$prescription_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid prescription ID']);
    exit();
}

try {
    // Fetch prescription details
    $stmt = $conn->prepare("
        SELECT p.*,
               CONCAT(e.first_name, ' ', e.last_name) as doctor_name,
               a.appointment_date, a.appointment_time
        FROM prescriptions p
        LEFT JOIN employees e ON p.prescribed_by_employee_id = e.employee_id
        LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
        WHERE p.prescription_id = ? AND p.patient_id = ?
    ");
    $stmt->bind_param("ii", $prescription_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prescription = $result->fetch_assoc();
    $stmt->close();

    if (!$prescription) {
        echo json_encode(['success' => false, 'message' => 'Prescription not found']);
        exit();
    }

    // Fetch prescribed medications
    $stmt = $conn->prepare("
        SELECT *
        FROM prescribed_medications
        WHERE prescription_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("i", $prescription_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode([
        'success' => true,
        'prescription' => $prescription,
        'medications' => $medications
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>