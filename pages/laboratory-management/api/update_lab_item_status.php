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

    // Get current status and order info for timing logic
    $currentSql = "SELECT loi.status, loi.lab_order_id, loi.started_at, lo.order_date 
                   FROM lab_order_items loi
                   LEFT JOIN lab_orders lo ON loi.lab_order_id = lo.lab_order_id
                   WHERE loi.item_id = ?";
    $currentStmt = $conn->prepare($currentSql);
    $currentStmt->bind_param("i", $lab_order_item_id);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentData = $currentResult->fetch_assoc();
    
    if (!$currentData) {
        throw new Exception('Lab order item not found');
    }

    $currentStatus = $currentData['status'];
    $orderDate = $currentData['order_date'];
    $startedAt = $currentData['started_at'];

    // Prepare timing updates based on status transitions
    $timingUpdates = [];
    $timingParams = [];
    $timingTypes = "";

    // Check if this is a timing-relevant transition for lab technicians
    // Support both role name and role_id checking
    $isLabTechnician = ($_SESSION['role'] === 'laboratory_tech') || 
                       (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 9);
    
    if ($isLabTechnician && $currentStatus === 'pending' && $status === 'in_progress') {
        // Transition: pending → in_progress
        // Set started_at and calculate waiting_time
        $timingUpdates[] = "started_at = NOW()";
        $timingUpdates[] = "waiting_time = TIMESTAMPDIFF(MINUTE, ?, NOW())";
        $timingParams[] = $orderDate;
        $timingTypes .= "s";
    } elseif ($isLabTechnician && $currentStatus === 'in_progress' && $status === 'completed' && $startedAt) {
        // Transition: in_progress → completed
        // Set completed_at and calculate turnaround_time
        $timingUpdates[] = "completed_at = NOW()";
        $timingUpdates[] = "turnaround_time = TIMESTAMPDIFF(MINUTE, started_at, NOW())";
    }

    // Build the update SQL
    $updateSql = "UPDATE lab_order_items 
                  SET status = ?, remarks = ?, updated_at = NOW()";
    
    if (!empty($timingUpdates)) {
        $updateSql .= ", " . implode(", ", $timingUpdates);
    }
    
    $updateSql .= " WHERE item_id = ?";
    
    // Prepare parameters
    $params = [$status, $remarks];
    $types = "ss";
    
    // Add timing parameters if any
    if (!empty($timingParams)) {
        $params = array_merge($params, $timingParams);
        $types .= $timingTypes;
    }
    
    // Add item_id parameter
    $params[] = $lab_order_item_id;
    $types .= "i";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param($types, ...$params);
    $updateStmt->execute();

    if ($updateStmt->affected_rows === 0) {
        throw new Exception('Lab order item not found or no changes made');
    }

    // Update overall order status and calculate average turnaround time
    $lab_order_id = $currentData['lab_order_id'];
    
    // Calculate overall status based on individual item statuses
    $statusSql = "SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_items,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_items,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_items,
                    AVG(CASE WHEN turnaround_time IS NOT NULL THEN turnaround_time END) as avg_turnaround
                  FROM lab_order_items 
                  WHERE lab_order_id = ?";
    
    $statusStmt = $conn->prepare($statusSql);
    $statusStmt->bind_param("i", $lab_order_id);
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
    
    // Check if overall_status column exists, if not use regular status
    $checkColumnSql = "SHOW COLUMNS FROM lab_orders LIKE 'overall_status'";
    $columnResult = $conn->query($checkColumnSql);
    $hasOverallStatus = $columnResult->num_rows > 0;
    
    // Update overall order status and average turnaround time
    if ($hasOverallStatus) {
        $updateOrderSql = "UPDATE lab_orders SET overall_status = ?, average_tat = ?, updated_at = NOW() WHERE lab_order_id = ?";
        $updateOrderStmt = $conn->prepare($updateOrderSql);
        $updateOrderStmt->bind_param("sdi", $overall_status, $statusData['avg_turnaround'], $lab_order_id);
    } else {
        // Fallback if overall_status column doesn't exist
        $updateOrderSql = "UPDATE lab_orders SET status = ?, updated_at = NOW() WHERE lab_order_id = ?";
        $updateOrderStmt = $conn->prepare($updateOrderSql);
        $updateOrderStmt->bind_param("si", $overall_status, $lab_order_id);
    }
    $updateOrderStmt->execute();

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