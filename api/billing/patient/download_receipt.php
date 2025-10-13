<?php
/**
 * Download Receipt API
 * Generates and downloads receipt for paid invoices (patient access only)
 */

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
    $format = $_GET['format'] ?? 'html'; // html, json, pdf
    
    if (!$billing_id) {
        throw new Exception('Billing ID is required');
    }
    
    // Verify invoice belongs to patient and has been paid
    $verify_sql = "
        SELECT 
            b.billing_id,
            b.patient_id,
            b.payment_status,
            b.receipt_id,
            r.receipt_number
        FROM billing b
        LEFT JOIN receipts r ON b.receipt_id = r.receipt_id
        WHERE b.billing_id = ? AND b.patient_id = ? AND b.payment_status = 'paid' AND b.receipt_id IS NOT NULL
    ";
    
    $stmt = $pdo->prepare($verify_sql);
    $stmt->execute([$billing_id, $patient_id]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$verification) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Receipt not found or invoice not paid'
        ]);
        exit();
    }
    
    // Get complete receipt data
    $receipt_sql = "
        SELECT 
            b.billing_id,
            b.total_amount,
            b.paid_amount,
            b.discount_amount,
            b.philhealth_coverage,
            b.billing_date,
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
            e.first_name as cashier_first_name,
            e.last_name as cashier_last_name
        FROM billing b
        JOIN receipts r ON b.receipt_id = r.receipt_id
        JOIN patients p ON b.patient_id = p.patient_id
        LEFT JOIN employees e ON r.processed_by = e.employee_id
        WHERE b.billing_id = ?
    ";
    
    $stmt = $pdo->prepare($receipt_sql);
    $stmt->execute([$billing_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get receipt items
    $items_sql = "
        SELECT 
            bi.quantity,
            bi.unit_price,
            bi.subtotal,
            si.item_name,
            si.description
        FROM billing_items bi
        JOIN service_items si ON bi.service_item_id = si.service_item_id
        WHERE bi.billing_id = ?
        ORDER BY bi.item_id
    ";
    
    $stmt = $pdo->prepare($items_sql);
    $stmt->execute([$billing_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format receipt data
    $receipt_data = [
        'receipt_number' => $receipt['receipt_number'],
        'billing_id' => $receipt['billing_id'],
        'patient' => [
            'name' => $receipt['first_name'] . ' ' . $receipt['last_name'],
            'patient_number' => $receipt['patient_number'],
            'phone' => $receipt['phone_number'],
            'address' => $receipt['address']
        ],
        'items' => $items,
        'amounts' => [
            'subtotal' => array_sum(array_column($items, 'subtotal')),
            'discount' => floatval($receipt['discount_amount']),
            'philhealth_coverage' => floatval($receipt['philhealth_coverage']),
            'total' => floatval($receipt['total_amount']),
            'paid' => floatval($receipt['payment_amount'])
        ],
        'payment' => [
            'method' => $receipt['payment_method'],
            'date' => $receipt['payment_date'],
            'notes' => $receipt['notes']
        ],
        'dates' => [
            'billing_date' => $receipt['billing_date'],
            'payment_date' => $receipt['payment_date']
        ],
        'cashier' => $receipt['cashier_first_name'] ? 
            $receipt['cashier_first_name'] . ' ' . $receipt['cashier_last_name'] : 'System'
    ];
    
    // Handle different output formats
    if ($format === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $receipt_data
        ]);
        
    } else if ($format === 'html') {
        // Generate HTML receipt for display/printing
        include $root_path . '/api/billing/shared/receipt_generator.php';
        generateHTMLReceipt($receipt_data);
        
    } else if ($format === 'pdf') {
        // For future PDF implementation
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'PDF format not yet implemented'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
    error_log("Download receipt API error: " . $e->getMessage());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>