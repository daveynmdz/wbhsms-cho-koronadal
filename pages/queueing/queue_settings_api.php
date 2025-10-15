<?php
/**
 * Queue Settings API
 * CHO Koronadal Queue Management System
 * 
 * Purpose: API endpoint for managing queue system settings
 */

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include necessary files
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_settings_service.php';

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Invalid request']));
}

// Check authorization - admin only
if (!isset($_SESSION['employee_id']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied - admin only']));
}

try {
    // Initialize queue settings service
    $queueSettings = new QueueSettingsService($pdo);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_status':
            $status = $queueSettings->getSystemStatus();
            echo json_encode([
                'success' => true,
                'data' => $status,
                'message' => 'System status retrieved successfully'
            ]);
            break;
            
        case 'toggle_testing_mode':
            $result = $queueSettings->toggleSetting('testing_mode');
            
            // Log the action
            error_log("Admin {$_SESSION['employee_id']} toggled testing mode");
            
            echo json_encode($result);
            break;
            
        case 'toggle_time_constraints':
            $result = $queueSettings->toggleSetting('ignore_time_constraints');
            
            // Log the action
            error_log("Admin {$_SESSION['employee_id']} toggled time constraints");
            
            echo json_encode($result);
            break;
            
        case 'toggle_override_mode':
            $result = $queueSettings->toggleSetting('queue_override_mode');
            
            // Log the action
            error_log("Admin {$_SESSION['employee_id']} toggled override mode");
            
            echo json_encode($result);
            break;
            
        case 'toggle_force_stations':
            $result = $queueSettings->toggleSetting('force_all_stations_open');
            
            // Log the action
            error_log("Admin {$_SESSION['employee_id']} toggled force stations open");
            
            echo json_encode($result);
            break;
            
        case 'update_setting':
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';
            
            if (empty($key)) {
                throw new Exception('Setting key is required');
            }
            
            $result = $queueSettings->updateSetting($key, $value);
            
            // Log the action
            error_log("Admin {$_SESSION['employee_id']} updated setting '$key' to '$value'");
            
            echo json_encode($result);
            break;
            
        case 'get_all_settings':
            $settings = $queueSettings->getAllSettings();
            echo json_encode([
                'success' => true,
                'data' => $settings,
                'message' => 'Settings retrieved successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    error_log("Queue Settings API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>