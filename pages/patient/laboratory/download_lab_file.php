<?php
// Prevent direct access
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if patient is logged in
if (!isset($_SESSION['patient_id'])) {
    http_response_code(401);
    die('Unauthorized access');
}

// Get the root path
$root_path = dirname(dirname(dirname(__DIR__)));

// Include database connection
require_once $root_path . '/config/db.php';

// Get file path and result ID from request
$file_path = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);
$result_id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

if (!$file_path || !$result_id) {
    http_response_code(400);
    die('Invalid parameters');
}

$patient_id = $_SESSION['patient_id'];

try {
    // Verify that this file belongs to this patient's lab result
    $stmt = $pdo->prepare("
        SELECT lo.result, lo.test_type 
        FROM lab_orders lo
        WHERE lo.lab_order_id = ? 
        AND lo.patient_id = ?
        AND lo.status = 'completed'
        AND lo.result = ?
    ");
    
    $stmt->execute([$result_id, $patient_id, $file_path]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        http_response_code(404);
        die('File not found or access denied');
    }
    
    // Construct full file path
    $full_file_path = $root_path . '/' . ltrim($file_path, '/');
    
    // Check if file exists
    if (!file_exists($full_file_path)) {
        http_response_code(404);
        die('File not found on server');
    }
    
    // Get file info
    $file_info = pathinfo($full_file_path);
    $file_size = filesize($full_file_path);
    $file_extension = strtolower($file_info['extension']);
    
    // Set appropriate headers based on file type
    switch ($file_extension) {
        case 'pdf':
            $content_type = 'application/pdf';
            break;
        case 'jpg':
        case 'jpeg':
            $content_type = 'image/jpeg';
            break;
        case 'png':
            $content_type = 'image/png';
            break;
        case 'gif':
            $content_type = 'image/gif';
            break;
        case 'doc':
            $content_type = 'application/msword';
            break;
        case 'docx':
            $content_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
        default:
            $content_type = 'application/octet-stream';
    }
    
    // Generate download filename
    $download_filename = 'lab_result_' . $result['test_type'] . '_' . date('Y-m-d') . '.' . $file_extension;
    $download_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $download_filename);
    
    // Set download headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $download_filename . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read and output file
    $handle = fopen($full_file_path, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    } else {
        http_response_code(500);
        die('Error reading file');
    }
    
} catch (PDOException $e) {
    error_log("Database error in download_lab_file.php: " . $e->getMessage());
    http_response_code(500);
    die('Database error occurred');
} catch (Exception $e) {
    error_log("Error in download_lab_file.php: " . $e->getMessage());
    http_response_code(500);
    die('An error occurred');
}

exit;
?>