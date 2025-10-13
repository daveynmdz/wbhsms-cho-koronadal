<?php
// Get Patient Invoices API
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
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get patient ID from query parameter
$patient_id = $_GET['patient_id'] ?? '';

if (empty($patient_id) || !is_numeric($patient_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid patient ID is required']);
    exit();
}

$patient_id = intval($patient_id);

try {
    // Verify patient exists
    $patient_check_sql = "SELECT id, first_name, last_name FROM patients WHERE id = ?";
    $patient_stmt = $pdo->prepare($patient_check_sql);
    $patient_stmt->execute([$patient_id]);
    $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
        exit();
    }
    
    // Get invoices with service details
    $invoices_sql = "
        SELECT 
            b.billing_id as id,
            b.patient_id,
            CONCAT('INV-', YEAR(b.billing_date), MONTH(b.billing_date), '-', LPAD(b.billing_id, 4, '0')) as invoice_number,
            DATE(b.billing_date) as invoice_date,
            DATE_ADD(DATE(b.billing_date), INTERVAL 30 DAY) as due_date,
            b.total_amount,
            b.paid_amount,
            b.payment_status as status,
            NULL as payment_method,
            NULL as payment_date,
            b.notes,
            b.created_at,
            NULL as created_by,
            'System' as created_by_name,
            GROUP_CONCAT(
                CONCAT('Service: ', si.item_name, ' (', bi.quantity, 'x ₱', bi.item_price, ')')
                SEPARATOR '; '
            ) as service_summary,
            COUNT(bi.billing_item_id) as service_count
        FROM billing b
        LEFT JOIN billing_items bi ON b.billing_id = bi.billing_id
        LEFT JOIN service_items si ON bi.service_item_id = si.item_id
        WHERE b.patient_id = ?
        GROUP BY b.billing_id
        ORDER BY b.created_at DESC, b.billing_id DESC
    ";
    
    $stmt = $pdo->prepare($invoices_sql);
    $stmt->execute([$patient_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format invoice data and calculate status
    $formatted_invoices = array_map(function($invoice) {
        // Determine actual status based on payment and due date
        $status = $invoice['status'];
        $current_date = new DateTime();
        $due_date = new DateTime($invoice['due_date']);
        $paid_amount = floatval($invoice['paid_amount']);
        $total_amount = floatval($invoice['total_amount']);
        
        // Update status logic
        if ($paid_amount >= $total_amount) {
            $status = 'paid';
        } elseif ($current_date > $due_date && $status === 'unpaid') {
            $status = 'overdue';
        }
        
        return [
            'id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'invoice_date' => $invoice['invoice_date'],
            'due_date' => $invoice['due_date'],
            'total_amount' => $total_amount,
            'paid_amount' => $paid_amount,
            'balance_amount' => $total_amount - $paid_amount,
            'status' => $status,
            'payment_method' => $invoice['payment_method'],
            'payment_date' => $invoice['payment_date'],
            'notes' => $invoice['notes'],
            'service_summary' => $invoice['service_summary'],
            'service_count' => intval($invoice['service_count']),
            'created_at' => $invoice['created_at'],
            'created_by' => $invoice['created_by'],
            'created_by_name' => $invoice['created_by_name'],
            'days_overdue' => $status === 'overdue' ? $current_date->diff($due_date)->days : 0,
            'formatted_total' => number_format($total_amount, 2),
            'formatted_paid' => number_format($paid_amount, 2),
            'formatted_balance' => number_format($total_amount - $paid_amount, 2)
        ];
    }, $invoices);
    
    // Calculate summary statistics
    $total_invoices = count($formatted_invoices);
    $total_amount = array_sum(array_column($formatted_invoices, 'total_amount'));
    $total_paid = array_sum(array_column($formatted_invoices, 'paid_amount'));
    $total_outstanding = $total_amount - $total_paid;
    
    $status_counts = [
        'paid' => 0,
        'unpaid' => 0,
        'overdue' => 0,
        'cancelled' => 0
    ];
    
    foreach ($formatted_invoices as $invoice) {
        if (isset($status_counts[$invoice['status']])) {
            $status_counts[$invoice['status']]++;
        }
    }
    
    // Log access activity - using existing audit system
    $employee_id = get_employee_session('employee_id');
    try {
        $log_sql = "
            INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, created_at) 
            VALUES (?, ?, 'update', ?, NOW())
        ";
        $log_stmt = $pdo->prepare($log_sql);
        $log_description = "Viewed invoices for patient: " . $patient['first_name'] . ' ' . $patient['last_name'] . " (ID: {$patient_id})";
        $log_stmt->execute([$employee_id, $employee_id, $log_description]);
    } catch (PDOException $e) {
        // Log error but don't fail the request
        error_log("Activity log error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'patient' => [
            'id' => $patient['id'],
            'name' => $patient['first_name'] . ' ' . $patient['last_name']
        ],
        'invoices' => $formatted_invoices,
        'summary' => [
            'total_invoices' => $total_invoices,
            'total_amount' => $total_amount,
            'total_paid' => $total_paid,
            'total_outstanding' => $total_outstanding,
            'status_counts' => $status_counts,
            'formatted_total_amount' => number_format($total_amount, 2),
            'formatted_total_paid' => number_format($total_paid, 2),
            'formatted_total_outstanding' => number_format($total_outstanding, 2)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get Patient Invoices Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred while retrieving invoices'
    ]);
} catch (Exception $e) {
    error_log("Get Patient Invoices Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred'
    ]);
}
?>