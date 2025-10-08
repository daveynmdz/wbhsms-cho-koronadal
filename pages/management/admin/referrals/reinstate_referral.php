<?php
// reinstate_referral.php - API endpoint to reinstate cancelled/expired referrals with logging
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
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

// Check if role is authorized for reinstating referrals
$authorized_roles = ['doctor', 'bhw', 'dho', 'records_officer', 'admin'];
if (!in_array(strtolower($_SESSION['role']), $authorized_roles)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Access denied: You do not have permission to reinstate referrals.'
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

    // Validate required fields
    if (empty($referral_id) || !is_numeric($referral_id)) {
        throw new Exception('Invalid referral ID provided.');
    }

    // Get employee information for logging
    $stmt = $conn->prepare("SELECT employee_id, first_name, last_name FROM employees WHERE employee_id = ?");
    $stmt->bind_param('i', $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Employee not found.');
    }
    
    $employee = $result->fetch_assoc();
    $stmt->close();

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

    // Check if referral can be reinstated (only cancelled or expired referrals)
    $allowedStatuses = ['cancelled', 'expired'];
    if (!in_array(strtolower($referral['status']), $allowedStatuses)) {
        throw new Exception('Referral cannot be reinstated. Only cancelled or expired referrals can be reinstated. Current status: ' . $referral['status']);
    }

    // Begin transaction
    $conn->begin_transaction();

    // Update referral status to active
    $stmt = $conn->prepare("
        UPDATE referrals 
        SET status = 'active', 
            updated_at = NOW() 
        WHERE referral_id = ?
    ");
    $stmt->bind_param('i', $referral_id);
    $stmt->execute();
    $stmt->close();

    // Ensure referral_logs table exists (create if doesn't exist)
    $table_check = $conn->query("SHOW TABLES LIKE 'referral_logs'");
    if ($table_check->num_rows === 0) {
        // Create referral_logs table
        $create_table_sql = "
            CREATE TABLE referral_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                referral_id INT NOT NULL,
                employee_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                reason TEXT,
                previous_status VARCHAR(50),
                new_status VARCHAR(50),
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_referral_id (referral_id),
                INDEX idx_employee_id (employee_id),
                FOREIGN KEY (referral_id) REFERENCES referrals(referral_id) ON DELETE CASCADE,
                FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $conn->query($create_table_sql);
    }

    // Insert reinstatement log
    $reinstate_reason = "Referral reinstated by " . $employee['first_name'] . " " . $employee['last_name'] . " (" . $employee_role . ") - Status changed from " . $referral['status'] . " to active";
    
    $stmt = $conn->prepare("
        INSERT INTO referral_logs (
            referral_id, employee_id, action, reason, 
            previous_status, new_status, timestamp
        ) VALUES (?, ?, 'reinstated', ?, ?, 'active', NOW())
    ");
    $stmt->bind_param('iiss', $referral_id, $employee_id, $reinstate_reason, $referral['status']);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Log successful reinstatement for audit trail
    error_log("Referral reinstated - ID: {$referral_id}, Employee: {$employee['first_name']} {$employee['last_name']} (ID: {$employee_id}), Previous Status: {$referral['status']}");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Referral has been successfully reinstated and is now active.',
        'data' => [
            'referral_id' => $referral_id,
            'previous_status' => $referral['status'],
            'new_status' => 'active',
            'reinstated_by' => $employee['first_name'] . ' ' . $employee['last_name'],
            'reinstated_at' => date('Y-m-d H:i:s'),
            'patient_name' => $referral['patient_first_name'] . ' ' . $referral['patient_last_name'],
            'referral_number' => $referral['referral_num']
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Log error for debugging
    error_log("Referral reinstatement error - Employee ID: {$employee_id}, Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'REINSTATEMENT_FAILED'
    ]);
} catch (Error $e) {
    // Handle PHP errors
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    error_log("Referral reinstatement PHP error - Employee ID: {$employee_id}, Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again.',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>