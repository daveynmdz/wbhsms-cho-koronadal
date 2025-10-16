<?php
// Include employee session configuration
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';
include $root_path . '/config/db.php';

// Server-side role enforcement
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['admin', 'laboratory_tech'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

$lab_order_item_id = $input['lab_order_item_id'] ?? null;
$status = $input['status'] ?? null;
$remarks = $input['remarks'] ?? '';

if (!$lab_order_item_id || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lab order item ID and status are required']);
    exit();
}

// Validate status values
$validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    $conn->begin_transaction();

    // Update lab order item status
    $updateSql = "UPDATE lab_order_items 
                  SET status = ?, remarks = ?, updated_at = NOW()
                  WHERE lab_order_item_id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ssi", $status, $remarks, $lab_order_item_id);
    $updateStmt->execute();

    if ($updateStmt->affected_rows === 0) {
        throw new Exception('Lab order item not found or no changes made');
    }

    // Get the lab order ID to update overall status
    $orderSql = "SELECT lab_order_id FROM lab_order_items WHERE lab_order_item_id = ?";
    $orderStmt = $conn->prepare($orderSql);
    $orderStmt->bind_param("i", $lab_order_item_id);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    $orderData = $orderResult->fetch_assoc();
    
    if ($orderData) {
        // Calculate overall status based on individual item statuses
        $statusSql = "SELECT 
                        COUNT(*) as total_items,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_items,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_items
                      FROM lab_order_items 
                      WHERE lab_order_id = ?";
        
        $statusStmt = $conn->prepare($statusSql);
        $statusStmt->bind_param("i", $orderData['lab_order_id']);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();
        $statusData = $statusResult->fetch_assoc();
        
        // Determine overall status
        $overall_status = 'pending';
        if ($statusData['completed_items'] == $statusData['total_items']) {
            $overall_status = 'completed';
        } elseif ($statusData['cancelled_items'] == $statusData['total_items']) {
            $overall_status = 'cancelled';
        } elseif ($statusData['completed_items'] > 0 || $statusData['in_progress_items'] > 0) {
            if ($statusData['completed_items'] > 0) {
                $overall_status = 'partial';
            } else {
                $overall_status = 'in_progress';
            }
        }
        
        // Update overall order status
        $updateOrderSql = "UPDATE lab_orders SET overall_status = ?, updated_at = NOW() WHERE lab_order_id = ?";
        $updateOrderStmt = $conn->prepare($updateOrderSql);
        $updateOrderStmt->bind_param("si", $overall_status, $orderData['lab_order_id']);
        $updateOrderStmt->execute();
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Lab test status updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}