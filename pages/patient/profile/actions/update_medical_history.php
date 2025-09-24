<?php
// update_medical_history.php

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

// Helper: sanitize input
function get_post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Debug: Log received data
error_log("Update request - Received POST data: " . print_r($_POST, true));

$table = get_post('table');
$id = get_post('id');

if (!$id) {
    error_log("Update failed: Missing ID");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing record ID']);
    exit();
}

// Only allow updates for known tables
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
    error_log("Update failed: Invalid table - " . $table);
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

// Build update logic for each table
try {
    switch ($table) {
        case 'allergies':
            $allergen = get_post('allergen_dropdown') === 'Others' ? get_post('allergen_other') : get_post('allergen_dropdown');
            $reaction = get_post('reaction_dropdown') === 'Others' ? get_post('reaction_other') : get_post('reaction_dropdown');
            $severity = get_post('severity');
            if (!$allergen || !$reaction || !$severity) throw new Exception('Missing fields');
            $sql = "UPDATE allergies SET allergen=?, reaction=?, severity=? WHERE id=? AND patient_id=?";
            $params = [$allergen, $reaction, $severity, $id, $patient_id];
            break;
        case 'past_medical_conditions':
            $condition = get_post('condition_dropdown') === 'Others' ? get_post('condition_other') : get_post('condition_dropdown');
            $year = get_post('year_diagnosed');
            $status = get_post('status');
            if (!$condition || !$year || !$status) throw new Exception('Missing fields');
            $sql = "UPDATE past_medical_conditions SET `condition`=?, year_diagnosed=?, status=? WHERE id=? AND patient_id=?";
            $params = [$condition, $year, $status, $id, $patient_id];
            break;
        case 'chronic_illnesses':
            $illness = get_post('illness_dropdown') === 'Others' ? get_post('illness_other') : get_post('illness_dropdown');
            $year = get_post('year_diagnosed');
            $management = get_post('management');
            if (!$illness || !$year || !$management) throw new Exception('Missing fields');
            $sql = "UPDATE chronic_illnesses SET illness=?, year_diagnosed=?, management=? WHERE id=? AND patient_id=?";
            $params = [$illness, $year, $management, $id, $patient_id];
            break;
        case 'family_history':
            $member = get_post('family_member_dropdown') === 'Others' ? get_post('family_member_other') : get_post('family_member_dropdown');
            $condition = get_post('condition_dropdown') === 'Others' ? get_post('condition_other') : get_post('condition_dropdown');
            $age = get_post('age_diagnosed');
            $status = get_post('current_status');
            if (!$member || !$condition || !$age || !$status) throw new Exception('Missing fields');
            $sql = "UPDATE family_history SET family_member=?, `condition`=?, age_diagnosed=?, current_status=? WHERE id=? AND patient_id=?";
            $params = [$member, $condition, $age, $status, $id, $patient_id];
            break;
        case 'surgical_history':
            $surgery = get_post('surgery_dropdown') === 'Others' ? get_post('surgery_other') : get_post('surgery_dropdown');
            $year = get_post('year');
            $hospital = get_post('hospital_dropdown') === 'Others' ? get_post('hospital_other') : get_post('hospital_dropdown');
            if (!$surgery || !$year || !$hospital) throw new Exception('Missing fields');
            $sql = "UPDATE surgical_history SET surgery=?, year=?, hospital=? WHERE id=? AND patient_id=?";
            $params = [$surgery, $year, $hospital, $id, $patient_id];
            break;
        case 'current_medications':
            $med = get_post('medication_dropdown') === 'Others' ? get_post('medication_other') : get_post('medication_dropdown');
            $dosage = get_post('dosage');
            $freq = get_post('frequency_dropdown') === 'Others' ? get_post('frequency_other') : get_post('frequency_dropdown');
            $prescribed = get_post('prescribed_by_dropdown') === 'Others' ? get_post('prescribed_by_other') : get_post('prescribed_by_dropdown');
            if (!$med || !$dosage || !$freq) throw new Exception('Missing fields');
            $sql = "UPDATE current_medications SET medication=?, dosage=?, frequency=?, prescribed_by=? WHERE id=? AND patient_id=?";
            $params = [$med, $dosage, $freq, $prescribed, $id, $patient_id];
            break;
        case 'immunizations':
            $vaccine = get_post('vaccine_dropdown') === 'Others' ? get_post('vaccine_other') : get_post('vaccine_dropdown');
            $year_received = get_post('year_received');
            $doses_completed = get_post('doses_completed');
            $status = get_post('status');
            if (!$vaccine || !$year_received || $doses_completed === '' || !$status) throw new Exception('Missing fields');
            $sql = "UPDATE immunizations SET vaccine=?, year_received=?, doses_completed=?, status=? WHERE id=? AND patient_id=?";
            $params = [$vaccine, $year_received, $doses_completed, $status, $id, $patient_id];
            break;
        default:
            throw new Exception('Invalid table');
    }
    
    error_log("Executing update SQL: " . $sql);
    error_log("Update parameters: " . print_r($params, true));
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);
    
    if (!$result) {
        error_log("SQL execution failed: " . print_r($stmt->errorInfo(), true));
        throw new Exception('Database execution failed');
    }
    
    if ($stmt->rowCount() === 0) {
        error_log("No rows affected - record may not exist or no changes made");
        throw new Exception('Record not found or no changes made');
    }
    
    echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
    exit();
} catch (Exception $e) {
    error_log("Exception in update_medical_history.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'table' => $table,
        'id' => $id,
        'debug_data' => $_POST
    ]);
    exit();
}
