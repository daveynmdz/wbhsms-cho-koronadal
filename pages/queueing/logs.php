<?php
/**
 * Queue Logs Analytics Interface
 * Purpose: Admin interface for auditing all queue activities and analyzing queue performance
 * Accessible only to admin role for comprehensive queue system oversight
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
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

// DB connection
require_once $root_path . '/config/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'];

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$queue_type_filter = $_GET['queue_type'] ?? 'all';
$employee_filter = $_GET['employee'] ?? 'all';
$action_filter = $_GET['action'] ?? 'all';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Export CSV functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Build query for CSV export (no pagination)
    $export_query = "
        SELECT 
            ql.created_at as date_time,
            qe.queue_code,
            ql.action,
            CONCAT(COALESCE(ql.old_status, 'N/A'), ' → ', ql.new_status) as status_change,
            ql.remarks,
            CONCAT(COALESCE(e.first_name, 'System'), ' ', COALESCE(e.last_name, '')) as performed_by,
            qe.queue_type,
            s.station_name,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM queue_logs ql
        JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
        LEFT JOIN employees e ON ql.performed_by = e.employee_id
        LEFT JOIN patients p ON qe.patient_id = p.patient_id
        LEFT JOIN stations s ON qe.station_id = s.station_id
        WHERE DATE(ql.created_at) BETWEEN ? AND ?
    ";
    
    $params = [$date_from, $date_to];
    $param_types = "ss";
    
    if ($queue_type_filter !== 'all') {
        $export_query .= " AND qe.queue_type = ?";
        $params[] = $queue_type_filter;
        $param_types .= "s";
    }
    
    if ($employee_filter !== 'all') {
        $export_query .= " AND ql.performed_by = ?";
        $params[] = $employee_filter;
        $param_types .= "i";
    }
    
    if ($action_filter !== 'all') {
        $export_query .= " AND ql.action = ?";
        $params[] = $action_filter;
        $param_types .= "s";
    }
    
    $export_query .= " ORDER BY ql.created_at DESC";
    
    $stmt = $conn->prepare($export_query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $export_result = $stmt->get_result();
    
    // Generate CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="queue_logs_' . $date_from . '_to_' . $date_to . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date/Time', 'Queue Code', 'Action', 'Status Change', 'Remarks', 'Performed By', 'Queue Type', 'Station', 'Patient']);
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [
            $row['date_time'],
            $row['queue_code'],
            ucfirst(str_replace('_', ' ', $row['action'])),
            $row['status_change'],
            $row['remarks'],
            $row['performed_by'],
            ucfirst($row['queue_type']),
            $row['station_name'] ?? 'N/A',
            $row['patient_name']
        ]);
    }
    
    fclose($output);
    exit();
}

// Build main query with filters
$base_query = "
    SELECT 
        ql.queue_log_id,
        ql.created_at,
        qe.queue_code,
        ql.action,
        ql.old_status,
        ql.new_status,
        ql.remarks,
        CONCAT(COALESCE(e.first_name, 'System'), ' ', COALESCE(e.last_name, '')) as performed_by_name,
        qe.queue_type,
        s.station_name,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name
    FROM queue_logs ql
    JOIN queue_entries qe ON ql.queue_entry_id = qe.queue_entry_id
    LEFT JOIN employees e ON ql.performed_by = e.employee_id
    LEFT JOIN patients p ON qe.patient_id = p.patient_id
    LEFT JOIN stations s ON qe.station_id = s.station_id
    WHERE DATE(ql.created_at) BETWEEN ? AND ?
";

$params = [$date_from, $date_to];
$param_types = "ss";

if ($queue_type_filter !== 'all') {
    $base_query .= " AND qe.queue_type = ?";
    $params[] = $queue_type_filter;
    $param_types .= "s";
}

if ($employee_filter !== 'all') {
    $base_query .= " AND ql.performed_by = ?";
    $params[] = $employee_filter;
    $param_types .= "i";
}

if ($action_filter !== 'all') {
    $base_query .= " AND ql.action = ?";
    $params[] = $action_filter;
    $param_types .= "s";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM (" . $base_query . ") as counted";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get paginated results
$main_query = $base_query . " ORDER BY ql.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($main_query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get distinct queue types for filter
$queue_types_query = "SELECT DISTINCT queue_type FROM queue_entries ORDER BY queue_type";
$queue_types_result = $conn->query($queue_types_query);
$queue_types = $queue_types_result->fetch_all(MYSQLI_ASSOC);

// Get employees for filter
$employees_query = "SELECT employee_id, first_name, last_name FROM employees WHERE role_id IN (SELECT role_id FROM roles WHERE role_name IN ('nurse', 'doctor', 'laboratory_tech', 'pharmacist', 'cashier', 'records_officer')) ORDER BY first_name, last_name";
$employees_result = $conn->query($employees_query);
$employees = $employees_result->fetch_all(MYSQLI_ASSOC);

// Get distinct actions for filter
$actions_query = "SELECT DISTINCT action FROM queue_logs ORDER BY action";
$actions_result = $conn->query($actions_query);
$actions = $actions_result->fetch_all(MYSQLI_ASSOC);

// Set active page for sidebar highlighting
$activePage = 'queue_management';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Logs Analytics | CHO Koronadal</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        /* Queue Logs specific styles - MATCHING DASHBOARD TEMPLATE */
        .queue-logs-container {
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

        .queue-logs-container .content-area {
            padding: 1.5rem;
            min-height: calc(100vh - 60px);
        }

        /* Breadcrumb Navigation - exactly matching dashboard */
        .queue-logs-container .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .queue-logs-container .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .queue-logs-container .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Page header styling - exactly matching dashboard */
        .queue-logs-container .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .queue-logs-container .page-header h1 {
            color: #0077b6;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-logs-container .page-header h1 i {
            color: #0077b6;
        }

        /* Total count badges styling - exactly matching dashboard */
        .queue-logs-container .total-count {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
        }

        .queue-logs-container .total-count .badge {
            min-width: 120px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            border-radius: 25px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .queue-logs-container .total-count .badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Card container styling - matching dashboard */
        .queue-logs-container .card-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .queue-logs-container .section-header {
            display: flex;
            align-items: center;
            padding: 0 0 15px 0;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 119, 182, 0.2);
        }
        
        .queue-logs-container .section-header h4 {
            margin: 0;
            color: var(--primary-dark);
            font-size: 18px;
            font-weight: 600;
        }
        
        .queue-logs-container .section-header h4 i {
            color: var(--primary);
            margin-right: 8px;
        }

        /* Page title styling - matching dashboard */
        .queue-logs-container .page-title {
            color: var(--primary-dark);
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .queue-logs-container .filter-section {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
        }

        .queue-logs-container .filter-title {
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .queue-logs-container .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .queue-logs-container .form-group {
            display: flex;
            flex-direction: column;
        }

        .queue-logs-container .form-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-size: 14px;
        }

        .queue-logs-container .form-control {
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            font-size: 14px;
            transition: var(--transition);
        }

        .queue-logs-container .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 119, 182, 0.25);
        }

        /* Action buttons - matching dashboard style */
        .queue-logs-container .btn {
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

        .queue-logs-container .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.15);
            text-decoration: none;
        }

        .queue-logs-container .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .queue-logs-container .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .queue-logs-container .btn-outline {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .queue-logs-container .button-group {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Dashboard sections - matching dashboard card style */
        .queue-logs-container .logs-section {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .queue-logs-container .logs-header {
            background: linear-gradient(135deg, #0077b6, #03045e);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
            border-bottom: none;
        }

        .queue-logs-container .logs-title {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 18px;
            font-weight: 600;
        }

        .queue-logs-container .logs-title i {
            color: white;
        }

        .queue-logs-container .records-count {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        /* Table styling - matching dashboard table */
        .queue-logs-container .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        .queue-logs-container .logs-table {
            width: 100%;
            border-collapse: collapse;
            box-shadow: var(--shadow);
        }

        .queue-logs-container .logs-table th {
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

        .queue-logs-container .logs-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            font-size: 14px;
        }

        .queue-logs-container .logs-table tbody tr:hover {
            background-color: rgba(240, 247, 255, 0.6);
            transition: background-color 0.2s;
        }
        
        .queue-logs-container .logs-table tr:last-child td {
            border-bottom: none;
        }

        .queue-logs-container .queue-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: var(--primary);
        }

        .queue-logs-container .action-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .queue-logs-container .action-created { background: #d1ecf1; color: #0c5460; }
        .queue-logs-container .action-status_changed { background: #fff3cd; color: #856404; }
        .queue-logs-container .action-moved { background: #d4edda; color: #155724; }
        .queue-logs-container .action-cancelled { background: #f8d7da; color: #721c24; }
        .queue-logs-container .action-skipped { background: #e2e3e5; color: #383d41; }
        .queue-logs-container .action-reinstated { background: #cce7ff; color: #004085; }

        .queue-logs-container .status-change {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .queue-logs-container .old-status {
            color: var(--danger);
        }

        .queue-logs-container .new-status {
            color: var(--success);
        }

        .queue-logs-container .status-arrow {
            color: var(--secondary);
            margin: 0 0.5rem;
        }

        .queue-logs-container .no-records {
            text-align: center;
            padding: 3rem;
            color: var(--secondary);
        }

        .queue-logs-container .no-records i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }

        .queue-logs-container .text-muted {
            color: var(--secondary) !important;
        }

        .queue-logs-container .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            background: var(--light);
            border-top: 1px solid var(--border);
        }

        .queue-logs-container .pagination-info {
            font-size: 14px;
            color: var(--secondary);
        }

        .queue-logs-container .pagination-controls {
            display: flex;
            gap: 0.5rem;
        }

        .queue-logs-container .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            background: white;
            color: var(--primary);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 14px;
        }

        .queue-logs-container .pagination-btn:hover {
            background: var(--primary);
            color: white;
            text-decoration: none;
        }

        .queue-logs-container .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .queue-logs-container .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .queue-logs-container .export-info {
            font-size: 12px;
            color: var(--secondary);
            margin-top: 0.5rem;
        }

        /* Badge styling - matching dashboard */
        .queue-logs-container .badge {
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
        
        .queue-logs-container .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }
        
        .queue-logs-container .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }
        
        .queue-logs-container .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }
        
        .queue-logs-container .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }
        
        .queue-logs-container .bg-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        /* Mobile responsive for page header - matching dashboard */
        @media (max-width: 768px) {
            .queue-logs-container .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .queue-logs-container .total-count {
                width: 100%;
                justify-content: flex-start;
                gap: 0.75rem;
            }

            .queue-logs-container .total-count .badge {
                min-width: 100px;
                font-size: 0.8rem;
                padding: 6px 12px;
            }

            .queue-logs-container .content-area {
                padding: 1rem;
            }
            
            .queue-logs-container .form-grid {
                grid-template-columns: 1fr;
            }
            
            .queue-logs-container .button-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .queue-logs-container .logs-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .queue-logs-container .pagination {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .queue-logs-container .total-count {
                flex-direction: column;
                align-items: stretch;
                gap: 0.5rem;
            }

            .queue-logs-container .total-count .badge {
                width: 100%;
                min-width: auto;
                text-align: center;
            }
        }
    </style>
</head>

<body>
    <?php
    // Tell the sidebar which menu item to highlight
    $activePage = 'queueing';
    include '../../includes/sidebar_admin.php';
    ?>

    <main class="homepage">
        <div class="queue-logs-container">
            <div class="content-area">
                <!-- Breadcrumb Navigation - matching dashboard -->
                <div class="breadcrumb" style="margin-top: 50px;">
                    <a href="../management/admin/dashboard.php">Admin Dashboard</a>
                    <span>›</span>
                    <a href="dashboard.php">Queue Management</a>
                    <span>›</span>
                    <span>Queue Logs Analytics</span>
                </div>

                <!-- Page Header with Status Badges - matching dashboard -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-history"></i>
                        Queue Logs Analytics
                    </h1>
                    <div class="total-count">
                        <span class="badge bg-primary"><?php echo number_format($total_records); ?> Records</span>
                        <span class="badge bg-info"><?php echo $total_pages; ?> Pages</span>
                    </div>
                </div>

            <!-- Filter Section -->
            <div class="card-container">
                <div class="section-header">
                    <h4>
                        <i class="fas fa-filter"></i>
                        Filter Options
                    </h4>
                </div>
                
                <form method="GET" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Queue Type</label>
                            <select name="queue_type" class="form-control">
                                <option value="all" <?php echo $queue_type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <?php foreach ($queue_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['queue_type']); ?>" 
                                            <?php echo $queue_type_filter === $type['queue_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($type['queue_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Employee</label>
                            <select name="employee" class="form-control">
                                <option value="all" <?php echo $employee_filter === 'all' ? 'selected' : ''; ?>>All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['employee_id']; ?>" 
                                            <?php echo $employee_filter == $employee['employee_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-control">
                                <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" 
                                            <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action['action']))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Apply Filters
                        </button>
                        
                        <a href="?" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Clear Filters
                        </a>
                        
                        <?php
                        $export_params = http_build_query(array_merge($_GET, ['export' => 'csv']));
                        ?>
                        <a href="?<?php echo $export_params; ?>" class="btn btn-success">
                            <i class="fas fa-download"></i>
                            Export to CSV
                        </a>
                        
                        <a href="dashboard.php" class="btn btn-outline">
                            <i class="fas fa-tachometer-alt"></i>
                            Queue Dashboard
                        </a>
                    </div>
                    
                    <div class="export-info">
                        <i class="fas fa-info-circle"></i>
                        CSV export will include all filtered records (no pagination limit)
                    </div>
                </form>
            </div>

            <!-- Logs Section -->
            <div class="logs-section">
                <div class="logs-header">
                    <h3 class="logs-title">
                        <i class="fas fa-list-alt"></i>
                        Queue Activity Logs
                    </h3>
                    <div class="records-count">
                        <?php echo number_format($total_records); ?> records found
                        <?php if ($total_records > 0): ?>
                            | Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="no-records">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>No Records Found</h4>
                        <p>No queue logs match your current filter criteria.</p>
                        <p class="text-muted">Try adjusting your date range or removing some filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="logs-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-clock"></i> Date/Time</th>
                                    <th><i class="fas fa-barcode"></i> Queue Code</th>
                                    <th><i class="fas fa-cogs"></i> Action</th>
                                    <th><i class="fas fa-exchange-alt"></i> Status Change</th>
                                    <th><i class="fas fa-comment"></i> Remarks</th>
                                    <th><i class="fas fa-user"></i> Performed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                        <td>
                                            <span class="queue-code"><?php echo htmlspecialchars($log['queue_code'] ?? 'N/A'); ?></span>
                                            <?php if ($log['patient_name']): ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($log['patient_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="action-badge action-<?php echo $log['action']; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?>
                                            </span>
                                            <?php if ($log['queue_type']): ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars(ucfirst($log['queue_type'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="status-change">
                                                <?php if ($log['old_status']): ?>
                                                    <span class="old-status"><?php echo htmlspecialchars(str_replace('_', ' ', $log['old_status'])); ?></span>
                                                    <span class="status-arrow">→</span>
                                                <?php endif; ?>
                                                <span class="new-status"><?php echo htmlspecialchars(str_replace('_', ' ', $log['new_status'])); ?></span>
                                            </div>
                                            <?php if ($log['station_name']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($log['station_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['remarks']): ?>
                                                <?php echo htmlspecialchars($log['remarks']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($log['performed_by_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div class="pagination-info">
                                Showing <?php echo number_format(($page - 1) * $limit + 1); ?> to 
                                <?php echo number_format(min($page * $limit, $total_records)); ?> of 
                                <?php echo number_format($total_records); ?> records
                            </div>
                            
                            <div class="pagination-controls">
                                <?php if ($page > 1): ?>
                                    <?php
                                    $prev_params = array_merge($_GET, ['page' => $page - 1]);
                                    unset($prev_params['export']);
                                    ?>
                                    <a href="?<?php echo http_build_query($prev_params); ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                    $page_params = array_merge($_GET, ['page' => $i]);
                                    unset($page_params['export']);
                                ?>
                                    <a href="?<?php echo http_build_query($page_params); ?>" 
                                       class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <?php
                                    $next_params = array_merge($_GET, ['page' => $page + 1]);
                                    unset($next_params['export']);
                                    ?>
                                    <a href="?<?php echo http_build_query($next_params); ?>" class="pagination-btn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </main>

    <!-- Essential JS -->
    <script>
        // Auto-submit form when date inputs change (for better UX)
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            let timeout;
            
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        // Auto-submit after 1 second of no changes
                        if (this.form) {
                            this.form.submit();
                        }
                    }, 1000);
                });
            });

            // Add tooltips for action badges
            const actionBadges = document.querySelectorAll('.action-badge');
            actionBadges.forEach(badge => {
                const action = badge.textContent.trim().toLowerCase();
                let tooltip = '';
                
                switch(action) {
                    case 'created':
                        tooltip = 'Queue entry was created for patient';
                        break;
                    case 'status changed':
                        tooltip = 'Queue status was updated';
                        break;
                    case 'moved':
                        tooltip = 'Patient was transferred between queues';
                        break;
                    case 'cancelled':
                        tooltip = 'Queue entry was cancelled';
                        break;
                    case 'skipped':
                        tooltip = 'Patient was skipped in queue';
                        break;
                    case 'reinstated':
                        tooltip = 'Previously cancelled/skipped entry was reinstated';
                        break;
                }
                
                if (tooltip) {
                    badge.title = tooltip;
                }
            });

            console.log('Queue Logs Analytics interface initialized');
        });
    </script>
</body>
</html>