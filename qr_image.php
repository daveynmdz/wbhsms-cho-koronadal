<?php
/**
 * QR Code Image Endpoint
 * Serves QR code images directly from database BLOB storage
 * 
 * URL: /qr_image.php?appointment_id=XXX
 * Returns: PNG image with proper headers
 */

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

try {
    // Get appointment ID from query parameter
    $appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
    
    if ($appointment_id <= 0) {
        throw new Exception('Invalid appointment ID: ' . $_GET['appointment_id']);
    }
    
    error_log("QR Image Endpoint: Requested appointment_id = $appointment_id");
    
    // Connect to database
    require_once __DIR__ . '/config/db.php';
    
    // Fetch QR code BLOB data from database with debugging
    $stmt = $conn->prepare("SELECT qr_code_path, LENGTH(qr_code_path) as blob_size FROM appointments WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        throw new Exception("No appointment found with ID $appointment_id");
    }
    
    if (!$row['qr_code_path'] || $row['blob_size'] == 0) {
        error_log("QR Image Endpoint: Appointment $appointment_id has no QR BLOB data (size: " . ($row['blob_size'] ?? 0) . ")");
        throw new Exception("QR code not found for appointment $appointment_id");
    }
    
    error_log("QR Image Endpoint: Found QR BLOB data for appointment $appointment_id (size: " . $row['blob_size'] . " bytes)");
    
    $qr_binary_data = $row['qr_code_path'];
    
    // Validate that it's a PNG image
    if (substr($qr_binary_data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new Exception('Invalid PNG data');
    }
    
    // Set appropriate headers for PNG image
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($qr_binary_data));
    header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    
    // Output the binary image data
    echo $qr_binary_data;
    exit;
    
} catch (Exception $e) {
    // Log the error
    error_log("QR Image Endpoint Error: " . $e->getMessage());
    
    // Return error image or 404
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'QR code not found';
    exit;
}
?>