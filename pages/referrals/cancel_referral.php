<?php
// cancel_referral.php - API endpoint to cancel referrals with ownership-based access control
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Please login to continue.'
    ]);
    exit();
}

// Check if role is authorized for referral operations
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: You do not have permission to cancel referrals.'
    ]);
    exit();
}



// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit();
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please contact administrator.'
    ]);
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

try {
    // Get and validate input parameters
    $referral_id = $_POST['referral_id'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if (empty($referral_id) || !is_numeric($referral_id)) {
        throw new Exception('Invalid referral ID provided.');
    }

    if (empty($reason)) {
        throw new Exception('Cancellation reason is required.');
    }

    if (strlen($reason) < 10) {
        throw new Exception('Cancellation reason must be at least 10 characters long.');
    }

    if (empty($password)) {
        throw new Exception('Password is required for verification.');
    }

    // Verify employee password
    $stmt = $conn->prepare("SELECT employee_id, first_name, last_name, password FROM employees WHERE employee_id = ?");
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $employee_id);
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found.');
    }
    
    $employee = $result->fetch_assoc();
    $stmt->close();

    // Verify password
    if (!password_verify($password, $employee['password'])) {
        throw new Exception('Invalid password. Please try again.');
    }

    // Check if referral exists and get current details
    $stmt = $conn->prepare("
        SELECT r.*, p.first_name as patient_first_name, p.last_name as patient_last_name, 
               p.username as patient_number
        FROM referrals r 
        JOIN patients p ON r.patient_id = p.patient_id 
        WHERE r.referral_id = ?
    ");
    $stmt->bind_param('i', $referral_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Referral not found.');
    }
    
    $referral = $result->fetch_assoc();
    $stmt->close();

    // Check ownership: Users can only cancel their own referrals, admins can cancel any
    if (strtolower($_SESSION['role']) !== 'admin' && $referral['referred_by'] != $employee_id) {
        throw new Exception('Access denied: You can only cancel referrals that you issued.');
    }

    // Check if referral can be cancelled (only active referrals)
    if (strtolower($referral['status']) !== 'active') {
        throw new Exception('Only active referrals can be cancelled. Current status: ' . $referral['status']);
    }

    // Begin transaction
    $conn->begin_transaction();

    // Update referral status to cancelled
    $stmt = $conn->prepare("
        UPDATE referrals 
        SET status = 'cancelled', 
            updated_at = NOW() 
        WHERE referral_id = ?
    ");
    $stmt->bind_param('i', $referral_id);
    $stmt->execute();
    $stmt->close();

    // Insert cancellation log into referral_logs table
    $stmt = $conn->prepare("
        INSERT INTO referral_logs (
            referral_id, employee_id, action, reason, 
            previous_status, new_status, timestamp
        ) VALUES (?, ?, 'cancelled', ?, ?, 'cancelled', NOW())
    ");
    $stmt->bind_param('iiss', $referral_id, $employee_id, $reason, $referral['status']);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Referral has been successfully cancelled.',
        'data' => [
            'referral_id' => $referral_id,
            'new_status' => 'cancelled',
            'cancelled_by' => $employee['first_name'] . ' ' . $employee['last_name'],
            'cancelled_at' => date('Y-m-d H:i:s'),
            'reason' => $reason,
            'patient_name' => $referral['patient_first_name'] . ' ' . $referral['patient_last_name'],
            'referral_number' => $referral['referral_num']
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>