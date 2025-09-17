<?php
// update_medical_history.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['patient_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}
$patient_id = $_SESSION['patient_id'];

// Helper: sanitize input
function get_post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

$table = get_post('table');
$id = get_post('id');

// Only allow updates for known tables
// Add immunizations to allowed tables
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
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid table']);
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
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit();
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'table' => $table,
        'fields' => $_POST
    ]);
    exit();
}
