<?php
/**
 * Queue Management API
 * 
 * This endpoint handles queue status updates performed by staff members.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration (assuming staff use employee session)
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// Check if user is logged in as employee
if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Employee login required']);
    exit();
}

// Database connection
require_once $root_path . '/config/db.php';

// Include queue management service
require_once $root_path . '/utils/queue_management_service.php';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$employee_id = $_SESSION['employee_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $queue_service = new QueueManagementService($conn);
    
    switch ($method) {
        case 'GET':
            handleGetRequest($queue_service);
            break;
        case 'POST':
            handlePostRequest($queue_service, $employee_id);
            break;
        case 'PUT':
            handlePutRequest($queue_service, $employee_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Handle GET requests - fetch queue information
 */
function handleGetRequest($queue_service) {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'queue_list':
            $queue_type = $_GET['queue_type'] ?? 'consultation';
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $result = $queue_service->getQueueByTypeAndDate($queue_type, $date);
            echo json_encode($result);
            break;
            
        case 'statistics':
            $date = $_GET['date'] ?? date('Y-m-d');
            
            $result = $queue_service->getQueueStatistics($date);
            echo json_encode($result);
            break;
            
        case 'appointment_queue':
            $appointment_id = (int)($_GET['appointment_id'] ?? 0);
            
            if ($appointment_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
                return;
            }
            
            $result = $queue_service->getQueueInfoByAppointment($appointment_id);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Handle POST requests - create new queue entries (if needed)
 */
function handlePostRequest($queue_service, $employee_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'create_queue':
            $appointment_id = (int)($input['appointment_id'] ?? 0);
            $patient_id = (int)($input['patient_id'] ?? 0);
            $service_id = (int)($input['service_id'] ?? 0);
            $queue_type = $input['queue_type'] ?? 'consultation';
            $priority_level = $input['priority_level'] ?? 'normal';
            
            if ($appointment_id <= 0 || $patient_id <= 0 || $service_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }
            
            $result = $queue_service->createQueueEntry(
                $appointment_id, $patient_id, $service_id, 
                $queue_type, $priority_level, $employee_id
            );
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

/**
 * Handle PUT requests - update queue status
 */
function handlePutRequest($queue_service, $employee_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_status':
            $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
            $new_status = $input['new_status'] ?? '';
            $old_status = $input['old_status'] ?? '';
            $remarks = $input['remarks'] ?? null;
            
            if ($queue_entry_id <= 0 || empty($new_status)) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                return;
            }
            
            // Validate status values
            $valid_statuses = ['waiting', 'in_progress', 'skipped', 'done', 'cancelled', 'no_show'];
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status']);
                return;
            }
            
            $result = $queue_service->updateQueueStatus(
                $queue_entry_id, $new_status, $old_status, $employee_id, $remarks
            );
            echo json_encode($result);
            break;
            
        case 'reinstate':
            $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
            $remarks = $input['remarks'] ?? 'Patient reinstated by staff';
            
            if ($queue_entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Missing queue entry ID']);
                return;
            }
            
            $result = $queue_service->reinstateQueueEntry($queue_entry_id, $employee_id, $remarks);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

$conn->close();
?>