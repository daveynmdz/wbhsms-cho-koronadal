<?php
// get_patient_facilities.php - AJAX endpoint for getting patient's barangay and district facilities
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Include database connection
// Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/db.php';

// Check if patient_id is provided
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if (!$patient_id) {
    echo json_encode(['error' => 'Patient ID is required']);
    exit;
}

try {
    // Get patient's barangay information
    $stmt = $conn->prepare("
        SELECT p.barangay_id, b.barangay_name 
        FROM patients p 
        JOIN barangay b ON p.barangay_id = b.barangay_id 
        WHERE p.patient_id = ? AND p.status = 'active'
    ");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient_data = $result->fetch_assoc();
    
    if (!$patient_data) {
        echo json_encode(['error' => 'Patient not found or inactive']);
        exit;
    }
    
    $patient_barangay_id = $patient_data['barangay_id'];
    $patient_barangay_name = $patient_data['barangay_name'];
    
    // Find barangay health center for this patient's barangay
    $stmt = $conn->prepare("
        SELECT facility_id, name, type 
        FROM facilities 
        WHERE type = 'Barangay Health Center' 
        AND barangay_id = ? 
        AND status = 'active'
        LIMIT 1
    ");
    $stmt->bind_param('i', $patient_barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $barangay_facility = $result->fetch_assoc();
    
    // Find district based on patient's barangay
    $stmt = $conn->prepare("
        SELECT DISTINCT district 
        FROM facilities 
        WHERE barangay_id = ? 
        LIMIT 1
    ");
    $stmt->bind_param('i', $patient_barangay_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $district_data = $result->fetch_assoc();
    
    $district_office = null;
    if ($district_data) {
        $district = $district_data['district'];
        
        // Find district health office
        $stmt = $conn->prepare("
            SELECT facility_id, name, type, district 
            FROM facilities 
            WHERE type = 'District Health Office' 
            AND district = ? 
            AND status = 'active'
            LIMIT 1
        ");
        $stmt->bind_param('s', $district);
        $stmt->execute();
        $result = $stmt->get_result();
        $district_office = $result->fetch_assoc();
    }
    
    // Get city health office (main facility)
    $stmt = $conn->prepare("
        SELECT facility_id, name, type 
        FROM facilities 
        WHERE type = 'City Health Office' 
        AND is_main = 1 
        AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $city_office = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'patient' => [
            'barangay_id' => $patient_barangay_id,
            'barangay_name' => $patient_barangay_name
        ],
        'facilities' => [
            'barangay_center' => $barangay_facility,
            'district_office' => $district_office,
            'city_office' => $city_office
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>