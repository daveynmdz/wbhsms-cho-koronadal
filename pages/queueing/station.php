<?php
/**
 * Station Queue Management Interface
 * Purpose: Individual station interface for healthcare providers to manage their assigned queue
 */

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
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
$queueService = new QueueManagementService($conn);

// Check if role is authorized for queue management
$allowed_roles = ['doctor', 'nurse', 'pharmacist', 'laboratory_tech', 'cashier', 'records_officer', 'bhw', 'admin'];
if (!in_array(strtolower($employee_role), $allowed_roles)) {
    header("Location: ../management/" . strtolower($employee_role) . "/dashboard.php");
    exit();
}

// Initialize variables
$selected_station_id = null;
$station_assignment = null;
$all_stations = [];
$can_manage_queue = false;
$assigned_employee_info = null;

// Get all stations for dropdown (visible to all roles)
$all_stations = $queueService->getAllStationsWithAssignments();

// Determine station selection logic based on role
if (strtolower($_SESSION['role']) === 'admin') {
    // Admin can view any station
    if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
        $selected_station_id = intval($_GET['station_id']);
    } elseif (!empty($all_stations)) {
        // Default to first station if none selected
        $selected_station_id = $all_stations[0]['station_id'];
    }
    $can_manage_queue = true; // Admin can always manage
} else {
    // Staff members - check their assignment first
    $station_assignment = $queueService->getActiveStationByEmployee($employee_id);
    
    if ($station_assignment) {
        // Staff has assignment - default to their station but allow viewing others
        if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
            $selected_station_id = intval($_GET['station_id']);
            // Can only manage their own assigned station
            $can_manage_queue = ($selected_station_id == $station_assignment['station_id']);
        } else {
            $selected_station_id = $station_assignment['station_id'];
            $can_manage_queue = true;
        }
    } else {
        // Staff has no assignment - can view but not manage
        if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
            $selected_station_id = intval($_GET['station_id']);
        } elseif (!empty($all_stations)) {
            $selected_station_id = $all_stations[0]['station_id'];
        }
        $can_manage_queue = false;
    }
}

// Get selected station details and assigned employee info
if ($selected_station_id) {
    foreach ($all_stations as $station) {
        if ($station['station_id'] == $selected_station_id) {
            $assigned_employee_info = $station;
            break;
        }
    }
}

// Handle POST actions (only if user can manage the queue)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_queue && $selected_station_id) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'call_next':
                $result = $queueService->callNextPatient($assigned_employee_info['station_type'], $selected_station_id, $employee_id);
                if ($result['success']) {
                    $message = "Patient called successfully: " . $result['patient_name'];
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'complete_service':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Service completed';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'done', 'in_progress', $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient service completed successfully.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'skip_patient':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Patient skipped';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'skipped', 'waiting', $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient skipped.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'no_show':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $result = $queueService->updateQueueStatus($queue_entry_id, 'no_show', 'waiting', $employee_id, 'Marked as no-show');
                if ($result['success']) {
                    $message = "Patient marked as no-show.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'reinstate':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $result = $queueService->updateQueueStatus($queue_entry_id, 'waiting', 'skipped', $employee_id, 'Patient reinstated');
                if ($result['success']) {
                    $message = "Patient reinstated to queue.";
                } else {
                    $error = $result['error'];
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Action failed: " . $e->getMessage();
    }
}

// Get queue data for selected station
$waiting_queue = [];
$in_progress_queue = [];
$completed_queue = [];
$skipped_queue = [];
$queue_stats = ['waiting_count' => 0, 'in_progress_count' => 0, 'completed_count' => 0, 'skipped_count' => 0];

if ($selected_station_id) {
    try {
        $waiting_queue = $queueService->getStationQueue($selected_station_id, 'waiting');
        $in_progress_queue = $queueService->getStationQueue($selected_station_id, 'in_progress');
        $completed_queue = $queueService->getStationQueue($selected_station_id, 'done', date('Y-m-d'), 10);
        $skipped_queue = $queueService->getStationQueue($selected_station_id, 'skipped');
        $queue_stats = $queueService->getStationQueueStats($selected_station_id);
    } catch (Exception $e) {
        $error = "Error loading queue data: " . $e->getMessage();
    }
}

// Set active page for sidebar highlighting
$activePage = 'queue_station';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Queue Management - CHO Koronadal WBHSMS</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    
    <style>
        :root {
            --primary-color: #0077b6;
            --primary-dark: #023e8a;
            --secondary-color: #48cae4;
            --success-color: #52b788;
            --warning-color: #ffba08;
            --danger-color: #e63946;
            --light-gray: #f8f9fa;
            --border-color: #dee2e6;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-radius: 8px;
            --box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        .homepage {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            background-color: #f8f9fa;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .content-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .station-info {
            background: linear-gradient(135deg, var(--light-gray), #e9ecef);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }

        .station-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .station-details {
            color: var(--text-secondary);
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--box-shadow);
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .stat-card.waiting { border-left-color: #17a2b8; }
        .stat-card.in-progress { border-left-color: var(--warning-color); }
        .stat-card.completed { border-left-color: var(--success-color); }
        .stat-card.skipped { border-left-color: var(--danger-color); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .queue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .queue-table th,
        .queue-table td {
            padding: 0.875rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .queue-table th {
            background-color: var(--light-gray);
            font-weight: 600;
            color: var(--primary-dark);
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .queue-table tbody tr:hover {
            background-color: rgba(0, 119, 182, 0.05);
        }

        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-normal {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .priority-priority {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .priority-emergency {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-waiting {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-in_progress {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .status-done {
            background-color: #e8f5e8;
            color: #2e7d32;
        }

        .status-skipped {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            color: white;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0 0.25rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #2d6a4f);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #e09f00);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #c5303e);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .empty-queue {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-queue i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .current-patient-highlight {
            background: linear-gradient(135deg, #fff3e0, #ffcc02) !important;
            border: 2px solid var(--warning-color);
        }

        .inactive-station {
            text-align: center;
            padding: 3rem 2rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
        }

        .activate-station-btn {
            background: linear-gradient(135deg, var(--success-color), #2d6a4f);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1rem;
        }

        .activate-station-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: var(--transition);
            z-index: 1000;
        }

        .refresh-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
            padding: 0;
            background: none;
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb i.fas.fa-chevron-right {
            color: #6c757d;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .station-details {
                flex-direction: column;
                gap: 0.5rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Include sidebar based on role -->
    <?php 
    $sidebar_file = '../../includes/sidebar_' . strtolower($employee_role) . '.php';
    if (file_exists($sidebar_file)) {
        include $sidebar_file;
    } else {
        include '../../includes/sidebar_admin.php';
    }
    ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Include topbar -->
            <?php include '../../includes/topbar.php'; ?>

            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb" style="margin-top: 50px;">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Queue Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Station Management</span>
            </div>

            <!-- Page Header with Station Dropdown -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-desktop"></i>
                    Station Queue Management
                </h1>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Station Selection Dropdown -->
                    <form method="get" style="margin: 0;">
                        <select name="station_id" class="form-control" style="min-width: 250px;" onchange="this.form.submit()">
                            <option value="">-- Select Station --</option>
                            <?php foreach ($all_stations as $station): ?>
                                <option value="<?php echo $station['station_id']; ?>" 
                                    <?php echo ($station['station_id'] == $selected_station_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['station_name']) . ' (' . ucfirst($station['station_type']) . ')'; ?>
                                    <?php if ($station['employee_name']): ?>
                                        - <?php echo htmlspecialchars($station['employee_name']); ?>
                                    <?php else: ?>
                                        - Unassigned
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <button type="button" class="action-btn btn-info" onclick="refreshQueue()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$station_assignment): ?>
                <!-- No Station Assignment -->
                <div class="content-card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No Station Assignment:</strong> You are not assigned to any station today. Please contact the administrator to get your station assignment.
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Station Information -->
                <div class="station-info">
                    <div class="station-name">
                        <i class="fas fa-hospital-user"></i>
                        <?php echo htmlspecialchars($station_info['station_name']); ?>
                        <span class="status-badge" style="background: #28a745; color: white; margin-left: 1rem;">ASSIGNED</span>
                    </div>
                    <div class="station-details">
                        <span><i class="fas fa-cogs"></i> <strong>Type:</strong> <?php echo ucfirst($station_info['station_type']); ?></span>
                        <span><i class="fas fa-stethoscope"></i> <strong>Service:</strong> <?php echo htmlspecialchars($station_info['service_name']); ?></span>
                        <span><i class="fas fa-user"></i> <strong>Assigned to:</strong> <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></span>
                        <?php if ($station_info['shift_start_time'] && $station_info['shift_end_time']): ?>
                            <span><i class="fas fa-clock"></i> <strong>Shift:</strong> 
                                <?php echo date('g:i A', strtotime($station_info['shift_start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($station_info['shift_end_time'])); ?>
                            </span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar"></i> <strong>Date:</strong> <?php echo date('M j, Y'); ?></span>
                    </div>
                </div>

                <!-- Queue Statistics -->
                    <div class="stats-grid">
                        <div class="stat-card waiting">
                            <div class="stat-number"><?php echo $queue_stats['waiting_count'] ?? 0; ?></div>
                            <div class="stat-label">Waiting</div>
                        </div>
                        <div class="stat-card in-progress">
                            <div class="stat-number"><?php echo $queue_stats['in_progress_count'] ?? 0; ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="stat-number"><?php echo $queue_stats['completed_count'] ?? 0; ?></div>
                            <div class="stat-label">Completed Today</div>
                        </div>
                        <div class="stat-card skipped">
                            <div class="stat-number"><?php echo $queue_stats['skipped_count'] ?? 0; ?></div>
                            <div class="stat-label">Skipped</div>
                        </div>
                    </div>

                    <!-- Current Patient (In Progress) -->
                    <?php if (!empty($in_progress_queue)): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-user-clock"></i> Current Patient</h3>
                                <span class="status-badge" style="background: var(--warning-color); color: #000;">
                                    <?php echo count($in_progress_queue); ?> In Progress
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Queue Code</th>
                                                <th>Patient Name</th>
                                                <th>Priority</th>
                                                <th>Started Time</th>
                                                <th>Duration</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($in_progress_queue as $patient): ?>
                                                <tr class="current-patient-highlight">
                                                    <td><strong><?php echo htmlspecialchars($patient['queue_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                                    <td>
                                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                            <?php echo ucfirst($patient['priority_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('g:i A', strtotime($patient['time_started'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $start_time = strtotime($patient['time_started']);
                                                        $duration = floor((time() - $start_time) / 60);
                                                        echo $duration . ' min';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="action-btn btn-success" 
                                                                onclick="completeService(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Waiting Queue -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3><i class="fas fa-hourglass-half"></i> Waiting Queue</h3>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <span class="status-badge" style="background: #17a2b8; color: white;">
                                    <?php echo count($waiting_queue); ?> Waiting
                                </span>
                                <?php if (!empty($waiting_queue) && empty($in_progress_queue)): ?>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="action" value="call_next">
                                        <button type="submit" class="action-btn btn-primary">
                                            <i class="fas fa-phone"></i> Call Next Patient
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($waiting_queue)): ?>
                                <div class="table-responsive">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Queue Code</th>
                                                <th>Patient Name</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Check-in Time</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($waiting_queue as $patient): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($patient['queue_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                                    <td>
                                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                            <?php echo ucfirst($patient['priority_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo $patient['status']; ?>">
                                                            <?php echo ucfirst($patient['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('g:i A', strtotime($patient['time_in'])); ?></td>
                                                    <td>
                                                        <?php if (empty($in_progress_queue)): ?>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="call_next">
                                                                <button type="submit" class="action-btn btn-primary">
                                                                    <i class="fas fa-arrow-right"></i> Call Next
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button type="button" class="action-btn btn-warning" 
                                                                onclick="skipPatient(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-forward"></i> Skip
                                                        </button>
                                                        <form method="post" style="display: inline;" 
                                                              onsubmit="return confirm('Mark <?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?> as no-show?');">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                                            <button type="submit" class="action-btn btn-danger">
                                                                <i class="fas fa-user-times"></i> No Show
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-queue">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No Patients Waiting</h4>
                                    <p>There are currently no patients in the waiting queue.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Skipped Patients -->
                    <?php if (!empty($skipped_queue)): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-user-minus"></i> Skipped Patients</h3>
                                <span class="status-badge" style="background: var(--danger-color); color: white;">
                                    <?php echo count($skipped_queue); ?> Skipped
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Queue Code</th>
                                                <th>Patient Name</th>
                                                <th>Priority</th>
                                                <th>Skip Time</th>
                                                <th>Remarks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($skipped_queue as $patient): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($patient['queue_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                                    <td>
                                                        <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                            <?php echo ucfirst($patient['priority_level']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('g:i A', strtotime($patient['updated_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($patient['remarks'] ?: 'No remarks'); ?></td>
                                                    <td>
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="action" value="reinstate">
                                                            <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                                            <button type="submit" class="action-btn btn-info">
                                                                <i class="fas fa-undo"></i> Reinstate
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Completed -->
                    <?php if (!empty($completed_queue)): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <h3><i class="fas fa-check-circle"></i> Recent Completed (Last 10)</h3>
                                <span class="status-badge" style="background: var(--success-color); color: white;">
                                    <?php echo count($completed_queue); ?> Completed
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Queue Code</th>
                                                <th>Patient Name</th>
                                                <th>Completed Time</th>
                                                <th>Duration</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_reverse($completed_queue) as $patient): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($patient['queue_number']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($patient['time_completed'])); ?></td>
                                                    <td>
                                                        <?php if ($patient['turnaround_time']): ?>
                                                            <?php echo $patient['turnaround_time']; ?> min
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($patient['remarks'] ?: '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
            <?php endif; ?>

            <!-- Refresh Button -->
            <button type="button" class="refresh-btn" onclick="refreshQueue()" title="Refresh Queue">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        function refreshQueue() {
            // Add rotation animation to refresh button
            const btn = document.querySelector('.refresh-btn i');
            btn.style.transition = 'transform 0.5s ease';
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                btn.style.transform = 'rotate(0deg)';
            }, 500);
            
            // Reload the page
            window.location.reload();
        }

        function completeService(queueEntryId, patientName) {
            const remarks = prompt(`Complete service for ${patientName}.\n\nOptional service notes:`, '');
            if (remarks !== null) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="complete_service">
                    <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                    <input type="hidden" name="remarks" value="${remarks}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function skipPatient(queueEntryId, patientName) {
            const reason = prompt(`Skip patient: ${patientName}\n\nPlease provide a reason for skipping:`, '');
            if (reason !== null && reason.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="skip_patient">
                    <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                    <input type="hidden" name="remarks" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (reason !== null) {
                alert('Please provide a reason for skipping the patient.');
            }
        }

        // Auto-refresh every 60 seconds
        setInterval(function() {
            refreshQueue();
        }, 60000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // F5 to refresh
            if (event.key === 'F5') {
                event.preventDefault();
                refreshQueue();
            }
        });

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Station Queue Management loaded');
        });
    </script>
</body>
</html>