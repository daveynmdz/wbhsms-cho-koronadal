<?php
// pages/management/admin/user-management/edit_employee.php
// Edit Employee with validation and audit logging
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

// Get employee ID from URL
$employee_id = intval($_GET['id'] ?? 0);
if ($employee_id <= 0) {
    header('Location: employee_list.php?error=invalid_id');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$validation_errors = [];
$employee = null;

// Fetch employee data
try {
    $stmt = $conn->prepare("
        SELECT e.*, r.role_name, f.name as facility_name 
        FROM employees e 
        LEFT JOIN roles r ON e.role_id = r.role_id 
        LEFT JOIN facilities f ON e.facility_id = f.facility_id 
        WHERE e.employee_id = ?
    ");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: employee_list.php?error=employee_not_found');
        exit();
    }
    
    $employee = $result->fetch_assoc();
} catch (Exception $e) {
    header('Location: employee_list.php?error=fetch_failed');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input data
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact_num = trim($_POST['contact_num'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 0);
        $facility_id = intval($_POST['facility_id'] ?? 0);
        $license_number = trim($_POST['license_number'] ?? '');
        $birth_date = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $status = $_POST['status'] ?? '';
        
        // Validation
        if (empty($first_name)) $validation_errors[] = 'First name is required';
        if (empty($last_name)) $validation_errors[] = 'Last name is required';
        if (empty($email)) $validation_errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $validation_errors[] = 'Invalid email format';
        if (empty($contact_num)) $validation_errors[] = 'Contact number is required';
        if ($role_id <= 0) $validation_errors[] = 'Valid role is required';
        if ($facility_id <= 0) $validation_errors[] = 'Valid facility is required';
        if (empty($birth_date)) $validation_errors[] = 'Birth date is required';
        if (empty($gender)) $validation_errors[] = 'Gender is required';
        if (empty($status)) $validation_errors[] = 'Status is required';
        
        // Validate contact number format
        if (!preg_match('/^09\d{9}$/', $contact_num)) {
            $validation_errors[] = 'Contact number must be a valid Philippine mobile number (09XXXXXXXXX)';
        }
        
        // Validate birth date
        $birth_datetime = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$birth_datetime) {
            $validation_errors[] = 'Invalid birth date format';
        } else {
            $today = new DateTime();
            $age = $today->diff($birth_datetime)->y;
            if ($age < 18 || $age > 100) {
                $validation_errors[] = 'Employee must be between 18 and 100 years old';
            }
        }
        
        // Prevent admin from deactivating themselves
        if ($employee_id == $_SESSION['employee_id'] && $status === 'inactive') {
            $validation_errors[] = 'You cannot deactivate your own account';
        }
        
        if (empty($validation_errors)) {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Store old values for logging
                $old_values = [
                    'first_name' => $employee['first_name'],
                    'middle_name' => $employee['middle_name'],
                    'last_name' => $employee['last_name'],
                    'email' => $employee['email'],
                    'contact_num' => $employee['contact_num'],
                    'role_id' => $employee['role_id'],
                    'facility_id' => $employee['facility_id'],
                    'license_number' => $employee['license_number'],
                    'birth_date' => $employee['birth_date'],
                    'gender' => $employee['gender'],
                    'status' => $employee['status']
                ];
                
                // Update employee
                $update_stmt = $conn->prepare("
                    UPDATE employees SET 
                        first_name = ?, middle_name = ?, last_name = ?, email = ?, 
                        contact_num = ?, role_id = ?, facility_id = ?, license_number = ?, 
                        birth_date = ?, gender = ?, status = ?, updated_at = NOW()
                    WHERE employee_id = ?
                ");
                
                $update_stmt->bind_param("sssssiissssi", 
                    $first_name, $middle_name, $last_name, $email,
                    $contact_num, $role_id, $facility_id, $license_number,
                    $birth_date, $gender, $status, $employee_id
                );
                
                if (!$update_stmt->execute()) {
                    throw new Exception("Failed to update employee record");
                }
                
                // Log the update activity
                $new_values = [
                    'first_name' => $first_name,
                    'middle_name' => $middle_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'contact_num' => $contact_num,
                    'role_id' => $role_id,
                    'facility_id' => $facility_id,
                    'license_number' => $license_number,
                    'birth_date' => $birth_date,
                    'gender' => $gender,
                    'status' => $status
                ];
                
                // Identify what changed
                $changes = [];
                foreach ($new_values as $field => $new_value) {
                    if ($old_values[$field] != $new_value) {
                        $changes[] = "$field: '{$old_values[$field]}' → '$new_value'";
                    }
                }
                
                if (!empty($changes)) {
                    $log_stmt = $conn->prepare("
                        INSERT INTO user_activity_logs (
                            admin_id, employee_id, action_type, description, 
                            old_values, new_values, ip_address, user_agent
                        ) VALUES (?, ?, 'update', ?, ?, ?, ?, ?)
                    ");
                    
                    $description = "Updated employee: $first_name $last_name (" . implode(', ', $changes) . ")";
                    $old_values_json = json_encode($old_values);
                    $new_values_json = json_encode($new_values);
                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    
                    $log_stmt->bind_param("iissssss", 
                        $_SESSION['employee_id'], $employee_id, $description, 
                        $old_values_json, $new_values_json, $ip_address, $user_agent
                    );
                    $log_stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Employee updated successfully!";
                
                // Refresh employee data
                $stmt = $conn->prepare("
                    SELECT e.*, r.role_name, f.name as facility_name 
                    FROM employees e 
                    LEFT JOIN roles r ON e.role_id = r.role_id 
                    LEFT JOIN facilities f ON e.facility_id = f.facility_id 
                    WHERE e.employee_id = ?
                ");
                $stmt->bind_param("i", $employee_id);
                $stmt->execute();
                $employee = $stmt->get_result()->fetch_assoc();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to update employee: " . $e->getMessage();
            }
        }
        
    } catch (Exception $e) {
        $error_message = "System error: " . $e->getMessage();
    }
}

// Fetch roles for dropdown
try {
    $roles_stmt = $conn->prepare("SELECT role_id, role_name, description FROM roles ORDER BY role_name");
    $roles_stmt->execute();
    $roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// Fetch facilities for dropdown  
try {
    $facilities_stmt = $conn->prepare("SELECT facility_id, name, type FROM facilities WHERE status = 'active' ORDER BY name");
    $facilities_stmt->execute();
    $facilities = $facilities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $facilities = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - User Management</title>
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../../../assets/css/topbar.css">
    <link rel="stylesheet" href="../../../../assets/css/profile-edit.css">
    <link rel="stylesheet" href="../../../../assets/css/edit.css">
    
    <style>
        .form-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-section h4 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-weight: 600;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .employee-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .validation-error {
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <?php 
    require_once $root_path . '/includes/topbar.php';
    renderTopbar([
        'title' => 'Edit Employee',
        'subtitle' => 'User Management System',
        'back_url' => 'employee_list.php',
        'user_type' => 'employee'
    ]); 
    ?>

    <section class="homepage">
                    
                    <!-- Employee Info Header -->
                    <div class="employee-info">
                        <div class="row">
                            <div class="col-md-8">
                                <h3><i class="fas fa-user-edit"></i> Edit Employee</h3>
                                <p class="mb-0">
                                    <strong><?= htmlspecialchars($employee['employee_number']) ?></strong> - 
                                    <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?>
                                </p>
                                <small class="text-muted">
                                    Current Role: <?= htmlspecialchars($employee['role_name']) ?> | 
                                    Facility: <?= htmlspecialchars($employee['facility_name']) ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge bg-<?= $employee['status'] === 'active' ? 'success' : 'warning' ?> p-2">
                                    <?= ucfirst($employee['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
        <!-- Success Message -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Validation Errors -->
        <?php if (!empty($validation_errors)): ?>
            <div class="alert alert-danger">
                <strong><i class="fas fa-exclamation-triangle"></i> Please correct the following errors:</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <?php foreach ($validation_errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="profile-wrapper">
                    
                    <form method="POST" novalidate>
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h4><i class="fas fa-user"></i> Personal Information</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="first_name">
                                        First Name <span class="required">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($employee['first_name']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="middle_name">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                           value="<?= htmlspecialchars($employee['middle_name'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="last_name">
                                        Last Name <span class="required">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($employee['last_name']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="birth_date">
                                        Birth Date <span class="required">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" 
                                           value="<?= htmlspecialchars($employee['birth_date']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="gender">
                                        Gender <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="male" <?= ($employee['gender'] === 'male') ? 'selected' : '' ?>>Male</option>
                                        <option value="female" <?= ($employee['gender'] === 'female') ? 'selected' : '' ?>>Female</option>
                                        <option value="other" <?= ($employee['gender'] === 'other') ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="license_number">License Number</label>
                                    <input type="text" class="form-control" id="license_number" name="license_number" 
                                           value="<?= htmlspecialchars($employee['license_number'] ?? '') ?>"
                                           placeholder="Professional license number (if applicable)">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <h4><i class="fas fa-envelope"></i> Contact Information</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="email">
                                        Email Address <span class="required">*</span>
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($employee['email']) ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="contact_num">
                                        Contact Number <span class="required">*</span>
                                    </label>
                                    <input type="tel" class="form-control" id="contact_num" name="contact_num" 
                                           value="<?= htmlspecialchars($employee['contact_num'] ?? '') ?>" 
                                           placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Employment Information Section -->
                        <div class="form-section">
                            <h4><i class="fas fa-briefcase"></i> Employment Information</h4>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="role_id">
                                        Role <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="role_id" name="role_id" required>
                                        <option value="">Select Role</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role['role_id'] ?>" 
                                                    <?= ($employee['role_id'] == $role['role_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($role['role_name']) ?> 
                                                - <?= htmlspecialchars($role['description']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="facility_id">
                                        Department/Facility <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="facility_id" name="facility_id" required>
                                        <option value="">Select Facility</option>
                                        <?php foreach ($facilities as $facility): ?>
                                            <option value="<?= $facility['facility_id'] ?>" 
                                                    <?= ($employee['facility_id'] == $facility['facility_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($facility['name']) ?> 
                                                (<?= htmlspecialchars($facility['type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="status">
                                        Status <span class="required">*</span>
                                    </label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">Select Status</option>
                                        <option value="active" <?= ($employee['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= ($employee['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                        <option value="on_leave" <?= ($employee['status'] === 'on_leave') ? 'selected' : '' ?>>On Leave</option>
                                        <option value="retired" <?= ($employee['status'] === 'retired') ? 'selected' : '' ?>>Retired</option>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if ($employee_id == $_SESSION['employee_id']): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Note:</strong> You are editing your own account. You cannot deactivate yourself.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="form-section">
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Employee
                                </button>
                                
                                <a href="employee_list.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                                
                                <a href="user_activity_logs.php?employee_id=<?= $employee_id ?>" class="btn btn-outline-info btn-lg">
                                    <i class="fas fa-history"></i> View Activity Log
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>


    
    <script>
        // Auto-format contact number
        document.getElementById('contact_num').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.substring(0, 11);
            if (value.length >= 2 && !value.startsWith('09')) {
                value = '09' + value.substring(2);
            }
            e.target.value = value;
        });
        
        // Prevent self-deactivation
        document.getElementById('status').addEventListener('change', function(e) {
            <?php if ($employee_id == $_SESSION['employee_id']): ?>
                if (e.target.value === 'inactive') {
                    // Show error message instead of alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> You cannot deactivate your own account!
                        <button type="button" class="btn-close" onclick="this.parentElement.remove();">&times;</button>
                    `;
                    document.querySelector('.profile-wrapper').insertBefore(alertDiv, document.querySelector('.profile-wrapper').firstChild);
                    e.target.value = 'active';
                    setTimeout(() => {
                        alertDiv.style.opacity = '0';
                        setTimeout(() => alertDiv.remove(), 300);
                    }, 5000);
                }
            <?php endif; ?>
        });
        
        // Auto-dismiss alerts after 8 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 8000);
    </script>
</body>
</html>