<?php
// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Server-side role enforcement
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['admin', 'laboratory_tech', 'doctor', 'nurse', 'patient'])) {
    http_response_code(403);
    exit('Not authorized');
}

$filename = $_GET['file'] ?? null;

if (!$filename) {
    http_response_code(400);
    exit('Filename is required');
}

// Validate filename to prevent directory traversal attacks
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    http_response_code(400);
    exit('Invalid filename');
}

// Ensure filename has .pdf extension
if (pathinfo($filename, PATHINFO_EXTENSION) !== 'pdf') {
    http_response_code(400);
    exit('Only PDF files are allowed');
}

$uploadsDir = $root_path . '/uploads/lab_results';
$filePath = $uploadsDir . '/' . $filename;

// Check if file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Additional authorization check - verify user has access to this file
if ($_SESSION['role'] === 'patient') {
    // Patients can only download their own results
    $patient_id = $_SESSION['patient_id'] ?? null;
    if (!$patient_id) {
        http_response_code(403);
        exit('Patient ID not found in session');
    }
    
    // Check if this file belongs to the patient
    $authSql = "SELECT COUNT(*) as count 
                FROM lab_order_items loi
                LEFT JOIN lab_orders lo ON loi.lab_order_id = lo.lab_order_id
                WHERE loi.result_file = ? AND lo.patient_id = ?";
    
    $authStmt = $conn->prepare($authSql);
    $authStmt->bind_param("si", $filename, $patient_id);
    $authStmt->execute();
    $authResult = $authStmt->get_result();
    $authData = $authResult->fetch_assoc();
    
    if ($authData['count'] == 0) {
        http_response_code(403);
        exit('Access denied to this file');
    }
} else {
    // For healthcare staff, verify the file exists in the database
    $authSql = "SELECT COUNT(*) as count FROM lab_order_items WHERE result_file = ?";
    $authStmt = $conn->prepare($authSql);
    $authStmt->bind_param("s", $filename);
    $authStmt->execute();
    $authResult = $authStmt->get_result();
    $authData = $authResult->fetch_assoc();
    
    if ($authData['count'] == 0) {
        http_response_code(404);
        exit('File not found in database');
    }
}

// Get file info
$fileSize = filesize($filePath);
$displayName = 'lab_result_' . date('Y-m-d') . '.pdf';

// Set appropriate headers for PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $displayName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, must-revalidate');
header('Pragma: private');
header('Expires: 0');

// Prevent any output before file content
ob_clean();
flush();

// Output file content
readfile($filePath);
exit();