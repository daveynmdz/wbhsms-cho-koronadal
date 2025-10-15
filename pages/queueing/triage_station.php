<?php
/**
 * Triage Station Interface
 * Purpose: Dedicated triage station interface for nurses to manage patient vitals and triage queue
 * Layout: 7-div grid system with comprehensive queue management functionality
 */

// Include employee session configuration
require_once __DIR__ . '/station_path_helper.php';
require_once $root_path . '/config/session/employee_session.php';

// Check if user is logged in
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header("Location: ../management/auth/employee_login.php");
    exit();
}

// Include database connection and queue management service
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];
$message = '';
$error = '';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);

// Check if role is authorized for triage operations
$allowed_roles = ['nurse', 'admin', 'doctor'];
if (!in_array(strtolower($employee_role), $allowed_roles)) {
    header("Location: ../management/" . strtolower($employee_role) . "/dashboard.php");
    exit();
}

// Get triage station assignment for the current employee
$current_date = date('Y-m-d');
$triage_station = null;
$can_manage_queue = false;

// Check if employee is assigned to a triage station today
$assignment_query = "SELECT sch.*, s.station_name, s.station_type 
                     FROM assignment_schedules sch 
                     JOIN stations s ON sch.station_id = s.station_id 
                     WHERE sch.employee_id = ? 
                     AND s.station_type = 'triage'
                     AND sch.is_active = 1
                     AND (sch.start_date <= CURDATE() AND (sch.end_date IS NULL OR sch.end_date >= CURDATE()))
                     ORDER BY sch.assigned_at DESC LIMIT 1";
$stmt = $pdo->prepare($assignment_query);
$stmt->execute([$employee_id]);
$triage_station = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle station selection for multi-station support
$selected_station_id = $_GET['station_id'] ?? null;
$available_triage_stations = [];

// Get all available triage stations
$stations_query = "SELECT s.station_id, s.station_name, s.station_type, s.is_active 
                   FROM stations s 
                   WHERE s.station_type = 'triage' AND s.is_active = 1
                   ORDER BY s.station_name";
$stmt = $pdo->prepare($stations_query);
$stmt->execute();
$available_triage_stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin can select any triage station, others use their assigned station
if (strtolower($employee_role) === 'admin') {
    $can_manage_queue = true;
    
    // If admin specified a station via URL parameter
    if ($selected_station_id) {
        foreach ($available_triage_stations as $station) {
            if ($station['station_id'] == $selected_station_id) {
                $triage_station = $station;
                $triage_station['assignment_id'] = null;
                $triage_station['employee_id'] = $employee_id;
                break;
            }
        }
    }
    
    // If no specific station selected and no assignment, use first available
    if (!$triage_station && !empty($available_triage_stations)) {
        $triage_station = $available_triage_stations[0];
        $triage_station['assignment_id'] = null;
        $triage_station['employee_id'] = $employee_id;
    }
} else if ($triage_station) {
    // Regular staff: use their assigned station
    $can_manage_queue = true;
} else {
    // Check if user has permission for selected station (doctors/nurses might access multiple stations)
    if ($selected_station_id && in_array(strtolower($employee_role), ['doctor', 'nurse'])) {
        foreach ($available_triage_stations as $station) {
            if ($station['station_id'] == $selected_station_id) {
                $triage_station = $station;
                $triage_station['assignment_id'] = null;
                $triage_station['employee_id'] = $employee_id;
                $can_manage_queue = true;
                break;
            }
        }
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    if (!$can_manage_queue || !$triage_station) {
        echo json_encode(['success' => false, 'message' => 'Not authorized for triage operations']);
        exit();
    }
    
    try {
        $action = $_POST['action'] ?? '';
        $queue_entry_id = $_POST['queue_entry_id'] ?? null;
        $station_id = $triage_station['station_id'];
        
        switch ($action) {
            case 'get_queue_data':
                // Return current queue data for frontend updates
                $current_patient = null;
                $waiting_queue = [];
                $in_progress_queue = [];
                $completed_queue = [];
                $skipped_queue = [];
                
                // Get current patient
                $current_query = "SELECT qe.*, p.patient_id, p.first_name, p.last_name,
                                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                p.date_of_birth, p.barangay, v.priority_level, s.name as service_name
                                FROM queue_entries qe
                                JOIN visits v ON qe.visit_id = v.visit_id
                                JOIN patients p ON v.patient_id = p.patient_id
                                LEFT JOIN services s ON v.service_id = s.service_id
                                WHERE qe.station_id = ? AND qe.status = 'in_progress' 
                                AND DATE(qe.time_in) = CURDATE()
                                ORDER BY qe.time_started DESC LIMIT 1";
                $stmt = $pdo->prepare($current_query);
                $stmt->execute([$station_id]);
                $current_patient = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get waiting queue
                $waiting_query = "SELECT qe.*, p.patient_id, p.first_name, p.last_name,
                                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                p.date_of_birth, p.barangay, v.priority_level, s.name as service_name
                                FROM queue_entries qe
                                JOIN visits v ON qe.visit_id = v.visit_id
                                JOIN patients p ON v.patient_id = p.patient_id
                                LEFT JOIN services s ON v.service_id = s.service_id
                                WHERE qe.station_id = ? AND qe.status = 'waiting' 
                                AND DATE(qe.time_in) = CURDATE()
                                ORDER BY v.priority_level DESC, qe.time_in ASC";
                $stmt = $pdo->prepare($waiting_query);
                $stmt->execute([$station_id]);
                $waiting_queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get skipped queue
                $skipped_query = "SELECT qe.*, p.patient_id, p.first_name, p.last_name,
                                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                p.date_of_birth, p.barangay, v.priority_level, s.name as service_name
                                FROM queue_entries qe
                                JOIN visits v ON qe.visit_id = v.visit_id
                                JOIN patients p ON v.patient_id = p.patient_id
                                LEFT JOIN services s ON v.service_id = s.service_id
                                WHERE qe.station_id = ? AND qe.status = 'skipped' 
                                AND DATE(qe.time_in) = CURDATE()
                                ORDER BY qe.time_started DESC";
                $stmt = $pdo->prepare($skipped_query);
                $stmt->execute([$station_id]);
                $skipped_queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get completed queue
                $completed_query = "SELECT qe.*, p.patient_id, p.first_name, p.last_name,
                                  CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                  p.date_of_birth, p.barangay, v.priority_level, s.name as service_name
                                  FROM queue_entries qe
                                  JOIN visits v ON qe.visit_id = v.visit_id
                                  JOIN patients p ON v.patient_id = p.patient_id
                                  LEFT JOIN services s ON v.service_id = s.service_id
                                  WHERE qe.station_id = ? AND qe.status = 'completed' 
                                  AND DATE(qe.time_in) = CURDATE()
                                  ORDER BY qe.time_completed DESC LIMIT 20";
                $stmt = $pdo->prepare($completed_query);
                $stmt->execute([$station_id]);
                $completed_queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'current_patient' => $current_patient,
                        'waiting_queue' => $waiting_queue,
                        'skipped_queue' => $skipped_queue,
                        'completed_queue' => $completed_queue,
                        'in_progress_queue' => $in_progress_queue
                    ]
                ]);
                break;
                
            case 'call_next':
                $result = $queueService->callNextPatient('triage', $station_id, $employee_id);
                if ($result['success']) {
                    echo json_encode(['success' => true, 'message' => 'Patient called successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => $result['error'] ?? 'Failed to call next patient']);
                }
                break;
                
            case 'skip_patient':
                if (!$queue_entry_id) {
                    echo json_encode(['success' => false, 'message' => 'Queue entry ID required']);
                    break;
                }
                $reason = $_POST['reason'] ?? 'Patient skipped by triage staff';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'skipped', 'in_progress', $employee_id, $reason);
                echo json_encode($result);
                break;
                
            case 'recall_patient':
                if (!$queue_entry_id) {
                    echo json_encode(['success' => false, 'message' => 'Queue entry ID required']);
                    break;
                }
                $result = $queueService->updateQueueStatus($queue_entry_id, 'waiting', 'skipped', $employee_id, 'Patient recalled to waiting queue');
                echo json_encode($result);
                break;
                
            case 'force_call':
                if (!$queue_entry_id) {
                    echo json_encode(['success' => false, 'message' => 'Queue entry ID required']);
                    break;
                }
                // Force call by updating status from waiting to in_progress
                $result = $queueService->updateQueueStatus($queue_entry_id, 'in_progress', 'waiting', $employee_id, 'Patient force called');
                echo json_encode($result);
                break;
                
            case 'push_to_station':
                if (!$queue_entry_id) {
                    echo json_encode(['success' => false, 'message' => 'Queue entry ID required']);
                    break;
                }
                $target_station = $_POST['target_station'] ?? 'consultation';
                
                // Complete current station and create entry for target station
                $pdo->beginTransaction();
                try {
                    // Complete at current station
                    $complete_result = $queueService->updateQueueStatus($queue_entry_id, 'completed', 'in_progress', $employee_id, "Pushed to {$target_station}");
                    
                    if ($complete_result['success']) {
                        // Get visit_id for creating new queue entry
                        $visit_query = "SELECT visit_id, patient_id FROM queue_entries WHERE queue_entry_id = ?";
                        $stmt = $pdo->prepare($visit_query);
                        $stmt->execute([$queue_entry_id]);
                        $visit_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($visit_data) {
                            // Get target station info
                            $station_query = "SELECT station_id FROM stations WHERE station_type = ? AND is_active = 1 LIMIT 1";
                            $stmt = $pdo->prepare($station_query);
                            $stmt->execute([$target_station]);
                            $target_station_data = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($target_station_data) {
                                // Create new queue entry for target station
                                $new_queue_query = "INSERT INTO queue_entries (visit_id, station_id, status, time_in, created_by) VALUES (?, ?, 'waiting', NOW(), ?)";
                                $stmt = $pdo->prepare($new_queue_query);
                                $stmt->execute([$visit_data['visit_id'], $target_station_data['station_id'], $employee_id]);
                                
                                $pdo->commit();
                                echo json_encode(['success' => true, 'message' => "Patient pushed to {$target_station} successfully"]);
                            } else {
                                $pdo->rollback();
                                echo json_encode(['success' => false, 'message' => "Target station {$target_station} not found"]);
                            }
                        } else {
                            $pdo->rollback();
                            echo json_encode(['success' => false, 'message' => 'Visit data not found']);
                        }
                    } else {
                        $pdo->rollback();
                        echo json_encode(['success' => false, 'message' => 'Failed to complete at current station']);
                    }
                } catch (Exception $e) {
                    $pdo->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error pushing patient: ' . $e->getMessage()]);
                }
                break;
                
            case 'complete_patient':
                if (!$queue_entry_id) {
                    echo json_encode(['success' => false, 'message' => 'Queue entry ID required']);
                    break;
                }
                $result = $queueService->updateQueueStatus($queue_entry_id, 'completed', 'in_progress', $employee_id, 'Patient completed at triage');
                echo json_encode($result);
                break;
                
            case 'force_call':
                $reason = $_POST['reason'] ?? 'Force called by triage staff';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'in_progress', 'waiting', $employee_id, $reason);
                echo json_encode($result);
                break;
                
            case 'push_to_consultation':
                $remarks = $_POST['remarks'] ?? 'Completed triage, forwarded to consultation';
                // Route patient to consultation (maintaining same HHM-XXX queue code)
                $result = $queueService->routePatientToStation($queue_entry_id, 'consultation', $employee_id, $remarks);
                echo json_encode($result);
                break;
                
            case 'save_vitals':
                $patient_id = $_POST['patient_id'] ?? null;
                $visit_id = $_POST['visit_id'] ?? null;
                
                if (!$patient_id) {
                    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
                    exit();
                }
                
                // Prepare vitals data
                $vitals_data = [
                    'patient_id' => $patient_id,
                    'systolic_bp' => $_POST['systolic_bp'] ?? null,
                    'diastolic_bp' => $_POST['diastolic_bp'] ?? null,
                    'heart_rate' => $_POST['heart_rate'] ?? null,
                    'respiratory_rate' => $_POST['respiratory_rate'] ?? null,
                    'temperature' => $_POST['temperature'] ?? null,
                    'weight' => $_POST['weight'] ?? null,
                    'height' => $_POST['height'] ?? null,
                    'remarks' => $_POST['vitals_remarks'] ?? null,
                    'recorded_by' => $employee_id
                ];
                
                // Calculate BMI if height and weight are provided
                if ($vitals_data['height'] && $vitals_data['weight']) {
                    $height_m = $vitals_data['height'] / 100;
                    $vitals_data['bmi'] = round($vitals_data['weight'] / ($height_m * $height_m), 2);
                }
                
                // Insert vitals record
                $vitals_query = "INSERT INTO vitals (patient_id, systolic_bp, diastolic_bp, heart_rate, 
                                respiratory_rate, temperature, weight, height, bmi, recorded_by, recorded_at, remarks) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                $stmt = $pdo->prepare($vitals_query);
                $stmt->execute([
                    $vitals_data['patient_id'],
                    $vitals_data['systolic_bp'],
                    $vitals_data['diastolic_bp'],
                    $vitals_data['heart_rate'],
                    $vitals_data['respiratory_rate'],
                    $vitals_data['temperature'],
                    $vitals_data['weight'],
                    $vitals_data['height'],
                    $vitals_data['bmi'],
                    $vitals_data['recorded_by'],
                    $vitals_data['remarks']
                ]);
                
                $vitals_id = $pdo->lastInsertId();
                
                // Update visit with vitals_id if visit_id is provided
                if ($visit_id && $vitals_id) {
                    $update_visit_query = "UPDATE visits SET vitals_id = ? WHERE visit_id = ?";
                    $stmt = $pdo->prepare($update_visit_query);
                    $stmt->execute([$vitals_id, $visit_id]);
                }
                
                // Log the vitals recording action
                if ($queue_entry_id) {
                    $log_query = "INSERT INTO queue_logs (queue_entry_id, action, old_status, new_status, remarks, performed_by) 
                                 VALUES (?, 'status_changed', 'in_progress', 'in_progress', ?, ?)";
                    $stmt = $pdo->prepare($log_query);
                    $stmt->execute([$queue_entry_id, 'Vitals recorded during triage', $employee_id]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Vitals saved successfully', 'vitals_id' => $vitals_id]);
                break;
                
            case 'get_queue_logs':
                $date = $_POST['date'] ?? date('Y-m-d');
                $station_id = $triage_station['station_id'] ?? null;
                
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

// Get queue data for triage station
$waiting_queue = [];
$in_progress_queue = [];
$completed_queue = [];
$skipped_queue = [];
$queue_stats = ['waiting_count' => 0, 'in_progress_count' => 0, 'completed_count' => 0, 'skipped_count' => 0];
$current_patient = null;

if ($triage_station) {
    $station_id = $triage_station['station_id'];
    
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
    <title>Triage Station - CHO Koronadal</title>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/edit.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Queue Management Framework -->
    <script src="../../assets/js/queue-sync.js"></script>
    <script src="../../assets/js/station-manager.js"></script>
    
    <!-- Queue Code Formatter -->
    <?php include 'queue_code_formatter.php'; ?>
    <script>
        // Make PHP formatter available to JavaScript
        function formatQueueCodeForPublicDisplay(queueCode) {
            return '<?php echo "' + queueCode + '"; ?>'.replace(/(\d{6}-(\d{2}[AP])-(\d{3}))/, function(match, full, timeSlot, sequence) {
                const hour = timeSlot.substring(0, 2);
                const period = timeSlot.substring(2, 1);
                return hour + period + 'M-' + sequence;
            });
        }
    </script>
    <style>
        /* Triage Station Layout - inheriting dashboard styles */

        
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
        
        /* Keep existing triage-specific functionality */
        
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
                    <span>Triage Station</span>
                </div>

                <!-- Page Header with Status Badges - matching dashboard style -->
                <div class="page-header">
                    <h1><i class="fas fa-user-nurse"></i> Triage Station</h1>
                    <div class="total-count">
                        <span class="badge bg-info"><?php echo htmlspecialchars($triage_station['station_name'] ?? 'Triage Station'); ?></span>
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
                                        <span class="btn-title" style="text-align: left;">Queue Dashboard</span>
                                        <span class="btn-subtitle" style="text-align: left;">Main queue overview</span>
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
                                
                                <?php if (count($available_triage_stations) > 1): ?>
                                <div class="modern-btn btn-station-switch">
                                    <div class="btn-icon">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <div class="btn-content">
                                        <span class="btn-title" style="text-align: left;">Switch Station</span>
                                        <select id="stationSelector" onchange="switchStation(this.value)" class="station-select">
                                            <option value="">Select Station...</option>
                                            <?php foreach ($available_triage_stations as $station): ?>
                                            <option value="<?php echo $station['station_id']; ?>" 
                                                    <?php echo ($triage_station && $station['station_id'] == $triage_station['station_id']) ? 'selected' : ''; ?>>
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
                                        <span class="btn-title" style="text-align: left;">Queue Logs</span>
                                        <span class="btn-subtitle" style="text-align: left;">Activity history</span>
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

                <?php if (!$can_manage_queue): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>You are not assigned to a triage station today. Please contact your supervisor.</span>
                    <?php if (strtolower($employee_role) === 'admin'): ?>
                    <br><small>Note: As an admin, you can still access the interface for monitoring purposes.</small>
                    <?php endif; ?>
                </div>
                <?php else: ?>

                <!-- Main Grid Layout -->
                <div class="parent">
                    <!-- Div 1: Station Info -->
                    <div class="div1 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-hospital"></i> Station Information</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <div class="station-info">
                            <div class="info-item">
                                <span class="info-label">Station Name</span>
                                <span class="info-value"><?php echo htmlspecialchars($triage_station['station_name'] ?? 'Triage Station'); ?></span>
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
                                <span class="info-value">Triage & Vitals</span>
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
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>No patient currently in triage</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>

                    <!-- Div 4: Actions for Current Patient -->
                    <div class="div4 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-tools"></i> Patient Actions</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <div class="actions-grid">
                            <button class="action-btn btn-primary" onclick="openVitalsModal()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-heartbeat"></i> Enter Vitals
                            </button>
                            <button class="action-btn btn-secondary" onclick="viewPatientProfile()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-user-circle"></i> View Profile
                            </button>
                            <button class="action-btn btn-secondary" onclick="viewReferral()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-file-medical"></i> View Referral
                            </button>
                            <button class="action-btn btn-success" onclick="pushToConsultation()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-arrow-right"></i> Push to Consultation
                            </button>
                            <button class="action-btn btn-primary" onclick="callNextPatient()">
                                <i class="fas fa-phone"></i> Call Next
                            </button>
                            <button class="action-btn btn-warning" onclick="skipPatient()" <?php echo !$current_patient ? 'disabled' : ''; ?>>
                                <i class="fas fa-forward"></i> Skip
                            </button>
                            <button class="action-btn btn-secondary" onclick="recallPatient()" <?php echo empty($skipped_queue) ? 'disabled' : ''; ?>>
                                <i class="fas fa-undo"></i> Recall
                            </button>
                            <button class="action-btn btn-danger" onclick="forceCallPatient()">
                                <i class="fas fa-exclamation"></i> Force Call
                            </button>
                        </div>
                        </div>
                    </div>

                    <!-- Div 5: Live Queued Patients -->
                    <div class="div5 card-container">
                        <div class="section-header">
                            <h4><i class="fas fa-list"></i> Waiting Queue</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if (!empty($waiting_queue)): ?>
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Queue Code</th>
                                    <th>Patient Name</th>
                                    <th>Priority</th>
                                    <th>Time In</th>
                                    <th>ETA</th>
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
                                    <td><?php echo date('H:i', strtotime($patient['time_in'])); ?></td>
                                    <td><?php echo $patient['estimated_wait'] ?? 'N/A'; ?></td>
                                    <td class="queue-actions">
                                        <button class="btn-primary" onclick="forceCallSpecificPatient(<?php echo $patient['queue_entry_id']; ?>)">
                                            Force Call
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>No patients in waiting queue</span>
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
                            <h4><i class="fas fa-check-circle"></i> Completed Today</h4>
                        </div>
                        <div class="section-body" style="padding: 15px 20px;">
                        <?php if (!empty($completed_queue)): ?>
                        <table class="queue-table">
                            <thead>
                                <tr>
                                    <th>Queue Code</th>
                                    <th>Patient Name</th>
                                    <th>Completed</th>
                                    <th>Next Station</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($completed_queue as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['queue_code'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                    <td><?php echo $patient['time_completed'] ? date('H:i', strtotime($patient['time_completed'])) : 'N/A'; ?></td>
                                    <td><?php echo 'Consultation'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span>No completed patients today</span>
                        </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>

    <!-- Enter Vitals Modal -->
    <div id="vitalsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-heartbeat"></i> Enter Patient Vitals</h2>
                <span class="close" onclick="closeVitalsModal()">&times;</span>
            </div>
            <form id="vitalsForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="systolic_bp">Systolic BP (mmHg)</label>
                        <input type="number" id="systolic_bp" name="systolic_bp" min="50" max="300" placeholder="120">
                    </div>
                    <div class="form-group">
                        <label for="diastolic_bp">Diastolic BP (mmHg)</label>
                        <input type="number" id="diastolic_bp" name="diastolic_bp" min="30" max="200" placeholder="80">
                    </div>
                    <div class="form-group">
                        <label for="heart_rate">Heart Rate (bpm)</label>
                        <input type="number" id="heart_rate" name="heart_rate" min="40" max="200" placeholder="72">
                    </div>
                    <div class="form-group">
                        <label for="respiratory_rate">Respiratory Rate (per min)</label>
                        <input type="number" id="respiratory_rate" name="respiratory_rate" min="8" max="60" placeholder="18">
                    </div>
                    <div class="form-group">
                        <label for="temperature">Temperature (°C)</label>
                        <input type="number" id="temperature" name="temperature" min="30" max="45" step="0.1" placeholder="36.5">
                    </div>
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" name="weight" min="1" max="300" step="0.1" placeholder="70.0">
                    </div>
                    <div class="form-group">
                        <label for="height">Height (cm)</label>
                        <input type="number" id="height" name="height" min="50" max="250" step="0.1" placeholder="170.0">
                    </div>
                    <div class="form-group">
                        <label for="bmi_display">BMI (Auto-calculated)</label>
                        <input type="text" id="bmi_display" name="bmi_display" readonly placeholder="Will be calculated">
                    </div>
                    <div class="form-group full-width">
                        <label for="vitals_remarks">Remarks</label>
                        <textarea id="vitals_remarks" name="vitals_remarks" rows="3" placeholder="Additional observations or notes..."></textarea>
                    </div>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeVitalsModal()">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="saveVitals()">
                        <i class="fas fa-save"></i> Save Vitals
                    </button>
                </div>
            </form>
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
        // Current patient data and configuration
        const currentPatient = <?php echo json_encode($current_patient); ?>;
        const canManageQueue = <?php echo json_encode($can_manage_queue); ?>;
        const stationConfig = {
            stationType: 'triage',
            stationId: <?php echo json_encode($triage_station['station_id'] ?? null); ?>,
            employeeId: <?php echo json_encode($employee_id); ?>,
            stationName: <?php echo json_encode($triage_station['station_name'] ?? 'Triage Station'); ?>
        };
        
        // Initialize station manager when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (canManageQueue && stationConfig.stationId) {
                // Initialize the station manager
                initializeStationManager(stationConfig);
                
                // Register with queue sync manager
                registerStationManager(stationConfig.stationType, stationConfig.stationId, window.stationManager);
                
                console.log('Triage station initialized with new framework');
            } else {
                console.warn('Station management not available - user not authorized or station not assigned');
            }
        });
        
        // Public display window reference
        let publicDisplayWindow = null;
        
        // Function to open public display in popup window using new framework
        function openPublicDisplay() {
            // Use the new queue synchronization framework
            publicDisplayWindow = openPublicDisplay('triage');
            
            if (publicDisplayWindow) {
                // Show success message using station manager if available
                if (window.stationManager) {
                    window.stationManager.showAlert('Public display opened successfully', 'success');
                } else {
                    showAlert('Public display opened successfully', 'success');
                }
            } else {
                // Show error message
                if (window.stationManager) {
                    window.stationManager.showAlert('Failed to open public display. Please check popup blockers.', 'error');
                } else {
                    showAlert('Failed to open public display. Please check popup blockers.', 'error');
                }
            }
        }
        
        // Legacy function for compatibility
        function openPublicDisplayOld() {
            // Check if public display is already open
            if (publicDisplayWindow && !publicDisplayWindow.closed) {
                publicDisplayWindow.focus();
                showAlert('Public display is already open', 'info');
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
                // Open the public display window
                publicDisplayWindow = window.open(
                    'public_display_triage.php',
                    'TriagePublicDisplay',
                    windowFeatures
                );
                
                if (publicDisplayWindow) {
                    // Focus on the new window
                    publicDisplayWindow.focus();
                    
                    // Set window title after load
                    publicDisplayWindow.addEventListener('load', function() {
                        try {
                            publicDisplayWindow.document.title = 'Triage Queue Display - CHO Koronadal';
                        } catch (e) {
                            // Cross-origin restriction, ignore
                        }
                    });
                    
                    // Handle window close event
                    publicDisplayWindow.addEventListener('beforeunload', function() {
                        console.log('Public display window closing');
                        publicDisplayWindow = null;
                        updateDisplayButtonState(false);
                    });
                    
                    // Show success message
                    showAlert('Public display opened successfully', 'success');
                    
                    // Update button appearance to show active state
                    updateDisplayButtonState(true);
                    
                } else {
                    throw new Error('Popup was blocked');
                }
                
            } catch (error) {
                console.error('Error opening public display:', error);
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
        
        // Auto-refresh interval (30 seconds)
        setInterval(() => {
            if (canManageQueue) {
                location.reload();
            }
        }, 30000);

        // BMI calculation
        function calculateBMI() {
            const height = parseFloat(document.getElementById('height').value);
            const weight = parseFloat(document.getElementById('weight').value);
            
            if (height && weight && height > 0) {
                const heightInMeters = height / 100;
                const bmi = weight / (heightInMeters * heightInMeters);
                document.getElementById('bmi_display').value = bmi.toFixed(2);
            } else {
                document.getElementById('bmi_display').value = '';
            }
        }

        // Add event listeners for BMI calculation
        document.getElementById('height').addEventListener('input', calculateBMI);
        document.getElementById('weight').addEventListener('input', calculateBMI);

        // Modal functions
        function openVitalsModal() {
            if (!currentPatient) {
                showAlert('No patient currently in triage', 'error');
                return;
            }
            document.getElementById('vitalsModal').style.display = 'block';
        }

        function closeVitalsModal() {
            document.getElementById('vitalsModal').style.display = 'none';
            document.getElementById('vitalsForm').reset();
        }

        function openQueueLogsModal() {
            document.getElementById('queueLogsModal').style.display = 'block';
            loadQueueLogs();
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
                showAlert('No patient currently in triage', 'error');
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

        function pushToConsultation() {
            if (!currentPatient) {
                showAlert('No patient currently in triage', 'error');
                return;
            }
            
            const remarks = prompt('Remarks for consultation referral:') || 'Completed triage, forwarded to consultation';
            performQueueAction('push_to_consultation', currentPatient.queue_entry_id, 'Forwarding to consultation...', { remarks: remarks });
        }

        function saveVitals() {
            if (!currentPatient) {
                showAlert('No patient currently in triage', 'error');
                return;
            }
            
            // Basic validation
            const systolic = document.getElementById('systolic_bp').value;
            const diastolic = document.getElementById('diastolic_bp').value;
            
            // Check if at least one vital sign is entered
            const requiredFields = ['systolic_bp', 'diastolic_bp', 'heart_rate', 'temperature'];
            const hasRequiredData = requiredFields.some(field => {
                const value = document.getElementById(field).value;
                return value && value.trim() !== '';
            });
            
            if (!hasRequiredData) {
                showAlert('Please enter at least one vital sign before saving', 'error');
                return;
            }
            
            // Validate blood pressure if both values are provided
            if (systolic && diastolic) {
                const sys = parseInt(systolic);
                const dia = parseInt(diastolic);
                
                if (sys <= dia) {
                    showAlert('Systolic pressure must be higher than diastolic pressure', 'error');
                    return;
                }
                
                if (sys < 70 || sys > 250 || dia < 40 || dia > 150) {
                    if (!confirm('Blood pressure values seem unusual. Do you want to continue?')) {
                        return;
                    }
                }
            }
            
            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('action', 'save_vitals');
            formData.append('patient_id', currentPatient.patient_id);
            formData.append('visit_id', currentPatient.visit_id || '');
            formData.append('queue_entry_id', currentPatient.queue_entry_id);
            
            // Get all vital signs
            const vitalsFields = ['systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate', 
                                 'temperature', 'weight', 'height', 'vitals_remarks'];
            
            vitalsFields.forEach(field => {
                const value = document.getElementById(field).value;
                if (value && value.trim() !== '') {
                    formData.append(field, value);
                }
            });
            
            showAlert('Saving vitals...', 'info');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    closeVitalsModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                showAlert('Error saving vitals: ' + error.message, 'error');
            });
        }

        function viewPatientProfile() {
            if (!currentPatient) {
                showAlert('No patient currently in triage', 'error');
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

        // Alert system - enhanced with station manager integration
        function showAlert(message, type = 'info') {
            // Use station manager alert if available, otherwise fallback to legacy
            if (window.stationManager && typeof window.stationManager.showAlert === 'function') {
                window.stationManager.showAlert(message, type);
                return;
            }
            
            // Legacy alert implementation with improved error handling
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => {
                if (!alert.parentElement || !alert.parentElement.classList.contains('modal-content')) {
                    alert.remove();
                }
            });
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            
            let icon = 'fa-info-circle';
            if (type === 'success') icon = 'fa-check-circle';
            else if (type === 'error') icon = 'fa-exclamation-triangle';
            else if (type === 'warning') icon = 'fa-exclamation-circle';
            
            alertDiv.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            // Try multiple container selectors
            let container = document.querySelector('.queue-dashboard-container');
            if (!container) container = document.querySelector('.triage-container');
            if (!container) container = document.querySelector('.homepage');
            if (!container) container = document.body;
            
            const header = document.querySelector('.page-header');
            
            try {
                if (header && container.contains(header)) {
                    container.insertBefore(alertDiv, header.nextSibling);
                } else {
                    container.insertBefore(alertDiv, container.firstChild);
                }
            } catch (error) {
                console.warn('Failed to insert alert, appending to container:', error);
                container.appendChild(alertDiv);
            }
            
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
                window.location.href = `triage_station.php?station_id=${stationId}`;
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
    </script>
</body>
</html>