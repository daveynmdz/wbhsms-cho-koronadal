<?php
// verify_employee_password.php - Verify employee password for sensitive actions
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Security check
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if role is authorized
$authorized_roles = ['Doctor', 'BHW', 'DHO', 'Records Officer', 'Admin'];
if (!in_array($_SESSION['role'], $authorized_roles)) {
    echo json_encode(['error' => 'Insufficient permissions']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['password']) || empty(trim($_POST['password']))) {
    echo json_encode(['error' => 'Password is required']);
    exit();
}

// Database connection
require_once '../../../config/db.php';

$employee_id = $_SESSION['employee_id'];
$password = trim($_POST['password']);

try {
    // Get employee details and verify password
    $stmt = $conn->prepare("SELECT first_name, last_name, password FROM employees WHERE employee_id = ?");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Employee not found']);
        exit();
    }
    
    $employee = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password (check both hashed and plain text for compatibility)
    $password_valid = false;
    
    if (password_verify($password, $employee['password'])) {
        // Password is hashed and verification successful
        $password_valid = true;
    } elseif ($password === $employee['password']) {
        // Fallback for plain text passwords (for backward compatibility)
        $password_valid = true;
    }
    
    if ($password_valid) {
        echo json_encode([
            'success' => true,
            'employee_name' => $employee['first_name'] . ' ' . $employee['last_name']
        ]);
    } else {
        echo json_encode(['error' => 'Invalid password']);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>