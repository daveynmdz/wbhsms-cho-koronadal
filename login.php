<?php
/**
 * Main Login Router
 * Redirects users to appropriate login pages based on user type or selection
 */

session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['patient_id'])) {
    header("Location: pages/patient/dashboard.php");
    exit();
}

if (isset($_SESSION['employee_id'])) {
    $role = $_SESSION['role'] ?? 'employee';
    header("Location: pages/management/" . strtolower($role) . "/dashboard.php");
    exit();
}

// Check if user type is specified
$user_type = $_GET['type'] ?? '';

if ($user_type === 'patient') {
    header("Location: pages/patient/auth/patient_login.php");
    exit();
} elseif ($user_type === 'employee') {
    header("Location: pages/management/auth/employee_login.php");
    exit();
}

// Default: Show login selection page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CHO Koronadal</title>
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
            padding: 1rem;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .subtitle {
            color: #6c757d;
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }

        .login-options {
            display: grid;
            gap: 1.5rem;
        }

        .login-option {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 2px solid transparent;
            border-radius: 15px;
            padding: 2rem 1.5rem;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .login-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: #007bff;
            color: #007bff;
        }

        .login-option.patient:hover {
            border-color: #28a745;
            color: #28a745;
        }

        .login-option.employee:hover {
            border-color: #007bff;
            color: #007bff;
        }

        .option-icon {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .option-icon.patient {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .option-content {
            text-align: left;
            flex: 1;
        }

        .option-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .option-description {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .footer {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .footer a {
            color: #007bff;
            text-decoration: none;
            margin: 0 1rem;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: #0056b3;
        }

        @media (max-width: 768px) {
            .login-container {
                padding: 2rem;
                margin: 1rem;
            }

            .login-option {
                padding: 1.5rem 1rem;
                gap: 1rem;
            }

            .option-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-heartbeat"></i>
        </div>
        
        <h1>CHO Koronadal</h1>
        <p class="subtitle">Web-Based Healthcare Services Management System</p>
        
        <div class="login-options">
            <a href="pages/patient/auth/patient_login.php" class="login-option patient">
                <div class="option-icon patient">
                    <i class="fas fa-user"></i>
                </div>
                <div class="option-content">
                    <div class="option-title">Patient Portal</div>
                    <div class="option-description">Book appointments, view medical records, and manage your healthcare</div>
                </div>
            </a>
            
            <a href="pages/management/auth/employee_login.php" class="login-option employee">
                <div class="option-icon employee">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="option-content">
                    <div class="option-title">Employee Portal</div>
                    <div class="option-description">Access staff dashboard, manage patients, and healthcare operations</div>
                </div>
            </a>
        </div>
        
        <div class="footer">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="#"><i class="fas fa-question-circle"></i> Help</a>
            <a href="#"><i class="fas fa-info-circle"></i> About</a>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.querySelectorAll('.login-option').forEach(option => {
            option.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            option.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    </script>
</body>
</html>