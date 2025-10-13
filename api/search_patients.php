<?php
// Search Patients API
session_start();

// Root path for includes
$root_path = dirname(__DIR__);

// Check if user is logged in as employee
require_once $root_path . '/config/session/employee_session.php';

if (!is_employee_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Check if user has cashier or admin role
$employee_role = get_employee_session('role_name');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Cashier or admin role required.']);
    exit();
}

// Include database connection
require_once $root_path . '/config/db.php';

// Set content type
header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['query']) || empty(trim($input['query']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit();
}

$query = trim($input['query']);

// Validate query length
if (strlen($query) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Search query must be at least 2 characters']);
    exit();
}

try {
    // Search patients by name, phone, or ID
    $search_sql = "
        SELECT 
            id,
            first_name,
            last_name,
            middle_name,
            contact_number,
            email,
            DATE(created_at) as registration_date
        FROM patients 
        WHERE 
            (CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) LIKE :name_query)
            OR (contact_number LIKE :phone_query)
            OR (id = :id_query)
            OR (first_name LIKE :first_name_query)
            OR (last_name LIKE :last_name_query)
        ORDER BY 
            CASE 
                WHEN id = :exact_id THEN 1
                WHEN CONCAT(first_name, ' ', last_name) LIKE :exact_name THEN 2
                WHEN first_name LIKE :starts_first_name THEN 3
                WHEN last_name LIKE :starts_last_name THEN 4
                ELSE 5
            END,
            last_name, first_name
        LIMIT 20
    ";

    $stmt = $pdo->prepare($search_sql);
    
    // Prepare search parameters
    $name_pattern = "%{$query}%";
    $phone_pattern = "%{$query}%";
    $first_name_pattern = "%{$query}%";
    $last_name_pattern = "%{$query}%";
    $exact_name_pattern = "{$query}%";
    $starts_first_pattern = "{$query}%";
    $starts_last_pattern = "{$query}%";
    
    // Handle numeric ID search
    $id_value = is_numeric($query) ? intval($query) : 0;
    
    $stmt->bindParam(':name_query', $name_pattern);
    $stmt->bindParam(':phone_query', $phone_pattern);
    $stmt->bindParam(':id_query', $id_value, PDO::PARAM_INT);
    $stmt->bindParam(':first_name_query', $first_name_pattern);
    $stmt->bindParam(':last_name_query', $last_name_pattern);
    $stmt->bindParam(':exact_id', $id_value, PDO::PARAM_INT);
    $stmt->bindParam(':exact_name', $exact_name_pattern);
    $stmt->bindParam(':starts_first_name', $starts_first_pattern);
    $stmt->bindParam(':starts_last_name', $starts_last_pattern);
    
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format patient data
    $formatted_patients = array_map(function($patient) {
        return [
            'id' => $patient['id'],
            'first_name' => $patient['first_name'],
            'last_name' => $patient['last_name'],
            'middle_name' => $patient['middle_name'],
            'full_name' => trim($patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name']),
            'contact_number' => $patient['contact_number'],
            'email' => $patient['email'],
            'registration_date' => $patient['registration_date']
        ];
    }, $patients);
    
    // Log search activity - using existing audit system
    $employee_id = get_employee_session('employee_id');
    try {
        $log_sql = "
            INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, created_at) 
            VALUES (?, ?, 'update', ?, NOW())
        ";
        $log_stmt = $pdo->prepare($log_sql);
        $log_details = json_encode([
            'action' => 'patient_search',
            'query' => $query,
            'results_count' => count($formatted_patients),
            'searched_by' => get_employee_session('first_name') . ' ' . get_employee_session('last_name')
        ]);
        $log_stmt->execute([$employee_id, $employee_id, "Patient Search: {$query} ({" . count($formatted_patients) . " results)"]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Activity log error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'patients' => $formatted_patients,
        'total_results' => count($formatted_patients),
        'query' => $query
    ]);
    
} catch (PDOException $e) {
    error_log("Patient Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while searching patients'
    ]);
} catch (Exception $e) {
    error_log("Patient Search Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred'
    ]);
}
?>