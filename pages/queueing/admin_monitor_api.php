<?php
// pages/queueing/admin_monitor_api.php
// API endpoint for admin monitor smart refresh

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
header('Content-Type: text/html; charset=UTF-8');

try {
    // Initialize queue management service
    $queueService = new QueueManagementService($pdo);
    
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get stations with assignments and queue stats
    $stations = $queueService->getAllStationsWithAssignments($date);
    
    // Enhance stations data with queue statistics and current patient
    foreach ($stations as &$station) {
        if ($station['is_active']) {
            $station['queue_stats'] = $queueService->getStationQueueStats($station['station_id'], $date);
            
            // Get current patient (in progress)
            $current_patients = $queueService->getStationQueue($station['station_id'], 'in_progress');
            $station['current_patient'] = !empty($current_patients) ? $current_patients[0] : null;
            
            // Get next patient (first in waiting)
            $waiting_patients = $queueService->getStationQueue($station['station_id'], 'waiting');
            $station['next_patient'] = !empty($waiting_patients) ? $waiting_patients[0] : null;
            
            // Format queue codes if present
            if ($station['current_patient'] && isset($station['current_patient']['queue_code'])) {
                $station['current_patient']['formatted_code'] = formatQueueCodeForDisplay($station['current_patient']['queue_code']);
            }
            if ($station['next_patient'] && isset($station['next_patient']['queue_code'])) {
                $station['next_patient']['formatted_code'] = formatQueueCodeForDisplay($station['next_patient']['queue_code']);
            }
        }
    }
    
    // Get overall statistics
    $overall_stats = $queueService->getQueueStatistics($date);

    // Return only the monitor grid HTML content
    ?>
    <!-- Overall Statistics Section -->
    <div class="overall-stats mb-4">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-users"></i>
                        <span>Total Patients</span>
                    </div>
                    <div class="stat-value"><?php echo $overall_stats['total_patients'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-clock"></i>
                        <span>In Progress</span>
                    </div>
                    <div class="stat-value"><?php echo $overall_stats['in_progress'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-check-circle"></i>
                        <span>Completed</span>
                    </div>
                    <div class="stat-value"><?php echo $overall_stats['completed'] ?? 0; ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <i class="fas fa-hourglass-half"></i>
                        <span>Avg Time</span>
                    </div>
                    <div class="stat-value"><?php echo isset($overall_stats['avg_turnaround_time']) ? round($overall_stats['avg_turnaround_time']) . 'm' : 'N/A'; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitor Grid -->
    <div class="monitor-grid">
        <?php foreach ($stations as $station): ?>
            <div class="station-card <?php echo !$station['is_active'] ? 'inactive-station' : (!$station['assigned_employee_id'] ? 'unassigned-station' : ''); ?>">
                <div class="station-header <?php echo !$station['is_active'] ? 'inactive' : (!$station['assigned_employee_id'] ? 'unassigned' : ''); ?>">
                    <div>
                        <h3 class="station-title"><?php echo htmlspecialchars($station['station_name']); ?></h3>
                        <?php if ($station['assigned_employee']): ?>
                            <div class="assigned-employee">
                                <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($station['assigned_employee']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="station-type"><?php echo strtoupper($station['station_type']); ?></span>
                </div>
                
                <div class="station-body">
                    <?php if (!$station['is_active']): ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-pause-circle me-2"></i>Station Inactive
                        </div>
                    <?php elseif (!$station['assigned_employee_id']): ?>
                        <div class="text-center text-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>No Employee Assigned
                        </div>
                    <?php else: ?>
                        <!-- Queue Statistics -->
                        <div class="station-info">
                            <div class="info-item">
                                <div class="info-label">Waiting</div>
                                <div class="info-value"><?php echo $station['queue_stats']['waiting_count'] ?? 0; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Completed</div>
                                <div class="info-value"><?php echo $station['queue_stats']['completed_count'] ?? 0; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">In Progress</div>
                                <div class="info-value"><?php echo $station['queue_stats']['in_progress_count'] ?? 0; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Skipped</div>
                                <div class="info-value"><?php echo $station['queue_stats']['skipped_count'] ?? 0; ?></div>
                            </div>
                        </div>

                        <!-- Current Patient -->
                        <?php if ($station['current_patient']): ?>
                            <div class="current-patient">
                                <div class="info-label">Current Patient</div>
                                <div class="patient-name">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($station['current_patient']['patient_name']); ?>
                                </div>
                                <div class="patient-details">
                                    <?php if (isset($station['current_patient']['formatted_code'])): ?>
                                        <?php echo $station['current_patient']['formatted_code']; ?> •
                                    <?php else: ?>
                                        Queue #<?php echo $station['current_patient']['queue_number']; ?> • 
                                    <?php endif; ?>
                                    Started: <?php echo date('g:i A', strtotime($station['current_patient']['time_started'])); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Next Patient -->
                        <?php if ($station['next_patient']): ?>
                            <div class="next-patient">
                                <div class="info-label">Next Patient</div>
                                <div class="patient-name">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($station['next_patient']['patient_name']); ?>
                                </div>
                                <div class="patient-details">
                                    <?php if (isset($station['next_patient']['formatted_code'])): ?>
                                        <?php echo $station['next_patient']['formatted_code']; ?> • 
                                    <?php else: ?>
                                        Queue #<?php echo $station['next_patient']['queue_number']; ?> • 
                                    <?php endif; ?>
                                    <span class="badge bg-<?php 
                                        echo $station['next_patient']['priority_level'] === 'emergency' ? 'danger' : 
                                            ($station['next_patient']['priority_level'] === 'priority' ? 'warning' : 'secondary'); 
                                    ?>">
                                        <?php echo ucfirst($station['next_patient']['priority_level']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php elseif (!$station['current_patient'] && ($station['queue_stats']['waiting_count'] ?? 0) == 0): ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-check-circle me-2"></i>No Patients Waiting
                            </div>
                        <?php endif; ?>

                        <!-- Average Processing Time -->
                        <?php if (!empty($station['queue_stats']['avg_turnaround_time'])): ?>
                            <div class="text-center text-muted" style="margin-top: 10px;">
                                <small>
                                    <i class="fas fa-clock me-2"></i>
                                    Avg Time: <?php echo round($station['queue_stats']['avg_turnaround_time']); ?> min
                                </small>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    
} catch (Exception $e) {
    error_log("Admin Monitor API Error: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="error-message">Error loading monitor data. Please refresh the page.</div>';
}
?>