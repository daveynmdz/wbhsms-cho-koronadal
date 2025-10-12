<?php
/**
 * Patient Queue Status API Endpoint
 * Purpose: Provides real-time queue status updates for individual patients
 * Used by patient queue interface for AJAX updates without full page refresh
 */

// Include patient session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/patient_session.php';
require_once $root_path . '/config/db.php';

// Set JSON header
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Check if patient is logged in
if (!isset($_SESSION['patient_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$patient_id = $_SESSION['patient_id'];

try {
    // Get current queue entry for patient
    $queue_query = "
        SELECT 
            qe.*,
            s.station_name,
            s.station_type,
            v.visit_id,
            v.visit_type,
            CASE 
                WHEN pf.priority_level IS NOT NULL THEN pf.priority_level 
                ELSE 'regular' 
            END as priority_level,
            a.appointment_date,
            a.appointment_time,
            p.first_name,
            p.last_name
        FROM queue_entries qe
        JOIN stations s ON qe.station_id = s.station_id
        LEFT JOIN visits v ON qe.visit_id = v.visit_id
        LEFT JOIN appointments a ON v.appointment_id = a.appointment_id
        LEFT JOIN patient_flags pf ON qe.patient_id = pf.patient_id AND pf.is_active = 1
        LEFT JOIN patients p ON qe.patient_id = p.patient_id
        WHERE qe.patient_id = ? 
            AND qe.status IN ('waiting', 'called', 'in_progress')
            AND DATE(qe.time_in) = CURDATE()
        ORDER BY qe.time_in DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($queue_query);
    $stmt->execute([$patient_id]);
    $current_queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_queue) {
        // Get waiting ahead count and calculate wait time
        $waiting_ahead_query = "
            SELECT COUNT(*) as waiting_ahead 
            FROM queue_entries 
            WHERE station_id = ? 
                AND status IN ('waiting', 'called') 
                AND time_in < ? 
                AND DATE(time_in) = CURDATE()
        ";
        $stmt = $pdo->prepare($waiting_ahead_query);
        $stmt->execute([$current_queue['station_id'], $current_queue['time_in']]);
        $wait_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $wait_time_info = [
            'waiting_ahead' => (int)$wait_result['waiting_ahead'],
            'estimated_minutes' => max(1, $wait_result['waiting_ahead'] * 5) // 5 min average per patient
        ];
        
        // Format queue code
        $time_prefix = date('H', strtotime($current_queue['time_in']));
        $time_suffix = date('H', strtotime($current_queue['time_in'])) < 12 ? 'A' : 'P';
        $priority_indicator = $current_queue['priority_level'] === 'priority' ? 'P' : 'R';
        $sequence = str_pad($current_queue['queue_id'], 3, '0', STR_PAD_LEFT);
        $formatted_code = $time_prefix . $time_suffix . '-' . $priority_indicator . '-' . $sequence;
        
        // Add formatted code to queue data
        $current_queue['formatted_code'] = $formatted_code;
        
        // Return success response with queue data
        echo json_encode([
            'success' => true,
            'queue' => $current_queue,
            'wait_info' => $wait_time_info,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        // No active queue found
        echo json_encode([
            'success' => true,
            'queue' => null,
            'message' => 'No active queue',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    error_log("Patient queue API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>