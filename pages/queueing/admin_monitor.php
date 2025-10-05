<?php
// pages/queueing/admin_monitor.php
// Admin interface for monitoring all stations and their queue status

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../management/admin/dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Initialize queue management service
$queueService = new QueueManagementService($conn);

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
    }
}

// Get overall statistics
$overall_stats = $queueService->getQueueStatistics($date);

// Set active page for sidebar highlighting
$activePage = 'queue_management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Monitor | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Additional styles for admin monitor */
        :root {
            --primary: #0077b6;
            --primary-dark: #03045e;
            --secondary: #6c757d;
            --success: #2d6a4f;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #d00000;
            --light: #f8f9fa;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .station-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .station-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .station-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .station-header.inactive {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .station-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }

        .station-type {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .station-body {
            padding: 20px;
        }

        .station-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            font-size: 12px;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .employee-info {
            background: var(--light);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 15px;
        }

        .employee-name {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 2px;
        }

        .employee-role {
            font-size: 12px;
            color: var(--secondary);
        }

        .current-patient {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
        }

        .patient-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .patient-details {
            font-size: 12px;
            color: var(--secondary);
        }

        .next-patient {
            background: #e3f2fd;
            border-radius: 6px;
            padding: 10px;
        }

        .unassigned-station {
            opacity: 0.6;
        }

        .inactive-station {
            opacity: 0.5;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            border-left: 4px solid var(--primary);
        }

        .stat-card.waiting { border-left-color: var(--info); }
        .stat-card.in-progress { border-left-color: var(--warning); }
        .stat-card.completed { border-left-color: var(--success); }
        .stat-card.total { border-left-color: var(--primary); }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            margin: 2px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active { background-color: var(--success); }
        .status-inactive { background-color: var(--danger); }
        .status-unassigned { background-color: var(--secondary); }

        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .bg-success {
            background-color: var(--success);
            color: white;
        }
        
        .bg-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .bg-warning {
            background-color: var(--warning);
            color: #000;
        }
        
        .bg-secondary {
            background-color: var(--secondary);
            color: white;
        }

        .d-flex { display: flex; }
        .me-2 { margin-right: 8px; }
        .mb-2 { margin-bottom: 8px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--secondary) !important; }

        @media (max-width: 768px) {
            .monitor-grid {
                grid-template-columns: 1fr;
            }
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../../includes/sidebar_admin.php'; ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Include topbar -->
            <?php include '../../includes/topbar.php'; ?>
            
            <div class="card">
                <div class="content-header">
                    <h1 class="page-title">
                        <i class="fas fa-monitor-heart-rate me-2"></i>Queue Monitor
                    </h1>
                    <div class="d-flex">
                        <a href="staff_assignments.php" class="action-btn btn-primary me-2">
                            <i class="fas fa-users-cog me-2"></i>Manage Assignments
                        </a>
                    </div>
                </div>

                <!-- Date selector -->
                <div class="mb-2">
                    <form method="get" class="d-flex">
                        <label for="date" style="margin-right: 10px; align-self: center;">Date:</label>
                        <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control" style="width: 200px; margin-right: 10px;">
                        <button type="submit" class="action-btn btn-primary">
                            <i class="fas fa-search me-2"></i>View
                        </button>
                    </form>
                </div>

                <!-- Overall Statistics -->
                <?php if (!empty($overall_stats['statistics'])): ?>
                    <?php
                    $total_waiting = array_sum(array_column($overall_stats['statistics'], 'waiting'));
                    $total_in_progress = array_sum(array_column($overall_stats['statistics'], 'in_progress'));
                    $total_completed = array_sum(array_column($overall_stats['statistics'], 'done'));
                    $total_entries = array_sum(array_column($overall_stats['statistics'], 'total_entries'));
                    ?>
                    <div class="stats-overview">
                        <div class="stat-card total">
                            <div class="stat-number"><?php echo $total_entries; ?></div>
                            <div class="stat-label">Total Patients</div>
                        </div>
                        <div class="stat-card waiting">
                            <div class="stat-number"><?php echo $total_waiting; ?></div>
                            <div class="stat-label">Waiting</div>
                        </div>
                        <div class="stat-card in-progress">
                            <div class="stat-number"><?php echo $total_in_progress; ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="stat-number"><?php echo $total_completed; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Station Monitor Grid -->
                <div class="monitor-grid">
                    <?php foreach ($stations as $station): ?>
                        <div class="station-card <?php echo !$station['employee_id'] ? 'unassigned-station' : ''; ?> <?php echo !$station['is_active'] ? 'inactive-station' : ''; ?>">
                            <div class="station-header <?php echo !$station['is_active'] ? 'inactive' : ''; ?>">
                                <div>
                                    <h3 class="station-title">
                                        <span class="status-indicator <?php 
                                            if (!$station['is_active']) echo 'status-inactive';
                                            elseif (!$station['employee_id']) echo 'status-unassigned';
                                            else echo 'status-active';
                                        ?>"></span>
                                        <?php echo htmlspecialchars($station['station_name']); ?>
                                    </h3>
                                    <small><?php echo htmlspecialchars($station['service_name']); ?></small>
                                </div>
                                <span class="station-type"><?php echo ucfirst($station['station_type']); ?></span>
                            </div>
                            
                            <div class="station-body">
                                <!-- Employee Assignment -->
                                <?php if ($station['employee_id']): ?>
                                    <div class="employee-info">
                                        <div class="employee-name">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($station['employee_name']); ?>
                                        </div>
                                        <div class="employee-role">
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($station['employee_role'])); ?></span>
                                            <?php if ($station['shift_start'] && $station['shift_end']): ?>
                                                <span class="text-muted">
                                                    <?php echo date('g:i A', strtotime($station['shift_start'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($station['shift_end'])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="employee-info">
                                        <div class="employee-name text-muted">
                                            <i class="fas fa-user-times me-2"></i>Unassigned
                                        </div>
                                        <div class="employee-role">
                                            <span class="badge bg-warning">No Staff</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Station Status -->
                                <?php if (!$station['is_active']): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-power-off me-2"></i>Station Inactive
                                    </div>
                                <?php elseif (!$station['employee_id']): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-exclamation-triangle me-2"></i>No Staff Assigned
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
                                                Queue #<?php echo $station['current_patient']['queue_number']; ?> • 
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
                                                Queue #<?php echo $station['next_patient']['queue_number']; ?> • 
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

                <!-- Legend -->
                <div class="text-center text-muted" style="margin-top: 30px;">
                    <small>
                        <span class="status-indicator status-active"></span>Active & Assigned •
                        <span class="status-indicator status-unassigned"></span>Active but Unassigned •
                        <span class="status-indicator status-inactive"></span>Inactive Station
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh button -->
    <button type="button" class="refresh-btn" onclick="refreshMonitor()" title="Refresh Monitor">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        function refreshMonitor() {
            // Add rotation animation to refresh button
            const btn = document.querySelector('.refresh-btn i');
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                btn.style.transform = 'rotate(0deg)';
            }, 500);
            
            // Reload the page to refresh data
            window.location.reload();
        }
        
        // Auto-refresh every 10 seconds
        setInterval(function() {
            refreshMonitor();
        }, 10000);
        
        // Add pulse animation to active stations with patients
        document.addEventListener('DOMContentLoaded', function() {
            const activeStations = document.querySelectorAll('.station-card:not(.unassigned-station):not(.inactive-station)');
            activeStations.forEach(station => {
                const currentPatient = station.querySelector('.current-patient');
                if (currentPatient) {
                    station.style.animation = 'pulse 2s infinite';
                }
            });
        });
        
        // Add CSS for pulse animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { box-shadow: 0 0 0 0 rgba(0, 119, 182, 0.4); }
                70% { box-shadow: 0 0 0 10px rgba(0, 119, 182, 0); }
                100% { box-shadow: 0 0 0 0 rgba(0, 119, 182, 0); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>