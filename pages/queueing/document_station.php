<?php
/**
 * Document Station Interface
 * Purpose: Dedicated document station interface for records officers to manage document issuance and document queue
 * Layout: 7-div grid system with comprehensive queue management functionality
 */

// Include employee session configuration
$root_path = dirname(dirname(dirname(__FILE__)));
require_once $root_path . '/config/session/employee_session.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header("Location: ../management/auth/employee_login.php");
    exit();
}

// Include database connection and queue management service
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';
require_once $root_path . '/utils/queue_code_formatter.php';

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];
$message = '';
$error = '';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);

// Check if role is authorized for document operations
$allowed_roles = ['records_officer', 'admin', 'nurse'];
if (!in_array(strtolower($employee_role), $allowed_roles)) {
    header("Location: ../management/" . strtolower($employee_role) . "/dashboard.php");
    exit();
}

// Get document station assignment for the current employee
$current_date = date('Y-m-d');
$document_station = null;
$can_manage_queue = false;

// Check if employee is assigned to a document station today
$assignment_query = "SELECT sch.*, s.station_name, s.station_type 
                     FROM assignment_schedules sch 
                     JOIN stations s ON sch.station_id = s.station_id 
                     WHERE sch.employee_id = ? 
                     AND s.station_type = 'document'
                     AND sch.is_active = 1
                     AND (sch.start_date <= CURDATE() AND (sch.end_date IS NULL OR sch.end_date >= CURDATE()))
                     ORDER BY sch.assigned_at DESC LIMIT 1";
$stmt = $pdo->prepare($assignment_query);
$stmt->execute([$employee_id]);
$document_station = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle station selection for multi-station support
$selected_station_id = $_GET['station_id'] ?? null;
$available_document_stations = [];

// Get all available document stations
$stations_query = "SELECT s.station_id, s.station_name, s.station_type, s.is_active 
                   FROM stations s 
                   WHERE s.station_type = 'document' AND s.is_active = 1
                   ORDER BY s.station_name";
$stmt = $pdo->prepare($stations_query);
$stmt->execute();
$available_document_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin can select any document station, others use their assigned station
if (strtolower($employee_role) === 'admin') {
    $can_manage_queue = true;
    
    // If admin specified a station via URL parameter
    if ($selected_station_id) {
        foreach ($available_document_stations as $station) {
            if ($station['station_id'] == $selected_station_id) {
                $document_station = $station;
                $document_station['assignment_id'] = null;
                $document_station['employee_id'] = $employee_id;
                break;
            }
        }
    }
    
    // If no specific station selected and no assignment, use first available
    if (!$document_station && !empty($available_document_stations)) {
        $document_station = $available_document_stations[0];
        $document_station['assignment_id'] = null;
        $document_station['employee_id'] = $employee_id;
    }
} else if ($document_station) {
    // Regular staff: use their assigned station
    $can_manage_queue = true;
} else {
    // Check if user has permission for selected station (records officers/nurses might access multiple stations)
    if ($selected_station_id && in_array(strtolower($employee_role), ['records_officer', 'nurse'])) {
        foreach ($available_document_stations as $station) {
            if ($station['station_id'] == $selected_station_id) {
                $document_station = $station;
                $document_station['assignment_id'] = null;
                $document_station['employee_id'] = $employee_id;
                $can_manage_queue = true;
                break;
            }
        }
    }
}

// Fallback: If still no access but user is authorized role and there are available stations, give access
if (!$can_manage_queue && in_array(strtolower($employee_role), ['records_officer', 'admin', 'nurse']) && !empty($available_document_stations)) {
    $can_manage_queue = true;
    if (!$document_station) {
        $document_station = $available_document_stations[0];
        $document_station['assignment_id'] = null;
        $document_station['employee_id'] = $employee_id;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (!$can_manage_queue || !$document_station) {
        echo json_encode(['success' => false, 'message' => 'Not authorized for document operations']);
        exit();
    }
    
    try {
        $action = $_POST['action'] ?? '';
        $queue_entry_id = $_POST['queue_entry_id'] ?? null;
        $station_id = $document_station['station_id'];
        
        switch ($action) {
            case 'get_queue_data':
                // Get updated queue data for document station
                $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
                $in_progress_queue = $queueService->getStationQueue($station_id, 'in_progress');
                $completed_queue = $queueService->getStationQueue($station_id, 'done', $current_date, 10);
                $skipped_queue = $queueService->getStationQueue($station_id, 'skipped');
                $queue_stats = $queueService->getStationQueueStats($station_id, $current_date);
                
                // Format queue code for display
                foreach ($waiting_queue as &$entry) {
                    $entry['display_queue_code'] = formatQueueCodeForDisplay($entry['queue_code']);
                }
                foreach ($in_progress_queue as &$entry) {
                    $entry['display_queue_code'] = formatQueueCodeForDisplay($entry['queue_code']);
                }
                foreach ($completed_queue as &$entry) {
                    $entry['display_queue_code'] = formatQueueCodeForDisplay($entry['queue_code']);
                }
                foreach ($skipped_queue as &$entry) {
                    $entry['display_queue_code'] = formatQueueCodeForDisplay($entry['queue_code']);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'waiting_queue' => $waiting_queue,
                        'in_progress_queue' => $in_progress_queue,
                        'completed_queue' => $completed_queue,
                        'skipped_queue' => $skipped_queue,
                        'stats' => $queue_stats
                    ]
                ]);
                break;
                
            case 'call_next':
                $result = $queueService->callNextPatient('document', $station_id, $employee_id);
                if ($result['success']) {
                    echo json_encode(['success' => true, 'message' => 'Patient called successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Failed to call next patient']);
                }
                break;
                
            case 'skip_patient':
                $reason = $_POST['reason'] ?? 'Patient skipped by document staff';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'skipped', 'in_progress', $employee_id, $reason);
                echo json_encode($result);
                break;
                
            case 'recall_patient':
                $result = $queueService->updateQueueStatus($queue_entry_id, 'waiting', 'skipped', $employee_id, 'Patient recalled to waiting queue');
                echo json_encode($result);
                break;
                
            case 'force_call':
                $reason = $_POST['reason'] ?? 'Force called by document staff';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'in_progress', 'waiting', $employee_id, $reason);
                echo json_encode($result);
                break;
                
            case 'end_patient_queue':
                $remarks = $_POST['remarks'] ?? 'Document processing completed';
                // Mark document queue as completed and visit as finished
                $result = $queueService->updateQueueStatus($queue_entry_id, 'done', 'in_progress', $employee_id, $remarks);
                
                if ($result['success']) {
                    // Get visit info to mark as completed
                    $visit_query = "SELECT visit_id FROM queue_entries WHERE queue_entry_id = ?";
                    $stmt = $pdo->prepare($visit_query);
                    $stmt->execute([$queue_entry_id]);
                    $visit_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($visit_data) {
                        // Mark visit as completed
                        $complete_visit_query = "UPDATE visits SET status = 'completed', completed_at = NOW() WHERE visit_id = ?";
                        $stmt = $pdo->prepare($complete_visit_query);
                        $stmt->execute([$visit_data['visit_id']]);
                        
                        echo json_encode(['success' => true, 'message' => 'Patient visit completed successfully']);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    echo json_encode($result);
                }
                break;
                
            case 'issue_document':
                $visit_id = $_POST['visit_id'] ?? null;
                $document_type = $_POST['document_type'] ?? '';
                $document_details = $_POST['document_details'] ?? '';
                
                if (!$visit_id || empty($document_type)) {
                    echo json_encode(['success' => false, 'message' => 'Visit ID and document type are required']);
                    exit();
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Record issued document
                    $issue_query = "INSERT INTO documents_issued (visit_id, document_type, document_details, 
                                   issued_by, issued_at, status) 
                                   VALUES (?, ?, ?, ?, NOW(), 'issued')";
                    $stmt = $pdo->prepare($issue_query);
                    $stmt->execute([
                        $visit_id,
                        $document_type,
                        $document_details,
                        $employee_id
                    ]);
                    
                    // Log the document issuance action
                    if ($queue_entry_id) {
                        $log_query = "INSERT INTO queue_logs (queue_entry_id, action, old_status, new_status, remarks, performed_by) 
                                     VALUES (?, 'status_changed', 'in_progress', 'in_progress', ?, ?)";
                        $stmt = $pdo->prepare($log_query);
                        $stmt->execute([$queue_entry_id, 'Document issued: ' . $document_type, $employee_id]);
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Document issued successfully']);
                } catch (Exception $e) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to issue document: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_queue_logs':
                $date = $_POST['date'] ?? date('Y-m-d');
                $station_id = $document_station['station_id'] ?? null;
                
                if ($station_id) {
                    $logs_query = "
                        SELECT 
                            ql.*,
                            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                            CONCAT(e.first_name, ' ', e.last_name) as employee_name
                        FROM queue_logs ql
                        LEFT JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
                        LEFT JOIN patients p ON qe.patient_id = p.patient_id
                        LEFT JOIN employees e ON ql.performed_by = e.employee_id
                        WHERE qe.station_id = ? AND DATE(ql.created_at) = ?
                        ORDER BY ql.created_at DESC
                        LIMIT 50
                    ";
                    
                    $stmt = $pdo->prepare($logs_query);
                    $stmt->execute([$station_id, $date]);
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode(['success' => true, 'logs' => $logs]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Station not assigned']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Get queue data for document station
$waiting_queue = [];
$in_progress_queue = [];
$completed_queue = [];
$skipped_queue = [];
$queue_stats = ['waiting_count' => 0, 'in_progress_count' => 0, 'completed_count' => 0, 'skipped_count' => 0];
$current_patient = null;

if ($document_station) {
    $station_id = $document_station['station_id'];
    
    try {
        $waiting_queue = $queueService->getStationQueue($station_id, 'waiting');
        $in_progress_queue = $queueService->getStationQueue($station_id, 'in_progress');
        $completed_queue = $queueService->getStationQueue($station_id, 'done', $current_date, 10);
        $skipped_queue = $queueService->getStationQueue($station_id, 'skipped');
        
        // Get queue statistics
        $queue_stats = $queueService->getStationQueueStats($station_id, $current_date);
        
        // Get current patient (first in progress)
        if (!empty($in_progress_queue)) {
            $current_patient = $in_progress_queue[0];
        }
        
    } catch (Exception $e) {
        $error = "Error loading queue data: " . $e->getMessage();
    }
}

// Set active page for sidebar
$activePage = 'queue_management';

// Get employee info for display
$employee_info = [
    'name' => $_SESSION['employee_name'] ?? ($_SESSION['first_name'] . ' ' . $_SESSION['last_name']),
    'role' => $_SESSION['role'],
    'employee_number' => $_SESSION['employee_number'] ?? ''
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Station - CHO Koronadal</title>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Universal Station Management Framework -->
    <script src="../../assets/js/station-manager.js"></script>
    <script src="../../assets/js/queue-sync.js"></script>
    <style>
        /* Document Station Layout - inheriting dashboard styles */

        
        .card-container {
            background: white;
            border-radius: var(--border-radius, 8px);
            box-shadow: var(--shadow, 0 2px 4px rgba(0,0,0,0.1));
            border: 1px solid var(--border, #e9ecef);
            margin-bottom: 20px;
        }
        
        .section-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            border-bottom: none;
        }
        
        .section-header h4 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 600;
        }
        
        .section-header h4 i {
            color: white;
            margin-right: 0;
        }
        
        .section-body {
            padding: 20px;
        }
        
        /* Keep existing document-specific functionality */
        
        /* Breadcrumb matching dashboard style */
        .breadcrumb {
            background: none;
            padding: 8px 0;
            margin-bottom: 15px;
            font-size: 14px;
            color: #6c757d;
        }
        
        .breadcrumb a {
            color: #007bff;
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* Page header matching dashboard style */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
            font-weight: 700;
        }
        
        .page-header h1 i {
            color: #007bff;
            margin-right: 10px;
        }
        
        .total-count {
            display: flex;
            gap: 8px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .bg-info { background: linear-gradient(135deg, #48cae4, #0096c7); }
        .bg-success { background: linear-gradient(135deg, #52b788, #2d6a4f); }
        .bg-warning { background: linear-gradient(135deg, #ffba08, #faa307); }
        
        /* Modern Station Actions Styling */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        .status-active {
            background: #27ae60;
            box-shadow: 0 0 8px rgba(39, 174, 96, 0.4);
        }
        
        .status-inactive {
            background: #e74c3c;
            box-shadow: 0 0 8px rgba(231, 76, 60, 0.4);
        }
        
        .status-text {
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(39, 174, 96, 0); }
            100% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0); }
        }
        
        .action-section {
            margin-bottom: 25px;
        }
        
        .action-section:last-child {
            margin-bottom: 0;
        }
        
        .action-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 15px 0;
            padding: 8px 12px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-left: 4px solid #007bff;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
        }
        
        .action-section-title i {
            color: #007bff;
            font-size: 16px;
        }
        
        .action-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .modern-btn {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .modern-btn:hover {
            border-color: #007bff;
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
            transform: translateY(-2px);
            text-decoration: none;
            color: #495057;
        }
        
        .btn-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 10px;
            margin-right: 15px;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .btn-nav .btn-icon {
            background: linear-gradient(135deg, #48cae4, #0096c7);
            color: white;
        }
        
        .btn-station-switch .btn-icon {
            background: linear-gradient(135deg, #ffd60a, #f77f00);
            color: white;
        }
        
        .btn-logs .btn-icon {
            background: linear-gradient(135deg, #9d4edd, #7209b7);
            color: white;
        }
        
        .btn-content {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        
        .btn-title {
            font-weight: 600;
            font-size: 15px;
            color: #2c3e50;
        }
        
        .btn-subtitle {
            font-size: 12px;
            color: #7f8c8d;
            font-weight: 400;
        }
        
        .station-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            font-size: 13px;
            color: #495057;
            margin-top: 5px;
            cursor: pointer;
        }
        
        .station-select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .quick-stat {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border: 2px solid;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .quick-stat:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .stat-waiting {
            border-color: #ffc107;
            background: linear-gradient(135deg, #fff9c4, #fff3cd);
        }
        
        .stat-active {
            border-color: #17a2b8;
            background: linear-gradient(135deg, #b8f5ff, #d1ecf1);
        }
        
        .stat-done {
            border-color: #28a745;
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
        }
        
        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            margin-right: 12px;
            font-size: 18px;
        }
        
        .stat-waiting .stat-icon {
            background: #ffc107;
            color: white;
        }
        
        .stat-active .stat-icon {
            background: #17a2b8;
            color: white;
        }
        
        .stat-done .stat-icon {
            background: #28a745;
            color: white;
        }
        
        .stat-info {
            display: flex;
            flex-direction: column;
        }
        
        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 12px;
            font-weight: 500;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Legacy action button styles for other sections */
        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            width: 100%;
        }
        
        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px 12px;
            text-align: center;
            min-height: 60px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .action-btn i {
            font-size: 20px;
            margin-bottom: 6px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-primary, .action-btn.btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        .btn-secondary, .action-btn.btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .btn-success, .action-btn.btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .btn-warning, .action-btn.btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .btn-danger, .action-btn.btn-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .btn-info, .action-btn.btn-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }
        
        /* Grid Layout */
        .parent {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(7, auto);
            grid-gap: 15px;
            margin-top: 20px;
        }
        
        .div1 { grid-area: 1 / 1 / 2 / 4; }
        .div2 { grid-area: 1 / 4 / 2 / 7; }
        .div3 { grid-area: 2 / 1 / 4 / 4; }
        .div4 { grid-area: 2 / 4 / 4 / 7; }
        .div5 { grid-area: 4 / 1 / 6 / 7; }
        .div6 { grid-area: 6 / 1 / 7 / 4; }
        .div7 { grid-area: 6 / 4 / 7 / 7; }
        
        .grid-item {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .grid-item h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
            font-size: 18px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        
        /* Station Info Styles */
        .station-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: bold;
            color: #7f8c8d;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 16px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-open {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-closed {
            background: #fadbd8;
            color: #e74c3c;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            color: white;
        }
        
        .stat-waiting { background: #3498db; }
        .stat-progress { background: #f39c12; }
        .stat-completed { background: #27ae60; }
        .stat-skipped { background: #e74c3c; }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Current Patient Card */
        .patient-card {
            border: 2px solid #3498db;
            border-radius: 8px;
            padding: 15px;
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #7f8c8d;
        }
        
        .patient-info h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 14px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
        }
        
        .detail-label {
            font-weight: bold;
            color: #7f8c8d;
        }
        
        /* Action Buttons Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .action-btn {
            padding: 12px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        /* Queue Tables */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .queue-table th,
        .queue-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .queue-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .queue-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .priority-normal { color: #2c3e50; }
        .priority-priority { color: #f39c12; font-weight: bold; }
        .priority-emergency { color: #e74c3c; font-weight: bold; }
        
        .queue-actions {
            display: flex;
            gap: 5px;
        }
        
        .queue-actions button {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group textarea {
            padding: 8px 12px;
            border: 1px solid #bdc3c7;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }
        
        .alert-error {
            background: #fadbd8;
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        
        .alert-info {
            background: #d6eaf8;
            color: #3498db;
            border-left: 4px solid #3498db;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            margin-left: auto;
        }
        
        /* Action Badges for Logs */
        .action-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .action-created { background: #d6eaf8; color: #3498db; }
        .action-status_changed { background: #fdeaa7; color: #f39c12; }
        .action-moved { background: #d5f4e6; color: #27ae60; }
        .action-reinstated { background: #ebdef0; color: #8e44ad; }
        .action-cancelled { background: #fadbd8; color: #e74c3c; }
        .action-skipped { background: #fcf3cf; color: #d68910; }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .parent {
                grid-template-columns: repeat(4, 1fr);
                grid-template-rows: repeat(8, auto);
            }
            
            .div1 { grid-area: 1 / 1 / 2 / 3; }
            .div2 { grid-area: 1 / 3 / 2 / 5; }
            .div3 { grid-area: 2 / 1 / 4 / 5; }
            .div4 { grid-area: 4 / 1 / 6 / 5; }
            .div5 { grid-area: 6 / 1 / 7 / 5; }
            .div6 { grid-area: 7 / 1 / 8 / 3; }
            .div7 { grid-area: 7 / 3 / 8 / 5; }
        }
        
        @media (max-width: 768px) {
            .parent {
                grid-template-columns: 1fr;
                grid-template-rows: repeat(7, auto);
            }
            
            .div1, .div2, .div3, .div4, .div5, .div6, .div7 {
                grid-column: 1;
            }
            
            .div1 { grid-row: 1; }
            .div2 { grid-row: 2; }
            .div3 { grid-row: 3; }
            .div4 { grid-row: 4; }
            .div5 { grid-row: 5; }
            .div6 { grid-row: 6; }
            .div7 { grid-row: 7; }
            
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            /* Modern button responsive styles */
            .action-row {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
            }
            
            .modern-btn {
                padding: 14px 16px;
            }
            
            .btn-icon {
                width: 40px;
                height: 40px;
                margin-right: 12px;
                font-size: 18px;
            }
            
            .btn-title {
                font-size: 14px;
            }
            
            .btn-subtitle {
                font-size: 11px;
            }
            
            .quick-stat {
                padding: 12px;
            }
            
            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 16px;
                margin-right: 10px;
            }
            
            .stat-number {
                font-size: 18px;
            }
            
            .stat-label {
                font-size: 11px;
            }
            
            .action-section-title {
                font-size: 13px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <section class="homepage">
        <!-- Include Sidebar -->
        <?php 
        $defaults = [
            'name' => $employee_info['name'],
            'employee_number' => $employee_info['employee_number']
        ];
        
        // Dynamically include appropriate sidebar based on user role
        $sidebar_file = '../../includes/sidebar_' . strtolower($employee_role) . '.php';
        if (file_exists($sidebar_file)) {
            include $sidebar_file;
        } else {
            // Fallback to admin sidebar if role-specific sidebar doesn't exist
            include '../../includes/sidebar_admin.php';
        }
        ?>
        
        <div class="queue-dashboard-container">
            <div class="content-area">
                <!-- Breadcrumb Navigation - matching dashboard style -->
                <div class="breadcrumb" style="margin-top: 50px;">
                    <a href="dashboard.php"><i class="fas fa-home"></i> Queue Management</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Document Station</span>
                </div>

                <!-- Page Header with Status Badges - matching dashboard style -->
                <div class="page-header">
                    <h1><i class="fas fa-file-alt"></i> Document Station</h1>
                    <div class="total-count">
                        <span class="badge bg-info"><?php echo htmlspecialchars($document_station['station_name'] ?? 'Document Station'); ?></span>
                        <span class="badge bg-success"><?php echo $queue_stats['waiting_count']; ?> Waiting</span>
                        <span class="badge bg-warning"><?php echo $queue_stats['in_progress_count']; ?> In Progress</span>
                    </div>
                </div>
                <!-- Station Navigation & Actions -->
                <div class="card-container">
                    <div class="section-header">
                        <h4><i class="fas fa-tools"></i> Navigation & Station Tools</h4>
                        <div class="station-status">
                            <span class="status-indicator <?php echo $can_manage_queue ? 'status-active' : 'status-inactive'; ?>"></span>
                            <span class="status-text"><?php echo $can_manage_queue ? 'Station Active' : 'Station Inactive'; ?></span>
                        </div>
                    </div>
                    <div class="section-body">
                        
                        <!-- Navigation & Station Management -->
                        <div class="action-section">
                            <h5 class="action-section-title"><i class="fas fa-compass"></i> Navigation & Management</h5>
                            <div class="action-row">
                                <a href="dashboard.php" class="modern-btn btn-nav">
                                    <div class="btn-icon">
                                        <i class="fas fa-tachometer-alt"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Queue Dashboard</span>
                                        <span class="btn-subtitle">Main queue overview</span>
                                    </div>
                                </a>
                                
                                <a href="#" class="modern-btn btn-nav" onclick="openPublicDisplay(); return false;">
                                    <div class="btn-icon">
                                        <i class="fas fa-desktop"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title" style="text-align: left;">Open Waiting Display</span>
                                        <span class="btn-subtitle" style="text-align: left;">Launch the public queue view for this station</span>
                                    </div>
                                </a>
                                
                                <?php if (count($available_document_stations) > 1): ?>
                                <div class="modern-btn btn-station-switch">
                                    <div class="btn-icon">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Switch Station</span>
                                        <select id="stationSelector" onchange="switchStation(this.value)" class="station-select">
                                            <option value="">Select Station...</option>
                                            <?php foreach ($available_document_stations as $station): ?>
                                            <option value="<?php echo $station['station_id']; ?>" 
                                                    <?php echo ($document_station && $station['station_id'] == $document_station['station_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($station['station_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <button class="modern-btn btn-logs" onclick="openQueueLogsModal()">
                                    <div class="btn-icon">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title">Queue Logs</span>
                                        <span class="btn-subtitle">Activity history</span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="action-section">
                            <h5 class="action-section-title"><i class="fas fa-chart-line"></i> Quick Stats</h5>
                            <div class="stats-row">
                                <div class="quick-stat stat-waiting">
                                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $queue_stats['waiting_count']; ?></span>
                                        <span class="stat-label">Waiting</span>
                                    </div>
                                </div>
                                <div class="quick-stat stat-active">
                                    <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $queue_stats['in_progress_count']; ?></span>
                                        <span class="stat-label">Active</span>
                                    </div>
                                </div>
                                <div class="quick-stat stat-done">
                                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="stat-info">
                                        <span class="stat-number"><?php echo $queue_stats['completed_count']; ?></span>
                                        <span class="stat-label">Completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                    <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
                </div>
                <?php endif; ?>

                <!-- Debug Information (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-info">
                    <strong>Debug Info:</strong><br>
                    Employee Role: <?php echo htmlspecialchars($employee_role); ?><br>
                    Can Manage Queue: <?php echo $can_manage_queue ? 'Yes' : 'No'; ?><br>
                    Document Station: <?php echo $document_station ? 'Set' : 'Not Set'; ?><br>
                    Available Stations: <?php echo count($available_document_stations); ?><br>
                    Selected Station ID: <?php echo htmlspecialchars($selected_station_id ?? 'None'); ?>
                </div>
                <?php endif; ?>

                <?php if (!$can_manage_queue): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>You are not assigned to a document station today. Please contact your supervisor.</span>
                    <?php if (strtolower($employee_role) === 'admin'): ?>
                    <br><small>Note: As an admin, you can still access the interface for monitoring purposes.</small>
                    <?php endif; ?>
                    <br><small><a href="?debug=1">Show debug info</a></small>
                </div>
                <?php else: ?>

                <!-- Main Grid Layout -->
                <div class="parent">
                    <!-- Div 1: Station Info -->
                    <div class="div1 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-file-alt"></i> Station Information</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <div class="station-info">
                            <div class="info-item">
                                <span class="info-label">Station Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($document_station['station_name'] ?? 'Document Station'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status</span>
                                <span class="status-badge status-open">OPEN</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Assigned Staff</span>
                                <span class="info-value"><?php echo htmlspecialchars($employee_info['name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Service Type</span>
                                <span class="info-value">Document Issuance</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Shift Hours</span>
                                <span class="info-value">08:00 - 17:00</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date</span>
                                <span class="info-value"><?php echo date('F j, Y'); ?></span>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Div 2: Stats Grid -->
                    <div class="div2 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-chart-bar"></i> Queue Statistics</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <div class="stats-grid">
                            <div class="stat-card stat-waiting">
                                <div class="stat-number"><?php echo $queue_stats['waiting_count'] ?? 0; ?></div>
                                <div class="stat-label">Waiting</div>
                            </div>
                            <div class="stat-card stat-progress">
                                <div class="stat-number"><?php echo $queue_stats['in_progress_count'] ?? 0; ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                            <div class="stat-card stat-completed">
                                <div class="stat-number"><?php echo $queue_stats['completed_count'] ?? 0; ?></div>
                                <div class="stat-label">Completed Today</div>
                            </div>
                            <div class="stat-card stat-skipped">
                                <div class="stat-number"><?php echo $queue_stats['skipped_count'] ?? 0; ?></div>
                                <div class="stat-label">Skipped</div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Div 3: Current Patient Details -->
                    <div class="div3 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-user-injured"></i> Current Patient</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if ($current_patient): ?>
                        <div class="patient-card">
                            <div class="patient-header">
                                <div class="patient-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="patient-info">
                                    <h4><?php echo htmlspecialchars($current_patient['patient_name']); ?></h4>
                                    <div style="color: #7f8c8d; font-size: 14px;">
                                        Queue: <?php echo htmlspecialchars($current_patient['queue_code'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="patient-details">
                                <div class="detail-item">
                                    <span class="detail-label">Patient ID:</span>
                                    <span><?php echo htmlspecialchars($current_patient['patient_id']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">DOB:</span>
                                    <span><?php echo htmlspecialchars($current_patient['date_of_birth'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Barangay:</span>
                                    <span><?php echo htmlspecialchars($current_patient['barangay'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Priority:</span>
                                    <span class="priority-<?php echo $current_patient['priority_level'] ?? 'normal'; ?>">
                                        <?php echo strtoupper($current_patient['priority_level'] ?? 'NORMAL'); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Service:</span>
                                    <span><?php echo htmlspecialchars($current_patient['service_name'] ?? 'General'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Time Started:</span>
                                    <span><?php echo $current_patient['time_started'] ? date('H:i', strtotime($current_patient['time_started'])) : 'N/A'; ?></span>
                                </div>
                            </div>
                            
                            <!-- Document Request Summary -->
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ecf0f1;">
                                <div style="font-weight: bold; margin-bottom: 8px; color: #2c3e50;">
                                    <i class="fas fa-file-alt"></i> Document Request Summary:
                                </div>
                                <div style="font-size: 13px; color: #495057;">
                                    <?php 
                                    // Get document requests for current patient
                                    if ($current_patient['visit_id']) {
                                        $doc_query = "SELECT service_name, service_description FROM services s 
                                                     JOIN visits v ON s.service_id = v.service_id 
                                                     WHERE v.visit_id = ?";
                                        $stmt = $pdo->prepare($doc_query);
                                        $stmt->execute([$current_patient['visit_id']]);
                                        $service_info = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                        if ($service_info): ?>
                                            <div style="margin-bottom: 5px;">
                                                <strong><?php echo htmlspecialchars($service_info['service_name']); ?></strong>
                                                <div style="color: #868e96; font-size: 12px;"><?php echo htmlspecialchars($service_info['service_description'] ?? 'Standard document processing'); ?></div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #868e96;">General document request</span>
                                        <?php endif; 
                                    } else {
                                        echo '<span style="color: #868e96;">No visit data available</span>';
                                    } ?>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>No patient currently in document queue</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Div 4: Actions for Current Patient -->
                    <div class="div4 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-tools"></i> Document Actions</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <div class="actions-grid">
                            <button class="action-btn btn-danger" onclick="endPatientQueue()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-check-circle"></i> End Patient Queue
                            </button>
                            <button class="action-btn btn-primary" onclick="callNextPatient()">
                                <i class="fas fa-phone"></i> Call Next Patient to Serve
                            </button>
                            <button class="action-btn btn-warning" onclick="skipPatient()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-forward"></i> Skip Patient Queue
                            </button>
                            <button class="action-btn btn-secondary" onclick="recallPatient()" <?php echo empty($skipped_queue) ? 'disabled' : ''; ?>>
                                <i class="fas fa-undo"></i> Recall Patient Queue
                            </button>
                            <button class="action-btn btn-info" onclick="forceCallPatient()">
                                <i class="fas fa-exclamation"></i> Force Call Queue
                            </button>
                            <button class="action-btn btn-secondary" onclick="viewPatientProfile()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-user-circle"></i> View Profile
                            </button>
                        </div>
                        </div>
                    </div>

                    <!-- Div 5: Live Queued Patients -->
                    <div class="div5 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-list"></i> Document Waiting Queue</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if (!empty($waiting_queue)): ?>
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Queue Code</th>
                                    <th>Patient Name</th>
                                    <th>Priority</th>
                                    <th>Request Type</th>
                                    <th>Time In</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waiting_queue as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['queue_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                    <td class="priority-<?php echo $patient['priority_level'] ?? 'normal'; ?>">
                                        <?php echo strtoupper($patient['priority_level'] ?? 'NORMAL'); ?>
                                    </td>
                                    <td style="font-size: 12px;">
                                        <?php 
                                        // Get service/document type for this patient
                                        $service_query = "SELECT service_name FROM services s JOIN visits v ON s.service_id = v.service_id WHERE v.visit_id = ?";
                                        $stmt = $pdo->prepare($service_query);
                                        $stmt->execute([$patient['visit_id']]);
                                        $service_name = $stmt->fetchColumn();
                                        echo $service_name ? htmlspecialchars($service_name) : "General Document";
                                        ?>
                                    </td>
                                    <td><?php echo date('H:i', strtotime($patient['time_in'])); ?></td>
                                    <td class="queue-actions">
                                        <button class="btn-primary" onclick="forceCallSpecificPatient(<?php echo $patient['queue_entry_id']; ?>)">
                                            Force Call Queue
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-file-alt"></i>
                            <span>No patients in document queue</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Div 6: Skipped Queue -->
                    <div class="div6 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-user-times"></i> Skipped Patients</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if (!empty($skipped_queue)): ?>
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Queue Code</th>
                                    <th>Patient Name</th>
                                    <th>Skipped Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($skipped_queue as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['queue_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                    <td><?php echo $patient['time_started'] ? date('H:i', strtotime($patient['time_started'])) : 'N/A'; ?></td>
                                    <td class="queue-actions">
                                        <button class="btn-success" onclick="recallSpecificPatient(<?php echo $patient['queue_entry_id']; ?>)">
                                            Recall
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>No skipped patients</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Div 7: Completed Patients -->
                    <div class="div7 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-check-circle"></i> Documents Issued Today</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if (!empty($completed_queue)): ?>
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Queue Code</th>
                                    <th>Patient Name</th>
                                    <th>Document Issued</th>
                                    <th>Completed</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_queue as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['queue_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                    <td style="font-size: 11px;">
                                        <?php 
                                        // Get issued documents for this visit
                                        $doc_issued_query = "SELECT document_type FROM documents_issued WHERE visit_id = ? LIMIT 2";
                                        $stmt = $pdo->prepare($doc_issued_query);
                                        $stmt->execute([$patient['visit_id']]);
                                        $documents = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        if (!empty($documents)) {
                                            echo implode(', ', array_map('htmlspecialchars', $documents));
                                            if (count($documents) >= 2) echo '...';
                                        } else {
                                            echo 'Standard documents';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $patient['time_completed'] ? date('H:i', strtotime($patient['time_completed'])) : 'N/A'; ?></td>
                                    <td><span class="status-badge status-open">Visit Completed</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-file-alt"></i>
                            <span>No documents issued today</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>


    <!-- Queue Logs Modal -->
    <div id="queueLogsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-clipboard-list"></i> Queue Logs</h2>
                <span class="close" onclick="closeQueueLogsModal()">&times;</span>
            </div>
            <div id="queueLogsContent">
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>Loading queue logs...</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Current patient data
        const currentPatient = <?php echo json_encode($current_patient); ?>;
        const canManageQueue = <?php echo json_encode($can_manage_queue); ?>;
        
        // Public display window reference
        let publicDisplayWindow = null;
        
        // Function to open public document display in popup window
        function openPublicDisplay() {
            // Check if public display is already open
            if (publicDisplayWindow && !publicDisplayWindow.closed) {
                publicDisplayWindow.focus();
                showAlert('Public document display is already open', 'info');
                return;
            }
            
            // Calculate optimal window size for different screen sizes
            const screenWidth = window.screen.width;
            const screenHeight = window.screen.height;
            
            let windowWidth, windowHeight;
            
            // Responsive window sizing
            if (screenWidth <= 768) {
                // Mobile/small screens - fullscreen
                windowWidth = screenWidth;
                windowHeight = screenHeight;
            } else if (screenWidth <= 1024) {
                // Tablets - 90% of screen
                windowWidth = Math.floor(screenWidth * 0.9);
                windowHeight = Math.floor(screenHeight * 0.9);
            } else {
                // Desktop - 80% of screen
                windowWidth = Math.floor(screenWidth * 0.8);
                windowHeight = Math.floor(screenHeight * 0.8);
            }
            
            // Center the window
            const left = Math.floor((screenWidth - windowWidth) / 2);
            const top = Math.floor((screenHeight - windowHeight) / 2);
            
            // Window features optimized for public display
            const windowFeatures = [
                `width=${windowWidth}`,
                `height=${windowHeight}`,
                `left=${left}`,
                `top=${top}`,
                'resizable=yes',
                'scrollbars=auto',
                'toolbar=no',
                'menubar=no',
                'location=no',
                'status=no',
                'titlebar=yes'
            ].join(',');
            
            try {
                // Open the public document display window
                publicDisplayWindow = window.open(
                    'public_display_document.php',
                    'DocumentPublicDisplay',
                    windowFeatures
                );
                
                if (publicDisplayWindow) {
                    // Focus on the new window
                    publicDisplayWindow.focus();
                    
                    // Set window title after load
                    publicDisplayWindow.addEventListener('load', function() {
                        try {
                            publicDisplayWindow.document.title = 'Document Services Queue Display - CHO Koronadal';
                        } catch (e) {
                            // Cross-origin restriction, ignore
                        }
                    });
                    
                    // Handle window close event
                    publicDisplayWindow.addEventListener('beforeunload', function() {
                        console.log('Public document display window closing');
                        publicDisplayWindow = null;
                        updateDisplayButtonState(false);
                    });
                    
                    // Show success message
                    showAlert('Public document display opened successfully', 'success');
                    
                    // Update button appearance to show active state
                    updateDisplayButtonState(true);
                    
                } else {
                    throw new Error('Popup was blocked');
                }
                
            } catch (error) {
                console.error('Error opening public document display:', error);
                showAlert('Could not open public display. Please check popup settings and try again.', 'error');
            }
        }
        
        // Function to update button appearance based on display state
        function updateDisplayButtonState(isOpen) {
            const displayButton = document.querySelector('a[onclick*="openPublicDisplay"]');
            if (displayButton) {
                if (isOpen) {
                    displayButton.style.borderColor = '#28a745';
                    displayButton.style.backgroundColor = '#f8fff9';
                } else {
                    displayButton.style.borderColor = '#e9ecef';
                    displayButton.style.backgroundColor = 'white';
                }
            }
        }
        
        // Check public display status periodically
        setInterval(function() {
            if (publicDisplayWindow && publicDisplayWindow.closed) {
                publicDisplayWindow = null;
                updateDisplayButtonState(false);
            }
        }, 5000);
        
        // Function to show alert messages with enhanced DOM insertion
        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            `;
            
            // Try multiple insertion strategies for DOM compatibility
            let insertionSuccessful = false;
            
            // Strategy 1: Insert after page header
            const pageHeader = document.querySelector('.page-header, .header, h1');
            if (pageHeader && !insertionSuccessful) {
                try {
                    pageHeader.parentNode.insertBefore(alertDiv, pageHeader.nextSibling);
                    insertionSuccessful = true;
                } catch (e) {
                    console.log('Header insertion failed:', e);
                }
            }
            
            // Strategy 2: Insert at beginning of main content area
            if (!insertionSuccessful) {
                const mainContent = document.querySelector('.content, .main-content, .container-fluid, main');
                if (mainContent) {
                    try {
                        mainContent.insertBefore(alertDiv, mainContent.firstChild);
                        insertionSuccessful = true;
                    } catch (e) {
                        console.log('Main content insertion failed:', e);
                    }
                }
            }
            
            // Strategy 3: Insert at beginning of body
            if (!insertionSuccessful) {
                try {
                    document.body.insertBefore(alertDiv, document.body.firstChild);
                    insertionSuccessful = true;
                } catch (e) {
                    console.log('Body insertion failed:', e);
                }
            }
            
            // Fallback: Use console log if all DOM insertion fails
            if (!insertionSuccessful) {
                console.log(`Alert [${type}]: ${message}`);
            } else {
                // Auto-remove alert after 8 seconds
                setTimeout(() => {
                    if (alertDiv && alertDiv.parentNode) {
                        alertDiv.style.transition = 'opacity 0.5s ease';
                        alertDiv.style.opacity = '0';
                        setTimeout(() => {
                            if (alertDiv.parentNode) {
                                alertDiv.parentNode.removeChild(alertDiv);
                            }
                        }, 500);
                    }
                }, 8000);
            }
        }

        // Auto-refresh interval (30 seconds)
        setInterval(() => {
            if (canManageQueue) {
                location.reload();
            }
        }, 30000);

        // Modal functions
        function openQueueLogsModal() {
            document.getElementById('queueLogsModal').style.display = 'block';
            loadQueueLogs();
        }

        function closeQueueLogsModal() {
            document.getElementById('queueLogsModal').style.display = 'none';
        }

        function closeQueueLogsModal() {
            document.getElementById('queueLogsModal').style.display = 'none';
        }

        // Queue management functions
        function callNextPatient() {
            performQueueAction('call_next', null, 'Calling next patient...');
        }

        function skipPatient() {
            if (!currentPatient) {
                showAlert('No patient currently in document queue', 'error');
                return;
            }
            
            const reason = prompt('Reason for skipping patient:');
            if (reason !== null) {
                performQueueAction('skip_patient', currentPatient.queue_entry_id, 'Skipping patient...', { reason: reason });
            }
        }

        function recallPatient() {
            const skippedPatients = <?php echo json_encode($skipped_queue); ?>;
            if (skippedPatients.length === 0) {
                showAlert('No skipped patients to recall', 'error');
                return;
            }
            
            // For simplicity, recall the first skipped patient
            const patientToRecall = skippedPatients[0];
            performQueueAction('recall_patient', patientToRecall.queue_entry_id, 'Recalling patient...');
        }

        function recallSpecificPatient(queueEntryId) {
            performQueueAction('recall_patient', queueEntryId, 'Recalling patient...');
        }

        function forceCallPatient() {
            const waitingPatients = <?php echo json_encode($waiting_queue); ?>;
            if (waitingPatients.length === 0) {
                showAlert('No patients in waiting queue', 'error');
                return;
            }
            
            // Show selection dialog for force call
            let patientList = 'Select patient to force call:\n\n';
            waitingPatients.forEach((patient, index) => {
                patientList += `${index + 1}. ${patient.patient_name} (${patient.queue_code})\n`;
            });
            
            const selection = prompt(patientList + '\nEnter patient number:');
            if (selection !== null) {
                const patientIndex = parseInt(selection) - 1;
                if (patientIndex >= 0 && patientIndex < waitingPatients.length) {
                    const reason = prompt('Reason for force calling patient:');
                    if (reason !== null) {
                        forceCallSpecificPatient(waitingPatients[patientIndex].queue_entry_id, reason);
                    }
                } else {
                    showAlert('Invalid patient selection', 'error');
                }
            }
        }

        function forceCallSpecificPatient(queueEntryId, reason = null) {
            if (!reason) {
                reason = prompt('Reason for force calling patient:');
                if (reason === null) return;
            }
            
            performQueueAction('force_call', queueEntryId, 'Force calling patient...', { reason: reason });
        }

        function endPatientQueue() {
            if (!currentPatient) {
                showAlert('No patient currently in document queue', 'error');
                return;
            }
            
            const remarks = prompt('Remarks for document completion:') || 'Document processing completed';
            performQueueAction('end_patient_queue', currentPatient.queue_entry_id, 'Completing patient visit...', { remarks: remarks });
        }

        function issueDocument() {
            if (!currentPatient) {
                showAlert('No patient currently in document queue', 'error');
                return;
            }
            
            // Get document details
            const documentType = prompt('Document type (e.g., Medical Certificate, Referral Letter):');
            if (!documentType) {
                showAlert('Document type is required', 'error');
                return;
            }
            
            const documentDetails = prompt('Additional document details (optional):') || '';
            
            performQueueAction('issue_document', currentPatient.queue_entry_id, 'Issuing document...', { 
                visit_id: currentPatient.visit_id,
                document_type: documentType,
                document_details: documentDetails
            });
        }

        function viewPatientProfile() {
            if (!currentPatient) {
                showAlert('No patient currently in document queue', 'error');
                return;
            }
            
            // Open patient profile in new tab
            const profileUrl = `../management/admin/patient-records/view_patient.php?id=${currentPatient.patient_id}`;
            window.open(profileUrl, '_blank');
        }

        function viewReferral() {
            if (!currentPatient || !currentPatient.referral_id) {
                showAlert('No referral information available for this patient', 'error');
                return;
            }
            
            // Open referral details in new tab
            const referralUrl = `../management/admin/referrals/view_referral.php?id=${currentPatient.referral_id}`;
            window.open(referralUrl, '_blank');
        }

        // Generic queue action function
        function performQueueAction(action, queueEntryId, loadingMessage, additionalData = {}) {
            showAlert(loadingMessage, 'info');
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', action);
            if (queueEntryId) {
                formData.append('queue_entry_id', queueEntryId);
            }
            
            // Add additional data
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Action failed: ' + error.message, 'error');
            });
        }

        function loadQueueLogs() {
            const content = document.getElementById('queueLogsContent');
            content.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Loading queue logs...</div>';
            
            // Load actual queue logs via AJAX
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'get_queue_logs');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let logsHtml = '';
                    if (data.logs && data.logs.length > 0) {
                        logsHtml = `
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Action</th>
                                        <th>Patient</th>
                                        <th>Status Change</th>
                                        <th>Performed By</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        
                        data.logs.forEach(log => {
                            logsHtml += `
                                <tr>
                                    <td>${formatDateTime(log.created_at)}</td>
                                    <td><span class="action-badge action-${log.action}">${log.action.replace('_', ' ').toUpperCase()}</span></td>
                                    <td>${log.patient_name || 'N/A'}</td>
                                    <td>${log.old_status ? log.old_status + ' → ' + log.new_status : log.new_status}</td>
                                    <td>${log.employee_name || 'System'}</td>
                                    <td>${log.remarks || '-'}</td>
                                </tr>
                            `;
                        });
                        
                        logsHtml += '</tbody></table>';
                    } else {
                        logsHtml = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> No queue logs found for today.</div>';
                    }
                    
                    content.innerHTML = logsHtml;
                } else {
                    content.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Error loading logs: ${data.message}</div>`;
                }
            })
            .catch(error => {
                content.innerHTML = `<div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Failed to load queue logs: ${error.message}</div>`;
            });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }

        // Alert system
        function showAlert(message, type) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => {
                if (!alert.parentElement.classList.contains('modal-content')) {
                    alert.remove();
                }
            });
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            else if (type === 'error') icon = 'fa-exclamation-triangle';
            
            alertDiv.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            const container = document.querySelector('.homepage');
            const header = document.querySelector('.page-header');
            container.insertBefore(alertDiv, header.nextSibling);
            
            // Auto-remove success and info alerts after 5 seconds
            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    if (alertDiv.parentElement) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
        }

        // Station switching function
        function switchStation(stationId) {
            if (stationId) {
                window.location.href = `document_station.php?station_id=${stationId}`;
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
        
        // Initialize Universal Station Management Framework
        document.addEventListener('DOMContentLoaded', function() {
            // Station configuration for document station
            const stationConfig = {
                stationType: 'document',
                stationId: <?php echo json_encode($document_station['station_id'] ?? null); ?>,
                stationName: <?php echo json_encode($document_station['station_name'] ?? 'Document Station'); ?>,
                employeeId: <?php echo json_encode($employee_id); ?>,
                employeeName: <?php echo json_encode($employee_info['name'] ?? ''); ?>,
                canManageQueue: <?php echo json_encode($can_manage_queue); ?>,
                ajaxUrl: 'document_station.php',
                refreshInterval: 5000,
                nextStations: [],
                allowedActions: ['call_next', 'skip_patient', 'recall_patient', 'force_call', 'complete_patient']
            };
            
            // Initialize station manager
            if (typeof StationManager !== 'undefined') {
                window.stationManager = new StationManager(stationConfig);
                console.log('✅ Document Station Manager initialized successfully');
            } else {
                console.error('❌ StationManager class not found. Make sure station-manager.js is loaded.');
            }
            
            // Initialize queue synchronization
            if (typeof QueueSyncManager !== 'undefined') {
                window.queueSync = new QueueSyncManager();
                window.queueSync.registerStation(stationConfig.stationType, stationConfig.stationId);
                console.log('✅ Queue Synchronization Manager initialized successfully');
            } else {
                console.error('❌ QueueSyncManager class not found. Make sure queue-sync.js is loaded.');
            }
        });
    </script>
</body>
</html>