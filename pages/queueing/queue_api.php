<?php
/**
 * Queue Management REST API
 * CHO Koronadal Queue Management System
 * 
 * Purpose: Comprehensive REST API for queue operations and real-time updates
 * Access: Employee session required (role-based)
 * 
 * Endpoints:
 * - GET /queue_api.php?action=get_queue&station_id=1
 * - POST /queue_api.php with action and data
 * 
 * Supported Actions:
 * - get_queue: Retrieve queue for station
 * - call_next: Call next patient
 * - skip_patient: Skip current patient
 * - complete_patient: Mark patient as completed
 * - route_patient: Route patient to another station
 * - get_stats: Get queue statistics
 * - toggle_station: Open/close station
 */

// Set headers for JSON API
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include necessary files
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';
require_once $root_path . '/utils/queue_code_formatter.php';

// CORS headers for frontend requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

/**
 * Validate employee session and permissions
 */
function validateSession($required_roles = []) {
    if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
        sendResponse(false, 'Authentication required', null, 401);
    }
    
    if (!empty($required_roles) && !in_array(strtolower($_SESSION['role']), $required_roles)) {
        sendResponse(false, 'Insufficient permissions', null, 403);
    }
    
    return [
        'employee_id' => $_SESSION['employee_id'],
        'role' => $_SESSION['role']
    ];
}

/**
 * Get request data (supports both GET and POST)
 */
function getRequestData() {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        return $_GET;
    } else if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $json_data = json_decode($input, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
            return array_merge($_POST, $json_data);
        }
        
        return $_POST;
    }
    
    return [];
}

// Initialize queue service
try {
    $queueService = new QueueManagementService($pdo);
} catch (Exception $e) {
    sendResponse(false, 'Service initialization failed: ' . $e->getMessage(), null, 500);
}

// Get request data and validate action
$data = getRequestData();
$action = $data['action'] ?? '';

if (empty($action)) {
    sendResponse(false, 'Action parameter is required', null, 400);
}

// Validate session for all actions
$session = validateSession();
$employee_id = $session['employee_id'];
$employee_role = $session['role'];

try {
    switch ($action) {
        
        case 'get_queue':
            handleGetQueue($data);
            break;
            
        case 'get_queue_data':
            handleGetQueueData($data);
            break;
            
        case 'get_stats':
            handleGetStats($data);
            break;
            
        case 'call_next':
            handleCallNext($data);
            break;
            
        case 'skip_patient':
            handleSkipPatient($data);
            break;
            
        case 'complete_patient':
            handleCompletePatient($data);
            break;
            
        case 'route_patient':
            handleRoutePatient($data);
            break;
            
        case 'recall_patient':
            handleRecallPatient($data);
            break;
            
        case 'toggle_station':
            handleToggleStation($data);
            break;
            
        case 'get_patient_details':
            handleGetPatientDetails($data);
            break;
            
        case 'update_station_status':
            handleUpdateStationStatus($data);
            break;
            
        case 'get_queue_logs':
            handleGetQueueLogs($data);
            break;
            
        default:
            sendResponse(false, 'Invalid action: ' . $action, null, 400);
    }
    
} catch (Exception $e) {
    error_log("Queue API Error [{$action}]: " . $e->getMessage());
    sendResponse(false, 'Operation failed: ' . $e->getMessage(), null, 500);
}

/**
 * Get queue for specific station
 */
function handleGetQueue($data) {
    global $queueService, $employee_id;
    
    $station_id = $data['station_id'] ?? 0;
    
    if (!$station_id) {
        sendResponse(false, 'Station ID is required', null, 400);
    }
    
    // Verify access to station
    if (!hasStationAccess($station_id)) {
        sendResponse(false, 'Access denied to this station', null, 403);
    }
    
    $queue_data = [
        'waiting_queue' => $queueService->getStationQueue($station_id, ['waiting'], date('Y-m-d')),
        'in_progress_queue' => $queueService->getStationQueue($station_id, ['in_progress'], date('Y-m-d')),
        'skipped_queue' => $queueService->getStationQueue($station_id, ['skipped'], date('Y-m-d')),
        'completed_today' => $queueService->getStationQueue($station_id, ['completed'], date('Y-m-d')),
        'statistics' => $queueService->getStationQueueStats($station_id)
    ];
    
    sendResponse(true, 'Queue data retrieved successfully', $queue_data);
}

/**
 * Get formatted queue data for universal framework (supporting div3-div7)
 */
function handleGetQueueData($data) {
    global $queueService, $pdo;
    
    $station_id = $data['station_id'] ?? 0;
    
    if (!$station_id) {
        sendResponse(false, 'Station ID is required', null, 400);
    }
    
    // Verify access to station
    if (!hasStationAccess($station_id)) {
        sendResponse(false, 'Access denied to this station', null, 403);
    }
    
    try {
        // Get current queue data
        $waiting_queue = $queueService->getStationQueue($station_id, ['waiting'], date('Y-m-d'));
        $in_progress_queue = $queueService->getStationQueue($station_id, ['in_progress'], date('Y-m-d'));
        $skipped_queue = $queueService->getStationQueue($station_id, ['skipped'], date('Y-m-d'));
        $completed_today = $queueService->getStationQueue($station_id, ['completed'], date('Y-m-d'));
        
        // Format queue codes for display
        foreach ([$waiting_queue, $in_progress_queue, $skipped_queue, $completed_today] as &$queue) {
            foreach ($queue as &$entry) {
                if (isset($entry['queue_code'])) {
                    $entry['formatted_code'] = formatQueueCodeForDisplay($entry['queue_code']);
                }
            }
        }
        
        // Get queue statistics
        $stats = $queueService->getStationQueueStats($station_id);
        
        // Prepare response data matching universal framework expectations
        $response_data = [
            'station_id' => $station_id,
            'timestamp' => time(),
            'div3' => $waiting_queue, // Next patients
            'div4' => $in_progress_queue, // Current patients
            'div5' => $skipped_queue, // Skipped patients (for recall)
            'div6' => $completed_today, // Completed today
            'div7' => $stats, // Statistics and counters
            'queue_data' => [
                'waiting_queue' => $waiting_queue,
                'in_progress_queue' => $in_progress_queue,
                'skipped_queue' => $skipped_queue,
                'completed_today' => $completed_today,
                'statistics' => $stats
            ]
        ];
        
        sendResponse(true, 'Queue data retrieved successfully', $response_data);
        
    } catch (Exception $e) {
        error_log("Error in handleGetQueueData: " . $e->getMessage());
        sendResponse(false, 'Failed to retrieve queue data', null, 500);
    }
}

/**
 * Get queue statistics
 */
function handleGetStats($data) {
    global $queueService;
    
    $station_id = $data['station_id'] ?? null;
    $date = $data['date'] ?? date('Y-m-d');
    
    if ($station_id) {
        // Station-specific stats
        if (!hasStationAccess($station_id)) {
            sendResponse(false, 'Access denied to this station', null, 403);
        }
        
        $stats = $queueService->getStationQueueStats($station_id, $date);
    } else {
        // Overall stats (admin only)
        validateSession(['admin']);
        $stats = $queueService->getQueueStatistics($date);
    }
    
    sendResponse(true, 'Statistics retrieved successfully', $stats);
}

/**
 * Call next patient in queue
 */
function handleCallNext($data) {
    global $queueService, $employee_id;
    
    $station_id = $data['station_id'] ?? 0;
    
    if (!$station_id) {
        sendResponse(false, 'Station ID is required', null, 400);
    }
    
    if (!hasStationAccess($station_id, true)) {
        sendResponse(false, 'Access denied for queue management on this station', null, 403);
    }
    
    // Get station type first
    global $pdo;
    $stmt = $pdo->prepare("SELECT station_type FROM stations WHERE station_id = ?");
    $stmt->execute([$station_id]);
    $station_type = $stmt->fetchColumn();
    
    if (!$station_type) {
        sendResponse(false, 'Station not found', null, 404);
    }
    
    $result = $queueService->callNextPatient($station_type, $station_id, $employee_id);
    
    if ($result) {
        sendResponse(true, 'Next patient called successfully', $result);
    } else {
        sendResponse(false, 'No patients available in queue');
    }
}

/**
 * Skip current patient
 */
function handleSkipPatient($data) {
    global $queueService, $employee_id;
    
    $queue_entry_id = $data['queue_entry_id'] ?? 0;
    $reason = $data['reason'] ?? 'Patient unavailable';
    
    if (!$queue_entry_id) {
        sendResponse(false, 'Queue entry ID is required', null, 400);
    }
    
    // Verify access to queue entry
    if (!hasQueueEntryAccess($queue_entry_id)) {
        sendResponse(false, 'Access denied to this queue entry', null, 403);
    }
    
    // For skip patient, we'll update the status directly since there's no dedicated method
    global $pdo;
    $pdo->beginTransaction();
    
    try {
        // Update queue entry status to skipped
        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'skipped', updated_at = NOW() WHERE queue_entry_id = ?");
        $stmt->execute([$queue_entry_id]);
        
        // Log the action
        $log_stmt = $pdo->prepare("
            INSERT INTO queue_logs (queue_entry_id, action, employee_id, notes, created_at) 
            VALUES (?, 'skip_patient', ?, ?, NOW())
        ");
        $log_stmt->execute([$queue_entry_id, $employee_id, $reason]);
        
        $pdo->commit();
        $result = ['queue_entry_id' => $queue_entry_id, 'status' => 'skipped', 'reason' => $reason];
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
    if ($result) {
        sendResponse(true, 'Patient skipped successfully', $result);
    } else {
        sendResponse(false, 'Failed to skip patient');
    }
}

/**
 * Complete patient service
 */
function handleCompletePatient($data) {
    global $queueService, $employee_id;
    
    $queue_entry_id = $data['queue_entry_id'] ?? 0;
    $notes = $data['notes'] ?? '';
    
    if (!$queue_entry_id) {
        sendResponse(false, 'Queue entry ID is required', null, 400);
    }
    
    if (!hasQueueEntryAccess($queue_entry_id)) {
        sendResponse(false, 'Access denied to this queue entry', null, 403);
    }
    
    $result = $queueService->completePatientVisit($queue_entry_id, $employee_id, $notes);
    
    if ($result) {
        sendResponse(true, 'Patient completed successfully', $result);
    } else {
        sendResponse(false, 'Failed to complete patient');
    }
}

/**
 * Route patient to another station
 */
function handleRoutePatient($data) {
    global $queueService, $employee_id;
    
    $queue_entry_id = $data['queue_entry_id'] ?? 0;
    $to_station_type = $data['to_station_type'] ?? '';
    $notes = $data['notes'] ?? '';
    
    if (!$queue_entry_id || !$to_station_type) {
        sendResponse(false, 'Queue entry ID and destination station type are required', null, 400);
    }
    
    if (!hasQueueEntryAccess($queue_entry_id)) {
        sendResponse(false, 'Access denied to this queue entry', null, 403);
    }
    
    $result = $queueService->routePatientToStation($queue_entry_id, $to_station_type, $employee_id, $notes);
    
    if ($result) {
        sendResponse(true, 'Patient routed successfully', $result);
    } else {
        sendResponse(false, 'Failed to route patient');
    }
}

/**
 * Recall skipped patient
 */
function handleRecallPatient($data) {
    global $queueService, $employee_id;
    
    $queue_entry_id = $data['queue_entry_id'] ?? 0;
    
    if (!$queue_entry_id) {
        sendResponse(false, 'Queue entry ID is required', null, 400);
    }
    
    if (!hasQueueEntryAccess($queue_entry_id)) {
        sendResponse(false, 'Access denied to this queue entry', null, 403);
    }
    
    // For recall patient, we'll update the status directly since there's no dedicated method
    global $pdo;
    $pdo->beginTransaction();
    
    try {
        // Update queue entry status back to waiting
        $stmt = $pdo->prepare("UPDATE queue_entries SET status = 'waiting', updated_at = NOW() WHERE queue_entry_id = ?");
        $stmt->execute([$queue_entry_id]);
        
        // Log the action
        $log_stmt = $pdo->prepare("
            INSERT INTO queue_logs (queue_entry_id, action, employee_id, notes, created_at) 
            VALUES (?, 'recall_patient', ?, 'Patient recalled from skipped queue', NOW())
        ");
        $log_stmt->execute([$queue_entry_id, $employee_id]);
        
        $pdo->commit();
        $result = ['queue_entry_id' => $queue_entry_id, 'status' => 'waiting'];
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
    
    if ($result) {
        sendResponse(true, 'Patient recalled successfully', $result);
    } else {
        sendResponse(false, 'Failed to recall patient');
    }
}

/**
 * Toggle station open/closed status
 */
function handleToggleStation($data) {
    global $pdo, $employee_id;
    
    $station_id = $data['station_id'] ?? 0;
    
    if (!$station_id) {
        sendResponse(false, 'Station ID is required', null, 400);
    }
    
    if (!hasStationAccess($station_id, true)) {
        sendResponse(false, 'Access denied for station management', null, 403);
    }
    
    // Get current status
    $stmt = $pdo->prepare("SELECT is_open, station_name FROM stations WHERE station_id = ? AND facility_id = 1");
    $stmt->execute([$station_id]);
    $station = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$station) {
        sendResponse(false, 'Station not found', null, 404);
    }
    
    // Toggle status
    $new_status = $station['is_open'] ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE stations SET is_open = ?, updated_at = NOW() WHERE station_id = ?");
    $stmt->execute([$new_status, $station_id]);
    
    $action_text = $new_status ? 'opened' : 'closed';
    
    sendResponse(true, "Station {$action_text} successfully", [
        'station_id' => $station_id,
        'station_name' => $station['station_name'],
        'is_open' => $new_status,
        'action' => $action_text
    ]);
}

/**
 * Get detailed patient information
 */
function handleGetPatientDetails($data) {
    global $pdo;
    
    $patient_id = $data['patient_id'] ?? 0;
    $appointment_id = $data['appointment_id'] ?? 0;
    
    if (!$patient_id) {
        sendResponse(false, 'Patient ID is required', null, 400);
    }
    
    try {
        // Get patient details
        $stmt = $pdo->prepare("
            SELECT p.*, b.barangay_name,
                   CASE 
                       WHEN p.isPWD = 1 THEN 'PWD'
                       WHEN p.isSenior = 1 THEN 'Senior'
                       ELSE 'Normal'
                   END as priority_status
            FROM patients p
            LEFT JOIN barangays b ON p.barangay_id = b.barangay_id
            WHERE p.patient_id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            sendResponse(false, 'Patient not found', null, 404);
        }
        
        $response_data = ['patient' => $patient];
        
        // Get appointment details if provided
        if ($appointment_id) {
            $stmt = $pdo->prepare("
                SELECT a.*, s.service_name, r.referral_reason, r.referred_by
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN referrals r ON a.referral_id = r.referral_id
                WHERE a.appointment_id = ? AND a.patient_id = ?
            ");
            $stmt->execute([$appointment_id, $patient_id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $response_data['appointment'] = $appointment;
        }
        
        sendResponse(true, 'Patient details retrieved successfully', $response_data);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to retrieve patient details: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Update station status and assignments
 */
function handleUpdateStationStatus($data) {
    global $pdo;
    
    // Admin only
    validateSession(['admin']);
    
    $updates = $data['updates'] ?? [];
    
    if (empty($updates)) {
        sendResponse(false, 'No updates provided', null, 400);
    }
    
    $pdo->beginTransaction();
    
    try {
        foreach ($updates as $update) {
            $station_id = $update['station_id'] ?? 0;
            $is_open = $update['is_open'] ?? 0;
            
            if ($station_id) {
                $stmt = $pdo->prepare("UPDATE stations SET is_open = ?, updated_at = NOW() WHERE station_id = ? AND facility_id = 1");
                $stmt->execute([$is_open, $station_id]);
            }
        }
        
        $pdo->commit();
        sendResponse(true, 'Station statuses updated successfully');
        
    } catch (Exception $e) {
        $pdo->rollback();
        sendResponse(false, 'Failed to update station statuses: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get queue logs with filtering
 */
function handleGetQueueLogs($data) {
    global $pdo;
    
    // Admin only for logs
    validateSession(['admin']);
    
    $date_from = $data['date_from'] ?? date('Y-m-d');
    $date_to = $data['date_to'] ?? date('Y-m-d');
    $station_id = $data['station_id'] ?? null;
    $action_type = $data['action_type'] ?? null;
    $page = max(1, intval($data['page'] ?? 1));
    $limit = min(100, max(10, intval($data['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    try {
        $where_conditions = ["DATE(ql.created_at) BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];
        
        if ($station_id) {
            $where_conditions[] = "ql.station_id = ?";
            $params[] = $station_id;
        }
        
        if ($action_type) {
            $where_conditions[] = "ql.action = ?";
            $params[] = $action_type;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $count_query = "
            SELECT COUNT(*) as total
            FROM queue_logs ql
            WHERE {$where_clause}
        ";
        
        $stmt = $pdo->prepare($count_query);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();
        
        // Get paginated results
        $query = "
            SELECT ql.*, s.station_name, e.last_name as employee_name,
                   p.first_name, p.last_name as patient_last_name
            FROM queue_logs ql
            LEFT JOIN stations s ON ql.station_id = s.station_id
            LEFT JOIN employees e ON ql.employee_id = e.employee_id
            LEFT JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
            LEFT JOIN patients p ON qe.patient_id = p.patient_id
            WHERE {$where_clause}
            ORDER BY ql.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Queue logs retrieved successfully', [
            'logs' => $logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        sendResponse(false, 'Failed to retrieve queue logs: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Check if employee has access to station
 */
function hasStationAccess($station_id, $manage_required = false) {
    global $pdo, $employee_id, $employee_role;
    
    // Admin has access to all stations
    if (strtolower($employee_role) === 'admin') {
        return true;
    }
    
    if (!$manage_required) {
        // View access - check if assigned or role matches station type
        $stmt = $pdo->prepare("
            SELECT s.station_type, 
                   COUNT(asg.schedule_id) as is_assigned
            FROM stations s
            LEFT JOIN assignment_schedules asg ON s.station_id = asg.station_id 
                AND asg.employee_id = ? 
                AND asg.start_date <= CURDATE() 
                AND (asg.end_date IS NULL OR asg.end_date >= CURDATE()) 
                AND asg.is_active = 1
            WHERE s.station_id = ?
            GROUP BY s.station_id, s.station_type
        ");
        $stmt->execute([$employee_id, $station_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) return false;
        
        // Check if assigned or role matches
        return ($result['is_assigned'] > 0) || roleMatchesStationType($employee_role, $result['station_type']);
    } else {
        // Management access - must be assigned
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as is_assigned
            FROM assignment_schedules asg
            JOIN stations s ON asg.station_id = s.station_id
            WHERE asg.station_id = ? AND asg.employee_id = ? 
                AND asg.start_date <= CURDATE() 
                AND (asg.end_date IS NULL OR asg.end_date >= CURDATE()) 
                AND asg.is_active = 1
        ");
        $stmt->execute([$station_id, $employee_id]);
        
        return $stmt->fetchColumn() > 0;
    }
}

/**
 * Check if employee has access to queue entry
 */
function hasQueueEntryAccess($queue_entry_id) {
    global $pdo, $employee_id, $employee_role;
    
    // Admin has access to all queue entries
    if (strtolower($employee_role) === 'admin') {
        return true;
    }
    
    $stmt = $pdo->prepare("
        SELECT qe.station_id
        FROM queue_entries qe
        WHERE qe.queue_entry_id = ?
    ");
    $stmt->execute([$queue_entry_id]);
    $station_id = $stmt->fetchColumn();
    
    return $station_id ? hasStationAccess($station_id, true) : false;
}

/**
 * Check if role matches station type
 */
function roleMatchesStationType($role, $station_type) {
    $role_mappings = [
        'doctor' => ['consultation', 'triage'],
        'nurse' => ['triage', 'consultation'],
        'pharmacist' => ['pharmacy'],
        'laboratory_tech' => ['lab'],
        'cashier' => ['billing'],
        'records_officer' => ['checkin', 'document'],
        'bhw' => ['checkin'],
        'dho' => ['checkin']
    ];
    
    $allowed_types = $role_mappings[strtolower($role)] ?? [];
    return in_array($station_type, $allowed_types);
}

?>