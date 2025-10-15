<?php
// admin_settings.php - Admin Settings Page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Include employee session configuration
$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/session/employee_session.php';

// Authentication check
if (!isset($_SESSION['employee_id']) || empty($_SESSION['employee_id'])) {
    header('Location: ../management/auth/employee_login.php');
    exit();
}

$employee_id = $_SESSION['employee_id'];
$employee_role = $_SESSION['role'] ?? '';

require_once $root_path . '/config/db.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                // Handle profile update
                $first_name = trim($_POST['first_name'] ?? '');
                $middle_name = trim($_POST['middle_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                
                if ($first_name && $last_name && $email) {
                    try {
                        $stmt = $conn->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, email = ?, phone = ? WHERE employee_id = ?");
                        $stmt->bind_param("sssssi", $first_name, $middle_name, $last_name, $email, $phone, $employee_id);
                        if ($stmt->execute()) {
                            $message = "Profile updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Failed to update profile.";
                            $message_type = "error";
                        }
                        $stmt->close();
                    } catch (mysqli_sql_exception $e) {
                        $message = "Database error: " . $e->getMessage();
                        $message_type = "error";
                    }
                } else {
                    $message = "Please fill in all required fields.";
                    $message_type = "error";
                }
                break;
                
            case 'change_password':
                // Handle password change
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                if ($current_password && $new_password && $confirm_password) {
                    if ($new_password === $confirm_password) {
                        if (strlen($new_password) >= 8) {
                            try {
                                // Verify current password
                                $stmt = $conn->prepare("SELECT password FROM employees WHERE employee_id = ?");
                                $stmt->bind_param("i", $employee_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $user = $result->fetch_assoc();
                                $stmt->close();
                                
                                if ($user && password_verify($current_password, $user['password'])) {
                                    // Update password
                                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                    $stmt = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = ?");
                                    $stmt->bind_param("si", $hashed_password, $employee_id);
                                    if ($stmt->execute()) {
                                        $message = "Password changed successfully!";
                                        $message_type = "success";
                                    } else {
                                        $message = "Failed to change password.";
                                        $message_type = "error";
                                    }
                                    $stmt->close();
                                } else {
                                    $message = "Current password is incorrect.";
                                    $message_type = "error";
                                }
                            } catch (mysqli_sql_exception $e) {
                                $message = "Database error: " . $e->getMessage();
                                $message_type = "error";
                            }
                        } else {
                            $message = "New password must be at least 8 characters long.";
                            $message_type = "error";
                        }
                    } else {
                        $message = "New password and confirmation do not match.";
                        $message_type = "error";
                    }
                } else {
                    $message = "Please fill in all password fields.";
                    $message_type = "error";
                }
                break;
        }
    }
}

// Fetch current employee data
$employee_data = [];
try {
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employee_data = $result->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log("Error fetching employee data: " . $e->getMessage());
}

$activePage = 'settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <link rel="stylesheet" href="../assets/css/profile-edit.css">
    <style>
        .settings-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .settings-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .settings-header h1 {
            margin: 0;
            color: #0077b6;
            font-size: 1.8rem;
        }
        
        .settings-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .tab-button.active {
            color: #0077b6;
            border-bottom-color: #0077b6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #0077b6;
            box-shadow: 0 0 0 2px rgba(0, 119, 182, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-primary {
            background-color: #0077b6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #023e8a;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #495057;
        }
        
        .password-requirements {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
    </style>
</head>
<body>
    <?php
    // Include appropriate sidebar based on role
    if ($employee_role === 'admin') {
        include '../includes/sidebar_admin.php';
    } elseif ($employee_role === 'doctor') {
        include '../includes/sidebar_doctor.php';
    } elseif ($employee_role === 'nurse') {
        include '../includes/sidebar_nurse.php';
    } elseif ($employee_role === 'bhw') {
        include '../includes/sidebar_bhw.php';
    } elseif ($employee_role === 'dho') {
        include '../includes/sidebar_dho.php';
    } else {
        include '../includes/sidebar_admin.php'; // Default fallback
    }
    ?>

    <main class="content-wrapper">
        <div class="settings-container">
            <div class="settings-header">
                <h1><i class="fas fa-cog"></i> Account Settings</h1>
                <p>Manage your account information and security settings</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?>">
                    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="settings-tabs">
                <button class="tab-button active" onclick="showTab('profile')">
                    <i class="fas fa-user"></i> Profile Information
                </button>
                <button class="tab-button" onclick="showTab('password')">
                    <i class="fas fa-lock"></i> Change Password
                </button>
            </div>

            <!-- Profile Information Tab -->
            <div id="profile" class="tab-content active">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   value="<?= htmlspecialchars($employee_data['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   value="<?= htmlspecialchars($employee_data['middle_name'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" 
                               value="<?= htmlspecialchars($employee_data['last_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" 
                               value="<?= htmlspecialchars($employee_data['email'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= htmlspecialchars($employee_data['phone'] ?? '') ?>">
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="admin_profile.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Change Password Tab -->
            <div id="password" class="tab-content">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password *</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <div class="password-requirements">
                            Password requirements:
                            <ul>
                                <li>At least 8 characters long</li>
                                <li>Mix of letters and numbers recommended</li>
                                <li>Avoid common passwords</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Clear form messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>