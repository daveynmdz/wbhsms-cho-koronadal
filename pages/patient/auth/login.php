<?php
session_start();
require_once '../../config/db.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = trim($_POST['employee_number'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($employee_number) && !empty($password)) {
        $stmt = $conn->prepare("
            SELECT 
                employee_id, 
                employee_number, 
                first_name, 
                middle_name, 
                last_name, 
                password, 
                status,
                role_id
            FROM employees 
            WHERE employee_number = ? 
            LIMIT 1
        ");
        
        if ($stmt) {
            $stmt->bind_param("s", $employee_number);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                if ($row['status'] === 'active' && password_verify($password, $row['password'])) {
                    // Map role_id to role names
                    $role_mapping = [
                        1 => 'admin',
                        2 => 'doctor', 
                        3 => 'nurse',
                        4 => 'laboratory_tech',
                        5 => 'pharmacist',
                        6 => 'cashier',
                        7 => 'records_officer',
                        8 => 'bhw',
                        9 => 'dho'
                    ];
                    
                    $role = $role_mapping[$row['role_id']] ?? 'admin';
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['employee_id'] = $row['employee_id'];
                    $_SESSION['employee_number'] = $row['employee_number'];
                    $_SESSION['employee_first_name'] = $row['first_name'];
                    $_SESSION['employee_middle_name'] = $row['middle_name'];
                    $_SESSION['employee_last_name'] = $row['last_name'];
                    
                    // Create full name
                    $full_name = $row['first_name'];
                    if (!empty($row['middle_name'])) $full_name .= ' ' . $row['middle_name'];
                    $full_name .= ' ' . $row['last_name'];
                    $_SESSION['employee_name'] = trim($full_name);
                    
                    $_SESSION['role'] = $role;
                    $_SESSION['role_id'] = $row['role_id'];
                    $_SESSION['login_time'] = time();
                    
                    // Redirect to dashboard
                    if ($role === 'admin') {
                        header('Location: ../dashboard/dashboard_admin.php');
                        exit();
                    } else {
                        header('Location: ../dashboard/dashboard_' . $role . '.php');
                        exit();
                    }
                } else {
                    $error_message = 'Invalid credentials or inactive account.';
                }
            } else {
                $error_message = 'Invalid credentials.';
            }
            $stmt->close();
        } else {
            $error_message = 'Database error. Please try again.';
        }
    } else {
        $error_message = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - CHO Koronadal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d1edff;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .links {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 10px;
        }

        .links a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
                margin: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-hospital"></i>
        </div>
        
        <h1>Employee Login</h1>
        <p class="subtitle">CHO Koronadal Health Management System</p>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="employee_number">Employee Number</label>
                <div class="input-group">
                    <i class="fas fa-id-badge"></i>
                    <input type="text" 
                           id="employee_number" 
                           name="employee_number" 
                           placeholder="EMP00001"
                           value="<?= htmlspecialchars($_POST['employee_number'] ?? '') ?>"
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           placeholder="Enter your password"
                           required>
                </div>
            </div>
            
            <button type="submit" class="login-btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="links">
            <a href="test_login.php"><i class="fas fa-vial"></i> Test Login</a>
            <a href="test_db_structure.php"><i class="fas fa-database"></i> Check Database</a>
        </div>
    </div>
</body>
</html>