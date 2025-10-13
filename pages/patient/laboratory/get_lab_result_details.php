<?php
// Prevent direct access
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if patient is logged in
if (!isset($_SESSION['patient_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get the root path
$root_path = dirname(dirname(dirname(__DIR__)));

// Include database connection
require_once $root_path . '/config/db.php';

header('Content-Type: application/json');

// Get result ID from request
$result_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$result_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid result ID']);
    exit;
}

$patient_id = $_SESSION['patient_id'];

try {
    // Fetch lab result details with security check
    $stmt = $pdo->prepare("
        SELECT 
            lo.lab_order_id,
            lo.test_type,
            lo.specimen_type,
            lo.test_description,
            lo.order_date,
            lo.result_date,
            lo.result,
            lo.status,
            lo.remarks,
            CONCAT(e.first_name, ' ', e.last_name) as doctor_name,
            c.consultation_date,
            a.appointment_date
        FROM lab_orders lo
        LEFT JOIN consultations c ON lo.consultation_id = c.consultation_id
        LEFT JOIN appointments a ON c.appointment_id = a.appointment_id
        LEFT JOIN employees e ON c.employee_id = e.employee_id
        WHERE lo.lab_order_id = ? 
        AND lo.patient_id = ?
        AND lo.status = 'completed'
    ");
    
    $stmt->execute([$result_id, $patient_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Lab result not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true, 
        'result' => $result
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_lab_result_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>