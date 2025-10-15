<?php
// pages/queueing/public_display_selector_api.php
// API endpoint for public display selector real-time status updates

// Include necessary configurations
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    exit('Bad Request');
}

// Check authorization - admin only for public display selector
if (!isset($_SESSION['employee_id']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    exit('Access Denied');
}

require_once $root_path . '/config/db.php';

// Set content type
header('Content-Type: application/json');

try {
    // Define station types to check
    $station_types = ['triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document'];
    
    // Fetch stations data with assignments for today
    $today = date('Y-m-d');
    $station_types_list = "'" . implode("','", $station_types) . "'";
    
    $query = "
        SELECT 
            s.station_id,
            s.station_name,
            s.station_type,
            s.is_open,
            s.is_active,
            sv.name as service_name,
            CONCAT(e.first_name, ' ', e.last_name) as assigned_employee,
            r.role_name as employee_role,
            asch.schedule_id
        FROM stations s
        LEFT JOIN services sv ON s.service_id = sv.service_id
        LEFT JOIN assignment_schedules asch ON s.station_id = asch.station_id 
            AND asch.start_date <= ? 
            AND (asch.end_date IS NULL OR asch.end_date >= ?)
            AND asch.is_active = 1
        LEFT JOIN employees e ON asch.employee_id = e.employee_id
        LEFT JOIN roles r ON e.role_id = r.role_id
        WHERE s.station_type IN ($station_types_list)
            AND s.is_active = 1
        ORDER BY 
            FIELD(s.station_type, 'triage', 'consultation', 'lab', 'pharmacy', 'billing', 'document')
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$today, $today]);
    $stations_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize data by station type
    $stations_data = [];
    foreach ($stations_result as $row) {
        $stations_data[$row['station_type']] = [
            'station_id' => $row['station_id'],
            'station_name' => $row['station_name'],
            'station_type' => $row['station_type'],
            'is_open' => (bool)$row['is_open'],
            'is_active' => (bool)$row['is_active'],
            'service_name' => $row['service_name'],
            'assigned_employee' => $row['assigned_employee'],
            'employee_role' => $row['employee_role'],
            'schedule_id' => $row['schedule_id']
        ];
    }
    
    // Ensure all station types are represented
    foreach ($station_types as $type) {
        if (!isset($stations_data[$type])) {
            $stations_data[$type] = [
                'station_id' => null,
                'station_name' => ucfirst($type) . ' Station',
                'station_type' => $type,
                'is_open' => false,
                'is_active' => false,
                'service_name' => null,
                'assigned_employee' => null,
                'employee_role' => null,
                'schedule_id' => null
            ];
        }
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Station status retrieved successfully',
        'stations' => $stations_data,
        'timestamp' => time(),
        'date' => $today
    ]);
    
} catch (Exception $e) {
    error_log("Public Display Selector API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve station status',
        'error' => $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>