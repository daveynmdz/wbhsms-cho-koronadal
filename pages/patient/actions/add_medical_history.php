<?php
// add_medical_history.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['patient_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}
$patient_id = $_SESSION['patient_id'];

function get_post($key)
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

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
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid table']);
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
            $surgery = get_post('surgery');
            $year = get_post('year');
            $hospital = get_post('hospital');
            if (!$surgery || !$year || !$hospital) throw new Exception('Missing fields');
            $sql = "INSERT INTO surgical_history (patient_id, surgery, year, hospital) VALUES (?, ?, ?, ?)";
            $params = [$patient_id, $surgery, $year, $hospital];
            break;
        case 'current_medications':
            $medication = get_post('medication_dropdown') === 'Others' ? get_post('medication_other') : get_post('medication_dropdown');
            $dosage = get_post('dosage');
            $frequency = get_post('frequency_dropdown') === 'Others' ? get_post('frequency_other') : get_post('frequency_dropdown');
            $prescribed_by = get_post('prescribed_by');
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
        'table' => $table,
        'fields' => $_POST
    ]);
    exit();
}
