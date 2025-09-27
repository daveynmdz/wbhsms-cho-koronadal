<?php
// check_slot_availability.php - API endpoint for checking appointment slot availability
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include patient session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/patient_session.php';

// If user is not logged in, return error
if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$date = $input['date'] ?? '';
$service = $input['service'] ?? '';
$facility_type = $input['facility_type'] ?? '';

if (empty($date) || empty($service) || empty($facility_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit();
}

// Check if date is not in the past
$today = date('Y-m-d');
if ($date <= $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot book appointments for today or past dates']);
    exit();
}

try {
    // Get the count of appointments for each time slot on the given date and service
    // Note: We'll need to join with services table to get service name, and facilities table for facility type
    $stmt = $conn->prepare("
        SELECT scheduled_time, COUNT(*) as booking_count
        FROM appointments a
        LEFT JOIN services s ON a.service_id = s.service_id
        LEFT JOIN facilities f ON a.facility_id = f.facility_id
        WHERE scheduled_date = ? 
        AND s.name = ? 
        AND f.type LIKE CONCAT('%', ?, '%')
        AND status IN ('confirmed', 'pending')
        GROUP BY scheduled_time
    ");
    
    $stmt->bind_param("sss", $date, $service, $facility_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $availability = [];
    while ($row = $result->fetch_assoc()) {
        $availability[$row['scheduled_time']] = (int)$row['booking_count'];
    }
    
    $stmt->close();
    
    // Return availability data
    echo json_encode([
        'success' => true,
        'availability' => $availability,
        'max_per_slot' => 20
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking availability: ' . $e->getMessage()
    ]);
}

$conn->close();
?>