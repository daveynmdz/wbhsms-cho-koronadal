<?php
// pages/queueing/dashboard_queue_api.php
// API endpoint for queue dashboard overview

// Include necessary configurations
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    exit('Bad Request');
}

// Check authorization - admin only
if (!isset($_SESSION['employee_id']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    exit('Access Denied');
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';
require_once $root_path . '/utils/queue_code_formatter.php';

// Set content type
header('Content-Type: application/json');

try {
    // Initialize queue management service
    $queueService = new QueueManagementService($pdo);
    
    $date = date('Y-m-d');
    
    // Get stations with assignments and queue stats
    $stations = $queueService->getAllStationsWithAssignments($date);
    
    // Filter and enhance stations data for dashboard overview
    $dashboard_stations = [];
    
    foreach ($stations as $station) {
        // Only include active stations
        if (!$station['is_active']) continue;
        
        $station_data = [
            'station_id' => $station['station_id'],
            'station_name' => $station['station_name'],
            'station_type' => $station['station_type'],
            'is_active' => $station['is_active'],
            'is_open' => $station['is_open'],
            'assigned_employee' => $station['assigned_employee'],
            'assigned_employee_id' => $station['assigned_employee_id'],
            'current_patient' => null,
            'next_patient' => null,
            'queue_stats' => null
        ];
        
        // Get queue statistics if station has assignment
        if ($station['assigned_employee_id']) {
            $station_data['queue_stats'] = $queueService->getStationQueueStats($station['station_id'], $date);
            
            // Get current patient (in progress)
            $current_patients = $queueService->getStationQueue($station['station_id'], ['in_progress'], $date);
            if (!empty($current_patients)) {
                $current_patient = $current_patients[0];
                if (isset($current_patient['queue_code'])) {
                    $current_patient['formatted_code'] = formatQueueCodeForDisplay($current_patient['queue_code']);
                }
                $station_data['current_patient'] = $current_patient;
            }
            
            // Get next patient (first in waiting)
            $waiting_patients = $queueService->getStationQueue($station['station_id'], ['waiting'], $date);
            if (!empty($waiting_patients)) {
                $next_patient = $waiting_patients[0];
                if (isset($next_patient['queue_code'])) {
                    $next_patient['formatted_code'] = formatQueueCodeForDisplay($next_patient['queue_code']);
                }
                $station_data['next_patient'] = $next_patient;
            }
        }
        
        $dashboard_stations[] = $station_data;
    }
    
    // Get overall statistics for the dashboard
    $overall_stats = $queueService->getQueueStatistics($date);
    
    // Calculate additional metrics
    $total_waiting = 0;
    $total_in_progress = 0;
    $active_stations = 0;
    
    foreach ($dashboard_stations as $station) {
        if ($station['assigned_employee_id'] && $station['queue_stats']) {
            $total_waiting += $station['queue_stats']['waiting_count'] ?? 0;
            $total_in_progress += $station['queue_stats']['in_progress_count'] ?? 0;
            $active_stations++;
        }
    }
    
    $enhanced_stats = array_merge($overall_stats, [
        'total_waiting' => $total_waiting,
        'total_in_progress' => $total_in_progress,
        'active_stations' => $active_stations,
        'last_updated' => date('Y-m-d H:i:s')
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard queue data retrieved successfully',
        'stations' => $dashboard_stations,
        'overall_stats' => $enhanced_stats,
        'timestamp' => time(),
        'date' => $date
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard Queue API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve dashboard queue data',
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>