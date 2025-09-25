<?php
// get_referral_details.php - Fetch complete referral details for view modal
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

header('Content-Type: application/json');

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if role is authorized
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    echo json_encode(['error' => 'Insufficient permissions']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid referral ID']);
    exit();
}

$referral_id = intval($_GET['id']);

try {
    // Fetch complete referral details with patient and issuer information
    $sql = "
        SELECT r.*, 
               p.first_name, p.middle_name, p.last_name, p.username as patient_number, 
               b.barangay_name as barangay, p.date_of_birth, p.sex, p.contact_number,
               e.first_name as issuer_first_name, e.last_name as issuer_last_name,
               ro.role_name as issuer_position,
               f.name as referred_facility_name,
               s.name as service_name
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.patient_id
        LEFT JOIN barangay b ON p.barangay_id = b.barangay_id
        LEFT JOIN employees e ON r.referred_by = e.employee_id
        LEFT JOIN roles ro ON e.role_id = ro.role_id
        LEFT JOIN facilities f ON r.referred_to_facility_id = f.facility_id
        LEFT JOIN services s ON r.service_id = s.service_id
        WHERE r.referral_id = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $referral_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Referral not found']);
        exit();
    }
    
    $referral = $result->fetch_assoc();
    $stmt->close();

    // Fetch patient vitals if available
    $vitals_sql = "
        SELECT systolic_bp, diastolic_bp, 
               CONCAT(systolic_bp, '/', diastolic_bp) as blood_pressure,
               heart_rate, respiratory_rate, temperature, 
               weight, height, recorded_at, remarks
        FROM vitals 
        WHERE patient_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 5
    ";
    
    $vitals_stmt = $conn->prepare($vitals_sql);
    $vitals_stmt->bind_param("i", $referral['patient_id']);
    $vitals_stmt->execute();
    $vitals_result = $vitals_stmt->get_result();
    $vitals = $vitals_result->fetch_all(MYSQLI_ASSOC);
    $vitals_stmt->close();

    // Calculate patient age
    $age = '';
    if ($referral['date_of_birth']) {
        $dob = new DateTime($referral['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($dob)->y . ' years old';
    }

    $response = [
        'success' => true,
        'referral' => $referral,
        'vitals' => $vitals,
        'patient_age' => $age
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>