<?php
// get_facility_services.php - AJAX endpoint for fetching services available at a specific facility
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Include database connection
// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';

// Check if facility_id is provided
$facility_id = isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : 0;

if (!$facility_id) {
    echo json_encode(['error' => 'Facility ID is required', 'services' => []]);
    exit;
}

try {
    // Get services available at the specified facility
    $stmt = $conn->prepare("
        SELECT s.service_id, s.name, s.description 
        FROM services s
        INNER JOIN facility_services fs ON s.service_id = fs.service_id
        WHERE fs.facility_id = ?
        ORDER BY s.name
    ");
    $stmt->bind_param('i', $facility_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $services = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'facility_id' => $facility_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'services' => []
    ]);
}
?>
