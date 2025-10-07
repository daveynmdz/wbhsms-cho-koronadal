<?php
/**
 * QR Code Lookup API Endpoint
 * Handles QR code scan requests for patient check-in
 * 
 * WBHSMS - City Health Office Queueing System
 * Created: October 2025
 */

// Start session and set JSON headers
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Include required files
require_once '../../config/db.php';
require_once '../../utils/queue_management_service.php';

// ==========================================
// ACCESS CONTROL VALIDATION
// ==========================================

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Please log in to continue.'
    ]);
    exit;
}

// Define allowed roles for QR lookup
$allowed_roles = ['admin', 'records_officer', 'dho', 'bhw'];
$user_role = strtolower($_SESSION['role']);

if (!in_array($user_role, $allowed_roles)) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Insufficient permissions.'
    ]);
    exit;
}

// ==========================================
// REQUEST VALIDATION
// ==========================================

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST required.'
    ]);
    exit;
}

// Get QR code data
$qr_data = filter_input(INPUT_POST, 'qr_data', FILTER_SANITIZE_STRING);

if (empty($qr_data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing QR code data.'
    ]);
    exit;
}

// ==========================================
// QR CODE PARSING
// ==========================================

try {
    // Parse JSON payload from QR code
    $parsed_data = json_decode($qr_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid QR code format. Expected JSON payload.'
        ]);
        exit;
    }
    
    // Extract required fields
    $appointment_id = isset($parsed_data['appointment_id']) ? (int)$parsed_data['appointment_id'] : 0;
    $patient_id = isset($parsed_data['patient_id']) ? (int)$parsed_data['patient_id'] : 0;
    $referral_id = isset($parsed_data['referral_id']) ? (int)$parsed_data['referral_id'] : null;
    
    // Validate required fields
    if (!$appointment_id || !$patient_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid QR code. Missing appointment ID or patient ID.'
        ]);
        exit;
    }
    
    // ==========================================
    // DATABASE LOOKUP
    // ==========================================
    
    // Initialize queue management service
    $queueService = new QueueManagementService($conn);
    
    // Get comprehensive patient and appointment details
    $details = $queueService->getPatientCheckInDetails($patient_id, $appointment_id);
    
    if (!$details['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'No records found for the scanned QR code.'
        ]);
        exit;
    }
    
    $patient = $details['data']['patient'];
    $appointment = $details['data']['appointment'];
    
    // Validate appointment exists and matches
    if (!$appointment || $appointment['appointment_id'] != $appointment_id) {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment not found or QR code mismatch.'
        ]);
        exit;
    }
    
    // Check if appointment is eligible for check-in
    if (!in_array($appointment['status'], ['confirmed', 'pending'])) {
        $status_message = ucfirst(str_replace('_', ' ', $appointment['status']));
        echo json_encode([
            'success' => false,
            'message' => "Cannot check in. Appointment status: {$status_message}"
        ]);
        exit;
    }
    
    // Check appointment date (should be today or allow some flexibility)
    $scheduled_date = new DateTime($appointment['scheduled_date']);
    $today = new DateTime();
    $date_diff = $today->diff($scheduled_date)->days;
    
    if ($scheduled_date < $today && $date_diff > 1) {
        echo json_encode([
            'success' => false,
            'message' => 'This appointment is from a past date and cannot be checked in.'
        ]);
        exit;
    }
    
    if ($scheduled_date > $today && $date_diff > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This appointment is scheduled for a future date.'
        ]);
        exit;
    }
    
    // ==========================================
    // SUCCESS RESPONSE
    // ==========================================
    
    // Prepare comprehensive response data
    $response_data = [
        'success' => true,
        'message' => 'QR code scanned successfully',
        'data' => [
            'appointment' => [
                'appointment_id' => $appointment['appointment_id'],
                'scheduled_date' => $appointment['scheduled_date'],
                'scheduled_time' => $appointment['scheduled_time'],
                'service_name' => $appointment['service_name'] ?? 'General Consultation',
                'status' => $appointment['status'],
                'formatted_date' => date('M d, Y', strtotime($appointment['scheduled_date'])),
                'formatted_time' => date('g:i A', strtotime($appointment['scheduled_time']))
            ],
            'patient' => [
                'patient_id' => $patient['patient_id'],
                'first_name' => $patient['first_name'],
                'last_name' => $patient['last_name'],
                'full_name' => $patient['first_name'] . ' ' . $patient['last_name'],
                'age' => $patient['age'] ?? 'N/A',
                'sex' => $patient['sex'] ?? 'N/A',
                'contact_number' => $patient['contact_number'] ?? 'N/A',
                'barangay' => $patient['barangay'] ?? 'N/A',
                'philhealth_id' => $patient['philhealth_id'] ?? null,
                'isSenior' => (bool)$patient['isSenior'],
                'isPWD' => (bool)$patient['isPWD']
            ],
            'flags' => $details['data']['flags'] ?? [],
            'recent_visits' => $details['data']['recent_visits'] ?? [],
            'referral_id' => $referral_id,
            'scan_timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode($response_data);
    
} catch (Exception $e) {
    error_log("QR Lookup Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System error occurred during QR code processing.'
    ]);
}

?>