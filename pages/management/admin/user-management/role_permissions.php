<?php
// pages/management/admin/user-management/role_permissions.php
// Role-Based Access Control Management System
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

// Handle form submissions
$success_message = '';
$error_message = '';

// Process permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_permissions':
                $role_id = intval($_POST['role_id']);
                $permissions = $_POST['permissions'] ?? [];
                
                if (updateRolePermissions($role_id, $permissions, $_SESSION['employee_id'])) {
                    $success_message = "Permissions updated successfully for role.";
                } else {
                    $error_message = "Failed to update permissions.";
                }
                break;
                
            case 'create_permission':
                $permission_key = trim($_POST['permission_key']);
                $permission_name = trim($_POST['permission_name']);
                $permission_category = trim($_POST['permission_category']);
                $description = trim($_POST['description']);
                
                if (createPermission($permission_key, $permission_name, $permission_category, $description, $_SESSION['employee_id'])) {
                    $success_message = "New permission created successfully.";
                } else {
                    $error_message = "Failed to create permission.";
                }
                break;
                
            case 'reset_role_permissions':
                $role_id = intval($_POST['role_id']);
                
                if (resetRolePermissions($role_id, $_SESSION['employee_id'])) {
                    $success_message = "Role permissions reset to defaults.";
                } else {
                    $error_message = "Failed to reset role permissions.";
                }
                break;
        }
    }
}

// Define comprehensive permission system
$permission_categories = [
    'user_management' => [
        'name' => 'User Management',
        'permissions' => [
            'create_employee' => 'Create Employee Accounts',
            'edit_employee' => 'Edit Employee Information', 
            'delete_employee' => 'Delete/Deactivate Employees',
            'view_employee' => 'View Employee Details',
            'manage_passwords' => 'Reset/Manage Passwords',
            'assign_roles' => 'Assign/Change Employee Roles',
            'view_activity_logs' => 'View User Activity Logs'
        ]
    ],
    'patient_management' => [
        'name' => 'Patient Management',
        'permissions' => [
            'create_patient' => 'Register New Patients',
            'edit_patient' => 'Edit Patient Information',
            'view_patient' => 'View Patient Records',
            'delete_patient' => 'Delete Patient Records',
            'manage_medical_history' => 'Manage Medical History',
            'view_patient_reports' => 'View Patient Reports'
        ]
    ],
    'appointment_management' => [
        'name' => 'Appointment Management',
        'permissions' => [
            'create_appointment' => 'Schedule Appointments',
            'edit_appointment' => 'Modify Appointments',
            'cancel_appointment' => 'Cancel Appointments',
            'view_appointments' => 'View All Appointments',
            'approve_appointments' => 'Approve/Reject Appointments',
            'manage_schedules' => 'Manage Doctor Schedules'
        ]
    ],
    'queue_management' => [
        'name' => 'Queue Management',
        'permissions' => [
            'manage_queue' => 'Manage Patient Queues',
            'call_next_patient' => 'Call Next Patient',
            'override_queue' => 'Override Queue Order',
            'view_queue_logs' => 'View Queue Activity Logs',
            'manage_stations' => 'Assign/Manage Stations'
        ]
    ],
    'clinical_operations' => [
        'name' => 'Clinical Operations',
        'permissions' => [
            'conduct_consultation' => 'Conduct Medical Consultations',
            'prescribe_medication' => 'Prescribe Medications',
            'order_lab_tests' => 'Order Laboratory Tests',
            'view_lab_results' => 'View Laboratory Results',
            'upload_lab_results' => 'Upload Laboratory Results',
            'dispense_medication' => 'Dispense Medications'
        ]
    ],
    'financial_operations' => [
        'name' => 'Financial Operations',
        'permissions' => [
            'process_billing' => 'Process Patient Billing',
            'handle_payments' => 'Handle Payment Transactions',
            'issue_receipts' => 'Issue Payment Receipts',
            'view_financial_reports' => 'View Financial Reports',
            'manage_billing_rates' => 'Manage Service Rates'
        ]
    ],
    'administrative' => [
        'name' => 'Administrative',
        'permissions' => [
            'generate_reports' => 'Generate System Reports',
            'manage_facilities' => 'Manage Facility Information',
            'system_configuration' => 'System Configuration',
            'backup_restore' => 'Backup & Restore Data',
            'view_system_logs' => 'View System Logs'
        ]
    ],
    'referral_system' => [
        'name' => 'Referral System',
        'permissions' => [
            'create_referral' => 'Create Patient Referrals',
            'approve_referral' => 'Approve Referrals',
            'view_referrals' => 'View Referral Records',
            'manage_referral_network' => 'Manage Referral Network'
        ]
    ]
];

// Default role permission mappings
$default_role_permissions = [
    'admin' => ['all'], // Admin gets all permissions
    'doctor' => [
        'view_employee', 'view_patient', 'edit_patient', 'manage_medical_history',
        'view_appointments', 'edit_appointment', 'manage_queue', 'call_next_patient', 'override_queue',
        'conduct_consultation', 'prescribe_medication', 'order_lab_tests', 'view_lab_results',
        'create_referral', 'view_referrals', 'generate_reports'
    ],
    'nurse' => [
        'view_employee', 'view_patient', 'edit_patient', 'manage_medical_history',
        'view_appointments', 'manage_queue', 'call_next_patient', 'override_queue',
        'view_lab_results', 'view_referrals'
    ],
    'laboratory_tech' => [
        'view_patient', 'view_appointments', 'manage_queue', 'call_next_patient',
        'view_lab_results', 'upload_lab_results'
    ],
    'pharmacist' => [
        'view_patient', 'view_appointments', 'manage_queue', 'call_next_patient',
        'dispense_medication'
    ],
    'cashier' => [
        'view_patient', 'process_billing', 'handle_payments', 'issue_receipts',
        'view_financial_reports'
    ],
    'records_officer' => [
        'view_patient', 'edit_patient', 'view_appointments', 'generate_reports'
    ],
    'dho' => [
        'view_patient', 'view_appointments', 'view_referrals', 'approve_referral',
        'generate_reports', 'manage_referral_network'
    ],
    'bhw' => [
        'view_patient', 'create_referral', 'view_referrals'
    ]
];

function createPermission($key, $name, $category, $description, $admin_id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Check if permission already exists
        $check_stmt = $conn->prepare("SELECT permission_id FROM permissions WHERE permission_key = ?");
        $check_stmt->bind_param('s', $key);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Permission key already exists.");
        }
        
        // Insert new permission
        $insert_stmt = $conn->prepare("
            INSERT INTO permissions (permission_key, permission_name, category, description, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param('ssss', $key, $name, $category, $description);
        $insert_stmt->execute();
        
        // Log the action
        logUserActivity($admin_id, null, 'permission_create', 
            "Created new permission: $name ($key)", null, 
            json_encode(['permission_key' => $key, 'permission_name' => $name, 'category' => $category]));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function updateRolePermissions($role_id, $permissions, $admin_id) {
    global $conn;
    
    try {
        $conn->begin_transaction();
        
        // Get role name for logging
        $role_stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
        $role_stmt->bind_param('i', $role_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        
        if ($role_result->num_rows === 0) {
            throw new Exception("Role not found.");
        }
        
        $role_name = $role_result->fetch_assoc()['role_name'];
        
        // Get current permissions for logging
        $current_perms_stmt = $conn->prepare("
            SELECT p.permission_key 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.permission_id 
            WHERE rp.role_id = ?
        ");
        $current_perms_stmt->bind_param('i', $role_id);
        $current_perms_stmt->execute();
        $current_permissions = array_column($current_perms_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_key');
        
        // Delete existing permissions for this role
        $delete_stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $delete_stmt->bind_param('i', $role_id);
        $delete_stmt->execute();
        
        // Insert new permissions
        if (!empty($permissions)) {
            $insert_stmt = $conn->prepare("
                INSERT INTO role_permissions (role_id, permission_id) 
                SELECT ?, permission_id FROM permissions WHERE permission_key = ?
            ");
            
            foreach ($permissions as $permission_key) {
                $insert_stmt->bind_param('is', $role_id, $permission_key);
                $insert_stmt->execute();
            }
        }
        
        // Log the action
        logUserActivity($admin_id, null, 'role_permissions_update', 
            "Updated permissions for role: $role_name", 
            json_encode($current_permissions), 
            json_encode($permissions));
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function resetRolePermissions($role_id, $admin_id) {
    global $conn, $default_role_permissions;
    
    try {
        $conn->begin_transaction();
        
        // Get role name
        $role_stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
        $role_stmt->bind_param('i', $role_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        
        if ($role_result->num_rows === 0) {
            throw new Exception("Role not found.");
        }
        
        $role_name = $role_result->fetch_assoc()['role_name'];
        
        // Get default permissions for this role
        $default_perms = $default_role_permissions[$role_name] ?? [];
        
        // Update permissions using the existing function
        return updateRolePermissions($role_id, $default_perms, $admin_id);
        
    } catch (Exception $e) {
        $conn->rollback();
        return false;
    }
}

function logUserActivity($admin_id, $employee_id, $action_type, $description, $old_values = null, $new_values = null) {
    global $conn;
    
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt = $conn->prepare("
            INSERT INTO user_activity_logs (admin_id, employee_id, action_type, description, old_values, new_values, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param('iisssss', $admin_id, $employee_id, $action_type, $description, $old_values, $new_values, $ip_address);
        $stmt->execute();
        
    } catch (Exception $e) {
        // Log error but don't fail the main operation
        error_log("Failed to log user activity: " . $e->getMessage());
    }
}

// Get all roles
try {
    $roles_stmt = $conn->prepare("SELECT * FROM roles ORDER BY role_name");
    $roles_stmt->execute();
    $roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// Get all permissions
try {
    $permissions_stmt = $conn->prepare("SELECT * FROM permissions ORDER BY category, permission_name");
    $permissions_stmt->execute();
    $all_permissions = $permissions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $all_permissions = [];
}

// Get role permissions
$role_permissions = [];
foreach ($roles as $role) {
    try {
        $rp_stmt = $conn->prepare("
            SELECT p.permission_key 
            FROM role_permissions rp 
            JOIN permissions p ON rp.permission_id = p.permission_id 
            WHERE rp.role_id = ?
        ");
        $rp_stmt->bind_param('i', $role['role_id']);
        $rp_stmt->execute();
        $role_permissions[$role['role_id']] = array_column($rp_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_key');
    } catch (Exception $e) {
        $role_permissions[$role['role_id']] = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role-Based Access Control - User Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../../../assets/css/sidebar.css">
    
    <style>
        .main-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 25px 0;
            margin-bottom: 30px;
            border-radius: 10px;
        }
        
        .permissions-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .role-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .role-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .role-card-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .role-card-body {
            padding: 20px;
        }
        
        .permission-category {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .permission-item:last-child {
            border-bottom: none;
        }
        
        .permission-checkbox {
            margin-right: 10px;
            transform: scale(1.2);
        }
        
        .permission-label {
            flex-grow: 1;
            font-weight: 500;
        }
        
        .permission-key {
            font-family: 'Courier New', monospace;
            font-size: 0.8em;
            color: #6c757d;
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .stats-row {
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            border: 1px solid #dee2e6;
            height: 100%;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #28a745;
        }
        
        .role-badge {
            font-size: 0.9em;
            padding: 5px 10px;
        }
        
        .permission-coverage {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 10px;
            margin-top: 10px;
            border-radius: 0 5px 5px 0;
        }
        
        @media (max-width: 768px) {
            .role-card {
                margin-bottom: 15px;
            }
            
            .permission-category {
                padding: 10px;
            }
            
            .permission-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .permission-checkbox {
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>
    <?php 
    require_once $root_path . '/includes/topbar.php';
    renderTopbar([
        'title' => 'Role-Based Access Control',
        'subtitle' => 'Manage System Permissions',
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
                        <h2><i class="fas fa-shield-alt"></i> Role-Based Access Control</h2>
                        <p class="mb-0">Configure system permissions and role-based security for CHO Koronadal</p>
                    </div>
                    
                    <!-- Statistics -->
                    <div class="stats-row">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number"><?= count($roles) ?></div>
                                    <div class="text-muted">System Roles</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number text-primary"><?= count($all_permissions) ?></div>
                                    <div class="text-muted">Total Permissions</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <div class="stat-number text-info"><?= count(array_unique(array_column($all_permissions, 'category'))) ?></div>
                                    <div class="text-muted">Categories</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-card">
                                    <?php 
                                    $total_assigned = 0;
                                    foreach ($role_permissions as $perms) {
                                        $total_assigned += count($perms);
                                    }
                                    ?>
                                    <div class="stat-number text-success"><?= $total_assigned ?></div>
                                    <div class="text-muted">Assigned Permissions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Role Permissions Management -->
                    <div class="permissions-section">
                        <h5><i class="fas fa-users-cog"></i> Role Permission Matrix</h5>
                        
                        <?php foreach ($roles as $role): ?>
                            <div class="role-card">
                                <div class="role-card-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <i class="fas fa-user-tag"></i> 
                                                <?= htmlspecialchars(ucwords($role['role_name'])) ?>
                                                <span class="badge role-badge bg-primary">
                                                    <?= count($role_permissions[$role['role_id']]) ?> permissions
                                                </span>
                                            </h6>
                                            <small class="text-muted"><?= htmlspecialchars($role['description'] ?? 'No description available') ?></small>
                                        </div>
                                        <div>
                                            <button type="button" class="btn btn-outline-warning btn-sm" 
                                                    onclick="resetRolePermissions(<?= $role['role_id'] ?>)">
                                                <i class="fas fa-undo"></i> Reset to Default
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <?php 
                                    $total_perms = count($all_permissions);
                                    $assigned_perms = count($role_permissions[$role['role_id']]);
                                    $coverage_percent = $total_perms > 0 ? round(($assigned_perms / $total_perms) * 100) : 0;
                                    ?>
                                    <div class="permission-coverage">
                                        <small>
                                            <strong>Permission Coverage:</strong> <?= $coverage_percent ?>% 
                                            (<?= $assigned_perms ?> of <?= $total_perms ?> permissions assigned)
                                        </small>
                                        <div class="progress mt-2" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $coverage_percent ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="role-card-body">
                                    <form method="POST" class="role-permissions-form">
                                        <input type="hidden" name="action" value="update_permissions">
                                        <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                        
                                        <?php foreach ($permission_categories as $category_key => $category_info): ?>
                                            <div class="permission-category">
                                                <h6 class="mb-3">
                                                    <i class="fas fa-folder"></i> <?= htmlspecialchars($category_info['name']) ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary float-end toggle-category" 
                                                            data-role="<?= $role['role_id'] ?>" data-category="<?= $category_key ?>">
                                                        <i class="fas fa-check-square"></i> Toggle All
                                                    </button>
                                                </h6>
                                                
                                                <?php foreach ($category_info['permissions'] as $perm_key => $perm_name): ?>
                                                    <div class="permission-item">
                                                        <input type="checkbox" 
                                                               class="form-check-input permission-checkbox" 
                                                               name="permissions[]" 
                                                               value="<?= htmlspecialchars($perm_key) ?>"
                                                               id="perm_<?= $role['role_id'] ?>_<?= $perm_key ?>"
                                                               data-category="<?= $category_key ?>"
                                                               data-role="<?= $role['role_id'] ?>"
                                                               <?= in_array($perm_key, $role_permissions[$role['role_id']]) ? 'checked' : '' ?>>
                                                        <label class="permission-label" for="perm_<?= $role['role_id'] ?>_<?= $perm_key ?>">
                                                            <?= htmlspecialchars($perm_name) ?>
                                                        </label>
                                                        <span class="permission-key"><?= htmlspecialchars($perm_key) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="text-center mt-3">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save"></i> Update Permissions
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Create New Permission -->
                    <div class="permissions-section">
                        <h5><i class="fas fa-plus-circle"></i> Create New Permission</h5>
                        
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create_permission">
                            
                            <div class="col-md-3">
                                <label class="form-label">Permission Key</label>
                                <input type="text" class="form-control" name="permission_key" 
                                       placeholder="e.g., view_reports" required>
                                <small class="text-muted">Unique identifier (lowercase, underscores)</small>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Permission Name</label>
                                <input type="text" class="form-control" name="permission_name" 
                                       placeholder="e.g., View Reports" required>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Category</label>
                                <select name="permission_category" class="form-select" required>
                                    <?php foreach ($permission_categories as $cat_key => $cat_info): ?>
                                        <option value="<?= $cat_key ?>"><?= htmlspecialchars($cat_info['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" 
                                       placeholder="Brief description">
                            </div>
                            
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i> Create
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Navigation Links -->
                    <div class="text-center">
                        <a href="employee_list.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Back to Employee List
                        </a>
                        <a href="user_activity_logs.php" class="btn btn-outline-info">
                            <i class="fas fa-history"></i> Activity Logs
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning"></i> Reset Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reset this role's permissions to default values? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="reset_role_permissions">
                        <input type="hidden" name="role_id" id="reset_role_id">
                        <button type="submit" class="btn btn-warning">Reset Permissions</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function resetRolePermissions(roleId) {
            document.getElementById('reset_role_id').value = roleId;
            new bootstrap.Modal(document.getElementById('resetModal')).show();
        }
        
        // Toggle all permissions in a category
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('toggle-category') || e.target.closest('.toggle-category')) {
                const button = e.target.closest('.toggle-category');
                const roleId = button.dataset.role;
                const category = button.dataset.category;
                
                const checkboxes = document.querySelectorAll(
                    `input[data-role="${roleId}"][data-category="${category}"]`
                );
                
                // Determine if we should check or uncheck all
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(checkbox => {
                    checkbox.checked = !allChecked;
                });
                
                // Update button text
                const icon = button.querySelector('i');
                if (!allChecked) {
                    icon.className = 'fas fa-square';
                } else {
                    icon.className = 'fas fa-check-square';
                }
            }
        });
        
        // Auto-submit forms when checkboxes change
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('permission-checkbox')) {
                // Optional: Add auto-save functionality
                // e.target.closest('form').submit();
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 5000);
        
        // Form validation
        document.addEventListener('submit', function(e) {
            if (e.target.querySelector('input[name="permission_key"]')) {
                const keyInput = e.target.querySelector('input[name="permission_key"]');
                const keyValue = keyInput.value;
                
                // Validate permission key format
                if (!/^[a-z_]+$/.test(keyValue)) {
                    e.preventDefault();
                    // Show error message instead of alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> Permission key must contain only lowercase letters and underscores.
                        <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                    `;
                    document.querySelector('.homepage').insertBefore(alertDiv, document.querySelector('.homepage').firstChild);
                    setTimeout(() => {
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 300);
                    }, 5000);
                    keyInput.focus();
                    return false;
                }
            }
        });
    </script>
</body>
</html>