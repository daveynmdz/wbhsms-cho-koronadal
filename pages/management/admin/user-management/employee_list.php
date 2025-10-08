<?php
// pages/management/admin/user-management/employee_list.php
// Employee Management Dashboard with CRUD operations, filtering, and status management
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

// Initialize variables
$success_message = '';
$error_message = '';

// Handle actions (deactivate, activate, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $employee_id = intval($_POST['employee_id'] ?? 0);
        
        if ($employee_id <= 0) {
            throw new Exception('Invalid employee ID');
        }
        
        // Prevent admin from deactivating themselves
        if ($employee_id == $_SESSION['employee_id'] && in_array($action, ['deactivate', 'delete'])) {
            throw new Exception('You cannot deactivate or delete your own account');
        }
        
        $conn->begin_transaction();
        
        switch ($action) {
            case 'deactivate':
                $stmt = $conn->prepare("UPDATE employees SET status = 'inactive' WHERE employee_id = ?");
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                
                // Log activity
                $log_description = "Deactivated employee ID: $employee_id";
                break;
                
            case 'activate':
                $stmt = $conn->prepare("UPDATE employees SET status = 'active' WHERE employee_id = ?");
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                
                // Log activity
                $log_description = "Activated employee ID: $employee_id";
                break;
                
            case 'reset_password':
                // Generate new secure password
                $new_password = 'CHO' . date('Y') . '@' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE employees SET password = ?, must_change_password = 1, password_changed_at = NOW() WHERE employee_id = ?");
                $stmt->bind_param("si", $hashed_password, $employee_id);
                $stmt->execute();
                
                // Log activity
                $log_description = "Reset password for employee ID: $employee_id";
                $success_message = "Password reset successfully! New password: <strong>$new_password</strong><br><small>Please share this securely with the employee.</small>";
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
        // Log the activity
        if (isset($log_description)) {
            $log_stmt = $conn->prepare("
                INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $log_stmt->bind_param("iissss", $_SESSION['employee_id'], $employee_id, $action, $log_description, $ip_address, $user_agent);
            $log_stmt->execute();
        }
        
        $conn->commit();
        
        if (empty($success_message)) {
            $success_message = ucfirst($action) . " completed successfully!";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Action failed: " . $e->getMessage();
    }
}

// Handle search and filtering
$search = trim($_GET['search'] ?? '');
$role_filter = intval($_GET['role_filter'] ?? 0);
$status_filter = $_GET['status_filter'] ?? '';
$facility_filter = intval($_GET['facility_filter'] ?? 0);

// Pagination setup
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(e.employee_number LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

if ($role_filter > 0) {
    $where_conditions[] = "e.role_id = ?";
    $params[] = $role_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($facility_filter > 0) {
    $where_conditions[] = "e.facility_id = ?";
    $params[] = $facility_filter;
    $types .= 'i';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM employees e 
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get employees with pagination
$query = "
    SELECT 
        e.employee_id,
        e.employee_number,
        e.first_name,
        e.middle_name,
        e.last_name,
        e.email,
        e.contact_num,
        e.status,
        e.license_number,
        e.birth_date,
        e.gender,
        e.last_login,
        e.must_change_password,
        e.created_at,
        r.role_name,
        f.name as facility_name,
        f.type as facility_type
    FROM employees e
    LEFT JOIN roles r ON e.role_id = r.role_id
    LEFT JOIN facilities f ON e.facility_id = f.facility_id
    $where_clause
    ORDER BY e.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
$limit_params = array_merge($params, [$per_page, $offset]);
$limit_types = $types . 'ii';

if (!empty($limit_params)) {
    $stmt->bind_param($limit_types, ...$limit_params);
}
$stmt->execute();
$employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get roles for filter dropdown
$roles_stmt = $conn->prepare("SELECT role_id, role_name FROM roles ORDER BY role_name");
$roles_stmt->execute();
$roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get facilities for filter dropdown
$facilities_stmt = $conn->prepare("SELECT facility_id, name FROM facilities WHERE status = 'active' ORDER BY name");
$facilities_stmt->execute();
$facilities = $facilities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - User Management System</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Files - loaded by sidebar -->
    
    <style>
        /* Exact CSS from referrals_management.php */

        .content-wrapper {
            margin-left: 300px;
            padding: 2rem;
            transition: margin-left 0.3s;
        }

        @media (max-width: 768px) {
            .content-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
        }

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
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card.total {
            border-left: 4px solid #0077b6;
        }

        .stat-card.active {
            border-left: 4px solid #43e97b;
        }

        .stat-card.inactive {
            border-left: 4px solid #fa709a;
        }

        .stat-card.on_leave {
            border-left: 4px solid #f093fb;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filters-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #0077b6;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #023e8a, #001d3d);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .btn-outline-info {
            background: transparent;
            color: #17a2b8;
            border: 2px solid #17a2b8;
        }

        .btn-outline-info:hover {
            background: #17a2b8;
            color: white;
        }
        
        .card-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #0077b6;
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
            white-space: normal;
            align-content: flex-start;
        }

        .table th {
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
            align-content: center;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .actions-group {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f1b2b7;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-primary {
            background: #007bff;
            color: white;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #212529;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-secondary {
            background: #6c757d;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

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

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-inactive {
            background: #dc3545;
            color: white;
        }

        .status-on_leave {
            background: #ffc107;
            color: #212529;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.4rem 0.6rem;
            font-size: 0.75rem;
            border-radius: 6px;
            border: 1px solid;
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
        }

        .btn-outline-primary.btn-action {
            color: #0077b6;
            border-color: #0077b6;
        }

        .btn-outline-primary.btn-action:hover {
            background: #0077b6;
            color: white;
        }

        .btn-outline-warning.btn-action {
            color: #ffc107;
            border-color: #ffc107;
        }

        .btn-outline-warning.btn-action:hover {
            background: #ffc107;
            color: #212529;
        }

        .btn-outline-success.btn-action {
            color: #28a745;
            border-color: #28a745;
        }

        .btn-outline-success.btn-action:hover {
            background: #28a745;
            color: white;
        }

        .btn-outline-info.btn-action {
            color: #17a2b8;
            border-color: #17a2b8;
        }

        .btn-outline-info.btn-action:hover {
            background: #17a2b8;
            color: white;
        }

        .btn-outline-secondary.btn-action {
            color: #6c757d;
            border-color: #6c757d;
        }

        .btn-outline-secondary.btn-action:hover {
            background: #6c757d;
            color: white;
        }
        
        /* Pagination */
        .pagination-wrapper {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .page-item {
            display: inline-block;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            background: white;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 40px;
            justify-content: center;
        }

        .page-link:hover {
            border-color: #0077b6;
            color: #0077b6;
            background: #f8f9fa;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border-color: #0077b6;
        }

        .pagination-info {
            margin-top: 1rem;
            text-align: center;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
                font-size: 0.8rem;
            }

            .table th {
                font-size: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.25rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .table {
                min-width: 600px;
            }

            .table th,
            .table td {
                padding: 0.4rem 0.2rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
    <!-- Include sidebar -->
    <?php include $root_path . '/includes/sidebar_admin.php'; ?>
    
    <section class="content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb" style="margin-top: 50px;">
            <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Employee Management</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> Employee Management</h1>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="user_activity_logs.php" class="btn btn-outline-info">
                    <i class="fas fa-history"></i> View Activity Logs
                </a>
                <a href="add_employee.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Employee
                </a>
            </div>
        </div>
                    
        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($total_records) ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card">
                <?php
                $active_count = array_reduce($employees, function($carry, $emp) { 
                    return $carry + ($emp['status'] === 'active' ? 1 : 0); 
                }, 0);
                ?>
                <div class="stat-number success"><?= $active_count ?></div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <?php
                $inactive_count = array_reduce($employees, function($carry, $emp) { 
                    return $carry + ($emp['status'] === 'inactive' ? 1 : 0); 
                }, 0);
                ?>
                <div class="stat-number warning"><?= $inactive_count ?></div>
                <div class="stat-label">Inactive</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info"><?= count($roles) ?></div>
                <div class="stat-label">Roles</div>
            </div>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="filters-container">
            <div class="section-header" style="padding: 0 0 15px 0;margin-bottom: 15px;border-bottom: 1px solid rgba(0, 119, 182, 0.2);">
                <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-filter"></i> Search & Filter Options
                </h4>
            </div>
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Name, email, employee number...">
                </div>
                <div class="form-group">
                    <label for="role_filter">Role</label>
                    <select id="role_filter" name="role_filter">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['role_id'] ?>" 
                                    <?= ($role_filter == $role['role_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status_filter">Status</label>
                    <select id="status_filter" name="status_filter">
                        <option value="">All Status</option>
                        <option value="active" <?= ($status_filter === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($status_filter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                        <option value="on_leave" <?= ($status_filter === 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                        <option value="retired" <?= ($status_filter === 'retired') ? 'selected' : '' ?>>Retired</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="facility_filter">Facility</label>
                    <select id="facility_filter" name="facility_filter">
                        <option value="">All Facilities</option>
                        <?php foreach ($facilities as $facility): ?>
                            <option value="<?= $facility['facility_id'] ?>" 
                                    <?= ($facility_filter == $facility['facility_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($facility['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="form-group">
                    <a href="?" class="btn btn-secondary" style="margin-top: 0.5rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Employee Table -->
        <div class="card-container">
            <div class="section-header" style="padding: 0 0 15px 0;margin-bottom: 15px;border-bottom: 1px solid rgba(0, 119, 182, 0.2);">
                <h4 style="margin: 0;color: var(--primary-dark);font-size: 18px;font-weight: 600;">
                    <i class="fas fa-users"></i> Employee Directory
                </h4>
            </div>
            <div class="table-container">
                <div class="table-wrapper">
                    <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee #</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Facility</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <br>No employees found matching your criteria.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($employee['employee_number']) ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                                            <?php if ($employee['must_change_password']): ?>
                                                <br><small style="color: #856404;"><i class="fas fa-exclamation-triangle"></i> Must change password</small>
                                            <?php endif; ?>
                                        </div>
                                        <small style="color: #6c757d;"><?= htmlspecialchars($employee['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($employee['role_name']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($employee['facility_name']) ?></small>
                                        <br><small style="color: #6c757d;"><?= htmlspecialchars($employee['facility_type']) ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($employee['contact_num']) ?></small>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $employee['status'] ?>">
                                            <?= ucfirst($employee['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?= $employee['last_login'] ? 
                                                date('M j, Y g:i A', strtotime($employee['last_login'])) : 
                                                '<span style="color: #6c757d;">Never</span>' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_employee.php?id=<?= $employee['employee_id'] ?>" 
                                               class="btn-action btn-outline-primary" 
                                               title="Edit Employee">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($employee['status'] === 'active'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                    <button type="submit" class="btn-action btn-outline-warning" 
                                                            title="Deactivate Employee"
                                                            onclick="return confirm('Are you sure you want to deactivate this employee?')">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                    <button type="submit" class="btn-action btn-outline-success" 
                                                            title="Activate Employee">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                <button type="submit" class="btn-action btn-outline-info" 
                                                        title="Reset Password"
                                                        onclick="return confirm('Are you sure you want to reset this employee\'s password?')">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            </form>
                                            
                                            <a href="../staff-management/staff_assignments.php?employee_filter=<?= $employee['employee_id'] ?>" 
                                               class="btn-action btn-outline-secondary" 
                                               title="Assign Stations">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="pagination-info">
                    Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $per_page, $total_records) ?> 
                    of <?= number_format($total_records) ?> employees
                </div>
            </div>
        <?php endif; ?>
    </section>
    
    <script>
        // Auto-dismiss alerts after 8 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 8000);
        
        // Confirm bulk actions
        document.querySelectorAll('form[method="POST"]').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const action = form.querySelector('input[name="action"]').value;
                if (['deactivate', 'delete', 'reset_password'].includes(action)) {
                    if (!confirm(`Are you sure you want to ${action.replace('_', ' ')} this employee?`)) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>