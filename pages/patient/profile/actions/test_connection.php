<?php
// test_connection.php - Simple test to debug JSON response issues

// Set content type for JSON response FIRST
header('Content-Type: application/json');

// Disable HTML error display to avoid corrupting JSON
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Test basic response
    echo json_encode([
        'success' => true, 
        'message' => 'Connection test successful',
        'timestamp' => date('Y-m-d H:i:s'),
        'post_data' => $_POST
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Connection test failed: ' . $e->getMessage()
    ]);
}
?>