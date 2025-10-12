<?php
// Prevent direct access
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if patient is logged in
if (!isset($_SESSION['patient_id'])) {
    header('Location: ../auth/patient_login.php');
    exit();
}

// Get the root path
$root_path = dirname(dirname(dirname(__DIR__)));

// Include database connection
require_once $root_path . '/config/db.php';

$patient_id = $_SESSION['patient_id'];

try {
    // Fetch all lab orders and results for this patient
    $stmt = $pdo->prepare("
        SELECT 
            lo.lab_order_id,
            lo.test_type,
            lo.specimen_type,
            lo.order_date,
            lo.result_date,
            lo.status,
            lo.remarks,
            CONCAT(e.first_name, ' ', e.last_name) as doctor_name,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM lab_orders lo
        LEFT JOIN consultations c ON lo.consultation_id = c.consultation_id
        LEFT JOIN employees e ON c.employee_id = e.employee_id
        LEFT JOIN patients p ON lo.patient_id = p.patient_id
        WHERE lo.patient_id = ?
        ORDER BY lo.order_date DESC
    ");
    
    $stmt->execute([$patient_id]);
    $lab_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lab_history)) {
        die('No lab history found');
    }
    
    // Get patient info
    $patient_stmt = $pdo->prepare("
        SELECT first_name, last_name, date_of_birth, gender, phone_number 
        FROM patients 
        WHERE patient_id = ?
    ");
    $patient_stmt->execute([$patient_id]);
    $patient_info = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error in download_lab_history.php: " . $e->getMessage());
    die('Database error occurred');
}

// Set headers for CSV download
$filename = 'lab_history_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fputs($output, "\xEF\xBB\xBF");

// CSV Headers
fputcsv($output, [
    'Patient Name',
    'Date of Birth',
    'Gender',
    'Phone Number',
    'Generated Date'
]);

// Patient info row
fputcsv($output, [
    $patient_info['first_name'] . ' ' . $patient_info['last_name'],
    date('F j, Y', strtotime($patient_info['date_of_birth'])),
    ucfirst($patient_info['gender']),
    $patient_info['phone_number'] ?: 'N/A',
    date('F j, Y g:i A')
]);

// Empty row
fputcsv($output, []);

// Lab history headers
fputcsv($output, [
    'Order ID',
    'Test Type',
    'Specimen Type',
    'Order Date',
    'Result Date',
    'Status',
    'Ordered By',
    'Remarks'
]);

// Lab history data
foreach ($lab_history as $record) {
    fputcsv($output, [
        $record['lab_order_id'],
        $record['test_type'],
        $record['specimen_type'] ?: 'N/A',
        date('F j, Y', strtotime($record['order_date'])),
        $record['result_date'] ? date('F j, Y', strtotime($record['result_date'])) : 'Pending',
        ucfirst(str_replace('_', ' ', $record['status'])),
        $record['doctor_name'] ? 'Dr. ' . $record['doctor_name'] : 'Lab Direct',
        $record['remarks'] ?: 'N/A'
    ]);
}

fclose($output);
exit;
?>