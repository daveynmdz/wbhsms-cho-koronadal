<?php
/**
 * Get Billing Reports API
 * Generate billing reports and statistics (management access only)
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
if (!in_array($employee_role, ['admin', 'cashier'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Insufficient permissions'
    ]);
    exit();
}

try {
    // Get report parameters
    $report_type = $_GET['type'] ?? 'summary';
    $date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default to current month start
    $date_to = $_GET['date_to'] ?? date('Y-m-d'); // Default to today
    $group_by = $_GET['group_by'] ?? 'day'; // day, week, month
    
    $reports = [];
    
    // Summary Report - Overall statistics
    if ($report_type === 'summary' || $report_type === 'all') {
        $summary_sql = "
            SELECT 
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_billed,
                SUM(paid_amount) as total_collected,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_invoices,
                SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_invoices,
                SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_invoices,
                SUM(CASE WHEN payment_status = 'exempted' THEN 1 ELSE 0 END) as exempted_invoices,
                SUM(CASE WHEN payment_status = 'unpaid' THEN total_amount - paid_amount ELSE 0 END) as outstanding_amount,
                SUM(discount_amount) as total_discounts,
                SUM(philhealth_coverage) as total_philhealth,
                AVG(total_amount) as avg_invoice_amount
            FROM billing 
            WHERE DATE(billing_date) BETWEEN ? AND ?
        ";
        
        $stmt = $pdo->prepare($summary_sql);
        $stmt->execute([$date_from, $date_to]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $reports['summary'] = [
            'period' => ['from' => $date_from, 'to' => $date_to],
            'totals' => [
                'invoices' => intval($summary['total_invoices']),
                'billed_amount' => floatval($summary['total_billed']),
                'collected_amount' => floatval($summary['total_collected']),
                'outstanding_amount' => floatval($summary['outstanding_amount']),
                'discount_amount' => floatval($summary['total_discounts']),
                'philhealth_amount' => floatval($summary['total_philhealth']),
                'average_invoice' => floatval($summary['avg_invoice_amount'])
            ],
            'counts' => [
                'paid' => intval($summary['paid_invoices']),
                'unpaid' => intval($summary['unpaid_invoices']),
                'partial' => intval($summary['partial_invoices']),
                'exempted' => intval($summary['exempted_invoices'])
            ],
            'collection_rate' => $summary['total_billed'] > 0 ? 
                round(($summary['total_collected'] / $summary['total_billed']) * 100, 2) : 0
        ];
    }
    
    // Daily Revenue Report
    if ($report_type === 'daily' || $report_type === 'all') {
        $daily_sql = "
            SELECT 
                DATE(billing_date) as report_date,
                COUNT(*) as invoices_count,
                SUM(total_amount) as total_billed,
                SUM(paid_amount) as total_collected,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
            FROM billing 
            WHERE DATE(billing_date) BETWEEN ? AND ?
            GROUP BY DATE(billing_date)
            ORDER BY report_date DESC
        ";
        
        $stmt = $pdo->prepare($daily_sql);
        $stmt->execute([$date_from, $date_to]);
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reports['daily_revenue'] = array_map(function($row) {
            return [
                'date' => $row['report_date'],
                'invoices_count' => intval($row['invoices_count']),
                'total_billed' => floatval($row['total_billed']),
                'total_collected' => floatval($row['total_collected']),
                'paid_count' => intval($row['paid_count']),
                'collection_rate' => $row['total_billed'] > 0 ? 
                    round(($row['total_collected'] / $row['total_billed']) * 100, 2) : 0
            ];
        }, $daily_data);
    }
    
    // Payment Methods Report
    if ($report_type === 'payment_methods' || $report_type === 'all') {
        $payment_methods_sql = "
            SELECT 
                r.payment_method,
                COUNT(*) as transaction_count,
                SUM(r.payment_amount) as total_amount,
                AVG(r.payment_amount) as avg_amount
            FROM receipts r
            JOIN billing b ON r.billing_id = b.billing_id
            WHERE DATE(r.payment_date) BETWEEN ? AND ?
            GROUP BY r.payment_method
            ORDER BY total_amount DESC
        ";
        
        $stmt = $pdo->prepare($payment_methods_sql);
        $stmt->execute([$date_from, $date_to]);
        $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reports['payment_methods'] = array_map(function($row) {
            return [
                'method' => $row['payment_method'],
                'transaction_count' => intval($row['transaction_count']),
                'total_amount' => floatval($row['total_amount']),
                'average_amount' => floatval($row['avg_amount'])
            ];
        }, $payment_methods);
    }
    
    // Top Services Report
    if ($report_type === 'services' || $report_type === 'all') {
        $services_sql = "
            SELECT 
                si.item_name,
                si.category,
                COUNT(*) as usage_count,
                SUM(bi.quantity) as total_quantity,
                SUM(bi.subtotal) as total_revenue,
                AVG(bi.unit_price) as avg_price
            FROM billing_items bi
            JOIN service_items si ON bi.service_item_id = si.service_item_id
            JOIN billing b ON bi.billing_id = b.billing_id
            WHERE DATE(b.billing_date) BETWEEN ? AND ?
            GROUP BY si.service_item_id
            ORDER BY total_revenue DESC
            LIMIT 20
        ";
        
        $stmt = $pdo->prepare($services_sql);
        $stmt->execute([$date_from, $date_to]);
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reports['top_services'] = array_map(function($row) {
            return [
                'service_name' => $row['item_name'],
                'category' => $row['category'],
                'usage_count' => intval($row['usage_count']),
                'total_quantity' => floatval($row['total_quantity']),
                'total_revenue' => floatval($row['total_revenue']),
                'average_price' => floatval($row['avg_price'])
            ];
        }, $services);
    }
    
    // Outstanding Invoices Report
    if ($report_type === 'outstanding' || $report_type === 'all') {
        $outstanding_sql = "
            SELECT 
                b.billing_id,
                b.billing_date,
                b.due_date,
                b.total_amount,
                b.paid_amount,
                (b.total_amount - b.paid_amount) as balance_due,
                DATEDIFF(CURDATE(), b.due_date) as days_overdue,
                p.first_name,
                p.last_name,
                p.patient_number,
                p.phone_number
            FROM billing b
            JOIN patients p ON b.patient_id = p.patient_id
            WHERE b.payment_status IN ('unpaid', 'partial')
            AND b.total_amount > b.paid_amount
            ORDER BY days_overdue DESC, balance_due DESC
            LIMIT 50
        ";
        
        $stmt = $pdo->prepare($outstanding_sql);
        $stmt->execute();
        $outstanding = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reports['outstanding_invoices'] = array_map(function($row) {
            return [
                'billing_id' => intval($row['billing_id']),
                'patient' => [
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'patient_number' => $row['patient_number'],
                    'phone' => $row['phone_number']
                ],
                'dates' => [
                    'billing_date' => $row['billing_date'],
                    'due_date' => $row['due_date']
                ],
                'amounts' => [
                    'total' => floatval($row['total_amount']),
                    'paid' => floatval($row['paid_amount']),
                    'balance_due' => floatval($row['balance_due'])
                ],
                'days_overdue' => intval($row['days_overdue']),
                'is_overdue' => intval($row['days_overdue']) > 0
            ];
        }, $outstanding);
    }
    
    // Return reports
    echo json_encode([
        'success' => true,
        'data' => [
            'reports' => $reports,
            'generated_at' => date('Y-m-d H:i:s'),
            'parameters' => [
                'type' => $report_type,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'group_by' => $group_by
            ]
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Billing reports API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>