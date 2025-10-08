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
    <title>Password Manager - User Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    
    <style>
        .main-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 25px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        
        .password-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .password-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 500;
        }
        
        .security-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .security-high { background-color: #28a745; }
        .security-medium { background-color: #ffc107; }
        .security-low { background-color: #dc3545; }
        
        .lock-indicator {
            color: #dc3545;
            font-weight: bold;
        }
        
        .requirements-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .requirements-list ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .bulk-actions {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9em;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>

<body>
    <?php 
    require_once $root_path . '/includes/topbar.php';
    renderTopbar([
        'title' => 'Password Manager',
        'subtitle' => 'Authentication & Security Control',
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
                        <h2><i class="fas fa-shield-alt"></i> Password & Authentication Manager</h2>
                        <p class="mb-0">Secure password management and authentication control for CHO Koronadal</p>
                    </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Password Requirements -->
                    <div class="password-section">
                        <h5><i class="fas fa-info-circle text-primary"></i> Password Requirements</h5>
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
                        <h6><i class="fas fa-users-cog"></i> Bulk Password Operations</h6>
                        <form method="POST" id="bulkForm">
                            <input type="hidden" name="action" value="bulk_reset">
                            <div class="row align-items-end">
                                <div class="col-md-8">
                                    <label class="form-label">Selected employees will receive new secure passwords:</label>
                                    <div id="selectedCount" class="text-muted">Select employees below to enable bulk reset</div>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-warning" id="bulkResetBtn" disabled>
                                        <i class="fas fa-key"></i> Bulk Password Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Search and Filter -->
                    <div class="password-section">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search Employees</label>
                                <input type="text" class="form-control" name="search" 
                                       value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Name, email, employee number...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status Filter</label>
                                <select name="status_filter" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="active" <?= ($status_filter === 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= ($status_filter === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                    <option value="on_leave" <?= ($status_filter === 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Employees Password Status Table -->
                    <div class="password-section">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
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
                                            <td colspan="7" class="text-center py-4">
                                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                                <br>No employees found.
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
                                                        <input type="checkbox" class="form-check-input employee-select" 
                                                               name="employee_ids[]" value="<?= $employee['employee_id'] ?>">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($employee['employee_number']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= htmlspecialchars($employee['role_name']) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($employee['must_change_password']): ?>
                                                        <span class="password-status bg-danger text-white">
                                                            <i class="fas fa-exclamation-triangle"></i> Must Change
                                                        </span>
                                                    <?php elseif ($is_locked): ?>
                                                        <span class="password-status bg-warning text-dark">
                                                            <i class="fas fa-lock"></i> Locked
                                                        </span>
                                                    <?php elseif ($employee['failed_login_attempts'] > 0): ?>
                                                        <span class="password-status bg-warning text-dark">
                                                            <i class="fas fa-exclamation-circle"></i> <?= $employee['failed_login_attempts'] ?> Failed Attempts
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="password-status bg-success text-white">
                                                            <i class="fas fa-check"></i> Normal
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($employee['active_tokens'] > 0): ?>
                                                        <br><small class="text-info">
                                                            <i class="fas fa-key"></i> Active Reset Token
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="security-indicator security-<?= $password_age_class === 'success' ? 'high' : ($password_age_class === 'warning' ? 'medium' : 'low') ?>"></span>
                                                        <?php
                                                        $security_text = $password_age_class === 'success' ? 'High' : ($password_age_class === 'warning' ? 'Medium' : 'Low');
                                                        echo $security_text;
                                                        ?>
                                                    </div>
                                                    
                                                    <?php if ($employee['two_factor_enabled']): ?>
                                                        <small class="text-success">
                                                            <i class="fas fa-mobile-alt"></i> 2FA Enabled
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-mobile-alt"></i> 2FA Disabled
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <?= $employee['last_login'] ? 
                                                            date('M j, Y g:i A', strtotime($employee['last_login'])) : 
                                                            '<span class="text-muted">Never</span>' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="action-buttons d-flex gap-1">
                                                        <?php if ($employee['employee_id'] != $_SESSION['employee_id']): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="single_reset">
                                                                <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                                <button type="submit" class="btn btn-outline-warning btn-sm" 
                                                                        title="Reset Password"
                                                                        onclick="return confirm('Reset password for this employee?')">
                                                                    <i class="fas fa-key"></i>
                                                                </button>
                                                            </form>
                                                            
                                                            <?php if ($is_locked): ?>
                                                                <form method="POST" style="display: inline;">
                                                                    <input type="hidden" name="action" value="unlock_account">
                                                                    <input type="hidden" name="employee_id" value="<?= $employee['employee_id'] ?>">
                                                                    <button type="submit" class="btn btn-outline-success btn-sm" 
                                                                            title="Unlock Account">
                                                                        <i class="fas fa-unlock"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <small class="text-muted">Self</small>
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
                    
                    <!-- Navigation Links -->
                    <div class="text-center">
                        <a href="employee_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Employee List
                        </a>
                        <a href="user_activity_logs.php" class="btn btn-outline-info">
                            <i class="fas fa-history"></i> View Security Logs
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.employee-select');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionButton();
        });
        
        // Handle individual checkboxes
        document.querySelectorAll('.employee-select').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateBulkActionButton);
        });
        
        function updateBulkActionButton() {
            const selectedCheckboxes = document.querySelectorAll('.employee-select:checked');
            const bulkResetBtn = document.getElementById('bulkResetBtn');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedCheckboxes.length > 0) {
                bulkResetBtn.disabled = false;
                selectedCount.textContent = `${selectedCheckboxes.length} employee(s) selected for password reset`;
                selectedCount.className = 'text-warning fw-bold';
            } else {
                bulkResetBtn.disabled = true;
                selectedCount.textContent = 'Select employees below to enable bulk reset';
                selectedCount.className = 'text-muted';
            }
        }
        
        // Handle bulk form submission
        document.getElementById('bulkForm').addEventListener('submit', function(e) {
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
                document.querySelector('.homepage').insertBefore(alertDiv, document.querySelector('.homepage').firstChild);
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
                this.appendChild(input);
            }.bind(this));
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 10000);
    </script>
</body>
</html>