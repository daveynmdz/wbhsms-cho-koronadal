<?php
/**
 * Search Invoices API
 * Search and filter invoices (management/cashier access only)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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
if (!in_array($employee_role, ['cashier', 'admin', 'doctor', 'nurse'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient permissions'
    ]);
    exit();
}

try {
    // Get search and filter parameters
    $filters = [
        'patient_search' => $_GET['patient_search'] ?? '',
        'patient_id' => $_GET['patient_id'] ?? null,
        'status' => $_GET['status'] ?? 'all',
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'amount_min' => $_GET['amount_min'] ?? null,
        'amount_max' => $_GET['amount_max'] ?? null,
        'payment_method' => $_GET['payment_method'] ?? 'all',
        'page' => max(1, intval($_GET['page'] ?? 1)),
        'limit' => min(100, max(10, intval($_GET['limit'] ?? 25)))
    ];
    
    $offset = ($filters['page'] - 1) * $filters['limit'];
    
    // Build base query
    $sql = "
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
            b.receipt_id,
            r.receipt_number,
            r.payment_method,
            r.payment_date,
            p.first_name,
            p.last_name,
            p.patient_number,
            p.phone_number,
            COUNT(bi.item_id) as item_count,
            GROUP_CONCAT(si.item_name SEPARATOR ', ') as service_names
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN receipts r ON b.receipt_id = r.receipt_id
        LEFT JOIN billing_items bi ON b.billing_id = bi.billing_id
        LEFT JOIN service_items si ON bi.service_item_id = si.service_item_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add patient search filter
    if (!empty($filters['patient_search'])) {
        $search_term = '%' . $filters['patient_search'] . '%';
        $sql .= " AND (
            p.first_name LIKE ? OR 
            p.last_name LIKE ? OR 
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR
            p.patient_number LIKE ?
        )";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    }
    
    // Add specific patient filter
    if ($filters['patient_id']) {
        $sql .= " AND b.patient_id = ?";
        $params[] = intval($filters['patient_id']);
    }
    
    // Add status filter
    if ($filters['status'] !== 'all') {
        $sql .= " AND b.payment_status = ?";
        $params[] = $filters['status'];
    }
    
    // Add date filters
    if ($filters['date_from']) {
        $sql .= " AND DATE(b.billing_date) >= ?";
        $params[] = $filters['date_from'];
    }
    
    if ($filters['date_to']) {
        $sql .= " AND DATE(b.billing_date) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Add amount filters
    if ($filters['amount_min']) {
        $sql .= " AND b.total_amount >= ?";
        $params[] = floatval($filters['amount_min']);
    }
    
    if ($filters['amount_max']) {
        $sql .= " AND b.total_amount <= ?";
        $params[] = floatval($filters['amount_max']);
    }
    
    // Add payment method filter
    if ($filters['payment_method'] !== 'all') {
        $sql .= " AND r.payment_method = ?";
        $params[] = $filters['payment_method'];
    }
    
    $sql .= " GROUP BY b.billing_id";
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(DISTINCT b.billing_id) FROM (" . $sql . ") as filtered_results";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $filters['limit']);
    
    // Add sorting and pagination
    $sql .= " ORDER BY b.billing_date DESC, b.billing_id DESC LIMIT ? OFFSET ?";
    $params[] = $filters['limit'];
    $params[] = $offset;
    
    // Execute main query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results
    $formatted_invoices = [];
    foreach ($invoices as $invoice) {
        $formatted_invoices[] = [
            'billing_id' => intval($invoice['billing_id']),
            'visit_id' => $invoice['visit_id'] ? intval($invoice['visit_id']) : null,
            'patient' => [
                'patient_id' => intval($invoice['patient_id']),
                'name' => $invoice['first_name'] . ' ' . $invoice['last_name'],
                'patient_number' => $invoice['patient_number'],
                'phone' => $invoice['phone_number']
            ],
            'amounts' => [
                'total_amount' => floatval($invoice['total_amount']),
                'paid_amount' => floatval($invoice['paid_amount']),
                'discount_amount' => floatval($invoice['discount_amount']),
                'philhealth_coverage' => floatval($invoice['philhealth_coverage']),
                'balance_due' => floatval($invoice['total_amount'] - $invoice['paid_amount'])
            ],
            'payment_status' => $invoice['payment_status'],
            'dates' => [
                'billing_date' => $invoice['billing_date'],
                'due_date' => $invoice['due_date'],
                'payment_date' => $invoice['payment_date']
            ],
            'receipt' => $invoice['receipt_id'] ? [
                'receipt_number' => $invoice['receipt_number'],
                'payment_method' => $invoice['payment_method']
            ] : null,
            'services' => [
                'item_count' => intval($invoice['item_count']),
                'service_names' => $invoice['service_names']
            ],
            'status_info' => [
                'is_paid' => $invoice['payment_status'] === 'paid',
                'is_overdue' => $invoice['payment_status'] === 'unpaid' && $invoice['due_date'] < date('Y-m-d'),
                'has_receipt' => !empty($invoice['receipt_id']),
                'days_overdue' => $invoice['payment_status'] === 'unpaid' && $invoice['due_date'] < date('Y-m-d') ? 
                    (new DateTime())->diff(new DateTime($invoice['due_date']))->days : 0
            ]
        ];
    }
    
    // Get summary statistics for the filtered results
    $summary_sql = "
        SELECT 
            COUNT(*) as total_invoices,
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN payment_status = 'exempted' THEN 1 ELSE 0 END) as exempted_count,
            SUM(total_amount) as total_amount_sum,
            SUM(paid_amount) as paid_amount_sum,
            SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount - paid_amount ELSE 0 END) as outstanding_amount
        FROM billing b
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN receipts r ON b.receipt_id = r.receipt_id
        WHERE 1=1
    ";
    
    // Apply same filters for summary (excluding pagination)
    $summary_params = array_slice($params, 0, -2); // Remove limit and offset
    
    if (!empty($filters['patient_search'])) {
        $search_term = '%' . $filters['patient_search'] . '%';
        $summary_sql .= " AND (
            p.first_name LIKE ? OR 
            p.last_name LIKE ? OR 
            CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR
            p.patient_number LIKE ?
        )";
        // summary_params already includes these from the main params
    }
    
    if ($filters['patient_id']) {
        $summary_sql .= " AND b.patient_id = ?";
    }
    
    if ($filters['status'] !== 'all') {
        $summary_sql .= " AND b.payment_status = ?";
    }
    
    if ($filters['date_from']) {
        $summary_sql .= " AND DATE(b.billing_date) >= ?";
    }
    
    if ($filters['date_to']) {
        $summary_sql .= " AND DATE(b.billing_date) <= ?";
    }
    
    if ($filters['amount_min']) {
        $summary_sql .= " AND b.total_amount >= ?";
    }
    
    if ($filters['amount_max']) {
        $summary_sql .= " AND b.total_amount <= ?";
    }
    
    if ($filters['payment_method'] !== 'all') {
        $summary_sql .= " AND r.payment_method = ?";
    }
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute($summary_params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return results
    echo json_encode([
        'success' => true,
        'data' => [
            'invoices' => $formatted_invoices,
            'pagination' => [
                'current_page' => $filters['page'],
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'per_page' => $filters['limit'],
                'has_next' => $filters['page'] < $total_pages,
                'has_previous' => $filters['page'] > 1
            ],
            'summary' => [
                'total_invoices' => intval($summary['total_invoices']),
                'unpaid_count' => intval($summary['unpaid_count']),
                'paid_count' => intval($summary['paid_count']),
                'exempted_count' => intval($summary['exempted_count']),
                'total_amount_sum' => floatval($summary['total_amount_sum']),
                'paid_amount_sum' => floatval($summary['paid_amount_sum']),
                'outstanding_amount' => floatval($summary['outstanding_amount'])
            ],
            'filters_applied' => $filters
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Search invoices API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>