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

$lab_order_id = $input['lab_order_id'] ?? null;
$overall_status = $input['overall_status'] ?? null;
$remarks = $input['remarks'] ?? '';

if (!$lab_order_id || !$overall_status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lab order ID and status are required']);
    exit();
}

// Validate status values
$validStatuses = ['pending', 'in_progress', 'completed', 'cancelled', 'partial'];
if (!in_array($overall_status, $validStatuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit();
}

try {
    $conn->begin_transaction();

    // Update lab order overall status
    $updateSql = "UPDATE lab_orders 
                  SET overall_status = ?, remarks = ?, updated_at = NOW()
                  WHERE lab_order_id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("ssi", $overall_status, $remarks, $lab_order_id);
    $updateStmt->execute();

    if ($updateStmt->affected_rows === 0) {
        throw new Exception('Lab order not found or no changes made');
    }

    // If marking entire order as completed or cancelled, update all items accordingly
    if ($overall_status === 'completed' || $overall_status === 'cancelled') {
        $updateItemsSql = "UPDATE lab_order_items 
                          SET status = ?, updated_at = NOW() 
                          WHERE lab_order_id = ? AND status != ?";
        
        $updateItemsStmt = $conn->prepare($updateItemsSql);
        $updateItemsStmt->bind_param("sis", $overall_status, $lab_order_id, $overall_status);
        $updateItemsStmt->execute();
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Lab order status updated successfully'
    ]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}