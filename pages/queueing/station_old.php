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

// Get employee's active station assignment
$station_assignment = null;
$station_info = null;
$can_activate = false;

try {
    // Get employee's station assignment for today
    $station_assignment = $queueService->getActiveStationByEmployee($employee_id);
    
    if ($station_assignment) {
        $station_info = $station_assignment;
        
        // Check if station is currently inactive and can be activated
        if (!$station_assignment['is_station_active']) {
            $can_activate = true;
        }
    } else {
        $error = "You are not assigned to any station today. Please contact the administrator.";
    }
} catch (Exception $e) {
    $error = "Error retrieving station assignment: " . $e->getMessage();
}
    
    // Handle POST actions (only if user can manage the queue)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_queue) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {

                
            case 'call_next':
                $result = $queueService->callNextPatient($station_assignment['station_type'], $station_id, $employee_id);
                if ($result['success']) {
                    $message = 'Patient called successfully.';
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'complete_patient':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Service completed';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'done', 'in_progress', $employee_id, $remarks);
                if ($result['success']) {
                    $message = 'Patient service completed.';
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'skip_patient':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Skipped by staff';
                $result = $queueService->updateQueueStatus($queue_entry_id, 'skipped', 'waiting', $employee_id, $remarks);
                if ($result['success']) {
                    $message = 'Patient skipped.';
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'no_show':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $result = $queueService->updateQueueStatus($queue_entry_id, 'no_show', 'waiting', $employee_id, 'Marked as no-show');
                if ($result['success']) {
                    $message = 'Patient marked as no-show.';
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'reinstate_patient':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $result = $queueService->updateQueueStatus($queue_entry_id, 'waiting', 'skipped', $employee_id, 'Reinstated by staff');
                if ($result['success']) {
                    $message = 'Patient reinstated to queue.';
                } else {
                    $error = $result['error'];
                }
                break;
        }
    }
    
    // Get queue data for selected station
    $waiting_queue = $queueService->getStationQueue($selected_station_id, 'waiting');
    $in_progress_queue = $queueService->getStationQueue($selected_station_id, 'in_progress');
    $skipped_queue = $queueService->getStationQueue($selected_station_id, 'skipped');
    $completed_today = $queueService->getStationQueue($selected_station_id, 'done', date('Y-m-d'), 10);
    
    // Get queue statistics
    $queue_stats = $queueService->getStationQueueStats($selected_station_id);
}

// Set active page for sidebar highlighting
$activePage = 'station_view';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station View | CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Additional styles for station management */
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

        /* Breadcrumb styling */
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .breadcrumb a {
            color: var(--primary);
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

        /* Page header styling */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .page-header h1 {
            color: var(--primary);
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Card container styling */
        .card-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }
        
        .card-container .section-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .card-container .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .card-container .section-header h4 i {
            margin-right: 8px;
            color: var(--primary);
        }

        .station-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary);
        }

        .station-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .station-details {
            color: var(--secondary);
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .station-inactive {
            text-align: center;
            padding: 40px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .activation-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 15px;
        }

        .activation-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Queue statistics */
        .queue-stats {
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
        .stat-card.skipped { border-left-color: var(--danger); }

        .stat-number {
            font-size: 32px;
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

        /* Queue section styling */
        .queue-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .queue-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .queue-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .queue-count {
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .queue-item {
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .queue-item:hover {
            background-color: rgba(240, 247, 255, 0.6);
        }

        .queue-item:last-child {
            border-bottom: none;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 5px;
        }

        .queue-details {
            display: flex;
            gap: 15px;
            color: var(--secondary);
            font-size: 14px;
            flex-wrap: wrap;
        }

        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-normal { background-color: #e3f2fd; color: #1976d2; }
        .priority-priority { background-color: #fff3e0; color: #f57c00; }
        .priority-emergency { background-color: #ffebee; color: #d32f2f; }

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
        
        .btn-primary { background-color: var(--primary); color: white; }
        .btn-success { background-color: var(--success); color: white; }
        .btn-warning { background-color: var(--warning); color: #000; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-secondary { background-color: var(--secondary); color: white; }
        .btn-info { background-color: var(--info); color: white; }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .current-patient {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            border: 2px solid var(--warning);
        }

        .empty-queue {
            text-align: center;
            padding: 40px 20px;
            color: var(--secondary);
        }

        .empty-queue i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Alert styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }

        /* Table styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 14px;
        }

        table th,
        table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        table th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary);
        }

        table tbody tr:hover {
            background-color: rgba(0, 119, 182, 0.05);
        }

        /* Refresh button */
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

        /* Modal styles */
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
        
        .modal.show { display: block; }
        
        .modal-dialog {
            position: relative;
            margin: 50px auto;
            max-width: 500px;
            animation: slideDown 0.3s ease-out;
        }
        
        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body { padding: 20px; }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--secondary);
        }
        
        .btn-close:hover { color: var(--danger); }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            transition: var(--transition);
            resize: vertical;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Utility classes */
        .d-flex { display: flex; }
        .me-2 { margin-right: 8px; }
        .mb-2 { margin-bottom: 8px; }
        .text-center { text-align: center; }
        .text-muted { color: var(--secondary) !important; }

        /* Responsive design */
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .station-details { flex-direction: column; gap: 10px; }
            .queue-stats { grid-template-columns: repeat(2, 1fr); }
            .queue-item { flex-direction: column; align-items: flex-start; gap: 15px; }
            .queue-details { flex-direction: column; gap: 5px; }
        }

        @media (max-width: 480px) {
            .queue-stats { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>
    <!-- Include sidebar based on role -->
    <?php 
    $sidebar_file = '../../includes/sidebar_' . strtolower($_SESSION['role']) . '.php';
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
                <span>Station View</span>
            </div>

            <!-- Page Header -->
            <div class="page-header">
                <h1><i class="fas fa-desktop"></i> Station View</h1>
                <div class="d-flex" style="gap: 10px;">
                    <a href="dashboard.php" class="action-btn btn-secondary">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="checkin.php" class="action-btn btn-info">
                        <i class="fas fa-clipboard-check me-2"></i>Check-in
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Station Selection Dropdown -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-hospital"></i> Station Selection</h4>
                </div>
                <form method="get" style="margin-bottom: 0;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <label for="station_select" style="font-weight: 600; color: var(--primary-dark);">Select Station:</label>
                        <select name="station_id" id="station_select" class="form-control" style="max-width: 300px;" onchange="this.form.submit()">
                            <option value="">-- Choose Station --</option>
                            <?php foreach ($all_stations as $station): ?>
                                <option value="<?php echo $station['station_id']; ?>" 
                                    <?php echo ($station['station_id'] == $selected_station_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($station['station_name']) . ' (' . ucfirst($station['station_type']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if (strtolower($_SESSION['role']) !== 'admin' && !$station_assignment): ?>
                            <div class="alert" style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 8px 12px; border-radius: 4px; margin: 0; font-size: 14px;">
                                <i class="fas fa-info-circle"></i> You are not currently assigned to any active station.
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($selected_station_id && $assigned_employee_info): ?>
                <!-- Station Information -->
                <div class="station-info">
                    <div class="station-name">
                        <i class="fas fa-hospital me-2"></i><?php echo htmlspecialchars($assigned_employee_info['station_name']); ?>
                        <?php if (!$can_manage_queue): ?>
                            <span style="background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">VIEW ONLY</span>
                        <?php endif; ?>
                    </div>
                    <div class="station-details">
                        <span><i class="fas fa-cogs me-2"></i><strong>Type:</strong> <?php echo ucfirst($assigned_employee_info['station_type']); ?></span>
                        <span><i class="fas fa-medical-kit me-2"></i><strong>Service:</strong> <?php echo htmlspecialchars($assigned_employee_info['service_name']); ?></span>
                        <?php if ($assigned_employee_info['employee_name']): ?>
                            <span><i class="fas fa-user me-2"></i><strong>Assigned to:</strong> <?php echo htmlspecialchars($assigned_employee_info['employee_name']); ?> (<?php echo ucfirst($assigned_employee_info['employee_role']); ?>)</span>
                        <?php else: ?>
                            <span><i class="fas fa-user-slash me-2"></i><strong>Assigned to:</strong> <em>No employee assigned</em></span>
                        <?php endif; ?>
                        <?php if ($assigned_employee_info['shift_start_time'] && $assigned_employee_info['shift_end_time']): ?>
                            <span><i class="fas fa-clock me-2"></i><strong>Shift:</strong> 
                                <?php echo date('g:i A', strtotime($assigned_employee_info['shift_start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($assigned_employee_info['shift_end_time'])); ?>
                            </span>
                        <?php endif; ?>
                        <span><i class="fas fa-calendar me-2"></i><strong>Date:</strong> <?php echo date('M j, Y'); ?></span>
                        <?php if (strtolower($_SESSION['role']) === 'admin'): ?>
                            <span><i class="fas fa-crown me-2"></i><strong>Access Level:</strong> Administrator (Full Access)</span>
                        <?php elseif ($can_manage_queue): ?>
                            <span><i class="fas fa-check-circle me-2"></i><strong>Access Level:</strong> Queue Manager</span>
                        <?php else: ?>
                            <span><i class="fas fa-eye me-2"></i><strong>Access Level:</strong> View Only</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Queue Management Content -->
                    <!-- Queue Statistics -->
                    <div class="queue-stats">
                        <div class="stat-card waiting">
                            <div class="stat-number"><?php echo count($waiting_queue); ?></div>
                            <div class="stat-label">Waiting</div>
                        </div>
                        <div class="stat-card in-progress">
                            <div class="stat-number"><?php echo count($in_progress_queue); ?></div>
                            <div class="stat-label">In Progress</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="stat-number"><?php echo count($completed_today); ?></div>
                            <div class="stat-label">Completed Today</div>
                        </div>
                        <div class="stat-card skipped">
                            <div class="stat-number"><?php echo count($skipped_queue); ?></div>
                            <div class="stat-label">Skipped</div>
                        </div>
                    </div>

                    <!-- Current Patient (In Progress) -->
                    <?php if (!empty($in_progress_queue)): ?>
                        <div class="queue-section">
                            <div class="queue-header">
                                <h3 class="queue-title"><i class="fas fa-user-clock me-2"></i>Current Patient</h3>
                                <span class="queue-count"><?php echo count($in_progress_queue); ?></span>
                            </div>
                            <?php foreach ($in_progress_queue as $patient): ?>
                                <div class="queue-item current-patient">
                                    <div class="patient-info">
                                        <div class="patient-name">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                        </div>
                                        <div class="queue-details">
                                            <span><i class="fas fa-hashtag me-2"></i>Queue: <?php echo htmlspecialchars($patient['queue_number']); ?></span>
                                            <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                <?php echo ucfirst($patient['priority_level']); ?>
                                            </span>
                                            <span><i class="fas fa-clock me-2"></i>Started: <?php echo date('g:i A', strtotime($patient['time_started'])); ?></span>
                                            <?php if ($patient['appointment_id']): ?>
                                                <span><i class="fas fa-calendar-check me-2"></i>Appointment #<?php echo $patient['appointment_id']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <?php if ($can_manage_queue): ?>
                                            <button type="button" class="action-btn btn-success" onclick="openCompleteModal(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-check me-2"></i>Complete
                                            </button>
                                            <button type="button" class="action-btn btn-warning" onclick="openSkipModal(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-forward me-2"></i>Skip
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="action-btn btn-secondary" disabled title="You are not assigned to this station">
                                                <i class="fas fa-lock me-2"></i>View Only
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Waiting Queue -->
                    <div class="queue-section">
                        <div class="queue-header">
                            <h3 class="queue-title"><i class="fas fa-hourglass-half me-2"></i>Waiting Queue</h3>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <span class="queue-count"><?php echo count($waiting_queue); ?></span>
                                <?php if ($can_manage_queue && !empty($waiting_queue) && empty($in_progress_queue)): ?>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="action" value="call_next">
                                        <button type="submit" class="action-btn btn-primary">
                                            <i class="fas fa-phone me-2"></i>Call Next Patient
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($waiting_queue)): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Queue Code</th>
                                            <th>Patient Name</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Check-in Time</th>
                                            <th style="width: 200px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($waiting_queue as $patient): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($patient['queue_number']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($patient['patient_name']); ?></td>
                                                <td>
                                                    <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                        <?php echo ucfirst($patient['priority_level']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: #17a2b8; color: white;">
                                                        <?php echo ucfirst($patient['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('g:i A', strtotime($patient['time_in'])); ?></td>
                                                <td>
                                                    <?php if ($can_manage_queue): ?>
                                                        <?php if (empty($in_progress_queue)): // Only allow next if no current patient ?>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="call_next">
                                                                <button type="submit" class="action-btn btn-primary">
                                                                    <i class="fas fa-arrow-right me-2"></i>Call Next
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button type="button" class="action-btn btn-warning" onclick="openSkipModal(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-forward me-2"></i>Skip
                                                        </button>
                                                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark <?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?> as no-show?');">
                                                            <input type="hidden" name="action" value="no_show">
                                                            <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                                            <button type="submit" class="action-btn btn-danger">
                                                                <i class="fas fa-user-times me-2"></i>No Show
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="action-btn btn-secondary" disabled title="You are not assigned to this station">
                                                            <i class="fas fa-lock me-2"></i>View Only
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-queue">
                                <i class="fas fa-clipboard-list"></i>
                                <p>No patients waiting in queue</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Skipped Patients -->
                    <?php if (!empty($skipped_queue)): ?>
                        <div class="queue-section">
                            <div class="queue-header">
                                <h3 class="queue-title"><i class="fas fa-user-minus me-2"></i>Skipped Patients</h3>
                                <span class="queue-count"><?php echo count($skipped_queue); ?></span>
                            </div>
                            <?php foreach ($skipped_queue as $patient): ?>
                                <div class="queue-item">
                                    <div class="patient-info">
                                        <div class="patient-name">
                                            <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['patient_name']); ?>
                                        </div>
                                        <div class="queue-details">
                                            <span><i class="fas fa-hashtag me-2"></i>Queue: <?php echo htmlspecialchars($patient['queue_number']); ?></span>
                                            <span class="priority-badge priority-<?php echo $patient['priority_level']; ?>">
                                                <?php echo ucfirst($patient['priority_level']); ?>
                                            </span>
                                            <span><i class="fas fa-comment me-2"></i><?php echo htmlspecialchars($patient['remarks'] ?: 'No remarks'); ?></span>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <?php if ($can_manage_queue): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="reinstate_patient">
                                                <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                                <button type="submit" class="action-btn btn-info">
                                                    <i class="fas fa-undo me-2"></i>Reinstate
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button type="button" class="action-btn btn-secondary" disabled title="You are not assigned to this station">
                                                <i class="fas fa-lock me-2"></i>View Only
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Completed -->
                    <?php if (!empty($completed_today)): ?>
                        <div class="card-container">
                            <div class="section-header">
                                <h4><i class="fas fa-check-circle"></i> Recent Completed (Last 10)</h4>
                            </div>
                            <div class="table-responsive">
                                <table>
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
                                        <?php foreach (array_reverse($completed_today) as $patient): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($patient['queue_number']); ?></td>
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
                    <?php endif; ?>

            <?php else: ?>
                <!-- No Station Selected -->
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>No Station Selected:</strong> Please select a station from the dropdown above to view its queue.
                </div>
            <?php endif; ?>

            <!-- Refresh Button -->
            <button type="button" class="refresh-btn" onclick="refreshPage()" title="Refresh Queue">
                <i class="fas fa-sync-alt"></i>
            </button>
        </div>
    </div>
    
    <!-- Complete Patient Modal -->
    <div class="modal" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Complete Patient Service</h5>
                        <button type="button" class="btn-close" onclick="closeModal('completeModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="complete_patient">
                        <input type="hidden" name="queue_entry_id" id="complete_queue_entry_id">
                        
                        <p>Complete service for: <strong id="complete_patient_name"></strong></p>
                        
                        <div class="mb-2">
                            <label>Service Notes (Optional):</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any notes about the service provided..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('completeModal')">Cancel</button>
                        <button type="submit" class="action-btn btn-success">
                            <i class="fas fa-check me-2"></i>Complete Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Skip Patient Modal -->
    <div class="modal" id="skipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Skip Patient</h5>
                        <button type="button" class="btn-close" onclick="closeModal('skipModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="skip_patient">
                        <input type="hidden" name="queue_entry_id" id="skip_queue_entry_id">
                        
                        <p>Skip patient: <strong id="skip_patient_name"></strong></p>
                        
                        <div class="mb-2">
                            <label>Reason for skipping:</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="Please provide a reason for skipping this patient..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('skipModal')">Cancel</button>
                        <button type="submit" class="action-btn btn-warning">
                            <i class="fas fa-forward me-2"></i>Skip Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openCompleteModal(queueEntryId, patientName) {
            document.getElementById('complete_queue_entry_id').value = queueEntryId;
            document.getElementById('complete_patient_name').textContent = patientName;
            document.getElementById('completeModal').classList.add('show');
        }
        
        function openSkipModal(queueEntryId, patientName) {
            document.getElementById('skip_queue_entry_id').value = queueEntryId;
            document.getElementById('skip_patient_name').textContent = patientName;
            document.getElementById('skipModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        function refreshPage() {
            // Add rotation animation to refresh button
            const btn = document.querySelector('.refresh-btn i');
            btn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                btn.style.transform = 'rotate(0deg)';
            }, 500);
            
            // Reload the page
            window.location.reload();
        }
        
        // Auto-refresh every 60 seconds
        setInterval(function() {
            refreshPage();
        }, 60000);
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(event) {
            // F5 or Ctrl+R to refresh
            if (event.key === 'F5' || (event.ctrlKey && event.key === 'r')) {
                event.preventDefault();
                refreshPage();
            }
            
            // Escape to close modals
            if (event.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    modal.classList.remove('show');
                });
            }
        });
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Station view loaded');
            
            // Focus on first action button if available
            const firstButton = document.querySelector('.action-btn');
            if (firstButton) {
                firstButton.focus();
            }
        });
    </script>
</body>
</html>