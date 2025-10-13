<?php
/**
 * Get Invoice Details API
 * Returns detailed information about a specific invoice (patient access only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Root path for includes
$root_path = dirname(dirname(dirname(__DIR__)));

// Check if user is logged in as patient
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';

// Authentication check
if (!is_patient_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

try {
    // Get patient ID from session
    $patient_id = get_patient_session('patient_id');
    
    // Get billing ID from URL parameter
    $billing_id = $_GET['billing_id'] ?? null;
    
    if (!$billing_id) {
        throw new Exception('Billing ID is required');
    }
    
    // Get invoice details with security check (patient can only see their own invoices)
    $invoice_sql = "
        SELECT 
            b.billing_id,
            b.patient_id,
            b.visit_id,
            b.total_amount,
            b.paid_amount,
            b.discount_amount,
            b.philhealth_coverage,
            b.payment_status,
            b.billing_date,
            b.due_date,
            b.created_at,
            b.updated_at,
            b.receipt_id,
            r.receipt_number,
            r.payment_date,
            r.payment_method,
            r.payment_amount as receipt_payment_amount,
            r.notes as payment_notes,
            p.first_name,
            p.last_name,
            p.patient_number,
            p.phone_number,
            p.email
        FROM billing b
        LEFT JOIN receipts r ON b.receipt_id = r.receipt_id
        LEFT JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.billing_id = ? AND b.patient_id = ?
    ";
    
    $stmt = $pdo->prepare($invoice_sql);
    $stmt->execute([$billing_id, $patient_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Invoice not found or access denied'
        ]);
        exit();
    }
    
    // Get billing items (line items)
    $items_sql = "
        SELECT 
            bi.item_id,
            bi.service_item_id,
            bi.quantity,
            bi.unit_price,
            bi.subtotal,
            si.item_name,
            si.description,
            si.category
        FROM billing_items bi
        LEFT JOIN service_items si ON bi.service_item_id = si.service_item_id
        WHERE bi.billing_id = ?
        ORDER BY bi.item_id
    ";
    
    $stmt = $pdo->prepare($items_sql);
    $stmt->execute([$billing_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format invoice data
    $formatted_invoice = [
        'billing_id' => intval($invoice['billing_id']),
        'visit_id' => $invoice['visit_id'] ? intval($invoice['visit_id']) : null,
        'patient' => [
            'patient_id' => intval($invoice['patient_id']),
            'patient_number' => $invoice['patient_number'],
            'name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
            'phone' => $invoice['phone_number'],
            'email' => $invoice['email']
        ],
        'amounts' => [
            'subtotal' => floatval(array_sum(array_column($items, 'subtotal'))),
            'discount_amount' => floatval($invoice['discount_amount']),
            'philhealth_coverage' => floatval($invoice['philhealth_coverage']),
            'total_amount' => floatval($invoice['total_amount']),
            'paid_amount' => floatval($invoice['paid_amount']),
            'balance_due' => floatval($invoice['total_amount'] - $invoice['paid_amount'])
        ],
        'payment_status' => $invoice['payment_status'],
        'dates' => [
            'billing_date' => $invoice['billing_date'],
            'due_date' => $invoice['due_date'],
            'payment_date' => $invoice['payment_date'],
            'created_at' => $invoice['created_at'],
            'updated_at' => $invoice['updated_at']
        ],
        'receipt' => $invoice['receipt_id'] ? [
            'receipt_id' => intval($invoice['receipt_id']),
            'receipt_number' => $invoice['receipt_number'],
            'payment_amount' => floatval($invoice['receipt_payment_amount']),
            'payment_method' => $invoice['payment_method'],
            'payment_date' => $invoice['payment_date'],
            'notes' => $invoice['payment_notes']
        ] : null,
        'items' => array_map(function($item) {
            return [
                'item_id' => intval($item['item_id']),
                'service_item_id' => intval($item['service_item_id']),
                'name' => $item['item_name'],
                'description' => $item['description'],
                'category' => $item['category'],
                'quantity' => floatval($item['quantity']),
                'unit_price' => floatval($item['unit_price']),
                'subtotal' => floatval($item['subtotal'])
            ];
        }, $items),
        'status_info' => [
            'is_paid' => $invoice['payment_status'] === 'paid',
            'is_overdue' => $invoice['payment_status'] === 'unpaid' && $invoice['due_date'] < date('Y-m-d'),
            'has_receipt' => !empty($invoice['receipt_id']),
            'is_exempted' => $invoice['payment_status'] === 'exempted'
        ]
    ];
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $formatted_invoice
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Invoice details API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>