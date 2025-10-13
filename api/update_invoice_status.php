<?php
// Update Invoice Status API
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
if (!isset($input['invoice_id']) || !isset($input['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invoice ID and status are required']);
    exit();
}

// Validate invoice ID
if (!is_numeric($input['invoice_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid invoice ID']);
    exit();
}

$invoice_id = intval($input['invoice_id']);
$new_status = trim($input['status']);
$payment_method = $input['payment_method'] ?? '';
$payment_amount = $input['payment_amount'] ?? null;
$notes = $input['notes'] ?? '';

// Validate status
$valid_statuses = ['unpaid', 'paid', 'partial', 'exempted', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Validate payment amount for paid status
if ($new_status === 'paid' && $payment_amount !== null) {
    if (!is_numeric($payment_amount) || floatval($payment_amount) <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
        exit();
    }
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Get current invoice details
    $invoice_sql = "
        SELECT 
            b.billing_id as id,
            b.patient_id,
            CONCAT('INV-', YEAR(b.billing_date), MONTH(b.billing_date), '-', LPAD(b.billing_id, 4, '0')) as invoice_number,
            b.total_amount,
            b.paid_amount,
            b.payment_status as current_status,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM billing b
        JOIN patients p ON b.patient_id = p.id
        WHERE b.billing_id = ?
    ";
    
    $invoice_stmt = $pdo->prepare($invoice_sql);
    $invoice_stmt->execute([$invoice_id]);
    $invoice = $invoice_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invoice) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invoice not found']);
        exit();
    }
    
    $total_amount = floatval($invoice['total_amount']);
    $current_paid_amount = floatval($invoice['paid_amount']);
    $current_status = $invoice['current_status'];
    
    // Prepare update data
    $update_fields = ['payment_status = ?'];
    $update_values = [$new_status];
    
    // Handle payment updates
    if ($new_status === 'paid') {
        if ($payment_amount !== null) {
            $payment_amount = floatval($payment_amount);
            // Validate payment amount doesn't exceed total
            if ($payment_amount > $total_amount) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Payment amount cannot exceed total amount']);
                exit();
            }
            $new_paid_amount = $payment_amount;
        } else {
            // If no payment amount specified, mark as fully paid
            $new_paid_amount = $total_amount;
        }
        
        $update_fields[] = 'paid_amount = ?';
        $update_values[] = $new_paid_amount;
        
        $update_fields[] = 'updated_at = NOW()';
        
    } elseif ($new_status === 'partial' && $payment_amount !== null) {
        $payment_amount = floatval($payment_amount);
        $new_paid_amount = $current_paid_amount + $payment_amount;
        
        // Validate partial payment
        if ($new_paid_amount > $total_amount) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Total payments cannot exceed invoice amount']);
            exit();
        }
        
        // Check if this partial payment actually completes the invoice
        if ($new_paid_amount >= $total_amount) {
            $new_status = 'paid';
            $update_values[0] = 'paid'; // Update the status in our values array
            $update_fields[] = 'updated_at = NOW()';
        }
        
        $update_fields[] = 'paid_amount = ?';
        $update_values[] = $new_paid_amount;
    }
    
    // Add notes if provided
    if (!empty($notes)) {
        $update_fields[] = 'notes = CONCAT(COALESCE(notes, ""), "\n[", NOW(), "] ", ?)';
        $update_values[] = $notes;
    }
    
    // Update invoice
    $update_sql = "UPDATE billing SET " . implode(', ', $update_fields) . " WHERE billing_id = ?";
    $update_values[] = $invoice_id;
    
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute($update_values);
    
    // Log status change - using existing audit system
    $employee_id = get_employee_session('employee_id');
    try {
        $log_sql = "
            INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, created_at) 
            VALUES (?, ?, 'update', ?, NOW())
        ";
        $log_stmt = $pdo->prepare($log_sql);
        $log_description = "Updated invoice {$invoice['invoice_number']} status from {$current_status} to {$new_status} for patient: " . $invoice['patient_name'];
        $log_stmt->execute([$employee_id, $employee_id, $log_description]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Activity log error: " . $e->getMessage());
    }
    
    // If payment was made, also log in payment records (if table exists)
    if (in_array($new_status, ['paid', 'partially_paid']) && isset($new_paid_amount)) {
        try {
            $payment_log_sql = "
                INSERT INTO payment_logs (
                    invoice_id, 
                    patient_id, 
                    amount, 
                    payment_method, 
                    payment_date, 
                    processed_by, 
                    created_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, NOW())
            ";
            $payment_stmt = $pdo->prepare($payment_log_sql);
            $payment_stmt->execute([
                $invoice_id,
                $invoice['patient_id'],
                $payment_amount ?? ($new_paid_amount - $current_paid_amount),
                $payment_method ?: 'cash',
                $employee_id
            ]);
        } catch (PDOException $e) {
            // Payment logs table might not exist, continue without error
            error_log("Payment log error (non-critical): " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Prepare response data
    $response_data = [
        'success' => true,
        'message' => 'Invoice status updated successfully',
        'invoice' => [
            'id' => $invoice_id,
            'invoice_number' => $invoice['invoice_number'],
            'patient_name' => $invoice['patient_name'],
            'old_status' => $current_status,
            'new_status' => $new_status,
            'total_amount' => $total_amount,
            'paid_amount' => $new_paid_amount ?? $current_paid_amount,
            'balance_amount' => $total_amount - ($new_paid_amount ?? $current_paid_amount),
            'payment_method' => $payment_method ?: null
        ]
    ];
    
    // Add payment info if applicable
    if (isset($payment_amount)) {
        $response_data['payment'] = [
            'amount' => $payment_amount,
            'method' => $payment_method ?: 'cash',
            'processed_by' => get_employee_session('first_name') . ' ' . get_employee_session('last_name'),
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    echo json_encode($response_data);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Update Invoice Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while updating invoice status'
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Update Invoice Status Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred'
    ]);
}
?>