<?php
/**
 * Print Receipt API
 * Generate printable receipt for paid invoices (management access only)
 */

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

try {
    // Get parameters
    $billing_id = $_GET['billing_id'] ?? null;
    $receipt_id = $_GET['receipt_id'] ?? null;
    $format = $_GET['format'] ?? 'html'; // html, json
    
    if (!$billing_id && !$receipt_id) {
        throw new Exception('Either billing_id or receipt_id is required');
    }
    
    // Build query based on provided parameter
    if ($billing_id) {
        $condition = "b.billing_id = ?";
        $param = intval($billing_id);
    } else {
        $condition = "r.receipt_id = ?";
        $param = intval($receipt_id);
    }
    
    // Get receipt data
    $receipt_sql = "
        SELECT 
            b.billing_id,
            b.total_amount,
            b.paid_amount,
            b.discount_amount,
            b.philhealth_coverage,
            b.billing_date,
            b.payment_status,
            r.receipt_id,
            r.receipt_number,
            r.payment_amount,
            r.payment_method,
            r.payment_date,
            r.notes,
            p.first_name,
            p.last_name,
            p.patient_number,
            p.phone_number,
            p.address,
            p.email,
            e.first_name as cashier_first_name,
            e.last_name as cashier_last_name,
            e.employee_number
        FROM billing b
        JOIN receipts r ON b.receipt_id = r.receipt_id
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN employees e ON r.processed_by = e.employee_id
        WHERE $condition AND b.payment_status IN ('paid', 'partial')
    ";
    
    $stmt = $pdo->prepare($receipt_sql);
    $stmt->execute([$param]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receipt) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Receipt not found or invoice not paid'
        ]);
        exit();
    }
    
    // Get receipt items
    $items_sql = "
        SELECT 
            bi.quantity,
            bi.unit_price,
            bi.subtotal,
            si.item_name,
            si.description,
            si.category
        FROM billing_items bi
        JOIN service_items si ON bi.service_item_id = si.service_item_id
        WHERE bi.billing_id = ?
        ORDER BY bi.item_id
    ";
    
    $stmt = $pdo->prepare($items_sql);
    $stmt->execute([$receipt['billing_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format receipt data
    $receipt_data = [
        'receipt_info' => [
            'receipt_number' => $receipt['receipt_number'],
            'receipt_id' => intval($receipt['receipt_id']),
            'billing_id' => intval($receipt['billing_id']),
            'issue_date' => date('F d, Y', strtotime($receipt['payment_date'])),
            'issue_time' => date('h:i A', strtotime($receipt['payment_date']))
        ],
        'facility' => [
            'name' => 'City Health Office Koronadal',
            'address' => 'Koronadal City, South Cotabato',
            'phone' => '(083) 228-8045',
            'email' => 'cho.koronadal@gmail.com'
        ],
        'patient' => [
            'name' => $receipt['first_name'] . ' ' . $receipt['last_name'],
            'patient_number' => $receipt['patient_number'],
            'phone' => $receipt['phone_number'],
            'address' => $receipt['address'],
            'email' => $receipt['email']
        ],
        'services' => array_map(function($item) {
            return [
                'name' => $item['item_name'],
                'description' => $item['description'],
                'category' => $item['category'],
                'quantity' => floatval($item['quantity']),
                'unit_price' => floatval($item['unit_price']),
                'subtotal' => floatval($item['subtotal'])
            ];
        }, $items),
        'amounts' => [
            'subtotal' => array_sum(array_column($items, 'subtotal')),
            'discount' => floatval($receipt['discount_amount']),
            'philhealth_coverage' => floatval($receipt['philhealth_coverage']),
            'total' => floatval($receipt['total_amount']),
            'amount_paid' => floatval($receipt['payment_amount']),
            'balance_due' => floatval($receipt['total_amount'] - $receipt['paid_amount'])
        ],
        'payment' => [
            'method' => ucfirst($receipt['payment_method']),
            'date' => $receipt['payment_date'],
            'notes' => $receipt['notes'],
            'status' => $receipt['payment_status']
        ],
        'staff' => [
            'cashier' => $receipt['cashier_first_name'] ? 
                $receipt['cashier_first_name'] . ' ' . $receipt['cashier_last_name'] : 'System',
            'employee_number' => $receipt['employee_number']
        ]
    ];
    
    // Handle different output formats
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $receipt_data
        ]);
        
    } else if ($format === 'html') {
        // Generate HTML receipt for printing
        include $root_path . '/api/billing/shared/receipt_generator.php';
        generatePrintableReceipt($receipt_data);
        
    } else {
        throw new Exception('Invalid format requested');
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Print receipt API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>