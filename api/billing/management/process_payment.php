<?php
/**
 * Process Payment API
 * Process payments for invoices (management/cashier access only)
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
    $required_fields = ['billing_id', 'payment_amount', 'payment_method'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $billing_id = intval($input['billing_id']);
    $payment_amount = floatval($input['payment_amount']);
    $payment_method = $input['payment_method'];
    $notes = $input['notes'] ?? '';
    $processed_by = get_employee_session('employee_id');
    
    // Validate payment method
    $valid_methods = ['cash', 'check', 'bank_transfer', 'philhealth'];
    if (!in_array($payment_method, $valid_methods)) {
        throw new Exception('Invalid payment method');
    }
    
    // Validate payment amount
    if ($payment_amount <= 0) {
        throw new Exception('Payment amount must be greater than zero');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get current invoice details
    $invoice_sql = "
        SELECT 
            billing_id,
            patient_id,
            total_amount,
            paid_amount,
            payment_status,
            receipt_id
        FROM billing 
        WHERE billing_id = ? AND payment_status IN ('unpaid', 'partial')
    ";
    
    $stmt = $pdo->prepare($invoice_sql);
    $stmt->execute([$billing_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        throw new Exception('Invoice not found or already paid');
    }
    
    // Calculate new payment totals
    $current_paid = floatval($invoice['paid_amount']);
    $total_amount = floatval($invoice['total_amount']);
    $new_paid_amount = $current_paid + $payment_amount;
    $remaining_balance = $total_amount - $new_paid_amount;
    
    // Validate payment doesn't exceed total
    if ($new_paid_amount > $total_amount) {
        throw new Exception('Payment amount exceeds remaining balance');
    }
    
    // Determine new payment status
    if ($new_paid_amount >= $total_amount) {
        $new_status = 'paid';
    } else {
        $new_status = 'partial';
    }
    
    // Generate receipt number
    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad($billing_id, 6, '0', STR_PAD_LEFT);
    
    // Check if receipt number already exists and make it unique
    $receipt_check = $pdo->prepare("SELECT receipt_id FROM receipts WHERE receipt_number = ?");
    $counter = 1;
    $original_receipt_number = $receipt_number;
    
    do {
        $receipt_check->execute([$receipt_number]);
        if ($receipt_check->fetch()) {
            $receipt_number = $original_receipt_number . '-' . $counter;
            $counter++;
        } else {
            break;
        }
    } while ($counter < 100); // Prevent infinite loop
    
    // Create receipt record
    $receipt_sql = "
        INSERT INTO receipts (
            billing_id, receipt_number, payment_amount, payment_method, 
            payment_date, processed_by, notes, created_at
        ) VALUES (?, ?, ?, ?, NOW(), ?, ?, NOW())
    ";
    
    $stmt = $pdo->prepare($receipt_sql);
    $stmt->execute([
        $billing_id,
        $receipt_number,
        $payment_amount,
        $payment_method,
        $processed_by,
        $notes
    ]);
    
    $receipt_id = $pdo->lastInsertId();
    
    // Update billing record
    $update_billing_sql = "
        UPDATE billing 
        SET paid_amount = ?, payment_status = ?, receipt_id = ?, updated_at = NOW()
        WHERE billing_id = ?
    ";
    
    $stmt = $pdo->prepare($update_billing_sql);
    $stmt->execute([
        $new_paid_amount,
        $new_status,
        $receipt_id,
        $billing_id
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    // Get updated invoice details for response
    $response_sql = "
        SELECT 
            b.billing_id,
            b.patient_id,
            b.total_amount,
            b.paid_amount,
            b.payment_status,
            r.receipt_id,
            r.receipt_number,
            r.payment_amount,
            r.payment_method,
            r.payment_date,
            p.first_name,
            p.last_name,
            p.patient_number
        FROM billing b
        JOIN receipts r ON b.receipt_id = r.receipt_id
        JOIN patients p ON b.patient_id = p.patient_id
        WHERE b.billing_id = ?
    ";
    
    $stmt = $pdo->prepare($response_sql);
    $stmt->execute([$billing_id]);
    $updated_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate change (if cash payment and overpayment)
    $change_amount = 0;
    if ($payment_method === 'cash' && $remaining_balance < 0) {
        $change_amount = abs($remaining_balance);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'data' => [
            'billing_id' => $billing_id,
            'receipt' => [
                'receipt_id' => $receipt_id,
                'receipt_number' => $receipt_number,
                'payment_amount' => $payment_amount,
                'payment_method' => $payment_method,
                'payment_date' => $updated_invoice['payment_date']
            ],
            'invoice' => [
                'total_amount' => floatval($updated_invoice['total_amount']),
                'paid_amount' => floatval($updated_invoice['paid_amount']),
                'remaining_balance' => max(0, $remaining_balance),
                'payment_status' => $updated_invoice['payment_status']
            ],
            'patient' => [
                'name' => $updated_invoice['first_name'] . ' ' . $updated_invoice['last_name'],
                'patient_number' => $updated_invoice['patient_number']
            ],
            'transaction' => [
                'change_amount' => $change_amount,
                'is_fully_paid' => $new_status === 'paid',
                'processed_by' => $processed_by,
                'processed_at' => date('Y-m-d H:i:s')
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Process payment API error: " . $e->getMessage());
    
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