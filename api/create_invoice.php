<?php
// Create Invoice API
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

// Validate required fields
$required_fields = ['patient_id', 'invoice_date', 'due_date', 'services'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Field '{$field}' is required"]);
        exit();
    }
}

// Validate patient ID
if (!is_numeric($input['patient_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit();
}

$patient_id = intval($input['patient_id']);
$invoice_date = $input['invoice_date'];
$due_date = $input['due_date'];
$services = $input['services'];
$notes = $input['notes'] ?? '';

// Validate dates
if (!DateTime::createFromFormat('Y-m-d', $invoice_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid invoice date format']);
    exit();
}

if (!DateTime::createFromFormat('Y-m-d', $due_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid due date format']);
    exit();
}

// Validate due date is not before invoice date
if (strtotime($due_date) < strtotime($invoice_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Due date cannot be before invoice date']);
    exit();
}

// Validate services array
if (!is_array($services) || empty($services)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'At least one service is required']);
    exit();
}

// Validate each service item
$valid_service_types = ['consultation', 'laboratory', 'pharmacy', 'dental', 'prenatal', 'immunization', 'emergency', 'family_planning', 'nutrition', 'other'];
$total_amount = 0;

foreach ($services as $index => $service) {
    $required_service_fields = ['service_type', 'description', 'quantity', 'unit_amount'];
    
    foreach ($required_service_fields as $field) {
        if (!isset($service[$field]) || $service[$field] === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Service item " . ($index + 1) . " is missing '{$field}'"]);
            exit();
        }
    }
    
    if (!in_array($service['service_type'], $valid_service_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid service type for item " . ($index + 1)]);
        exit();
    }
    
    if (!is_numeric($service['quantity']) || floatval($service['quantity']) <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid quantity for service item " . ($index + 1)]);
        exit();
    }
    
    if (!is_numeric($service['unit_amount']) || floatval($service['unit_amount']) < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Invalid unit amount for service item " . ($index + 1)]);
        exit();
    }
    
    $total_amount += floatval($service['quantity']) * floatval($service['unit_amount']);
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Verify patient exists
    $patient_check_sql = "SELECT id, first_name, last_name FROM patients WHERE id = ?";
    $patient_stmt = $pdo->prepare($patient_check_sql);
    $patient_stmt->execute([$patient_id]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Insert billing record (using existing structure)
    $employee_id = get_employee_session('employee_id');
    
    $billing_sql = "
        INSERT INTO billing (
            patient_id, 
            billing_date, 
            total_amount, 
            paid_amount, 
            payment_status, 
            notes, 
            created_at
        ) VALUES (?, ?, ?, 0, 'unpaid', ?, NOW())
    ";
    
    $billing_stmt = $pdo->prepare($billing_sql);
    $billing_stmt->execute([
        $patient_id,
        $invoice_date,
        $total_amount,
        $notes
    ]);
    
    $billing_id = $pdo->lastInsertId();
    
    // Generate invoice number based on billing_id
    $year = date('Y', strtotime($invoice_date));
    $month = date('m', strtotime($invoice_date));
    $invoice_number = sprintf("INV-%s%s-%04d", $year, $month, $billing_id);
    
    // Create service items in service_items table if they don't exist and insert billing_items
    $item_sql = "
        INSERT INTO billing_items (
            billing_id, 
            service_item_id, 
            item_price, 
            quantity, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?)
    ";
    
    $item_stmt = $pdo->prepare($item_sql);
    
    // Check/create service items and insert billing items
    $service_check_sql = "SELECT item_id FROM service_items WHERE item_name = ? LIMIT 1";
    $service_insert_sql = "INSERT INTO service_items (service_id, item_name, price_php, unit, is_active) VALUES (?, ?, ?, 'per service', 1)";
    
    // Map service types to service IDs
    $service_type_mapping = [
        'consultation' => 1, // Primary Care
        'laboratory' => 8,   // Laboratory Test
        'pharmacy' => 1,     // Primary Care (medication)
        'dental' => 2,       // Dental Services
        'prenatal' => 1,     // Primary Care
        'immunization' => 4, // Vaccination Services
        'emergency' => 5,    // HEMS
        'family_planning' => 6, // Family Planning Services
        'nutrition' => 1,    // Primary Care
        'other' => 1         // Primary Care (default)
    ];
    
    foreach ($services as $service) {
        $quantity = floatval($service['quantity']);
        $unit_amount = floatval($service['unit_amount']);
        $item_total = $quantity * $unit_amount;
        $service_type = $service['service_type'] ?? 'other';
        $service_id = $service_type_mapping[$service_type] ?? 1;
        
        // Check if service item exists
        $check_stmt = $pdo->prepare($service_check_sql);
        $check_stmt->execute([trim($service['description'])]);
        $service_item_id = $check_stmt->fetchColumn();
        
        if (!$service_item_id) {
            // Create new service item
            $insert_stmt = $pdo->prepare($service_insert_sql);
            $insert_stmt->execute([
                $service_id,
                trim($service['description']),
                $unit_amount
            ]);
            $service_item_id = $pdo->lastInsertId();
        }
        
        // Insert billing item
        $item_stmt->execute([
            $billing_id,
            $service_item_id,
            $unit_amount,
            $quantity,
            $item_total
        ]);
    }
    
    // Log invoice creation - using existing audit system
    try {
        $log_sql = "
            INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, created_at) 
            VALUES (?, ?, 'create', ?, NOW())
        ";
        $log_stmt = $pdo->prepare($log_sql);
        $log_description = "Created invoice {$invoice_number} for patient: " . $patient['first_name'] . ' ' . $patient['last_name'] . " (Amount: ₱" . number_format($total_amount, 2) . ")";
        $log_stmt->execute([$employee_id, $employee_id, $log_description]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Activity log error: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'invoice' => [
            'id' => $billing_id,
            'invoice_number' => $invoice_number,
            'patient_id' => $patient_id,
            'patient_name' => $patient['first_name'] . ' ' . $patient['last_name'],
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'total_amount' => $total_amount,
            'formatted_total' => number_format($total_amount, 2),
            'status' => 'unpaid',
            'service_count' => count($services)
        ]
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Create Invoice Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while creating invoice'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Create Invoice Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred'
    ]);
}
?>