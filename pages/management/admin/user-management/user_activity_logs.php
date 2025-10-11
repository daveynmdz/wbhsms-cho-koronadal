<?php
// pages/management/admin/user-management/user_activity_logs.php
// Comprehensive Security and Auditing System - Activity Logs Viewer
// Author: GitHub Copilot

// Include employee session configuration - Use absolute path resolution
$root_path = dirname(dirname(dirname(dirname(__DIR__))));
require_once $root_path . '/config/session/employee_session.php';
require_once $root_path . '/config/db.php';

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['employee_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/employee_login.php');
    exit();
}

// Set active page for sidebar highlighting
$activePage = 'user_management';

// Initialize filters
$search = trim($_GET['search'] ?? '');
$action_filter = $_GET['action_filter'] ?? '';
$employee_filter = intval($_GET['employee_filter'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$admin_filter = intval($_GET['admin_filter'] ?? 0);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(ual.description LIKE ? OR ae.first_name LIKE ? OR ae.last_name LIKE ? OR te.first_name LIKE ? OR te.last_name LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= 'sssss';
}

if (!empty($action_filter)) {
    $where_conditions[] = "ual.action_type = ?";
    $params[] = $action_filter;
    $types .= 's';
}

if ($employee_filter > 0) {
    $where_conditions[] = "ual.employee_id = ?";
    $params[] = $employee_filter;
    $types .= 'i';
}

if ($admin_filter > 0) {
    $where_conditions[] = "ual.admin_id = ?";
    $params[] = $admin_filter;
    $types .= 'i';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(ual.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(ual.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM user_activity_logs ual 
    LEFT JOIN employees ae ON ual.admin_id = ae.employee_id
    LEFT JOIN employees te ON ual.employee_id = te.employee_id
    $where_clause
";

try {
    $count_stmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_records / $per_page);
} catch (Exception $e) {
    $total_records = 0;
    $total_pages = 1;
}

// Get activity logs
try {
    $query = "
        SELECT 
            ual.*,
            ae.first_name as admin_first_name,
            ae.last_name as admin_last_name,
            ae.employee_number as admin_employee_number,
            te.first_name as target_first_name,
            te.last_name as target_last_name,
            te.employee_number as target_employee_number,
            ar.role_name as admin_role,
            tr.role_name as target_role
        FROM user_activity_logs ual
        LEFT JOIN employees ae ON ual.admin_id = ae.employee_id
        LEFT JOIN employees te ON ual.employee_id = te.employee_id
        LEFT JOIN roles ar ON ae.role_id = ar.role_id
        LEFT JOIN roles tr ON te.role_id = tr.role_id
        $where_clause
        ORDER BY ual.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    $limit_params = array_merge($params, [$per_page, $offset]);
    $limit_types = $types . 'ii';

    if (!empty($limit_params)) {
        $stmt->bind_param($limit_types, ...$limit_params);
    }
    $stmt->execute();
    $activity_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $activity_logs = [];
    $error_message = "Failed to load activity logs: " . $e->getMessage();
}

// Get employees for filter dropdowns
try {
    $employees_stmt = $conn->prepare("
        SELECT employee_id, employee_number, first_name, last_name, role_id
        FROM employees 
        WHERE status = 'active' 
        ORDER BY first_name, last_name
    ");
    $employees_stmt->execute();
    $employees = $employees_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Action types for filter
$action_types = [
    'create' => 'Employee Created',
    'update' => 'Employee Updated',
    'deactivate' => 'Employee Deactivated',
    'activate' => 'Employee Activated',
    'password_reset' => 'Password Reset',
    'role_change' => 'Role Changed',
    'station_assign' => 'Station Assigned',
    'station_unassign' => 'Station Unassigned',
    'unlock' => 'Account Unlocked'
];

function getActionBadgeClass($action_type)
{
    switch ($action_type) {
        case 'create':
            return 'bg-success';
        case 'update':
            return 'bg-info';
        case 'deactivate':
            return 'bg-warning';
        case 'activate':
            return 'bg-success';
        case 'password_reset':
            return 'bg-danger';
        case 'role_change':
            return 'bg-primary';
        case 'station_assign':
        case 'station_unassign':
            return 'bg-secondary';
        case 'unlock':
            return 'bg-success';
        default:
            return 'bg-dark';
    }
}

function formatJsonData($json_string)
{
    if (empty($json_string)) return null;

    $data = json_decode($json_string, true);
    if (!$data) return null;

    $formatted = [];
    foreach ($data as $key => $value) {
        $formatted[] = "<strong>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</strong> " . htmlspecialchars($value);
    }

    return implode('<br>', $formatted);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Activity Logs Management - MATCHING EMPLOYEE LIST TEMPLATE */
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid var(--border);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--info));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            display: block;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--secondary);
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            color: var(--primary);
            opacity: 0.3;
        }

        .filters-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
            outline: none;
        }

        .logs-container {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .logs-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .logs-body {
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
        }

        .log-entry {
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            transition: var(--transition);
            background: white;
            overflow: hidden;
        }

        .log-entry:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
        }

        .log-header {
            padding: 1rem 1.5rem;
            background: var(--light);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .log-body {
            padding: 1.5rem;
        }

        .log-meta {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .log-changes {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-top: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            border: 1px solid var(--border);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.4rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bg-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .bg-info {
            background: linear-gradient(135deg, #48cae4, #0077b6);
        }

        .bg-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }

        .bg-danger {
            background: linear-gradient(135deg, #ef476f, #d00000);
        }

        .bg-primary {
            background: linear-gradient(135deg, #0096c7, #03045e);
        }

        .bg-secondary {
            background: linear-gradient(135deg, #adb5bd, #6c757d);
        }

        .bg-dark {
            background: linear-gradient(135deg, #495057, #212529);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            color: white;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
            color: white;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #48cae4, #0096c7);
        }

        .btn-success {
            background: linear-gradient(135deg, #52b788, #2d6a4f);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffba08, #faa307);
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .page-link:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .log-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .log-body {
                padding: 1rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }

            .logs-header {
                padding: 1rem;
            }
        }

        /* Content header styling */
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            color: var(--primary-dark);
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            color: var(--secondary);
            font-size: 1rem;
            margin: 0.5rem 0 0 0;
            font-weight: 400;
        }

        /* Breadcrumb styling */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
            margin-top: 50px;
        }

        .breadcrumb a {
            color: #0077b6;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <!-- Include sidebar -->
    <?php include '../../../../includes/sidebar_admin.php'; ?>

    <div class="homepage">
        <div class="main-content">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="../dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <span>></span>
                <a href="employee_list.php">User Management</a>
                <span>></span>
                <span>Activity Logs</span>
            </div>

            <!-- Page Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-history"></i>
                        User Activity Logs
                    </h1>
                    <p class="page-subtitle">Comprehensive audit trail and security monitoring for CHO Koronadal</p>
                </div>


                <!-- Navigation Links -->
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="employee_list.php" class="action-btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Employee List
                    </a>
                    <a href="password_manager.php" class="action-btn btn-warning" style="margin-left: 1rem;">
                        <i class="fas fa-shield-alt"></i> Password Manager
                    </a>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-clipboard-list stat-icon"></i>
                    <div class="stat-number"><?= number_format($total_records) ?></div>
                    <div class="stat-label">Total Logs</div>
                </div>
                <div class="stat-card">
                    <?php
                    $today_count = 0;
                    foreach ($activity_logs as $log) {
                        if (date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d')) {
                            $today_count++;
                        }
                    }
                    ?>
                    <i class="fas fa-calendar-day stat-icon"></i>
                    <div class="stat-number"><?= $today_count ?></div>
                    <div class="stat-label">Today</div>
                </div>
                <div class="stat-card">
                    <?php
                    $password_resets = array_filter($activity_logs, function ($log) {
                        return $log['action_type'] === 'password_reset';
                    });
                    ?>
                    <i class="fas fa-key stat-icon"></i>
                    <div class="stat-number"><?= count($password_resets) ?></div>
                    <div class="stat-label">Password Resets</div>
                </div>
                <div class="stat-card">
                    <?php
                    $security_actions = array_filter($activity_logs, function ($log) {
                        return in_array($log['action_type'], ['deactivate', 'unlock', 'role_change']);
                    });
                    ?>
                    <i class="fas fa-shield-alt stat-icon"></i>
                    <div class="stat-number"><?= count($security_actions) ?></div>
                    <div class="stat-label">Security Actions</div>
                </div>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filters-card">
                <h3><i class="fas fa-filter"></i> Filter Activity Logs</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Description, names...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Action Type</label>
                        <select name="action_filter" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($action_types as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($action_filter === $value) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Target Employee</label>
                        <select name="employee_filter" class="form-control">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['employee_id'] ?>"
                                    <?= ($employee_filter == $emp['employee_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Admin User</label>
                        <select name="admin_filter" class="form-control">
                            <option value="">All Admins</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?= $emp['employee_id'] ?>"
                                    <?= ($admin_filter == $emp['employee_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">From</label>
                        <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">To</label>
                        <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="action-btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Activity Logs -->
            <div class="logs-container">
                <div class="logs-header">
                    <h3><i class="fas fa-clipboard-list"></i> Activity Timeline</h3>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="action-btn btn-success">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>

                <div class="logs-body">
                    <?php if (empty($activity_logs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No activity logs found</h4>
                            <p>No activity logs match your current filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="log-entry">
                                <div class="log-header">
                                    <div>
                                        <span class="badge <?= getActionBadgeClass($log['action_type']) ?>">
                                            <?= htmlspecialchars($action_types[$log['action_type']] ?? $log['action_type']) ?>
                                        </span>
                                        <strong><?= htmlspecialchars($log['description']) ?></strong>
                                    </div>
                                    <div class="log-meta">
                                        <i class="fas fa-clock"></i>
                                        <?= date('M j, Y g:i:s A', strtotime($log['created_at'])) ?>
                                    </div>
                                </div>

                                <div class="log-body">
                                    <div class="log-meta">
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                                            <div>
                                                <i class="fas fa-user-shield"></i>
                                                <strong>Admin:</strong>
                                                <?= htmlspecialchars(($log['admin_first_name'] ?? '') . ' ' . ($log['admin_last_name'] ?? '')) ?>
                                                (<?= htmlspecialchars($log['admin_employee_number'] ?? 'N/A') ?>)

                                                <?php if (!empty($log['target_first_name'])): ?>
                                                    <br><i class="fas fa-user"></i>
                                                    <strong>Target:</strong>
                                                    <?= htmlspecialchars($log['target_first_name'] . ' ' . $log['target_last_name']) ?>
                                                    (<?= htmlspecialchars($log['target_employee_number']) ?>)
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <?php if (!empty($log['ip_address'])): ?>
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <strong>IP Address:</strong> <?= htmlspecialchars($log['ip_address']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                        <div class="log-changes">
                                            <?php if (!empty($log['old_values'])): ?>
                                                <div><strong>Previous Values:</strong></div>
                                                <div style="margin-bottom: 1rem;"><?= formatJsonData($log['old_values']) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($log['new_values'])): ?>
                                                <div><strong>New Values:</strong></div>
                                                <div><?= formatJsonData($log['new_values']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a class="page-link <?= ($i === $page) ? 'active' : '' ?>"
                                    href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div style="text-align: center; color: var(--secondary); margin-top: 1rem;">
                            Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $per_page, $total_records) ?>
                            of <?= number_format($total_records) ?> activity logs
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 5000);
            });

            // Enhanced date inputs - set default ranges if empty
            const dateFromInput = document.querySelector('input[name="date_from"]');
            const dateToInput = document.querySelector('input[name="date_to"]');

            if (dateFromInput && !dateFromInput.value) {
                // Default to 7 days ago
                const weekAgo = new Date();
                weekAgo.setDate(weekAgo.getDate() - 7);
                dateFromInput.value = weekAgo.toISOString().split('T')[0];
            }

            if (dateToInput && !dateToInput.value) {
                // Default to today
                const today = new Date();
                dateToInput.value = today.toISOString().split('T')[0];
            }
        });

        // Auto-refresh page every 2 minutes for real-time monitoring (optional)
        // setInterval(function() {
        //     if (!document.querySelector('.alert')) {
        //         window.location.reload();
        //     }
        // }, 120000);
    </script>
</body>

</html>