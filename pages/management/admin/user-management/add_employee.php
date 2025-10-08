<?php
// pages/management/admin/user-management/add_employee.php
// Employee Registration System with auto-generated employee numbers
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
        
        // Validate contact number format (Philippine mobile number)
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
        
        if (empty($validation_errors)) {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Generate unique employee number
                $stmt = $conn->prepare("SELECT employee_number FROM employees ORDER BY employee_id DESC LIMIT 1");
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    // Extract number from last employee number (e.g., EMP00092 -> 92)
                    $last_num = intval(substr($row['employee_number'], 3));
                    $new_num = $last_num + 1;
                } else {
                    $new_num = 1;
                }
                
                $employee_number = 'EMP' . str_pad($new_num, 5, '0', STR_PAD_LEFT);
                
                // Check if employee number already exists (safety check)
                $check_stmt = $conn->prepare("SELECT employee_id FROM employees WHERE employee_number = ?");
                $check_stmt->bind_param("s", $employee_number);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception("Employee number collision detected. Please try again.");
                }
                
                // Generate secure default password
                $default_password = 'CHO' . date('Y') . '@' . str_pad(random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
                
                // Insert new employee
                $insert_stmt = $conn->prepare("
                    INSERT INTO employees (
                        employee_number, first_name, middle_name, last_name, email, 
                        contact_num, role_id, facility_id, password, status, 
                        license_number, birth_date, gender, must_change_password,
                        password_changed_at, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, 1, NOW(), NOW())
                ");
                
                $insert_stmt->bind_param("sssssiissss", 
                    $employee_number, $first_name, $middle_name, $last_name, $email,
                    $contact_num, $role_id, $facility_id, $hashed_password,
                    $license_number, $birth_date, $gender
                );
                
                if (!$insert_stmt->execute()) {
                    throw new Exception("Failed to create employee record");
                }
                
                $new_employee_id = $conn->insert_id;
                
                // Log the creation activity
                $log_stmt = $conn->prepare("
                    INSERT INTO user_activity_logs (
                        admin_id, employee_id, action_type, description, 
                        new_values, ip_address, user_agent
                    ) VALUES (?, ?, 'create', ?, ?, ?, ?)
                ");
                
                $description = "Created new employee: $first_name $last_name ($employee_number)";
                $new_values = json_encode([
                    'employee_number' => $employee_number,
                    'name' => "$first_name $middle_name $last_name",
                    'email' => $email,
                    'role_id' => $role_id,
                    'facility_id' => $facility_id,
                    'status' => 'active'
                ]);
                
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
                
                $log_stmt->bind_param("iissss", 
                    $_SESSION['employee_id'], $new_employee_id, $description, 
                    $new_values, $ip_address, $user_agent
                );
                $log_stmt->execute();
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Employee created successfully!<br><strong>Employee Number:</strong> $employee_number<br><strong>Default Password:</strong> $default_password<br><small class='text-warning'>Please share this password securely with the employee. They will be required to change it on first login.</small>";
                
                // Reset form
                $_POST = [];
                
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to create employee: " . $e->getMessage();
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
    $error_message = "Failed to load roles: " . $e->getMessage();
}

// Fetch facilities for dropdown  
try {
    $facilities_stmt = $conn->prepare("SELECT facility_id, name, type FROM facilities WHERE status = 'active' ORDER BY name");
    $facilities_stmt->execute();
    $facilities = $facilities_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    $facilities = [];
    $error_message = "Failed to load facilities: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - User Management</title>
    
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
        
        .form-group.half {
            flex: 0.5;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .password-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
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
        'title' => 'Add New Employee',
        'subtitle' => 'User Management System',
        'back_url' => 'employee_list.php',
        'user_type' => 'employee'
    ]); 
    ?>

    <section class="homepage">
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
            <form method="POST" novalidate class="profile-form">
                <!-- Personal Information Section -->
                <div class="section">
                    <h3 class="section-title"><i class="fas fa-user"></i> Personal Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" 
                                   value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date">Birth Date <span class="required">*</span></label>
                            <input type="date" id="birth_date" name="birth_date" 
                                   value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender <span class="required">*</span></label>
                            <select id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= (($_POST['gender'] ?? '') === 'other') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="license_number">License Number</label>
                            <input type="text" id="license_number" name="license_number" 
                                   value="<?= htmlspecialchars($_POST['license_number'] ?? '') ?>"
                                   placeholder="Professional license number (if applicable)">
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div class="section">
                    <h3 class="section-title"><i class="fas fa-envelope"></i> Contact Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <small class="form-note">
                                <i class="fas fa-info-circle"></i> Note: Duplicate emails are allowed for prototyping purposes
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_num">Contact Number <span class="required">*</span></label>
                            <input type="tel" id="contact_num" name="contact_num" 
                                   value="<?= htmlspecialchars($_POST['contact_num'] ?? '') ?>" 
                                   placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required>
                            <small class="form-note">Philippine mobile number format (11 digits)</small>
                        </div>
                    </div>
                </div>
                
                <!-- Employment Information Section -->
                <div class="section">
                    <h3 class="section-title"><i class="fas fa-briefcase"></i> Employment Information</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role_id">Role <span class="required">*</span></label>
                            <select id="role_id" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>" 
                                            <?= (($_POST['role_id'] ?? '') == $role['role_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($role['role_name']) ?> 
                                        - <?= htmlspecialchars($role['description']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="facility_id">Department/Facility <span class="required">*</span></label>
                            <select id="facility_id" name="facility_id" required>
                                <option value="">Select Facility</option>
                                <?php foreach ($facilities as $facility): ?>
                                    <option value="<?= $facility['facility_id'] ?>" 
                                            <?= (($_POST['facility_id'] ?? '') == $facility['facility_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($facility['name']) ?> 
                                        (<?= htmlspecialchars($facility['type']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="info-note">
                        <i class="fas fa-info-circle"></i>
                        <strong>Security Note:</strong> A secure default password will be automatically generated. 
                        The employee will be required to change it on first login for security purposes.
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Create Employee
                            </button>
                            
                            <a href="employee_list.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            
                            <button type="reset" class="btn btn-outline">
                                <i class="fas fa-undo"></i> Reset Form
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
    
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
        
        // Auto-dismiss alerts after 10 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 10000);
    </script>
</body>
</html>