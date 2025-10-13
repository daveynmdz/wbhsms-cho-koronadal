<?php
/**
 * Create Invoice API
 * Creates new invoices (management/cashier access only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Root path for includes
$root_path = dirname(dirname(dirname(__DIR__)));

// Check if user is logged in as employee with proper role
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Authentication and authorization check
if (!is_employee_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$employee_role = get_employee_session('role');
if (!in_array($employee_role, ['cashier', 'admin'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient permissions'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['patient_id', 'items'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $patient_id = intval($input['patient_id']);
    $visit_id = isset($input['visit_id']) ? intval($input['visit_id']) : null;
    $items = $input['items'];
    $discount_amount = floatval($input['discount_amount'] ?? 0);
    $philhealth_coverage = floatval($input['philhealth_coverage'] ?? 0);
    $due_days = intval($input['due_days'] ?? 30);
    
    // Validate patient exists
    $patient_check = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $patient_check->execute([$patient_id]);
    if (!$patient_check->fetch()) {
        throw new Exception('Invalid patient ID');
    }
    
    // Validate items array
    if (!is_array($items) || empty($items)) {
        throw new Exception('Items array is required and must not be empty');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Calculate totals
    $subtotal = 0;
    $validated_items = [];
    
    foreach ($items as $item) {
        if (!isset($item['service_item_id']) || !isset($item['quantity'])) {
            throw new Exception('Each item must have service_item_id and quantity');
        }
        
        $service_item_id = intval($item['service_item_id']);
        $quantity = floatval($item['quantity']);
        $custom_price = isset($item['custom_price']) ? floatval($item['custom_price']) : null;
        
        // Get service item details
        $service_sql = "SELECT service_item_id, item_name, price, status FROM service_items WHERE service_item_id = ?";
        $service_stmt = $pdo->prepare($service_sql);
        $service_stmt->execute([$service_item_id]);
        $service_item = $service_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service_item || $service_item['status'] !== 'active') {
            throw new Exception("Service item ID $service_item_id not found or inactive");
        }
        
        $unit_price = $custom_price ?? floatval($service_item['price']);
        $item_subtotal = $quantity * $unit_price;
        $subtotal += $item_subtotal;
        
        $validated_items[] = [
            'service_item_id' => $service_item_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'subtotal' => $item_subtotal
        ];
    }
    
    // Calculate final total
    $total_amount = $subtotal - $discount_amount - $philhealth_coverage;
    
    if ($total_amount < 0) {
        throw new Exception('Total amount cannot be negative');
    }
    
    // Create billing record
    $billing_sql = "
        INSERT INTO billing (
            patient_id, visit_id, total_amount, paid_amount, discount_amount, 
            philhealth_coverage, payment_status, billing_date, due_date, created_at
        ) VALUES (?, ?, ?, 0, ?, ?, 'unpaid', CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), NOW())
    ";
    
    $stmt = $pdo->prepare($billing_sql);
    $stmt->execute([
        $patient_id,
        $visit_id,
        $total_amount,
        $discount_amount,
        $philhealth_coverage,
        $due_days
    ]);
    
    $billing_id = $pdo->lastInsertId();
    
    // Insert billing items
    $item_sql = "
        INSERT INTO billing_items (billing_id, service_item_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ";
    $item_stmt = $pdo->prepare($item_sql);
    
    foreach ($validated_items as $item) {
        $item_stmt->execute([
            $billing_id,
            $item['service_item_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['subtotal']
        ]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response with invoice details
    echo json_encode([
        'success' => true,
        'message' => 'Invoice created successfully',
        'data' => [
            'billing_id' => $billing_id,
            'patient_id' => $patient_id,
            'visit_id' => $visit_id,
            'subtotal' => $subtotal,
            'discount_amount' => $discount_amount,
            'philhealth_coverage' => $philhealth_coverage,
            'total_amount' => $total_amount,
            'payment_status' => 'unpaid',
            'items_count' => count($validated_items),
            'due_date' => date('Y-m-d', strtotime("+$due_days days"))
        ]
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Create invoice API error: " . $e->getMessage());
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>