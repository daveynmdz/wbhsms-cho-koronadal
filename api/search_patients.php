<?php
// Search Patients API for Prescription Management

// Root path for includes
$root_path = dirname(__DIR__);

// Include session configuration - this will start session if needed
require_once $root_path . '/config/session/employee_session.php';

// Include database connection
require_once $root_path . '/config/db.php';

// Set content type
header('Content-Type: application/json');

if (!is_employee_logged_in()) {
    // Debug information - remove in production
    error_log("Search API Auth Debug - Session ID: " . session_id());
    error_log("Search API Auth Debug - Employee ID: " . (isset($_SESSION['employee_id']) ? $_SESSION['employee_id'] : 'NOT SET'));
    error_log("Search API Auth Debug - Session data: " . print_r($_SESSION, true));
    
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'message' => 'Authentication required',
        'debug' => [
            'session_id' => session_id(),
            'employee_id_set' => isset($_SESSION['employee_id']),
            'session_started' => session_status() === PHP_SESSION_ACTIVE
        ]
    ]);
    exit();
}

// Check if user has prescription creation privileges
$employee_role = get_employee_session('role');
$authorized_roles = ['doctor', 'pharmacist', 'admin', 'cashier'];

// Debug logging - remove in production
error_log("Search API Role Debug - Employee Role: " . ($employee_role ?: 'NOT SET'));
error_log("Search API Role Debug - Authorized Roles: " . implode(', ', $authorized_roles));

if (!$employee_role || !in_array(strtolower($employee_role), array_map('strtolower', $authorized_roles))) {
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'message' => 'Access denied. Authorized roles only.',
        'debug' => [
            'current_role' => $employee_role,
            'authorized_roles' => $authorized_roles
        ]
    ]);
    exit();
}

// Include database connection
require_once $root_path . '/config/db.php';

// Set content type
header('Content-Type: application/json');

// Check request method - allow both GET and POST for flexibility
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get search parameters
$patient_id = $_GET['patient_id'] ?? $_POST['patient_id'] ?? '';
$first_name = $_GET['first_name'] ?? $_POST['first_name'] ?? '';
$last_name = $_GET['last_name'] ?? $_POST['last_name'] ?? '';
$barangay = $_GET['barangay'] ?? $_POST['barangay'] ?? '';

// Validate that at least one search parameter is provided
if (empty($patient_id) && empty($first_name) && empty($last_name) && empty($barangay)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least one search parameter is required']);
    exit();
}

try {
    $results = [];
    $search_conditions = [];
    $params = [];
    $param_types = "";
    
    // Build search query - ONLY return patients with visits (visit_id is required)
    // Use a simpler approach first to ensure it works
    $base_sql = "
        SELECT DISTINCT
            p.patient_id,
            p.first_name,
            p.last_name,
            p.middle_name,
            p.username as patient_code,
            b.barangay_name as barangay,
            v.visit_id,
            v.visit_date,
            'General Visit' as service_name
        FROM patients p
        INNER JOIN barangay b ON p.barangay_id = b.barangay_id
        INNER JOIN appointments a ON p.patient_id = a.patient_id
        INNER JOIN visits v ON a.appointment_id = v.appointment_id
        WHERE p.status = 'active' 
        AND v.visit_id IS NOT NULL
        AND v.visit_status IN ('ongoing', 'completed')
    ";
    
    // Add search conditions based on provided filters
    if (!empty($patient_id)) {
        $search_conditions[] = "(p.patient_id = ? OR p.username LIKE ?)";
        $params[] = (int)$patient_id;
        $params[] = "%{$patient_id}%";
        $param_types .= "is";
    }
    
    if (!empty($first_name)) {
        $search_conditions[] = "p.first_name LIKE ?";
        $params[] = "%{$first_name}%";
        $param_types .= "s";
    }
    
    if (!empty($last_name)) {
        $search_conditions[] = "p.last_name LIKE ?";
        $params[] = "%{$last_name}%";
        $param_types .= "s";
    }
    
    if (!empty($barangay)) {
        $search_conditions[] = "b.barangay_name LIKE ?";
        $params[] = "%{$barangay}%";
        $param_types .= "s";
    }
    
    // Combine conditions with AND logic (all must match)
    if (!empty($search_conditions)) {
        $base_sql .= " AND (" . implode(" AND ", $search_conditions) . ")";
    }
    
    $base_sql .= " ORDER BY v.visit_date DESC LIMIT 20";
    
    // Execute the search using MySQLi for compatibility
    if ($stmt = $conn->prepare($base_sql)) {
        if (!empty($params)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $full_name = trim($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name']);
            
            $results[] = [
                'patient_id' => $row['patient_id'],
                'full_name' => $full_name,
                'patient_code' => $row['patient_code'],
                'barangay' => $row['barangay'] ?: 'Not specified',
                'visit_id' => $row['visit_id'],
                'visit_date' => $row['visit_date'] ? date('M j, Y', strtotime($row['visit_date'])) : 'N/A',
                'service_name' => $row['service_name']
            ];
        }
        
        $stmt->close();
    } else {
        throw new Exception('Failed to prepare search query: ' . $conn->error);
    }
    
    // Log search activity
    $employee_id = get_employee_session('employee_id');
    $search_params = [];
    if (!empty($patient_id)) $search_params[] = "Patient ID: {$patient_id}";
    if (!empty($first_name)) $search_params[] = "First Name: {$first_name}";
    if (!empty($last_name)) $search_params[] = "Last Name: {$last_name}";
    if (!empty($barangay)) $search_params[] = "Barangay: {$barangay}";
    $search_description = "Prescription Patient Search (with visits) - " . implode(", ", $search_params) . " (" . count($results) . " results)";
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total_results' => count($results),
        'search_parameters' => [
            'patient_id' => $patient_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'barangay' => $barangay
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Prescription Patient Search Error: " . $e->getMessage());
    error_log("Search parameters: " . json_encode([
        'patient_id' => $patient_id,
        'first_name' => $first_name, 
        'last_name' => $last_name,
        'barangay' => $barangay
    ]));
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while searching: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?>