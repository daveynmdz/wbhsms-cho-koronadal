<?php
// dashboard_{ROLE}.php
$root_path = dirname(dirname(dirname(__DIR__)));
require_once $root_path . '/config/session/employee_session.php';

// Set up proper error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start with detailed error tracking
error_log('{ROLE} Dashboard - Initializing...');
error_log('{ROLE} Dashboard - Session Data: ' . print_r($_SESSION, true));

// Validate user session
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['role'])) {
    error_log('{ROLE} Dashboard - Session invalid, employee_id or role not set');
    
    // Clear any redirect attempts that might cause loops
    $_SESSION['redirect_attempts'] = ($_SESSION['redirect_attempts'] ?? 0) + 1;
    
    if ($_SESSION['redirect_attempts'] > 3) {
        // Too many redirects, show an error instead
        error_log('{ROLE} Dashboard - Too many redirect attempts, showing error');
        echo "Error: Session validation failed. Please <a href='{$root_path}/logout.php'>logout</a> and login again.";
        exit();
    }
    
    header('Location: ' . $root_path . '/pages/auth/employee_login.php');
    exit();
}

// Validate correct role
if ($_SESSION['role'] !== '{ROLE}') {
    error_log('{ROLE} Dashboard - Invalid role: ' . $_SESSION['role']);
    
    // Clear any redirect attempts that might cause loops
    $_SESSION['redirect_attempts'] = ($_SESSION['redirect_attempts'] ?? 0) + 1;
    
    if ($_SESSION['redirect_attempts'] > 3) {
        // Too many redirects, show an error instead
        error_log('{ROLE} Dashboard - Too many redirect attempts, showing error');
        echo "Error: You don't have permission to access this page. Please <a href='{$root_path}/logout.php'>logout</a> and login with appropriate credentials.";
        exit();
    }
    
    header('Location: ' . $root_path . '/pages/auth/employee_login.php');
    exit();
}

// Reset redirect counter on successful validation
$_SESSION['redirect_attempts'] = 0;

// Database connection
require_once $root_path . '/config/db.php';
$employee_id = $_SESSION['employee_id'];

// Debug info
error_log('{ROLE} Dashboard - Session validated for employee ID: ' . $employee_id);
error_log('DB Connection Status: MySQLi=' . ($conn ? 'Connected' : 'Failed') . ', PDO=' . ($pdo ? 'Connected' : 'Failed'));

// Fetch employee details
$stmt = $conn->prepare("SELECT employee_number, fullname, email, role, department FROM employees WHERE id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $employee = $result->fetch_assoc();
    $employee_number = $employee['employee_number'];
    $fullname = $employee['fullname'];
    $email = $employee['email'];
    $role = ucwords(str_replace('_', ' ', $employee['role']));
    $department = $employee['department'];
} else {
    error_log('{ROLE} Dashboard - Failed to retrieve employee details for ID: ' . $employee_id);
    $fullname = 'User';
    $employee_number = 'Unknown';
    $email = 'Unknown';
    $role = '{ROLE}';
    $department = '';
}
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{ROLE_TITLE} Dashboard - WBHSMS</title>
    
    <!-- Use absolute paths for all resources -->
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?php echo $root_path; ?>/assets/css/topbar.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* Add your role-specific custom styles here */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 16px;
            color: #555;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #0056b3;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .action-card i {
            font-size: 32px;
            color: #0056b3;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .action-card p {
            color: #777;
            margin: 0;
        }
        
        .content-cards {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .content-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .content-card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #333;
        }
        
        @media (max-width: 992px) {
            .content-cards {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .quick-actions {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>

<body>
    <?php
    // Include the sidebar with the active page set
    $activePage = 'dashboard';
    include $root_path . '/includes/sidebar_{ROLE}.php';
    ?>

    <section class="content-wrapper">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <h1>Welcome, <?php echo htmlspecialchars($fullname); ?>!</h1>
            <p class="subtitle"><?php echo htmlspecialchars($role); ?> • <?php echo htmlspecialchars($department); ?> • ID: <?php echo htmlspecialchars($employee_number); ?></p>
        </div>

        <!-- Statistics Overview -->
        <h2 class="section-title"><i class="fas fa-chart-line"></i> Overview</h2>
        <div class="stats-grid">
            <!-- Customize these statistics for the specific role -->
            <div class="stat-card">
                <h3><i class="fas fa-clipboard-list"></i> Statistic One</h3>
                <div class="stat-value">42</div>
                <p>Description of this statistic</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-calendar-check"></i> Statistic Two</h3>
                <div class="stat-value">128</div>
                <p>Description of this statistic</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Statistic Three</h3>
                <div class="stat-value">856</div>
                <p>Description of this statistic</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-chart-bar"></i> Statistic Four</h3>
                <div class="stat-value">23</div>
                <p>Description of this statistic</p>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="quick-actions">
            <!-- Customize these actions for the specific role -->
            <div class="action-card">
                <i class="fas fa-plus-circle"></i>
                <h3>Action One</h3>
                <p>Description of this action</p>
            </div>
            <div class="action-card">
                <i class="fas fa-search"></i>
                <h3>Action Two</h3>
                <p>Description of this action</p>
            </div>
            <div class="action-card">
                <i class="fas fa-clipboard"></i>
                <h3>Action Three</h3>
                <p>Description of this action</p>
            </div>
            <div class="action-card">
                <i class="fas fa-cog"></i>
                <h3>Action Four</h3>
                <p>Description of this action</p>
            </div>
        </div>

        <!-- Content Cards -->
        <div class="content-cards">
            <div class="content-card">
                <h2><i class="fas fa-list"></i> Main Content</h2>
                <p>This is the main content area where you can display tables, lists, or other important information specific to this role.</p>
                
                <!-- Add role-specific content here -->
                
            </div>
            <div class="content-card">
                <h2><i class="fas fa-bell"></i> Notifications</h2>
                <p>This is a sidebar area for notifications, alerts, or other secondary information.</p>
                
                <!-- Add notifications or secondary content here -->
                
            </div>
        </div>
    </section>

    <!-- Include any JavaScript files -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        // Any dashboard-specific JavaScript can go here
        document.addEventListener('DOMContentLoaded', function() {
            console.log('{ROLE} Dashboard loaded successfully');
            
            // Example: Add click handlers to action cards
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('click', function() {
                    const action = this.querySelector('h3').textContent;
                    console.log(`Action selected: ${action}`);
                    // You can add navigation or modal triggers here
                });
            });
        });
    </script>
</body>
</html>