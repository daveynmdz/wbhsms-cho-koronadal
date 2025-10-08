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

function getActionBadgeClass($action_type) {
    switch ($action_type) {
        case 'create': return 'bg-success';
        case 'update': return 'bg-info';
        case 'deactivate': return 'bg-warning';
        case 'activate': return 'bg-success';
        case 'password_reset': return 'bg-danger';
        case 'role_change': return 'bg-primary';
        case 'station_assign': 
        case 'station_unassign': return 'bg-secondary';
        case 'unlock': return 'bg-success';
        default: return 'bg-dark';
    }
}

function formatJsonData($json_string) {
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
    <title>User Activity Logs - Security & Auditing</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    
    <style>
        .main-header {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            color: white;
            padding: 25px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        
        .logs-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .log-entry {
            border-left: 4px solid #dee2e6;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            transition: all 0.3s ease;
        }
        
        .log-entry:hover {
            border-left-color: #007bff;
            background: #e3f2fd;
        }
        
        .log-entry.high-priority {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        .log-entry.medium-priority {
            border-left-color: #ffc107;
            background: #fffbf0;
        }
        
        .log-metadata {
            font-size: 0.85em;
            color: #6c757d;
        }
        
        .log-changes {
            background: #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .stats-row {
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #007bff;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column !important;
            }
            
            .log-entry {
                padding: 10px;
            }
            
            .log-metadata {
                font-size: 0.8em;
            }
        }
    </style>
</head>

<body>
    <?php 
    require_once $root_path . '/includes/topbar.php';
    renderTopbar([
        'title' => 'User Activity Logs',
        'subtitle' => 'Security & Audit Trail',
        'back_url' => 'employee_list.php',
        'user_type' => 'employee'
    ]); 
    ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <?php 
                $defaults = [];
                require_once $root_path . '/includes/sidebar_admin.php'; 
                ?>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="container-fluid mt-4">
                    
                    <!-- Main Header -->
                    <div class="main-header text-center">
                        <h2><i class="fas fa-history"></i> User Activity Logs</h2>
                        <p class="mb-0">Comprehensive audit trail and security monitoring for CHO Koronadal</p>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="stats-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= number_format($total_records) ?></div>
                                    <div class="text-muted">Total Logs</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <?php
                                    $today_count = 0;
                                    foreach ($activity_logs as $log) {
                                        if (date('Y-m-d', strtotime($log['created_at'])) === date('Y-m-d')) {
                                            $today_count++;
                                        }
                                    }
                                    ?>
                                    <div class="stat-number text-primary"><?= $today_count ?></div>
                                    <div class="text-muted">Today</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <?php
                                    $password_resets = array_filter($activity_logs, function($log) {
                                        return $log['action_type'] === 'password_reset';
                                    });
                                    ?>
                                    <div class="stat-number text-warning"><?= count($password_resets) ?></div>
                                    <div class="text-muted">Password Resets</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <?php
                                    $security_actions = array_filter($activity_logs, function($log) {
                                        return in_array($log['action_type'], ['deactivate', 'unlock', 'role_change']);
                                    });
                                    ?>
                                    <div class="stat-number text-danger"><?= count($security_actions) ?></div>
                                    <div class="text-muted">Security Actions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error Messages -->
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <h5><i class="fas fa-filter"></i> Filter Activity Logs</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Description, names...">
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Action Type</label>
                                <select name="action_filter" class="form-select">
                                    <option value="">All Actions</option>
                                    <?php foreach ($action_types as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($action_filter === $value) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Target Employee</label>
                                <select name="employee_filter" class="form-select">
                                    <option value="">All Employees</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['employee_id'] ?>" 
                                                <?= ($employee_filter == $emp['employee_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Admin User</label>
                                <select name="admin_filter" class="form-select">
                                    <option value="">All Admins</option>
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?= $emp['employee_id'] ?>" 
                                                <?= ($admin_filter == $emp['employee_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">From</label>
                                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            
                            <div class="col-md-1">
                                <label class="form-label">To</label>
                                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Activity Logs -->
                    <div class="logs-section">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5><i class="fas fa-clipboard-list"></i> Activity Timeline</h5>
                            <div>
                                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-download"></i> Export CSV
                                </a>
                            </div>
                        </div>
                        
                        <?php if (empty($activity_logs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <br>No activity logs found matching your criteria.
                            </div>
                        <?php else: ?>
                            <?php foreach ($activity_logs as $log): ?>
                                <?php
                                $priority_class = '';
                                if (in_array($log['action_type'], ['password_reset', 'deactivate', 'role_change'])) {
                                    $priority_class = 'high-priority';
                                } elseif (in_array($log['action_type'], ['update', 'unlock'])) {
                                    $priority_class = 'medium-priority';
                                }
                                ?>
                                <div class="log-entry <?= $priority_class ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="badge <?= getActionBadgeClass($log['action_type']) ?> me-2">
                                                    <?= htmlspecialchars($action_types[$log['action_type']] ?? $log['action_type']) ?>
                                                </span>
                                                <strong><?= htmlspecialchars($log['description']) ?></strong>
                                            </div>
                                            
                                            <div class="log-metadata">
                                                <div class="row">
                                                    <div class="col-md-6">
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
                                                    <div class="col-md-6 text-md-end">
                                                        <i class="fas fa-clock"></i> 
                                                        <strong><?= date('M j, Y g:i:s A', strtotime($log['created_at'])) ?></strong>
                                                        
                                                        <?php if (!empty($log['ip_address'])): ?>
                                                            <br><i class="fas fa-map-marker-alt"></i> 
                                                            IP: <?= htmlspecialchars($log['ip_address']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                                                <div class="log-changes mt-2">
                                                    <?php if (!empty($log['old_values'])): ?>
                                                        <div><strong>Previous Values:</strong></div>
                                                        <div class="mb-2"><?= formatJsonData($log['old_values']) ?></div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($log['new_values'])): ?>
                                                        <div><strong>New Values:</strong></div>
                                                        <div><?= formatJsonData($log['new_values']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="d-flex justify-content-center mt-4">
                                <nav>
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
                                </nav>
                            </div>
                            
                            <div class="text-center text-muted">
                                Showing <?= min($offset + 1, $total_records) ?> to <?= min($offset + $per_page, $total_records) ?> 
                                of <?= number_format($total_records) ?> activity logs
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="text-center">
                        <a href="employee_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Employee List
                        </a>
                        <a href="password_manager.php" class="btn btn-outline-warning">
                            <i class="fas fa-shield-alt"></i> Password Manager
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh page every 30 seconds for real-time monitoring
        setInterval(function() {
            if (document.querySelector('.alert') === null) { // Only refresh if no alerts are showing
                window.location.reload();
            }
        }, 30000);
        
        // Auto-dismiss alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 8000);
        
        // Enhanced date inputs - set default ranges if empty
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</body>
</html>