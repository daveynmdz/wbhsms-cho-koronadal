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

// Enhanced role-based access control
$is_admin = (strtolower($_SESSION['role']) === 'admin');
$station_assignment = null;
$can_manage_queue = false;
$can_toggle_station = false;
$access_level = 'view-only'; // default access level

// Get user's station assignment (if any)
if (!$is_admin) {
    $station_assignment = $queueService->getActiveStationByEmployee($employee_id);
}

// Determine access permissions and station selection
if ($is_admin) {
    // Admin: Can access and manage all stations
    $access_level = 'full-admin';
    $can_manage_queue = true;
    $can_toggle_station = true;
    
    if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
        $selected_station_id = intval($_GET['station_id']);
    } elseif (!empty($all_stations)) {
        $selected_station_id = $all_stations[0]['station_id'];
    }
} else {
    // Regular staff and other roles
    if ($station_assignment && in_array(strtolower($employee_role), $allowed_roles)) {
        // Staff with assignment: Can manage their assigned station, view others
        if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
            $selected_station_id = intval($_GET['station_id']);
            if ($selected_station_id == $station_assignment['station_id']) {
                $access_level = 'manage-assigned';
                $can_manage_queue = true;
                $can_toggle_station = true;
            } else {
                $access_level = 'view-only';
                $can_manage_queue = false;
                $can_toggle_station = false;
            }
        } else {
            // Default to their assigned station
            $selected_station_id = $station_assignment['station_id'];
            $access_level = 'manage-assigned';
            $can_manage_queue = true;
            $can_toggle_station = true;
        }
    } else {
        // Staff without assignment or unauthorized roles: View-only
        $access_level = 'view-only';
        $can_manage_queue = false;
        $can_toggle_station = false;
        
        if (isset($_GET['station_id']) && !empty($_GET['station_id'])) {
            $selected_station_id = intval($_GET['station_id']);
        } elseif (!empty($all_stations)) {
            $selected_station_id = $all_stations[0]['station_id'];
        }
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

// Handle station toggle (for admin and assigned staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_station']) && $can_toggle_station) {
    $station_id = intval($_POST['station_id']);
    $is_open = intval($_POST['is_open']);
    
    // Verify user has permission for this specific station
    $can_toggle_this_station = $is_admin || ($station_assignment && $station_assignment['station_id'] == $station_id);
    
    if ($can_toggle_this_station) {
        $stmt = $conn->prepare("UPDATE stations SET is_open = ? WHERE station_id = ?");
        $stmt->bind_param("ii", $is_open, $station_id);
        
        if ($stmt->execute()) {
            $message = $is_open ? "Station opened successfully" : "Station closed successfully";
        } else {
            $error = "Failed to update station status";
        }
        $stmt->close();
    } else {
        $error = "You don't have permission to toggle this station";
    }
}

// Handle POST actions (only if user can manage the queue)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_queue && $selected_station_id && !isset($_POST['toggle_station'])) {
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
                
            case 'route_to_lab':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Referred to laboratory for tests';
                $result = $queueService->routePatientToStation($queue_entry_id, 'lab', $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient successfully routed to Laboratory.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'route_to_pharmacy':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Prescription provided - proceed to pharmacy';
                $result = $queueService->routePatientToStation($queue_entry_id, 'pharmacy', $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient successfully routed to Pharmacy.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'complete_visit':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Visit completed - no further treatment needed';
                $result = $queueService->completePatientVisit($queue_entry_id, $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient visit completed successfully.";
                } else {
                    $error = $result['error'];
                }
                break;
                
            case 'return_to_doctor':
                $queue_entry_id = intval($_POST['queue_entry_id']);
                $remarks = $_POST['remarks'] ?? 'Tests completed - returning to doctor for consultation';
                $result = $queueService->routePatientToStation($queue_entry_id, 'consultation', $employee_id, $remarks);
                if ($result['success']) {
                    $message = "Patient successfully returned to Doctor.";
                } else {
                    $error = $result['error'];
                }
                break;            case 'skip_patient':
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
        /* Station Management specific styles - MATCHING DASHBOARD TEMPLATE */
        .queue-station-container {
            /* CHO Theme Variables - Matching dashboard.php */
            --primary: #0077b6;
            --primary-dark: #03045e;
            --secondary: #6c757d;
            --success: #2d6a4f;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #d00000;
            --light: #f8f9fa;
            --dark: #212529;
            --white: #ffffff;
            --border: #dee2e6;
            --shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --border-radius: 0.5rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s ease;
            --gradient: linear-gradient(135deg, #0077b6, #03045e);
        }

        .queue-station-container .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
        }

        /* Breadcrumb Navigation - exactly matching dashboard */
        .queue-station-container .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .queue-station-container .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .queue-station-container .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Page header styling - exactly matching dashboard */
        .queue-station-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .queue-station-container .page-header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-station-container .page-header h1 i {
            color: #0077b6;
        }

        /* Station controls styling */
        .queue-station-container .station-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .queue-station-container .content-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .queue-station-container .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .queue-station-container .card-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .queue-station-container .card-body {
            padding: 1.5rem;
        }

        .queue-station-container .station-info {
            background: linear-gradient(135deg, var(--light), #e9ecef);
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }

        .queue-station-container .station-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .queue-station-container .station-details {
            color: var(--secondary);
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .queue-station-container .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .queue-station-container .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary);
            transition: var(--transition);
            border: 1px solid var(--border);
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

        .queue-station-container .queue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            box-shadow: var(--shadow);
        }

        .queue-station-container .queue-table th {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .queue-station-container .queue-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .queue-station-container .queue-table tbody tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        .queue-station-container .queue-table tr:last-child td {
            border-bottom: none;
        }

        .queue-station-container .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .queue-station-container .priority-normal {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .queue-station-container .priority-priority {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .queue-station-container .priority-emergency {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .queue-station-container .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .queue-station-container .status-waiting {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .queue-station-container .status-in_progress {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .queue-station-container .status-done {
            background-color: #e8f5e8;
            color: #2e7d32;
        }

        .queue-station-container .status-skipped {
            background-color: #ffebee;
            color: #d32f2f;
        }

        /* Action buttons - matching dashboard style */
        .queue-station-container .btn,
        .queue-station-container .action-btn {
            margin-right: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            color: white;
            font-size: 14px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            text-decoration: none;
            font-weight: 600;
        }

        .queue-station-container .btn:hover,
        .queue-station-container .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }

        .queue-station-container .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .queue-station-container .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .queue-station-container .btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }

        .queue-station-container .btn-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }

        .queue-station-container .btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .queue-station-container .btn-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }
        
        .queue-station-container .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }

        .queue-station-container .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        /* Alert Messages - matching dashboard */
        .queue-station-container .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left-width: 4px;
            border-left-style: solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .queue-station-container .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-left-color: #28a745;
        }
        
        .queue-station-container .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }
        
        .queue-station-container .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-color: #ffeaa7;
            border-left-color: #ffc107;
        }
        
        .queue-station-container .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
            border-left-color: #17a2b8;
        }
        
        .queue-station-container .alert i {
            margin-right: 5px;
        }

        /* Badge styling - matching dashboard */
        .queue-station-container .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .queue-station-container .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .queue-station-container .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .queue-station-container .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .queue-station-container .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .queue-station-container .bg-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        .queue-station-container .bg-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }

        .queue-station-container .empty-queue {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--secondary);
        }

        .queue-station-container .empty-queue i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .queue-station-container .current-patient-highlight {
            background: linear-gradient(135deg, #fff3e0, #ffcc02) !important;
            border: 2px solid var(--warning);
        }

        .queue-station-container .inactive-station {
            text-align: center;
            padding: 3rem 2rem;
            background: var(--light);
            border-radius: var(--border-radius);
        }

        .queue-station-container .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--primary);
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

        .queue-station-container .refresh-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .queue-station-container .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        /* Responsive design - matching dashboard */
        @media (max-width: 768px) {
            .queue-station-container .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .queue-station-container .station-controls {
                width: 100%;
                justify-content: flex-start;
                gap: 0.75rem;
            }

            .queue-station-container .content-area {
                padding: 1rem;
            }
            
            .queue-station-container .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .queue-station-container .station-details {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .queue-station-container .station-controls {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }
        }

        .queue-station-container .text-muted {
            color: var(--secondary) !important;
            font-style: italic;
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
    
    <main class="homepage">
        <div class="queue-station-container">
            <div class="content-area">
                <!-- Include topbar -->
                <?php include '../../includes/topbar.php'; ?>

                <!-- Breadcrumb Navigation - matching dashboard -->
                <div class="breadcrumb" style="margin-top: 50px;">
                    <a href="../management/admin/dashboard.php">Admin Dashboard</a>
                    <span>›</span>
                    <a href="dashboard.php">Queue Management Dashboard</a>
                    <span>›</span>
                    <span><?php echo $assigned_employee_info ? htmlspecialchars($assigned_employee_info['station_name']) : 'Station Management'; ?></span>
                </div>

                <!-- Page Header with Station Dropdown and Controls -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-desktop"></i>
                        <?php echo $assigned_employee_info ? htmlspecialchars($assigned_employee_info['station_name']) : 'Station Management'; ?>
                    </h1>
                    <div class="station-controls">
                        <!-- Access Level Badge -->
                        <?php if ($access_level === 'full-admin'): ?>
                            <span class="badge bg-primary">Full Admin Access</span>
                        <?php elseif ($access_level === 'manage-assigned'): ?>
                            <span class="badge bg-success">Manage Access</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">View Only</span>
                        <?php endif; ?>
                        
                        <!-- Station Selection Dropdown -->
                        <form method="get" style="margin: 0;">
                            <select name="station_id" class="form-control" style="min-width: 280px;" onchange="this.form.submit()">
                                <option value="">-- Select Station --</option>
                                <?php foreach ($all_stations as $station): ?>
                                    <option value="<?php echo $station['station_id']; ?>" 
                                        <?php echo ($station['station_id'] == $selected_station_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station['station_name']) . ' (' . ucfirst($station['station_type']) . ')'; ?>
                                        <?php if ($station['employee_name']): ?>
                                            - <?php echo htmlspecialchars($station['employee_name']); ?>
                                            <?php echo isset($station['is_open']) && $station['is_open'] ? ' [OPEN]' : ' [CLOSED]'; ?>
                                        <?php else: ?>
                                            - Unassigned
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        
                        <!-- Station Toggle (if authorized) -->
                        <?php if ($can_toggle_station && $assigned_employee_info): ?>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="station_id" value="<?php echo $selected_station_id; ?>">
                                <input type="hidden" name="is_open" value="<?php echo (isset($assigned_employee_info['is_open']) && $assigned_employee_info['is_open']) ? 0 : 1; ?>">
                                <button type="submit" name="toggle_station" class="btn <?php echo (isset($assigned_employee_info['is_open']) && $assigned_employee_info['is_open']) ? 'btn-danger' : 'btn-success'; ?>">
                                    <i class="fas <?php echo (isset($assigned_employee_info['is_open']) && $assigned_employee_info['is_open']) ? 'fa-pause' : 'fa-play'; ?>"></i>
                                    <?php echo (isset($assigned_employee_info['is_open']) && $assigned_employee_info['is_open']) ? 'Close Station' : 'Open Station'; ?>
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-info" onclick="refreshQueue()">
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

            <?php if (!$selected_station_id): ?>
                <!-- No Station Selected -->
                <div class="content-card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>No Station Selected:</strong> Please select a station from the dropdown above to view its queue.
                        </div>
                    </div>
                </div>
            <?php elseif (!$can_manage_queue && $access_level === 'view-only' && !$station_assignment): ?>
                <!-- No Station Assignment for Non-Admin -->
                <div class="content-card">
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>View-Only Access:</strong> You are not assigned to any station today. You can view station queues but cannot manage them. Please contact the administrator to get your station assignment.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($selected_station_id): ?>
                <!-- Station Information -->
                <?php if ($assigned_employee_info): ?>
                    <div class="station-info">
                        <div class="station-name">
                            <i class="fas fa-hospital-user"></i>
                            <?php echo htmlspecialchars($assigned_employee_info['station_name']); ?>
                            <?php if ($access_level === 'manage-assigned'): ?>
                                <span class="badge bg-success" style="margin-left: 1rem;">ASSIGNED</span>
                            <?php elseif ($access_level === 'full-admin'): ?>
                                <span class="badge bg-primary" style="margin-left: 1rem;">ADMIN ACCESS</span>
                            <?php else: ?>
                                <span class="badge bg-secondary" style="margin-left: 1rem;">VIEW ONLY</span>
                            <?php endif; ?>
                            
                            <?php if (isset($assigned_employee_info['is_open'])): ?>
                                <span class="badge <?php echo $assigned_employee_info['is_open'] ? 'bg-success' : 'bg-danger'; ?>" style="margin-left: 0.5rem;">
                                    <?php echo $assigned_employee_info['is_open'] ? 'OPEN' : 'CLOSED'; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="station-details">
                            <span><i class="fas fa-cogs"></i> <strong>Type:</strong> <?php echo ucfirst($assigned_employee_info['station_type']); ?></span>
                            <span><i class="fas fa-stethoscope"></i> <strong>Service:</strong> <?php echo htmlspecialchars($assigned_employee_info['service_name'] ?? 'N/A'); ?></span>
                            <?php if ($assigned_employee_info['employee_name']): ?>
                                <span><i class="fas fa-user"></i> <strong>Assigned to:</strong> <?php echo htmlspecialchars($assigned_employee_info['employee_name']); ?></span>
                            <?php else: ?>
                                <span><i class="fas fa-user-times"></i> <strong>Status:</strong> Unassigned</span>
                            <?php endif; ?>
                            <?php if ($assigned_employee_info['shift_start_time'] && $assigned_employee_info['shift_end_time']): ?>
                                <span><i class="fas fa-clock"></i> <strong>Shift:</strong> 
                                    <?php echo date('g:i A', strtotime($assigned_employee_info['shift_start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($assigned_employee_info['shift_end_time'])); ?>
                                </span>
                            <?php endif; ?>
                            <span><i class="fas fa-calendar"></i> <strong>Date:</strong> <?php echo date('M j, Y'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

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
                                                        <?php if ($can_manage_queue): ?>
                                                            <?php if ($assigned_employee_info['station_type'] === 'consultation'): ?>
                                                                <!-- Doctor/Consultation Station - Routing Options -->
                                                                <button type="button" class="action-btn btn-info" 
                                                                        onclick="routeToLab(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-microscope"></i> Lab
                                                                </button>
                                                                <button type="button" class="action-btn btn-warning" 
                                                                        onclick="routeToPharmacy(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-pills"></i> Pharmacy
                                                                </button>
                                                                <button type="button" class="action-btn btn-success" 
                                                                        onclick="completeVisit(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-check-circle"></i> Complete Visit
                                                                </button>
                                                            <?php elseif ($assigned_employee_info['station_type'] === 'lab'): ?>
                                                                <!-- Laboratory Station - Return to Doctor or Complete -->
                                                                <button type="button" class="action-btn btn-primary" 
                                                                        onclick="returnToDoctor(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-user-md"></i> Return to Doctor
                                                                </button>
                                                                <button type="button" class="action-btn btn-success" 
                                                                        onclick="completeVisit(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-check-circle"></i> Complete Visit
                                                                </button>
                                                            <?php elseif ($assigned_employee_info['station_type'] === 'pharmacy'): ?>
                                                                <!-- Pharmacy Station - Complete Visit Only -->
                                                                <button type="button" class="action-btn btn-success" 
                                                                        onclick="completeVisit(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-check-circle"></i> Complete Visit
                                                                </button>
                                                            <?php else: ?>
                                                                <!-- Other Stations - Standard Complete -->
                                                                <button type="button" class="action-btn btn-success" 
                                                                        onclick="completeService(<?php echo $patient['queue_entry_id']; ?>, '<?php echo htmlspecialchars($patient['patient_name'], ENT_QUOTES); ?>')">
                                                                    <i class="fas fa-check"></i> Complete
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-eye"></i> View Only</span>
                                                        <?php endif; ?>
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
                                <?php if (!empty($waiting_queue) && empty($in_progress_queue) && $can_manage_queue): ?>
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
                                                        <?php if ($can_manage_queue): ?>
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
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-eye"></i> View Only</span>
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
                                                        <?php if ($can_manage_queue): ?>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="action" value="reinstate">
                                                                <input type="hidden" name="queue_entry_id" value="<?php echo $patient['queue_entry_id']; ?>">
                                                                <button type="submit" class="action-btn btn-info">
                                                                    <i class="fas fa-undo"></i> Reinstate
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-muted"><i class="fas fa-eye"></i> View Only</span>
                                                        <?php endif; ?>
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
    </main>

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

        function routeToLab(queueEntryId, patientName) {
            const remarks = prompt(`Route ${patientName} to Laboratory.\n\nTests needed/reason:`, '');
            if (remarks !== null && remarks.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="route_to_lab">
                    <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                    <input type="hidden" name="remarks" value="${remarks}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (remarks !== null) {
                alert('Please specify the tests needed or reason for laboratory referral.');
            }
        }

        function routeToPharmacy(queueEntryId, patientName) {
            const remarks = prompt(`Route ${patientName} to Pharmacy.\n\nPrescription details:`, '');
            if (remarks !== null && remarks.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="route_to_pharmacy">
                    <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                    <input type="hidden" name="remarks" value="${remarks}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (remarks !== null) {
                alert('Please provide prescription details for pharmacy.');
            }
        }

        function completeVisit(queueEntryId, patientName) {
            const remarks = prompt(`Complete visit for ${patientName}.\n\nFinal notes (optional):`, '');
            if (remarks !== null) {
                if (confirm(`Are you sure you want to complete the entire visit for ${patientName}?\n\nThis will mark their appointment as finished.`)) {
                    const form = document.createElement('form');
                    form.method = 'post';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="complete_visit">
                        <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                        <input type="hidden" name="remarks" value="${remarks}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }

        function returnToDoctor(queueEntryId, patientName) {
            const remarks = prompt(`Return ${patientName} to Doctor.\n\nLab results/notes:`, '');
            if (remarks !== null && remarks.trim() !== '') {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <input type="hidden" name="action" value="return_to_doctor">
                    <input type="hidden" name="queue_entry_id" value="${queueEntryId}">
                    <input type="hidden" name="remarks" value="${remarks}">
                `;
                document.body.appendChild(form);
                form.submit();
            } else if (remarks !== null) {
                alert('Please provide lab results or notes for the doctor.');
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