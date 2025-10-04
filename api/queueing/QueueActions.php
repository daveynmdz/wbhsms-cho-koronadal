<?php
/**
 * Queue Actions - Procedural Endpoints
 * Provides action-based endpoints for queue management operations
 * 
 * Usage:
 * POST to this file with 'action' parameter and required data
 * 
 * Example:
 * POST /api/queueing/QueueActions.php
 * Content-Type: application/json
 * {
 *   "action": "checkInPatient",
 *   "queue_entry_id": 123,
 *   "employee_id": 456
 * }
 * 
 * Or via form data:
 * POST /api/queueing/QueueActions.php
 * action=checkInPatient&queue_entry_id=123&employee_id=456
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include database connection
require_once __DIR__ . '/../../config/db.php';

/**
 * Check in a patient to start their queue process
 * 
 * This function handles the patient check-in process including:
 * - Verify patient and queue entry validity
 * - Update queue status from 'scheduled' to 'waiting'
 * - Record check-in timestamp and employee
 * - Generate queue number if not already assigned
 * - Send notifications to relevant stations
 * 
 * @param int $queue_entry_id Queue entry identifier
 * @param int $employee_id Employee performing the check-in
 * @return string JSON response with status and queue details
 */
function checkInPatient($queue_entry_id, $employee_id) {
    // TODO: Implement patient check-in logic
    // - Validate queue entry exists and is in correct status
    // - Update status to 'waiting'
    // - Record check-in timestamp
    // - Assign queue number if needed
    // - Log the action
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'checkInPatient',
        'queue_entry_id' => $queue_entry_id,
        'employee_id' => $employee_id,
        'message' => 'Check-in functionality pending implementation'
    ]);
}

/**
 * Call the next patient in queue for a specific service and station
 * 
 * This function manages the patient calling process including:
 * - Find next patient in queue for the service
 * - Update patient status to 'in_progress'
 * - Assign patient to specific station/provider
 * - Update public display with "now serving" information
 * - Send notifications to patient (if system available)
 * 
 * @param int $service_id Service identifier (consultation, lab, etc.)
 * @param int $station_id Station/provider identifier
 * @param int $employee_id Employee calling the patient
 * @return string JSON response with patient details and queue info
 */
function callNextPatient($service_id, $station_id, $employee_id) {
    // TODO: Implement next patient calling logic
    // - Query next waiting patient for service
    // - Consider priority levels (urgent, emergency)
    // - Update status to 'in_progress'
    // - Assign to station
    // - Update displays
    // - Log the action
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'callNextPatient',
        'service_id' => $service_id,
        'station_id' => $station_id,
        'employee_id' => $employee_id,
        'message' => 'Next patient calling functionality pending implementation'
    ]);
}

/**
 * Mark a patient's service as completed
 * 
 * This function handles service completion including:
 * - Update queue status to 'completed'
 * - Record completion timestamp and duration
 * - Free up the station for next patient
 * - Update service statistics
 * - Handle follow-up service referrals if needed
 * 
 * @param int $queue_entry_id Queue entry identifier
 * @param int $employee_id Employee completing the service
 * @return string JSON response with completion status and next actions
 */
function completePatient($queue_entry_id, $employee_id) {
    // TODO: Implement patient completion logic
    // - Validate queue entry is in 'in_progress' status
    // - Update status to 'completed'
    // - Calculate and record service duration
    // - Free station assignment
    // - Update statistics
    // - Check for follow-up services
    // - Log the action
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'completePatient',
        'queue_entry_id' => $queue_entry_id,
        'employee_id' => $employee_id,
        'message' => 'Patient completion functionality pending implementation'
    ]);
}

/**
 * Skip a patient in the queue (temporary or permanent)
 * 
 * This function handles patient skipping including:
 * - Update queue status to 'skipped'
 * - Record skip reason and remarks
 * - Determine if skip is temporary (re-queue) or permanent
 * - Adjust queue position if temporary skip
 * - Notify patient of skip status
 * 
 * @param int $queue_entry_id Queue entry identifier
 * @param int $employee_id Employee performing the skip
 * @param string $remarks Reason for skipping the patient
 * @return string JSON response with skip status and next queue position
 */
function skipPatient($queue_entry_id, $employee_id, $remarks) {
    // TODO: Implement patient skip logic
    // - Validate queue entry and current status
    // - Update status to 'skipped'
    // - Record skip reason and timestamp
    // - Determine skip type (temporary/permanent)
    // - Adjust queue order if temporary
    // - Log the action with remarks
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'skipPatient',
        'queue_entry_id' => $queue_entry_id,
        'employee_id' => $employee_id,
        'remarks' => $remarks,
        'message' => 'Patient skip functionality pending implementation'
    ]);
}

/**
 * Transfer a patient to a different service queue
 * 
 * This function handles inter-service transfers including:
 * - Validate transfer is allowed between services
 * - Create new queue entry for target service
 * - Update original entry status to 'transferred'
 * - Maintain patient priority level in new queue
 * - Record transfer reason and authorization
 * 
 * @param int $queue_entry_id Original queue entry identifier
 * @param int $to_service_id Target service identifier
 * @param int $employee_id Employee authorizing the transfer
 * @return string JSON response with new queue details and transfer status
 */
function transferPatient($queue_entry_id, $to_service_id, $employee_id) {
    // TODO: Implement patient transfer logic
    // - Validate source and target services
    // - Check transfer authorization rules
    // - Create new queue entry for target service
    // - Update original entry status
    // - Maintain patient data and priority
    // - Log the transfer with reason
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'transferPatient',
        'queue_entry_id' => $queue_entry_id,
        'to_service_id' => $to_service_id,
        'employee_id' => $employee_id,
        'message' => 'Patient transfer functionality pending implementation'
    ]);
}

/**
 * Reinstate a previously skipped or cancelled patient
 * 
 * This function handles patient reinstatement including:
 * - Validate patient can be reinstated
 * - Update queue status back to 'waiting'
 * - Assign new queue position (usually at end)
 * - Record reinstatement reason and authorization
 * - Notify patient of new queue status
 * 
 * @param int $queue_entry_id Queue entry identifier
 * @param int $employee_id Employee performing the reinstatement
 * @return string JSON response with new queue position and status
 */
function reinstatePatient($queue_entry_id, $employee_id) {
    // TODO: Implement patient reinstatement logic
    // - Validate queue entry can be reinstated
    // - Check business rules for reinstatement
    // - Update status back to 'waiting'
    // - Assign new queue position
    // - Record reinstatement details
    // - Log the action
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'reinstatePatient',
        'queue_entry_id' => $queue_entry_id,
        'employee_id' => $employee_id,
        'message' => 'Patient reinstatement functionality pending implementation'
    ]);
}

/**
 * Cancel all queue entries associated with an appointment
 * 
 * This function handles appointment-level cancellation including:
 * - Find all queue entries linked to the appointment
 * - Update all entries to 'cancelled' status
 * - Record cancellation reason and timestamp
 * - Free up any assigned stations/resources
 * - Update appointment status in main system
 * 
 * @param int $appointment_id Appointment identifier
 * @param int $employee_id Employee performing the cancellation
 * @return string JSON response with cancellation details and affected queues
 */
function cancelAppointmentQueue($appointment_id, $employee_id) {
    // TODO: Implement appointment queue cancellation logic
    // - Find all queue entries for appointment
    // - Validate cancellation is allowed
    // - Update all entries to 'cancelled'
    // - Free assigned resources
    // - Update appointment status
    // - Log the cancellation
    
    return json_encode([
        'status' => 'not_implemented',
        'action' => 'cancelAppointmentQueue',
        'appointment_id' => $appointment_id,
        'employee_id' => $employee_id,
        'message' => 'Appointment queue cancellation functionality pending implementation'
    ]);
}

// Simple router for testing purposes
// TODO: Replace with proper API routing in production
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        // Fallback to form data
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    try {
        switch ($action) {
            case 'checkInPatient':
                $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo checkInPatient($queue_entry_id, $employee_id);
                break;
                
            case 'callNextPatient':
                $service_id = (int)($input['service_id'] ?? 0);
                $station_id = (int)($input['station_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo callNextPatient($service_id, $station_id, $employee_id);
                break;
                
            case 'completePatient':
                $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo completePatient($queue_entry_id, $employee_id);
                break;
                
            case 'skipPatient':
                $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                $remarks = $input['remarks'] ?? '';
                echo skipPatient($queue_entry_id, $employee_id, $remarks);
                break;
                
            case 'transferPatient':
                $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
                $to_service_id = (int)($input['to_service_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo transferPatient($queue_entry_id, $to_service_id, $employee_id);
                break;
                
            case 'reinstatePatient':
                $queue_entry_id = (int)($input['queue_entry_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo reinstatePatient($queue_entry_id, $employee_id);
                break;
                
            case 'cancelAppointmentQueue':
                $appointment_id = (int)($input['appointment_id'] ?? 0);
                $employee_id = (int)($input['employee_id'] ?? 0);
                echo cancelAppointmentQueue($appointment_id, $employee_id);
                break;
                
            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid action specified',
                    'available_actions' => [
                        'checkInPatient',
                        'callNextPatient', 
                        'completePatient',
                        'skipPatient',
                        'transferPatient',
                        'reinstatePatient',
                        'cancelAppointmentQueue'
                    ]
                ]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
} else {
    // Handle GET requests - show usage information
    echo json_encode([
        'status' => 'info',
        'message' => 'Queue Actions API - POST only',
        'usage' => [
            'method' => 'POST',
            'content_type' => 'application/json or application/x-www-form-urlencoded',
            'required_fields' => ['action'],
            'available_actions' => [
                'checkInPatient' => ['queue_entry_id', 'employee_id'],
                'callNextPatient' => ['service_id', 'station_id', 'employee_id'],
                'completePatient' => ['queue_entry_id', 'employee_id'],
                'skipPatient' => ['queue_entry_id', 'employee_id', 'remarks'],
                'transferPatient' => ['queue_entry_id', 'to_service_id', 'employee_id'],
                'reinstatePatient' => ['queue_entry_id', 'employee_id'],
                'cancelAppointmentQueue' => ['appointment_id', 'employee_id']
            ]
        ]
    ]);
}

?>