<?php
// pages/management/admin/user-management/password_manager.php
// Password & Authentication Control System with complexity validation and MFA preparation
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
$validation_errors = [];

// Password complexity requirements
$password_requirements = [
    'min_length' => 8,
    'max_length' => 128,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_symbols' => true,
    'forbidden_patterns' => ['password', '123456', 'admin', 'user']
];

// Handle bulk password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        
        if ($action === 'bulk_reset') {
            $employee_ids = $_POST['employee_ids'] ?? [];
            $reset_count = 0;
            $failed_count = 0;
            
            if (empty($employee_ids)) {
                throw new Exception('No employees selected');
            }
            
            $conn->begin_transaction();
            
            foreach ($employee_ids as $employee_id) {
                try {
                    $employee_id = intval($employee_id);
                    
                    // Skip self
                    if ($employee_id == $_SESSION['employee_id']) {
                        continue;
                    }
                    
                    // Generate secure password
                    $new_password = generateSecurePassword();
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update employee password
                    $stmt = $conn->prepare("
                        UPDATE employees 
                        SET password = ?, must_change_password = 1, password_changed_at = NOW(), 
                            failed_login_attempts = 0, locked_until = NULL
                        WHERE employee_id = ?
                    ");
                    $stmt->bind_param("si", $hashed_password, $employee_id);
                    $stmt->execute();
                    
                    // Log activity
                    $log_stmt = $conn->prepare("
                        INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, ip_address, user_agent) 
                        VALUES (?, ?, 'password_reset', ?, ?, ?)
                    ");
                    
                    $description = "Password reset via bulk operation - New password: $new_password";
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    
                    $log_stmt->bind_param("iissss", $_SESSION['employee_id'], $employee_id, $description, $ip_address, $user_agent);
                    $log_stmt->execute();
                    
                    $reset_count++;
                    
                } catch (Exception $e) {
                    $failed_count++;
                }
            }
            
            $conn->commit();
            
            $success_message = "Bulk password reset completed! Reset: $reset_count employees" . 
                ($failed_count > 0 ? " (Failed: $failed_count)" : "");
                
        } elseif ($action === 'single_reset') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            
            if ($employee_id <= 0) {
                throw new Exception('Invalid employee ID');
            }
            
            if ($employee_id == $_SESSION['employee_id']) {
                throw new Exception('Cannot reset your own password through this interface');
            }
            
            $conn->begin_transaction();
            
            // Generate secure password
            $new_password = generateSecurePassword();
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update employee password
            $stmt = $conn->prepare("
                UPDATE employees 
                SET password = ?, must_change_password = 1, password_changed_at = NOW(),
                    failed_login_attempts = 0, locked_until = NULL
                WHERE employee_id = ?
            ");
            $stmt->bind_param("si", $hashed_password, $employee_id);
            $stmt->execute();
            
            // Create password reset token for secure delivery
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $token_stmt = $conn->prepare("
                INSERT INTO password_reset_tokens (employee_id, token, expires_at, created_by) 
                VALUES (?, ?, ?, ?)
            ");
            $token_stmt->bind_param("issi", $employee_id, $token, $expires_at, $_SESSION['employee_id']);
            $token_stmt->execute();
            
            // Log activity
            $log_stmt = $conn->prepare("
                INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, ip_address, user_agent) 
                VALUES (?, ?, 'password_reset', ?, ?, ?)
            ");
            
            $description = "Password reset with secure token - Employee must verify identity";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $log_stmt->bind_param("iissss", $_SESSION['employee_id'], $employee_id, $description, $ip_address, $user_agent);
            $log_stmt->execute();
            
            $conn->commit();
            
            $success_message = "Password reset successfully! New password: <strong>$new_password</strong><br>
                <small>Reset Token: $token (Valid for 24 hours)<br>
                Please share this information securely with the employee.</small>";
                
        } elseif ($action === 'unlock_account') {
            $employee_id = intval($_POST['employee_id'] ?? 0);
            
            if ($employee_id <= 0) {
                throw new Exception('Invalid employee ID');
            }
            
            $stmt = $conn->prepare("
                UPDATE employees 
                SET failed_login_attempts = 0, locked_until = NULL
                WHERE employee_id = ?
            ");
            $stmt->bind_param("i", $employee_id);
            $stmt->execute();
            
            // Log activity
            $log_stmt = $conn->prepare("
                INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, ip_address, user_agent) 
                VALUES (?, ?, 'unlock', 'Account unlocked by admin', ?, ?)
            ");
            
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $log_stmt->bind_param("iiss", $_SESSION['employee_id'], $employee_id, $ip_address, $user_agent);
            $log_stmt->execute();
            
            $success_message = "Account unlocked successfully!";
        }
        
    } catch (Exception $e) {
        if ($conn->in_transaction) {
            $conn->rollback();
        }
        $error_message = "Operation failed: " . $e->getMessage();
    }
}

// Get employees with password status
try {
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status_filter'] ?? '';
    
    $where_conditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $where_conditions[] = "(e.employee_number LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
        $types .= 'ssss';
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "e.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $query = "
        SELECT 
            e.employee_id,
            e.employee_number,
            e.first_name,
            e.last_name,
            e.email,
            e.status,
            e.last_login,
            e.must_change_password,
            e.failed_login_attempts,
            e.locked_until,
            e.password_changed_at,
            e.two_factor_enabled,
            r.role_name,
            (SELECT COUNT(*) FROM password_reset_tokens prt 
             WHERE prt.employee_id = e.employee_id 
             AND prt.expires_at > NOW() 
             AND prt.used_at IS NULL) as active_tokens
        FROM employees e
        LEFT JOIN roles r ON e.role_id = r.role_id
        $where_clause
        ORDER BY e.last_login DESC, e.employee_number ASC
    ";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $employees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $employees = [];
    $error_message = "Failed to load employees: " . $e->getMessage();
}

// Helper function to generate secure passwords
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*()-_=+[]{}|;:,.<>?';
    
    $password = '';
    
    // Ensure at least one character from each required set
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Fill the rest randomly
    $all_chars = $uppercase . $lowercase . $numbers . $symbols;
    for ($i = 4; $i < $length; $i++) {
        $password .= $all_chars[random_int(0, strlen($all_chars) - 1)];
    }
    
    // Shuffle the password
    $password = str_shuffle($password);
    
    return $password;
}

function getPasswordStrengthClass($password_changed_at, $must_change_password) {
    if ($must_change_password) return 'danger';
    if (empty($password_changed_at)) return 'warning';
    
    $days_old = (time() - strtotime($password_changed_at)) / (60 * 60 * 24);
    if ($days_old > 90) return 'danger';
    if ($days_old > 60) return 'warning';
    return 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Manager | CHO Koronadal</title>
    <!-- CSS Files -->
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Password Manager - MATCHING EMPLOYEE LIST TEMPLATE */
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

        .password-section {
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

        .form-control, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 119, 182, 0.1);
            outline: none;
        }

        .password-table-container {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .table-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }

        .table {
            margin: 0;
            border: none;
        }

        .table th {
            background: var(--light);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            color: var(--primary-dark);
            padding: 1rem;
            border: none;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(0, 119, 182, 0.05);
        }

        .password-status {
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
            gap: 0.25rem;
        }

        .security-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .security-high { background-color: var(--success); }
        .security-medium { background-color: var(--warning); }
        .security-low { background-color: var(--danger); }

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

        .btn-outline-warning {
            background: transparent;
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        .btn-outline-warning:hover {
            background: var(--warning);
            color: white;
        }

        .btn-outline-success {
            background: transparent;
            border: 1px solid var(--success);
            color: var(--success);
        }

        .btn-outline-success:hover {
            background: var(--success);
            color: white;
        }

        .btn-outline-info {
            background: transparent;
            border: 1px solid var(--info);
            color: var(--info);
        }

        .btn-outline-info:hover {
            background: var(--info);
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

        .btn-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: inherit;
            cursor: pointer;
            margin-left: auto;
        }

        .requirements-list {
            background: var(--light);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid var(--border);
        }

        .requirements-list ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .requirements-list li {
            margin-bottom: 0.5rem;
            color: var(--secondary);
        }

        .bulk-actions {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeeba;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
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

            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .table-responsive {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                padding: 1rem;
            }
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
                <span>Password Manager</span>
            </div>

            <!-- Page Header -->
            <div class="content-header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-shield-alt"></i>
                        Password Manager
                    </h1>
                    <p class="page-subtitle">Secure password management and authentication control for CHO Koronadal</p>
                </div>

                <!-- Navigation Links -->
                <div style="text-align: center;">
                    <a href="employee_list.php" class="action-btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Employee List
                    </a>
                    <a href="user_activity_logs.php" class="action-btn btn-outline-info" style="margin-left: 1rem;">
                        <i class="fas fa-history"></i> View Security Logs
                    </a>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?= count($employees) ?></div>
                    <div class="stat-label">Total Employees</div>
                </div>
                <div class="stat-card">
                    <?php
                    $locked_count = 0;
                    foreach ($employees as $emp) {
                        $is_locked = !empty($emp['locked_until']) && strtotime($emp['locked_until']) > time();
                        if ($is_locked) $locked_count++;
                    }
                    ?>
                    <i class="fas fa-lock stat-icon"></i>
                    <div class="stat-number"><?= $locked_count ?></div>
                    <div class="stat-label">Locked Accounts</div>
                </div>
                <div class="stat-card">
                    <?php
                    $must_change_count = array_filter($employees, function($emp) {
                        return $emp['must_change_password'] == 1;
                    });
                    ?>
                    <i class="fas fa-exclamation-triangle stat-icon"></i>
                    <div class="stat-number"><?= count($must_change_count) ?></div>
                    <div class="stat-label">Must Change Password</div>
                </div>
                <div class="stat-card">
                    <?php
                    $two_fa_enabled = array_filter($employees, function($emp) {
                        return $emp['two_factor_enabled'] == 1;
                    });
                    ?>
                    <i class="fas fa-mobile-alt stat-icon"></i>
                    <div class="stat-number"><?= count($two_fa_enabled) ?></div>
                    <div class="stat-label">2FA Enabled</div>
                </div>
            </div>
                    
            <!-- Error Messages -->
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
                    
            <!-- Password Requirements -->
            <div class="password-section">
                <h3><i class="fas fa-info-circle"></i> Password Requirements & Security Policy</h3>
                <div class="requirements-list">
                    <ul>
                        <li>Minimum <?= $password_requirements['min_length'] ?> characters, maximum <?= $password_requirements['max_length'] ?></li>
                        <li>Must contain uppercase letters (A-Z)</li>
                        <li>Must contain lowercase letters (a-z)</li>
                        <li>Must contain numbers (0-9)</li>
                        <li>Must contain special characters (!@#$%^&*)</li>
                        <li>Cannot contain common words like 'password', '123456', etc.</li>
                        <li>Passwords expire after 90 days</li>
                    </ul>
                </div>
            </div>
                    
            <!-- Bulk Actions -->
            <div class="bulk-actions">
                <h3><i class="fas fa-users-cog"></i> Bulk Password Operations</h3>
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_reset">
                    <div class="filter-form" style="grid-template-columns: 2fr 1fr;">
                        <div class="form-group">
                            <label class="form-label">Selected employees will receive new secure passwords:</label>
                            <div id="selectedCount" class="text-muted">Select employees below to enable bulk reset</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="action-btn btn-warning" id="bulkResetBtn" disabled>
                                <i class="fas fa-key"></i> Bulk Password Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
                    
            <!-- Search and Filter -->
            <div class="password-section">
                <h3><i class="fas fa-filter"></i> Filter Employee Passwords</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label class="form-label">Search Employees</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               placeholder="Name, email, employee number...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status Filter</label>
                        <select name="status_filter" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" <?= ($status_filter === 'active') ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= ($status_filter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            <option value="on_leave" <?= ($status_filter === 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="action-btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
                    
            <!-- Employees Password Status Table -->
            <div class="password-table-container">
                <div class="table-header">
                    <h3><i class="fas fa-users-cog"></i> Employee Password Status</h3>
                    <span>Total: <?= count($employees) ?> employees</span>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Employee</th>
                                <th>Role</th>
                                <th>Password Status</th>
                                <th>Security Level</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-users"></i>
                                        <h4>No employees found</h4>
                                        <p>No employees match your current filter criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                    <?php
                                    $is_locked = !empty($employee['locked_until']) && strtotime($employee['locked_until']) > time();
                                    $password_age_class = getPasswordStrengthClass($employee['password_changed_at'], $employee['must_change_password']);
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($employee['employee_id'] != $_SESSION['employee_id']): ?>
                                                <input type="checkbox" class="employee-select" 
                                                       name="employee_ids[]" value="<?= $employee['employee_id'] ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                                                <br><small style="color: var(--secondary);"><?= htmlspecialchars($employee['employee_number']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($employee['role_name']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($employee['must_change_password']): ?>
                                                <span class="password-status" style="background: var(--danger);">
                                                    <i class="fas fa-exclamation-triangle"></i> Must Change
                                                </span>
                                            <?php elseif ($is_locked): ?>
                                                <span class="password-status" style="background: var(--warning); color: #000;">
                                                    <i class="fas fa-lock"></i> Locked
                                                </span>
                                            <?php elseif ($employee['failed_login_attempts'] > 0): ?>
                                                <span class="password-status" style="background: var(--warning); color: #000;">
                                                    <i class="fas fa-exclamation-circle"></i> <?= $employee['failed_login_attempts'] ?> Failed Attempts
                                                </span>
                                            <?php else: ?>
                                                <span class="password-status" style="background: var(--success);">
                                                    <i class="fas fa-check"></i> Normal
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($employee['active_tokens'] > 0): ?>
                                                <br><small style="color: var(--info);">
                                                    <i class="fas fa-key"></i> Active Reset Token
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <span class="security-indicator security-<?= $password_age_class === 'success' ? 'high' : ($password_age_class === 'warning' ? 'medium' : 'low') ?>"></span>
                                                <?php
                                                $security_text = $password_age_class === 'success' ? 'High' : ($password_age_class === 'warning' ? 'Medium' : 'Low');
                                                echo $security_text;
                                                ?>
                                            </div>
                                            
                                            <?php if ($employee['two_factor_enabled']): ?>
                                                <small style="color: var(--success);">
                                                    <i class="fas fa-mobile-alt"></i> 2FA Enabled
                                                </small>
                                            <?php else: ?>
                                                <small style="color: var(--secondary);">
                                                    <i class="fas fa-mobile-alt"></i> 2FA Disabled
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <?= $employee['last_login'] ? 
                                                    date('M j, Y g:i A', strtotime($employee['last_login'])) : 
                                                    '<span style="color: var(--secondary);">Never</span>' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($employee['employee_id'] != $_SESSION['employee_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="single_reset">
                                                        <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                        <button type="submit" class="action-btn btn-outline-warning" 
                                                                title="Reset Password" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;"
                                                                onclick="return confirm('Reset password for this employee?')">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if ($is_locked): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="unlock_account">
                                                            <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                            <button type="submit" class="action-btn btn-outline-success" 
                                                                    title="Unlock Account" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
                                                                <i class="fas fa-unlock"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <small style="color: var(--secondary);">Self</small>
                                                <?php endif; ?>
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

            // Handle select all checkbox
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.employee-select');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateBulkActionButton();
                });
            }
            
            // Handle individual checkboxes
            document.querySelectorAll('.employee-select').forEach(function(checkbox) {
                checkbox.addEventListener('change', updateBulkActionButton);
            });
        });
        
        function updateBulkActionButton() {
            const selectedCheckboxes = document.querySelectorAll('.employee-select:checked');
            const bulkResetBtn = document.getElementById('bulkResetBtn');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedCheckboxes.length > 0) {
                bulkResetBtn.disabled = false;
                selectedCount.textContent = `${selectedCheckboxes.length} employee(s) selected for password reset`;
                selectedCount.style.color = 'var(--warning)';
                selectedCount.style.fontWeight = 'bold';
            } else {
                bulkResetBtn.disabled = true;
                selectedCount.textContent = 'Select employees below to enable bulk reset';
                selectedCount.style.color = 'var(--secondary)';
                selectedCount.style.fontWeight = 'normal';
            }
        }
        
        // Handle bulk form submission
        const bulkForm = document.getElementById('bulkForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                const selectedCheckboxes = document.querySelectorAll('.employee-select:checked');
                
                if (selectedCheckboxes.length === 0) {
                    e.preventDefault();
                    // Show error message instead of alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> Please select at least one employee for bulk password reset.
                        <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                    `;
                    document.querySelector('.main-content').insertBefore(alertDiv, document.querySelector('.main-content').firstChild);
                    setTimeout(() => {
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 300);
                    }, 5000);
                    return;
                }
                
                if (!confirm(`Are you sure you want to reset passwords for ${selectedCheckboxes.length} employee(s)?\n\nThis action cannot be undone and will force all selected employees to change their passwords on next login.`)) {
                    e.preventDefault();
                    return;
                }
                
                // Add selected employee IDs to the form
                selectedCheckboxes.forEach(function(checkbox) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'employee_ids[]';
                    input.value = checkbox.value;
                    bulkForm.appendChild(input);
                });
            });
        }
    </script>
</body>
</html>