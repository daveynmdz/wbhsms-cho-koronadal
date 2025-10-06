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
        /* Admin Monitor specific styles - MATCHING CHO THEME */
        .admin-monitor-container {
            /* CHO Theme Variables - Matching dashboard.php */
            --cho-primary: #0077b6;
            --cho-primary-dark: #03045e;
            --cho-secondary: #6c757d;
            --cho-success: #2d6a4f;
            --cho-info: #17a2b8;
            --cho-warning: #ffc107;
            --cho-danger: #d00000;
            --cho-light: #f8f9fa;
            --cho-border: #dee2e6;
            --cho-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --cho-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --cho-border-radius: 0.5rem;
            --cho-border-radius-lg: 1rem;
            --cho-transition: all 0.3s ease;
        }

        /* Breadcrumb Navigation - exactly matching dashboard */
        .admin-monitor-container .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--cho-secondary);
        }

        .admin-monitor-container .breadcrumb a {
            color: var(--cho-primary);
            text-decoration: none;
            transition: var(--cho-transition);
        }

        .admin-monitor-container .breadcrumb a:hover {
            color: var(--cho-primary-dark);
            text-decoration: underline;
        }

        /* Page header styling - exactly matching dashboard */
        .admin-monitor-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
            padding: 25px;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow-lg);
        }

        .admin-monitor-container .page-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-monitor-container .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .monitor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .station-card {
            background: white;
            border-radius: var(--cho-border-radius-lg);
            box-shadow: var(--cho-shadow);
            border: 2px solid var(--cho-border);
            overflow: hidden;
            transition: var(--cho-transition);
        }

        .station-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--cho-shadow-lg);
            border-color: var(--cho-primary);
        }

        .station-header {
            background: linear-gradient(135deg, var(--cho-primary), var(--cho-primary-dark));
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .station-header.inactive {
            background: linear-gradient(135deg, var(--cho-secondary), #495057);
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
            font-size: 20px;
            font-weight: 700;
            color: var(--cho-primary-dark);
        }

        .employee-info {
            background: var(--cho-light);
            border-radius: var(--cho-border-radius);
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--cho-border);
        }

        .employee-name {
            font-weight: 600;
            color: var(--cho-primary-dark);
            margin-bottom: 5px;
            font-size: 16px;
        }

        .employee-role {
            font-size: 14px;
            color: var(--cho-secondary);
        }

        .current-patient {
            background: linear-gradient(135deg, #fff3e0, var(--cho-warning));
            border-radius: var(--cho-border-radius);
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid #ffd54f;
        }

        .patient-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
            color: var(--cho-primary-dark);
        }

        .patient-details {
            font-size: 14px;
            color: var(--cho-secondary);
        }

        .next-patient {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: var(--cho-border-radius);
            padding: 15px;
            border: 2px solid var(--cho-info);
        }

        .unassigned-station {
            opacity: 0.6;
        }

        .inactive-station {
            opacity: 0.5;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--cho-border-radius-lg);
            padding: 25px;
            box-shadow: var(--cho-shadow);
            border: 2px solid var(--cho-border);
            transition: var(--cho-transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--cho-primary);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--cho-shadow-lg);
            border-color: var(--cho-primary);
        }

        .stat-card.waiting::before { background: var(--cho-info); }
        .stat-card.in-progress::before { background: var(--cho-warning); }
        .stat-card.completed::before { background: var(--cho-success); }
        .stat-card.total::before { background: var(--cho-primary); }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--cho-primary-dark);
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: var(--cho-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Date selector styling */
        .admin-monitor-container .date-selector {
            background: white;
            padding: 20px;
            border-radius: var(--cho-border-radius);
            box-shadow: var(--cho-shadow);
            border: 1px solid var(--cho-border);
            margin-bottom: 25px;
        }

        .admin-monitor-container .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--cho-border);
            border-radius: var(--cho-border-radius);
            font-size: 14px;
            transition: var(--cho-transition);
        }
        
        .admin-monitor-container .form-control:focus {
            outline: none;
            border-color: var(--cho-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        .admin-monitor-container .d-flex {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .admin-monitor-container .action-btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--cho-border-radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--cho-transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }
        
        .admin-monitor-container .btn-primary {
            background: linear-gradient(135deg, var(--cho-primary) 0%, var(--cho-primary-dark) 100%);
            color: white;
        }

        .admin-monitor-container .btn-secondary {
            background: linear-gradient(135deg, var(--cho-secondary) 0%, #495057 100%);
            color: white;
        }

        .admin-monitor-container .btn-outline {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .admin-monitor-container .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--cho-shadow-lg);
            color: white;
            text-decoration: none;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--cho-primary), var(--cho-primary-dark));
            color: white;
            border: none;
            font-size: 22px;
            cursor: pointer;
            box-shadow: var(--cho-shadow-lg);
            transition: var(--cho-transition);
            z-index: 1000;
        }

        .refresh-btn:hover {
            background: linear-gradient(135deg, var(--cho-primary-dark), #001d3d);
            transform: scale(1.1);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active { background-color: var(--cho-success); }
        .status-inactive { background-color: var(--cho-danger); }
        .status-unassigned { background-color: var(--cho-secondary); }

        .badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .bg-success {
            background-color: var(--cho-success);
            color: white;
        }
        
        .bg-danger {
            background-color: var(--cho-danger);
            color: white;
        }
        
        .bg-warning {
            background-color: var(--cho-warning);
            color: #000;
        }
        
        .bg-secondary {
            background-color: var(--cho-secondary);
            color: white;
        }

        .d-flex { display: flex; }
        .me-2 { margin-right: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--cho-secondary) !important; }

        @media (max-width: 768px) {
            .admin-monitor-container .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }

            .admin-monitor-container .header-actions {
                justify-content: center;
            }

            .admin-monitor-container .action-btn {
                min-width: 120px;
            }

            .monitor-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .stats-overview {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 15px;
            }

            .stat-card {
                padding: 20px;
            }

            .station-header {
                padding: 15px 20px;
            }
        }

        @media (max-width: 576px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }

            .admin-monitor-container .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .admin-monitor-container .action-btn {
                width: 100%;
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
                <div class="admin-monitor-container">
                    <!-- Breadcrumb Navigation - matching dashboard -->
                    <div class="breadcrumb" style="margin-top: 50px;">
                        <a href="../management/admin/dashboard.php">Admin Dashboard</a>
                        <span>›</span>
                        <a href="dashboard.php">Queue Management Dashboard</a>
                        <span>›</span>
                        <span>Master View</span>
                    </div>

                    <!-- Page Header -->
                    <div class="page-header">
                        <h1>
                            <i class="fas fa-tv"></i>
                            Master Queue Monitor
                        </h1>
                        <div class="header-actions">
                            <a href="staff_assignments.php" class="action-btn btn-secondary">
                                <i class="fas fa-users-cog"></i>
                                <span>Staff Assignments</span>
                            </a>
                            <a href="public_display_selector.php" class="action-btn btn-outline">
                                <i class="fas fa-display"></i>
                                <span>Display Launcher</span>
                            </a>
                        </div>
                    </div>

                    <!-- Date selector -->
                    <div class="date-selector">
                        <form method="get" class="d-flex">
                            <label for="date" style="font-weight: 600; color: var(--cho-primary-dark);">Select Date:</label>
                            <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control" style="max-width: 200px;">
                            <button type="submit" class="action-btn btn-primary">
                                <i class="fas fa-search"></i>
                                <span>View</span>
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
                                            <?php if (isset($station['shift_start_time']) && isset($station['shift_end_time']) && $station['shift_start_time'] && $station['shift_end_time']): ?>
                                                <span class="text-muted">
                                                    <?php echo date('g:i A', strtotime($station['shift_start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($station['shift_end_time'])); ?>
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
                    <div class="text-center text-muted" style="margin-top: 30px; padding: 15px; background: var(--cho-light); border-radius: var(--cho-border-radius);">
                        <small style="font-size: 14px;">
                            <span class="status-indicator status-active"></span>Active & Assigned •
                            <span class="status-indicator status-unassigned"></span>Active but Unassigned •
                            <span class="status-indicator status-inactive"></span>Inactive Station
                        </small>
                    </div>
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