<?php
// pages/management/admin/staff-management/staff_assignments.php
// Admin interface for assigning staff to stations and managing station status

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';

// If user is not logged in, bounce to login
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    header('Location: ../../auth/employee_login.php');
    exit();
}

// Check if role is authorized for admin functions
if (strtolower($_SESSION['role']) !== 'admin') {
    header('Location: ../dashboard.php');
    exit();
}

require_once $root_path . '/config/db.php';
require_once $root_path . '/utils/queue_management_service.php';

// Initialize queue management service
$queueService = new QueueManagementService($pdo);

$date = $_GET['date'] ?? date('Y-m-d');
$message = '';
$error = '';

// Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_employee'])) {
        $employee_id = intval($_POST['employee_id']);
        $station_id = intval($_POST['station_id']);
        $start_date = $_POST['start_date'] ?? ($_POST['assigned_date'] ?? $date); // Check both field names
        $assignment_type = $_POST['assignment_type'] ?? 'permanent';
        $end_date = $_POST['end_date'] ?? null;
        $shift_start = $_POST['shift_start'] ?? '08:00:00';
        $shift_end = $_POST['shift_end'] ?? '17:00:00';
        $assigned_by = $_SESSION['employee_id'];
        
        // Validate required fields
        if ($employee_id <= 0) {
            $error = "Please select an employee to assign.";
        } elseif ($station_id <= 0) {
            $error = "Invalid station selected.";
        } elseif (empty($start_date)) {
            $error = "Start date is required.";
        } else {
            // Debug: Log assignment parameters
            error_log("Assignment Parameters: employee_id=$employee_id, station_id=$station_id, start_date=$start_date, assignment_type=$assignment_type, shift_start=$shift_start, shift_end=$shift_end, assigned_by=$assigned_by, end_date=$end_date");
            
            // Use new efficient assignment method with correct parameter order
            $result = $queueService->assignEmployeeToStation(
                $employee_id, $station_id, $start_date, $assignment_type, 
                $shift_start, $shift_end, $assigned_by, $end_date
            );
            
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['error'];
            }
        }

    } elseif (isset($_POST['remove_assignment'])) {
        $station_id = intval($_POST['station_id']);
        $removal_date = $_POST['removal_date'] ?? ($_POST['assigned_date'] ?? $date);
        $removal_type = $_POST['removal_type'] ?? 'end_assignment';
        $performed_by = $_SESSION['employee_id'];
        
        $result = $queueService->removeEmployeeAssignment($station_id, $removal_date, $removal_type, $performed_by);
        
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['error'];
        }
        
    } elseif (isset($_POST['reassign_employee'])) {
        $station_id = intval($_POST['station_id']);
        $new_employee_id = intval($_POST['new_employee_id']);
        $reassign_date = $_POST['reassign_date'] ?? ($_POST['assigned_date'] ?? $date);
        $assigned_by = $_SESSION['employee_id'];
        
        $result = $queueService->reassignStation($station_id, $new_employee_id, $reassign_date, $assigned_by);
        
        if ($result['success']) {
            $message = $result['message'];
        } else {
            $error = $result['error'];
        }
        
    } elseif (isset($_POST['toggle_station'])) {
        $station_id = intval($_POST['station_id']);
        $is_active = intval($_POST['is_active']);
        
        if ($queueService->toggleStationStatus($station_id, $is_active)) {
            $message = $is_active ? 'Station activated successfully.' : 'Station deactivated successfully.';
        } else {
            $error = 'Failed to update station status.';
        }
    }
    
    // Redirect to prevent form resubmission only if there's a success message
    if ($message && empty($error)) {
        header('Location: staff_assignments.php?date=' . urlencode($date));
        exit();
    }
}

// Get stations with assignments
$stations = $queueService->getAllStationsWithAssignments($date);

// Get all employees for assignment dropdown (facility_id = 1 only)
$employees = $queueService->getActiveEmployees(1);

// Create role-to-employees mapping for JavaScript
$employeesByRole = [];
foreach ($employees as $emp) {
    $role = strtolower($emp['role_name']);
    if (!isset($employeesByRole[$role])) {
        $employeesByRole[$role] = [];
    }
    $employeesByRole[$role][] = $emp;
}

// Set active page for sidebar highlighting
$activePage = 'staff_assignments';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Staff Assignments | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Additional styles for staff assignments management - MATCHING PATIENT RECORDS TEMPLATE */
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
        
        .loader {
            border: 5px solid rgba(240, 240, 240, 0.5);
            border-radius: 50%;
            border-top: 5px solid var(--primary);
            width: 30px;
            height: 30px;
            animation: spin 1.5s linear infinite;
            margin: 0 auto;
            display: none;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--border-radius);
            margin-top: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: var(--shadow);
        }
        
        table th {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            position: sticky;
            top: 0;
            z-index: 10;
            cursor: pointer;
            user-select: none;
        }
        
        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        table tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        .action-btn {
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
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
        }
        
        .btn-info {
            background: linear-gradient(135deg, #0096c7, #0077b6);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .badge {
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
        
        .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .bg-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        /* Content header styling */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-dialog {
            max-width: 450px;
            margin: 50px auto;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }
        
        .modal.show .modal-dialog {
            transform: translateY(0);
        }
        
        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--primary-dark);
            color: white;
        }
        
        .modal-body {
            padding: 20px;
        }
        
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
            font-size: 20px;
            cursor: pointer;
            color: white;
            transition: color 0.2s ease;
        }
        
        .btn-close:hover {
            color: var(--light);
        }

        .station-type-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .station-type-checkin { background-color: #e3f2fd; color: #1976d2; }
        .station-type-triage { background-color: #fff3e0; color: #f57c00; }
        .station-type-consultation { background-color: #f3e5f5; color: #7b1fa2; }
        .station-type-lab { background-color: #e8f5e8; color: #388e3c; }
        .station-type-pharmacy { background-color: #fce4ec; color: #c2185b; }
        .station-type-billing { background-color: #f1f8e9; color: #689f38; }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left-width: 4px;
            border-left-style: solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
            border-left-color: #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
            border-left-color: #dc3545;
        }
        
        .alert i {
            margin-right: 5px;
        }

        /* Form inputs */
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.2);
            outline: none;
        }
        
        .form-select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            margin-bottom: 12px;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            font-size: 14px;
        }
        
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.2);
            outline: none;
        }
        
        .input-group {
            display: flex;
            position: relative;
        }
        
        .input-group-text {
            padding: 10px 15px;
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-right: none;
            border-radius: var(--border-radius) 0 0 var(--border-radius);
            display: flex;
            align-items: center;
            color: #64748b;
        }
        
        .input-group .form-control {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            margin-bottom: 0;
            flex: 1;
        }

        /* Breadcrumb styling */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
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
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .page-header h1 i {
            color: #0077b6;
        }

        /* Total count badges styling */
        .total-count {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .total-count .badge {
            min-width: 120px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            border-radius: 25px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .total-count .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Section header styling */
        .section-header {
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
        }
        
        .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 18px;
            font-weight: 600;
        }
        
        .section-header h4 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Responsive grid */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin-right: -15px;
            margin-left: -15px;
        }
        
        .col-12 {
            flex: 0 0 100%;
            max-width: 100%;
            padding: 0 15px;
        }
        
        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
            padding: 0 15px;
        }
        
        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
            padding: 0 15px;
        }
        
        .col-md-2 {
            flex: 0 0 16.666667%;
            max-width: 16.666667%;
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .col-md-4, .col-md-3, .col-md-2 {
                flex: 0 0 100%;
                max-width: 100%;
            }
        }

        /* Utility classes */
        .d-flex {
            display: flex;
        }
        
        .me-2 {
            margin-right: 10px;
        }
        
        .mb-2 {
            margin-bottom: 10px;
        }
        
        .mt-4 {
            margin-top: 20px;
        }
        
        .justify-content-center {
            justify-content: center;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
            font-style: italic;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }

        /* Mobile responsive styling */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .total-count {
                width: 100%;
                justify-content: flex-start;
                gap: 0.75rem;
            }

            .total-count .badge {
                min-width: 100px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }
        }

        @media (max-width: 480px) {
            .total-count {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .total-count .badge {
                width: 100%;
                min-width: auto;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include '../../../../includes/sidebar_admin.php'; ?>
    
    <div class="homepage">
        <div class="main-content">
            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb" style="margin-top: 50px;">
                <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="../staff-management/">Staff Management</a>
                <i class="fas fa-chevron-right"></i>
                <span>Station Staff Assignments</span>
            </div>

            <div class="page-header">
                <h1><i class="fas fa-users-cog"></i> Station Staff Assignments</h1>
                <div class="total-count">
                    <span class="badge bg-primary"><?php echo count($stations); ?> Total Stations</span>
                    <span class="badge bg-success"><?php echo count(array_filter($stations, function($s) { return $s['is_active']; })); ?> Active</span>
                    <span class="badge bg-warning"><?php echo count(array_filter($stations, function($s) { return $s['employee_id']; })); ?> Assigned</span>
                </div>
            </div>
            
            <!-- Date Selection Section -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-calendar-alt"></i> Date Selection</h4>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="alert" style="background-color: #e3f2fd; color: #1976d2; border-left-color: #2196f3; margin-bottom: 15px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Simple Assignment:</strong> Use "Only this Day" for temporary assignments or "Permanent Assignment" for ongoing daily assignments. Permanent assignments will continue every day until you remove them.
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <form method="get" class="d-flex">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control">
                            </div>
                            <button type="submit" class="action-btn btn-primary" style="margin-left: 10px;">
                                <i class="fas fa-search"></i> View
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger" style="border-left: 4px solid #dc3545; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);">
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <i class="fas fa-exclamation-triangle" style="color: #dc3545; font-size: 20px; margin-right: 10px;"></i>
                        <strong style="color: #721c24;">Assignment Conflict!</strong>
                    </div>
                    <div style="color: #721c24; line-height: 1.5;">
                        <?php echo nl2br(htmlspecialchars($error)); ?>
                    </div>
                    <?php if (strpos($error, 'Duplicate entry') !== false): ?>
                        <div style="margin-top: 15px; padding: 10px; background-color: #fff3cd; border-radius: 5px; border-left: 4px solid #ffc107;">
                            <strong style="color: #856404;">🔧 Fix Available:</strong><br>
                            <span style="color: #856404;">This error occurs when there are inactive assignment records blocking new assignments.</span><br>
                            <a href="../../../../cleanup_assignments.php" target="_blank" style="color: #0056b3; text-decoration: underline; margin-top: 5px; display: inline-block;">
                                → Open Assignment Cleanup Tool
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Station Assignments Table -->
            <div class="card-container">
                <div class="section-header">
                    <h4><i class="fas fa-list"></i> Station Assignments for <?php echo date('F j, Y', strtotime($date)); ?></h4>
                </div>
                <div class="table-responsive">
                    <table id="stationTable">
                        <thead>
                            <tr>
                                <th>Station</th>
                                <th>Type</th>
                                <th>Service</th>
                                <th>Assigned Employee</th>
                                <th>Role</th>
                                <th>Shift</th>
                                <th>Status</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($stations)): ?>
                                <?php foreach ($stations as $station): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($station['station_name']); ?></strong>
                                            <?php if ($station['station_number'] > 1): ?>
                                                <br><small class="text-muted">#<?php echo $station['station_number']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="station-type-badge station-type-<?php echo $station['station_type']; ?>">
                                                <?php echo ucfirst($station['station_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($station['service_name']); ?></td>
                                        <td>
                                            <?php if ($station['employee_name']): ?>
                                                <?php echo htmlspecialchars($station['employee_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($station['employee_role']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($station['employee_role'])); ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($station['shift_start_time'] && $station['shift_end_time']): ?>
                                                <?php echo date('g:i A', strtotime($station['shift_start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($station['shift_end_time'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($station['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($station['employee_id']): ?>
                                                    <!-- Reassign button -->
                                                    <button type="button" class="action-btn btn-warning" onclick="openReassignModal(<?php echo $station['station_id']; ?>, '<?php echo htmlspecialchars($station['station_name']); ?>', <?php echo $station['employee_id']; ?>, '<?php echo htmlspecialchars($station['station_type']); ?>')" title="Reassign Employee">
                                                        <i class="fas fa-exchange-alt"></i>
                                                    </button>
                                                    <!-- Remove assignment button -->
                                                    <button type="button" class="action-btn btn-danger" onclick="openRemoveModal(<?php echo $station['station_id']; ?>, '<?php echo htmlspecialchars($station['station_name']); ?>', '<?php echo htmlspecialchars($station['employee_name']); ?>')" title="Remove Assignment">
                                                        <i class="fas fa-user-minus"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <!-- Assign button -->
                                                    <button type="button" class="action-btn btn-primary" onclick="openAssignModal(<?php echo $station['station_id']; ?>, '<?php echo htmlspecialchars($station['station_name']); ?>', '<?php echo htmlspecialchars($station['station_type']); ?>')" title="Assign Employee">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <!-- Toggle station status -->
                                                <form method="post" style="display: inline;" onsubmit="return confirm('<?php echo $station['is_active'] ? 'Deactivate' : 'Activate'; ?> this station?');">
                                                    <input type="hidden" name="station_id" value="<?php echo $station['station_id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $station['is_active'] ? 0 : 1; ?>">
                                                    <input type="hidden" name="assigned_date" value="<?php echo htmlspecialchars($date); ?>">
                                                    <button type="submit" name="toggle_station" class="action-btn <?php echo $station['is_active'] ? 'btn-secondary' : 'btn-success'; ?>" title="<?php echo $station['is_active'] ? 'Deactivate Station' : 'Activate Station'; ?>">
                                                        <i class="fas <?php echo $station['is_active'] ? 'fa-power-off' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">
                                        <div style="padding: 30px 0;">
                                            <i class="fas fa-users-cog" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                            <p>No stations found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Assign Modal -->
    <div class="modal" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Assign Employee to Station</h5>
                        <button type="button" class="btn-close" onclick="closeModal('assignModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="station_id" id="assign_station_id">
                        
                        <div class="mb-2">
                            <label>Station:</label>
                            <input type="text" id="assign_station_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-2">
                            <label>Employee:</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">Select Employee...</option>
                                <!-- Options populated dynamically by JavaScript based on station type -->
                            </select>
                        </div>
                        
                        <div class="mb-2">
                            <label>Assignment Type:</label>
                            <select name="assignment_type" class="form-select" onchange="toggleAssignmentFields()">
                                <option value="permanent">Permanent Assignment (Ongoing)</option>
                                <option value="temporary">Temporary Assignment (Fixed Duration)</option>
                            </select>
                        </div>
                        
                        <div class="mb-2">
                            <label>Start Date:</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($date); ?>" class="form-control" required>
                        </div>
                        
                        <div class="mb-2" id="end_date_field" style="display: none;">
                            <label>End Date (Optional for temporary):</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        
                        <div class="alert" style="background-color: #e3f2fd; color: #1976d2; border-left-color: #2196f3; margin-bottom: 15px; font-size: 13px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Efficient System:</strong> One assignment record covers the entire period. Permanent assignments continue indefinitely (no end date). Temporary assignments have a specific end date.
                        </div>
                        
                        <div class="d-flex">
                            <div style="flex: 1; margin-right: 10px;">
                                <label>Shift Start:</label>
                                <input type="time" name="shift_start" value="08:00" class="form-control">
                            </div>
                            <div style="flex: 1;">
                                <label>Shift End:</label>
                                <input type="time" name="shift_end" value="17:00" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('assignModal')">Cancel</button>
                        <button type="submit" name="assign_employee" class="action-btn btn-primary">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reassign Modal -->
    <div class="modal" id="reassignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Reassign Employee to Station</h5>
                        <button type="button" class="btn-close" onclick="closeModal('reassignModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="station_id" id="reassign_station_id">
                        
                        <div class="mb-2">
                            <label>Station:</label>
                            <input type="text" id="reassign_station_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-2">
                            <label>New Employee:</label>
                            <select name="new_employee_id" class="form-select" required>
                                <option value="">Select Employee...</option>
                                <!-- Options populated dynamically by JavaScript based on station type -->
                            </select>
                        </div>
                        
                        <div class="mb-2">
                            <label>Reassignment Date:</label>
                            <input type="date" name="reassign_date" value="<?php echo htmlspecialchars($date); ?>" class="form-control" required>
                        </div>
                        
                        <div class="alert" style="background-color: #fff3cd; color: #856404; border-left-color: #ffc107; margin-bottom: 15px; font-size: 13px;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> The current assignment will end the day before the reassignment date, and the new assignment will start from the reassignment date.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('reassignModal')">Cancel</button>
                        <button type="submit" name="reassign_employee" class="action-btn btn-warning">Reassign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Remove Assignment Modal -->
    <div class="modal" id="removeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5>Remove Employee Assignment</h5>
                        <button type="button" class="btn-close" onclick="closeModal('removeModal')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="station_id" id="remove_station_id">
                        
                        <div class="mb-2">
                            <label>Station:</label>
                            <input type="text" id="remove_station_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-2">
                            <label>Current Employee:</label>
                            <input type="text" id="remove_employee_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-2">
                            <label>Removal Date:</label>
                            <input type="date" name="removal_date" value="<?php echo htmlspecialchars($date); ?>" class="form-control" required>
                        </div>
                        
                        <div class="mb-2">
                            <label>Removal Type:</label>
                            <select name="removal_type" class="form-select" required>
                                <option value="end_assignment">End Assignment (Set end date)</option>
                                <option value="deactivate">Deactivate Assignment (Keep record but inactive)</option>
                            </select>
                        </div>
                        
                        <div class="alert alert-warning" style="font-size: 13px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Note:</strong> "End Assignment" will set the assignment end date to the day before removal date. "Deactivate" will keep the record but mark it inactive.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="action-btn btn-secondary" onclick="closeModal('removeModal')">Cancel</button>
                        <button type="submit" name="remove_assignment" class="action-btn btn-danger">Remove Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Employee data organized by role
        const employeesByRole = <?php echo json_encode($employeesByRole); ?>;
        
        // Station type to allowed roles mapping (matching stations table)
        const stationRoles = {
            'checkin': ['records_officer', 'nurse'],
            'triage': ['nurse'],
            'billing': ['cashier'],
            'consultation': ['doctor'],
            'lab': ['laboratory_tech'],
            'pharmacy': ['pharmacist'],
            'document': ['records_officer']
        };
        
        function populateEmployeeDropdown(selectElement, stationType, excludeEmployeeId = null) {
            // Debug logging
            console.log('Populating dropdown for station type:', stationType);
            console.log('Available employees by role:', employeesByRole);
            
            // Clear existing options except the first one
            selectElement.innerHTML = '<option value="">Select Employee...</option>';
            
            // Get allowed roles for this station type
            const allowedRoles = stationRoles[stationType] || [];
            console.log('Allowed roles for', stationType, ':', allowedRoles);
            
            let addedCount = 0;
            
            // Add employees from allowed roles
            allowedRoles.forEach(role => {
                if (employeesByRole[role]) {
                    employeesByRole[role].forEach(emp => {
                        // Skip excluded employee (for reassign modal)
                        if (excludeEmployeeId && emp.employee_id == excludeEmployeeId) {
                            return;
                        }
                        
                        const option = document.createElement('option');
                        option.value = emp.employee_id;
                        option.textContent = emp.full_name + ' (' + emp.role_name.charAt(0).toUpperCase() + emp.role_name.slice(1) + ')';
                        selectElement.appendChild(option);
                        addedCount++;
                    });
                }
            });
            
            console.log('Added', addedCount, 'employees to dropdown');
            
            if (addedCount === 0) {
                const noOption = document.createElement('option');
                noOption.value = '';
                noOption.textContent = 'No available employees for this station type';
                noOption.disabled = true;
                selectElement.appendChild(noOption);
            }
        }
        
        function openAssignModal(stationId, stationName, stationType) {
            document.getElementById('assign_station_id').value = stationId;
            document.getElementById('assign_station_name').value = stationName;
            
            // Populate employee dropdown with role-specific employees
            const select = document.querySelector('#assignModal select[name="employee_id"]');
            populateEmployeeDropdown(select, stationType);
            
            document.getElementById('assignModal').classList.add('show');
        }
        
        function openReassignModal(stationId, stationName, currentEmployeeId, stationType) {
            document.getElementById('reassign_station_id').value = stationId;
            document.getElementById('reassign_station_name').value = stationName;
            
            // Populate employee dropdown with role-specific employees, excluding current employee
            const select = document.querySelector('#reassignModal select[name="new_employee_id"]');
            populateEmployeeDropdown(select, stationType, currentEmployeeId);
            
            // Add change listener to check conflicts when employee is selected for reassignment
            select.onchange = function() {
                const selectedEmployeeId = this.value;
                if (selectedEmployeeId) {
                    const selectedOption = this.options[this.selectedIndex];
                    const employeeName = selectedOption.textContent;
                    
                    // Check for conflicts (same function, but for reassignment)
                    if (checkEmployeeConflicts(selectedEmployeeId, stationName, '<?php echo $date; ?>', '08:00', '17:00')) {
                        // Show reassignment-specific warning
                        showReassignmentWarning(
                            employeeName,
                            stationName
                        );
                        this.value = ''; // Reset selection
                        return false;
                    }
                }
            };
            
            document.getElementById('reassignModal').classList.add('show');
        }
        
        function openRemoveModal(stationId, stationName, employeeName) {
            document.getElementById('remove_station_id').value = stationId;
            document.getElementById('remove_station_name').value = stationName;
            document.getElementById('remove_employee_name').value = employeeName;
            
            document.getElementById('removeModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
        
        // Toggle end date field based on assignment type
        function toggleAssignmentFields() {
            const assignmentType = document.querySelector('#assignModal select[name="assignment_type"]').value;
            const endDateField = document.getElementById('end_date_field');
            
            if (assignmentType === 'temporary') {
                endDateField.style.display = 'block';
            } else {
                endDateField.style.display = 'none';
            }
        }
        
        // Enhanced assignment system using efficient date ranges
        
        // Check for assignment conflicts before showing assign modal
        function checkEmployeeConflicts(employeeId, stationName, startDate, shiftStart, shiftEnd) {
            // Get all current assignments to check for conflicts
            const allAssignments = <?php echo json_encode($stations); ?>;
            
            for (let i = 0; i < allAssignments.length; i++) {
                const assignment = allAssignments[i];
                // Only check for ACTIVE assignments with actual employee IDs
                if (assignment.employee_id == employeeId && 
                    assignment.employee_id != null && 
                    assignment.assignment_status == 1) {  // Check if assignment is active
                    // Found a conflict
                    showAssignmentWarning(
                        assignment.employee_name,
                        assignment.station_name,
                        stationName,
                        assignment.shift_start_time + ' - ' + assignment.shift_end_time
                    );
                    return true; // Conflict found
                }
            }
            return false; // No conflict
        }
        
        // Show assignment conflict warning
        function showAssignmentWarning(employeeName, currentStation, newStation, shiftTime) {
            document.getElementById('warning_employee_name').textContent = employeeName;
            document.getElementById('warning_current_station').textContent = currentStation;
            document.getElementById('warning_new_station').textContent = newStation;
            document.getElementById('warning_shift_time').textContent = shiftTime;
            document.getElementById('assignmentWarningModal').classList.add('show');
        }
        
        // Show reassignment conflict warning
        function showReassignmentWarning(employeeName, targetStation) {
            // Find the employee's current assignment
            const allAssignments = <?php echo json_encode($stations); ?>;
            let currentAssignment = null;
            
            for (let i = 0; i < allAssignments.length; i++) {
                if (allAssignments[i].employee_name === employeeName && allAssignments[i].employee_id != null) {
                    currentAssignment = allAssignments[i];
                    break;
                }
            }
            
            if (currentAssignment) {
                document.getElementById('reassign_warning_employee_name').textContent = employeeName;
                document.getElementById('reassign_warning_current_station').textContent = currentAssignment.station_name;
                document.getElementById('reassign_warning_target_station').textContent = targetStation;
                document.getElementById('reassign_warning_shift_time').textContent = currentAssignment.shift_start_time + ' - ' + currentAssignment.shift_end_time;
                document.getElementById('reassignmentWarningModal').classList.add('show');
            }
        }
        
        // Enhanced assign modal with conflict checking
        function openAssignModalWithCheck(stationId, stationName, stationType) {
            document.getElementById('assign_station_id').value = stationId;
            document.getElementById('assign_station_name').value = stationName;
            
            // Populate employee dropdown with role-specific employees
            const select = document.querySelector('#assignModal select[name="employee_id"]');
            populateEmployeeDropdown(select, stationType);
            
            // Add change listener to check conflicts when employee is selected
            select.onchange = function() {
                const selectedEmployeeId = this.value;
                if (selectedEmployeeId) {
                    const selectedOption = this.options[this.selectedIndex];
                    const employeeName = selectedOption.textContent;
                    
                    // Check for conflicts
                    if (checkEmployeeConflicts(selectedEmployeeId, stationName, '<?php echo $date; ?>', '08:00', '17:00')) {
                        // Conflict found - warning modal will show
                        this.value = ''; // Reset selection
                        return false;
                    }
                }
            };
            
            document.getElementById('assignModal').classList.add('show');
        }
        
        // Update existing function to use new conflict checking
        function openAssignModal(stationId, stationName, stationType) {
            openAssignModalWithCheck(stationId, stationName, stationType);
        }
    </script>
    
    <!-- Assignment Conflict Warning Modal -->
    <div class="modal" id="assignmentWarningModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #d32f2f;">
                    <h5 style="color: white;"><i class="fas fa-exclamation-triangle"></i> Assignment Conflict Warning</h5>
                    <button type="button" class="btn-close" onclick="closeModal('assignmentWarningModal')" style="color: white;">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" style="margin-bottom: 15px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Cannot Assign Employee!</strong>
                    </div>
                    
                    <p><strong><span id="warning_employee_name"></span></strong> is already assigned to <strong><span id="warning_current_station"></span></strong>.</p>
                    
                    <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 15px 0;">
                        <h6 style="color: #856404; margin: 0 0 10px 0;"><i class="fas fa-info-circle"></i> Current Assignment Details:</h6>
                        <ul style="margin: 0; color: #856404;">
                            <li><strong>Station:</strong> <span id="warning_current_station"></span></li>
                            <li><strong>Shift Hours:</strong> <span id="warning_shift_time"></span></li>
                            <li><strong>Status:</strong> Active</li>
                        </ul>
                    </div>
                    
                    <div style="background-color: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 15px 0;">
                        <h6 style="color: #721c24; margin: 0 0 10px 0;"><i class="fas fa-ban"></i> Why This Is Not Allowed:</h6>
                        <ul style="margin: 0; color: #721c24;">
                            <li>An employee cannot be in two places at the same time</li>
                            <li>Overlapping shift schedules create conflicts</li>
                            <li>This violates scheduling business rules</li>
                        </ul>
                    </div>
                    
                    <div style="background-color: #d1ecf1; padding: 15px; border-radius: 8px; border-left: 4px solid #17a2b8; margin: 15px 0;">
                        <h6 style="color: #0c5460; margin: 0 0 10px 0;"><i class="fas fa-lightbulb"></i> What You Can Do:</h6>
                        <ul style="margin: 0; color: #0c5460;">
                            <li><strong>Reassign:</strong> Move the employee from their current station to <strong><span id="warning_new_station"></span></strong></li>
                            <li><strong>Choose Different Employee:</strong> Select someone who is not currently assigned</li>
                            <li><strong>Remove Current Assignment:</strong> End their current assignment first</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-primary" onclick="closeModal('assignmentWarningModal')">
                        <i class="fas fa-check"></i> Understood
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reassignment Conflict Warning Modal -->
    <div class="modal" id="reassignmentWarningModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #d32f2f;">
                    <h5 style="color: white;"><i class="fas fa-exclamation-triangle"></i> Reassignment Conflict Warning</h5>
                    <button type="button" class="btn-close" onclick="closeModal('reassignmentWarningModal')" style="color: white;">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" style="margin-bottom: 15px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Cannot Reassign Employee!</strong>
                    </div>
                    
                    <p><strong><span id="reassign_warning_employee_name"></span></strong> is already assigned to <strong><span id="reassign_warning_current_station"></span></strong> and cannot be reassigned to <strong><span id="reassign_warning_target_station"></span></strong> without proper handling.</p>
                    
                    <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 15px 0;">
                        <h6 style="color: #856404; margin: 0 0 10px 0;"><i class="fas fa-info-circle"></i> Current Assignment Details:</h6>
                        <ul style="margin: 0; color: #856404;">
                            <li><strong>Currently At:</strong> <span id="reassign_warning_current_station"></span></li>
                            <li><strong>Shift Hours:</strong> <span id="reassign_warning_shift_time"></span></li>
                            <li><strong>Status:</strong> Active Assignment</li>
                        </ul>
                    </div>
                    
                    <div style="background-color: #f8d7da; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 15px 0;">
                        <h6 style="color: #721c24; margin: 0 0 10px 0;"><i class="fas fa-ban"></i> Why Reassignment Is Blocked:</h6>
                        <ul style="margin: 0; color: #721c24;">
                            <li>Employee is currently assigned to another station</li>
                            <li>Cannot be in two stations simultaneously</li>
                            <li>System prevents scheduling conflicts automatically</li>
                        </ul>
                    </div>
                    
                    <div style="background-color: #d1ecf1; padding: 15px; border-radius: 8px; border-left: 4px solid #17a2b8; margin: 15px 0;">
                        <h6 style="color: #0c5460; margin: 0 0 10px 0;"><i class="fas fa-clipboard-list"></i> Required Steps for Reassignment:</h6>
                        <ol style="margin: 0; color: #0c5460; padding-left: 20px;">
                            <li><strong>Remove Current Assignment:</strong> First, remove <span id="reassign_warning_employee_name"></span> from <span id="reassign_warning_current_station"></span></li>
                            <li><strong>Confirm Removal:</strong> Ensure the employee is no longer assigned to any station</li>
                            <li><strong>Then Reassign:</strong> Assign them to <span id="reassign_warning_target_station"></span></li>
                        </ol>
                        
                        <div style="background-color: #bee5eb; padding: 10px; border-radius: 4px; margin-top: 10px;">
                            <strong>💡 Alternative:</strong> Choose a different employee who is not currently assigned to any station.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="action-btn btn-secondary" onclick="closeModal('reassignmentWarningModal')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="action-btn btn-primary" onclick="closeModal('reassignmentWarningModal')">
                        <i class="fas fa-check"></i> I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>