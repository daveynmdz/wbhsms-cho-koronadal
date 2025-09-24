<?php
// add_medical_history.php

// Set content type for JSON response FIRST
header('Content-Type: application/json');

// Enable error reporting but disable HTML display to avoid corrupting JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors in HTML
ini_set('log_errors', 1);      // Log errors instead

require_once __DIR__ . '/../../../../config/session/patient_session.php';
require_once __DIR__ . '/../../../../config/db.php';

if (!isset($_SESSION['patient_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - No patient session']);
    exit();
}
$patient_id = $_SESSION['patient_id'];

function get_post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Debug: Log received data
error_log("Received POST data: " . print_r($_POST, true));

$table = get_post('table');

$allowed_tables = [
    'allergies',
    'past_medical_conditions',
    'chronic_illnesses',
    'family_history',
    'surgical_history',
    'current_medications',
    'immunizations'
];

if (!in_array($table, $allowed_tables)) {
    error_log("Invalid table attempted: " . $table);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid table: ' . $table]);
    exit();
}

// Check if PDO connection exists
if (!isset($pdo)) {
    error_log("Database connection not found");
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

try {
    switch ($table) {
        case 'allergies':
            $allergen = get_post('allergen_dropdown') === 'Others' ? get_post('allergen_other') : get_post('allergen_dropdown');
            $reaction = get_post('reaction_dropdown') === 'Others' ? get_post('reaction_other') : get_post('reaction_dropdown');
            $severity = get_post('severity');
            if (!$allergen || !$reaction || !$severity) throw new Exception('Missing fields');
            $sql = "INSERT INTO allergies (patient_id, allergen, reaction, severity) VALUES (?, ?, ?, ?)";
            $params = [$patient_id, $allergen, $reaction, $severity];
            break;
        case 'past_medical_conditions':
            $condition = get_post('condition_dropdown') === 'Others' ? get_post('condition_other') : get_post('condition_dropdown');
            $year_diagnosed = get_post('year_diagnosed');
            $status = get_post('status');
            if (!$condition || !$year_diagnosed || !$status) throw new Exception('Missing fields');
            $sql = "INSERT INTO past_medical_conditions (patient_id, `condition`, year_diagnosed, status) VALUES (?, ?, ?, ?)";
            $params = [$patient_id, $condition, $year_diagnosed, $status];
            break;
        case 'chronic_illnesses':
            $illness = get_post('illness_dropdown') === 'Others' ? get_post('illness_other') : get_post('illness_dropdown');
            $year_diagnosed = get_post('year_diagnosed');
            $management = get_post('management');
            if (!$illness || !$year_diagnosed || !$management) throw new Exception('Missing fields');
            $sql = "INSERT INTO chronic_illnesses (patient_id, illness, year_diagnosed, management) VALUES (?, ?, ?, ?)";
            $params = [$patient_id, $illness, $year_diagnosed, $management];
            break;
        case 'family_history':
            $family_member = get_post('family_member_dropdown') === 'Others' ? get_post('family_member_other') : get_post('family_member_dropdown');
            $condition = get_post('condition_dropdown') === 'Others' ? get_post('condition_other') : get_post('condition_dropdown');
            $age_diagnosed = get_post('age_diagnosed');
            $current_status = get_post('current_status');
            if (!$family_member || !$condition || $age_diagnosed === '' || !$current_status) throw new Exception('Missing fields');
            $sql = "INSERT INTO family_history (patient_id, family_member, `condition`, age_diagnosed, current_status) VALUES (?, ?, ?, ?, ?)";
            $params = [$patient_id, $family_member, $condition, $age_diagnosed, $current_status];
            break;
        case 'surgical_history':
            $surgery = get_post('surgery_dropdown') === 'Others' ? get_post('surgery_other') : get_post('surgery_dropdown');
            $year = get_post('year');
            $hospital = get_post('hospital_dropdown') === 'Others' ? get_post('hospital_other') : get_post('hospital_dropdown');
            if (!$surgery || !$year || !$hospital) throw new Exception('Missing fields');
            $sql = "INSERT INTO surgical_history (patient_id, surgery, year, hospital) VALUES (?, ?, ?, ?)";
            $params = [$patient_id, $surgery, $year, $hospital];
            break;
        case 'current_medications':
            $medication = get_post('medication_dropdown') === 'Others' ? get_post('medication_other') : get_post('medication_dropdown');
            $dosage = get_post('dosage');
            $frequency = get_post('frequency_dropdown') === 'Others' ? get_post('frequency_other') : get_post('frequency_dropdown');
            $prescribed_by = get_post('prescribed_by_dropdown') === 'Others' ? get_post('prescribed_by_other') : get_post('prescribed_by_dropdown');
            if (!$medication || !$dosage || !$frequency) throw new Exception('Missing fields');
            $sql = "INSERT INTO current_medications (patient_id, medication, dosage, frequency, prescribed_by) VALUES (?, ?, ?, ?, ?)";
            $params = [$patient_id, $medication, $dosage, $frequency, $prescribed_by ?: null];
            break;
        case 'immunizations':
            $vaccine = get_post('vaccine_dropdown') === 'Others' ? get_post('vaccine_other') : get_post('vaccine_dropdown');
            $year_received = get_post('year_received');
            $doses_completed = get_post('doses_completed');
            $status = get_post('status');
            if (!$vaccine || !$year_received || $doses_completed === '' || !$status) throw new Exception('Missing fields');
            $sql = "INSERT INTO immunizations (patient_id, vaccine, year_received, doses_completed, status) VALUES (?, ?, ?, ?, ?)";
            $params = [$patient_id, $vaccine, $year_received, $doses_completed, $status];
            break;
        // Add other cases for other tables as needed
        default:
            throw new Exception('Invalid table');
    }
    
    error_log("Executing SQL: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        error_log("SQL execution failed: " . print_r($stmt->errorInfo(), true));
        throw new Exception('Database execution failed');
    }
    
    echo json_encode(['success' => true, 'message' => 'Record added successfully']);
    exit();
} catch (Exception $e) {
    error_log("Exception in add_medical_history.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'table' => $table,
        'debug_data' => $_POST
    ]);
    exit();
}
