<?php
// get_referral_details.php - Fetch complete referral details for view modal
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Security check
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if role is authorized
$authorized_roles = ['Doctor', 'BHW', 'DHO', 'Records Officer', 'Admin'];
if (!in_array($_SESSION['role'], $authorized_roles)) {
    echo json_encode(['error' => 'Insufficient permissions']);
    exit();
}

// Database connection
require_once '../../../config/db.php';

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
               p.barangay, p.dob, p.sex, p.contact_num,
               pi.street as address, pi.civil_status, pi.occupation,
               ec.first_name as emergency_contact_first_name, ec.last_name as emergency_contact_last_name,
               ec.contact_num as emergency_contact_number, ec.relation as emergency_contact_relation,
               e.first_name as issuer_first_name, e.last_name as issuer_last_name,
               e.role as issuer_position
        FROM referrals r
        LEFT JOIN patients p ON r.patient_id = p.id
        LEFT JOIN personal_information pi ON p.id = pi.patient_id
        LEFT JOIN emergency_contact ec ON p.id = ec.patient_id
        LEFT JOIN employees e ON r.issued_by = e.employee_id
        WHERE r.id = ?
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
        SELECT bp as blood_pressure, hr as heart_rate, rr as respiratory_rate, temp as temperature, 
               wt as weight, ht as height, recorded_at, date_recorded
        FROM vitals 
        WHERE patient_id = ? 
        ORDER BY date_recorded DESC 
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
    if ($referral['dob']) {
        $dob = new DateTime($referral['dob']);
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