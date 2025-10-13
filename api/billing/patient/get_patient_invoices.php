<?php
/**
 * Get Patient Invoices API
 * Returns all invoices for a specific patient (patient access only)
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
    
    // Validate patient ID
    if (!$patient_id) {
        throw new Exception('Invalid patient session');
    }
    
    // Get optional filters from query parameters
    $filters = [
        'status' => $_GET['status'] ?? 'all',
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'limit' => intval($_GET['limit'] ?? 50)
    ];
    
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
            r.payment_date,
            r.payment_method,
            COUNT(bi.item_id) as item_count
        FROM billing b
        LEFT JOIN receipts r ON b.receipt_id = r.receipt_id
        LEFT JOIN billing_items bi ON b.billing_id = bi.billing_id
        WHERE b.patient_id = ?
    ";
    
    $params = [$patient_id];
    
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
    
    $sql .= " GROUP BY b.billing_id ORDER BY b.billing_date DESC LIMIT ?";
    $params[] = $filters['limit'];
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    $formatted_invoices = [];
    foreach ($invoices as $invoice) {
        $formatted_invoices[] = [
            'billing_id' => intval($invoice['billing_id']),
            'visit_id' => $invoice['visit_id'] ? intval($invoice['visit_id']) : null,
            'total_amount' => floatval($invoice['total_amount']),
            'paid_amount' => floatval($invoice['paid_amount']),
            'discount_amount' => floatval($invoice['discount_amount']),
            'philhealth_coverage' => floatval($invoice['philhealth_coverage']),
            'balance_due' => floatval($invoice['total_amount'] - $invoice['paid_amount']),
            'payment_status' => $invoice['payment_status'],
            'billing_date' => $invoice['billing_date'],
            'due_date' => $invoice['due_date'],
            'receipt_number' => $invoice['receipt_number'],
            'payment_date' => $invoice['payment_date'],
            'payment_method' => $invoice['payment_method'],
            'item_count' => intval($invoice['item_count']),
            'is_overdue' => $invoice['payment_status'] === 'unpaid' && $invoice['due_date'] < date('Y-m-d'),
            'has_receipt' => !empty($invoice['receipt_id'])
        ];
    }
    
    // Get summary statistics
    $summary_sql = "
        SELECT 
            COUNT(*) as total_invoices,
            SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount - paid_amount ELSE 0 END) as total_outstanding,
            SUM(CASE WHEN payment_status = 'paid' AND YEAR(billing_date) = YEAR(CURDATE()) THEN paid_amount ELSE 0 END) as paid_this_year
        FROM billing 
        WHERE patient_id = ?
    ";
    
    $stmt = $pdo->prepare($summary_sql);
    $stmt->execute([$patient_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => [
            'invoices' => $formatted_invoices,
            'summary' => [
                'total_invoices' => intval($summary['total_invoices']),
                'unpaid_count' => intval($summary['unpaid_count']),
                'paid_count' => intval($summary['paid_count']),
                'total_outstanding' => floatval($summary['total_outstanding']),
                'paid_this_year' => floatval($summary['paid_this_year'])
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
    error_log("Patient invoices API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>