<?php
// Debug script to see what's being posted to the prescription creation
error_log("=== Prescription POST Debug ===");
error_log("POST data: " . json_encode($_POST));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));

// Return the debug info as JSON
header('Content-Type: application/json');
echo json_encode([
    'debug' => true,
    'post_data' => $_POST,
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
]);
?>